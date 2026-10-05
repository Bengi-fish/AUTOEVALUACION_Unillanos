# Guía de implementación en Drupal · paso a paso

Sistema de Información de Autoevaluación · Universidad de los Llanos

Esta guía lleva el proyecto desde el repositorio (prototipo + MER + documentos fuente) hasta un sitio Drupal funcionando en tu computador, con el contenido real de Ingeniería Electrónica cargado. Se trabaja con **Claude Code** abierto en la raíz del repositorio, dentro de Ubuntu (WSL).

Todas las fases siguen **el mismo ciclo** (sección 2). Cada fase indica qué hace Claude, qué haces tú y cómo se sabe que terminó. Si algo no está en el ciclo, no se hace.

---

## 0. ¿Ya podemos empezar?

**Sí.** Para arrancar el desarrollo local ya tenemos lo necesario:

| Insumo | Estado | Dónde está |
|---|---|---|
| Modelo de datos (MER v2, 20 entidades) | Listo | `docs/mer/` |
| Traducción del MER a Drupal | Listo | `docs/drupal/modelo-de-contenido.md` |
| Diseño visual y comportamiento (prototipo) | Listo | `plantilla/` |
| Estructura del plan de mejoramiento (FO-GCL-20) | Listo, con conversor a CSV | `docs/fuentes/` + `datos/herramientas/` |
| Modelo CNA 2020 (12 factores, 48 características) y CESU 01 de 2025 (12 / 51 / 70) | Listo como CSV | `datos/semillas/elementos_modelo.csv` |
| Datos reales de un programa (Ingeniería Electrónica) | Valoraciones por factor + plan completo | `datos/importacion/ingenieria-electronica/` |

**Pendiente por confirmar con la Universidad.** Nada de esto bloquea el trabajo local, pero sí la puesta en producción:

1. **Servidor:** dónde se publicará el sitio, con qué versión de PHP y de base de datos, y quién lo administra.
2. **Inicio de sesión:** si los editores entran con cuentas institucionales (SSO/LDAP) o con usuarios propios de Drupal.
3. **Roles:** quién edita qué. Por ejemplo, si cada director de programa edita solo lo suyo.
4. **Datos oficiales:** códigos SNIES, resoluciones de registro calificado y acreditación, y municipio de cada sede.
5. **Reglas de la escala:** cómo se clasifica una nota que cae entre rangos (por ejemplo 4,75 o 3,95). La Tabla 3.1 del informe deja esos huecos.
   - Implementado provisionalmente (Fase 4), **pendiente de confirmar con Acreditación**: el grado se calcula redondeando la nota a un decimal antes de compararla con los rangos.
   - Implementado provisionalmente, **pendiente de confirmar con Acreditación**: el destino sugerido de un hallazgo es `plan_mejoramiento` si la valoración es menor que 4, `plan_accion` si es exactamente 4 y `ninguno` si es mayor que 4 (siempre editable).
   - **Pendiente de confirmar con Acreditación:** qué pasa con "=4" cuando las valoraciones son decimales (por ejemplo 4,27 en un factor o característica). Hoy solo 4,00 exacto va a `plan_accion`; 4,27 sugiere `ninguno`.
6. **Hallazgos de otros programas:** por ahora solo existe el informe de Ingeniería Electrónica.

**Versión de Drupal:** Drupal **11** (rama estable con soporte largo y con todos los módulos que usamos). Si cuando empiecen ya salió Drupal 12, quédense en 11 hasta que `webform`, `paragraphs` y los módulos de migración publiquen versiones estables para 12.

---

## 1. Cómo se organiza el repositorio

```
AUTOEVALUACION_Unillanos/
├── CLAUDE.md                  ← contexto que Claude Code lee al abrir el proyecto
├── README.md
├── .gitignore
├── .ddev/                     ← entorno local (se crea con `ddev config`)
├── docs/
│   ├── IMPLEMENTACION_DRUPAL.md   ← esta guía
│   ├── fuentes/               ← documentos que nos entregaron (no se editan)
│   ├── mer/                   ← MER.md, diccionario-datos.md, MER-v2.png / .pdf
│   └── drupal/
│       └── modelo-de-contenido.md ← MER → tipos de contenido, campos, nombres de máquina
├── datos/
│   ├── semillas/              ← catálogos comunes a todos los programas (CSV)
│   ├── importacion/<programa>/← datos de cada programa (CSV)
│   └── herramientas/          ← conversor Excel FO-GCL-20 → CSV
├── plantilla/                 ← prototipo HTML/CSS/JS (referencia visual, no se borra)
└── drupal/                    ← proyecto Drupal: composer.json, web/, config/sync/
```

Reglas de oro:

- **Lo que va a Git:** código propio (`drupal/web/modules/custom`, `drupal/web/themes/custom`), `composer.json` y `composer.lock`, la configuración exportada (`drupal/config/sync`), los CSV y la documentación.
- **Lo que NO va a Git:** `vendor/`, el núcleo de Drupal y los módulos descargados (se reinstalan con `composer install`), la base de datos, los archivos subidos (`sites/default/files`) y `.serena/`. El `.gitignore` ya lo cubre.
- **El contenido no se copia a mano entre computadores:** se reconstruye importando los CSV (fase 5).

---

## 2. El ciclo de trabajo (igual en todas las fases)

Cada fase es una rama, un conjunto de cambios verificado y un merge a `main`. Reparto de responsabilidades:

