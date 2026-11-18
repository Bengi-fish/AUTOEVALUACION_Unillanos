#!/usr/bin/env python3
"""Convierte un manual en Markdown a una página HTML lista para imprimir.

Solo usa la biblioteca estándar. Admite: títulos (# a ####), párrafos, listas
con viñetas y numeradas (un nivel de anidación), tablas, bloques de código (los
de «mermaid» se muestran como código), citas, reglas, negrita, cursiva, código
en línea, enlaces e imágenes. Las imágenes se incrustan como datos (base64),
así que el HTML es un solo archivo.

Uso: md2html.py <manual.md> <salida.html> <fuente_public_sans.woff2>
"""
import base64
import html
import mimetypes
import re
import sys
from pathlib import Path

CSS = """
@font-face { font-family: 'Public Sans'; font-weight: 100 900; src: url('%(fuente)s') format('woff2'); }
@page { size: A4; margin: 2cm; }
:root { color-scheme: light; }
body { font-family: 'Public Sans', Arial, sans-serif; font-size: 10.5pt; line-height: 1.5; color: #1d1d1b; margin: 0; }
h1 { font-size: 22pt; line-height: 1.2; margin: 0 0 .6em; color: #0a2226; border-bottom: 3px solid #e30613; padding-bottom: .2em; }
h2 { font-size: 15pt; margin: 1.6em 0 .5em; color: #174349; break-after: avoid; }
h3 { font-size: 12.5pt; margin: 1.3em 0 .4em; color: #1d1d1b; break-after: avoid; }
h4 { font-size: 11pt; margin: 1.1em 0 .3em; break-after: avoid; }
p, li { orphans: 3; widows: 3; }
p { margin: .5em 0; }
ul, ol { margin: .4em 0 .6em 1.3em; padding: 0; }
li { margin: .2em 0; }
code { font-family: Consolas, 'DejaVu Sans Mono', monospace; font-size: 9.2pt; background: #f1f0ec; padding: 0 .25em; border-radius: 3px; overflow-wrap: anywhere; }
pre { background: #f1f0ec; border: 1px solid #d3d0c7; border-radius: 6px; padding: .6em .8em; overflow-wrap: anywhere; white-space: pre-wrap; break-inside: avoid; }
pre code { background: none; padding: 0; }
table { border-collapse: collapse; width: 100%%; margin: .7em 0; font-size: 9.5pt; break-inside: auto; }
th, td { border: 1px solid #d3d0c7; padding: .35em .5em; vertical-align: top; text-align: left; overflow-wrap: anywhere; }
th { background: #174349; color: #fff; }
tr { break-inside: avoid; }
img { box-sizing: border-box; max-width: 100%%; height: auto; display: block; margin: .6em auto; border: 1px solid #d3d0c7; break-inside: avoid; }
blockquote { margin: .6em 0; padding: .1em .9em; border-left: 4px solid #e30613; background: #f1f0ec; }
hr { border: 0; border-top: 1px solid #d3d0c7; margin: 1.2em 0; }
a { color: #a8040e; overflow-wrap: anywhere; }
"""


def inline(texto):
    """Pasa a HTML el texto en línea (código, imágenes, enlaces, énfasis)."""
    guardados = []

    def guardar(m):
        guardados.append('<code>' + html.escape(m.group(1)) + '</code>')
        return '\x00%d\x00' % (len(guardados) - 1)

    texto = re.sub(r'`([^`]+)`', guardar, texto)
    texto = html.escape(texto, quote=False)
    texto = re.sub(r'!\[([^\]]*)\]\(([^)]+)\)', lambda m: '<img alt="%s" src="%s">' % (html.escape(m.group(1), quote=True), m.group(2)), texto)
    texto = re.sub(r'\[([^\]]+)\]\(([^)]+)\)', lambda m: '<a href="%s">%s</a>' % (m.group(2), m.group(1)), texto)
    texto = re.sub(r'\*\*([^*]+)\*\*', r'<strong>\1</strong>', texto)
    texto = re.sub(r'(?<![*\w])\*([^*\n]+)\*(?![*\w])', r'<em>\1</em>', texto)
    return re.sub(r'\x00(\d+)\x00', lambda m: guardados[int(m.group(1))], texto)


