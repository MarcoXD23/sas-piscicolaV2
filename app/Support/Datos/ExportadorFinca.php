<?php

namespace App\Support\Datos;

use App\Models\Exportacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Paquete ZIP con los datos de UNA finca: un CSV por tabla (UTF-8 con BOM), las fotos originales y un LEEME.txt.
 * Nada sensible: sin contraseñas, tokens, claves de dos pasos ni rutas internas; nunca datos de otra finca
 * (cada tabla se lee con where tenant_id explícito).
 */
final class ExportadorFinca
{
    /** Tablas internas que no son datos de la finca. */
    private const OMITIDAS = ['exportaciones', 'registros_sin_conexion'];

    /** Columnas que nunca salen. */
    private const COLUMNAS_SENSIBLES = [
        'users' => ['password', 'remember_token', 'dos_pasos_clave', 'dos_pasos_recuperacion', 'dos_pasos_ultimo_paso'],
        'especie_fotos' => ['ruta_original', 'ruta_ligera'],
    ];

    /** Genera el ZIP y deja la exportación lista (o en fallo, sin archivos sueltos). */
    public static function generar(Exportacion $exportacion): void
    {
        $tenantId = $exportacion->tenant_id;
        $local = tempnam(sys_get_temp_dir(), 'exp');
        $zip = new ZipArchive;
        if ($zip->open($local, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo ZIP.');
        }

        try {
            $zip->addFromString('LEEME.txt', __('datos.leeme', ['version' => config('legal.version')]));
            $zip->addFromString('finca.csv', self::csv(DB::table('tenants')->where('id', $tenantId)->get()->all()));

            foreach (array_diff(TablasFinca::todas(), self::OMITIDAS) as $tabla) {
                $filas = DB::table($tabla)->where('tenant_id', $tenantId)->orderBy(Schema::hasColumn($tabla, 'id') ? 'id' : 'user_id')->get()
                    ->map(fn ($fila) => self::sinSensibles($tabla, (array) $fila))->all();
                $zip->addFromString($tabla.'.csv', self::csv($filas));
            }

            // Fotos originales, con un nombre legible (especie, orden) en lugar de la ruta interna
            foreach (DB::table('especie_fotos')->where('tenant_id', $tenantId)->get() as $foto) {
                $disco = Storage::disk('fotos');
                if ($disco->exists($foto->ruta_original)) {
                    $zip->addFile($disco->path($foto->ruta_original), sprintf('fotos/especie-%d-foto-%d.%s', $foto->especie_id, $foto->orden, pathinfo($foto->ruta_original, PATHINFO_EXTENSION)));
                }
            }
            $zip->close();

            $ruta = $tenantId.'/'.bin2hex(random_bytes(20)).'.zip';
            Storage::disk('exportaciones')->put($ruta, (string) file_get_contents($local));
            $exportacion->forceFill([
                'estado' => Exportacion::LISTA, 'ruta' => $ruta, 'bytes' => filesize($local),
                'vence_at' => now()->addHours(config('datos.horas_exportacion')),
            ])->save();
        } finally {
            @unlink($local);
        }
    }

    /** @param  list<array<string, mixed>|object>  $filas */
    private static function csv(array $filas): string
    {
        $salida = fopen('php://temp', 'r+');
        fwrite($salida, "\xEF\xBB\xBF");   // BOM: Excel abre bien las tildes
        $filas = array_map(fn ($fila) => (array) $fila, $filas);
        if ($filas !== []) {
            fputcsv($salida, array_keys($filas[0]), ',', '"', '');
            foreach ($filas as $fila) {
                fputcsv($salida, array_map(fn ($valor) => is_bool($valor) ? ($valor ? '1' : '0') : $valor, array_values($fila)), ',', '"', '');
            }
        }
        rewind($salida);

        return (string) stream_get_contents($salida);
    }

    private static function sinSensibles(string $tabla, array $fila): array
    {
        foreach (self::COLUMNAS_SENSIBLES[$tabla] ?? [] as $columna) {
            unset($fila[$columna]);
        }
        if ($tabla === 'especie_fotos') {
            $fila['archivo'] = sprintf('fotos/especie-%d-foto-%d', $fila['especie_id'], $fila['orden']);
        }

        return $fila;
    }
}
