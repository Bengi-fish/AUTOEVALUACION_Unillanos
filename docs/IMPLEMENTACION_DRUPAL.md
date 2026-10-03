# Guía de implementación en Drupal · paso a paso (trabajo individual)

Sistema de Información de Autoevaluación · Universidad de los Llanos

Esta guía lleva el proyecto desde el repositorio actual (prototipo + MER + documentos fuente) hasta un sitio Drupal funcionando en tu computador, con el contenido real de Ingeniería Electrónica cargado. Está pensada para hacerla con **Claude Code** abierto en la raíz del repositorio: cada fase trae los comandos que corres tú y el *prompt* que le das a Claude.

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
6. **Hallazgos de otros programas:** por ahora solo existe el informe de Ingeniería Electrónica.

**Versión de Drupal:** Drupal **11** (la rama estable con soporte largo y con todos los módulos que usamos). Si cuando empiecen ya salió Drupal 12, quédense en 11 hasta que `webform`, `paragraphs` y los módulos de migración publiquen versiones estables para 12.

---

## 1. Cómo se organiza el repositorio

```
AUTOEVALUACION_Unillanos/
├── CLAUDE.md                  ← contexto que Claude Code lee al abrir el proyecto
├── README.md
├── .gitignore
├── .ddev/                     ← (fase 1) entorno local, se crea con `ddev config`
├── docs/
│   ├── IMPLEMENTACION_DRUPAL.md   ← esta guía
│   ├── fuentes/               ← documentos que nos entregaron (no se editan)
│   │   ├── acuerdo-cesu-001-2025.docx
│   │   ├── informe-autoevaluacion-2022-ingenieria-electronica.pdf
│   │   └── plan-mejoramiento-ingenieria-electronica-2024-2-2030.xlsx
│   ├── mer/
│   │   ├── MER.md             ← MER en texto (Mermaid, GitHub lo dibuja)
│   │   ├── diccionario-datos.md
│   │   ├── MER-v2.png         ← exportado de Canva
│   │   └── MER-v2.pdf         ← exportado de Canva
│   └── drupal/
│       └── modelo-de-contenido.md ← MER → tipos de contenido, campos, nombres de máquina
├── datos/
│   ├── semillas/              ← catálogos comunes a todos los programas (CSV)
│   ├── importacion/<programa>/← datos de cada programa (CSV)
│   └── herramientas/          ← conversor Excel FO-GCL-20 → CSV
├── plantilla/                 ← prototipo HTML/CSS/JS (referencia visual, no se borra)
└── drupal/                    ← (fase 1) proyecto Drupal: composer.json, web/, config/sync/
```

Reglas de oro:

- **Lo que va a Git:** código propio (`drupal/web/modules/custom`, `drupal/web/themes/custom`), `composer.json` y `composer.lock`, la configuración exportada (`drupal/config/sync`), los CSV y la documentación.
- **Lo que NO va a Git:** `vendor/`, el núcleo de Drupal y los módulos descargados (se reinstalan con `composer install`), la base de datos y los archivos subidos (`sites/default/files`). El `.gitignore` ya lo cubre.
- **El contenido no se copia a mano entre computadores:** se reconstruye importando los CSV (fase 5). Por eso tu compañero podrá tener el mismo sitio con dos comandos.

---

## 2. Fase 0 · Preparar tu computador (una sola vez)

Instala:

