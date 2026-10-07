<?php

namespace App\Providers;

use App\Database\ConexionPostgresUtc;
use App\Support\Asistente\AsistenteNoDisponible;
use App\Support\Asistente\ClaudeAsistente;
use App\Support\Asistente\ClienteAsistente;
use App\Support\Asistente\GeminiAsistente;
use App\Support\TenantActual;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantActual::class);

        // Las fechas con hora viajan siempre en UTC a PostgreSQL (ver ConexionPostgresUtc)
        Connection::resolverFor('pgsql', fn ($pdo, $base, $prefijo, $config) => new ConexionPostgresUtc($pdo, $base, $prefijo, $config));

        // Proveedor del asistente IA según ASISTENTE_PROVEEDOR (claude o gemini)
        $this->app->bind(ClienteAsistente::class, function () {
            $proveedor = config('services.asistente.proveedor');

            return match ($proveedor) {
                'claude' => new ClaudeAsistente(config('services.anthropic.key'), config('services.anthropic.modelo')),
                'gemini' => new GeminiAsistente(config('services.gemini.key'), config('services.gemini.modelo')),
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
        // Cada pregunta al asistente cuesta dinero: máximo 10 por minuto por persona
        RateLimiter::for('asistente', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id));

        // Recuperar contraseña: 5 intentos por minuto por IP, con un contador aparte para pedir enlaces y para restablecer
        RateLimiter::for('pedir-enlace-contrasena', fn (Request $request) => Limit::perMinute(5)->by('enlace|'.$request->ip()));
        RateLimiter::for('restablecer-contrasena', fn (Request $request) => Limit::perMinute(5)->by('restablecer|'.$request->ip()));
    }
}
