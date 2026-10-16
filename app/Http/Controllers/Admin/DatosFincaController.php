<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerarExportacion;
use App\Models\Bitacora;
use App\Models\Exportacion;
use App\Models\Rol;
use App\Support\Datos\EliminadorFinca;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Datos de la finca (solo el propietario): exportarlos en un ZIP y pedir o cancelar la eliminación de la finca.
 */
class DatosFincaController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.datos.index', [
            'finca' => $request->user()->tenant,
            'exportaciones' => Exportacion::query()->latest()->limit(5)->get(),
        ]);
    }

    /** Una exportación por hora; se genera en segundo plano y queda en la bitácora. */
    public function exportar(Request $request): RedirectResponse
    {
        $minutos = (int) config('datos.minutos_entre_exportaciones');
        $ultima = Exportacion::query()->latest()->first();
        if ($ultima && $ultima->created_at->gt(now()->subMinutes($minutos))) {
            throw ValidationException::withMessages(['exportar' => __('datos.exportar.espera', [
                'minutos' => max(1, (int) ceil(now()->diffInMinutes($ultima->created_at->copy()->addMinutes($minutos)))),
            ])]);
        }

        $exportacion = DB::transaction(function () use ($request) {
            $exportacion = Exportacion::create(['user_id' => $request->user()->id]);
            Bitacora::registrar($request->user(), 'exportacion', null, [Rol::PROPIETARIO]);

            return $exportacion;
        });
        // Con la cola en «sync» (pruebas) se genera en el acto; en producción la toma el trabajador de la cola
        GenerarExportacion::dispatch($exportacion->id, $exportacion->tenant_id)->afterCommit();

        return redirect()->route('admin.datos.index')->with('estado', __('datos.exportar.encolada'));
    }

    public function descargar(Request $request, Exportacion $exportacion): BinaryFileResponse
    {
        abort_unless($exportacion->descargable() && Storage::disk('exportaciones')->exists($exportacion->ruta), 410, __('datos.exportar.vencida'));

        $nombre = 'datos-finca-'.$request->user()->tenant->codigo.'-'.$exportacion->created_at->format('Y-m-d').'.zip';

        return response()->download(Storage::disk('exportaciones')->path($exportacion->ruta), $nombre, ['Content-Type' => 'application/zip']);
    }

    /** Exige escribir el código de la finca y la contraseña. Queda suspendida y en eliminación (se puede cancelar). */
    public function solicitarEliminacion(Request $request): RedirectResponse
    {
        $finca = $request->user()->tenant;
        $request->validate([
            'codigo' => ['required', 'string', function ($atributo, $valor, $falla) use ($finca) {
                if (! $finca->tieneCodigo((string) $valor)) {
                    $falla(__('datos.eliminar.codigo_incorrecto'));
                }
            }],
            'actual' => ['required', 'string', 'current_password:web'],
        ], ['actual.current_password' => __('cuenta.contrasena.actual_incorrecta')]);

        Bitacora::registrar($request->user(), 'eliminacion', null, [Rol::PROPIETARIO]);
        EliminadorFinca::solicitar($finca, $request->user());

        return redirect()->route('admin.datos.eliminacion');
    }

    /** Pantalla de la finca en eliminación: lo único que ve el propietario durante el periodo de gracia. */
    public function eliminacion(Request $request): View|RedirectResponse
    {
        $finca = $request->user()->tenant;

        return $finca->enEliminacion() ? view('admin.datos.eliminacion', ['finca' => $finca]) : redirect()->route('admin.datos.index');
    }

    public function cancelarEliminacion(Request $request): RedirectResponse
    {
        $request->validate(['actual' => ['required', 'string', 'current_password:web']], ['actual.current_password' => __('cuenta.contrasena.actual_incorrecta')]);

        EliminadorFinca::cancelar($request->user()->tenant);
        Bitacora::registrar($request->user(), 'eliminacion_anulada', null, [Rol::PROPIETARIO]);

        return redirect()->route('inicio')->with('estado', __('datos.eliminar.cancelada'));
    }
}
