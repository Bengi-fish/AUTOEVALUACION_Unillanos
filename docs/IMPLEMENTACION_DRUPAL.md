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
| Modelo por proceso: 2020 para Ingeniería Electrónica, 2025 para los procesos nuevos, sin equivalencias entre modelos | Listo (validación, selector y pruebas) | `unillanos_autoeval`, `docs/drupal/modelo-de-contenido.md` |
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
| 7 | `fase-7-vistas` | Listado y buscador de programas (`/programas`, por servicio, sin Views), página de programa sin autoevaluación, menús y división del presentador | `/programas` y las páginas de la Fase 6 con datos reales; `/institucional` queda fuera | No |
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
- `equivalencias_factores_plan.csv`: asigna a cada texto de factor del Excel su elemento del modelo. Ojo: algunos planes usan la numeración de factores de 2013; el informe IE 2018-2022 respalda cada fila (ver `docs/pendientes-acreditacion.md`). Esto no es una equivalencia entre modelos del sistema.
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
términos y luego el padre), `programas`, `procesos`, `valoraciones`, `hallazgos_informe`,
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
`ddev exec -d /var/www/html/drupal php vendor/bin/dr generate-theme unillanos --path themes/custom`,
habilítalo y ponlo por defecto (`ddev drush theme:enable unillanos` y
`ddev drush config:set system.theme default unillanos -y`).
Convierte plantilla/ en el tema drupal/web/themes/custom/unillanos. 1) Copia `style.css` en `css/` y
`app.js` en `js/`, y declara una librería global. 2) Adapta `app.js` a `Drupal.behaviors.unillanos`
usando `once()`, sin cambiar la lógica de las pestañas animadas, el medidor ni el filtro del plan.
3) Crea `page.html.twig` con el encabezado, el menú plano y el pie del prototipo, más las regiones
necesarias. 4) Crea plantillas de nodo (`node--programa--full`) y de página propia (portada y
`/documentos`) que reproduzcan el HTML del prototipo con los atributos `data-ua-views`,
`data-routes` y `data-view`; los datos los prepara un servicio por página (en la Fase 6 era un único
presentador, dividido y retirado en la Fase 7; ver sección 11) (sin Views). 5) Copia los logos de plantilla/assets. No uses datos de
`datos-ejemplo.js`: todo debe salir de los campos de Drupal. Respeta `prefers-reduced-motion`.
Aplica las REGLAS DEL CICLO de la sección 2.2 de docs/IMPLEMENTACION_DRUPAL.md.
```

**[TÚ] verificas:** abres `plantilla/programa-ejemplo.html` y el nodo del programa en Drupal, lado a lado, en escritorio (1440 px) y en móvil (390 px). Pruebas las pestañas animadas con el teclado.

**Hecho cuando:** el sitio se ve y se comporta como el prototipo, con datos reales, y `config:status` queda sin diferencias.

**Cómo se resolvió (demo con datos reales de Ingeniería Electrónica, solo local):**

- **Sin Views.** Las páginas públicas (portada `/inicio`, programa, plan de mejoramiento, `/documentos`) son plantillas de nodo o de página propia más servicios de `unillanos_autoeval` que preparan los datos (ver la sección 11). Los componentes SDC solo muestran valores ya preparados; no calculan nada. Views queda para la Fase 7 (listado y buscador de programas).
- **El tema se genera con** `ddev exec -d /var/www/html/drupal php vendor/bin/dr generate-theme unillanos --path themes/custom` (el comando `core/scripts/drupal generate-theme` está obsoleto y falla con la estructura de este repositorio).
- **Alias de URL:** los alias viven en la base de datos, no en la configuración. En una base nueva, o tras importar un respaldo o migrar antes de importar la configuración, ejecuta `ddev drush pathauto:aliases-generate create canonical_entities:node` (solo crea los que faltan). Sin él, el enlace «Programa de Electrónica» del menú no encuentra su alias.
- **Documentos:** las migraciones de documentos (marcas `%raiz%` y `unillanos_csv_opcional`) copian los archivos a `public://documentos/`; `/documentos` y la sección «Documentos del programa» salen del servicio `DocumentosPublicados`.
- **Textos editoriales de la portada** («¿Qué es la autoevaluación?», fases del proceso) y de `/documentos`: se conservan los del prototipo y están **pendientes de confirmar con Acreditación**.
- **«Última actualización» en la portada:** se omite a propósito. Sería la fecha de carga de los datos, no la del informe; se añadirá cuando haya fechas de publicación propias.
- **Componentes sin usar y sin commitear:** los de los grupos 5 y 6 (`pane`, `program-card`, `program-search`, `progsearch`, `reco-form`, `scope`, `tool`, `view-tabs`) siguen sin usarse; su destino se decide en la Fase 7.
- Estado y pendientes de cada paso: `docs/pendientes-acreditacion.md`, secciones 16, 17 y 19 a 21.

**Push y merge:** sección 2.6 con `fase-6-tema`.

---

## 11. Fase 7 · Vistas y páginas · Fase 8 · Participa, roles y permisos

### Fase 7 · Vistas y páginas

**Rama:** `fase-7-vistas` · **Respaldo:** no.

**Claude ejecuta.** Prompt:

