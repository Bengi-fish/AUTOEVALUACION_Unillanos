#!/usr/bin/env bash
# Instalación del sitio de Autoevaluación UNILLANOS en un equipo nuevo.
#
# Uso (desde la raíz del repositorio, dentro de Ubuntu/WSL):
#   bash scripts/instalar.sh
#
# Es seguro repetirlo: si el sitio ya está instalado NO lo reinstala ni borra
# nada; solo pone al día dependencias, configuración y datos.
set -euo pipefail

# Ir a la raíz del repositorio, sin importar desde dónde se llame.
cd "$(dirname "${BASH_SOURCE[0]}")/.."

# Carpeta opcional con archivos extra del sitio (fotos, documentos). Va en la
# raíz del repositorio, no se versiona y puede no existir.
CARPETA_ARCHIVOS="archivos-sitio"
DESTINO_ARCHIVOS="drupal/web/sites/default/files"

INICIO=$(date +%s)

# Imprime un título y, al terminar el paso anterior, cuánto tardó.
paso() {
  echo
  echo "==> $1"
}

# Ejecuta un paso midiendo su duración.
medir() {
  local t0
  t0=$(date +%s)
  "$@"
  echo "    (listo en $(( $(date +%s) - t0 )) s)"
}

# --- 0. Comprobaciones previas ---------------------------------------------
if ! command -v ddev >/dev/null 2>&1; then
  echo "ERROR: DDEV no está instalado. Sigue docs/GUIA-OTRO-PC.md, sección 1." >&2
  exit 1
fi
if [ ! -f .ddev/config.yaml ] || [ ! -d drupal ]; then
  echo "ERROR: ejecuta este script dentro del repositorio clonado." >&2
  exit 1
fi
case "$(pwd)" in
  /mnt/*)
    echo "ERROR: el repositorio está en /mnt/... (disco de Windows). Clónalo en" >&2
    echo "       tu carpeta de Ubuntu (por ejemplo ~/proyectos) para que Docker" >&2
    echo "       no vaya lento ni falle por permisos." >&2
    exit 1
    ;;
esac

# --- 1. Contenedores --------------------------------------------------------
paso "1/7 Arrancando DDEV (la primera vez descarga imágenes: puede tardar)"
medir ddev start

# --- 2. Dependencias de PHP -------------------------------------------------
paso "2/7 Instalando dependencias con Composer"
medir ddev composer install --no-interaction

# --- 3. Instalación del sitio desde la configuración del repositorio --------
paso "3/7 Instalando el sitio desde la configuración existente"
if ddev drush status --field=bootstrap 2>/dev/null | grep -q "Successful"; then
  echo "    El sitio ya está instalado: no se reinstala ni se borra nada."
else
  # La clave del usuario 1 se genera al azar y no se muestra: cada persona
  # crea su propio administrador (docs/GUIA-OTRO-PC.md, sección 6).
  CLAVE_INICIAL=$(openssl rand -base64 24)
  medir ddev drush site:install --existing-config --locale=es -y \
    --site-name="Autoevaluación UNILLANOS" \
    --account-name=admin --account-pass="$CLAVE_INICIAL"
  unset CLAVE_INICIAL
fi

# --- 4. Configuración al día ------------------------------------------------
paso "4/7 Importando la configuración y aplicando actualizaciones"
medir ddev drush cim -y
medir ddev drush updb -y
ddev drush cr

# --- 5. Archivos (opcional) -------------------------------------------------
paso "5/7 Archivos del sitio"
if [ -d "$CARPETA_ARCHIVOS" ]; then
  mkdir -p "$DESTINO_ARCHIVOS"
  cp -a "$CARPETA_ARCHIVOS"/. "$DESTINO_ARCHIVOS"/
  echo "    Copiado el contenido de $CARPETA_ARCHIVOS/ a $DESTINO_ARCHIVOS/"
else
  echo "    No hay carpeta $CARPETA_ARCHIVOS/: se sigue sin ella (los documentos"
  echo "    del sitio salen de docs/fuentes y los copia la migración)."
fi

# --- 6. Datos: semillas y programas desde los CSV ---------------------------
paso "6/7 Cargando datos con unillanos_migrate (repetirlo no duplica nada)"
ddev drush cr
medir ddev drush migrate:import --group=unillanos
medir ddev drush pathauto:aliases-generate create canonical_entities:node
ddev drush cr

# --- 7. Chequeo final -------------------------------------------------------
paso "7/7 Chequeo final"
contar() {
  ddev drush sql:query "SELECT COUNT(*) FROM node_field_data WHERE type='$1' AND status=1" | tail -1
}
PROGRAMAS=$(contar programa)
PROCESOS=$(contar proceso)
echo "    Programas publicados: $PROGRAMAS (esperado: 40)"
echo "    Procesos publicados:  $PROCESOS (esperado: 1)"
if [ "$PROGRAMAS" != "40" ]; then
  echo "    AVISO: no son 40 programas; revisa: ddev drush migrate:status --group=unillanos"
fi

URL_BASE=$(ddev describe -j | python3 -c \
  'import json,sys; print(json.load(sys.stdin)["raw"]["primary_url"])')
echo
echo "Abre en el navegador:"
echo "  $URL_BASE/                      (portada)"
echo "  $URL_BASE/programas             (40 programas)"
echo "  $URL_BASE/documentos            (documentos)"
echo "  $URL_BASE/acceso-acreditacion   (entrada de Acreditación)"
echo
echo "Instalación terminada en $(( $(date +%s) - INICIO )) s."
echo "Siguiente paso: crea tu usuario administrador (docs/GUIA-OTRO-PC.md, sección 6)."
