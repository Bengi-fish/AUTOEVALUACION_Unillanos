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

DM Sans servida desde `fonts/` (variable, ejes de peso y tamaño óptico
`[opsz,wght]`, latin y latin-ext). Archivos de `@fontsource-variable/dm-sans`
5.3.0, licencia OFL 1.1 (`fonts/OFL.txt`). Con el eje `opsz` los títulos grandes
tienen el mismo trazo (y el mismo ancho) que con la hoja de Google Fonts del
prototipo; ocupa unos 38 KiB más que la variante solo de peso (unos 25 KiB más
en la práctica, porque el navegador solo descarga latin-ext si hace falta).

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
