#!/usr/bin/env bash
# Respaldo de la base de datos y de los archivos públicos, con fecha, en una
# carpeta FUERA del repositorio (variable AUTOEVAL_RESPALDOS).
#
# Uso:  bash deploy/respaldar.sh
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DESTINO="${AUTOEVAL_RESPALDOS:?Defina AUTOEVAL_RESPALDOS (carpeta fuera del repositorio)}"
umask 077
mkdir -p "$DESTINO"
# Se compara con rutas reales (resuelve «..», «.» y enlaces simbólicos).
DESTINO="$(cd "$DESTINO" && pwd -P)"
RAIZ="$(cd "$RAIZ" && pwd -P)"
case "$DESTINO" in
  "$RAIZ"|"$RAIZ"/*) echo "AUTOEVAL_RESPALDOS no puede estar dentro del repositorio." >&2; exit 1 ;;
esac

SELLO="$(date +%Y%m%d-%H%M%S)"

echo "==> Base de datos"
(cd "$RAIZ/drupal" && vendor/bin/drush sql:dump --gzip --result-file="$DESTINO/base-$SELLO.sql")

echo "==> Archivos públicos"
tar -czf "$DESTINO/archivos-$SELLO.tar.gz" -C "$RAIZ/drupal/web/sites/default" \
  --exclude='files/styles' --exclude='files/css' --exclude='files/js' \
  --exclude='files/php' files

(cd "$DESTINO" && sha256sum "base-$SELLO.sql.gz" "archivos-$SELLO.tar.gz" > "SHA256SUMS-$SELLO")
echo "Respaldo listo en $DESTINO (sello $SELLO)."
