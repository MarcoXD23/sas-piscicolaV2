<?php

namespace App\Support\Datos;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\FincaEnEliminacion;
use App\Support\TenantActual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Eliminación de una finca en dos tiempos:
 * 1. solicitar(): queda suspendida y «en eliminación» durante el periodo de gracia (se puede cancelar);
 * 2. eliminar(): al vencer el plazo, borra TODOS sus datos (en una transacción) y sus archivos.
 * Usa DB::table con where tenant_id explícito (no depende del filtro de finca) y borra la bitácora solo con
 * la función borrar_bitacora_finca(), que exige el plazo vencido.
 */
final class EliminadorFinca
{
    public static function solicitar(Tenant $finca, ?User $quien = null): void
    {
        DB::transaction(function () use ($finca, $quien) {
            $ahora = now();
            $finca->forceFill([
                'eliminacion_solicitada_at' => $ahora,
                'eliminacion_programada_at' => $ahora->copy()->addDays(config('datos.dias_gracia')),
                // Si ya estaba suspendida por otro motivo, esa suspensión se conserva al cancelar
                'suspendida_por_eliminacion' => $finca->suspendida_at === null,
                'suspendida_at' => $finca->suspendida_at ?? $ahora,
            ])->save();

            // Se cortan los accesos: tokens de la API y sesiones (salvo la de quien lo pidió, para que pueda cancelar)
            $usuarios = DB::table('users')->where('tenant_id', $finca->id)->pluck('id');
            DB::table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())->whereIn('tokenable_id', $usuarios)->delete();
            DB::table(config('session.table', 'sessions'))->whereIn('user_id', $usuarios)
                ->when($quien && request()->hasSession(), fn ($q) => $q->where('id', '!=', request()->session()->getId()))->delete();
        });

        // Aviso al propietario con la fecha (si el correo está configurado)
        $propietarios = TenantActual::sinFiltro(fn () => User::query()->where('tenant_id', $finca->id)
            ->whereHas('roles', fn ($q) => $q->where('nombre', Rol::PROPIETARIO))->get());
        Notification::send($propietarios, new FincaEnEliminacion($finca->fresh()));
        Log::warning('Finca en eliminación', ['tenant_id' => $finca->id, 'programada' => $finca->eliminacion_programada_at?->toIso8601String()]);
    }

    public static function cancelar(Tenant $finca): void
    {
        $finca->forceFill([
            'eliminacion_solicitada_at' => null,
            'eliminacion_programada_at' => null,
            'suspendida_at' => $finca->suspendida_por_eliminacion ? null : $finca->suspendida_at,
            'suspendida_por_eliminacion' => false,
        ])->save();
        Log::info('Eliminación de finca cancelada', ['tenant_id' => $finca->id]);
    }

    /** Borra definitivamente todo lo de la finca (solo con el plazo vencido). Devuelve filas borradas por tabla. */
    public static function eliminar(Tenant $finca): array
    {
        if (! $finca->enEliminacion() || $finca->eliminacion_programada_at->isFuture()) {
            throw new RuntimeException("La finca {$finca->id} no tiene vencido su plazo de eliminación.");
        }

        $id = $finca->id;
        $borradas = DB::transaction(function () use ($id) {
            DB::table('tenants')->where('id', $id)->lockForUpdate()->first();
            $usuarios = DB::table('users')->where('tenant_id', $id)->get(['id', 'email']);
            $conteo = [];

            // Lo que no lleva tenant_id: tokens, sesiones y enlaces de recuperación de esas personas
            $conteo['personal_access_tokens'] = DB::table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())
                ->whereIn('tokenable_id', $usuarios->pluck('id'))->delete();
            $conteo['sessions'] = DB::table(config('session.table', 'sessions'))->whereIn('user_id', $usuarios->pluck('id'))->delete();
            $conteo['password_reset_tokens'] = DB::table('password_reset_tokens')->whereIn('email', $usuarios->pluck('email'))->delete();

            // Tablas de la finca, de hijas a madres; la bitácora solo con su función
            foreach (TablasFinca::ordenDeBorrado() as $tabla) {
                $conteo[$tabla] = $tabla === 'bitacora'
                    ? (int) DB::selectOne('SELECT borrar_bitacora_finca(?) AS borradas', [$id])->borradas
                    : DB::table($tabla)->where('tenant_id', $id)->delete();
            }
            $conteo['tenants'] = DB::table('tenants')->where('id', $id)->delete();

            // Comprobación: no queda nada de la finca (si queda, se deshace todo)
            foreach (TablasFinca::todas() as $tabla) {
                if (DB::table($tabla)->where('tenant_id', $id)->exists()) {
                    throw new RuntimeException("Quedaron filas de la finca {$id} en {$tabla}.");
                }
            }

            return $conteo;
        });

        // Ya confirmado: archivos de la finca y registro (fuera de la base) para volver a borrarla si se restaura una copia vieja
        Storage::disk('fotos')->deleteDirectory((string) $id);
        Storage::disk('exportaciones')->deleteDirectory((string) $id);
        self::anotarEliminada($id);
        Log::warning('Finca eliminada definitivamente', ['tenant_id' => $id, 'filas' => array_sum($borradas)]);

        return $borradas;
    }

    /** @return list<int> Fincas ya eliminadas (del archivo fuera de la base). */
    public static function eliminadas(): array
    {
        $disco = Storage::disk('local');
        $archivo = config('datos.archivo_eliminadas');

        return $disco->exists($archivo) ? array_map('intval', array_column(json_decode($disco->get($archivo), true) ?: [], 'tenant_id')) : [];
    }

    private static function anotarEliminada(int $id): void
    {
        $disco = Storage::disk('local');
        $archivo = config('datos.archivo_eliminadas');
        $lista = $disco->exists($archivo) ? (json_decode($disco->get($archivo), true) ?: []) : [];
        if (! in_array($id, array_column($lista, 'tenant_id'), true)) {
            $lista[] = ['tenant_id' => $id, 'eliminada_at' => now()->utc()->toIso8601String()];
            $disco->put($archivo, json_encode($lista, JSON_PRETTY_PRINT));
        }
    }
}
