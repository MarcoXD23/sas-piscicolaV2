<?php

namespace App\Support\Fotos;

/**
 * Quita los metadatos de una imagen SIN recomprimirla: se recorren los bloques del archivo y se descartan los que
 * llevan ubicación (GPS), modelo del teléfono, fechas, autor u otros datos personales. Los píxeles no se tocan.
 * - JPEG: fuera APP1 (EXIF y XMP), APP13 (IPTC), APP3 a APP12 y APP15, y comentarios. Se conservan JFIF (APP0),
 *   el perfil de color (APP2) y APP14 (Adobe, necesario para los colores). La orientación vuelve en un EXIF mínimo
 *   con solo esa etiqueta, para que la foto no aparezca girada.
 * - PNG: fuera tEXt, zTXt, iTXt, eXIf y tIME.
 * - WebP: fuera EXIF y XMP (y se apagan sus marcas en VP8X).
 */
final class LimpiadorMetadatos
{
    public static function limpiar(string $bytes, string $tipo): string
    {
        return match ($tipo) {
            'image/jpeg' => self::jpeg($bytes),
            'image/png' => self::png($bytes),
            'image/webp' => self::webp($bytes),
        };
    }

    /** Orientación EXIF (1 a 8) de un JPEG; 1 si no tiene. */
    public static function orientacionJpeg(string $bytes): int
    {
        foreach (self::segmentosJpeg($bytes) as [$marcador, $datos]) {
            if ($marcador === 0xE1 && str_starts_with($datos, "Exif\0\0")) {
                return self::leerOrientacion(substr($datos, 6));
            }
        }

        return 1;
    }

    private static function jpeg(string $bytes): string
    {
        $exif = self::exifMinimo(self::orientacionJpeg($bytes));
        $salida = "\xFF\xD8";
        $exifPuesto = false;

        foreach (self::segmentosJpeg($bytes) as [$marcador, , $crudo]) {
            // El EXIF mínimo va justo después de JFIF (APP0), o al principio si no hay JFIF
            if (! $exifPuesto && $marcador !== 0xE0) {
                $salida .= $exif;
                $exifPuesto = true;
            }
            if ($marcador === 0xDA) {
                // Inicio de los datos de imagen: se copian tal cual hasta el final (sin recomprimir)
                return $salida.$crudo;
            }
            if (self::conservarSegmento($marcador)) {
                $salida .= $crudo;
            }
            if (! $exifPuesto && $marcador === 0xE0) {
                $salida .= $exif;
                $exifPuesto = true;
            }
        }

        return $salida;
    }

    /** Se conservan las tablas y los marcadores de imagen; de los APPn solo JFIF, el perfil de color y Adobe; nunca comentarios. */
    private static function conservarSegmento(int $marcador): bool
    {
        if ($marcador === 0xFE) {
            return false;
        }

        return $marcador < 0xE0 || $marcador > 0xEF || in_array($marcador, [0xE0, 0xE2, 0xEE], true);
    }

    /**
     * Segmentos de un JPEG hasta el inicio de los datos de imagen (SOS), que se devuelve con todo lo que sigue.
     *
     * @return \Generator<array{0: int, 1: string, 2: string}> [marcador, datos, bytes crudos del segmento]
     */
    private static function segmentosJpeg(string $bytes): \Generator
    {
        $pos = 2;
        $largo = strlen($bytes);

        while ($pos + 4 <= $largo && $bytes[$pos] === "\xFF") {
            $marcador = ord($bytes[$pos + 1]);
            if ($marcador === 0xFF) {   // relleno
                $pos++;

                continue;
            }
            if ($marcador === 0xDA) {
                yield [$marcador, '', substr($bytes, $pos)];

                return;
            }
            $tamano = unpack('n', substr($bytes, $pos + 2, 2))[1];
            yield [$marcador, substr($bytes, $pos + 4, $tamano - 2), substr($bytes, $pos, $tamano + 2)];
            $pos += $tamano + 2;
        }
    }

    /** APP1 con un EXIF que solo lleva la orientación (vacío si es la normal). */
    private static function exifMinimo(int $orientacion): string
    {
        if ($orientacion === 1) {
            return '';
        }

        // TIFF big-endian: cabecera, 1 entrada (0x0112 Orientation, SHORT, 1 valor) y sin IFD siguiente
        $tiff = 'MM'.pack('nN', 42, 8).pack('n', 1).pack('nnNn', 0x0112, 3, 1, $orientacion)."\0\0".pack('N', 0);
        $datos = "Exif\0\0".$tiff;

        return "\xFF\xE1".pack('n', strlen($datos) + 2).$datos;
    }

    private static function leerOrientacion(string $tiff): int
    {
        if (strlen($tiff) < 8) {
            return 1;
        }
        $big = substr($tiff, 0, 2) === 'MM';
        $corto = fn (int $pos) => unpack($big ? 'n' : 'v', substr($tiff, $pos, 2))[1] ?? 0;
        $largo = fn (int $pos) => unpack($big ? 'N' : 'V', substr($tiff, $pos, 4))[1] ?? 0;

        $ifd = $largo(4);
        if ($ifd + 2 > strlen($tiff)) {
            return 1;
        }
        $entradas = $corto($ifd);
        for ($i = 0; $i < $entradas; $i++) {
            $entrada = $ifd + 2 + $i * 12;
            if ($entrada + 12 > strlen($tiff)) {
                break;
            }
            if ($corto($entrada) === 0x0112) {
                $valor = $corto($entrada + 8);

                return $valor >= 1 && $valor <= 8 ? $valor : 1;
            }
        }

        return 1;
    }

    private static function png(string $bytes): string
    {
        $fuera = ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'];
        $salida = substr($bytes, 0, 8);
        $pos = 8;

        while ($pos + 12 <= strlen($bytes)) {
            $tamano = unpack('N', substr($bytes, $pos, 4))[1];
            $tipo = substr($bytes, $pos + 4, 4);
            $bloque = substr($bytes, $pos, $tamano + 12);
            if (! in_array($tipo, $fuera, true)) {
                $salida .= $bloque;
            }
            $pos += $tamano + 12;
            if ($tipo === 'IEND') {
                break;
            }
        }

        return $salida;
    }

    private static function webp(string $bytes): string
    {
        $cuerpo = '';
        $pos = 12;

        while ($pos + 8 <= strlen($bytes)) {
            $tipo = substr($bytes, $pos, 4);
            $tamano = unpack('V', substr($bytes, $pos + 4, 4))[1];
            $bloque = substr($bytes, $pos, 8 + $tamano + ($tamano % 2));
            if ($tipo === 'VP8X') {
                // Marcas de EXIF (0x08) y XMP (0x04) apagadas: esos bloques ya no están
                $bloque[8] = chr(ord($bloque[8]) & ~0x0C);
            }
            if (! in_array($tipo, ['EXIF', 'XMP '], true)) {
                $cuerpo .= $bloque;
            }
            $pos += 8 + $tamano + ($tamano % 2);
        }

        return 'RIFF'.pack('V', strlen($cuerpo) + 4).'WEBP'.$cuerpo;
    }
}