```
Ejecuta la Fase 7 en la rama fase-7-vistas. Parte de lo que ya existe tras la Fase 6: el programa,
el plan, la portada y `/documentos` son plantillas más servicios de `unillanos_autoeval`
(sin Views) y no se rehacen. Con la tabla "Páginas del prototipo → Drupal" de
docs/drupal/modelo-de-contenido.md, crea con Views lo que falta: el listado y buscador de programas
`/programas` (página con filtros expuestos) y las páginas que aún no existen (`/institucional`).
Decide el destino de los componentes de los grupos 5 y 6 (pane, program-card, program-search,
progsearch, reco-form, scope, tool, view-tabs): usarlos o descartarlos. Completa Pathauto
(`/procesos/[node:nid]`; el patrón `/programas/[node:field_clave]` ya existe) y `auto_entitylabel`.
Exporta la configuración.
Aplica las REGLAS DEL CICLO de la sección 2.2 de docs/IMPLEMENTACION_DRUPAL.md.
```

**[TÚ] verificas:** recorres las 6 páginas del prototipo en Drupal y pruebas el enlace "ver acciones de este factor". Si un servicio pasa de 1500 líneas, se divide (regla de `CLAUDE.md`).

**Hecho cuando:** las 6 páginas existen con datos reales (las de la demo siguen funcionando) y ese enlace abre el plan ya filtrado.

**Pendiente heredado de la Fase 5:** configurar `auto_entitylabel` (títulos de `valoracion` y `seguimiento`, hoy con ids) junto con Pathauto. No se hizo en la Fase 7.

**Cómo se resolvió (rama `fase-7-vistas`, pasos 1 a 7d):**

- **Rutas nuevas:** `/programas` (`ProgramasController`, ruta `unillanos_autoeval.programas`): listado y buscador con un formulario GET (`q`, facultad, nivel, sede; el filtro de sede solo si hay dos o más), resuelto en el servidor y sin Views. Los componentes `program-card` y `program-search` se usan; `pane`, `progsearch`, `reco-form`, `scope`, `tool` y `view-tabs` siguen sin usarse ni commitearse. `/institucional` no se creó.
- **Concordancia:** los rótulos de cifras, plan y filtro concuerdan en singular y plural (`formatPlural()` en el servicio; el componente solo imprime lo recibido).
- **Servicios** (`drupal/web/modules/custom/unillanos_autoeval/src/Service/`; ninguno calcula grados, destinos ni avances):
  - `ListadoProgramas`: tarjetas del listado, filtros y «otros programas de la facultad»; es la única fuente de la regla «con autoevaluación», compartida con la portada.
  - `ProgramasYProcesos`: consultas de programas y procesos publicados, base de los demás servicios.
  - `FactoresPrograma`: tarjetas y pestañas de factores de un programa, para cualquier modelo.
  - `PlanMejoramientoPrograma`: el plan FO-GCL-20 de un programa y su seguimiento.
  - `DocumentosPublicados`: `/documentos` y los documentos de cada programa.
  - `DatosPortada`: la portada (cifras, programas, procesos).
  - `PaginaPrograma`: cabecera, ficha (`datosPrograma()`), bajada y «otros programas» de la página de un programa; si el programa no tiene nada de autoevaluación, cambia la bajada y ofrece los demás programas de su facultad.
  - `EnlacesDelPie`: lee el menú `footer` que el visitante puede ver.
  - Traits `EtiquetasYOrdenTrait` y `FormatoTextoTrait` (etiquetas, orden, números y fechas).
- **División del presentador:** `PresentadorAutoevaluacion` (1508 líneas) se repartió en esos servicios en los pasos 7a a 7d-1, con un presentador fachada de transición; en 7d-2 (`0e1cfcf`) los llamadores (tema, controladores, hooks y pruebas) pasaron a usar los servicios y **la clase y el servicio `unillanos_autoeval.presentador` ya no existen**. Las páginas se comprobaron idénticas byte a byte (SHA-256) antes y después de cada paso.
- **Menús:** «Programas» y «Documentos» (menú principal) y la columna «Explora» del pie (menú `footer`) son definiciones de `unillanos_autoeval.links.menu.yml` por nombre de ruta, así que solo existen si la ruta existe. «Institucional» y «Participa» se añadirán cuando existan sus rutas.
- **Elemento activo en el programa:** `UnillanosAutoevalServiceProvider` sustituye la clase del servicio `menu.active_trail` por `RastroActivoProgramas` (`src/Menu/RastroActivoProgramas.php`), subclase de `Drupal\Core\Menu\MenuActiveTrail` que añade «Programas» al rastro del menú principal en la página de un programa, y lo deja no perezoso (`setLazy(FALSE)`). **Mantenimiento:** al actualizar Drupal hay que revisar que siga siendo compatible con la definición de `menu.active_trail` en `core.services.yml` (constructor y argumentos), con `getActiveTrailIds()` / `doGetActiveTrailIds()` y con las propiedades `routeMatch` y `menuLinkManager`; `MenusTest` cubre el comportamiento.
- **Etiquetas de caché:** el `hook_node_view_alter` que declara las etiquetas de la página del programa vive en el módulo (`UnillanosAutoevalHooks`), porque el núcleo no lo invoca para los hooks de un tema.
- **Al desplegar o actualizar: `ddev drush cr`.** Hace falta reconstruir el contenedor (la sustitución de `menu.active_trail` y los servicios nuevos) y la tabla de enlaces de menú (los `links.menu.yml`). Sin ello, el menú conserva los enlaces anteriores.
- **Textos editoriales y ajustes pendientes:** `docs/pendientes-acreditacion.md`, sección 22.

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

#### Fase 8.1 (rama `fase-8-acreditacion`): rol «Acreditación»

Primera parte de la Fase 8, hecha antes del formulario Participa. Un solo rol, `acreditacion` («Acreditación», `user.role.acreditacion`, en `config/sync` y en `config/optional` del módulo `unillanos_autoeval`), que **edita todos los programas**.

