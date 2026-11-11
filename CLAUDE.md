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
- Sin etiquetas visibles de «Pendiente»: lo vacío se omite. Excepción deliberada: la tarjeta de programa (`program-card`) reserva un **hueco** (misma altura, sin texto, `aria-hidden="true"`) para la línea «duración · sede» y la píldora «Con autoevaluación» cuando no hay dato, para que todas las tarjetas tengan la misma estructura; nunca se escribe «—», «Sin dato» ni «Pendiente».
- Los componentes **solo muestran valores guardados**; no calculan grados, destinos ni avances.
- Todo funciona para cualquier programa y modelo, no solo para Ingeniería Electrónica.

Reglas del dominio no obvias: el modelo se declara por proceso (`field_lineamiento`; CESU 2020 o CESU 2025) y no hay equivalencias entre ellos; el grado se calcula, no se digita; valoraciones solo para factor y característica.

## 4. Estado actual
- **Fase 6 (tema)** fusionada en `main` (PR #1).
- **Fase 7 (vistas y páginas) cerrada a falta del merge**, rama `fase-7-vistas`: `/programas` con listado y buscador (servicio `ListadoProgramas`, sin Views), página de programa sin autoevaluación, menús por `links.menu.yml` y el antiguo presentador dividido en servicios (`ListadoProgramas`, `PaginaPrograma`, `FactoresPrograma`, `ProgramasYProcesos`, `PlanMejoramientoPrograma`, `DocumentosPublicados`, `DatosPortada`, `EnlacesDelPie`). `/institucional` no se creó. El merge a `main` lo hace el usuario. Ajustes pendientes: `docs/pendientes-acreditacion.md`, sección 22.
- **Rediseño del micrositio (rama `rediseno-micrositio`, etapas 1 a 8 y cierre; sin merge, lo hace el usuario).** Solo CSS y marcado compatible: las clases `ua-*` y los atributos `data-ua-*` no se renombran y la huella de contenido de cada etapa solo admite diferencias deliberadas.
  - **Tokens** (`css/tokens.css`): rojo marca `#e30613` (solo decorativo o grande), rojo texto `#a8040e`, rojo oscuro `#85030b`, verde azulado `#174349`/`#0a2226`, carbón `#1d1d1b`, grises `#52504a`/`#6b6860`, borde `#d3d0c7`, superficies `#f1f0ec`/`#ebe9e4`, tintes por facultad (`--facultad-*`). Public Sans 400 y 600 (DM Sans retirada; League Gothic no se incorpora hasta que el diseñador lo confirme). Radios 4/8/48/píldora, sin sombras, objetivos táctiles ≥ 44 px, un solo anillo de foco.
  - **Componentes nuevos o reescritos:** `media` y `kv-list` (4a), `doc-filter` (6, filtro de `/documentos` con conteos), `cta-row` con segundo botón (7); `program-card` con tinte por facultad (`ua-prog--{sigla}`, 5); `hero`, `stats`, `steps`, `factor-card`, `factor-tabs`, `plan-table` y `doc-list` restilizados. Filtro Twig `|sin_cortes` (módulo, cierre) para que un rango de años no se parta al cambiar de línea.
  - **Decisiones:** hero y ficha lateral de 360 px (4a); plan de mejoramiento en tarjetas bajo 760 px (4b); tarjetas de facultad con conteo no implementadas (faltan descripción e imagen, `docs/pendientes-acreditacion.md`, sección 23); `/admin/config/unillanos/micrositio` edita cabecera, franja superior y pie (Etapa 8); `hyphens: auto` solo en párrafos de varias líneas, nunca en títulos, etiquetas, botones, migas ni tarjetas.
  - **Detalle:** `docs/IMPLEMENTACION_DRUPAL.md` (sección «Rediseño del micrositio»).
- **Fase 8 en curso** (rama `fase-8-acreditacion`; el merge lo hace el usuario). **8.1 hecha:** rol `acreditacion` («Acreditación», un solo rol que crea y edita todos los programas y sus procesos, valoraciones, hallazgos, planes, metas, seguimientos y documentos; no borra, no accede a configuración, módulos, estructura, personas ni informes; sin ningún `administer …`) y tres permisos propios en `unillanos_autoeval.permissions.yml`: `edit unillanos micrositio` (la ruta del formulario del micrositio ya no exige `administer site configuration`), `access acreditacion panel` y `change publication status on acreditacion content` (permite editar «Publicado» vía `hook_entity_field_access`, solo en los tipos de autoevaluación y el medio `documento`). Las cuentas las crea el administrador a mano (`register: admin_only`). **8.2 hecha:** panel `/admin/acreditacion` (permiso `access acreditacion panel`; lista los programas publicados con sus accesos a las pantallas de edición y creación de Drupal, precargando programa, proceso o plan con `?programa=`, `?proceso=` o `?plan=` vía `hook_entity_prepare_form`; enlace en el menú como hijo de «Administración»). **8.3 hecha:** modo de formulario `acreditacion` (ficha del programa y documento) con solo los campos necesarios, ayudas en lenguaje sencillo y rutas `/admin/acreditacion/programa/{node}/ficha`, `/admin/acreditacion/documento/{media}/editar` y `/admin/acreditacion/documento/nuevo`, con el control de acceso de editar; el formulario completo de Drupal no cambia y el panel enlaza a los simples. Pendiente de la Fase 8: formulario Participa y los roles `editor_documentos` y `gestor_participacion` (respaldo previo `antes-fase-8`).
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
- Ficheros de más de 1500 líneas se dividen (traits o servicios). El presentador único se dividió en la Fase 7: el servicio más grande es `PlanMejoramientoPrograma` (421 líneas), lejos del umbral.

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

## 6. Trabajo en equipo (cada persona en su propio equipo)
- **Una rama por tarea**, creada desde `main` actualizado (`git pull` primero). Nunca se trabaja en `main`.
- **La identidad es la de cada persona**: Claude usa el `user.name` y `user.email` de git de quien lo ejecuta; no se cambian ni se firman commits en nombre de otra persona.
- Claude no hace `push`, PR ni merge: la persona sube su rama y abre el PR. Tras un `git pull`: `ddev composer install`, `ddev drush updb -y`, `ddev drush cim -y`, `ddev drush cr`. Antes de cada commit con cambios de configuración: `ddev drush cex -y`.
- **Las pruebas nunca bajan**: el número de pruebas y aserciones es igual o mayor que el de antes de la tarea; no se borran aserciones para que pasen.
- PHPCS (`Drupal,DrupalPractice`) sin errores y PHPStan `--level=5` limpio en cada módulo tocado.
- Sin `|raw` en Twig con texto de usuario. `plantilla/` y `docs/fuentes/` son de solo lectura. Respaldos de la base fuera del repositorio (`$HOME/respaldos/`).
- Instalación desde cero y guía de uso: `scripts/instalar.sh` y `docs/GUIA-OTRO-PC.md`.
- El agente `revisor-unillanos` (`.claude/agents/`) se creó para el rediseño y la Fase 8; sus reglas citan esas etapas. Úsalo antes de cada commit; sus criterios de huella y capturas aplican solo a cambios de tema.
