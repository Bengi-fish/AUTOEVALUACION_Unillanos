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
- `unillanos_guia.links.menu.yml`: menú principal **temporal** (ver abajo).

## Menú temporal

Los enlaces Institucional, Programas, Documentos y Participa se declaran en
`unillanos_guia.links.menu.yml` con las URL definitivas, que dan 404 hasta
la Fase 7. «Inicio» lo aporta el perfil `standard`.

**Pendiente obligatorio de la Fase 7:** mover el menú fuera de este módulo,
porque este módulo se desactiva en la Fase 9 y el menú desaparecería con él.
