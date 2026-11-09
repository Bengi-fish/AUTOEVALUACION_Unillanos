# Tema Unillanos

Tema del Sistema de Información de Autoevaluación de la Universidad de los
Llanos. Se generó con el starterkit de core (`vendor/bin/dr generate-theme`) y
se adaptó desde el prototipo `plantilla/` del repositorio.

## Origen

- Fuente visual: `plantilla/` en el commit `60eff63`. Es una referencia de solo
  lectura: el port es de una sola vía y los cambios del prototipo se portan a
  mano.
- `plantilla/style.css` se repartió en `css/tokens.css`, `css/base.css`,
  `css/motion.css` y un CSS por componente en `components/<nombre>/`. Se
  descartó el CSS que ninguna de las 6 páginas usa (megamenú, filtros de
  documentos, chip «Prototipo», variantes de botón sin uso).
- CSS plano, sin compilación. Las clases `ua-*` y los atributos `data-ua-*`
  del prototipo se conservan.

## Tipografía

Public Sans servida desde `fonts/` (variable, eje de peso `wght`, latin y
latin-ext). Archivos de `@fontsource-variable/public-sans` 5.3.0, licencia OFL
1.1 (`fonts/OFL-PublicSans.txt`). El sistema usa dos pesos, 400 y 600. DM Sans
se retiró del rediseño (sin `@font-face` ni referencias; sus archivos y su
licencia se borraron en el cierre). League Gothic
(título de la portada) no está incorporada: espera la confirmación del
diseñador.

## Estructura

- `css/`: tokens, fuentes, base global y movimiento compartido.
- `components/`: componentes de directorio único (SDC), cada uno con su
  `*.component.yml`, plantilla Twig, CSS y, si hace falta, JS.
- `js/`: ayudas compartidas de movimiento (librería `unillanos/motion`).
- `images/`: logos y favicon del prototipo.
- `templates/`: plantillas del starterkit más las propias del tema.

## Alcance

Las páginas públicas del demo (portada, programa, plan y `/documentos`) usan
plantillas de nodo o de página propia (`templates/content/`) más el servicio
los servicios de `unillanos_autoeval` que arman los datos de cada página
(`PaginaPrograma`, `FactoresPrograma`, `PlanMejoramientoPrograma`,
`DatosPortada`, `DocumentosPublicados`); no usan Views. El listado y buscador
de `/programas` también sale de un servicio (`ListadoProgramas`), no de Views. Los datos de ejemplo viven solo en el
módulo `unillanos_guia` (`/guia-estilos`).

## Logos de terceros e íconos de redes

Los logos son marcas de sus titulares. Se usan sin modificar (solo se
escalan), en la versión que publica la fuente oficial. No se redibujan ni se
cambian sus colores o proporciones.

| Archivo | Titular | Fuente oficial | Descargado |
|---|---|---|---|
| `images/logos/govco-blanco.svg` | GOV.CO (Gobierno de Colombia) | https://cdn.www.gov.co/v2/assets/images/logo.svg (CDN de https://www.gov.co/); versión para fondo oscuro. Fuente oficial. | 8 de noviembre de 2026 |
| `images/logos/unillanos-blanco.png` | Universidad de los Llanos | https://acreditacion.unillanos.edu.co/images/logos/trascendencia.png (sitio de la propia Universidad; 555 × 99 px, con fondo gris oscuro opaco incluido en el archivo) | 8 de noviembre de 2026 |
| `images/logos/mineducacion-vigilada-blanco.png` | Ministerio de Educación Nacional («Vigilada MinEducación») | https://acreditacion.unillanos.edu.co/images/logos/certificaciones.png (copia publicada por la Universidad; 298 × 100 px, blanco). **Pendiente:** reemplazar por el archivo oficial de https://www.mineducacion.gov.co/ cuando se obtenga | 8 de noviembre de 2026 |
| `images/logos/colombia-marca-pais.png` | Marca País «CO Colombia» (Procolombia / Gobierno de Colombia) | https://acreditacion.unillanos.edu.co/images/logos/m_pais.png (copia publicada por la Universidad; solo 50 × 45 px: se muestra a su tamaño natural, sin ampliarlo). **Pendiente:** reemplazar por el archivo oficial de https://www.colombia.co/ | 8 de noviembre de 2026 |

Los logos de la Universidad, de MinEducación y de CO Colombia no se pudieron
bajar de las páginas oficiales de sus titulares (no publican un archivo
descargable directo), por eso se usan las copias que la Universidad publica en
su sitio de Acreditación, byte a byte iguales (se compararon sus huellas
SHA-256). Los cuatro son marcas de sus titulares.

Íconos de redes (`images/redes/`: Facebook, X, Instagram y YouTube): Simple
Icons 13.21.0 (https://simpleicons.org/, https://cdn.jsdelivr.net/npm/simple-icons@13.21.0/icons/),
licencia CC0 1.0. Se pintan como máscara con `currentColor`, así que toman el
color de los tokens. Los nombres y las marcas de Facebook, X, Instagram y
YouTube pertenecen a sus titulares. Descargados el 8 de noviembre de 2026.
