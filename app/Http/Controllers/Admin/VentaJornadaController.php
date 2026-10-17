<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Jornada;
use App\Models\Obsequio;
use App\Models\PrecioEspecie;
use App\Models\Presentacion;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tercero;
use App\Models\User;
use App\Models\Venta;
use App\Support\Ajustes\AjustesFinca;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\Formato;
use App\Support\Proceso\Ayuno;
use App\Support\Ventas\Cupo;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Venta rápida y obsequios de la jornada (docs/diseno/dia-de-proceso.md, temas 8 y 9). Solo con la jornada abierta y
 * desde su día, con el mismo bloqueo que los pesajes (Jornada::bloquearAbierta). Las registra el administrador.
 * - La venta va por especie, sin elegir lago. El comprador es un particular (nombre escrito), un fijo (persona con
 *   cuenta) o un jornalero (la lista de Jornaleros). El precio sale de Ajustes › Precios y no se puede cambiar; a fijos
 *   y jornaleros se les aplica el cupo (App\Support\Ventas\Cupo).
 * - Forma de pago: fijos y jornaleros, «descontar del pago» (crédito) o contado; particulares, contado o crédito. Sin
 *   elegir, se usa la de la última compra de esa persona. De contado se anota quién recibió el dinero.
 * - Obsequio: quien lo registra elige quién lo autorizó (ajuste obsequios.autoriza) y nunca puede ser él mismo.
 */
class VentaJornadaController extends Controller implements HasMiddleware
{
    public const PARTICULAR = 'particular';

    public const FIJO = 'fijo';

    public const JORNALERO = 'jornalero';

    public const TIPOS = [self::PARTICULAR, self::FIJO, self::JORNALERO];