1. **Git** y tu cuenta de GitHub configurada (`git config --global user.name "…"` y `user.email`).
2. **Docker**: Docker Desktop (Windows/Mac) u OrbStack (Mac). En Windows usa **WSL2** y trabaja dentro de la carpeta de Linux (`~/proyectos/...`), no en `C:\`.
3. **DDEV**: sigue https://ddev.readthedocs.io/en/stable/users/install/ . Te da PHP, base de datos, Composer y Drush sin instalarlos a mano.
4. **Claude Code** en la terminal, o la extensión de VS Code.
5. **Python 3** y `pip install openpyxl` (solo para el conversor del Excel).

Comprueba:

```bash
git --version
docker --version
ddev version
python3 -c "import openpyxl; print('ok')"
```

Clona el repositorio (si no lo tienes ya) y crea una rama de trabajo:

```bash
git clone https://github.com/Bengi-fish/AUTOEVALUACION_Unillanos.git
cd AUTOEVALUACION_Unillanos
git checkout -b fase-1-instalacion
```

> **Ritmo de trabajo:** una rama por fase (`fase-1-instalacion`, `fase-2-modulos`…). Al terminar la fase: `drush cex`, commit, push y *pull request* a `main`. Aunque trabajes solo, así queda un historial limpio y tu compañero podrá revisar cada paso.

---

## 3. Fase 1 · Crear el proyecto Drupal con DDEV

El entorno DDEV se configura en la **raíz del repositorio** y Drupal vive en la carpeta `drupal/`. Así el contenedor también ve `datos/` y `docs/`, que son los que usa la importación.

```bash
# desde la raíz del repositorio
ddev config --project-type=drupal11 --project-name=autoeval-unillanos \
  --docroot=drupal/web --composer-root=drupal
ddev start
ddev composer create-project "drupal/recommended-project:^11"
ddev composer require drush/drush
```

Configura la carpeta de configuración **antes** de instalar. Abre `drupal/web/sites/default/settings.php` y agrega al final, **antes** del bloque que incluye `settings.ddev.php`:

```php
$settings['config_sync_directory'] = '../config/sync';
```

Instala Drupal en español:

```bash
mkdir -p drupal/config/sync
ddev drush site:install standard --locale=es \
  --site-name="Autoevaluación Unillanos" --account-name=admin --account-pass=admin -y
ddev drush config:export -y
ddev launch          # abre el sitio
ddev drush uli       # enlace para entrar como admin
```

**Hecho cuando:** el sitio abre en `https://autoeval-unillanos.ddev.site`, la interfaz está en español y `drupal/config/sync` tiene archivos `.yml`.

```bash
git add .ddev drupal/composer.json drupal/composer.lock drupal/config drupal/web/sites/default/settings.php
git commit -m "Fase 1: proyecto Drupal 11 con DDEV"
```

> Si `ddev composer create-project` se queja de que la carpeta no está vacía, revisa que `drupal/` no exista antes de correrlo. Si el error sigue, pídele a Claude Code: *"Lee docs/IMPLEMENTACION_DRUPAL.md fase 1 y resuelve este error de DDEV: …"*.

---

## 4. Fase 2 · Módulos y configuración base

```bash
git checkout -b fase-2-modulos
ddev composer require drupal/admin_toolbar drupal/pathauto drupal/paragraphs drupal/field_group \
  drupal/auto_entitylabel drupal/webform drupal/migrate_plus drupal/migrate_tools \
  drupal/migrate_source_csv drupal/better_exposed_filters
ddev drush en -y admin_toolbar admin_toolbar_tools pathauto paragraphs field_group auto_entitylabel \
  webform webform_ui migrate_plus migrate_tools migrate_source_csv better_exposed_filters \
  media media_library datetime link options
ddev drush locale:check && ddev drush locale:update
ddev drush config:export -y
```

Configura a mano, en la interfaz:

- **Regional:** zona horaria `America/Bogota`, país Colombia, primer día de la semana lunes.
- **Formato de fecha** corto `d/m/Y`.
- Desactiva el registro libre de usuarios (solo administradores crean cuentas).

Luego exporta la configuración y haz commit:

```bash
ddev drush cex -y
git add drupal && git commit -m "Fase 2: módulos contrib y configuración regional"
```

---

## 5. Fase 3 · Modelo de contenido (MER → Drupal)

Aquí se crean los vocabularios, tipos de contenido, paragraphs, el media `documento` y los campos. Todo está especificado en `docs/drupal/modelo-de-contenido.md`.

**Estrategia recomendada:** Claude Code escribe un script PHP **idempotente** (se puede correr varias veces sin duplicar nada) que crea toda la estructura con la Entity API. Tú lo revisas, lo corres y exportas la configuración. Es más rápido y menos propenso a errores que crear 150 campos a mano, y queda documentado.

