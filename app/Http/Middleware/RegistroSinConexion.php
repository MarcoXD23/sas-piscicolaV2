<?php

namespace App\Http\Middleware;

use App\Models\RegistroSinConexion as Registro;
use App\Support\MomentoRegistro;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Uso: ->middleware('sin_conexion'), antes de 'turno'. Sin las cabeceras de abajo no hace nada (envío normal).
 * Recibe un registro hecho sin conexión y enviado al volver la señal (resources/js/sin-conexion.js):
 * - X-Registro-Sin-Conexion: id único (uuid) que generó el teléfono. Si ya se recibió, no se procesa otra vez.
 * - X-Registrado-En: hora real del registro (ISO 8601). No puede ser futura ni más vieja que SIN_CONEXION_HORAS;
 *   el turno y las horas guardadas se evalúan a esa hora (MomentoRegistro).
 * Las reglas de siempre (stock, peces vivos, finca, rol) se aplican al llegar. La respuesta es JSON:
 * 201 guardado, 200 duplicado, 403/422 rechazado con su motivo.
 */
class RegistroSinConexion
{
    public const CABECERA_ID = 'X-Registro-Sin-Conexion';

    public const CABECERA_MOMENTO = 'X-Registrado-En';

    public function __construct(private MomentoRegistro $momentoRegistro) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header(self::CABECERA_ID);
        if ($id === null) {
            return $next($request);
        }

        // El teléfono siempre recibe JSON (nunca una redirección que parezca éxito)
        $request->headers->set('Accept', 'application/json');

        $momento = $this->momentoValido($id, $request->header(self::CABECERA_MOMENTO));
        if ($momento instanceof Response) {
            return $momento;
        }

        $this->momentoRegistro->fijar($momento);

        try {
            return $this->procesar($request, $next, $id, $momento);
        } finally {
            // La hora del teléfono vale solo para esta petición
            $this->momentoRegistro->olvidar();
        }
    }

    private function procesar(Request $request, Closure $next, string $id, CarbonImmutable $momento): Response
    {
        $usuario = $request->user();

        return DB::transaction(function () use ($request, $next, $id, $momento, $usuario) {
            // Dos envíos simultáneos del mismo registro esperan su turno; el segundo ve el primero
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['sin_conexion|'.$usuario->tenant_id.'|'.$id]);

            if (Registro::query()->where('uuid', $id)->exists()) {
                return response()->json(['estado' => 'duplicado', 'mensaje' => __('mensajes.sin_conexion.duplicado')]);
            }

            $respuesta = $next($request);
            if ($respuesta->getStatusCode() >= 400) {
                return $respuesta;
            }

            Registro::create([
                'user_id' => $usuario->id,
                'uuid' => $id,
                'ruta' => (string) $request->route()?->getName(),
                'registrado_at' => $momento,
            ]);

            // El mensaje de éxito va en la respuesta, no en la próxima pantalla
            return response()->json([
                'estado' => 'guardado',
                'mensaje' => $request->hasSession() ? $request->session()->pull('estado') : null,
            ], 201);
        });
    }

    private function momentoValido(string $id, ?string $hora): CarbonImmutable|Response
    {
        $validador = Validator::make(['id' => $id, 'hora' => $hora], [
            'id' => ['required', 'uuid'],
            'hora' => ['required', 'date'],
        ]);

        if ($validador->fails()) {
            return $this->rechazo(__('mensajes.sin_conexion.datos_invalidos'));
        }

        try {
            $momento = CarbonImmutable::parse($hora)->utc();
        } catch (Throwable) {
            return $this->rechazo(__('mensajes.sin_conexion.datos_invalidos'));
        }

        if ($momento->greaterThan(CarbonImmutable::now()->addMinutes((int) config('sin_conexion.margen_reloj_minutos')))) {
            return $this->rechazo(__('mensajes.sin_conexion.hora_futura'));
        }

        if ($momento->lessThan(CarbonImmutable::now()->subHours((int) config('sin_conexion.horas')))) {
            return $this->rechazo(__('mensajes.sin_conexion.muy_viejo', ['horas' => config('sin_conexion.horas')]));
        }

        return $momento;
    }

    private function rechazo(string $mensaje): Response
    {
        return response()->json(['message' => $mensaje, 'errors' => ['registro' => [$mensaje]]], 422);
    }
}
