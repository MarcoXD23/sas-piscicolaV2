<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Persona externa de la finca, sin cuenta en la app: jornalero (y más adelante cliente o proveedor), en una sola tabla.
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
            'anonimizado_at' => 'immutable_datetime',
        ];
    }

    /** @return array<string, string> código => texto */
    public static function categorias(): array
    {
        return collect(self::CATEGORIAS)->mapWithKeys(fn (string $codigo) => [$codigo => __('jornaleros.categorias.'.$codigo)])->all();
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

    public function scopeActivos(Builder $consulta): void
    {
        $consulta->where('activo', true)->whereNull('anonimizado_at');
    }
}
