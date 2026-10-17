<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Persona o empresa externa de la finca, sin cuenta en la app: jornalero, proveedor (y más adelante cliente), en una
 * sola tabla. Un mismo tercero puede ser jornalero y proveedor.
 * - El proveedor dice qué provee (provee: alevinos, alimento, otros; uno o varios). es_proveedor queda en true mientras
 *   provea algo (asignarProvee), para filtrar rápido.
 * - El documento se guarda cifrado con la clave de la app (APP_KEY) y nunca en texto claro. Para no repetirlo dentro
 *   de la finca se guarda además una huella: HMAC-SHA256 del documento normalizado con una clave derivada de APP_KEY
 *   (no se puede adivinar probando documentos sin esa clave).
 * - El administrador ve solo los últimos 4 dígitos (documentoEnmascarado); el propietario, el documento completo.
 * - Anonimizar borra documento y teléfono y cambia el nombre por «Jornalero N»: como la bitácora y las ventas
 *   resuelven el nombre por su id, también quedan anonimizadas. No se puede deshacer.
 */
class Tercero extends Model
{
    use PerteneceATenant;

    /** Categorías de trabajo de un jornalero (no son roles de la app). */
    public const CATEGORIAS = ['procesador', 'pescador', 'rayador', 'lavado_canastas', 'otro'];

    /** Qué puede proveer un proveedor (texto validado en la aplicación, sin enum). */
    public const PROVEE_ALEVINOS = 'alevinos';

    public const PROVEE_ALIMENTO = 'alimento';

    public const PROVEE_OTROS = 'otros';

    public const PROVEE = [self::PROVEE_ALEVINOS, self::PROVEE_ALIMENTO, self::PROVEE_OTROS];

    protected $table = 'terceros';

    protected $fillable = ['nombres', 'apellidos', 'telefono', 'es_jornalero', 'es_cliente', 'es_proveedor', 'categoria_habitual', 'activo'];

    /** Nunca sale en JSON ni en arreglos: ni el documento (aunque descifrado) ni su huella. */
    protected $hidden = ['documento', 'documento_huella'];

    protected function casts(): array
    {
        return [
            'documento' => 'encrypted',
            'es_jornalero' => 'boolean',
            'es_cliente' => 'boolean',
            'es_proveedor' => 'boolean',
            'activo' => 'boolean',
            'provee' => 'array',
            'anonimizado_at' => 'immutable_datetime',
        ];
    }

    /** @return array<string, string> código => texto */
    public static function categorias(): array
    {
        return collect(self::CATEGORIAS)->mapWithKeys(fn (string $codigo) => [$codigo => __('jornaleros.categorias.'.$codigo)])->all();
    }

    /** @return array<string, string> código => texto de lo que puede proveer */
    public static function opcionesProvee(): array
    {
        return collect(self::PROVEE)->mapWithKeys(fn (string $codigo) => [$codigo => __('proveedores.provee.'.$codigo)])->all();
    }

    /** Asigna qué provee (en el orden de PROVEE, sin repetir ni códigos desconocidos) y mantiene es_proveedor al día. */
    public function asignarProvee(array $provee): void
    {
        $provee = array_values(array_intersect(self::PROVEE, $provee));
        $this->provee = $provee === [] ? null : $provee;
        $this->es_proveedor = $provee !== [];
    }

    public function proveeDe(string $codigo): bool
    {
        return in_array($codigo, (array) $this->provee, true);
    }

    /** Lo que provee, en texto («Alevinos, Alimento»), o null si no provee nada. */
    public function textoProvee(): ?string
    {
        $provee = array_values(array_intersect(self::PROVEE, (array) $this->provee));

        return $provee === [] ? null : implode(', ', array_map(fn (string $codigo) => __('proveedores.provee.'.$codigo), $provee));
    }

