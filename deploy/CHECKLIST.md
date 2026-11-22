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
- [ ] Responden bien: portada, `/programas`, Ingeniería Electrónica, `/documentos`, `/institucional` y sus cinco páginas.
- [ ] **Informes › Estado del sitio** sin errores; `drush config:status` solo con `core.extension` distinto (y, a lo sumo, `langcode` en `unillanos_autoeval.micrositio`).
- [ ] `composer audit` sin avisos.
- [ ] Módulo `unillanos_guia` desinstalado (lo hace `desplegar.sh`); `/guia-estilos` responde 404.
- [ ] Persona administradora creada; cuentas de prueba eliminadas; cuentas de Acreditación creadas.
- [ ] Una cuenta de Acreditación entra por `/acceso-acreditacion` y no ve opciones de administración.

## Contenido y decisiones pendientes
- [ ] Imágenes de prueba del carrusel reemplazadas por las reales.
- [ ] Logos oficiales (Vigilada MinEducación, CO Colombia) y redes sociales confirmados.
- [ ] Correo de PQRS del pie confirmado.
- [ ] Correo (SMTP) definido.
- [ ] Extensiones `zip` y `txt` fuera del tipo de medio «Documento» (ya viene en la configuración versionada; comprobar tras `drush deploy` y revisar a mano los archivos antiguos con esas extensiones).
- [ ] Control de acceso de los documentos no publicados decidido.
- [ ] Indexación por buscadores: publicar ya o con `noindex`.
