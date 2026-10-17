<?php

namespace App\Support;

use App\Models\Cosecha;
use App\Models\Siembra;
use App\Models\Traslado;
use Illuminate\Support\Collection;

/**
 * Comparación de proveedores de alevinos (F5): crecimiento, supervivencia y conversión de todos sus lotes (activos y
 * finalizados). Cada lote cuenta para su proveedor; el lote aparte que nace de un traslado lo hereda del origen.
 *
 * - Supervivencia = (vivos + cosechados + trasladados afuera) ÷ (sembrados + recibidos), sumando los lotes del grupo.
 *   Los traslados entre lotes del mismo grupo se cancelan (salen de uno y entran a otro), para no contarlos dos veces.
 *   Un lote finalizado ya no tiene vivos: lo que quedó al cerrarlo sin cosechar cuenta como pérdida.
 * - Crecimiento = gramos ganados por día de vida (del peso al sembrar al último peso conocido), promedio ponderado por
 *   los peces de cada lote.
 * - Conversión = alimento ÷ biomasa ganada, sumando los de cada lote (el FCA propio; los traslados entre lotes ya se
 *   restan en el lote que los recibe, así que nada se cuenta dos veces).
 * - Grupos: cada proveedor del catálogo; las siembras viejas con el proveedor solo escrito a mano («sin ligar», se ligan
 *   en Proveedores › Revisión); y las que no tienen proveedor.
 * - «Mezcla»: algún lote del grupo recibió peces de un lote de otro grupo; sus cifras incluyen esos peces.
 */
final class ComparacionProveedores
{
    public const SIN_LIGAR = 'sin_ligar';

    public const SIN_PROVEEDOR = 'sin_proveedor';

    public function __construct(
        private readonly ConversionAlimenticia $conversion = new ConversionAlimenticia,
        private ?ConversionVida $vida = null,
    ) {
        $this->vida ??= new ConversionVida($this->conversion);
    }

    /**
     * @return Collection<int, array{clave: string, nombre: ?string, lotes: int, alevinos: int, mezcla: bool,
     *                                supervivencia: ?float, crecimiento_g_dia: ?float, alimento_kg: float, ganancia_kg: float, fca: ?float, motivo: ?string}>
     */
    public function filas(): Collection
    {
        $siembras = Siembra::query()->with(['proveedor', 'trasladosRecibidos'])->orderBy('fecha')->orderBy('id')->get()->keyBy('id');
        if ($siembras->isEmpty()) {
            return collect();
        }

        $grupoDe = $siembras->map(fn (Siembra $siembra) => $this->grupo($siembra));
        $traslados = Traslado::query()->get();
        $cosechados = Cosecha::query()->whereNotNull('siembra_id')->groupBy('siembra_id')->selectRaw('siembra_id, SUM(peces) AS peces')->pluck('peces', 'siembra_id');

        return $siembras->groupBy(fn (Siembra $siembra) => $grupoDe[$siembra->id], preserveKeys: true)
            ->map(function (Collection $lotes, string $clave) use ($grupoDe, $traslados, $cosechados) {
                $ids = $lotes->keys()->all();
                $entradas = 0;
                $sobrevivientes = 0;
                $mezcla = false;

                foreach ($lotes as $lote) {
                    $entradas += $lote->vinoDeTraslado() ? 0 : (int) $lote->cantidad_alevinos;
                    $sobrevivientes += ($lote->estaActiva() ? (int) $lote->peces_vivos : 0) + (int) ($cosechados[$lote->id] ?? 0);
                }
                foreach ($traslados as $traslado) {
                    $sale = in_array($traslado->siembra_origen_id, $ids, true);
                    $entra = in_array($traslado->siembra_destino_id, $ids, true);
                    if ($sale && ! $entra) {
                        $sobrevivientes += (int) $traslado->cantidad;   // trasladados afuera del grupo
                    } elseif ($entra && ! $sale) {
                        $entradas += (int) $traslado->cantidad;         // recibidos de otro grupo
                        $mezcla = true;
                    }
                }

                $alimento = 0.0;
                $ganancia = 0.0;
                $conPeso = false;
                $gramosPorPez = 0.0;
                $pecesCrecimiento = 0;
                foreach ($lotes as $lote) {
                    $fca = $this->conversion->deSiembra($lote);
                    $alimento += $fca['alimento_kg'];
                    $ganancia += $fca['ganancia_kg'];
                    $conPeso = $conPeso || $fca['motivo'] !== ConversionAlimenticia::SIN_PESO;

                    $crecimiento = $this->crecimiento($lote);
                    if ($crecimiento !== null) {
                        $gramosPorPez += $crecimiento * (int) $lote->cantidad_alevinos;
                        $pecesCrecimiento += (int) $lote->cantidad_alevinos;
                    }
                }
                $alimento = round($alimento, 3);
                $ganancia = round($ganancia, 3);
                $motivo = ! $conPeso ? ConversionAlimenticia::SIN_PESO : ($ganancia <= 0 ? ConversionAlimenticia::SIN_GANANCIA : null);
                $primero = $lotes->first();

                return [
                    'clave' => $clave,
                    'nombre' => match ($clave) {
                        self::SIN_LIGAR, self::SIN_PROVEEDOR => null,
                        default => $primero->nombreProveedor(),
                    },
                    'lotes' => $lotes->count(),
                    'alevinos' => (int) $lotes->reject(fn (Siembra $lote) => $lote->vinoDeTraslado())->sum('cantidad_alevinos'),
                    'mezcla' => $mezcla,
                    'supervivencia' => $entradas > 0 ? round(min(100.0, $sobrevivientes / $entradas * 100), 1) : null,
                    'crecimiento_g_dia' => $pecesCrecimiento > 0 ? round($gramosPorPez / $pecesCrecimiento, 2) : null,
                    'alimento_kg' => $alimento,
                    'ganancia_kg' => $ganancia,
                    'fca' => $motivo === null ? round($alimento / $ganancia, 2) : null,
                    'motivo' => $motivo,
                ];
            })
            // Primero los proveedores del catálogo por nombre; al final los sin ligar y los sin proveedor
            ->sortBy(fn (array $fila) => match ($fila['clave']) {
                self::SIN_LIGAR => "\u{FFFF}1",
                self::SIN_PROVEEDOR => "\u{FFFF}2",
                default => mb_strtolower((string) $fila['nombre']),
            })
            ->values();
    }

    /** Grupo del lote: «p<id>» del proveedor del catálogo, o sin ligar, o sin proveedor. */
    private function grupo(Siembra $siembra): string
    {
        return match (true) {
            $siembra->proveedor_id !== null => 'p'.$siembra->proveedor_id,
            filled($siembra->proveedor_alevinos) => self::SIN_LIGAR,
            default => self::SIN_PROVEEDOR,
        };
    }

    /** Gramos ganados por día de vida: del peso al sembrar (en la siembra original) al último peso conocido del lote. */
    private function crecimiento(Siembra $siembra): ?float
    {
        $ultimo = $this->conversion->ultimoPeso($siembra);
        $raiz = $this->vida->raiz($siembra);
        $dias = (int) \Carbon\CarbonImmutable::parse($raiz->fecha->toDateString(), 'UTC')->diffInDays(\Carbon\CarbonImmutable::parse($ultimo['fecha'], 'UTC'), false);
        if ($dias <= 0) {
            return null;
        }

        return ($ultimo['gramos'] - (float) $raiz->peso_promedio_inicial_g) / $dias;
    }
}