    /** Partículas de nombres y apellidos que van en minúscula (salvo al inicio del campo). */
    private const PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'y', 'e'];

    /**
     * Nombres o apellidos con mayúscula inicial en cada palabra, al guardar: «tereza gonzale» → «Tereza Gonzale»,
     * «PEREZ» → «Perez», «maría de la cruz» → «María de la Cruz», «gómez-pérez» → «Gómez-Pérez», «o'neil» → «O'Neil».
     * Respeta tildes y eñes. Las iniciales quedan en mayúscula («j. pérez» → «J. Pérez», «J.C.» igual) y las partículas
     * (de, del, la, las, los, y, e) en minúscula salvo al inicio. Quita espacios repetidos.
     */
    public static function formatearNombre(?string $texto): ?string
    {
        $palabras = preg_split('/\s+/u', trim((string) $texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($palabras === []) {
            return null;
        }

        return implode(' ', array_map(function (string $palabra, int $posicion): string {
            $minuscula = mb_strtolower($palabra);
            // Iniciales: una letra sola, o letras con punto («J.», «J.C.»)
            if (preg_match('/^(\p{L}\.)+$|^\p{L}$/u', $palabra)) {
                return $posicion > 0 && in_array($minuscula, self::PARTICULAS, true) ? $minuscula : mb_strtoupper($palabra);
            }
            if ($posicion > 0 && in_array($minuscula, self::PARTICULAS, true)) {
                return $minuscula;
            }

            // Mayúscula al inicio y después de un guion o un apóstrofo («Gómez-Pérez», «O'Neil»)
            return preg_replace_callback("/(^|[-'’])(\\p{L})/u", fn (array $m) => $m[1].mb_strtoupper($m[2]), $minuscula);
        }, $palabras, array_keys($palabras)));
    }

    /** Documento sin espacios, puntos ni guiones, en mayúsculas («1.234.567-8» y «12345678» son el mismo). */
    public static function normalizarDocumento(?string $documento): ?string
    {
        $limpio = strtoupper((string) preg_replace('/[\s.\-]/', '', (string) $documento));

        return $limpio === '' ? null : $limpio;
    }

    /** Huella del documento: HMAC-SHA256 con una clave derivada de APP_KEY (solo para comparar, no se puede revertir). */
    public static function huella(?string $documento): ?string
    {
        $normalizado = self::normalizarDocumento($documento);

        return $normalizado === null ? null : hash_hmac('sha256', $normalizado, self::claveHuella());
    }

    private static function claveHuella(): string
    {
        return hash_hmac('sha256', 'terceros.documento_huella', (string) config('app.key'), true);
    }

    /** Asigna (o quita, con null o vacío) el documento: cifrado y con su huella. */
    public function asignarDocumento(?string $documento): void
    {
        $normalizado = self::normalizarDocumento($documento);
        $this->documento = $normalizado;
        $this->documento_huella = self::huella($normalizado);
    }

    /** Últimos 4 caracteres del documento, con el resto oculto; null si no tiene. */
    public function documentoEnmascarado(): ?string
    {
        $documento = $this->documento;

        return $documento === null ? null : '•••• '.mb_substr($documento, -4);
    }

    public function nombreCompleto(): string
    {
        return $this->anonimizado_at !== null
            ? __('jornaleros.anonimo', ['id' => $this->id])
            : trim($this->nombres.' '.($this->apellidos ?? ''));
    }

    public function anonimizado(): bool
    {
        return $this->anonimizado_at !== null;
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /** Saldo por cobrar de sus compras a crédito. */
    public function saldoPorCobrar(): int
    {
        return (int) $this->ventas()->conSaldo()->sum('saldo');
    }

    /**
     * Borra sus datos personales y deja solo «Jornalero N». Las ventas guardan el nombre de ese día en «cliente»:
     * también se reemplaza. Llamar dentro de una transacción, después de comprobar que no tiene saldo por cobrar.
     */
    public function anonimizar(): void
    {
        $this->forceFill([
            'nombres' => __('jornaleros.anonimo', ['id' => $this->id]),
            'apellidos' => null,
            'telefono' => null,
            'activo' => false,
            'anonimizado_at' => now(),
        ]);
        $this->asignarDocumento(null);
        $this->save();

        DB::table('ventas')->where('tenant_id', $this->tenant_id)->where('tercero_id', $this->id)->update(['cliente' => $this->nombreCompleto()]);
    }

    public function scopeJornaleros(Builder $consulta): void
    {
        $consulta->where('es_jornalero', true);
    }

    public function scopeProveedores(Builder $consulta): void
    {
        $consulta->where('es_proveedor', true);
    }

    /** Proveedores que proveen algo en particular (alevinos, alimento u otros). */
    public function scopeQueProveen(Builder $consulta, string $codigo): void
    {
        $consulta->where('es_proveedor', true)->whereJsonContains('provee', $codigo);
    }

    public function scopeActivos(Builder $consulta): void
    {
        $consulta->where('activo', true)->whereNull('anonimizado_at');
    }
}
