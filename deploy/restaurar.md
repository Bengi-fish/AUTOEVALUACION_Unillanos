# Restaurar un respaldo

Los respaldos los crea `deploy/respaldar.sh`: `base-AAAAMMDD-HHMMSS.sql.gz`,
`archivos-AAAAMMDD-HHMMSS.tar.gz` y su `SHA256SUMS-…`. Pruebe este
procedimiento al menos una vez, en una copia, antes de publicar.

1. Compruebe las sumas:

   ```bash
   cd "$AUTOEVAL_RESPALDOS"
   sha256sum -c SHA256SUMS-<sello>
   ```

2. Ponga el sitio en mantenimiento:

   ```bash
   cd /var/www/autoeval/drupal
   vendor/bin/drush state:set system.maintenance_mode 1 --input-format=integer
   ```

3. Vacíe la base y cargue el respaldo (la base y su usuario ya existen):

   ```bash
   vendor/bin/drush sql:drop -y
   gunzip -c "$AUTOEVAL_RESPALDOS/base-<sello>.sql.gz" | vendor/bin/drush sql:cli
   ```

4. Restaure los archivos públicos:

   ```bash
   tar -xzf "$AUTOEVAL_RESPALDOS/archivos-<sello>.tar.gz" -C web/sites/default/
   chmod -R u=rwX,g=rX,o= web/sites/default/files
   ```

5. Actualice y limpie caché, y quite el mantenimiento:

   ```bash
   cd /var/www/autoeval
   bash deploy/desplegar.sh
   ```

6. Compruebe el sitio con la lista de `deploy/CHECKLIST.md`.
