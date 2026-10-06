<?php

namespace App\Providers;

use App\Support\Asistente\AsistenteNoDisponible;
use App\Support\Asistente\ClaudeAsistente;
use App\Support\Asistente\ClienteAsistente;
use App\Support\Asistente\GeminiAsistente;
use App\Support\TenantActual;
use Illuminate\Cache\RateLimiting\Limit;
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
                throw new AsistenteNoDisponible('El asistente no está configurado. Pide al propietario que revise el proveedor del asistente.');
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
    }
}
