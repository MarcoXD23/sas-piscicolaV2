<?php

namespace App\Http\Middleware;

use App\Models\RegistroSinConexion as Registro;
use App\Support\MomentoRegistro;
use App\Support\Region;
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
 * - X-Registrado-En: hora del registro según el reloj del teléfono (ISO 8601).
 * - X-Hora-Telefono: hora del teléfono en el momento del envío. Obligatoria: con ella se corrige un reloj mal puesto.
 *   Desfase = hora del servidor − hora del teléfono; hora real = X-Registrado-En + desfase. La hora real no puede ser
 *   futura ni más vieja que SIN_CONEXION_HORAS; el turno, la bitácora y las horas guardadas se evalúan a esa hora
 *   (MomentoRegistro). Un desfase de más de 5 minutos queda anotado en la bitácora.
 * - X-Ultima-Hora-Servidor: la última hora que el teléfono recibió del servidor (cabecera X-Hora-Servidor de cada
 *   respuesta, middleware HoraServidor) antes de guardar el registro. La hora real del registro no puede ser anterior
 *   a ella (con margen_hora_servidor_minutos): cierra el truco de atrasar el reloj para caer en el turno y volverlo a
 *   poner bien antes de enviar. Obligatoria mientras SIN_CONEXION_EXIGIR_HORA_SERVIDOR sea true (por defecto).
 * Las reglas de siempre (stock, peces vivos, finca, rol) se aplican al llegar. La respuesta es JSON:
 * 201 guardado, 200 duplicado, 403/422 rechazado con su motivo.
 */
class RegistroSinConexion
{
    public const CABECERA_ID = 'X-Registro-Sin-Conexion';

    public const CABECERA_MOMENTO = 'X-Registrado-En';

    public const CABECERA_HORA_TELEFONO = 'X-Hora-Telefono';

    public const CABECERA_ULTIMA_HORA_SERVIDOR = 'X-Ultima-Hora-Servidor';

    /** Desde este desfase (en minutos) la corrección del reloj se anota en la bitácora. */
    public const MINUTOS_PARA_ANOTAR = 5;

    public function __construct(private MomentoRegistro $momentoRegistro) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header(self::CABECERA_ID);
        if ($id === null) {
            return $next($request);
        }

        // El teléfono siempre recibe JSON (nunca una redirección que parezca éxito)
        $request->headers->set('Accept', 'application/json');

        $momento = $this->momentoValido($id, $request->header(self::CABECERA_MOMENTO), $request->header(self::CABECERA_HORA_TELEFONO));
        if ($momento instanceof Response) {
            return $momento;
        }
        [$momento, $desfaseSegundos] = $momento;

        $rechazo = $this->despuesDeLaHoraDelServidor($momento, $request->header(self::CABECERA_ULTIMA_HORA_SERVIDOR));
        if ($rechazo !== null) {
            return $rechazo;
        }

        $minutosCorregidos = intdiv(abs($desfaseSegundos), 60);
        $this->momentoRegistro->fijar($momento, $minutosCorregidos > self::MINUTOS_PARA_ANOTAR ? $minutosCorregidos : null);

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

    /** @return array{0: CarbonImmutable, 1: int}|Response  Hora real corregida y desfase del reloj en segundos */
    private function momentoValido(string $id, ?string $hora, ?string $horaTelefono): array|Response
    {
        $validador = Validator::make(['id' => $id, 'hora' => $hora], [
            'id' => ['required', 'uuid'],
            'hora' => ['required', 'date'],
        ]);

        if ($validador->fails()) {
            return $this->rechazo(__('mensajes.sin_conexion.datos_invalidos'));
        }

        // Sin la hora del teléfono no se puede corregir su reloj: no se acepta
        if (Validator::make(['hora' => $horaTelefono], ['hora' => ['required', 'date']])->fails()) {
            return $this->rechazo(__('mensajes.sin_conexion.sin_hora_telefono'));
        }

        try {
            $ahora = CarbonImmutable::now();
            $desfase = (int) round($ahora->getTimestamp() - CarbonImmutable::parse($horaTelefono)->getTimestamp());
            $momento = CarbonImmutable::parse($hora)->utc()->addSeconds($desfase);
        } catch (Throwable) {
            return $this->rechazo(__('mensajes.sin_conexion.datos_invalidos'));
        }

        if ($momento->greaterThan(CarbonImmutable::now()->addMinutes((int) config('sin_conexion.margen_reloj_minutos')))) {
            return $this->rechazo(__('mensajes.sin_conexion.hora_futura'));
        }

        if ($momento->lessThan(CarbonImmutable::now()->subHours((int) config('sin_conexion.horas')))) {
            return $this->rechazo(__('mensajes.sin_conexion.muy_viejo', ['horas' => config('sin_conexion.horas')]));
        }

        return [$momento, $desfase];
    }

    /**
     * La hora real del registro (ya corregida) no puede ser anterior a la última hora que el teléfono recibió del
     * servidor antes de guardarlo: el registro no pudo ocurrir antes de esa respuesta.
     */
    private function despuesDeLaHoraDelServidor(CarbonImmutable $momento, ?string $ultimaHoraServidor): ?Response
    {
        if (blank($ultimaHoraServidor)) {
            return config('sin_conexion.exigir_hora_servidor') ? $this->rechazo(__('mensajes.sin_conexion.sin_hora_servidor')) : null;
        }

        try {
            $servidor = CarbonImmutable::parse($ultimaHoraServidor)->utc();
        } catch (Throwable) {
            return $this->rechazo(__('mensajes.sin_conexion.datos_invalidos'));
        }

        // El servidor nunca dio una hora futura: si la trae, el dato no es de él
        if ($servidor->greaterThan(CarbonImmutable::now()->addMinutes((int) config('sin_conexion.margen_reloj_minutos')))) {
            return $this->rechazo(__('mensajes.sin_conexion.datos_invalidos'));
        }

        if ($momento->lessThan($servidor->subMinutes((int) config('sin_conexion.margen_hora_servidor_minutos')))) {
            return $this->rechazo(__('mensajes.sin_conexion.antes_de_hora_servidor', [
                'momento' => Region::local($momento)->format('d/m/Y H:i'),
                'servidor' => Region::local($servidor)->format('d/m/Y H:i'),
            ]));
        }

        return null;
    }

    private function rechazo(string $mensaje): Response
    {
        return response()->json(['message' => $mensaje, 'errors' => ['registro' => [$mensaje]]], 422);
    }
}
