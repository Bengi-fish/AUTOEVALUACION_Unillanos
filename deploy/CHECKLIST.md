# Lista de verificación antes de publicar

Marque cada punto. Los detalles están en `docs/manuales/GUIA-DESPLIEGUE.md`.

## Servidor
- [ ] Dominio definido y HTTPS con certificado válido; `http://` redirige a `https://`.
- [ ] Raíz web en `drupal/web`; `sites/default/files` no ejecuta PHP.
- [ ] `settings.php` copiado de `deploy/settings.production.php.example`, de solo lectura (`chmod 444`).
- [ ] Variables de entorno definidas (`deploy/env.example`); ningún secreto en el repositorio.
- [ ] Archivos privados fuera de la raíz web.
- [ ] Cron programado (`deploy/cron.example`).
- [ ] Respaldos programados y una restauración probada (`deploy/restaurar.md`).

## Sitio
- [ ] `bash deploy/desplegar.sh` termina sin errores.
- [ ] Responden bien: portada (`/`), `/siaai`, `/programas`, Ingeniería Electrónica, `/documentos`, `/institucional`, `/noticias`, `/nuestro-equipo` y `/participa`.
- [ ] Redirigen (301): `/inicio` a `/siaai` y `/institucional/{procesos,dependencias,directivos,normatividad,cifras}` a la sección de `/institucional`.
- [ ] `/participa` envía un formulario de prueba y el envío aparece en `/admin/structure/webform/manage/participa/results/submissions`; después se borra el envío de prueba.
- [ ] Si la base no trae noticias, páginas informativas ni equipo: `drush migrate:import archivos_noticias,noticias,paginas_informativas,equipo` y alias con `drush pathauto:aliases-generate create canonical_entities:node`.
- [ ] `sites/default/files/galeria/` presente (viene en `archivos-sitio.tar.gz`) y los carruseles de programas e Institucional se ven.
- [ ] Olivero desinstalado y sin bloques (lo hace `drush deploy`); la franja superior ya no existe (`franja_superior` y `url_govco` fuera de `unillanos_autoeval.micrositio`).
- [ ] **Informes › Estado del sitio** sin errores; `drush config:status` solo con `core.extension` distinto (y, a lo sumo, `langcode` en `unillanos_autoeval.micrositio`).
- [ ] `composer audit` sin avisos.
- [ ] Módulo `unillanos_guia` desinstalado (lo hace `desplegar.sh`); `/guia-estilos` responde 404.
- [ ] Persona administradora creada; cuentas de prueba eliminadas; cuentas de Acreditación creadas (y, si hacen falta, con los roles Editor de documentos y Gestor de participación).
- [ ] Una cuenta de Acreditación entra por `/acceso-acreditacion` y no ve opciones de administración.

## Contenido y decisiones pendientes
- [ ] Imágenes de prueba del carrusel del portal reemplazadas por las reales.
- [ ] Imágenes reales en la galería de cada programa y del proceso institucional (las de prueba solo existen en el equipo de desarrollo).
- [ ] Participa: decidido captcha o honeypot, texto de la política de datos y quién gestiona las recomendaciones.
- [ ] Logos oficiales (Vigilada MinEducación, CO Colombia) y redes sociales confirmados.
- [ ] Correo de PQRS del pie confirmado.
- [ ] Correo (SMTP) definido.
- [ ] Extensiones `zip` y `txt` fuera del tipo de medio «Documento» (ya viene en la configuración versionada; comprobar tras `drush deploy` y revisar a mano los archivos antiguos con esas extensiones; `drush deploy` no los toca).
- [ ] Control de acceso de los documentos no publicados decidido.
- [ ] Indexación por buscadores: publicar ya o con `noindex`.
