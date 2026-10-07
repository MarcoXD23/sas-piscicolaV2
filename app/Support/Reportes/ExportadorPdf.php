<?php

namespace App\Support\Reportes;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

/**
 * Genera el PDF de un reporte con dompdf a partir de una plantilla sencilla (CSS dentro del archivo).
 * Sin acceso a archivos remotos ni a PHP dentro del HTML.
 */
class ExportadorPdf
{
    /**
     * @param  list<Tabla>  $tablas
     * @param  array{finca: string, titulo: string, periodo: string, generado: string, notas?: list<string>}  $contexto
     */
    public static function html(array $tablas, array $contexto): string
    {
        return view('pdf.reporte', ['tablas' => $tablas] + $contexto + ['notas' => []])->render();
    }

    /**
     * @param  list<Tabla>  $tablas
     * @param  array{finca: string, titulo: string, periodo: string, generado: string, notas?: list<string>}  $contexto
     */
    public static function generar(array $tablas, array $contexto, string $orientacion = 'portrait'): string
    {
        $opciones = (new Options)
            ->setIsRemoteEnabled(false)
            ->setIsPhpEnabled(false)
            ->setDefaultFont('DejaVu Sans')
            ->setChroot(resource_path('views'));

        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(self::html($tablas, $contexto), 'UTF-8');
        $pdf->setPaper('A4', $orientacion);
        $pdf->render();

        return (string) $pdf->output();
    }

    /**
     * @param  list<Tabla>  $tablas
     * @param  array{finca: string, titulo: string, periodo: string, generado: string, notas?: list<string>}  $contexto
     */
    public static function descargar(array $tablas, array $contexto, string $nombreArchivo, string $orientacion = 'portrait'): Response
    {
        return response(self::generar($tablas, $contexto, $orientacion), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombreArchivo.'"',
        ]);
    }
}