- **Qué puede:** crear y editar (cualquier contenido) `programa`, `proceso`, `valoracion`, `hallazgo`, `plan_mejoramiento`, `meta` y `seguimiento`, y crear y editar el medio `documento` (con la subida del archivo); ver revisiones, ver su contenido sin publicar, la lista de contenido y de medios, la navegación y el tema de administración; abrir el panel (`access acreditacion panel`, ver 8.2) y el formulario del micrositio (`edit unillanos micrositio`). Las facultades y las sedes solo se **seleccionan** (no puede editar esos vocabularios).
- **Qué no puede:** borrar nada (para retirar algo se **despublica**), tocar `lineamiento`, ni entrar a configuración, módulos, estructura, personas, informes ni actualizaciones (`/admin/config`, `/admin/modules`, `/admin/structure`, `/admin/people`, `/admin/reports` dan «Acceso denegado»). **Ningún permiso `administer …`** y ningún `bypass`.
- **Revisiones:** los ocho tipos de nodo y el medio `documento` ya crean revisión nueva al guardar; Acreditación publica directamente.
- **Permisos propios** (`unillanos_autoeval.permissions.yml`):
  - `edit unillanos micrositio`: abre y guarda `/admin/config/unillanos/micrositio`. **Diferencia deliberada:** la ruta ya no exige `administer site configuration`; quien solo tenga ese permiso ya no abre el formulario.
  - `access acreditacion panel`: panel `/admin/acreditacion` (Etapa 8.2).
  - `change publication status on acreditacion content`: **decisión deliberada.** El núcleo solo deja marcar o desmarcar «Publicado» a quien tenga `administer nodes` o `administer node published status`. En lugar de dar un `administer …` al rol, el módulo implementa `hook_entity_field_access()` (`UnillanosAutoevalHooks::entityFieldAccess()`) y concede **solo editar el campo `status`** en los tipos `programa`, `proceso`, `valoracion`, `hallazgo`, `plan_mejoramiento`, `meta`, `seguimiento` y `lineamiento` y en el medio `documento`. Solo suma (nunca devuelve «prohibido»), no toca otros campos, tipos de contenido ni tipos de medio, y no concede crear ni editar. El rol Acreditación edita siete de esos nodos (no `lineamiento`) y el medio `documento`; el permiso cubre también `lineamiento` por si otro rol lo recibe. En los medios el núcleo no restringe «Publicado»: quien edita un documento ya puede cambiarlo, así que ahí el permiso es neutro.
- **Registro:** abierto solo al administrador (`register: admin_only`); no se cambió.
- **Crear una cuenta de Acreditación a mano:** como administrador, ir a *Personas → Añadir usuario* (`/admin/people/create`), escribir nombre de usuario, correo y una contraseña segura, marcar el rol **Acreditación** (ningún otro) y guardar. Entregar la contraseña por un canal seguro y pedir que la cambie al entrar. Para retirar el acceso, bloquear la cuenta (no borrar: así se conserva la autoría de los cambios).
- **Pruebas:** `RolAcreditacionTest` (foto exacta de los 27 permisos del rol, crear y editar sin borrar, rutas de administración, formulario del micrositio, permiso de publicación y formulario «Publicado»).

#### Fase 8.2 (rama `fase-8-acreditacion`): panel `/admin/acreditacion`

- **Qué es:** página de administración (tema Claro) con la lista de los programas publicados (nombre, facultad, nivel, sede), un buscador por nombre (`?q=`, GET), los indicadores «Con proceso» y «Con plan» (solo cuando hay dato; nunca «Pendiente») y, por programa, un bloque desplegable «Accesos de edición» con enlaces a pantallas que **ya existen** en Drupal: editar la ficha; editar o crear procesos; valoraciones, hallazgos, plan de mejoramiento, metas, seguimientos y documentos. Arriba: todo el contenido, contenido sin publicar (`/admin/content?status=2`), documentos y el formulario del micrositio. Solo se muestran los enlaces que la persona puede abrir.
- **Permiso y ruta:** `access acreditacion panel` (lo tiene el rol Acreditación). Controlador `AcreditacionController` (usa `ListadoProgramas`, sin SQL a mano), plantilla `templates/unillanos-acreditacion-panel.html.twig` del módulo (se pinta con el tema de administración; no se tocó el tema público ni los servicios), hoja mínima `css/acreditacion.css` (la tabla se desplaza dentro de su caja para no ensanchar la página). Caché por usuario (`user`, `url.query_args:q`) y etiquetas `node_list:*`: nunca pública.
- **Menú:** el enlace «Acreditación» es **hijo directo de «Administración»** (`system.admin`), no de «Contenido» ni de «Configuración». Razones: el rol no accede a `/admin/config`, y el módulo Navigation del núcleo quita `system.admin_content` de su árbol (todo lo que cuelga de ahí quedaría oculto) y pinta la sección «Administración» desde el segundo nivel, así que un hijo de `system.admin` se ve con solo el permiso del panel, sin `access administration pages`.
- **Precarga de relaciones** (`UnillanosAutoevalHooks::entityPrepareForm()`, `hook_entity_prepare_form`): en los formularios de **creación**, si la URL trae `?programa=`, `?proceso=` o `?plan=` con el identificador de un contenido del tipo esperado que la persona pueda ver, se rellena la referencia (`proceso.field_programa`; `field_proceso` de valoración, hallazgo, plan de mejoramiento y medio documento; `meta.field_plan`). Nunca sobrescribe un valor, ignora lo que no sea un entero o apunte a otro tipo, y no cambia el modelo de datos. **No se puede precargar** el seguimiento (pertenece a una meta, no al programa): ahí solo hay «Crear un seguimiento» sin relación y el enlace a la lista. Sin proceso (o sin plan), los grupos que dependen de él muestran solo el enlace a la lista de contenido del tipo.
- **Límite conocido:** el panel y el menú muestran los programas **publicados** (así lo hace `ListadoProgramas`); para encontrar un programa despublicado se usa «Contenido sin publicar».
- **Pruebas:** `PanelAcreditacionTest` (acceso, los 40 programas con sus enlaces, cada enlace resuelve a una ruta, solo se enlaza lo que se puede abrir, indicadores, búsqueda, estado vacío, tabla accesible, caché, enlace del menú, precarga).

