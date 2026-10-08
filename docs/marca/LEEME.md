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

## Imágenes de la app (`public/images/`)

Toda imagen que traiga la app debe tener origen claro y anotarse aquí: archivo, peso, autor, fuente y licencia. No se agregan imágenes sin esos datos.

### Fondos (ilustraciones propias)

Dibujados en SVG para este proyecto, con los colores de la marca (`marca-*` de `resources/css/app.css`). Son parte del código del proyecto: no vienen de ningún banco de imágenes.

| Archivo | Peso | Uso |
|---|---|---|
| `fondo-login.svg` | 3 KB | Fondo del ingreso, el registro y la recuperación de contraseña (con un velo oscuro para el contraste). |
| `fondo-estanque.svg` | 2 KB | Fondo del saludo en Inicio (con un velo a la izquierda, donde va el texto). |

### Fotos de referencia de especies (`especies/`)

Se muestran mientras la finca no suba fotos propias (Especies → Fotos); la ficha muestra el crédito debajo de la foto. Todas son de Wikimedia Commons, convertidas a WebP de 1200 × 750 (16:10) sin metadatos. La de la mojarra negra es un recorte de la ilustración; la de la trucha lleva bordes negros (el fondo original) para que la tarjeta no corte el pez.

| Archivo | Peso | Autor | Fuente | Licencia |
|---|---|---|---|---|
| `mojarra-roja.webp` | 97 KB | Takeaway | [Pla thapthim.jpg](https://commons.wikimedia.org/wiki/File:Pla_thapthim.jpg) (tilapia roja híbrida) | [CC BY-SA 3.0](https://creativecommons.org/licenses/by-sa/3.0) |
| `mojarra-negra.webp` | 75 KB | Duane Raver, U.S. Fish and Wildlife Service | [Tilapia oreochromis niloticus fish.jpg](https://commons.wikimedia.org/wiki/File:Tilapia_oreochromis_niloticus_fish.jpg) | Dominio público |
| `cachama-blanca.webp` | 146 KB | w_endo (iNaturalist) | [Piaractus brachypomus 208962529.jpg](https://commons.wikimedia.org/wiki/File:Piaractus_brachypomus_208962529.jpg) | [CC0](https://creativecommons.org/publicdomain/zero/1.0/) |
| `bocachico.webp` | 155 KB | Zuluaga-Gómez A., Ictiología Universidad Católica de Oriente | [Prochilodus magdalenae (16197296986).jpg](https://commons.wikimedia.org/wiki/File:Prochilodus_magdalenae_(16197296986).jpg) | [CC BY 2.0](https://creativecommons.org/licenses/by/2.0) |
| `bagre-rayado.webp` | 119 KB | Whaldener Endo | [Pseudoplaystoma duo.jpg](https://commons.wikimedia.org/wiki/File:Pseudoplaystoma_duo.jpg) | [CC0](https://creativecommons.org/publicdomain/zero/1.0/) |
| `trucha-arcoiris.webp` | 45 KB | USFWS Mountain Prairie | [Rainbow Trout (Oncorhynchus mykiss) Gavins Point.jpg](https://commons.wikimedia.org/wiki/File:Rainbow_Trout_(Oncorhynchus_mykiss)_Gavins_Point.jpg) | Dominio público |

- **Bagre rayado:** no hay fotos libres de *Pseudoplatystoma magdaleniatum*. La foto es de dos bagres del mismo género (*P. tigrinum* arriba y *P. punctifer* abajo), y la ficha lo dice.
- **CC BY y CC BY-SA** exigen mostrar el crédito: lo hace la ficha (autor, licencia y enlace a la fuente). Con CC BY-SA, si se modifica la imagen (más allá del recorte y la conversión), la versión modificada debe compartirse con la misma licencia.
- **Para cambiar una foto:** reemplaza el archivo (WebP, menos de 200 KB) y actualiza esta tabla y el campo `foto` de la especie en `app/Support/CatalogoEspecies.php`.
