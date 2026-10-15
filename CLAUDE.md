# CLAUDE.md

## 1. Proyecto
Sistema de Información de Autoevaluación de la **Universidad de los Llanos (UNILLANOS)**: Drupal 11 con DDEV. Publica, por programa y a nivel institucional, los procesos de autoevaluación, las valoraciones por factor y característica, fortalezas y aspectos por mejorar, el plan de mejoramiento (FO-GCL-20) con su seguimiento, los documentos y un formulario de participación. Idioma del sitio, código comentado y documentación: **español**.

| Qué | Dónde |
|---|---|
| Composer root | `drupal/` |
| Docroot | `drupal/web` |
| Configuración | `drupal/config/sync` |
| Tema | `drupal/web/themes/custom/unillanos` |
| Módulos | `drupal/web/modules/custom/`: `unillanos_autoeval` (reglas de negocio), `unillanos_migrate` (CSV → Drupal), `unillanos_guia` (guía de estilos) |
| Datos | `datos/semillas/`, `datos/importacion/<programa>/` |
| Prototipo visual | `plantilla/` |
| Documentación | `docs/` (empieza por `docs/IMPLEMENTACION_DRUPAL.md`) |

Arranque: `ddev start`. Todo comando va con prefijo `ddev` desde la raíz del repositorio (`ddev drush cr`, `ddev drush cex -y`, `ddev composer …`). Tras cualquier cambio de configuración: `ddev drush cex -y` y commit de `drupal/config/sync`.

Antes de tocar código lee `docs/drupal/modelo-de-contenido.md` (nombres de máquina y campos: fuente de verdad), `docs/mer/MER.md` y `docs/mer/diccionario-datos.md`. Nunca edites `web/core`, `web/modules/contrib` ni `vendor/`.

## 2. Reglas de trabajo
- Una rama por fase.
- Commits con `git add` de **rutas explícitas** (nunca `git add .`); mostrar `git diff --cached --stat` antes y `git show --stat HEAD` + `git status --short` después.
- Sin firmas: ni `Co-Authored-By`, ni `Claude-Session`, ni «Generated with Claude Code». Autor: la identidad de git del usuario.
- **Esperar la confirmación del usuario antes de cada commit.**
- Nunca `push`, `merge`, `rebase` ni tocar `main`: los hace el usuario.
- Preguntar antes de acciones destructivas: `ddev delete`, `drush sql:drop`, `drush si` sobre una base con contenido, `migrate:rollback` de todo el grupo, `git push --force`, borrar archivos.
- No imprimir enlaces de `drush uli` ni contraseñas.
- Respaldos fuera del repositorio: `ddev export-db --file=$HOME/respaldos/<nombre>.sql.gz`.
- `plantilla/` (referencia visual) y `docs/fuentes/` (documentos originales) son **de solo lectura**.
- Los datos entran por migraciones desde CSV, nunca a mano.
- Cada cambio del MER se refleja en `MER.md`, `diccionario-datos.md` y `modelo-de-contenido.md`.

## 3. Criterios de calidad
- Componentes SDC con el mismo marcado que `plantilla/`; JS con `Drupal.behaviors` + `once()` y atributos `data-ua-*` del prototipo.
- Medir en Edge a **1440, 390 y 320 px** e inspeccionar las capturas reales, no solo las métricas.
- PHPCS (`Drupal,DrupalPractice`) con 0 errores y PHPStan **`--level=5`** (sin el flag corre en nivel 0):
  `ddev exec "cd drupal && vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/<modulo>/"`
  `ddev exec "cd drupal && vendor/bin/phpstan analyze --level=5 --no-progress web/modules/custom/<modulo>/"`
- Pruebas: `ddev exec "cd drupal && SIMPLETEST_DB=mysql://db:db@db/db SIMPLETEST_BASE_URL=http://localhost vendor/bin/phpunit -c web/core web/modules/custom/unillanos_autoeval/tests"` (y `unillanos_migrate`).
- Revisor de seguridad y accesibilidad en cada paso.
- Sin etiquetas visibles de «Pendiente»: lo vacío se omite.
- Los componentes **solo muestran valores guardados**; no calculan grados, destinos ni avances.
- Todo funciona para cualquier programa y modelo, no solo para Ingeniería Electrónica.

Reglas del dominio no obvias: el modelo se declara por proceso (`field_lineamiento`; CESU 2020 o CESU 2025) y no hay equivalencias entre ellos; el grado se calcula, no se digita; valoraciones solo para factor y característica.

