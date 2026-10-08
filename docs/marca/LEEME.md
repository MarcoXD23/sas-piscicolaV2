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

## Fotos

La app no trae fotos de fondo ni de especies por defecto: no se pudo documentar su origen. Si se agrega una foto, debe tener licencia clara y anotarse aquí (archivo, peso, autor, fuente, licencia).

Las fotos de especies las sube cada finca (Especies → Fotos), con su autor y su licencia. Los fondos del ingreso y del saludo de inicio son degradados de CSS con los colores de la marca.
