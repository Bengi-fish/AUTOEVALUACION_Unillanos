# Guía de despliegue para la oficina de Sistemas

Esta guía describe cómo instalar el Sistema de Información de Autoevaluación de la Universidad de los Llanos en un servidor. Está escrita para un Linux con servidor web, PHP y base de datos MariaDB o MySQL, sin suponer un servidor concreto. Los archivos de la carpeta `deploy/` del repositorio son ejemplos que hay que adaptar.

El sitio es Drupal 11. El código vive en el repositorio; el contenido de ejemplo viene en un volcado de base de datos y en una carpeta de archivos que acompañan al paquete de entrega (ver `LEEME-SISTEMAS.md`).

## 1. Qué necesita

| Pieza | Requisito |
|---|---|
| Sistema | Linux con acceso por consola (SSH) y permisos para instalar paquetes. |
| Servidor web | Nginx con PHP-FPM, o Apache con PHP-FPM o `mod_php`. |
| PHP | 8.3 o superior (se desarrolló con 8.4), con las extensiones `gd`, `curl`, `mbstring`, `xml`, `zip`, `intl`, `opcache` y `pdo_mysql`. `memory_limit` de 256 MB o más. |
| Base de datos | MariaDB 10.6 o superior (se desarrolló con 11.8) o MySQL 8. Juego de caracteres `utf8mb4`. |
| Composer | Versión 2. |
| Git | Para bajar el código. |
| Dominio y HTTPS | Un dominio y un certificado válido. El sitio debe publicarse solo por HTTPS. |
| Correo | Un servidor SMTP para los correos del sitio, aún por definir (ver sección 8). |

Espacio: unos 500 MB para el código y las dependencias, más la base de datos y los archivos subidos (los de ejemplo ocupan pocos megabytes).

## 2. Qué lleva el paquete de entrega

| Archivo | Para qué |
|---|---|
| `LEEME-SISTEMAS.md` | Qué es cada archivo y en qué orden se usa. |
| Volcado de base de datos (`.sql.gz`) | La base con el contenido de ejemplo, ya saneada (correos y claves reemplazados). |
| Archivos públicos (`archivos-sitio.tar.gz`) | El contenido de `sites/default/files` (documentos e imágenes, incluidas las carpetas `galeria/` con las imágenes de los carruseles de los programas y de Institucional, `noticias/` y `carrusel/`). Las imágenes de `galeria/` no están en el repositorio: solo llegan con este paquete. |
| Los tres manuales en PDF | Manual de uso, esta guía y manual técnico. |
| `SHA256SUMS` | Sumas de verificación de todo lo anterior. |

Compruebe las sumas antes de usar nada:

```bash
sha256sum -c SHA256SUMS
```

## 3. Instalación

Todos los comandos se corren como el usuario que administra el sitio (no como `root` si es posible) y desde la carpeta donde quedó el repositorio, que en los ejemplos es `/var/www/autoeval`.

### 3.1. Bajar el código

```bash
cd /var/www
git clone <dirección del repositorio> autoeval
cd autoeval
git checkout <rama o etiqueta que indique LEEME-SISTEMAS.md>
```

### 3.2. Dependencias

```bash
cd drupal
composer install --no-dev --optimize-autoloader
cd ..
```

`--no-dev` deja fuera las herramientas de desarrollo. No use `composer update`: instala lo que dice `composer.lock`.

### 3.3. Base de datos

Cree una base de datos y un usuario propios (con una clave larga y propia, que no se escribe en ningún archivo del repositorio):

```sql
CREATE DATABASE autoeval CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'autoeval'@'localhost' IDENTIFIED BY '<clave larga y propia>';
GRANT ALL PRIVILEGES ON autoeval.* TO 'autoeval'@'localhost';
```

### 3.4. Configuración del servidor (`settings.php` y variables)

1. Copie el ejemplo de configuración de producción:

   ```bash
   cp deploy/settings.production.php.example drupal/web/sites/default/settings.php
   ```

   El repositorio trae su propio `settings.php` (el de desarrollo): al reemplazarlo, `git status` lo mostrará como modificado. Es normal: no suba ese cambio.

2. El ejemplo no lleva ningún secreto: la sal (`hash_salt`), los datos de la base y el dominio vienen de variables de entorno. Los nombres están en `deploy/env.example`. Defínalas donde corresponda a su servidor (el grupo de PHP-FPM, el servicio, o el archivo de entorno que use) y también en la consola desde la que corre `drush`. Genere la sal con `openssl rand -hex 32`.
3. Cree la carpeta de archivos privados **fuera** de la raíz web (por ejemplo `/var/www/autoeval-privado`) y póngala en la variable correspondiente.
4. Compruebe que `trusted_host_patterns` coincide con su dominio (el ejemplo lo arma desde la variable del dominio).
5. Los errores quedan ocultos y la carpeta de configuración es `drupal/config/sync`.

### 3.5. Servidor web