| Quién | Qué hace |
|---|---|
| **Claude** | Crea la rama, instala, genera código y configuración, corre la verificación estándar y **propone** el commit. |
| **Tú** | Preparas el punto de partida, revisas el resultado, confirmas el commit, y haces `push` y `merge`. |

Claude nunca hace `push`, `merge` ni cambios en `main`.

### 2.1 Antes de empezar la fase [TÚ]

```bash
cd ~/proyectos/AUTOEVALUACION_Unillanos
git checkout main
git pull
git status                  # debe decir "working tree clean"
```

Si `git status` no está limpio, resuélvelo antes de seguir. Si la fase lo indica, haz la copia de seguridad (sección 2.7). Después abre Claude:

```bash
claude
```

Una sesión de Claude por fase. Si traes una sesión de la fase anterior, ciérrala con `/exit` y abre una nueva: el contexto del proyecto lo recupera de `CLAUDE.md`.

### 2.2 Reglas del ciclo (Claude las aplica en todas las fases)

Cada prompt de fase termina con la frase "Aplica las REGLAS DEL CICLO de la sección 2.2 de `docs/IMPLEMENTACION_DRUPAL.md`". Estas son las reglas:

```
REGLAS DEL CICLO
1. Rama: crea la rama de la fase desde main actualizado. Nunca trabajes sobre main.
2. Alcance: haz solo lo que pide esta fase. Lo que quede fuera, anótalo y avísame.
3. Verificación: al terminar corre la verificación estándar (sección 2.4) y muéstrame el resultado.
4. Commit: antes de commitear muéstrame `git status --short`, un resumen de cambios y el mensaje
   propuesto, y espera mi confirmación. Usa `git add` con rutas explícitas, nunca `git add -A`.
5. Sin firmas: el mensaje de commit no lleva Co-Authored-By, Claude-Session, "Generated with
   Claude Code" ni ninguna atribución a Claude. Usa mi identidad de Git; no uses -c user.name
   ni -c user.email.
6. No hagas git push, merge, rebase ni cambios en main. Eso lo hago yo.
7. Pregunta antes de lo destructivo: ddev delete, drush sql:drop, migrate:rollback del grupo
   completo, push --force, borrar archivos de docs/fuentes.
8. No imprimas enlaces de `drush uli` ni contraseñas.
9. Termina con una lista corta: qué hiciste, qué verificaste, qué falló o quedó pendiente.
```

### 2.3 Qué hace Claude en cada fase

1. Lee `CLAUDE.md` y los documentos que cita el prompt.
2. Crea la rama de la fase.
3. Ejecuta el trabajo del prompt.
4. Corre la verificación estándar (2.4) y los comandos de verificación propios de la fase.
5. Propone el commit (archivos + mensaje) y **se detiene**.

### 2.4 Verificación estándar (la corre Claude al final de cada fase)

```bash
ddev drush cr
ddev drush cex -y
ddev drush config:status                                  # sin diferencias
ddev drush status                                         # base de datos conectada, Drupal 11.x
curl -sI https://autoeval-unillanos.ddev.site | head -1   # 200 o 302
git status --short                                        # solo los archivos esperados de la fase
```

Si `config:status` muestra diferencias, la fase **no está terminada**: la configuración no quedó exportada y no llegaría a GitHub ni a tu compañero.

### 2.5 Qué revisas tú antes de confirmar [TÚ]

1. Lees el informe de Claude: lista de lo hecho, resultado de la verificación y pendientes.
2. Abres el sitio (`ddev launch`) y compruebas el criterio **"Hecho cuando"** de la fase.
3. Miras el tamaño del cambio: `git status --short` debe mostrar decenas de archivos, no miles. Si ves `vendor/`, `web/core/` o `settings.ddev.php`, detente.
4. Lees el mensaje de commit propuesto (sin firmas) y respondes "confirmo el commit". Si prefieres hacerlo tú:

```bash
git add <rutas>
git commit -m "Fase N: ..."
```

### 2.6 Push y merge a `main` [TÚ]

Cada fase termina con este bloque, con el nombre de su rama:

```bash
git push -u origin <rama-de-la-fase>
git checkout main
git pull
git merge <rama-de-la-fase>
git push
git branch -d <rama-de-la-fase>
```

Con tu compañero en el proyecto, el merge se hace con **Pull Request** en GitHub (botón "Compare & pull request", revisión, "Merge pull request") y luego `git checkout main && git pull`. No empieces la fase siguiente sin haber fusionado la anterior: cada rama nueva debe salir de un `main` que ya contenga lo anterior.

Si `git push` pide credenciales, usa un token personal de GitHub o `gh auth login`; la contraseña de la cuenta ya no funciona.

### 2.7 Copia de seguridad (antes de las fases 3, 5 y 8) [TÚ]

Son las que más escriben en la base de datos. La copia queda **fuera** del repositorio:

```bash
mkdir -p ~/respaldos
ddev export-db --file=$HOME/respaldos/antes-fase-N.sql.gz
```

### 2.8 Si algo sale mal (antes del commit)

| Situación | Qué hacer |
|---|---|
| Quieres deshacer cambios en archivos versionados | `git restore .` |
| Hay archivos nuevos que no quieres | `git clean -nd` (solo muestra); si es lo esperado, `git clean -fd` |
| La base de datos quedó mal | `ddev import-db --file=$HOME/respaldos/antes-fase-N.sql.gz`, luego `ddev drush cr` |
| Abandonar la fase completa | `git checkout main` y `git branch -D <rama-de-la-fase>` |
| No entiendes un error | Pégalo a Claude: "Lee docs/IMPLEMENTACION_DRUPAL.md fase N y resuelve este error: …". Sin tocar `main`. |

