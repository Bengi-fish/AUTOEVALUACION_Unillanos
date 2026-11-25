#!/usr/bin/env python3
"""Extrae del sitio público de Acreditación las semillas de la Fase 9.

Genera, en datos/semillas/:
  - noticias.csv y imagenes/noticias/ (las noticias del slider),
  - paginas.csv (SAC, Esquema organizacional, Boletines y Capacitaciones),
  - equipo.csv (Nuestro equipo).

Solo lee páginas públicas y no inventa datos: lo que el sitio no publica (por
ejemplo la fecha de una noticia) queda vacío. El HTML se limpia a las etiquetas
que admite el formato «basic_html» de Drupal; los enlaces relativos pasan a
absolutos hacia el sitio de origen y los iframe de PDF pasan a enlaces.

Uso: python3 scripts/extraer-sitio-acreditacion.py
"""
import base64
import csv
import html
import re
import sys
import urllib.parse
import urllib.request
from html.parser import HTMLParser
from pathlib import Path

ORIGEN = 'https://acreditacion.unillanos.edu.co'
RAIZ = Path(__file__).resolve().parent.parent
SEMILLAS = RAIZ / 'datos' / 'semillas'
PERMITIDAS = {'p', 'br', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'strong', 'em', 'a', 'blockquote'}
CAMBIOS = {'h1': 'h2', 'b': 'strong', 'i': 'em'}


def leer(ruta: str, binario: bool = False):
    url = ruta if ruta.startswith('http') else ORIGEN + urllib.parse.quote(ruta, safe='/:?=&%#')
    with urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'}), timeout=60) as r:
        datos = r.read()
    return datos if binario else datos.decode('utf-8', 'ignore')


def absoluta(url: str) -> str:
    return urllib.parse.urljoin(ORIGEN + '/', url.strip())


class Limpiador(HTMLParser):
    """Deja solo las etiquetas permitidas y los href absolutos."""

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.salida = []
        self.omitir = 0
        self.abiertas = []

    def handle_starttag(self, tag, attrs):
        tag = CAMBIOS.get(tag, tag)
        a = dict(attrs)
        if tag in ('script', 'style'):
            self.omitir += 1
            return
        if tag == 'iframe' and a.get('src'):
            url = absoluta(a['src'])
            self.salida.append(f'<p><a href="{html.escape(url)}">Ver el documento (PDF)</a></p>')
            return
        if tag not in PERMITIDAS:
            return
        if tag == 'a':
            href = (a.get('href') or '').strip()
            if not re.match(r'^(https?://|/)', href):
                self.abiertas.append(None)
                return
            self.abiertas.append('a')
            self.salida.append(f'<a href="{html.escape(absoluta(href))}">')
            return
        self.abiertas.append(tag)
        self.salida.append('<br>' if tag == 'br' else f'<{tag}>')
        if tag == 'br':
            self.abiertas.pop()

    def handle_endtag(self, tag):
        tag = CAMBIOS.get(tag, tag)
        if tag in ('script', 'style'):
            self.omitir = max(0, self.omitir - 1)
            return
        if tag in PERMITIDAS and tag != 'br' and tag in self.abiertas:
            while self.abiertas:
                ultima = self.abiertas.pop()
                if ultima:
                    self.salida.append(f'</{ultima}>')
                if ultima == tag:
                    break

    def handle_data(self, data):
        if not self.omitir:
            self.salida.append(html.escape(data, quote=False))


def limpiar(fragmento: str) -> str:
    p = Limpiador()
    p.feed(fragmento)
    texto = ''.join(p.salida)
    texto = re.sub(r'<(p|h[2-6]|li|strong|em)>(\s|&nbsp;|\xa0)*</\1>', '', texto)
    texto = re.sub(r'\n{2,}', '\n', texto)
    return texto.strip()


def cuerpo(pagina: str) -> str:
    """Cuerpo del artículo de Joomla."""
    i = pagina.find('com-content-article__body')
    if i < 0:
        return ''
    i = pagina.find('>', i) + 1
    fin = pagina.find('</main>', i)
    segmento = pagina[i:fin]
    # El artículo termina antes del pie de página; se corta en el cierre del bloque.
    segmento = re.split(r'\n\s*</div>\s*\n\s*\n', segmento, maxsplit=1)[0]
    return segmento


def titulo(pagina: str) -> str:
    m = re.search(r'<div class="page-header">\s*<h1>(.*?)</h1>', pagina, re.S)
    return re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', '', m.group(1)))).strip() if m else ''


def escribir(nombre: str, campos: list, filas: list):
    with open(SEMILLAS / nombre, 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=campos)
        w.writeheader()
        w.writerows(filas)
    print(f'{nombre}: {len(filas)} filas')