```bash
git checkout -b fase-3-modelo
```

Prompt para Claude Code:

> Lee `CLAUDE.md`, `docs/mer/MER.md` y `docs/drupal/modelo-de-contenido.md`. Crea `drupal/scripts/crear_modelo.php`, un script idempotente para `ddev drush php:script` que cree, en este orden: 1) los vocabularios, 2) el nodo `lineamiento` y luego los demás tipos de contenido, 3) los tipos de paragraph, 4) el tipo de media `documento`, 5) todos los campos con su almacenamiento, instancia, etiqueta en español, obligatoriedad, cardinalidad y valores permitidos, 6) la visualización de formulario y la visualización por defecto de cada campo. No crees vistas ni el webform todavía. Al final muestra un resumen de lo creado. Después dime los comandos para correrlo y verificarlo.

Corre y verifica:

```bash
ddev drush php:script drupal/scripts/crear_modelo.php
ddev drush cr
ddev drush field:info node programa     # campos del programa (ajusta el nombre del comando si tu Drush usa otro)
ddev drush cex -y
```

Revisa en la interfaz (Estructura → Tipos de contenido / Taxonomía / Tipos de párrafo) que los nombres coincidan con el documento. Crea a mano un programa y un proceso de prueba y bórralos.

**Hecho cuando:** existen los 7 vocabularios, 8 tipos de contenido, 3 paragraphs y el media `documento`; `drush cex` no muestra cambios pendientes.

```bash
git add drupal && git commit -m "Fase 3: modelo de contenido según MER v2"
```

---

## 6. Fase 4 · Módulo propio `unillanos_autoeval` (reglas de negocio)

```bash
git checkout -b fase-4-reglas
```

Prompt para Claude Code:

> Crea el módulo `drupal/web/modules/custom/unillanos_autoeval` según la sección "Lógica del módulo" de `docs/drupal/modelo-de-contenido.md`: cálculo de `field_grado` en el presave de `valoracion`, restricciones de validación (proceso + elemento únicos; proceso con sede o programa, no ambos), servicio `AvanceMeta` y sugerencia de `field_destino` en hallazgos. Sigue los estándares de código de Drupal, con comentarios en español. Agrega pruebas Kernel para el cálculo del grado con los valores de `datos/semillas/grados_cumplimiento.csv` (5.0 → Pleno, 4.27 → Alto, 3.5 → Aceptable, 2.8 → Insatisfactorio, 2.0 → No se cumple).

```bash
ddev drush en -y unillanos_autoeval
ddev exec -d /var/www/html/drupal vendor/bin/phpunit -c web/core web/modules/custom/unillanos_autoeval   # si Claude configuró las pruebas
ddev drush cex -y
git add drupal && git commit -m "Fase 4: módulo unillanos_autoeval con reglas del modelo"
```

---

## 7. Fase 5 · Importar los datos (CSV → Drupal con Migrate)

Los datos entran **siempre** por importación, nunca a mano. Así cualquier persona reconstruye el mismo sitio.

### 7.1 Preparar los CSV de un programa

Los de Ingeniería Electrónica ya están en `datos/importacion/ingenieria-electronica/`. Para otro programa que llegue con su Excel FO-GCL-20:

```bash
python3 datos/herramientas/plan_excel_a_csv.py "docs/fuentes/<plan-del-programa>.xlsx" datos/importacion/<slug-del-programa>/
```

Después revisa a mano:

- `responsables.csv`: unifica los nombres repetidos o mal escritos, por ejemplo "Profsores" y "Profesores", o "Dirección General de Investigación" y "Dirección General de Investigaciones".
- `equivalencias_factores_plan.csv`: asigna a cada texto de factor del Excel su elemento del modelo. Ojo: algunos planes usan la numeración de factores de 2013.
- `valoraciones_factores.csv` y `proceso.csv`: se llenan a partir del informe del programa.

### 7.2 Extraer fortalezas y aspectos por mejorar del informe (con Claude)

Prompt:

> Lee `docs/fuentes/informe-autoevaluacion-2022-ingenieria-electronica.pdf`. Para cada factor (1 a 12) extrae la tabla "Fortalezas / Aspectos por mejorar" y genera `datos/importacion/ingenieria-electronica/hallazgos_informe.csv` con las columnas `id,proceso_id,elemento_id,tipo,descripcion,origen,destino,destacado`. Usa `proceso_id=PR-IE-2022`, `elemento_id` = `CNA20-Fxx`, `tipo` = `fortaleza` o `aspecto_por_mejorar` y `origen=autoevaluacion`. Copia el texto tal cual, sin resumir. Al final dime cuántas filas salieron por factor para que yo las compare con el PDF.

Coteja las cifras con el PDF antes de seguir. Si esta revisión se salta, un error del informe termina publicado en el sitio.

### 7.3 Migraciones

Prompt:

> Crea el módulo `drupal/web/modules/custom/unillanos_migrate` con migraciones YAML (grupo `unillanos`, fuente `csv` de migrate_source_csv) que lean `/var/www/html/datos/...`. Orden: `sedes`, `facultades`, `estamentos`, `grados`, `responsables`, `proyectos`, `lineamientos`, `elementos_modelo` (dos pasadas: términos y luego padre/equivalencia), `programas`, `procesos`, `valoraciones`, `hallazgos_informe`, `hallazgos_plan`, `planes`, `metas` (con indicadores y programación anual como paragraphs), `seguimientos`. Usa `migration_lookup` para las referencias y las claves `id` de los CSV como identificadores de origen. Hazlo por programa: la carpeta de importación es un parámetro, para poder agregar programas sin copiar YAML.

```bash
ddev drush en -y unillanos_migrate
ddev drush migrate:status --group=unillanos
ddev drush migrate:import --group=unillanos
# si algo sale mal:
ddev drush migrate:rollback --group=unillanos
ddev drush migrate:reset-status <id_migracion>
```

**Hecho cuando:** `migrate:status` muestra todo importado sin errores y en la interfaz el programa Ingeniería Electrónica tiene su proceso, 12 valoraciones de factor, el plan con 17 metas y sus seguimientos.

```bash
ddev drush cex -y
git add drupal datos && git commit -m "Fase 5: importación de catálogos y datos de Ingeniería Electrónica"
```

---

## 8. Fase 6 · Tema visual desde el prototipo

```bash
git checkout -b fase-6-tema
ddev exec -d /var/www/html/drupal/web php core/scripts/drupal generate-theme unillanos --path themes/custom
ddev drush theme:enable unillanos && ddev drush config:set system.theme default unillanos -y
```

Prompt:

> Convierte `plantilla/` en el tema `drupal/web/themes/custom/unillanos`. 1) Copia `style.css` en `css/` y `app.js` en `js/`, y declara una librería global. 2) Adapta `app.js` a `Drupal.behaviors.unillanos` usando `once()`, sin cambiar la lógica de las pestañas animadas, el medidor ni el filtro del plan. 3) Crea `page.html.twig` con el encabezado, el menú plano y el pie del prototipo, más las regiones necesarias. 4) Crea plantillas para `node--programa--full`, `node--proceso--full` y `views-view--plan` que reproduzcan el HTML del prototipo con los atributos `data-ua-views`, `data-routes` y `data-view`. 5) Copia los logos de `plantilla/assets`. No uses datos de `datos-ejemplo.js`: todo debe salir de los campos de Drupal. Respeta `prefers-reduced-motion`.

Compara lado a lado `plantilla/programa-ejemplo.html` con el nodo del programa en Drupal, en escritorio y en móvil.

---

## 9. Fase 7 · Vistas y páginas

Prompt:

> Con la tabla "Páginas del prototipo → Drupal" de `docs/drupal/modelo-de-contenido.md`, crea las vistas `programas` (página con filtros expuestos), `valoraciones_proceso` (bloque por factor y característica, con argumento proceso), `hallazgos_proceso`, `plan_mejoramiento` (bloque filtrable por factor a través de los hallazgos), `documentos` (página con pestañas por categoría) y los bloques de la portada. Configura Pathauto: `/programas/[node:field_clave]`, `/procesos/[node:nid]`. Exporta la configuración.

