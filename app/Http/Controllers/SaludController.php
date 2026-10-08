<?php

namespace App\Http\Controllers;

use App\Support\Salud\Comprobaciones;
use Illuminate\Http\Response;

/**
 * /salud: para un servicio de monitoreo externo. Responde solo «ok» (200) o «falla» (503), sin ningún detalle:
 * el detalle está en php artisan piscicola:estado (en el servidor).
 */
class SaludController extends Controller
{
    public function __invoke(): Response
    {
        $bien = Comprobaciones::todoBien(Comprobaciones::correr());

        return response($bien ? 'ok' : 'falla', $bien ? 200 : 503, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