Apunte la raíz del sitio a `drupal/web`. Hay ejemplos para los dos servidores: `deploy/nginx.conf.example` y `deploy/apache-vhost.conf.example`. Actívelos con su dominio y su certificado, y recargue el servidor.

### 3.6. Permisos de archivos

```bash
mkdir -p drupal/web/sites/default/files
sudo chown -R <usuario del servidor web>:<grupo> drupal/web/sites/default/files /var/www/autoeval-privado
chmod -R u=rwX,g=rX,o= drupal/web/sites/default/files
chmod 444 drupal/web/sites/default/settings.php
```

Nunca use `chmod 777`.

### 3.7. Cargar el contenido de ejemplo

```bash
gunzip -c <volcado>.sql.gz | mysql -u autoeval -p autoeval
tar -xzf archivos-sitio.tar.gz -C drupal/web/sites/default/
```

(O con `drush`: `drush sql:cli < volcado.sql`.)

### 3.8. Desplegar

```bash
bash deploy/desplegar.sh
```

El script instala las dependencias sin las de desarrollo, pone el sitio en modo mantenimiento, corre `drush deploy` (actualizaciones de base, importación de configuración y limpieza de caché), desinstala el módulo de desarrollo `unillanos_guia` y quita el modo mantenimiento. Es seguro repetirlo y se detiene ante el primer error.

No hay que correr a mano los scripts de `drupal/scripts/` (`crear_modelo_sitio.php`, `crear_webform_participa.php`, `crear_galeria.php`): lo que crean ya está en `drupal/config/sync`. La importación de configuración que hace `drush deploy` trae:

- Los tipos de contenido `noticia`, `pagina_informativa` y `miembro_equipo` y el campo de imágenes `field_galeria` de `programa` y `proceso`.
- El webform `participa` (página `/participa`) y los roles `acreditacion`, `editor_documentos` y `gestor_participacion`.
- La página de inicio en `/portal` (se entra por `/`).

Los menús «Información» y «Noticias» no vienen de la configuración sino del módulo `unillanos_autoeval` (`unillanos_autoeval.links.menu.yml`): se registran solos al limpiar la caché, que ya hace `drush deploy`.

El despliegue también quita de `unillanos_autoeval.micrositio` las claves `franja_superior` y `url_govco` (la franja superior ya no existe), desinstala Olivero y borra sus bloques, y quita `zip` y `txt` de las extensiones del medio `documento`.

Contenido nuevo: la configuración no trae el contenido. Si la base se carga del volcado del paquete, las noticias, las páginas informativas y el equipo ya vienen dentro. Si se despliega sobre una base anterior que no los tiene, corra en el servidor, desde `drupal/`, las migraciones nuevas (leen los CSV de `datos/semillas/`, que vienen con el repositorio; correrlas dos veces no duplica):

```bash
vendor/bin/drush migrate:import archivos_noticias,noticias,paginas_informativas,equipo
vendor/bin/drush pathauto:aliases-generate create canonical_entities:node
```

### 3.9. El primer administrador

El volcado va saneado, así que las cuentas que traía ya no sirven. Cree la persona administradora (cambie el nombre, el correo y la clave por los suyos; la clave queda en el historial de la consola):

```bash
cd drupal
vendor/bin/drush user:create <nombre> --mail="<correo>" --password="<clave larga y propia>"
vendor/bin/drush user:role:add administrator <nombre>
```

Entre por `/user/login`. Luego cree las cuentas de Acreditación desde **Personas › Añadir usuario**, con el rol **Acreditación** (no hay registro abierto). Para quien solo gestiona documentos use el rol **Editor de documentos**, y para quien atiende las recomendaciones de `/participa`, **Gestor de participación**. Elimine las cuentas de prueba que haya.

Acreditación entra por `/acceso-acreditacion`, una dirección que no está enlazada en el sitio.

## 4. Cron

Drupal necesita una tarea periódica para limpiar caché, revisiones y avisos. Basta con una vez por hora:

```cron
0 * * * * cd /var/www/autoeval/drupal && vendor/bin/drush cron
```

(Hay una línea de ejemplo en `deploy/cron.example`.)

## 5. Respaldos y restauración

```bash
bash deploy/respaldar.sh
```

Guarda la base de datos y los archivos públicos con la fecha en una carpeta de respaldos **fuera del repositorio**. Programe el respaldo (por ejemplo, cada noche) y guarde copias en otro equipo. La restauración, paso a paso, está en `deploy/restaurar.md`. Pruebe una restauración al menos una vez antes de publicar.

## 6. Actualizaciones de seguridad

Drupal y sus módulos publican avisos de seguridad. Cada mes, o cuando haya un aviso crítico:

1. Haga un respaldo.
2. En una copia de pruebas (o en su computador), actualice y pruebe:

   ```bash
   cd drupal
   composer update "drupal/core-*" --with-all-dependencies
   composer audit
   ```

