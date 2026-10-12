<?php

namespace App\Http\Controllers;

use App\Support\Salud\Comprobaciones;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * /salud: para un servicio de monitoreo externo. Responde solo «ok» (200) o «falla» (503), sin ningún detalle:
 * el detalle está en php artisan piscicola:estado (en el servidor). El resultado se guarda segundos_cache segundos
 * (config/salud.php) para no repetir las comprobaciones en cada consulta.
 */
class SaludController extends Controller
{
    public function __invoke(): Response
    {
        $comprobar = fn () => Comprobaciones::todoBien(Comprobaciones::correr()) ? 'ok' : 'falla';
        try {
            $resultado = Cache::remember('salud:resultado', (int) config('salud.segundos_cache'), $comprobar);
        } catch (Throwable) {
            // Si la caché misma no responde (por ejemplo, guardada en la base caída), se comprueba sin ella
            $resultado = $comprobar();
        }
        $bien = $resultado === 'ok';

        return response($bien ? 'ok' : 'falla', $bien ? 200 : 503, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
