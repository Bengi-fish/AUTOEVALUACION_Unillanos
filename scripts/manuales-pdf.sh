#!/usr/bin/env bash
# Genera los PDF de los manuales (docs/manuales/*.md).
#
# Uso (desde cualquier carpeta): bash scripts/manuales-pdf.sh [carpeta_de_salida]
#   La carpeta por defecto es ~/manuales-pdf (fuera del repositorio: los PDF no
#   se versionan).
#
# Convierte cada manual a HTML con una hoja de estilos de impresión (Public
# Sans, márgenes de 2 cm, imágenes dentro del ancho, numeración de páginas) y lo
# imprime a PDF con Edge o Chromium sin interfaz. Necesita python3 y uno de:
# microsoft-edge, chromium, google-chrome o, en WSL, el Edge de Windows.
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SALIDA="${1:-$HOME/manuales-pdf}"
FUENTE="$RAIZ/drupal/web/themes/custom/unillanos/fonts/public-sans-latin-wght-normal.woff2"
mkdir -p "$SALIDA"

# Busca un navegador.
NAVEGADOR=""
for candidato in microsoft-edge chromium chromium-browser google-chrome; do
  if command -v "$candidato" >/dev/null 2>&1; then NAVEGADOR="$(command -v "$candidato")"; break; fi
done
if [ -z "$NAVEGADOR" ]; then
  for ruta in "/mnt/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" "/mnt/c/Program Files/Microsoft/Edge/Application/msedge.exe"; do
    if [ -x "$ruta" ]; then NAVEGADOR="$ruta"; break; fi
  done
fi
if [ -z "$NAVEGADOR" ]; then
  echo "ERROR: no se encontró Edge ni Chromium. Instale uno y repita." >&2
  exit 1
fi

# En WSL con el Edge de Windows, las rutas deben ser de Windows.
ruta_para_navegador() {
  if [[ "$NAVEGADOR" == *.exe ]]; then wslpath -w "$1"; else echo "$1"; fi
}
url_para_navegador() {
  if [[ "$NAVEGADOR" == *.exe ]]; then echo "file:///$(wslpath -m "$1")"; else echo "file://$1"; fi
}

TMP="$(mktemp -d "${TMPDIR:-/tmp}/manuales.XXXXXX")"
trap 'rm -rf "$TMP"' EXIT
# Edge de Windows no lee /tmp de WSL: el HTML temporal va junto a la salida.
if [[ "$NAVEGADOR" == *.exe ]]; then TMP="$(mktemp -d "$SALIDA/.tmp.XXXXXX")"; trap 'rm -rf "$TMP"' EXIT; fi

for md in "$RAIZ"/docs/manuales/*.md; do
  nombre="$(basename "$md" .md)"
  html="$TMP/$nombre.html"
  pdf="$SALIDA/$nombre.pdf"
  python3 -I "$RAIZ/scripts/md2html.py" "$md" "$html" "$FUENTE"
  "$NAVEGADOR" --headless=new --disable-gpu --user-data-dir="$(ruta_para_navegador "$TMP/perfil-$nombre")" \
    --print-to-pdf="$(ruta_para_navegador "$pdf")" "$(url_para_navegador "$html")" >/dev/null 2>&1 || true
  if [ ! -s "$pdf" ]; then
    echo "ERROR: no se generó $pdf" >&2
    exit 1
  fi
  echo "$pdf ($(du -k "$pdf" | cut -f1) KB)"
done
