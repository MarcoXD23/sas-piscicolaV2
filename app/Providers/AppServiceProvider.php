<?php

namespace App\Providers;

use App\Support\Asistente\ClaudeAsistente;
use App\Support\Asistente\ClienteAsistente;
use App\Support\TenantActual;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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

        $this->app->bind(ClienteAsistente::class, fn () => new ClaudeAsistente(
            config('services.anthropic.key'),
            config('services.anthropic.modelo'),
        ));
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
