# Plantilla UNILLANOS · Sistema de Información de Autoevaluación

Prototipo HTML/CSS/JS de varias páginas (contenido de ejemplo) para llevar luego a Drupal.

## Páginas (menú plano: Inicio · Institucional · Programas · Documentos · Participa)
| Archivo | Contenido |
|---|---|
| index.html | Inicio |
| institucional.html | Pestañas: Registro calificado (`#registro-calificado`), Autoevaluación (`#autoevaluacion`, con selector de sede) y Plan de mejoramiento (`#plan-mejoramiento`) |
| programas.html | Buscador de programas (`?q=`) |
| programa-ejemplo.html | Pestañas: Registro calificado (`#registro`), Autoevaluación Nacional/Internacional (`#nacional`, `#internacional`) y Plan de mejoramiento (`#plan`) |
| documentos.html | Pestañas: Normatividad (`#normatividad`) y Documentos base (`#documentos-base`) |
| participa.html | Herramientas de participación y formulario de recomendación |

El plan de mejoramiento vive solo en su pestaña; cada factor enlaza a sus acciones (filtro por factor).
Las pestañas se animan (indicador deslizante, entrada direccional, cascada, barras que se llenan) y respetan `prefers-reduced-motion`.

## Archivos
- style.css: estilos (clases `.ua-*`, variables CSS).
- app.js: comportamiento (pensado para `Drupal.behaviors`, `Unillanos.attach(context)`).
- datos-ejemplo.js: datos inventados (`window.UnillanosData`).
- assets/: logos.
- herramientas/: scripts Python que generan las páginas (`python3 build.py`).