def convertir(lineas):
    salida = []
    i = 0
    n = len(lineas)
    while i < n:
        linea = lineas[i]
        if linea.strip() == '':
            i += 1
            continue
        m = re.match(r'^```(\w*)\s*$', linea)
        if m:
            cuerpo = []
            i += 1
            while i < n and not lineas[i].startswith('```'):
                cuerpo.append(lineas[i])
                i += 1
            i += 1
            etiqueta = m.group(1)
            nota = '<p><em>Diagrama en código Mermaid (se dibuja en GitHub y en los editores que lo admiten).</em></p>' if etiqueta == 'mermaid' else ''
            salida.append(nota + '<pre><code>' + html.escape('\n'.join(cuerpo)) + '</code></pre>')
            continue
        m = re.match(r'^(#{1,4})\s+(.*)$', linea)
        if m:
            nivel = len(m.group(1))
            salida.append('<h%d>%s</h%d>' % (nivel, inline(m.group(2)), nivel))
            i += 1
            continue
        if re.match(r'^-{3,}\s*$', linea):
            salida.append('<hr>')
            i += 1
            continue
        if linea.startswith('|') and i + 1 < n and re.match(r'^\|[\s:|-]+\|\s*$', lineas[i + 1]):
            celdas = lambda l: [c.strip() for c in l.strip().strip('|').split('|')]
            cab = celdas(linea)
            i += 2
            filas = []
            while i < n and lineas[i].startswith('|'):
                filas.append(celdas(lineas[i]))
                i += 1
            t = '<table><thead><tr>' + ''.join('<th>%s</th>' % inline(c) for c in cab) + '</tr></thead><tbody>'
            for f in filas:
                t += '<tr>' + ''.join('<td>%s</td>' % inline(c) for c in f) + '</tr>'
            salida.append(t + '</tbody></table>')
            continue
        if linea.startswith('>'):
            cita = []
            while i < n and lineas[i].startswith('>'):
                cita.append(lineas[i].lstrip('> ').rstrip())
                i += 1
            salida.append('<blockquote><p>' + inline(' '.join(cita)) + '</p></blockquote>')
            continue
        m = re.match(r'^(\s*)([-*]|\d+\.)\s+(.*)$', linea)
        if m:
            sangria0 = len(m.group(1))
            ordenada = m.group(2)[0].isdigit()
            etiqueta = 'ol' if ordenada else 'ul'
            items = []
            while i < n:
                mm = re.match(r'^(\s*)([-*]|\d+\.)\s+(.*)$', lineas[i])
                if mm and len(mm.group(1)) == sangria0:
                    items.append([mm.group(3), []])
                    i += 1
                elif mm and len(mm.group(1)) > sangria0 and items:
                    sub = []
                    while i < n:
                        s2 = re.match(r'^(\s*)([-*]|\d+\.)\s+(.*)$', lineas[i])
                        if s2 and len(s2.group(1)) > sangria0:
                            sub.append(s2.group(3))
                            i += 1
                        else:
                            break
                    items[-1][1] = sub
                elif lineas[i].startswith('  ') and lineas[i].strip() and items and not mm:
                    items[-1][0] += ' ' + lineas[i].strip()
                    i += 1
                elif lineas[i].strip() == '' and i + 1 < n and re.match(r'^\s+(```|\S)', lineas[i + 1]) and items and lineas[i + 1].startswith('   '):
                    # Bloque continuado de un ítem (por ejemplo, código con sangría).
                    i += 1
                    cont = []
                    while i < n and (lineas[i].startswith('   ') or lineas[i].strip() == ''):
                        cont.append(lineas[i][3:] if lineas[i].startswith('   ') else '')
                        i += 1
                    items[-1][0] += '\x01' + '\n'.join(cont)
                else:
                    break
            texto = '<%s>' % etiqueta
            for principal, hijos in items:
                if '\x01' in principal:
                    cabeza, resto = principal.split('\x01', 1)
                    extra = ''.join(convertir(resto.split('\n')))
                    li = inline(cabeza) + extra
                else:
                    li = inline(principal)
                if hijos:
                    li += '<ul>' + ''.join('<li>%s</li>' % inline(h) for h in hijos) + '</ul>'
                texto += '<li>%s</li>' % li
            salida.append(texto + '</%s>' % etiqueta)
            continue
        parrafo = [linea.strip()]
        i += 1
        while i < n and lineas[i].strip() and not re.match(r'^(#{1,4}\s|```|\||>|\s*([-*]|\d+\.)\s)', lineas[i]):
            parrafo.append(lineas[i].strip())
            i += 1
        salida.append('<p>' + inline(' '.join(parrafo)) + '</p>')
    return salida


def incrustar_imagenes(cuerpo, base):
    def reemplazo(m):
        ruta = (base / m.group(1)).resolve()
        if not ruta.is_file():
            raise SystemExit('Falta la imagen: %s' % ruta)
        tipo = mimetypes.guess_type(str(ruta))[0] or 'image/png'
        return 'src="data:%s;base64,%s"' % (tipo, base64.b64encode(ruta.read_bytes()).decode())
    return re.sub(r'src="(?!data:|https?:)([^"]+)"', reemplazo, cuerpo)


def main():
    entrada, salida, fuente = Path(sys.argv[1]), Path(sys.argv[2]), Path(sys.argv[3])
    lineas = entrada.read_text(encoding='utf-8').split('\n')
    cuerpo = '\n'.join(convertir(lineas))
    cuerpo = incrustar_imagenes(cuerpo, entrada.parent)
    titulo = next((l[2:].strip() for l in lineas if l.startswith('# ')), entrada.stem)
    estilos = CSS % {'fuente': fuente.resolve().as_uri()}
    salida.write_text('<!doctype html><html lang="es"><head><meta charset="utf-8"><title>%s</title><style>%s</style></head><body>%s</body></html>' % (html.escape(titulo), estilos, cuerpo), encoding='utf-8')


if __name__ == '__main__':
    main()
