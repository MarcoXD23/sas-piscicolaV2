<?php

namespace App\Providers;

use App\Auth\ProveedorUsuarios;
use App\Database\ConexionPostgresUtc;
use App\Models\MedicionAgua;
use App\Models\Mortalidad;
use App\Models\MovimientoAlimento;
use App\Models\TokenAcceso;
use App\Support\Alertas\CanalCorreo;
use App\Support\Alertas\CanalNulo;
use App\Support\Alertas\EvaluadorAlertas;
use App\Support\Asistente\AsistenteNoDisponible;
use App\Support\Asistente\ClaudeAsistente;
use App\Support\Asistente\ClienteAsistente;
use App\Support\Asistente\GeminiAsistente;
use App\Support\TenantActual;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantActual::class);

        // Canales para avisar alertas críticas. WhatsApp queda sin proveedor (CanalNulo) hasta elegir uno.
        // Al elegir proveedor (WHATSAPP_PROVEEDOR) se agrega aquí su clase, que implementa CanalNotificacion.
        $this->app->bind('canal.whatsapp', fn () => match (config('services.whatsapp.proveedor')) {
            default => new CanalNulo,
        });
        $this->app->tag([CanalCorreo::class, 'canal.whatsapp'], 'canales_notificacion');

        // Las fechas con hora viajan siempre en UTC a PostgreSQL (ver ConexionPostgresUtc)
        Connection::resolverFor('pgsql', fn ($pdo, $base, $prefijo, $config) => new ConexionPostgresUtc($pdo, $base, $prefijo, $config));

        // Proveedor del asistente IA según ASISTENTE_PROVEEDOR (claude o gemini)
        $this->app->bind(ClienteAsistente::class, function () {
            $proveedor = config('services.asistente.proveedor');

            return match ($proveedor) {
                'claude' => new ClaudeAsistente(config('services.anthropic.key'), config('services.anthropic.modelo')),
                'gemini' => new GeminiAsistente(config('services.gemini.key'), config('services.gemini.modelo'), config('services.gemini.modelo_respaldo')),
                default => $this->proveedorDesconocido((string) $proveedor),
            };
        });
    }

    /** Valor no válido en ASISTENTE_PROVEEDOR: el asistente queda como "no configurado" (no se adivina un proveedor). */
    private function proveedorDesconocido(string $proveedor): ClienteAsistente
    {
        Log::warning('Asistente IA: ASISTENTE_PROVEEDOR no válido (use claude o gemini)', ['valor' => $proveedor]);

        return new class implements ClienteAsistente
        {
            public function configurado(): bool
            {
                return false;
            }

            public function responder(string $instrucciones, string $contexto, array $mensajes): string
            {
                throw new AsistenteNoDisponible(__('asistente.errores.proveedor_no_configurado'));
            }
        };
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // El filtro de finca falla cerrado: la identidad (login, sesión, recuperar contraseña y tokens de la API)
        // se busca sin él, antes de que EstablecerTenant fije la finca
        Auth::provider('usuarios_finca', fn ($app, array $config) => new ProveedorUsuarios($app['hash'], $config['model']));
        Sanctum::usePersonalAccessTokenModel(TokenAcceso::class);

        // Las alertas se revisan después de guardar los registros que las afectan (al confirmar la transacción)
        $evaluar = fn (Model $registro) => $registro->tenant_id === null ? null
            : DB::afterCommit(fn () => EvaluadorAlertas::paraFinca($registro->tenant_id));
        foreach ([MovimientoAlimento::class, Mortalidad::class, MedicionAgua::class] as $modelo) {
            $modelo::saved($evaluar);
            $modelo::deleted($evaluar);
        }

        // Publicar con APP_DEBUG activo mostraría SQL, rutas y configuración en cada error
        if ($this->app->isProduction() && config('app.debug')) {
            Log::critical('Seguridad: APP_DEBUG está activo en producción. Ponlo en false en el .env.');
        }

        // Solo se confía en el proxy de TRUSTED_PROXIES: así Laravel sabe que la petición llegó por HTTPS y la IP real
        if (config('app.proxies_confianza') !== []) {
            TrustProxies::at(config('app.proxies_confianza'));
        }

        $this->limitesDeIntentos();
    }

    /**
     * Límites de intentos con nombre: cada formulario tiene su propio contador (un límite sin nombre
     * comparte el contador de la IP entre todas las rutas, y en la finca todos salen por la misma IP).
     */
    private function limitesDeIntentos(): void
    {
        $correo = fn (Request $request) => mb_strtolower(trim((string) $request->input('email')));

        // Ingreso (web y API): 5 por minuto por correo, aunque cambie la IP, y 20 por minuto por IP
        foreach (['ingreso', 'ingreso-api', 'ingreso-plataforma'] as $nombre) {
            RateLimiter::for($nombre, fn (Request $request) => [
                Limit::perMinute(5)->by('correo|'.$correo($request)),
                Limit::perMinute(20)->by('ip|'.$request->ip()),
            ]);
        }

        RateLimiter::for('registro', fn (Request $request) => Limit::perMinute(10)->by('registro|'.$request->ip()));

        // Cada pregunta al asistente cuesta dinero: 10 por minuto por persona y topes diarios por persona y por finca
        RateLimiter::for('asistente', fn (Request $request) => [
            Limit::perMinute(10)->by('minuto|'.$request->user()?->id),
            Limit::perDay(config('services.asistente.limite_diario_persona'))->by('persona|'.$request->user()?->id),
            Limit::perDay(config('services.asistente.limite_diario_finca'))->by('finca|'.$request->user()?->tenant_id),
        ]);

        // Recuperar contraseña: 5 intentos por minuto por IP, con un contador aparte para pedir enlaces y para restablecer
        RateLimiter::for('pedir-enlace-contrasena', fn (Request $request) => Limit::perMinute(5)->by('enlace|'.$request->ip()));
        RateLimiter::for('restablecer-contrasena', fn (Request $request) => Limit::perMinute(5)->by('restablecer|'.$request->ip()));
    }
}
