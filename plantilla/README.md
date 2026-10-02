# Plantilla UNILLANOS · Sistema de Información de Autoevaluación

Prototipo HTML/CSS/JS de varias páginas (contenido de ejemplo) para llevar luego a Drupal.

## Páginas
| Archivo | Menú |
|---|---|
| index.html | Inicio (qué es la autoevaluación, proceso en 5 fases, accesos, galería, formulario Participa) |
| institucional-registro-calificado.html | Institucional → Registro calificado |
| institucional-autoevaluacion.html | Institucional → Autoevaluación (selector de sede, factores, valoración) y `#plan-mejoramiento` |
| programas.html | Programas → buscador (`#registro`, `#nacional`, `#internacional`, `#plan`, `?q=`) |
| programa-ejemplo.html | Ficha de programa (Ing. Electrónica): registro, autoevaluación nacional/internacional, plan |
| documentos.html | Normatividad (`#normatividad`) y Documentos base (`#documentos-base`) |
| participa.html | Herramientas de participación y formulario de recomendación |

## Archivos
- style.css: estilos (clases `.ua-*`, variables CSS).
- app.js: comportamiento (pensado para `Drupal.behaviors`, `Unillanos.attach(context)`).
- datos-ejemplo.js: datos inventados (`window.UnillanosData`).
- assets/: logos.
- herramientas/: scripts Python que generan las páginas (`python3 build.py`).