---

## 3. Mapa de fases

| Fase | Rama | Qué entrega | Hecho cuando | Respaldo |
|---|---|---|---|---|
| 0 | (una vez) | Computador preparado | Comandos de comprobación OK | No |
| 1 | `fase-1-instalacion` | Drupal 11 con DDEV | El sitio abre y `config/sync` tiene `.yml` | No |
| 2 | `fase-2-modulos` | Módulos contrib y ajustes regionales | `config:status` sin diferencias | No |
| 3 | `fase-3-modelo` | Tipos de contenido, taxonomías, paragraphs, media | 7 vocabularios, 8 tipos, 3 paragraphs, media `documento` | Sí |
| 4 | `fase-4-reglas` | Módulo `unillanos_autoeval` | Grado se calcula; pruebas pasan | No |
| 5 | `fase-5-importacion` | Migraciones y datos de Ingeniería Electrónica | `migrate:status` sin errores; 17 metas | Sí |
| 6 | `fase-6-tema` | Tema `unillanos` desde la plantilla | Se ve igual que el prototipo | No |
| 7 | `fase-7-vistas` | Vistas y páginas | 6 páginas con datos reales | No |
| 8 | `fase-8-webform-roles` | Formulario Participa, roles y permisos | Cada rol ve solo lo suyo | Sí |
| 9 | `fase-9-revision` | Revisión final | Lista de la sección 12 completa | No |

---

## 4. Fase 0 · Preparar tu computador (una sola vez)

