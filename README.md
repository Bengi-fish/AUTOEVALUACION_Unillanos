# AUTOEVALUACION_Unillanos

Sistema de Información de Autoevaluación de la Universidad de los Llanos (UNILLANOS), en Drupal 11.

**Para empezar a desarrollar:** [`docs/IMPLEMENTACION_DRUPAL.md`](docs/IMPLEMENTACION_DRUPAL.md).
**Contexto para Claude Code:** [`CLAUDE.md`](CLAUDE.md).

## Estructura

| Carpeta | Contenido |
| --- | --- |
| `docs/fuentes/` | Documentos originales: Acuerdo CESU 01 de 2025, informe de autoevaluación de Ingeniería Electrónica 2018–2022 y plan de mejoramiento FO-GCL-20 de Ingeniería Electrónica (2024-2 – 2030). |
| `docs/mer/` | MER v2: diagrama (`MER-v2.png` / `.pdf`), versión en texto (`MER.md`) y diccionario de datos. |
| `docs/drupal/` | Traducción del MER a Drupal (tipos de contenido, campos, nombres de máquina). |
| `datos/semillas/` | Catálogos en CSV: lineamientos, factores/características/aspectos (CNA 2020, CESU 2025), condiciones del Decreto 1330, escala de cumplimiento, estamentos, sedes, facultades, programas, proyectos institucionales. |
| `datos/importacion/` | Datos por programa en CSV (por ahora, Ingeniería Electrónica). |
| `datos/herramientas/` | `plan_excel_a_csv.py`: convierte un Excel FO-GCL-20 en CSV. |
| `plantilla/` | Prototipo de alta fidelidad (HTML/CSS/JS). Abrir `plantilla/index.html`. |
| `drupal/` | Proyecto Drupal 11: configuración en `drupal/config/sync`, módulos propios (`unillanos_autoeval`, `unillanos_migrate`, `unillanos_guia`) y tema `unillanos` en `drupal/web`. |
| `deploy/` | Paquete de despliegue para la oficina de Sistemas: `desplegar.sh`, respaldos, ejemplos de servidor y `CHECKLIST.md`. |
| `scripts/` | `instalar.sh` (instalación en un equipo nuevo), `extraer-sitio-acreditacion.py`, `manuales-pdf.sh` y otros. |
| `docs/manuales/` | Manual de Acreditación, guía de despliegue y manual técnico. |

## Qué publica el sitio

Portal de Autoevaluación y Acreditación en `/` (carrusel, accesos y noticias); el SIAAI en `/siaai` con `/programas`, `/documentos` e `/institucional` (una sola página con la interfaz de un programa); `/noticias`, `/nuestro-equipo` y el formulario `/participa`. Para instalarlo en un equipo nuevo: [`docs/GUIA-OTRO-PC.md`](docs/GUIA-OTRO-PC.md). Para publicarlo en un servidor: [`docs/manuales/GUIA-DESPLIEGUE.md`](docs/manuales/GUIA-DESPLIEGUE.md).
