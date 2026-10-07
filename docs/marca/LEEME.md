# Marca de El SAS Piscícola

Trucha arcoíris con corona. Esta carpeta guarda los archivos de la marca; la app solo carga las copias livianas de `public/` (cada una de menos de 200 KB).

| Archivo | Uso en la app |
|---|---|
| `icono-original.png` | Ninguno. Arte original (pesado); nunca se carga. |
| `icon-192.png`, `icon-512.png` | `public/icons/`: manifest (PWA), logo del encabezado y del login. |
| `icon-maskable-512.webp` | `public/icons/`: ícono maskable del manifest. |
| `apple-touch-icon.png` | `public/icons/`: ícono al agregar a la pantalla de inicio en iPhone. |
| `favicon.ico` | Favicon entregado. Reemplazado en `public/` por el de abajo porque a 16 px no se reconocía. |
| `favicon-16.png`, `favicon-32.png`, `favicon-48.png` | Fuentes del `public/favicon.ico` actual. |

## Cómo se hizo el favicon actual

Sin dibujar nada nuevo, solo a partir de `icon-512.png`:

1. **Recorte** de la trucha con su corona: cuadro de 380 px desde (70, 40), sin el marco azul ni parte del agua.
2. **Fondo oscurecido:** los píxeles azules y turquesas saturados (tono entre 170° y 250°, saturación mayor a 35 %) se oscurecen al 35 %. La trucha (rosa, blanco, verde oliva) y la corona (dorado) conservan su color.
3. **Reducción por pasos** (a la mitad cada vez) hasta 48, 32 y 16 px.
4. Se arma `public/favicon.ico` con las tres imágenes en formato PNG.

Resultado medido (lo verifica `tests/Feature/MarcaTest.php`): a 16 px, 34 % de fondo oscuro y 17 % de figura clara; el favicon entregado tenía 10 % de fondo oscuro y se veía como una mancha turquesa.

Si el diseñador entrega un favicon simplificado (silueta de la trucha con la corona, pocos colores, revisado a 16 px), basta con reemplazar `favicon-16.png`, `favicon-32.png` y `favicon-48.png` y volver a armar `public/favicon.ico`.

## Imágenes de referencia de especies (`public/images/especies/`)

Se muestran como portada de una especie por defecto mientras la finca no suba fotos propias (Especies → Editar → Fotos).

| Archivo | Peso | Origen | Licencia |
|---|---|---|---|
| `bagre-rayado.webp` | 48 KB | Desconocido | Desconocida |
| `bocachico.webp` | 122 KB | Desconocido | Desconocida |
| `cachama-blanca.webp` | 175 KB | Desconocido | Desconocida |
| `mojarra-negra.webp` | 23 KB | Desconocido | Desconocida |
| `trucha-arcoiris.webp` | 77 KB | Desconocido | Desconocida |

Entraron al repositorio en la «Fase 1» (commit `0aa3ad2`, 5 de octubre de 2026) ya convertidas a WebP, sin registro de su fuente y sin metadatos de autor ni licencia. **No se puede saber de dónde salieron: hay que reemplazarlas por fotos propias** (o por fotos con licencia libre, anotando aquí el autor, la fuente y la licencia). La mojarra roja no tiene imagen: queda sin foto hasta que se suba una real.