## 4. Estado actual
- **Fase 6 (tema) casi cerrada**, rama `fase-6-tema`; último commit `280a1cf` (demo con datos reales de Ingeniería Electrónica, paso 5b).
- Antes del merge a `main`: este `CLAUDE.md` y el commit de documentación (`docs/IMPLEMENTACION_DRUPAL.md`, `docs/pendientes-acreditacion.md`, README del tema). El merge lo hace el usuario.
- **Fase 7** (`fase-7-vistas`): vistas y páginas (`/programas`, `/institucional`, etc.).
- **Fase 8** (`fase-8-webform-roles`): formulario Participa, roles y permisos (respaldo previo `antes-fase-8`).
- **Fase 9** (`fase-9-revision`): revisión antes de mostrar (accesibilidad, cifras contra el informe, desactivar `unillanos_guia`, control de acceso de `public://`).

## 5. Pendientes con Acreditación
Detalle en `docs/pendientes-acreditacion.md`.
- Regla de redondeo de grados (huecos 4,7–4,8 y 3,9–4,0 de la Tabla 3.1).
- Regla del 4 (destino de los hallazgos).
- Modelo de los procesos nuevos (se prevé CESU 2025).
- Textos editoriales de la portada y de `/documentos` (se conservan los del prototipo).
- Visibilidad pública de los documentos.

---

## Estándares de código y seguridad (heredado)

### Seguridad
- Sanitizar toda entrada de usuario (`Xss::filter()`, `Html::escape()`); en Twig, no usar `|raw` con texto de usuario (el único uso actual es el trazo de SVG, constante de la plantilla, de `components/icon`).
- Tokens CSRF en formularios y enlaces; comprobar acceso con manejadores de acceso y permisos (también en entidades propias y en cada medio mostrado: `accessCheck(TRUE)` no basta en consultas de media).
- Validar subidas de archivos con validadores de archivo.
- Sin SQL a mano: Entity Query o la API de base de datos con marcadores.
- Nunca confirmar claves, contraseñas ni secretos; usar variables de entorno.
- Prohibido: editar `web/core`, `web/modules/contrib` y `vendor/`; `chmod 777`; `push --force` a `main`.

### PHP
- PHP 8.4 (compatible 8.3+); estándar Drupal, 2 espacios, líneas de 120 caracteres (comentarios de 80, con punto final).
- Espacio de nombres PSR-4 `Drupal\{modulo}\{subdirectorio}`; plugins con **atributos PHP 8**, no anotaciones.
- Inyección de dependencias por constructor con promoción de propiedades; nada de `\Drupal::service()` en clases.
- Propiedades tipadas; PHPDoc en propiedades y métodos; `{@inheritdoc}` en los heredados; excepciones específicas con `@throws`.
- `declare(strict_types=1);` en el PHP nuevo.
- Nombres: clases PascalCase, métodos camelCase, variables locales y parámetros de constructor clásico snake_case, constantes MAYÚSCULAS, casos de enum PascalCase.
- Ficheros de más de 1500 líneas se dividen (traits o servicios). `PresentadorAutoevaluacion` (1553 líneas) está cerca del umbral: se vigila en la Fase 7 y no se divide ahora.

### Twig, CSS y JS (tema)
- Twig: escape automático siempre; textos traducibles con `|t`; componentes SDC en `components/`.
- SCSS/CSS con BEM, 2 espacios, sin `!important` salvo necesidad.
- JS: ES6+, `const`/`let`, sin `var` ni `console.log`; `Drupal.behaviors` con `once()`.

### Comandos habituales (siempre con `ddev`)
- Caché y estado: `ddev drush cr` · `ddev drush status` · `ddev drush watchdog:show --count=20`
- Configuración: `ddev drush cex -y` · `ddev drush cim -y` · `ddev drush cex --diff` · `ddev drush config:status` · `ddev drush config:get <nombre>`
- Módulos: `ddev drush en <modulo>` · `ddev drush pm:list --status=enabled` · `ddev composer require drupal/<modulo>`
- Base de datos: `ddev drush updb -y` · `ddev drush field:info <entidad> <bundle>`
- Migraciones: `ddev drush migrate:import --group=unillanos` · `migrate:rollback` (preguntar antes si es de todo el grupo)
- Alias tras migrar: `ddev drush pathauto:aliases-generate create canonical_entities:node`
- Si faltan phpcs/phpstan/phpunit: `ddev composer require --dev drupal/core-dev`