#### Fase 8.3 (rama `fase-8-acreditacion`): formularios simples de la ficha y los documentos

- **Qué es:** un **modo de formulario propio, `acreditacion`**, para la ficha del programa (`node.programa.acreditacion`) y para el documento (`media.documento.acreditacion`), con solo los campos que Acreditación necesita, en orden claro y con ayudas en lenguaje sencillo. Los administradores siguen usando el formulario completo de Drupal (`/node/ID/edit`, `/media/ID/edit`), que no se tocó.
- **Ficha** (orden): nombre, facultad, nivel, modalidad, sede, director, código SNIES, resolución y vigencia del registro calificado, resolución y vigencia de la acreditación, imagen de cabecera y «Publicado». **No muestra:** la clave del programa (identificador estable de la URL), autor, fecha de creación, alias de URL, idioma, «promover» ni «fijar». **Documento** (orden): título, archivo, categoría, fecha de publicación, versión, proceso, lineamiento y «Publicado». **No muestra:** autor, fecha de creación, alias de URL, idioma ni el enlace externo. Siguen apareciendo, como en todo contenido, el mensaje del historial de revisiones y «Crear nueva revisión».
- **Rutas** (bajo `/admin/acreditacion`, con el control de acceso de editar o crear, sin permiso nuevo): `/admin/acreditacion/programa/{node}/ficha` (`_entity_access: node.update`), `/admin/acreditacion/documento/{media}/editar` (`media.update`) y `/admin/acreditacion/documento/nuevo` (`_entity_create_access: media:documento`; con `?proceso=ID` el proceso ya viene elegido). Un contenido de otro tipo da 404; sin permiso de editar, 403. Controlador `AcreditacionFormulariosController`.
- **Implementación:** `core.entity_form_mode.{node,media}.acreditacion` y `core.entity_form_display.{node.programa,media.documento}.acreditacion` (en `config/sync` y en `config/optional` del módulo); la clase del formulario de la operación se registra en `UnillanosAutoevalHooks::entityTypeAlter()` (`NodeForm` y `MediaForm`, las mismas del formulario normal). Las ayudas y la vuelta al panel al guardar las pone `UnillanosAutoevalHooks::formAlter()` solo en `node_programa_acreditacion_form` y `media_documento_acreditacion_form`, con `t()`. No hay módulos nuevos ni cambios al modelo de datos. Decisión: ayudas desde código (y no desde la configuración del campo) para que el formulario completo no cambie, porque Drupal no permite una descripción distinta por modo de formulario.
- **Panel:** «Editar la ficha» y «Subir un documento» llevan a estos formularios; en cada programa con proceso aparecen además los documentos de sus procesos («Editar el documento …»). El resto de accesos (procesos, valoraciones, hallazgos, plan, metas, seguimientos) sigue igual.
- **Pruebas:** `FormulariosSimplesTest` (configuración exacta, el rol ve y guarda la ficha y el documento, no ve campos administrativos, 403 sin permiso, 404 de otro tipo, el formulario completo no cambia, ayudas sin tecnicismos, vuelta al panel, enlaces del panel).

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
- [ ] Desactivar el módulo `unillanos_guia` (`/guia-estilos` es público y muestra datos inventados).
- [ ] Control de acceso a `public://`: hoy un documento no publicado sigue accesible por su URL directa; decidir y aplicar la protección.
- [ ] Documentación al día: `README.md`, `CLAUDE.md` y los tres documentos del MER reflejan lo implementado.

### Rediseño del micrositio (rama `rediseno-micrositio`)

Rediseño visual por etapas sobre `main` tras la Fase 7. Reglas: solo CSS y marcado compatible; las clases `ua-*` y los atributos `data-ua-*` no se renombran; la huella de contenido (texto visible, enlaces, encabezados, ids, `data-ua-*`, ARIA y formularios) se compara contra la base de cada etapa y solo se aceptan diferencias deliberadas.

| Etapa | Qué cambió |
|---|---|
| 1 | Tokens de color y tipografía Public Sans (400 y 600), un solo anillo de foco. |
| 2a | Franja superior, cabecera y pie con configuración (`unillanos_autoeval.micrositio`, servicio `DatosMarco`). |
| 3 | Botones, tarjetas, etiquetas, buscador de programas y placeholder con nivel y sede. |
| 4a | Hero y ficha lateral de la página de programa (componentes `media`, `kv-list`; estilos de imagen `unillanos_hero_*`). |
| 4b | Rejilla de factores y pestañas; plan de mejoramiento (tarjetas bajo 760 px) y documentos del programa. |
| 5 | `/programas` con tarjetas teñidas por facultad (`facultad_sigla` en `ListadoProgramas`; clase `ua-prog--{sigla}`). |
| 6 | `/documentos` con panel de filtros y conteos (componente `doc-filter`; conteos en `DocumentosPublicados`); título de `doc-list` en 600. |
| 7 | Portada: hero verde azulado, cifras y pasos; ajustes 4 y 7 de la Fase 7 (`cta-row` con segundo botón; tarjeta única a todo el ancho). |
| 8 | Limpieza: formulario de administración del micrositio, retiro de DM Sans y de tokens sin uso, documentación. |