Instala, en Ubuntu (WSL2) y con el repositorio dentro de `~/proyectos/` (no en `C:\`):

1. **Git** y tu identidad:
   ```bash
   git config --global user.name "Tu Nombre"
   git config --global user.email "tu-correo@..."
   git config --global --list | grep user      # revisa que el correo esté bien escrito
   ```
2. **Docker Desktop** abierto en Windows, con la integración de WSL activada.
3. **DDEV**: https://ddev.readthedocs.io/en/stable/users/install/
4. **Python 3 y openpyxl** (solo para el conversor del Excel):
   ```bash
   sudo apt update && sudo apt install -y python3 python3-pip python3-venv python3-openpyxl
   ```
5. **Claude Code** en Ubuntu:
   ```bash
   curl -fsSL https://claude.ai/install.sh | bash
   ```
   Cierra y abre la terminal; si `claude` no se encuentra: `echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.bashrc && source ~/.bashrc`.
6. **Acceso a GitHub** para `git push`: `gh auth login` o un token personal. Tu usuario debe tener permiso de escritura en el repositorio.

Comprueba:

```bash
git --version
docker --version
ddev version
python3 -c "import openpyxl; print('openpyxl ok')"
claude --version
```

Clona el repositorio:

```bash
mkdir -p ~/proyectos && cd ~/proyectos
git clone https://github.com/Bengi-fish/AUTOEVALUACION_Unillanos.git
cd AUTOEVALUACION_Unillanos
```

---

## 5. Fase 1 · Crear el proyecto Drupal con DDEV

**Rama:** `fase-1-instalacion`. Esta fase se hace con comandos directos porque Claude Code todavía no está dentro del proyecto. Cuando se repite en otro computador, `ddev composer install` y `ddev drush si --existing-config` reemplazan casi todo (sección 15).

El entorno DDEV se configura en la **raíz del repositorio** y Drupal vive en `drupal/`. Así el contenedor también ve `datos/` y `docs/`.

**[TÚ]**

```bash
git checkout -b fase-1-instalacion
ddev config --project-type=drupal11 --project-name=autoeval-unillanos \
  --docroot=drupal/web --composer-root=drupal
ddev start
ddev composer create-project "drupal/recommended-project:^11"
ddev composer require drush/drush
mkdir -p drupal/config/sync
```

Instala Drupal en español:

```bash
ddev drush site:install standard --locale=es \
  --site-name="Autoevaluación Unillanos" --account-name=admin --account-pass=admin -y
```

Indica a Drupal dónde guardar la configuración (al final de `settings.php`; si el archivo está en solo lectura, primero `chmod u+w`):

```bash
chmod u+w drupal/web/sites/default/settings.php
echo "\$settings['config_sync_directory'] = '../config/sync';" >> drupal/web/sites/default/settings.php
ddev drush config:export -y
ddev launch                  # abre el sitio
```

**Complemento de Claude Code para DDEV (opcional).** Instala Serena y memorias para Drupal. Crea su propio `CLAUDE.md` y puede pisar el nuestro, así que haz copia antes y concilia después:

```bash
cp CLAUDE.md CLAUDE.proyecto.md
ddev add-on get lexsoft00/ddev-drupal-claude-code
ddev restart
```

Después, pide a Claude que deje **un solo** `CLAUDE.md` coherente: todo con `ddev ...` desde la raíz, rutas `/var/www/html/drupal/web` y `/var/www/html/drupal/config/sync`, versiones reales de PHP y MariaDB (`ddev describe`), y sin perder las secciones de reglas del dominio y convenciones. Borra `CLAUDE.proyecto.md` cuando termine.

**Hecho cuando:** el sitio abre en `https://autoeval-unillanos.ddev.site`, la interfaz está en español, `drupal/config/sync` tiene archivos `.yml` y `ddev drush config:status` no muestra diferencias.

**Commit** (con rutas explícitas):

```bash
git status --short           # revisa: nada de vendor/, web/core/ ni settings.ddev.php
git add .ddev .claudeignore drupal CLAUDE.md .gitignore
git commit -m "Fase 1: proyecto Drupal 11 con DDEV"
```

**Push y merge:** bloque de la sección 2.6 con `fase-1-instalacion`.

> Si `ddev composer create-project` dice que la carpeta no está vacía, revisa que `drupal/` no exista antes de correrlo.

---

## 6. Fase 2 · Módulos y configuración base

**Rama:** `fase-2-modulos` · **Respaldo:** no.

**Claude ejecuta.** Prompt:

```
Lee CLAUDE.md y docs/IMPLEMENTACION_DRUPAL.md (sección 6). Ejecuta la Fase 2 en la rama fase-2-modulos.

1. Antes de instalar, comprueba que webform y migrate_source_csv tengan versión compatible con la
   versión de Drupal instalada. Si alguno no la tiene, avísame y espera; no lo fuerces.
2. Instala con `ddev composer require`: drupal/pathauto drupal/paragraphs
   drupal/field_group drupal/auto_entitylabel drupal/webform drupal/migrate_plus drupal/migrate_tools
   drupal/migrate_source_csv drupal/better_exposed_filters.
3. Habilita con `ddev drush en -y`: pathauto paragraphs field_group
   auto_entitylabel webform webform_ui migrate_plus migrate_tools migrate_source_csv
   better_exposed_filters media media_library datetime link options.
4. Traducciones: `ddev drush locale:check && ddev drush locale:update`.
5. Configuración regional por drush (no por la interfaz):
   ddev drush config:set system.date country.default CO -y
   ddev drush config:set system.date first_day 1 -y
   ddev drush config:set core.date_format.short pattern 'd/m/Y' -y
   Verifica que la zona horaria sea America/Bogota y que el registro de usuarios sea solo por
   administradores (user.settings register: admin_only).
6. Crea `.claude/settings.json` (se versiona, a diferencia de settings.local.json) con
   {"attribution": {"commit": "", "pr": ""}, "includeCoAuthoredBy": false}
   para que Claude no firme commits ni pull requests.
7. Nota: el menú de administración es el módulo navigation del núcleo. No se usa admin_toolbar.
Mensaje de commit: "Fase 2: módulos contrib, configuración regional y menú Navigation del núcleo".
Aplica las REGLAS DEL CICLO de la sección 2.2 de docs/IMPLEMENTACION_DRUPAL.md.
```

**Tú verificas:** entras con `ddev drush uli` y compruebas que aparece la barra lateral de Navigation y que Estructura → Webform existe.

**Hecho cuando:** los módulos están habilitados, `config:status` sin diferencias y el sitio responde.

**Push y merge:** sección 2.6 con `fase-2-modulos`.

---

## 7. Fase 3 · Modelo de contenido (MER → Drupal)

**Rama:** `fase-3-modelo` · **Respaldo:** sí (sección 2.7, `antes-fase-3`).

Aquí se crean los vocabularios, tipos de contenido, paragraphs, el media `documento` y los campos. Todo está especificado en `docs/drupal/modelo-de-contenido.md`. Claude escribe un script PHP **idempotente** (se puede correr varias veces sin duplicar nada) que crea la estructura con la Entity API. Es más rápido y menos propenso a errores que crear 150 campos a mano, y queda documentado.

**Claude ejecuta.** Prompt:

```
Lee CLAUDE.md, docs/mer/MER.md y docs/drupal/modelo-de-contenido.md. Ejecuta la Fase 3 en la rama
fase-3-modelo. Crea drupal/scripts/crear_modelo.php, un script idempotente para
`ddev drush php:script` que cree, en este orden: 1) los vocabularios, 2) el nodo `lineamiento` y luego
los demás tipos de contenido, 3) los tipos de paragraph, 4) el tipo de media `documento`, 5) todos los
campos con su almacenamiento, instancia, etiqueta en español, obligatoriedad, cardinalidad y valores
permitidos, 6) la visualización de formulario y la visualización por defecto de cada campo.
No crees vistas ni el webform todavía. Los nombres de máquina deben ser exactamente los del documento.
Córrelo dos veces para comprobar que no duplica nada. Al final muestra un resumen de lo creado.
Aplica las REGLAS DEL CICLO de la sección 2.2 de docs/IMPLEMENTACION_DRUPAL.md.
```

**Claude verifica** (además de la verificación estándar):

```bash
ddev drush php:script drupal/scripts/crear_modelo.php
ddev drush php:eval 'print_r(array_keys(\Drupal\taxonomy\Entity\Vocabulary::loadMultiple()));'
ddev drush php:eval 'print_r(array_keys(\Drupal\node\Entity\NodeType::loadMultiple()));'
ddev drush php:eval 'print_r(array_keys(\Drupal\paragraphs\Entity\ParagraphsType::loadMultiple()));'
ddev drush php:eval 'print_r(array_keys(\Drupal\media\Entity\MediaType::loadMultiple()));'
```

**Tú verificas:** en la interfaz (Estructura → Tipos de contenido / Taxonomía / Tipos de párrafo) los nombres coinciden con el documento. Crea a mano un programa y un proceso de prueba y bórralos.

**Hecho cuando:** existen los 7 vocabularios, 8 tipos de contenido, 3 paragraphs y el media `documento`; el script corrido por segunda vez no cambia nada y `config:status` queda sin diferencias. Esas cifras cuentan solo lo propio del modelo: el vocabulario `tags` y los media por defecto de Drupal (`audio`, `document`, `image`, `remote_video`, `video`) se dejan como están.

**Push y merge:** sección 2.6 con `fase-3-modelo`.

---

## 8. Fase 4 · Módulo propio `unillanos_autoeval` (reglas de negocio)

**Rama:** `fase-4-reglas` · **Respaldo:** no.

**Pendientes heredados de la Fase 3** (resolverlos en esta fase):

- Instalar `drupal/core-dev` (PHPCS, PHPStan, PHPUnit) con `ddev composer require --dev drupal/core-dev` y correr PHPCS y PHPStan sobre `drupal/scripts/crear_modelo.php`.
- `field_estado` está compartido entre `proceso` y `seguimiento` con la unión de sus valores (`planeado`, `en_curso`, `finalizado`, `borrador`, `reportado`, `verificado`). Restringirlo por tipo de contenido (validación o `allowed_values_function`).
- Unicidad de `field_clave` en `programa` y `elemento_modelo`: la restricción `UniqueField` no se exporta en la configuración, así que se implementa como restricción de validación en el módulo.
- Obligatoriedad de campos: revisarla en la Fase 5 con los CSV reales antes de importar (se marcaron como obligatorios los campos de relación "exactamente uno" del MER y los enumerados que definen el tipo).

**Claude ejecuta.** Prompt:

```
Lee CLAUDE.md y la sección "Lógica del módulo" de docs/drupal/modelo-de-contenido.md. Ejecuta la
Fase 4 en la rama fase-4-reglas. Crea el módulo drupal/web/modules/custom/unillanos_autoeval:
cálculo de `field_grado` en el presave de `valoracion`, restricciones de validación (proceso +
elemento únicos; proceso con sede o programa, no ambos), servicio `AvanceMeta` y sugerencia de
`field_destino` en hallazgos. Sigue los estándares de código de Drupal, con comentarios en español.
Agrega pruebas Kernel para el cálculo del grado con los valores de datos/semillas/grados_cumplimiento.csv
(5.0 → Pleno, 4.27 → Alto, 3.5 → Aceptable, 2.8 → Insatisfactorio, 2.0 → No se cumple).
Habilita el módulo, corre las pruebas y exporta la configuración.
Aplica las REGLAS DEL CICLO de la sección 2.2 de docs/IMPLEMENTACION_DRUPAL.md.
```

**Claude verifica:**

```bash
ddev drush en -y unillanos_autoeval
ddev exec -d /var/www/html/drupal vendor/bin/phpunit -c web/core web/modules/custom/unillanos_autoeval
```

**Tú verificas:** creas una valoración de prueba con nota 4,27 y compruebas que el grado sale "Alto" sin digitarlo. Bórrala después.

**Hecho cuando:** las pruebas pasan, el módulo está habilitado y `config:status` queda sin diferencias.

**Push y merge:** sección 2.6 con `fase-4-reglas`.

---

## 9. Fase 5 · Importar los datos (CSV → Drupal con Migrate)

**Rama:** `fase-5-importacion` · **Respaldo:** sí (`antes-fase-5`).

Los datos entran **siempre** por importación, nunca a mano. Así cualquier persona reconstruye el mismo sitio. Esta fase tiene tres pasos, y entre ellos hay revisión tuya.

### 9.1 Preparar los CSV de un programa [TÚ]

Los de Ingeniería Electrónica ya están en `datos/importacion/ingenieria-electronica/`. Para otro programa que llegue con su Excel FO-GCL-20:

```bash
python3 datos/herramientas/plan_excel_a_csv.py "docs/fuentes/<plan-del-programa>.xlsx" datos/importacion/<slug-del-programa>/
```

Revisa a mano:

- `responsables.csv`: catálogo canónico (`id`, `nombre`, `tipo`, `sigla`), sin duplicados.
- `equivalencias_responsables.csv` (`variante`, `id`): mapea cada texto de `metas.csv` al responsable canónico, sin distinguir mayúsculas ni espacios. Aquí se unifican "Profsores" → "Profesores" o "Dirección General de Investigación" → "Dirección General de Investigaciones".
- `equivalencias_factores_plan.csv`: asigna a cada texto de factor del Excel su elemento del modelo. Ojo: algunos planes usan la numeración de factores de 2013.
- `valoraciones_factores.csv` y `proceso.csv`: se llenan a partir del informe del programa.

### 9.2 Extraer fortalezas y aspectos por mejorar del informe

**Claude ejecuta.** Prompt (rama `fase-5-importacion`, sin commit todavía):

```
Lee docs/fuentes/informe-autoevaluacion-2022-ingenieria-electronica.pdf. Para cada factor (1 a 12)
extrae la tabla "Fortalezas / Aspectos por mejorar" y genera
datos/importacion/ingenieria-electronica/hallazgos_informe.csv con las columnas
`id,proceso_id,elemento_id,tipo,descripcion,origen,destino,destacado`. Usa `proceso_id=PR-IE-2022`,
`elemento_id` = `CNA20-Fxx`, `tipo` = `fortaleza` o `aspecto_por_mejorar` y `origen=autoevaluacion`.
Copia el texto tal cual, sin resumir. Al final dime cuántas filas salieron por factor para que yo las
compare con el PDF.
Aplica las REGLAS DEL CICLO de la sección 2.2 de docs/IMPLEMENTACION_DRUPAL.md.
```

**[TÚ]** Coteja las cifras por factor con el PDF antes de seguir. Si esta revisión se salta, un error del informe termina publicado en el sitio.

### 9.3 Migraciones

**Claude ejecuta.** Prompt:

```
Crea el módulo drupal/web/modules/custom/unillanos_migrate con migraciones YAML (grupo `unillanos`,
fuente `csv` de migrate_source_csv) que lean /var/www/html/datos/... Orden: `sedes`, `facultades`,
`estamentos`, `grados`, `responsables`, `proyectos`, `lineamientos`, `elementos_modelo` (dos pasadas:
términos y luego padre/equivalencia), `programas`, `procesos`, `valoraciones`, `hallazgos_informe`,
`hallazgos_plan`, `planes`, `metas` (con indicadores y programación anual como paragraphs),
`seguimientos`. Usa `migration_lookup` para las referencias y las claves `id` de los CSV como
identificadores de origen. Hazlo por programa: la carpeta de importación es un parámetro, para poder
agregar programas sin copiar YAML. Habilita el módulo, importa el grupo e informa `migrate:status`.
Las migraciones NO validan las entidades al guardarlas (no actives `validate` en ellas): las
restricciones de `unillanos_autoeval` se comprueban después, con el script de validación posterior.
Escribe drupal/scripts/validar_importacion.php: carga las entidades importadas (valoracion, proceso,
programa, elemento_modelo, seguimiento), llama a validate() en cada una y lista las violaciones
(tipo, id, título, campo, mensaje) sin modificar nada ni guardar.
Si algo falla, muéstrame `migrate:messages <id>` y espera antes de hacer rollback.
Aplica las REGLAS DEL CICLO de la sección 2.2 de docs/IMPLEMENTACION_DRUPAL.md.
```

**Claude verifica:**

```bash
ddev drush en -y unillanos_migrate
ddev drush migrate:status --group=unillanos
ddev drush migrate:import --group=unillanos
ddev drush php:script drupal/scripts/validar_importacion.php   # lista violaciones; debe salir vacío
# si algo sale mal (previa confirmación):
ddev drush migrate:rollback --group=unillanos
ddev drush migrate:reset-status <id_migracion>
```

**Tú verificas:** en la interfaz, el programa Ingeniería Electrónica tiene su proceso, 12 valoraciones de factor, el plan con 17 metas y sus seguimientos. Compara 3 o 4 cifras con el informe (valoración global 4,508 · 90 %).

**Hecho cuando:** `migrate:status` muestra todo importado sin errores, `validar_importacion.php` no lista violaciones (o las que lista están explicadas) y lo anterior se cumple.

**Commit:** puede ir en dos commits (CSV de hallazgos; módulo y migraciones) o en uno, pero con rutas explícitas (`git add datos drupal`).

**Push y merge:** sección 2.6 con `fase-5-importacion`.

### 9.4 Importar en otro equipo (paso reproducible)

Con el repositorio actualizado (`git pull`) y el sitio levantado (`ddev start`):

```bash
ddev composer install
ddev drush cim -y          # activa unillanos_migrate y crea el grupo `unillanos`
ddev drush cr
ddev drush migrate:import --group=unillanos
ddev drush php:script drupal/scripts/validar_importacion.php
```

- La importación resuelve el orden por dependencias. Una segunda ejecución no cambia nada (`0 created, 0 updated`).
- Para volver a leer los CSV después de corregirlos: `ddev drush migrate:import --group=unillanos --update`. En `hallazgos_informe` no se toca `field_destino` (sugerido por `unillanos_autoeval` o editado a mano).
- Un programa nuevo se agrega creando su carpeta en `datos/importacion/<programa>/` con los CSV de 9.1 y corriendo `ddev drush cr`; las migraciones por programa (`procesos`, `valoraciones`, `hallazgos_informe`, `hallazgos_plan`, `planes`, `responsables`, `programacion_anual`, `indicadores`, `metas`, `seguimientos`) se replican solas, con id `<migración>:<carpeta con guiones bajos>`.
- Si algo falla: `ddev drush migrate:messages <id>`. Las filas fallidas se reintentan con `--update`. No hagas `migrate:rollback` del grupo sin confirmar.
- `validar_importacion.php` no modifica nada. Separa ERRORES (violaciones de validación y datos faltantes de programas con datos importados) de AVISOS (datos faltantes de los demás programas).

### 9.5 Pendientes al cierre de la Fase 5

Datos y decisiones que quedan abiertos. Ninguno bloquea el avance a la Fase 6.

**Por confirmar con Acreditación o con el Excel**

- [ ] `proceso_id = PR-IE-2022` en `plan.csv` y `hallazgos_plan.csv` es provisional. El plan habla del periodo "Autoevaluación con fines de calidad 2020-2023-1" (columna `proceso`); confirmar a qué proceso corresponde.
- [ ] Proyecto institucional de las metas M15 y M16: pendiente de verificación en el Excel. Sus nombres no coinciden con el catálogo; los candidatos provisionales son PI10 (M15, laboratorios) y PI12 (M16, equipos). Por ahora quedan **sin proyecto** en Drupal; si se confirman, agregar la equivalencia y reimportar.
- [ ] Seguimientos con estado `reportado`: provisional. El CSV no trae verificación (`verificacion` vacío) y solo 25 de los 51 tienen avance. Revisar el estado cuando haya verificación.
- [ ] Regla de destino del hallazgo (< 4 plan de mejoramiento, = 4 plan de acción, > 4 ninguno), ya pendiente de confirmar. Con ella los 85 hallazgos del informe de Ingeniería Electrónica quedaron en `ninguno`, porque los 12 factores están por encima de 4.

**Datos de los 40 programas por completar** (`datos/semillas/programas.csv` y `sedes.csv`)

- [ ] Modalidad y sede de los 39 programas restantes (hoy son AVISOS en `validar_importacion.php`; en Ingeniería Electrónica son obligatorios).
- [ ] Código SNIES, director, resoluciones de registro calificado y de acreditación (con sus fechas "vigente hasta") y municipio de cada sede.
- [ ] Ingeniería Electrónica: modalidad `presencial` y sede `barcelona` se completaron con la ficha del informe (Tabla 1.1, pág. 17; la sede se dedujo de la dirección "Vda. Barcelona", confirmar). La misma tabla trae SNIES 4169, registro calificado (Resolución 25083 del 17 de nov de 2017), acreditación (Resolución 13202 del 17/07/2020) y ubicación en Villavicencio; aún no se cargaron.

**Columnas de los CSV que no se importan** (el modelo no tiene dónde guardarlas)

- [ ] `indicadores.csv`: `valor_base` y `total`.
- [ ] `valoraciones_factores.csv`: `fuente`.
- [ ] `plan.csv`: `director`, `programa`, `facultad`, `nivel` y `proceso` (texto; se usa `proceso_id`).
- [ ] Decidir si alguna merece un campo nuevo (cambio de MER en los tres documentos).

**Otros**

- [ ] Títulos de valoraciones y seguimientos con ids (`PR-IE-2022 · CNA20-F01`, `M01 · 2024-2`) hasta configurar `auto_entitylabel` y Pathauto en la Fase 7.
- [ ] `field_modalidad` y `field_sede` de `programa` ya no son obligatorios (cambio en `crear_modelo.php` y en la configuración). Volver a exigirlos cuando el catálogo de programas esté completo.
- [ ] Se borraron dos términos de prueba (facultad tid 1 y sede tid 2) que duplicaban los del catálogo; no tenían ninguna referencia.

---

## 10. Fase 6 · Tema visual desde el prototipo

**Rama:** `fase-6-tema` · **Respaldo:** no.

**Claude ejecuta.** Prompt:

```
Ejecuta la Fase 6 en la rama fase-6-tema. Genera el tema con
`ddev exec -d /var/www/html/drupal/web php core/scripts/drupal generate-theme unillanos --path themes/custom`,
habilítalo y ponlo por defecto (`ddev drush theme:enable unillanos` y
`ddev drush config:set system.theme default unillanos -y`).
Convierte plantilla/ en el tema drupal/web/themes/custom/unillanos. 1) Copia `style.css` en `css/` y
`app.js` en `js/`, y declara una librería global. 2) Adapta `app.js` a `Drupal.behaviors.unillanos`
usando `once()`, sin cambiar la lógica de las pestañas animadas, el medidor ni el filtro del plan.
3) Crea `page.html.twig` con el encabezado, el menú plano y el pie del prototipo, más las regiones
necesarias. 4) Crea plantillas para `node--programa--full`, `node--proceso--full` y
`views-view--plan` que reproduzcan el HTML del prototipo con los atributos `data-ua-views`,
`data-routes` y `data-view`. 5) Copia los logos de plantilla/assets. No uses datos de
`datos-ejemplo.js`: todo debe salir de los campos de Drupal. Respeta `prefers-reduced-motion`.
Aplica las REGLAS DEL CICLO de la sección 2.2 de docs/IMPLEMENTACION_DRUPAL.md.
```

**[TÚ] verificas:** abres `plantilla/programa-ejemplo.html` y el nodo del programa en Drupal, lado a lado, en escritorio (1440 px) y en móvil (390 px). Pruebas las pestañas animadas con el teclado.

**Hecho cuando:** el sitio se ve y se comporta como el prototipo, con datos reales, y `config:status` queda sin diferencias.

**Push y merge:** sección 2.6 con `fase-6-tema`.

---

## 11. Fase 7 · Vistas y páginas · Fase 8 · Participa, roles y permisos

### Fase 7 · Vistas y páginas

**Rama:** `fase-7-vistas` · **Respaldo:** no.

**Claude ejecuta.** Prompt:

```
Ejecuta la Fase 7 en la rama fase-7-vistas. Con la tabla "Páginas del prototipo → Drupal" de
docs/drupal/modelo-de-contenido.md, crea las vistas `programas` (página con filtros expuestos),
`valoraciones_proceso` (bloque por factor y característica, con argumento proceso),
`hallazgos_proceso`, `plan_mejoramiento` (bloque filtrable por factor a través de los hallazgos),
`documentos` (página con pestañas por categoría) y los bloques de la portada. Configura Pathauto:
`/programas/[node:field_clave]`, `/procesos/[node:nid]`. Exporta la configuración.
Aplica las REGLAS DEL CICLO de la sección 2.2 de docs/IMPLEMENTACION_DRUPAL.md.
```

**[TÚ] verificas:** recorres las 6 páginas del prototipo en Drupal y pruebas el enlace "ver acciones de este factor".

**Hecho cuando:** las 6 páginas existen con datos reales y ese enlace abre el plan ya filtrado.

**Pendiente heredado de la Fase 5:** configurar `auto_entitylabel` (títulos de `valoracion` y `seguimiento`, hoy con ids) junto con Pathauto.

**Push y merge:** sección 2.6 con `fase-7-vistas`.

### Fase 8 · Participa, roles y permisos

**Rama:** `fase-8-webform-roles` · **Respaldo:** sí (`antes-fase-8`).

**Claude ejecuta.** Prompt:

```
Ejecuta la Fase 8 en la rama fase-8-webform-roles. Crea el webform `participa` según
docs/drupal/modelo-de-contenido.md (incluye consentimiento obligatorio, `estado_gestion` y
`respuesta` solo para administradores) y colócalo en `/participa`. Crea los roles
`editor_autoevaluacion` (gestiona procesos, valoraciones, hallazgos, planes, metas y seguimientos),
`editor_documentos` (media) y `gestor_participacion` (envíos del webform). Anónimos: solo ver
contenido publicado y enviar el formulario. Exporta la configuración y lista los permisos de cada rol.
Aplica las REGLAS DEL CICLO de la sección 2.2 de docs/IMPLEMENTACION_DRUPAL.md.
```

**[TÚ] verificas:** envías una recomendación como usuario anónimo (ventana privada) y luego la gestionas como `gestor_participacion`. Compruebas que un anónimo no ve `/admin`.

**Hecho cuando:** el formulario funciona, cada rol ve solo lo suyo y `config:status` queda sin diferencias.

**Push y merge:** sección 2.6 con `fase-8-webform-roles`.

---

## 12. Fase 9 · Revisión antes de mostrar

**Rama:** `fase-9-revision` (solo si hay correcciones).

Marca cada punto. Los comandos los puede correr Claude; la revisión visual es tuya.

- [ ] `ddev drush cex` sin cambios pendientes y `git status` limpio.
- [ ] Reinstalación en limpio: `ddev drush si --existing-config -y && ddev drush migrate:import --group=unillanos` deja el sitio igual (¡previa copia de seguridad y confirmación: es destructivo!).
- [ ] Las cifras del sitio coinciden con el informe (valoración global 4,508 · 90 %, tabla 5.1) y con el Excel (17 metas, pesos de 1/17).
- [ ] Accesibilidad: contraste, navegación con teclado por las pestañas, textos alternativos en imágenes.
- [ ] Móvil (390 px) y escritorio (1440 px) comparados con `plantilla/`.
- [ ] Copia de seguridad: `ddev export-db --file=$HOME/respaldos/final.sql.gz` guardada **fuera** del repositorio.
- [ ] Documentación al día: `README.md`, `CLAUDE.md` y los tres documentos del MER reflejan lo implementado.

### Registro de avance

Marca cada fase cuando ya esté fusionada en `main`:

- [ ] Fase 1 · [ ] Fase 2 · [ ] Fase 3 · [ ] Fase 4 · [ ] Fase 5 · [ ] Fase 6 · [ ] Fase 7 · [ ] Fase 8 · [ ] Fase 9

---

## 13. Comandos de todos los días

```bash
ddev start                    # encender el entorno
ddev drush uli                # entrar como admin
ddev drush cr                 # limpiar caché
ddev drush cex -y             # exportar configuración (después de cambiar algo en la interfaz)
ddev drush cim -y             # importar configuración (después de un git pull)
ddev composer install         # después de un git pull que cambió composer.lock
ddev drush updb -y            # actualizar la base de datos tras actualizar módulos
ddev drush watchdog:show      # ver errores recientes
ddev stop                     # apagar (los contenedores del ruteador pueden seguir; es normal)
```

Después de cada `git pull`, en este orden: `ddev composer install` → `ddev drush updb -y` → `ddev drush cim -y` → `ddev drush cr`.

## 14. Problemas frecuentes

| Síntoma | Solución |
|---|---|
| `git push`: "Password authentication is not supported" | Usa `gh auth login` o un token personal de GitHub. Si da 403, falta permiso de escritura en el repositorio. |
| `git pull` dice "Already up to date" pero falta la fase anterior | La rama no se fusionó. Haz el merge (sección 2.6) o el Pull Request. |
| `git status` muestra `M CLAUDE.md` tras instalar el complemento de DDEV | El complemento antepone su texto. Pide a Claude que concilie un solo `CLAUDE.md` (fase 1). |
| Claude Code firma los commits | Verifica que exista `.claude/settings.json` (fase 2) y que el prompt incluya las REGLAS DEL CICLO. |
| `drush cim` quiere borrar cosas que creaste | Creaste algo en la interfaz y no lo exportaste. Corre `drush cex` primero, haz commit y vuelve a importar. |
| "Configuration … depends on … that will not exist" | Falta habilitar un módulo: `drush en <módulo>` y repite. |
| La importación deja referencias vacías | El orden de las migraciones está mal o el `id` del CSV no coincide. Revisa `migrate:messages <id>`. |
| El sitio se ve sin estilos | `drush cr` y revisa que la librería del tema esté declarada en `unillanos.libraries.yml`. |
| Docker lento en Windows | Asegúrate de que el repositorio esté dentro de WSL2 (`/home/...`), no en `/mnt/c/...`. |
| `.git/index.lock` impide commits | Si no hay otro git corriendo: `rm .git/index.lock`. |

## 15. Cuando entre tu compañero (adelanto)

1. **Su instalación completa:** clona el repositorio y corre `ddev start`, `ddev composer install`, `ddev drush si --existing-config -y` y `ddev drush migrate:import --group=unillanos`. Tiene el mismo sitio.
2. **Pull Requests obligatorios:** protejan `main` en GitHub (Settings → Branches) para que nada entre sin revisión. El merge de la sección 2.6 pasa a ser el botón "Merge pull request".
3. **Reparto por carpetas para no pisarse:** uno toma el tema (`themes/custom`) y el otro el modelo, las migraciones y las vistas (`modules/custom`, `config/sync`). La configuración es el punto de choque: avisen antes de exportarla y hagan `git pull` + `drush cim` antes de empezar cada día.
4. **Mismo contexto para los dos:** `CLAUDE.md`, `.claude/settings.json` y estos documentos hacen que Claude Code trabaje igual en los dos computadores.
