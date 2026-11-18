#!/usr/bin/env bash
# Despliegue del sitio. Idempotente: se puede repetir. Se detiene ante el primer
# error y, si falla a medias, deja el sitio en mantenimiento (hay que revisar y
# volver a correrlo).
#
# Uso, desde cualquier carpeta:  bash deploy/desplegar.sh
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ/drupal"

echo "==> Dependencias (sin herramientas de desarrollo)"
composer install --no-dev --optimize-autoloader --no-interaction

DRUSH="vendor/bin/drush"

echo "==> Modo mantenimiento: activado"
"$DRUSH" state:set system.maintenance_mode 1 --input-format=integer

echo "==> Actualizaciones de base, configuración y caché"
"$DRUSH" deploy -y

# La guía de estilos es solo de desarrollo, pero la configuración versionada la
# lista como activa y «drush deploy» la vuelve a activar. Aquí se desinstala
# (el sitio sigue en mantenimiento). Por eso «config:status» muestra una única
# diferencia esperada: core.extension.
if "$DRUSH" pm:list --status=enabled --field=name | grep -qx unillanos_guia; then
  echo "==> Desinstalando unillanos_guia (solo desarrollo)"
  "$DRUSH" pm:uninstall unillanos_guia -y
fi

echo "==> Modo mantenimiento: desactivado"
"$DRUSH" state:set system.maintenance_mode 0 --input-format=integer
"$DRUSH" cache:rebuild

echo "==> Estado de la configuración (solo debe diferir core.extension)"
"$DRUSH" config:status

echo "Despliegue terminado."
