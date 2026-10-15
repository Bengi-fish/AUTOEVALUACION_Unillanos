# Unillanos Guía de estilos

Muestra en `/guia-estilos` todos los componentes SDC del tema `unillanos`,
con datos de ejemplo. Sirve para revisar el diseño contra `plantilla/`
(1440 px y 390 px) y para que cada grupo de componentes tenga un ejemplo
vivo.

## Solo desarrollo

- La ruta es pública (`access content`) mientras dura el desarrollo.
- Los datos son inventados. `data/datos-ejemplo.json` es una copia de
  `plantilla/datos-ejemplo.js` convertida a JSON; esos datos viven **solo
  aquí**, nunca en páginas reales.
- Se desactiva antes de mostrar el sitio (Fase 9). Ver
  `docs/pendientes-acreditacion.md`, sección 7.

## Estructura

- `templates/guia-estilos.html.twig`: portada de la guía e índice.
- `templates/grupos/`: una plantilla por grupo de componentes. Cada grupo se
  añade cuando se construye.
- `css/guia.css`: solo la maquetación de las muestras (prefijo `ug-`).
- `src/Service/DatosEjemplo.php`: lee el JSON.

## Menú

El menú principal ya no vive en este módulo: pasó a
`unillanos_autoeval.links.menu.yml` (Programa de Electrónica y Documentos).
Las muestras de la guía conservan los enlaces de ejemplo a `/institucional`,
`/programas`, `/documentos` y `/participa`, que pueden dar 404.