def noticias():
    home = leer('/')
    filas = []
    destino = SEMILLAS / 'imagenes' / 'noticias'
    destino.mkdir(parents=True, exist_ok=True)
    vistos = set()
    for ruta, img in re.findall(r'<a\s+href="(/index\.php/noticias/noticias-slider/[^"]+)"[^>]*>\s*<img class="dj-image" src="([^"#]+)', home):
        if ruta in vistos:
            continue
        vistos.add(ruta)
        pagina = leer(ruta)
        alias = ruta.rsplit('/', 1)[1]
        id_ = alias[:60].rstrip('-')
        nombre_img = Path(urllib.parse.unquote(img)).name
        (destino / nombre_img).write_bytes(leer(img, binario=True))
        filas.append({
            'id': id_,
            'titulo': titulo(pagina),
            'cuerpo': limpiar(cuerpo(pagina)),
            'imagen': f'datos/semillas/imagenes/noticias/{nombre_img}',
            'alt': titulo(pagina),
            'fecha': '',
            'fuente': absoluta(ruta),
        })
    escribir('noticias.csv', ['id', 'titulo', 'cuerpo', 'imagen', 'alt', 'fecha', 'fuente'], filas)


def boletines() -> str:
    partes = []
    for cat in ('8-boletines-2021', '7-boletines-2020', '6-boletines-2019', '5-boletines-2017', '4-boletines-2016', '3-boletines-2015'):
        pagina = leer(f'/index.php/noticias/boletines/{cat}')
        anio = cat.rsplit('-', 1)[1]
        # Cada boletín: un enlace «?download=» con su nombre y, después, el
        # enlace «Preview» al archivo (/phocadownload/...).
        nombres = [html.unescape(n).strip() for n in re.findall(r'\?download=[^"]+"[^>]*>\s*([^<]*?)\s*</a>', pagina)
                   if n.strip() and n.strip().lower() != 'download']
        rutas = re.findall(r'href="(/phocadownload/Boletines/[^"]+)"', pagina)
        items = list(zip(nombres, rutas)) if len(nombres) == len(rutas) else \
            [(urllib.parse.unquote(Path(r).stem), r) for r in rutas]
        vistos, lista = set(), []
        for nombre, ruta in items:
            if ruta in vistos:
                continue
            vistos.add(ruta)
            lista.append(f'<li><a href="{html.escape(absoluta(urllib.parse.quote(ruta, safe="/")))}">{html.escape(nombre.strip())}</a></li>')
        partes.append(f'<h2>Boletines {anio}</h2><ul>{"".join(lista)}</ul>')
    return ''.join(partes)


def paginas():
    filas = []
    for id_, ruta, alias in (
        ('sac', '/index.php/informacion/sac', '/informacion/sac'),
        ('esquema-organizacional', '/index.php/informacion/esquema-organizacional', '/informacion/esquema-organizacional'),
        ('capacitaciones', '/index.php/noticias/capacitaciones/historico', '/noticias/capacitaciones'),
    ):
        pagina = leer(ruta)
        # La página original se titula solo «Histórico»; fuera de su menú no se entiende.
        nombre = 'Capacitaciones (histórico)' if id_ == 'capacitaciones' else titulo(pagina)
        filas.append({'id': id_, 'titulo': nombre, 'alias': alias, 'cuerpo': limpiar(cuerpo(pagina)), 'fuente': absoluta(ruta)})
    filas.append({'id': 'boletines', 'titulo': 'Boletines', 'alias': '/noticias/boletines', 'cuerpo': boletines(), 'fuente': absoluta('/index.php/noticias/boletines')})
    escribir('paginas.csv', ['id', 'titulo', 'alias', 'cuerpo', 'fuente'], filas)


def equipo():
    pagina = leer('/index.php/nuestro-equipo')
    b = pagina[pagina.find('com-content-article__body'):]
    grupos = [('comite', 'Comité Autoevaluación y Acreditación Institucional'),
              ('equipo', 'Equipo de trabajo Secretaría Técnica de Acreditación')]
    cortes = [b.find('sstc-1-1'), b.find('sstc-1-2')]
    tramos = [b[cortes[0]:cortes[1]] if cortes[1] > 0 else b[cortes[0]:], b[cortes[1]:] if cortes[1] > 0 else '']
    filas, orden = [], 0
    for (clave, nombre_grupo), tramo in zip(grupos, tramos):
        for bloque in re.split(r'<h3>', tramo)[1:]:
            cargo = re.sub(r'<[^>]+>|&nbsp;|\xa0', '', bloque.split('</h3>')[0]).strip()
            nombre = re.search(r'Nombre:</strong>\s*([^<]+)', bloque)
            tel = re.search(r'Tel[ée]fono:</strong>\s*([^<]+)', bloque)
            texto = re.search(r'<joomla-hidden-mail[^>]*text="([^"]+)"', bloque)
            if not nombre:
                continue
            orden += 1
            filas.append({
                'id': f'{clave}-{orden}',
                'nombre': html.unescape(nombre.group(1)).strip(),
                'cargo': html.unescape(cargo),
                'grupo': nombre_grupo,
                'telefono': html.unescape(tel.group(1)).strip() if tel else '',
                'correo': base64.b64decode(texto.group(1)).decode() if texto else '',
                'orden': orden,
            })
    escribir('equipo.csv', ['id', 'nombre', 'cargo', 'grupo', 'telefono', 'correo', 'orden'], filas)


if __name__ == '__main__':
    for paso in (noticias, paginas, equipo):
        try:
            paso()
        except Exception as e:  # noqa: BLE001
            print(f'Falló {paso.__name__}: {e}', file=sys.stderr)
            raise
