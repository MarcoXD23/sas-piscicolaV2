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

Toda imagen que traiga la app debe tener origen claro y anotarse aquí: archivo, peso, autor, fuente, licencia y fecha de descarga. No se agregan imágenes sin esos datos.

**Verificación:** antes de usar cada imagen se consultaron en la API de Wikimedia Commons sus campos `Artist` y `LicenseShortName`, y las categorías y plantillas de su página. Ninguna tenía aviso de copyright disputado, borrado propuesto ni falta de permiso. Todas se descargaron y verificaron el **8 de octubre de 2026** (consulta de las 06:19–06:21 UTC). Se convirtieron a WebP sin metadatos, de menos de 200 KB.

### Fondos (CC0 o dominio público: no exigen crédito)

Detrás de cada uno va un velo oscuro de la marca para que el texto y los formularios se lean bien.

| Archivo | Peso | Uso | Autor | Fuente | Licencia |
|---|---|---|---|---|---|
| `fondo-login.webp` | 81 KB | Ingreso, registro y recuperación de contraseña | Alabama Extension | [Fish Farming Pond - 52271678987.jpg](https://commons.wikimedia.org/wiki/File:Fish_Farming_Pond_-_52271678987.jpg) | [CC0](https://creativecommons.org/publicdomain/zero/1.0/) |
| `fondo-estanque.webp` | 127 KB | Saludo de Inicio | USFWS Mountain Prairie | [Full Raceway at Hotchkiss National Fish Hatchery (51270942319).jpg](https://commons.wikimedia.org/wiki/File:Full_Raceway_at_Hotchkiss_National_Fish_Hatchery_(51270942319).jpg) | Dominio público |

`fondo-login.webp` está reducido a 1280 × 853 y suavizado (va detrás del velo); `fondo-estanque.webp`, recortado a 1600 × 600.

### Imágenes de referencia de especies (`especies/`)

Se muestran mientras la finca no suba fotos propias (Especies → Fotos). La ficha muestra el crédito debajo de la imagen: tipo (foto o ilustración), autor, licencia con enlace a su texto, enlace a la fuente y, cuando aplica, una nota. Todas están en 1200 × 750 (16:10).

| Archivo | Peso | Autor | Fuente | Licencia | Pie en la ficha |
|---|---|---|---|---|---|
| `mojarra-roja.webp` | 97 KB | Takeaway | [Pla thapthim.jpg](https://commons.wikimedia.org/wiki/File:Pla_thapthim.jpg) (tilapia roja híbrida) | [CC BY-SA 3.0](https://creativecommons.org/licenses/by-sa/3.0/) | Foto · «imagen recortada y reducida» |
| `mojarra-negra.webp` | 75 KB | Duane Raver, U.S. Fish and Wildlife Service | [Tilapia oreochromis niloticus fish.jpg](https://commons.wikimedia.org/wiki/File:Tilapia_oreochromis_niloticus_fish.jpg) | Dominio público | **Ilustración** (recorte alrededor del pez) |
| `cachama-blanca.webp` | 146 KB | w_endo (iNaturalist) | [Piaractus brachypomus 208962529.jpg](https://commons.wikimedia.org/wiki/File:Piaractus_brachypomus_208962529.jpg) | [CC0](https://creativecommons.org/publicdomain/zero/1.0/) | Foto |
| `bocachico.webp` | 155 KB | Zuluaga-Gómez A., Ictiología Universidad Católica de Oriente | [Prochilodus magdalenae (16197296986).jpg](https://commons.wikimedia.org/wiki/File:Prochilodus_magdalenae_(16197296986).jpg) | [CC BY 2.0](https://creativecommons.org/licenses/by/2.0/) | Foto · «imagen recortada y reducida» |
| `bagre-rayado.webp` | 119 KB | Whaldener Endo | [Pseudoplaystoma duo.jpg](https://commons.wikimedia.org/wiki/File:Pseudoplaystoma_duo.jpg) | [CC0](https://creativecommons.org/publicdomain/zero/1.0/) | «Otro bagre del mismo género (Pseudoplatystoma), no la especie exacta» |
| `trucha-arcoiris.webp` | 45 KB | USFWS Mountain Prairie | [Rainbow Trout (Oncorhynchus mykiss) Gavins Point.jpg](https://commons.wikimedia.org/wiki/File:Rainbow_Trout_(Oncorhynchus_mykiss)_Gavins_Point.jpg) | Dominio público | «Ejemplar juvenil» (con bordes negros, su fondo original, para que la tarjeta no corte el pez) |

- **CC BY y CC BY-SA** (mojarra roja y bocachico): exigen autor, licencia con enlace a su texto, enlace a la fuente e indicar los cambios. Por eso su pie dice «imagen recortada y reducida». Con CC BY-SA, si se hicieran más cambios, la versión modificada debe compartirse con la misma licencia.
- **Bagre rayado:** no hay fotos libres de *Pseudoplatystoma magdaleniatum*. La imagen es de dos bagres del mismo género (*P. tigrinum* arriba y *P. punctifer* abajo).
- **Para cambiar una imagen:** verifica antes su licencia en Commons (`Artist`, `LicenseShortName`, sin avisos de copyright ni de borrado), reemplaza el archivo (WebP, menos de 200 KB) y actualiza esta tabla con la fecha de descarga y el campo `foto` de la especie en `app/Support/CatalogoEspecies.php`.