**Hecho cuando:** las 6 páginas del prototipo existen en Drupal con datos reales y el enlace "ver acciones de este factor" abre el plan ya filtrado.

---

## 10. Fase 8 · Participa, roles y permisos

Prompt:

> Crea el webform `participa` según `docs/drupal/modelo-de-contenido.md` (incluye consentimiento obligatorio, `estado_gestion` y `respuesta` solo para administradores) y colócalo en `/participa`. Crea los roles `editor_autoevaluacion` (gestiona procesos, valoraciones, hallazgos, planes, metas y seguimientos), `editor_documentos` (media) y `gestor_participacion` (envíos del webform). Anónimos: solo ver contenido publicado y enviar el formulario. Exporta la configuración.

Prueba enviar una recomendación como usuario anónimo y luego gestionarla como `gestor_participacion`.

---

## 11. Fase 9 · Revisión antes de mostrar

- [ ] `ddev drush cex` sin cambios pendientes y `git status` limpio.
- [ ] Reinstalación en limpio: `ddev drush si --existing-config -y && ddev drush migrate:import --group=unillanos` deja el sitio igual.
- [ ] Las cifras del sitio coinciden con el informe (valoración global 4,508 · 90 %, tabla 5.1) y con el Excel (17 metas, pesos de 1/17).
- [ ] Accesibilidad: contraste, navegación con teclado por las pestañas, textos alternativos en imágenes.
- [ ] Móvil (390 px) y escritorio (1440 px) comparados con `plantilla/`.
- [ ] Copia de seguridad: `ddev export-db --file=respaldo.sql.gz` guardada **fuera** del repositorio.

---

## 12. Comandos de todos los días

```bash
ddev start                    # encender el entorno
ddev drush uli                # entrar como admin
ddev drush cr                 # limpiar caché
ddev drush cex -y             # exportar configuración (después de cambiar algo en la interfaz)
ddev drush cim -y             # importar configuración (después de un git pull)
ddev composer install         # después de un git pull que cambió composer.lock
ddev drush updb -y            # actualizar la base de datos tras actualizar módulos
ddev drush watchdog:show      # ver errores recientes
ddev stop                     # apagar
```

Después de cada `git pull`, en este orden: `ddev composer install` → `ddev drush updb -y` → `ddev drush cim -y` → `ddev drush cr`.

## 13. Problemas frecuentes

| Síntoma | Solución |
|---|---|
| `drush cim` quiere borrar cosas que creaste | Creaste algo en la interfaz y no lo exportaste. Corre `drush cex` primero, haz commit y vuelve a importar. |
| "Configuration … depends on … that will not exist" | Falta habilitar un módulo: `drush en <módulo>` y repite. |
| La importación deja referencias vacías | El orden de las migraciones está mal o el `id` del CSV no coincide. Revisa `migrate:messages <id>`. |
| El sitio se ve sin estilos | `drush cr` y revisa que la librería del tema esté declarada en `unillanos.libraries.yml`. |
| Docker lento en Windows | Asegúrate de que el repositorio esté dentro de WSL2 (`/home/...`), no en `/mnt/c/...`. |

## 14. Cuando entre tu compañero (adelanto)

Lo que ya deja listo esta guía para trabajar en equipo:

1. **Su instalación completa:** clona el repositorio, corre `ddev start`, `ddev composer install`, `ddev drush si --existing-config -y` y `ddev drush migrate:import --group=unillanos`, y tiene el mismo sitio.
2. **Trabajo en ramas y *pull requests*:** protejan `main` en GitHub (Settings → Branches) para que nada entre sin revisión.
3. **Reparto por carpetas para no pisarse:** uno toma el tema (`themes/custom`) y el otro el modelo, las migraciones y las vistas (`modules/custom`, `config/sync`). La configuración es el punto de choque: avisen antes de exportarla y hagan `git pull` + `drush cim` antes de empezar cada día.
4. **Mismo contexto para los dos:** `CLAUDE.md` y estos documentos hacen que Claude Code trabaje igual en los dos computadores.