3. Suba a `composer.json` y `composer.lock` el cambio, despliegue con `bash deploy/desplegar.sh` y compruebe el sitio (sección 7).

`composer audit` debe salir sin avisos antes de publicar y después de cada actualización.

## 7. Verificación después de desplegar

1. Abra el sitio por HTTPS y compruebe que `http://` redirige a `https://`.
2. Páginas que deben responder bien: la portada (`/`), `/siaai`, `/programas`, la página de Ingeniería Electrónica, `/documentos`, `/institucional`, `/noticias`, `/nuestro-equipo` y `/participa`. Las direcciones `/inicio` y `/institucional/procesos`, `/institucional/dependencias`, `/institucional/directivos`, `/institucional/normatividad` y `/institucional/cifras` deben redirigir (301) a `/siaai` y a la sección correspondiente de `/institucional`.
3. Como administrador, abra **Informes › Estado del sitio** (`/admin/reports/status`): no debe haber errores. Revise también **Informes › Mensajes recientes**.
4. Entre con una cuenta de Acreditación por `/acceso-acreditacion` y compruebe que aparece la barra de edición (una barra clara sobre el encabezado, solo con permiso de editar) y que no se ven opciones de administración.
   Pruebe también `/participa` sin sesión: el formulario se envía y el envío aparece en `/admin/structure/webform/manage/participa/results/submissions` (para un administrador o un gestor de participación). El formulario no envía correos ni guarda la dirección IP, y admite 5 envíos por hora y por visitante.
5. Corra `vendor/bin/drush config:status`. Debe diferir `core.extension` (es esperado: `desplegar.sh` desinstala `unillanos_guia`, que la configuración versionada lista como activo). Si aparece también `unillanos_autoeval.micrositio` y la única diferencia es la línea `langcode: es`, tampoco es un problema. Cualquier otra diferencia sí lo es.
6. Compruebe que `settings.php` es de solo lectura y que `sites/default/files` no ejecuta PHP.

## 8. Lista de pendientes antes de publicar

La lista completa y marcable está en `deploy/CHECKLIST.md`. Los puntos principales son estos:

- Servidor, dominio y HTTPS definidos y con certificado válido.
- Correo (SMTP) pendiente de definir: sin él no salen correos de recuperación de clave ni avisos.
- Módulos de desarrollo fuera de producción: `bash deploy/desplegar.sh` desinstala `unillanos_guia` (la guía de estilos) en cada despliegue.
- Retirar el starterkit (plantillas sobrantes dentro del tema `unillanos`); el detalle está en `docs/pendientes-acreditacion.md`. Olivero ya está desinstalado y fuera de la configuración versionada.
- Cuentas reales creadas y cuentas de prueba eliminadas.
- Las extensiones `zip` y `txt` ya no se admiten en el tipo de medio «Documento» (viene en la configuración versionada). Los archivos subidos antes con esas extensiones no cambian: revíselos a mano si los hay.
- Control de acceso de los documentos no publicados (hoy `public://` es público).
- Logos oficiales de Vigilada MinEducación y CO Colombia (hoy son copias del sitio de Acreditación), redes oficiales por confirmar y barra GOV.CO según la guía de MinTIC.
- Correo de PQRS del pie (hoy sin `.co`, como en el HTML original).
- Subir las imágenes reales de las galerías (campo «Galería» de cada programa y del proceso institucional; las de prueba solo existen en el computador de desarrollo) y revisar que `sites/default/files/galeria/` esté en el servidor.
- Participa: decidir captcha o honeypot, el texto de la política de datos y quién gestiona las recomendaciones (sección 32 de `docs/pendientes-acreditacion.md`).
- Reemplazar las imágenes de prueba del carrusel del portal por las reales: en desarrollo, `ddev drush migrate:rollback diapositivas` y `ddev drush migrate:rollback archivos_diapositivas` (solo esas dos); en producción, despublicar las de prueba desde el sitio y subir las reales.
- Indexación por buscadores: decidir si el sitio se publica ya o con `noindex` hasta tener todo.
- `composer audit` sin avisos.

## 9. Problemas comunes

| Síntoma | Qué mirar |
|---|---|
| Página en blanco o error 500 | El registro del servidor web y de PHP; luego **Informes › Mensajes recientes**. Con errores ocultos, la causa suele estar en los registros del servidor. |
| «The provided host name is not valid for this server» | `trusted_host_patterns` no incluye su dominio. |
| El sitio se ve sin estilos | Limpie caché (`vendor/bin/drush cr`) y compruebe que el servidor sirve `sites/default/files`. |
| No se pueden subir archivos | Permisos de `sites/default/files` (sección 3.6). |
| Pide actualizar la base de datos | Corra `bash deploy/desplegar.sh` o `vendor/bin/drush updb -y`. |
| `composer install` falla por memoria | Súbale el `memory_limit` de PHP en la consola o use `COMPOSER_MEMORY_LIMIT=-1`. |

Para todo lo demás, escriba a quien mantiene el código (manual técnico).
