<?php

namespace App\Support\Fotos;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Procesa una foto subida, con la extensión GD de PHP (sin paquetes):
 * 1. Revisa el CONTENIDO (firma del archivo y cabecera), no la extensión ni el tipo que dice el navegador:
 *    solo JPEG, PNG o WebP reales. SVG y cualquier otro formato se rechazan.
 * 2. Revisa las dimensiones en la cabecera ANTES de decodificar (el tope sale de memory_limit).
 * 3. Guarda el ORIGINAL sin recomprimir y sin metadatos (LimpiadorMetadatos) y una versión LIGERA WebP
 *    de máximo 1600 px y menos de 200 KB. Nombres aleatorios, en una carpeta por finca y especie.
 */
final class ProcesadorFoto
{
    public const TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** Píxeles máximos que se aceptan con el memory_limit actual (y nunca más que el tope fijo). */
    public static function pixelesMax(): int
    {
        $fijo = (int) (config('fotos.megapixeles_max') * 1_000_000);
        $limite = self::bytesDeIni((string) ini_get('memory_limit'));
        if ($limite <= 0) {
            return $fijo;   // sin límite de memoria
        }

        // La mitad de la memoria para decodificar; la otra mitad para la app y la versión ligera (128 MB ≈ 13 MP; 512 MB = 50 MP)
        return min($fijo, (int) floor($limite * 0.5 / config('fotos.bytes_por_pixel')));
    }

    /**
     * @return array{tipo: string, ancho: int, alto: int}
     *
     * @throws ValidationException
     */
    public static function inspeccionar(string $ruta): array
    {
        $tipo = self::tipoPorFirma((string) file_get_contents($ruta, false, null, 0, 16));
        $info = $tipo === null ? false : @getimagesize($ruta);   // solo lee la cabecera: no decodifica

        if ($tipo === null || $info === false || ($info['mime'] ?? null) !== $tipo) {
            throw ValidationException::withMessages(['foto' => __('especies.fotos.formato_invalido')]);
        }

        [$ancho, $alto] = $info;
        $lado = (int) config('fotos.lado_max');
        if ($ancho < 1 || $alto < 1 || $ancho > $lado || $alto > $lado || $ancho * $alto > self::pixelesMax()) {
            throw ValidationException::withMessages(['foto' => __('especies.fotos.dimensiones', [
                'ancho' => $ancho, 'alto' => $alto, 'megapixeles' => round(self::pixelesMax() / 1_000_000, 1), 'lado' => $lado,
            ])]);
        }

        return ['tipo' => $tipo, 'ancho' => $ancho, 'alto' => $alto];
    }

    /**
     * Guarda los dos archivos. Si algo falla, no deja archivos sueltos.
     *
     * @return array{ruta_original: string, ruta_ligera: string, tipo_original: string, bytes_original: int, bytes_ligera: int, ancho: int, alto: int}
     */
    public static function guardar(UploadedFile $archivo, int $tenantId, int $especieId): array
    {
        $info = self::inspeccionar($archivo->getRealPath());
        $bytes = (string) file_get_contents($archivo->getRealPath());

        $original = LimpiadorMetadatos::limpiar($bytes, $info['tipo']);
        $orientacion = $info['tipo'] === 'image/jpeg' ? LimpiadorMetadatos::orientacionJpeg($bytes) : 1;
        $ligera = self::ligera($bytes, $orientacion);
        gc_collect_cycles();

        $carpeta = $tenantId.'/'.$especieId;
        $rutaOriginal = $carpeta.'/'.bin2hex(random_bytes(20)).'.'.self::TIPOS[$info['tipo']];
        $rutaLigera = $carpeta.'/'.bin2hex(random_bytes(20)).'.webp';

        $disco = Storage::disk('fotos');
        try {
            $disco->put($rutaOriginal, $original);
            $disco->put($rutaLigera, $ligera);
        } catch (\Throwable $error) {
            $disco->delete([$rutaOriginal, $rutaLigera]);
            throw $error;
        }

        return [
            'ruta_original' => $rutaOriginal, 'ruta_ligera' => $rutaLigera, 'tipo_original' => $info['tipo'],
            'bytes_original' => strlen($original), 'bytes_ligera' => strlen($ligera), 'ancho' => $info['ancho'], 'alto' => $info['alto'],
        ];
    }

    /** WebP de máximo 1600 px de lado y menos de 200 KB (baja la calidad y, si hace falta, el tamaño). */
    public static function ligera(string $bytes, int $orientacion = 1): string
    {
        $imagen = @imagecreatefromstring($bytes);
        if (! $imagen instanceof GdImage) {
            throw ValidationException::withMessages(['foto' => __('especies.fotos.formato_invalido')]);
        }
        $imagen = self::orientar($imagen, $orientacion);

        $lado = (int) config('fotos.ligera_lado_max');
        $maximo = (int) config('fotos.ligera_bytes_max');

        for ($intento = 0; $intento < 6; $intento++) {
            $reducida = self::reducir($imagen, $lado);
            foreach ([82, 74, 66, 58, 50, 42] as $calidad) {
                ob_start();
                imagewebp($reducida, null, $calidad);
                $webp = (string) ob_get_clean();
                if (strlen($webp) < $maximo) {
                    return $webp;
                }
            }
            $lado = (int) floor($lado * 0.8);
        }

        throw ValidationException::withMessages(['foto' => __('especies.fotos.no_se_pudo_reducir')]);
    }

    private static function reducir(GdImage $imagen, int $lado): GdImage
    {
        [$ancho, $alto] = [imagesx($imagen), imagesy($imagen)];
        if (max($ancho, $alto) <= $lado) {
            $copia = $imagen;
        } else {
            $factor = $lado / max($ancho, $alto);
            $copia = imagescale($imagen, max(1, (int) round($ancho * $factor)), max(1, (int) round($alto * $factor)), IMG_BICUBIC);
        }
        // Conserva la transparencia de un PNG o WebP
        imagealphablending($copia, false);
        imagesavealpha($copia, true);

        return $copia;
    }

    /** Aplica la orientación EXIF (2 a 8) a los píxeles de la versión ligera. */
    private static function orientar(GdImage $imagen, int $orientacion): GdImage
    {
        if (in_array($orientacion, [2, 4, 5, 7], true)) {
            imageflip($imagen, IMG_FLIP_HORIZONTAL);
        }

        // imagerotate gira en sentido antihorario: -90 = 90° a la derecha
        return match ($orientacion) {
            3, 4 => imagerotate($imagen, 180, 0),
            6, 7 => imagerotate($imagen, -90, 0),
            5, 8 => imagerotate($imagen, 90, 0),
            default => $imagen,
        };
    }

    private static function tipoPorFirma(string $inicio): ?string
    {
        return match (true) {
            str_starts_with($inicio, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($inicio, "\x89PNG\r\n\x1A\n") => 'image/png',
            str_starts_with($inicio, 'RIFF') && substr($inicio, 8, 4) === 'WEBP' => 'image/webp',
            default => null,
        };
    }

    private static function bytesDeIni(string $valor): int
    {
        $valor = trim($valor);
        if ($valor === '' || $valor === '-1') {
            return -1;
        }
        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }
}
