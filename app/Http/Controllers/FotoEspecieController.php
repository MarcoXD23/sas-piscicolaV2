<?php

namespace App\Http\Controllers;

use App\Models\EspecieFoto;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Entrega una foto del disco privado solo a quien tiene sesión en ESA finca (otra finca recibe 404 por el filtro de finca).
 * La versión ligera la ve cualquier rol de la finca; el original, solo el propietario y el administrador.
 * El tipo de contenido lo fija el servidor (nunca el nombre del archivo) y va con nosniff.
 */
class FotoEspecieController extends Controller
{
    public function ver(Request $request, EspecieFoto $foto, string $version): BinaryFileResponse
    {
        $esOriginal = $version === EspecieFoto::ORIGINAL;
        abort_if($esOriginal && ! $request->user()->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR), 403);

        $ruta = $esOriginal ? $foto->ruta_original : $foto->ruta_ligera;
        abort_unless(Storage::disk('fotos')->exists($ruta), 404);

        $respuesta = response()->file(Storage::disk('fotos')->path($ruta), [
            'Content-Type' => $esOriginal ? $foto->tipo_original : 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="foto-'.$foto->id.'-'.$version.'.'.($esOriginal ? pathinfo($ruta, PATHINFO_EXTENSION) : 'webp').'"',
        ]);
        // Privada: el navegador la puede guardar un día, pero nunca un proxy compartido
        $respuesta->setPrivate();
        $respuesta->setMaxAge(86400);

        return $respuesta;
    }
}
