# CLAUDE.md · Contexto del proyecto

## Qué es
Sistema de Información de Autoevaluación de la **Universidad de los Llanos (Unillanos)**, en **Drupal 11**. Publica, por programa académico y a nivel institucional:

- los procesos de autoevaluación (registro calificado y acreditación en alta calidad);
- las valoraciones por factor y característica;
- las fortalezas y los aspectos por mejorar;
- el plan de mejoramiento (formato FO-GCL-20) con su seguimiento semestral;
- los documentos y un formulario de participación.

Idioma del sitio y de la documentación: **español**. Público: comunidad universitaria y pares evaluadores.

## Documentos que debes leer antes de tocar código
1. `docs/IMPLEMENTACION_DRUPAL.md`: fases, comandos y criterios de "hecho".
2. `docs/mer/MER.md` y `docs/mer/diccionario-datos.md`: modelo de datos (MER v2, 20 entidades).
3. `docs/drupal/modelo-de-contenido.md`: **nombres de máquina y tipos de campo**. Es la fuente de verdad; no inventes nombres.
4. `plantilla/`: prototipo visual (HTML/CSS/JS). El tema de Drupal debe verse y comportarse igual.
5. `docs/fuentes/`: documentos originales. Son de solo lectura.

## Estructura
- `drupal/`: proyecto Composer (docroot `drupal/web`, configuración en `drupal/config/sync`).
- `drupal/web/modules/custom/unillanos_autoeval`: reglas de negocio.
- `drupal/web/modules/custom/unillanos_migrate`: migraciones CSV → Drupal.
- `drupal/web/themes/custom/unillanos`: tema basado en `plantilla/`.
- `datos/semillas/`: catálogos comunes (lineamientos, elementos del modelo CNA 2020 / CESU 2025 / Decreto 1330, escala, estamentos, sedes, facultades, programas, proyectos).
- `datos/importacion/<programa>/`: datos de cada programa. La carpeta contenedora es `/var/www/html/datos/...`.
- `datos/herramientas/plan_excel_a_csv.py`: conversor del Excel FO-GCL-20.

## Entorno y comandos
DDEV, configurado en la raíz del repositorio (`composer_root: drupal`, `docroot: drupal/web`). Usa siempre `ddev …`:
- `ddev drush cr` · `ddev drush cex -y` · `ddev drush cim -y` · `ddev drush updb -y`
- `ddev composer require drupal/<modulo>`
- `ddev drush migrate:import --group=unillanos` / `migrate:rollback`
- Logs: `ddev drush watchdog:show`

## Convenciones
- Nombres de máquina en español, sin tildes, en snake_case (`plan_mejoramiento`, `field_tipo_hallazgo`), tal como aparecen en `modelo-de-contenido.md`.
- Código según los Drupal coding standards. Comentarios y etiquetas de interfaz en español.
- Después de **cualquier** cambio de configuración: `ddev drush cex -y` e incluir `drupal/config/sync` en el commit.
- Nunca edites `web/core`, `web/modules/contrib` ni `vendor/`. Si necesitas un parche, usa `cweagans/composer-patches`.
- Los datos entran por migraciones desde CSV, nunca a mano. Si falta un dato, agrégalo al CSV.
- JS del tema: `Drupal.behaviors` + `once()`. Conserva los atributos `data-ua-*` del prototipo.
- Cada cambio de MER se refleja en los tres documentos: `docs/mer/MER.md`, `diccionario-datos.md` y `modelo-de-contenido.md`.

## Reglas del dominio (no obvias)
- Escala de la Universidad (Tabla 3.1 del informe): Pleno 4,8–5,0 · Alto 4,0–4,7 · Aceptable 3,0–3,9 · Insatisfactorio 2,6–2,9 · No se cumple 1,0–2,5. El grado se calcula; no se digita.
- Las valoraciones se guardan solo para factor y característica. En condiciones de registro calificado se guarda el estado (cumple / cumple parcialmente / no cumple) más el documento soporte.
- Aspectos valorados por debajo de 4 van al plan de mejoramiento; los valorados en 4 van al plan de acción del programa.
- En el Excel FO-GCL-20 una meta puede atender varias oportunidades de mejora y tener varios indicadores, con programación anual de 2024 a 2030. Las hojas `2024-2` … `2030-2` son seguimientos semestrales.
- Algunos planes nombran factores con la numeración CNA 2013 (p. ej. "Factor 5 - Visibilidad"). Para eso existe `equivalencias_factores_plan.csv`.

## Antes de hacer algo destructivo, pregunta
`ddev delete`, `drush sql:drop`, `drush si` sobre una base con contenido, `migrate:rollback` de todo el grupo, `git push --force`, o borrar archivos de `docs/fuentes/`.