**Configuración del micrositio:** `/admin/config/unillanos/micrositio` (permiso `edit unillanos micrositio`) edita nombre, franja superior, enlace de accesibilidad y pie. Los enlaces se escriben «Título | URL»; las URL se validan con la misma regla que aplica `DatosMarco` al pintar (ruta con una sola barra, `#`, `?`, `https://`, `mailto:` o `tel:`). Tras guardar, `ddev drush cex -y` y commit de `drupal/config/sync`.

**Cabecera con GOV.CO y redes (rama `cabecera-pie-y-edicion`, commit 1):** el botón «Ingresar» / «Mi cuenta» ya no está en la cabecera ni en el menú móvil (la ruta `/user/login` sigue existiendo; los componentes `header` y `nav` conservan sus props opcionales `login_url` y `login`, que ya no se pasan). En la franja superior (oscura) van, a la derecha, el enlace de accesibilidad (si tiene URL), los íconos de Facebook, X, Instagram y YouTube (componente `redes`, 44 px, misma pestaña, nombre accesible «Facebook de la Universidad de los Llanos», etc.) y el logo GOV.CO enlazado (`images/logos/govco-blanco.svg`, archivo oficial para fondo oscuro, sin modificar). Las URL viven en `unillanos_autoeval.micrositio` (`url_govco` y `redes_sociales.{facebook,x,instagram,youtube}`) y se editan en `/admin/config/unillanos/micrositio`; una red sin URL segura no muestra su ícono. Valores de origen (8 de noviembre de 2026, pie de https://acreditacion.unillanos.edu.co): Facebook `https://www.facebook.com/UnillanosOficial/`, X `https://twitter.com/unillanos_?lang=es`, Instagram `https://www.instagram.com/unillanos_/`, YouTube `https://www.youtube.com/channel/UCmhXbLrVeyotUiBsaPBcxcA`, GOV.CO `https://www.gov.co/`. Decisión: la franja es oscura y el logo oficial de GOV.CO disponible es el de fondo oscuro (no se recolorea), por eso los íconos y GOV.CO van en la franja y no en la barra blanca. Los orígenes y licencias de los logos y de los íconos (Simple Icons, CC0) están en el README del tema.

**Pie con la estructura del sitio de Acreditación (commit 2):** el pie oscuro lleva cuatro columnas (Ubícanos con tres campus y horario; Contacto; Líneas de atención con las redes debajo; Políticas de privacidad y términos de uso), los enlaces del menú del pie (fila «Explora», que conserva lo que tenga el menú `footer`), la fila de logos (Universidad de los Llanos, Vigilada MinEducación, CO Colombia, GOV.CO, cada uno enlazado a su sitio oficial con nombre accesible) y la línea «© Universidad de los Llanos 1974 - <año actual> Reservados todos los derechos» con el NIT. Todo sale de `unillanos_autoeval.micrositio` (`pie.ubicanos`, `pie.contacto`, `pie.lineas`, `pie.redes_titulo`, `pie.politicas`, `pie.logos`, `pie.derechos`; las redes son las de la cabecera) y se edita por grupos en `/admin/config/unillanos/micrositio`. El año final lo pone `DatosMarco` con el reloj del sistema (`datetime.time`) y la página caduca el 1 de enero siguiente; solo el año de inicio (1974) está en la configuración. Columnas: 4 en escritorio, 2 hasta 1080 px, 1 hasta 560 px. **Se retiró** (la nueva estructura lo cubre o no tenía datos): el bloque de marca (tres logos del prototipo, nombre del sistema, descripción, institución y nota de sedes), la fila de «entidades» (vacía), las columnas «Sedes», «Contáctanos», «Explora» y «Síguenos», y los enlaces y el aviso legales del pie; sus claves se quitaron de la configuración y del formulario (`titulo`, `descripcion`, `institucion`, `nota_institucion`, `entidades`, `sedes`, `contacto`, `redes`, `enlaces_legales`, `legal`). Los datos se tomaron del HTML de https://acreditacion.unillanos.edu.co el 8 de noviembre de 2026; diferencias con la captura y dudas: `docs/pendientes-acreditacion.md`, sección 26. Logos: `images/logos/` y README del tema (fuente real de cada archivo).

**Ruta de acceso propia (commit 3) — solo documentación interna, no aparece en la interfaz pública:** `/acceso-acreditacion` (ruta `unillanos_autoeval.acceso`, `AccesoController`) muestra el formulario de inicio de sesión de Drupal (el mismo `UserLoginForm`: mismas validaciones y misma protección contra intentos repetidos, `user.flood`) a quien no tiene sesión; quien ya la tiene va a la portada. Tras iniciar sesión se redirige a la portada (`UnillanosAutoevalHooks::formAlter()` añade el manejador `alPortada` solo en esta ruta y solo si la URL no trae `destination`). La página lleva `noindex, nofollow`. **Ningún enlace público apunta a esta ruta ni a `/user/login`** (que sigue funcionando como respaldo del administrador). Quien administra las cuentas entrega la dirección por un canal interno (la ruta no está en menús, mapa del sitio ni textos). Las cuentas las crea el administrador a mano (`register: admin_only`).

**Modo edición con lápices (commit 4):** para quien tiene el permiso existente `access acreditacion panel` (rol Acreditación y administrador; sin permisos nuevos) la franja superior lleva una barra con el botón «Modo edición» (`aria-pressed`; el estado se guarda en `localStorage` dentro de `try/catch` y, si falla, empieza apagado), el enlace «Editar la cabecera y el pie» (solo con `edit unillanos micrositio`) y «Cerrar sesión» (la confirmación de Drupal). Con el modo encendido (`html[data-ua-edicion="on"]`) se ven los lápices y los «+», cada uno un enlace (componente `edit-link`, 44 px, nombre accesible, misma pestaña, `?destination=` a la página actual) a una pantalla que ya existe. Dónde: **página de programa**, lápiz de la ficha (formulario simple), del plan de mejoramiento, de cada meta (fila del plan) y de la valoración de cada factor (panel del factor), «+» para crear la valoración de un factor sin ella (precarga `?proceso=` y `?elemento=`, este último con una regla nueva en `entityPrepareForm()` para el vocabulario `elemento_modelo`, sin cambiar el modelo) y «+ Añadir un documento» (formulario simple con el proceso precargado); **`/programas`**, «+ Añadir programa» sobre la lista y el lápiz de la ficha en cada tarjeta (fuera del enlace de la tarjeta); **portada, cabecera y pie**, el lápiz del micrositio (en la barra y en el pie). Cada enlace lo arma `EnlacesEdicion` y solo existe si la persona tiene acceso real a la ruta de destino (comprobación de acceso de la ruta y de la entidad); las páginas llevan los contextos de caché `user.permissions` (y `url.path`/`url.query_args` solo para quien tiene el permiso, por el destino). El HTML de un anónimo o de alguien sin el permiso no cambia. Límites: el «+» de valoración solo aparece en los factores que la página ya muestra (con hallazgos y sin valoración); los factores sin ningún dato publicado no se listan. Los datos de los servicios ganaron claves de apoyo que los componentes ignoran (`factor_id`, `valoracion_id`, `meta_id`, `plan_id`).

**Panel lateral (commit 5):** con JavaScript, los lápices y los «+» abren su pantalla en el panel lateral de Drupal (off-canvas, herramientas de diálogo de core: los enlaces del componente `edit-link` llevan `use-ajax` y `data-dialog-type="dialog"`, `data-dialog-renderer="off_canvas"`; la biblioteca `unillanos/edicion` depende de `core/drupal.dialog.ajax` y `core/drupal.dialog.off_canvas`). Sin JavaScript siguen siendo enlaces normales (commit 4). En una petición del panel, `UnillanosAutoevalHooks::enPanelLateral()` (vía `hook_form_alter`, solo para quien tiene `access acreditacion panel`) envía el botón de guardar por AJAX: con errores el formulario se vuelve a pintar con sus mensajes dentro del panel; sin errores `devolverFormularioAlPanel()` cierra el panel y redirige a la página de origen (`?destination=`, solo rutas internas), que se recarga con el cambio y el mensaje de Drupal. Detalle técnico: al enviar, Drupal repite `_wrapper_format` en la URL (el del panel y el de AJAX) y PHP se queda con el último, por eso el panel se reconoce por la URL completa de la petición. En el panel se abren y guardan bien la ficha simple, la valoración, el plan, la meta, el micrositio, «Añadir programa» y «Añadir un documento» (comprobado en Edge a 1440, 390 y 320 px: el panel ocupa 520 px en escritorio y todo el ancho en móvil; el foco entra al panel y, al cerrar con Escape, vuelve al lápiz). El botón del campo de imagen («Subir al servidor») y los botones internos de los widgets (metas con programación anual e indicadores) usan sus propios AJAX de Drupal y no se tocaron.

**Eliminar con límites (paso 14, commit 1):** el rol `acreditacion` gana solo `delete own <tipo> content` para programa, proceso, valoración, hallazgo, plan, meta y seguimiento, y `delete own documento media`; nunca «cualquier», ni lineamiento, ni taxonomía. «Propio» es el autor (uid) del contenido: lo cargado por migración (autor anónimo) no es de Acreditación y no se puede borrar. `hook_entity_access` (operación `delete`, `UnillanosAutoevalHooks::entityAccess()` con `EntidadesDependientes`) niega el borrado si otro contenido todavía apunta a él, según los campos de referencia reales: programa ← proceso; proceso ← valoración, hallazgo, plan y documento; hallazgo ← meta; plan ← meta; meta ← seguimiento; documento ← valoración. Se consulta con entity queries, sin SQL; el resultado varía por permisos y se invalida con las listas de los tipos que pueden depender. **Administrador:** quien tiene `bypass node access` (nodos) o `administer media` (medios) conserva el acceso de núcleo y no se bloquea por dependientes (puede borrar un proceso con valoraciones; es su responsabilidad). Cada borrado de esos tipos queda en el registro del sitio (canal `unillanos_autoeval`: tipo, id, título, usuario, uid y fecha); `dblog` ya está activo.

**«Eliminar» en el panel lateral (paso 14, commit 2):** en la edición abierta desde un lápiz, el enlace «Eliminar» de núcleo (que solo existe con acceso real de borrado) se abre en el mismo panel (`UnillanosAutoevalHooks::eliminarEnPanel()`). La confirmación dice qué se elimina (tipo y título), a qué programa y proceso pertenece (`PertenenciaDeContenido`), «Esta acción no se puede deshacer.», botón rojo «Sí, eliminar» (rojo oscuro #85030b con texto blanco, contraste 10,4) y «Cancelar» (cierra el panel y el foco vuelve al lápiz). Si el borrado está bloqueado por dependientes, en lugar del botón aparece «Todavía no se puede eliminar. … Elimina primero:» con la lista (hasta 10) y enlaces que abren cada dependiente en el panel; solo se muestra a quien sería el autor y tiene el permiso de borrar lo propio. Tras eliminar se cierra el panel y se recarga la página de origen (`?destination=`); si lo borrado era la propia página (por ejemplo el programa), el enlace «Eliminar» lleva `destination` a `/programas` y Drupal muestra su mensaje de confirmación. Sin JavaScript todo sigue funcionando como páginas normales de Drupal. Probado en Edge con la cuenta `acreditacion_prueba` (uid 19). **Nota sobre las pruebas del paso 13:** `drush uli acreditacion_prueba` toma el argumento como ruta de destino y entraba como el administrador (uid 1), y el navegador conservaba esa sesión; los scripts ahora usan `drush uli --name=acreditacion_prueba` y limpian las cookies. Las capturas y mediciones del paso 13 «como Acreditación» fueron en realidad como administrador.

**«Cambiar foto» (paso 14, commit 3):** sobre la cabecera del programa, en modo edición y solo para quien puede editar el programa, aparece «Cambiar foto» (o «Añadir foto» si no tiene) en la esquina superior derecha (`pagehead`, propiedad opcional `photo_edit`, enlace `unillanos:edit-link`). Abre en el panel lateral el modo de formulario nuevo `node.foto` del programa (`core.entity_form_mode.node.foto` y `core.entity_form_display.node.programa.foto`, en `config/sync` y en `config/optional` del módulo), con **solo** el campo que ya existe, `field_imagen`; la clase del formulario se registra en `entityTypeAlter()`. Ruta `/admin/acreditacion/programa/{node}/foto` (`_entity_access: node.update`, 404 si no es un programa). Límites en la configuración del campo: JPG, PNG o WebP (`png jpg jpeg webp`), 5 MB, ancho mínimo 1280 px (`min_resolution: 1280x1`) y descripción (texto alternativo) obligatoria. Ayudas desde `formAlter()`: «Usa solo fotos de la Universidad o con permiso de uso.» con formatos, peso y ancho leídos del campo, y bajo la descripción «Describe lo que se ve en la foto, por ejemplo: estudiantes en el laboratorio de electrónica.». El formulario no muestra «Eliminar» (borraría el programa) ni las notas de revisión. Los errores de la foto (muy pequeña, peso, formato, falta la descripción) se pintan dentro del panel; el mensaje de «foto muy pequeña» no existe en la traducción de core y se agrega en `translations/es.po` del módulo (`ddev drush locale:import es /var/www/html/drupal/web/modules/custom/unillanos_autoeval/translations/es.po` tras instalar). Tras guardar, el panel se cierra y la página recarga con la foto nueva; el velo de la cabecera no baja de 0,5 de opacidad (con foto clara, el texto sigue legible). Detalle técnico: una subida AJAX reconstruye el formulario con otra URL (sin el formato del panel), por eso `enPanelLateral()` recuerda en el estado del formulario (`ua_en_panel`) que se abrió en el panel; sin eso el botón Guardar perdía su `#ajax`. Los botones de subir/quitar archivo dentro del panel miden 44 px. El arrastrar y soltar no se agrega: el widget de imagen de core no lo ofrece y no se instalaron módulos contrib. Atención: el texto de «foto muy pequeña» de `es.po` lleva «1280 píxeles» escrito; si cambia `min_resolution` del campo, hay que actualizarlo (las ayudas del formulario sí leen el valor del campo). La regla de 1280 px y de descripción obligatoria se aplica al volver a guardar programas que ya tengan una foto menor o sin descripción: confirmar con Acreditación. Pruebas: `FotoProgramaTest`.

**Sin la barra lateral de Drupal (paso 14, commit 4):** el rol `acreditacion` pierde `access navigation` (y la dependencia del módulo `navigation`); conserva `view the administration theme`, así que el panel lateral, los lápices y los formularios se ven igual. Sin esa barra, la página de Electrónica a 320 px mide 320 (antes 331). El administrador conserva su barra. En la barra del modo edición hay un botón «Panel avanzado» (`EnlacesEdicion::panelAvanzado()`, solo con `access acreditacion panel`; enlace normal a `/admin/acreditacion`, no abre el panel lateral) y en `/admin/acreditacion` un enlace «Volver al sitio» a la portada. **Traducciones propias:** el encabezado `Plural-Forms` de `translations/es.po` debe ser `nplurals=2; plural=(n != 1);`; con otra fórmula (por ejemplo `n > 1`) el import cambia el plural del idioma y el sitio muestra «Se muestra 0 programa».

**Cierre del rediseño:** los archivos de DM Sans y su licencia se borraron de `themes/custom/unillanos/fonts/` (queda Public Sans con su `OFL-PublicSans.txt`). `hyphens: auto` solo se aplica a párrafos de varias líneas (textos de fortalezas y de aspectos por mejorar), nunca a títulos, etiquetas, botones, migas ni tarjetas. El filtro Twig `|sin_cortes` (módulo `unillanos_autoeval`) hace indivisibles los rangos de años («2018–2022», «2024-2 – 2030») en los componentes que imprimen texto libre. Los rótulos de las pestañas de factores pasan a varias líneas en lugar de recortarse con «…». Las seis carpetas de componentes sin versionar (`pane`, `progsearch`, `reco-form`, `scope`, `tool`, `view-tabs`; solo CSS, ninguna plantilla las usaba) se archivaron en `~/respaldos/componentes-sin-versionar.tar.gz`, fuera del repositorio.

### Tarjeta de programa según la plantilla Pregrado (rama `ajuste-programas-plantilla`)

`program-card` (usada en `/programas` y en «Otros programas de la facultad» de la página de programa) tiene seis líneas fijas, alineadas a la izquierda: etiqueta «NIVEL · MODALIDAD» (rojo texto, 12 px, mayúsculas; sin modalidad, solo el nivel), nombre (Public Sans 600, 20/28), facultad (gris, 14/20), «duración · sede» (gris, 14/20; «1 semestre» / «N semestres»), píldora «Con autoevaluación» (componente `estado`, 30 px de alto) y «Ver programa →». Sin icono de birrete, sin filete superior y **sin tinte por facultad**: la clase `ua-prog--{sigla}` y sus variables `--prog-*` se conservan para la futura rejilla de facultades, pero ninguna regla de la tarjeta las usa. Se retiraron el icono circular (decorativo) y las etiquetas (`ua-prog__icon`, `ua-prog__meta`); se agregaron `ua-prog__eyebrow`, `ua-prog__datos`, `ua-prog__estado` y `ua-prog__hueco`. En «Otros programas de la facultad» la facultad no se pasa (es la de la página), así que esa línea se omite; el resto es igual.

**Hueco reservado (excepción deliberada a «lo vacío se omite»):** si no hay duración ni sede, o no hay autoevaluación, la línea existe en el marcado con la misma altura que tendría con dato, **sin texto** (nada de «—», «Sin dato» ni «Pendiente») y con `aria-hidden="true"`, para que todas las tarjetas tengan la misma estructura y la misma altura por fila. Los huecos no entran en `aria-describedby`. `ListadoProgramas::tarjetas()` entrega `modalidad` (de `field_modalidad`, solo si tiene valor) y la tarjeta lee `duracion` si la clave existe.

**Duración del programa (cambio de modelo autorizado solo para este campo):** `node.programa.field_duracion` («Duración (semestres)»), entero sin signo, opcional, mínimo 1 y máximo 20, con la ayuda «Número de semestres que dura el programa. Si no lo sabe, déjelo en blanco.» (por confirmar, `docs/pendientes-acreditacion.md`, sección 25). Va en el formulario completo (después de la sede) y en la ficha simple de Acreditación (modo `acreditacion`, después de «Sede»); el rol `acreditacion` ya edita los programas y no necesita permisos nuevos. `ListadoProgramas::tarjetas()` entrega `duracion` (entero) solo si tiene valor y la tarjeta imprime «N semestres». La migración `programas` lee una columna **opcional** `duracion` (si el CSV no la trae, el campo queda vacío); los CSV de `datos/semillas/` no se modificaron y no se cargó ninguna duración. Reflejado en `MER.md` (`duracion_semestres`), `diccionario-datos.md`, `modelo-de-contenido.md` y `scripts/crear_modelo.php`.

### Registro de avance

Marca cada fase cuando ya esté fusionada en `main`:

- [x] Fase 1 · [x] Fase 2 · [x] Fase 3 · [x] Fase 4 · [x] Fase 5 · [x] Fase 6 · [ ] Fase 7 · [ ] Fase 8 · [ ] Fase 9

**Fase 6 fusionada en `main`** (sección 10). **Fase 7 cerrada a falta del merge** (rama `fase-7-vistas`, sección 11); **siguiente: Fase 8** (rama `fase-8-webform-roles`, sección 11).

**Pendientes abiertos** (detalle en 9.5; ninguno bloquea la Fase 6):

- Confirmar con Acreditación `proceso_id = PR-IE-2022` en el plan de Ingeniería Electrónica.
- Verificar en el Excel el proyecto institucional de las metas M15 y M16 (candidatos PI10 y PI12).
- Completar modalidad, sede, SNIES, director, resoluciones y municipio de la sede de los 40 programas.
- Seguimientos en estado `reportado` y columnas de los CSV que no se importan: decidir qué hacer.
- Títulos de valoraciones y seguimientos con `auto_entitylabel` y Pathauto (no se hizo en la Fase 7).

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
| El menú o la página de un programa no refleja un cambio de servicios o de enlaces tras actualizar | `ddev drush cr`: reconstruye el contenedor y la tabla de enlaces de menú (sección 11, Fase 7). |
| `.git/index.lock` impide commits | Si no hay otro git corriendo: `rm .git/index.lock`. |

## 15. Cuando entre tu compañero (adelanto)

1. **Su instalación completa:** clona el repositorio y corre `ddev start`, `ddev composer install`, `ddev drush si --existing-config -y` y `ddev drush migrate:import --group=unillanos`. Tiene el mismo sitio.
2. **Pull Requests obligatorios:** protejan `main` en GitHub (Settings → Branches) para que nada entre sin revisión. El merge de la sección 2.6 pasa a ser el botón "Merge pull request".
3. **Reparto por carpetas para no pisarse:** uno toma el tema (`themes/custom`) y el otro el modelo, las migraciones y las vistas (`modules/custom`, `config/sync`). La configuración es el punto de choque: avisen antes de exportarla y hagan `git pull` + `drush cim` antes de empezar cada día.
4. **Mismo contexto para los dos:** `CLAUDE.md`, `.claude/settings.json` y estos documentos hacen que Claude Code trabaje igual en los dos computadores.