    /** Forma de pago sin elegir: la de la última compra de la persona. */
    public const LA_DE_SIEMPRE = 'ultima';

    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $request, Closure $next) {
                abort_unless(Ayuno::jornadasEncendidas(), 404);

                return $next($request);
            }),
        ];
    }

    public function vender(Request $request, Jornada $jornada): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $datos = $request->validate([
            'especie_id' => ['required', 'integer'],
            'tipo_comprador' => ['required', Rule::in(self::TIPOS)],
            'cliente' => ['nullable', 'required_if:tipo_comprador,'.self::PARTICULAR, 'string', 'max:255'],
            'comprador_id' => ['nullable', 'required_if:tipo_comprador,'.self::FIJO, 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)->whereNotNull('aprobado_at')],
            'tercero_id' => ['nullable', 'required_if:tipo_comprador,'.self::JORNALERO, 'integer', Rule::exists('terceros', 'id')->where('tenant_id', $tenantId)
                ->where('es_jornalero', true)->where('activo', true)->whereNull('anonimizado_at')],
            'kg' => ['required', 'numeric', 'min:0.1', 'max:100000'],
            'forma_pago' => ['nullable', Rule::in([...Venta::FORMAS_PAGO, self::LA_DE_SIEMPRE])],
            'recibio_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)->whereNotNull('aprobado_at')],
        ], [
            'especie_id.required' => __('jornadas.errores.elige_especie'),
            'cliente.required_if' => __('ventas.rapida.errores.escribe_cliente'),
            'comprador_id.required_if' => __('ventas.rapida.errores.elige_fijo'),
            'comprador_id.exists' => __('mensajes.venta.ese_empleado_no_pertenece_finca'),
            'tercero_id.required_if' => __('ventas.rapida.errores.elige_jornalero'),
            'tercero_id.exists' => __('mensajes.venta.ese_jornalero_no_esta_activo'),
        ]);

        $especieId = (int) $datos['especie_id'];
        if (! $this->especiesDe($jornada)->has($especieId)) {
            throw ValidationException::withMessages(['especie_id' => __('ventas.rapida.errores.especie_no_esta')]);
        }
        $precio = PrecioEspecie::query()->where('especie_id', $especieId)->first();
        if ($precio === null) {
            throw ValidationException::withMessages(['especie_id' => __('ventas.rapida.errores.sin_precio')]);
        }
        $fijo = $datos['tipo_comprador'] === self::FIJO ? User::find($datos['comprador_id']) : null;
        $jornalero = $datos['tipo_comprador'] === self::JORNALERO ? Tercero::find($datos['tercero_id']) : null;
        $forma = $this->formaPago($datos['forma_pago'] ?? null, $fijo, $jornalero);
        $recibio = $forma === 'contado' ? $this->recibio($datos['recibio_id'] ?? null) : null;

        $venta = DB::transaction(function () use ($request, $jornada, $datos, $especieId, $precio, $fijo, $jornalero, $forma, $recibio) {
            $bloqueada = Jornada::bloquearAbierta($jornada->id);
            if (! $bloqueada->yaEsSuDia()) {
                throw ValidationException::withMessages(['jornada' => __('jornadas.errores.antes_de_su_dia')]);
            }
            // El cupo se cuenta con la fecha de la jornada (día del calendario de la finca)
            $partes = Cupo::repartir((float) $datos['kg'], $precio, $fijo, $jornalero, $bloqueada->fecha);
            $venta = Venta::create([
                'jornada_id' => $bloqueada->id, 'especie_id' => $especieId, 'cosecha_id' => null,
                'user_id' => $request->user()->id, 'comprador_id' => $fijo?->id, 'tercero_id' => $jornalero?->id,
                'cliente' => $fijo?->name ?? $jornalero?->nombreCompleto() ?? trim((string) $datos['cliente']),
                'fecha' => $bloqueada->fecha->toDateString(), 'kg' => round((float) $datos['kg'], 2),
                'precio_kg' => $partes['precio_general'], 'kg_cupo' => $partes['precio_cupo'] !== null ? $partes['kg_cupo'] : null,
                'precio_cupo' => $partes['precio_cupo'], 'total' => $partes['total'],
                'forma_pago' => $forma, 'saldo' => $forma === 'credito' ? $partes['total'] : 0, 'recibio_id' => $recibio?->id,
            ]);
            // A un jornalero, la bitácora guarda solo su id: el nombre se resuelve al mostrarla (y se anonimiza con él)
            Bitacora::registrar($request->user(), 'venta', null, [Rol::ADMINISTRADOR], array_filter([
                'kg' => (float) $venta->kg, 'cliente' => $jornalero ? null : $venta->cliente, 'total' => Formato::dinero($venta->total),
                'forma_pago' => __('ventas.formas_pago.'.$venta->forma_pago), 'fecha' => $venta->fecha->toDateString(),
            ], fn ($valor) => $valor !== null), tercero: $jornalero);

            return $venta;
        });

        return back()->with('estado', __($venta->kg_cupo !== null && $venta->kg_cupo > 0 && $venta->kgExceso() > 0 ? 'ventas.rapida.registrada_con_exceso' : 'ventas.rapida.registrada', [
            'cliente' => $venta->nombreCliente(), 'kg' => Formato::sinCeros($venta->kg), 'total' => Formato::dinero($venta->total),
            'cupo' => Formato::sinCeros((float) $venta->kg_cupo), 'exceso' => Formato::sinCeros($venta->kgExceso()),
        ]));
    }

    public function obsequiar(Request $request, Jornada $jornada): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $datos = $request->validate([
            'especie_id' => ['required', 'integer'],
            'presentacion_id' => ['nullable', 'integer', Rule::exists('presentaciones', 'id')->where('tenant_id', $tenantId)],
            'kg' => ['required', 'numeric', 'min:0.1', 'max:100000'],
            'destinatario' => ['required', 'string', 'max:120'],
            'nota' => ['nullable', 'string', 'max:300'],
            'autorizado_por' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)->whereNotNull('aprobado_at')],
        ], [
            'especie_id.required' => __('jornadas.errores.elige_especie'),
            'destinatario.required' => __('obsequios.errores.escribe_destinatario'),
            'autorizado_por.required' => __('obsequios.errores.elige_autoriza'),
        ]);

        $especieId = (int) $datos['especie_id'];
        if (! $this->especiesDe($jornada)->has($especieId)) {
            throw ValidationException::withMessages(['especie_id' => __('obsequios.errores.especie_no_esta')]);
        }
        if (! empty($datos['presentacion_id']) && (int) Presentacion::query()->whereKey($datos['presentacion_id'])->value('especie_id') !== $especieId) {
            throw ValidationException::withMessages(['presentacion_id' => __('obsequios.errores.presentacion_otra_especie')]);
        }
        $autoriza = User::findOrFail($datos['autorizado_por']);
        if ($autoriza->id === $request->user()->id) {
            throw ValidationException::withMessages(['autorizado_por' => __('obsequios.errores.no_a_si_mismo')]);
        }
        if (! self::puedeAutorizar($autoriza)) {
            throw ValidationException::withMessages(['autorizado_por' => __('obsequios.errores.no_puede_autorizar', ['nombre' => $autoriza->name])]);
        }

        $obsequio = DB::transaction(function () use ($request, $jornada, $datos, $especieId, $autoriza) {
            $bloqueada = Jornada::bloquearAbierta($jornada->id);
            if (! $bloqueada->yaEsSuDia()) {
                throw ValidationException::withMessages(['jornada' => __('jornadas.errores.antes_de_su_dia')]);
            }
            $obsequio = Obsequio::create([
                'jornada_id' => $bloqueada->id, 'especie_id' => $especieId, 'presentacion_id' => $datos['presentacion_id'] ?? null,
                'kg' => round((float) $datos['kg'], 2), 'destinatario' => trim($datos['destinatario']), 'nota' => $datos['nota'] ?? null,
                'registrado_por' => $request->user()->id, 'autorizado_por' => $autoriza->id,
            ]);
            Bitacora::registrar($request->user(), 'obsequio', null, [Rol::ADMINISTRADOR], [
                'kg' => (float) $obsequio->kg, 'especie' => $obsequio->especie->nombre, 'destinatario' => $obsequio->destinatario,
                'autorizo' => $autoriza->name, 'fecha' => $bloqueada->fecha->toDateString(),
            ]);

            return $obsequio;
        });

        return back()->with('estado', __('obsequios.registrado', ['kg' => Formato::sinCeros($obsequio->kg), 'destinatario' => $obsequio->destinatario]));
    }

    /** Puede autorizar obsequios según el ajuste: el propietario siempre; el administrador, si la finca lo permite. */
    public static function puedeAutorizar(User $usuario): bool
    {
        $roles = AjustesFinca::valor(CatalogoAjustes::OBSEQUIOS_AUTORIZA) === CatalogoAjustes::AUTORIZA_PROPIETARIO_O_ADMINISTRADOR
            ? [Rol::PROPIETARIO, Rol::ADMINISTRADOR] : [Rol::PROPIETARIO];

        return $usuario->tieneRol(...$roles);
    }

    /**
     * Especies que se pueden vender u obsequiar en la jornada: las que tienen siembra activa o entero pesado en sus
     * lagos (especie_id => nombre).
     *
     * @return Collection<int, string>
     */
    public static function especiesDe(Jornada $jornada): Collection
    {
        $jornada->loadMissing(['lagos.lago.siembrasActivas', 'lagos.pesajes.especie']);
        $especies = collect();
        foreach ($jornada->lagos as $jornadaLago) {
            foreach ($jornadaLago->lago->siembrasActivas as $siembra) {
                /** @var Siembra $siembra */
                if ($siembra->especie_id !== null) {
                    $especies[(int) $siembra->especie_id] = $siembra->nombre_especie;
                }
            }
            foreach ($jornadaLago->pesajes as $pesaje) {
                $especies[(int) $pesaje->especie_id] = $pesaje->especie?->nombre;
            }
        }

        return $especies->sort();
    }

    /** Forma de pago elegida, o la de la última compra de esa persona; sin compras, crédito a fijos y jornaleros. */
    private function formaPago(?string $elegida, ?User $fijo, ?Tercero $jornalero): string
    {
        if ($elegida !== null && $elegida !== self::LA_DE_SIEMPRE) {
            return $elegida;
        }
        if ($fijo === null && $jornalero === null) {
            return 'contado';
        }
        $ultima = Venta::query()
            ->when($fijo, fn ($q) => $q->where('comprador_id', $fijo->id))
            ->when($jornalero, fn ($q) => $q->where('tercero_id', $jornalero->id))
            ->latest('fecha')->latest('id')->value('forma_pago');

        return $ultima ?? 'credito';
    }

    /** De contado se anota quién recibió el dinero (para cuadrar la caja del día). */
    private function recibio(mixed $id): User
    {
        if (empty($id)) {
            throw ValidationException::withMessages(['recibio_id' => __('ventas.rapida.errores.quien_recibio')]);
        }

        return User::findOrFail($id);
    }
}
