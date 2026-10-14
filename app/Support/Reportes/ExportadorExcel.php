<?php

namespace App\Support\Reportes;

use App\Support\Hora;
use DateTimeImmutable;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Genera un .xlsx con una hoja por tabla (openspout). Los números van como números (se pueden sumar en Excel).
 * Las fechas con hora (un DateTimeInterface en la fila, ya en la zona de la finca) van como fecha y hora de Excel, con
 * el formato de hora de la persona (12 o 24 horas): se pueden ordenar y calcular. Las fechas sin hora siguen como texto.
 */
class ExportadorExcel
{
    /**
     * @param  list<Tabla>  $tablas
     * @param  list<string>  $encabezado  Líneas de contexto al inicio de la primera hoja (finca, período, generado)
     */
    public static function generar(array $tablas, array $encabezado, string $ruta): void
    {
        $escritor = new Writer;
        $escritor->openToFile($ruta);
        $negrita = (new Style)->withFontBold(true);

        foreach ($tablas as $indice => $tabla) {
            $hoja = $indice === 0 ? $escritor->getCurrentSheet() : $escritor->addNewSheetAndMakeItCurrent();
            $hoja->setName(self::nombreHoja($tabla->nombre, $indice));

            if ($indice === 0) {
                foreach ($encabezado as $linea) {
                    $escritor->addRow(Row::fromValues([$linea]));
                }
                $escritor->addRow(Row::fromValues([]));
            }

            $escritor->addRow(Row::fromValuesWithStyle(array_column($tabla->columnas, 0), $negrita));

            foreach ($tabla->filas as $fila) {
                $celdas = [];
                foreach (array_values($fila) as $posicion => $valor) {
                    $decimales = $tabla->columnas[$posicion][1] ?? null;
                    $celdas[] = match (true) {
                        is_int($valor) || is_float($valor) => Cell::fromValue($valor, (new Style)->withFormat(self::formato($decimales, $valor))),
                        $valor instanceof DateTimeInterface => Cell::fromValue(DateTimeImmutable::createFromInterface($valor), (new Style)->withFormat(self::formatoFechaHora())),
                        default => Cell::fromValue((string) $valor),
                    };
                }
                $escritor->addRow(new Row($celdas));
            }
        }

        $escritor->close();
    }

    /** Formato de Excel de una fecha con hora: «15/10/2026 10:24 PM» (12 horas) o «15/10/2026 22:24» (24 horas). */
    public static function formatoFechaHora(): string
    {
        return Hora::de() === Hora::DOCE ? 'dd/mm/yyyy h:mm AM/PM' : 'dd/mm/yyyy hh:mm';
    }

    /**
     * @param  list<Tabla>  $tablas
     * @param  list<string>  $encabezado
     */
    public static function descargar(array $tablas, array $encabezado, string $nombreArchivo): BinaryFileResponse
    {
        // tempnam crea un archivo vacío para reservar el nombre: se usa ese nombre con extensión .xlsx y se borra el vacío
        $base = tempnam(sys_get_temp_dir(), 'reporte');
        $ruta = $base.'.xlsx';
        unlink($base);
        self::generar($tablas, $encabezado, $ruta);

        return response()->download($ruta, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private static function formato(?int $decimales, int|float $valor): string
    {
        $decimales ??= is_int($valor) ? 0 : 2;

        return $decimales === 0 ? '#,##0' : '#,##0.'.str_repeat('0', $decimales);
    }

    /** Excel limita los nombres de hoja a 31 caracteres y prohíbe algunos signos. */
    private static function nombreHoja(string $nombre, int $indice): string
    {
        $limpio = trim(mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $nombre), 0, 31));

        return $limpio !== '' ? $limpio : (string) ($indice + 1);
    }
}
