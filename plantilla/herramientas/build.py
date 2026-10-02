# -*- coding: utf-8 -*-
"""Genera las páginas estáticas del prototipo UNILLANOS a partir de piezas compartidas
(encabezado, menú, pie). Cada página queda como un HTML independiente."""
import json, os, sys, html
sys.path.insert(0, os.path.dirname(__file__))
import datos

OUT = "/mnt/user-data/outputs/unillanos-autoevaluacion"
ICONS = datos.ICONS

def ic(name, cls=""):
    return '<svg class="ua-icon%s" viewBox="0 0 24 24" aria-hidden="true">%s</svg>' % ((" " + cls) if cls else "", ICONS[name])

def esc(s):
    return html.escape(s, quote=True)

# ---------------------------------------------------------------- menú
NAV_INSTITUCIONAL = [
    ("Registro calificado institucional", "institucional-registro-calificado.html", "inst-registro"),
    ("Autoevaluación", "institucional-autoevaluacion.html", "inst-autoevaluacion"),
    ("Plan de mejoramiento", "institucional-autoevaluacion.html#plan-mejoramiento", "inst-plan"),
]
NAV_DOCUMENTOS = [
    ("Normatividad", "documentos.html#normatividad", "doc-normatividad"),
    ("Documentos base", "documentos.html#documentos-base", "doc-base"),
]

def nav(section, page):
    chev = ic("chevron-down", "ua-icon--sm ua-nav__chev")
    def link(label, href, key):
        cur = ' aria-current="page"' if key == page else ""
        return '<li><a href="%s"%s>%s</a></li>' % (href, cur, label)
    def top(label, key, extra=""):
        cur = ' aria-current="page"' if section == key else ""
        return '<li class="ua-nav__item"><a class="ua-nav__link" href="%s"%s>%s</a></li>' % (extra, cur, label)
    def mega(label, key, mid, body, cls):
        cur = " is-current" if section == key else ""
        return ('<li class="ua-nav__item ua-nav__item--mega ua-nav__item--compact">'
                '<button class="ua-nav__link%s" type="button" data-ua-subtoggle aria-expanded="false" aria-controls="%s">%s%s</button>'
                '<div class="ua-mega %s" id="%s">%s</div></li>') % (cur, mid, label, chev, cls, mid, body)

    inst = ('<div class="ua-mega__inner"><div class="ua-mega__col"><ul class="ua-mega__list">%s</ul></div></div>'
            % "".join(link(*x) for x in NAV_INSTITUCIONAL))
    prog_links = (
        '<li><a href="programas.html#registro">Registro calificado de programa</a></li>'
        '<li><p class="ua-mega__sublabel">Autoevaluación de programas</p><ul class="ua-mega__sublist">'
        '<li><a href="programas.html#nacional">Nacional</a></li><li><a href="programas.html#internacional">Internacional</a></li></ul></li>'
        '<li><a href="programas.html#plan">Planes de mejoramiento de programas</a></li>')
    prog = ('<div class="ua-mega__inner"><div class="ua-mega__col"><ul class="ua-mega__list">%s</ul></div>'
            '<div class="ua-mega__col"><p class="ua-mega__title">%s Buscar un programa</p>'
            '<form class="ua-search ua-search--full" data-ua-prog-search data-route="registro" role="search">%s<span class="ua-sr">Buscar un programa</span>'
            '<input type="search" list="menu-prog-lista" placeholder="Nombre del programa o facultad"><datalist id="menu-prog-lista"></datalist></form>'
            '<p class="ua-mega__hint">Ejemplo: Enfermería, Ingeniería de Sistemas, Medicina Veterinaria y Zootecnia.</p></div></div>'
            % (prog_links, ic("search", "ua-icon--sm"), ic("search")))
    docs = ('<div class="ua-mega__inner"><div class="ua-mega__col"><ul class="ua-mega__list">%s</ul></div></div>'
            % "".join(link(*x) for x in NAV_DOCUMENTOS))
    inicio_cur = ' aria-current="page"' if section == "inicio" else ""
    part_cur = ' aria-current="page"' if section == "participa" else ""
    return ('<nav class="ua-nav" id="ua-menu" aria-label="Menú principal"><div class="ua-container"><ul class="ua-nav__list">'
            '<li class="ua-nav__item"><a class="ua-nav__link" href="index.html"%s>Inicio</a></li>%s%s%s'
            '<li class="ua-nav__item"><a class="ua-nav__link" href="participa.html"%s>Participa</a></li>'
            '</ul></div></nav>') % (
        inicio_cur,
        mega("Institucional", "institucional", "menu-institucional", inst, "ua-mega--slim"),
        mega("Programas", "programas", "menu-programas", prog, "ua-mega--duo"),
        mega("Documentos", "documentos", "menu-documentos", docs, "ua-mega--slim"),
        part_cur)

def header(section, page):
    return ('<a class="ua-btn ua-btn--primario ua-skip" href="#contenido">Saltar al contenido</a>\n'
            '<header class="ua-header">\n'
            '  <div class="ua-container ua-header__bar">\n'
            '    <a class="ua-brand" href="index.html">\n'
            '      <span class="ua-logos"><img class="ua-logos__img ua-logos__img--unillanos" src="assets/logo-unillanos.png" alt="Universidad de los Llanos"><img class="ua-logos__img ua-logos__img--acreditacion" src="assets/logo-acreditacion.png" alt="Acreditación institucional en alta calidad"><img class="ua-logos__img ua-logos__img--volamos" src="assets/logo-volamos.png" alt="Volamos más alto por la calidad"></span>\n'
            '      <span class="ua-brand__system"><span class="ua-brand__kicker">Sistema de Información de</span><span class="ua-brand__name">Autoevaluación Institucional</span></span>\n'
            '    </a>\n'
            '    <div class="ua-header__tools">\n'
            '      <label class="ua-search">%s<span class="ua-sr">Buscar en el sitio</span><input type="search" placeholder="Buscar factores, documentos…"></label>\n'
            '      <a class="ua-btn ua-btn--secundario" href="#">%sIngresar</a>\n'
            '      <button class="ua-menu-toggle" type="button" data-ua-menu-toggle aria-controls="ua-menu" aria-expanded="false">%s<span class="ua-sr">Abrir menú</span></button>\n'
            '    </div>\n'
            '  </div>\n'
            '  %s\n'
            '</header>') % (ic("search"), ic("user"), ic("menu"), nav(section, page))

def footer():
    return ('<footer class="ua-footer">\n'
        '  <div class="ua-footer__brand"><div class="ua-container">\n'
        '    <span class="ua-logos ua-logos--lg"><img class="ua-logos__img ua-logos__img--unillanos" src="assets/logo-unillanos.png" alt="Universidad de los Llanos"><img class="ua-logos__img ua-logos__img--acreditacion" src="assets/logo-acreditacion.png" alt="Acreditación institucional en alta calidad"><img class="ua-logos__img ua-logos__img--volamos" src="assets/logo-volamos.png" alt="Volamos más alto por la calidad"></span>\n'
        '    <p class="ua-footer__inst"><strong>Universidad de los Llanos</strong><span>Sedes Villavicencio y Granada · Meta, Colombia</span></p>\n'
        '  </div></div>\n  <div class="ua-footer__rule"></div>\n  <div class="ua-container">\n  <div class="ua-footer__top">\n'
        '    <div><p class="ua-footer__name">Sistema de Información de Autoevaluación Institucional</p><p>Un espacio para consultar, participar y hacer seguimiento a la mejora continua de la Universidad.</p></div>\n'
        '    <div><h3>Institucional</h3><ul class="ua-footer__links"><li><a href="institucional-registro-calificado.html">Registro calificado institucional</a></li><li><a href="institucional-autoevaluacion.html">Autoevaluación</a></li><li><a href="institucional-autoevaluacion.html#plan-mejoramiento">Plan de mejoramiento</a></li></ul></div>\n'
        '    <div><h3>Programas y documentos</h3><ul class="ua-footer__links"><li><a href="programas.html#registro">Registro calificado de programa</a></li><li><a href="programas.html#nacional">Autoevaluación de programas</a></li><li><a href="documentos.html#normatividad">Normatividad</a></li><li><a href="documentos.html#documentos-base">Documentos base</a></li><li><a href="participa.html">Participa</a></li></ul></div>\n'
        '    <div><h3>Contacto</h3><ul class="ua-footer__contact"><li>%s<span>Dirección de la sede principal<br>Villavicencio, Meta, Colombia</span></li><li>%s<span>Teléfono de contacto</span></li><li>%s<span>Correo de la oficina de autoevaluación</span></li></ul></div>\n'
        '  </div>\n'
        '  <div class="ua-footer__bottom"><span>Institución de educación superior sujeta a inspección y vigilancia por el Ministerio de Educación Nacional. © 2026 Universidad de los Llanos.</span>\n'
        '    <span class="ua-footer__legal-links"><a href="#">Política de datos personales</a><a href="#">Mapa del sitio</a><a href="#">Accesibilidad</a></span></div>\n'
        '</div></footer>\n'
        '<a class="ua-fab" href="participa.html#recomendacion">%s<span class="ua-fab__text">¿Tienes una recomendación?</span></a>'
        ) % (ic("map-pin"), ic("phone"), ic("mail"), ic("message"))

def page(filename, title, section, current, body, extra_main_attr=""):
    doc = ('<!doctype html>\n<html lang="es-CO">\n<head>\n  <meta charset="utf-8">\n  <meta name="viewport" content="width=device-width, initial-scale=1">\n'
           '  <title>%s · Universidad de los Llanos</title>\n  <meta name="theme-color" content="#e3061d">\n'
           '  <link rel="icon" type="image/png" href="assets/favicon.png">\n'
           '  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..800&display=swap">\n'
           '  <link rel="stylesheet" href="style.css">\n</head>\n<body class="ua">\n%s\n<main id="contenido"%s>\n%s\n</main>\n%s\n'
           '<script src="datos-ejemplo.js"></script>\n<script src="app.js"></script>\n<script>Unillanos.attach(document);</script>\n</body>\n</html>\n'
           ) % (esc(title), header(section, current), (" " + extra_main_attr) if extra_main_attr else "", body, footer())
    open(os.path.join(OUT, filename), "w", encoding="utf-8").write(doc)

# ---------------------------------------------------------------- piezas
def crumbs(items):
    parts = []
    for i, (label, href) in enumerate(items):
        if i == len(items) - 1:
            parts.append('<span aria-current="page">%s</span>' % esc(label))
        else:
            parts.append('<a href="%s">%s</a>%s' % (href, esc(label), ic("chevron-right", "ua-icon--sm")))
    return '<nav class="ua-breadcrumb" aria-label="Ruta de navegación">%s</nav>' % "".join(parts)

CHIP_PROTO = '<span class="ua-chip-proto">%s Prototipo · contenido de ejemplo</span>' % ic("info", "ua-icon--sm")

def pagehead(crumb_items, eyebrow, title, lead, extra=""):
    return ('<section class="ua-pagehead"><div class="ua-container">%s'
            '<div class="ua-pagehead__row">%s%s</div>'
            '<span class="ua-eyebrow" style="display:block">%s</span><h1 class="ua-pagehead__title">%s</h1>'
            '<p class="ua-pagehead__lead">%s</p>%s</div></section>') % (
        crumbs(crumb_items), CHIP_PROTO, "", esc(eyebrow), title, esc(lead), extra)

def section_head(eyebrow, title, lead="", right=""):
    return ('<div class="ua-section__head"><div><span class="ua-eyebrow">%s</span><h2 class="ua-h2">%s</h2>%s</div>%s</div>'
            % (esc(eyebrow), title, ("<p>%s</p>" % esc(lead)) if lead else "", right))

def reco_band():
    factores = ["Factor 01 · Identidad institucional", "Factor 02 · Gobierno institucional y transparencia",
                "Factor 03 · Desarrollo, gestión y sostenibilidad institucional", "Factor 04 · Mejoramiento continuo y autorregulación",
                "Factor 05 · Estructura y procesos académicos", "Factor 06 · Aportes de la investigación, la innovación, el desarrollo tecnológico y la creación",
                "Factor 07 · Impacto social", "Factor 08 · Visibilidad nacional e internacional", "Factor 09 · Bienestar institucional",
                "Factor 10 · Comunidad de profesores", "Factor 11 · Comunidad de estudiantes", "Factor 12 · Comunidad de egresados", "Proceso en general"]
    opts = "".join("<option>%s</option>" % esc(f) for f in factores)
    estam = "".join("<option>%s</option>" % e for e in ["Estudiante", "Profesor", "Egresado", "Personal administrativo", "Directivo", "Sector externo"])
    chk = ic("check")
    return ('<section class="ua-reco" id="recomendacion"><div class="ua-container ua-reco__grid">\n'
        '  <div class="ua-reco__intro"><span class="ua-eyebrow">Participa</span><h2 class="ua-h2">¿Qué recomendarías para fortalecer la mejora continua en la Universidad?</h2>\n'
        '    <p>Tu voz hace parte del proceso. Cada recomendación se revisa por el comité de autoevaluación.</p>\n'
        '    <ul class="ua-reco__points">\n'
        '      <li>%s Se clasifica por factor y se remite a la dependencia responsable.</li>\n'
        '      <li>%s Alimenta la actualización del plan de mejoramiento.</li>\n'
        '      <li>%s Los resultados se publican en el informe de seguimiento.</li>\n'
        '    </ul></div>\n'
        '  <form class="ua-form" data-ua-form novalidate>\n'
        '  <div class="ua-form__row">\n'
        '    <div class="ua-field"><label for="r-nombre">Nombre <span class="ua-field__opt">(opcional)</span></label><input class="ua-input" id="r-nombre" type="text" autocomplete="name" placeholder="Tu nombre"></div>\n'
        '    <div class="ua-field"><label for="r-est">Estamento</label><select class="ua-select" id="r-est" required><option value="">Selecciona una opción</option>%s</select></div>\n'
        '  </div>\n'
        '  <div class="ua-field"><label for="r-factor">Factor relacionado</label><select class="ua-select" id="r-factor"><option value="">Selecciona un factor</option>%s</select></div>\n'
        '  <div class="ua-field"><label for="r-texto">Tu recomendación</label><textarea class="ua-textarea" id="r-texto" maxlength="1000" required placeholder="Escribe aquí tu recomendación"></textarea><span class="ua-field__help"><span>Sé concreto: qué propones y por qué.</span><span data-ua-count>0 / 1000</span></span></div>\n'
        '  <label class="ua-check"><input type="checkbox" required><span>Autorizo el tratamiento de mis datos personales conforme a la <a href="#">política de protección de datos</a> de la Universidad.</span></label>\n'
        '  <div class="ua-alert" data-ua-ok tabindex="-1" role="status" hidden>%s<span>Gracias. Recibimos tu recomendación y la tendremos en cuenta en el plan de mejoramiento.</span></div>\n'
        '  <div class="ua-form__actions"><span class="ua-meta">%s Tus datos se tratan de forma confidencial.</span><button class="ua-btn ua-btn--primario" type="submit">Enviar recomendación%s</button></div>\n'
        '</form>\n</div></section>') % (chk, chk, chk, estam, opts, chk, ic("shield", "ua-icon--sm"), ic("arrow-right"))

def estado(texto, tipo):
    return '<span class="ua-estado ua-estado--%s">%s</span>' % (tipo, esc(texto))

def doc_item(cat, title, meta, kind="PDF"):
    return ('<li class="ua-doc"><span class="ua-doc__icon">%s%s</span><div class="ua-doc__main"><span class="ua-doc__cat">%s</span><h3 class="ua-doc__title">%s</h3><p class="ua-meta">%s</p></div>'
            '<a class="ua-icon-btn" href="#">%s<span class="ua-sr">Descargar %s</span></a></li>') % (ic("file"), kind, esc(cat), esc(title), esc(meta), ic("download"), esc(title))

# ---------------------------------------------------------------- INICIO
def build_inicio():
    steps = [
        ("Planeación y organización", "Comité, cronograma y ponderación de factores.", "hecho", "Completada"),
        ("Recolección", "Encuestas, grupos focales y evidencias.", "hecho", "Completada"),
        ("Análisis y juicio", "Valoración y juicio de cada factor.", "hecho", "Completada"),
        ("Informe de autoevaluación", "Consolidación y aprobación del informe.", "hecho", "Completada"),
        ("Plan de mejoramiento y radicación", "Acciones, responsables y plazos; radicación del informe y sus anexos.", "actual", "En curso"),
    ]
    li = []
    for i, (t, d, st, lab) in enumerate(steps, 1):
        dot = ic("check") if st == "hecho" else str(i)
        cur = ' aria-current="step"' if st == "actual" else ""
        li.append('<li class="ua-step ua-step--%s"%s><span class="ua-step__dot">%s</span><h3 class="ua-step__title">%s</h3><p class="ua-step__text">%s</p><span class="ua-step__state">%s</span></li>' % (st, cur, dot, t, d, lab))
    steps_html = '<ol class="ua-steps" style="list-style:none;margin:0;padding:0">%s</ol>' % "".join(li)

    factores = datos.build()["institucional"]["factores"]
    chips = "".join('<li class="ua-factor-chip"><span class="ua-factor-chip__n">%02d</span>%s</li>' % (f["n"], esc(f["name"])) for f in factores)

    hero = ('<section class="ua-hero"><div class="ua-container"><div class="ua-hero__grid"><div>\n'
        '  <div class="ua-hero__meta">%s</div>\n'
        '  <span class="ua-eyebrow" style="display:block">Proceso de autoevaluación 2024 – 2026</span>\n'
        '  <h1 class="ua-hero__title">Autoevaluación <span>Institucional</span></h1>\n'
        '  <p class="ua-hero__lead">Un ejercicio participativo en el que la comunidad universitaria reconoce sus fortalezas, identifica oportunidades de mejora y define el camino hacia la excelencia académica en la Orinoquia.</p>\n'
        '  <p class="ua-hero__note ua-meta">%sÚltima actualización: 15 de septiembre de 2026</p>\n'
        '</div>\n'
        '<div class="ua-hero__media"><div class="ua-ph ua-ph--16x10" role="img" aria-label="Espacio para imagen: Fotografía institucional de la comunidad universitaria">%s<span class="ua-ph__label">Fotografía institucional de la comunidad universitaria</span><span class="ua-ph__size">1200 × 750 px</span></div></div>\n'
        '</div></div></section>') % (CHIP_PROTO, ic("calendar", "ua-icon--sm"), ic("image", "ua-icon--lg"))

    stats = ('<section class="ua-stats" aria-label="Cifras del proceso"><div class="ua-container"><ul class="ua-stats__list">'
        '<li class="ua-stat"><span class="ua-stat__value">12</span><span class="ua-stat__label">Factores evaluados</span></li>'
        '<li class="ua-stat"><span class="ua-stat__value">4.812</span><span class="ua-stat__label">Personas participaron en encuestas</span></li>'
        '<li class="ua-stat"><span class="ua-stat__value">37</span><span class="ua-stat__label">Acciones de mejora formuladas</span></li>'
        '<li class="ua-stat"><span class="ua-stat__value">4,2</span><span class="ua-stat__label">Valoración global sobre 5</span></li>'
        '</ul></div></section>')

    concepto = ('<section class="ua-section" id="que-es"><div class="ua-container"><div class="ua-concept">'
        '<div>%s'
        '<p class="ua-lead">La autoevaluación es el ejercicio con el que la Universidad y cada uno de sus programas se miran a sí mismos: reúnen evidencias, escuchan a la comunidad y emiten un juicio sobre su calidad frente a los lineamientos del modelo de acreditación en alta calidad.</p>'
        '<p>Su resultado no es un fin en sí mismo. Alimenta el <strong>plan de mejoramiento</strong>, sustenta los procesos de <strong>registro calificado</strong> y de <strong>acreditación</strong> y permite rendir cuentas a la comunidad sobre lo que se ha logrado y lo que falta por mejorar.</p>'
        '<ul class="ua-principles">'
        '<li>%s<span><strong>Participativa.</strong> Estudiantes, profesores, egresados, administrativos y sector externo aportan su mirada.</span></li>'
        '<li>%s<span><strong>Basada en evidencias.</strong> Cada juicio se sustenta en datos, documentos e instrumentos aplicados.</span></li>'
        '<li>%s<span><strong>Orientada a la mejora.</strong> Cada hallazgo se traduce en acciones con responsables y plazos.</span></li>'
        '</ul></div>'
        '<aside class="ua-concept__card" aria-label="Qué se evalúa"><h3 class="ua-h4">¿Qué se evalúa?</h3>'
        '<p class="ua-meta">Los doce factores del modelo institucional, para las dos sedes, y los factores de cada programa académico.</p>'
        '<ol class="ua-factor-chips">%s</ol></aside>'
        '</div></div></section>') % (
        section_head_inline("La autoevaluación", "¿Qué es la autoevaluación?"),
        ic("users", "ua-icon--sm"), ic("clipboard", "ua-icon--sm"), ic("target", "ua-icon--sm"), chips)

    proceso = ('<section class="ua-section ua-section--alt" id="proceso"><div class="ua-container">%s%s</div></section>') % (
        section_head("El proceso", "Así avanza la autoevaluación", "Cinco fases, desde la planeación hasta la radicación, con participación de estudiantes, profesores, egresados, personal administrativo y sector externo."),
        steps_html)

    def card(icon_name, title, text, href, cta):
        return ('<a class="ua-card" href="%s"><span class="ua-card__icon">%s</span><h3 class="ua-card__title">%s</h3><p class="ua-card__text">%s</p>'
                '<span class="ua-card__cta">%s%s</span></a>') % (href, ic(icon_name), title, text, cta, ic("arrow-right", "ua-icon--sm"))
    explora = ('<section class="ua-section" id="explora"><div class="ua-container">%s<div class="ua-cards">%s%s%s%s</div></div></section>') % (
        section_head("Explora el sistema", "Lo que se ha evaluado, en un solo lugar", "Entra por el ámbito que te interesa: la Universidad en sus dos sedes o cada uno de sus programas."),
        card("building", "Institucional", "Registro calificado, autoevaluación por factores y plan de mejoramiento de la Universidad, en las sedes Villavicencio y Granada.", "institucional-autoevaluacion.html", "Ver autoevaluación institucional"),
        card("cap", "Programas", "Busca un programa y consulta su registro calificado, su autoevaluación nacional o internacional y su plan de mejoramiento.", "programas.html", "Buscar un programa"),
        card("book", "Documentos", "Normatividad y documentos base que sustentan el proceso de autoevaluación.", "documentos.html", "Ver documentos"),
        card("message", "Participa", "Conoce las encuestas, grupos focales y entrevistas del proceso y envía tus recomendaciones.", "participa.html", "Participar"))

    galeria = galeria_html()
    body = hero + stats + concepto + proceso + explora + galeria + reco_band()
    page("index.html", "Autoevaluación Institucional", "inicio", "inicio", body)

def section_head_inline(eyebrow, title):
    return '<span class="ua-eyebrow">%s</span><h2 class="ua-h2">%s</h2>' % (esc(eyebrow), title)

def galeria_html():
    caps = ["Taller de ponderación con estudiantes", "Mesa de trabajo con profesores", "Encuentro con egresados", "Jornada en sede regional",
            "Comité de autoevaluación", "Socialización de resultados", "Visita a laboratorios", "Foro con el sector productivo"]
    items = "".join('<figure class="ua-gallery__item"><div class="ua-ph ua-ph--4x3" role="img" aria-label="Espacio para imagen: Fotografía">%s<span class="ua-ph__label">Fotografía</span><span class="ua-ph__size">800 × 600 px</span></div><figcaption>%s</figcaption></figure>' % (ic("image", "ua-icon--lg"), c) for c in caps)
    return ('<section class="ua-section ua-section--alt" data-ua-gallery><div class="ua-container">'
        '<div class="ua-section__head"><div><span class="ua-eyebrow">Galería</span><h2 class="ua-h2">La comunidad construye el proceso</h2></div>'
        '<div class="ua-gallery__controls"><button class="ua-icon-btn" type="button" data-dir="-1">%s<span class="ua-sr">Anterior</span></button><button class="ua-icon-btn" type="button" data-dir="1">%s<span class="ua-sr">Siguiente</span></button></div></div>'
        '<div class="ua-gallery__track" tabindex="0" aria-label="Galería del proceso">%s</div></div></section>') % (ic("chevron-left"), ic("chevron-right"), items)

# ---------------------------------------------------------------- INSTITUCIONAL · REGISTRO CALIFICADO
def build_inst_registro():
    head = pagehead([("Inicio", "index.html"), ("Institucional", "institucional-autoevaluacion.html"), ("Registro calificado institucional", "")],
        "Institucional", "Registro calificado <span>institucional</span>",
        "El registro calificado institucional reconoce que la Universidad cumple las condiciones de calidad exigidas para ofrecer y desarrollar sus programas.")

    concepto = ('<section class="ua-section"><div class="ua-container"><div class="ua-concept">'
        '<div>%s<p class="ua-lead">El registro calificado es el reconocimiento que otorga el Ministerio de Educación Nacional cuando una institución demuestra que reúne las condiciones de calidad para funcionar y ofrecer programas de educación superior.</p>'
        '<p>La verificación se hace sobre las <strong>condiciones institucionales</strong>: la forma en que la Universidad selecciona y evalúa a sus estudiantes y profesores, se organiza, se evalúa a sí misma, acompaña a sus egresados, cuida el bienestar de su comunidad y dispone de los recursos para cumplir sus funciones.</p>'
        '<p class="ua-meta">%s La información de esta página es de ejemplo; las condiciones y fechas definitivas se cargarán cuando estén disponibles.</p></div>'
        '<aside class="ua-concept__card"><h3 class="ua-h4">Estado por sede</h3><ul class="ua-sedes">'
        '<li class="ua-sede"><div class="ua-sede__head"><span class="ua-sede__name">%s Villavicencio</span>%s</div><dl class="ua-ficha ua-ficha--compact"><div><dt>Resolución</dt><dd>N.º 0000 de 2023 (ejemplo)</dd></div><div><dt>Vigente hasta</dt><dd>Diciembre de 2030</dd></div></dl></li>'
        '<li class="ua-sede"><div class="ua-sede__head"><span class="ua-sede__name">%s Granada</span>%s</div><dl class="ua-ficha ua-ficha--compact"><div><dt>Resolución</dt><dd>N.º 0000 de 2023 (ejemplo)</dd></div><div><dt>Vigente hasta</dt><dd>Diciembre de 2030</dd></div></dl></li>'
        '</ul></aside></div></div></section>') % (
        section_head_inline("Concepto", "¿Qué es el registro calificado?"), ic("info", "ua-icon--sm"),
        ic("map-pin", "ua-icon--sm"), estado("Vigente", "ok"), ic("map-pin", "ua-icon--sm"), estado("Vigente", "ok"))

    conds = [
        ("Mecanismos de selección y evaluación de estudiantes y profesores", "Criterios transparentes de ingreso, vinculación, permanencia y evaluación.", ("Cumple", "ok"), ("Cumple", "ok")),
        ("Estructura administrativa y académica", "Organización, gobierno y procesos que soportan las funciones misionales.", ("Cumple", "ok"), ("Cumple", "ok")),
        ("Cultura de autoevaluación", "Prácticas permanentes de autoevaluación, autorregulación y mejora continua.", ("Cumple", "ok"), ("En revisión", "proceso")),
        ("Programa de egresados", "Seguimiento a egresados y mecanismos para vincularlos con la Universidad.", ("En revisión", "proceso"), ("En revisión", "proceso")),
        ("Modelo de bienestar", "Servicios y programas de bienestar para toda la comunidad universitaria.", ("Cumple", "ok"), ("Cumple", "ok")),
        ("Recursos suficientes para las funciones misionales", "Recursos físicos, tecnológicos y financieros disponibles para el cumplimiento de la misión.", ("Cumple", "ok"), ("Pendiente", "pendiente")),
    ]
    cards = "".join('<li class="ua-cond"><span class="ua-cond__n">%02d</span><h3 class="ua-cond__title">%s</h3><p class="ua-cond__text">%s</p>'
                    '<div class="ua-cond__estados"><span class="ua-cond__sede">Villavicencio</span>%s<span class="ua-cond__sede">Granada</span>%s</div></li>'
                    % (i, esc(t), esc(d), estado(*v), estado(*g)) for i, (t, d, v, g) in enumerate(conds, 1))
    condiciones = ('<section class="ua-section ua-section--alt" id="condiciones"><div class="ua-container">%s<ol class="ua-cond-grid">%s</ol></div></section>') % (
        section_head("Condiciones institucionales", "Lo que se verifica", "Cada condición se documenta con evidencias y se revisa por sede. El estado que ves es ilustrativo."), cards)

    docs = ('<section class="ua-section"><div class="ua-container">%s<ul class="ua-docs">%s%s%s%s</ul></div></section>') % (
        section_head("Soportes", "Documentos del registro calificado"),
        doc_item("Acto administrativo", "Resolución de registro calificado institucional · Villavicencio", "PDF · 1,4 MB · ejemplo"),
        doc_item("Acto administrativo", "Resolución de registro calificado institucional · Granada", "PDF · 1,3 MB · ejemplo"),
        doc_item("Informe", "Informe de verificación de condiciones institucionales", "PDF · 3,2 MB · ejemplo"),
        doc_item("Matriz", "Matriz de evidencias por condición", "XLSX · 220 KB · ejemplo", "XLSX"))
    cta = ('<section class="ua-section ua-section--alt"><div class="ua-container"><div class="ua-cta-row"><div><h2 class="ua-h3">Siguiente paso</h2><p>Consulta la autoevaluación institucional por factores, con su valoración y su plan de mejoramiento.</p></div>'
           '<a class="ua-btn ua-btn--primario" href="institucional-autoevaluacion.html">Ver autoevaluación%s</a></div></div></section>') % ic("arrow-right")
    page("institucional-registro-calificado.html", "Registro calificado institucional", "institucional", "inst-registro", head + concepto + condiciones + docs + cta)

# ---------------------------------------------------------------- INSTITUCIONAL · AUTOEVALUACIÓN
def build_inst_autoevaluacion():
    scope = ('<div class="ua-scope" role="group" aria-label="Elegir sede">'
             '<span class="ua-scope__label">%s Sede</span>'
             '<button type="button" class="ua-scope__opt" data-ua-sede="villavicencio" aria-pressed="true">Villavicencio</button>'
             '<button type="button" class="ua-scope__opt" data-ua-sede="granada" aria-pressed="false">Granada</button></div>') % ic("map-pin", "ua-icon--sm")
    head = ('<section class="ua-pagehead"><div class="ua-container">%s'
            '<div class="ua-pagehead__row">%s%s</div>'
            '<span class="ua-eyebrow" style="display:block">Institucional · Proceso 2024 – 2026</span>'
            '<h1 class="ua-pagehead__title">Autoevaluación <span>institucional</span> · <span data-ua-sede-name>Villavicencio</span></h1>'
            '<p class="ua-pagehead__lead">Valoración de los doce factores del modelo institucional, con sus fortalezas y el plan de mejoramiento. Elige la sede para ver sus resultados.</p></div></section>') % (
        crumbs([("Inicio", "index.html"), ("Institucional", "institucional-autoevaluacion.html"), ("Autoevaluación", "")]), scope, CHIP_PROTO)

    stats = ('<section class="ua-stats" aria-label="Cifras de la sede"><div class="ua-container"><ul class="ua-stats__list">'
        '<li class="ua-stat"><span class="ua-stat__value" data-ua-stat="factores">12</span><span class="ua-stat__label">Factores evaluados</span></li>'
        '<li class="ua-stat"><span class="ua-stat__value" data-ua-stat="participantes">3.126</span><span class="ua-stat__label">Personas participaron en encuestas</span></li>'
        '<li class="ua-stat"><span class="ua-stat__value" data-ua-stat="acciones">22</span><span class="ua-stat__label">Acciones de mejora formuladas</span></li>'
        '<li class="ua-stat"><span class="ua-stat__value" data-ua-stat="global">4,2</span><span class="ua-stat__label">Valoración global sobre 5</span></li>'
        '</ul></div></section>')

    factores = ('<section class="ua-section" id="factores"><div class="ua-container">%s'
        '<div class="ua-factor-grid" data-ua-factor-grid data-source="institucional"></div>'
        '<div class="ua-adendas" data-ua-adendas data-source="institucional"></div></div></section>') % section_head(
        "Resultados", "Factores evaluados", "Consulta la valoración, las fortalezas y el plan de mejoramiento de cada uno de los doce factores y las adendas del informe.",
        '<a class="ua-btn ua-btn--secundario" href="#">%sDescargar informe consolidado</a>' % ic("download"))

    detalle = ('<section class="ua-section ua-section--alt" id="detalle"><div class="ua-container"><span id="plan-mejoramiento"></span>%s'
        '<div class="ua-tabs" id="tabs-institucional" data-ua-tabs data-source="institucional" data-initial="f1" data-label="Factores y adendas"></div></div></section>') % section_head(
        "Informe por factor", "Valoración y plan de mejoramiento", "Cada factor se acompaña de su plan de mejoramiento: situaciones por intervenir, acciones, responsables y plazos.")
    page("institucional-autoevaluacion.html", "Autoevaluación institucional", "institucional", "inst-autoevaluacion", head + stats + factores + detalle)

# ---------------------------------------------------------------- PROGRAMAS (buscador)
def build_programas():
    vistas = [("registro", "Registro calificado"), ("nacional", "Autoevaluación nacional"), ("internacional", "Autoevaluación internacional"), ("plan", "Plan de mejoramiento")]
    btns = "".join('<button type="button" class="ua-filter__btn" data-ua-vista="%s" aria-pressed="%s">%s</button>' % (k, "true" if k == "registro" else "false", v) for k, v in vistas)
    head = ('<section class="ua-pagehead"><div class="ua-container">%s'
            '<div class="ua-pagehead__row">%s</div>'
            '<span class="ua-eyebrow" style="display:block">Programas académicos</span>'
            '<h1 class="ua-pagehead__title" data-ua-vista-title>Registro calificado de programa</h1>'
            '<p class="ua-pagehead__lead" data-ua-vista-lead>Elige un programa para consultar su registro calificado.</p></div></section>') % (
        crumbs([("Inicio", "index.html"), ("Programas", "")]), CHIP_PROTO)
    cuerpo = ('<section class="ua-section"><div class="ua-container">'
        '<div class="ua-filter" role="group" aria-label="Qué quieres consultar">%s</div>'
        '<div class="ua-searchbar"><label class="ua-search ua-search--full">%s<span class="ua-sr">Buscar un programa</span><input type="search" data-ua-prog-q placeholder="Nombre del programa o facultad"></label>'
        '<label class="ua-selectbar"><span class="ua-sr">Facultad</span><select class="ua-select" data-ua-prog-facultad><option value="">Todas las facultades</option></select></label>'
        '<label class="ua-selectbar"><span class="ua-sr">Sede</span><select class="ua-select" data-ua-prog-sede><option value="">Todas las sedes</option><option>Villavicencio</option><option>Granada</option></select></label></div>'
        '<p class="ua-searchbar__count ua-meta" data-ua-prog-count aria-live="polite"></p>'
        '<ul class="ua-prog-list" data-ua-prog-list></ul>'
        '<p class="ua-meta ua-prog-note">%s En este prototipo, todos los programas abren la ficha del programa de ejemplo (Ingeniería Electrónica).</p>'
        '</div></section>') % (btns, ic("search"), ic("info", "ua-icon--sm"))
    page("programas.html", "Programas académicos", "programas", "prog-buscador", head + cuerpo, "data-ua-programas")

# ---------------------------------------------------------------- PROGRAMA DE EJEMPLO
def build_programa():
    nac = datos.NAC
    head = ('<section class="ua-pagehead"><div class="ua-container">%s'
        '<div class="ua-pagehead__row">%s<span class="ua-tag ua-tag--acento">Programa de ejemplo</span></div>'
        '<span class="ua-eyebrow" style="display:block">Facultad de Ciencias Básicas e Ingeniería · Sede Villavicencio</span>'
        '<h1 class="ua-pagehead__title">Ingeniería <span>Electrónica</span></h1>'
        '<p class="ua-pagehead__lead">Registro calificado, autoevaluación y plan de mejoramiento del programa, en un solo lugar.</p>'
        '<form class="ua-progsearch" data-ua-prog-search data-ua-route-link role="search"><label class="ua-search ua-search--full">%s<span class="ua-sr">Buscar otro programa</span>'
        '<input type="search" list="prog-lista" placeholder="Buscar otro programa"><datalist id="prog-lista"></datalist></label>'
        '<button class="ua-btn ua-btn--secundario" type="submit">Buscar</button></form>'
        '</div></section>') % (crumbs([("Inicio", "index.html"), ("Programas", "programas.html"), ("Ingeniería Electrónica", "")]), CHIP_PROTO, ic("search"))

    tabs = ('<div class="ua-viewtabs" role="tablist" aria-label="Secciones del programa">'
        '<button class="ua-viewtab" role="tab" type="button" data-view="registro" aria-selected="true">%s Registro calificado</button>'
        '<button class="ua-viewtab" role="tab" type="button" data-view="autoevaluacion" aria-selected="false">%s Autoevaluación</button>'
        '<button class="ua-viewtab" role="tab" type="button" data-view="plan" aria-selected="false">%s Plan de mejoramiento</button></div>') % (ic("award"), ic("target"), ic("layers"))

    ficha = [("Nivel", "Pregrado universitario"), ("Modalidad", "Presencial"), ("Duración", "10 semestres"), ("Créditos académicos", "160"),
             ("Código SNIES", "00000 (ejemplo)"), ("Sede", "Villavicencio"), ("Resolución de registro", "N.º 0000 de 2022 (ejemplo)"), ("Vigente hasta", "Diciembre de 2029")]
    ficha_html = "".join("<div><dt>%s</dt><dd>%s</dd></div>" % (a, b) for a, b in ficha)
    cond = [
        ("Denominación", "El nombre del programa corresponde a su contenido y a su nivel de formación.", ("Cumple", "ok")),
        ("Justificación", "Necesidad del programa y su contribución al desarrollo regional.", ("Cumple", "ok")),
        ("Aspectos curriculares", "Plan de estudios coherente, flexible e interdisciplinario.", ("Cumple", "ok")),
        ("Organización de las actividades académicas y proceso formativo", "Créditos, metodología y evaluación orientados a resultados de aprendizaje.", ("Cumple", "ok")),
        ("Investigación, innovación y creación", "Formación para la investigación y aportes al desarrollo tecnológico.", ("En revisión", "proceso")),
        ("Relación con el sector externo", "Vínculos con empresas, entidades y comunidad.", ("Cumple", "ok")),
        ("Profesores", "Núcleo profesoral suficiente, con formación y dedicación adecuadas.", ("Cumple", "ok")),
        ("Medios educativos", "Recursos bibliográficos, laboratorios y plataformas de apoyo.", ("Cumple", "ok")),
        ("Infraestructura física y tecnológica", "Espacios y equipos adecuados para el desarrollo del programa.", ("Pendiente", "pendiente")),
    ]
    cond_html = "".join('<li class="ua-cond"><span class="ua-cond__n">%02d</span><h3 class="ua-cond__title">%s</h3><p class="ua-cond__text">%s</p><div class="ua-cond__estados">%s</div></li>' % (i, esc(t), esc(d), estado(*e)) for i, (t, d, e) in enumerate(cond, 1))
    registro = ('<div data-view-panel="registro" role="tabpanel">'
        '<div class="ua-panel-intro"><div><span class="ua-eyebrow">Registro calificado</span><h2 class="ua-h2">Ficha del programa</h2></div>%s</div>'
        '<dl class="ua-ficha">%s</dl>'
        '<div class="ua-panel-intro"><div><span class="ua-eyebrow">Condiciones de calidad</span><h2 class="ua-h2">Lo que se verifica</h2><p>Las nueve condiciones de calidad del programa. El estado que ves es ilustrativo.</p></div></div>'
        '<ol class="ua-cond-grid">%s</ol>'
        '<ul class="ua-docs ua-docs--spaced">%s%s</ul></div>') % (
        estado("Registro vigente", "ok"), ficha_html, cond_html,
        doc_item("Acto administrativo", "Resolución de registro calificado · Ingeniería Electrónica", "PDF · 1,1 MB · ejemplo"),
        doc_item("Documento maestro", "Documento maestro del programa", "PDF · 4,8 MB · ejemplo"))

    auto = ('<div data-view-panel="autoevaluacion" role="tabpanel" hidden>'
        '<div class="ua-panel-intro"><div><span class="ua-eyebrow">Autoevaluación del programa</span><h2 class="ua-h2">Factores, valoración y fortalezas</h2><p>Elige con qué fin se mira la autoevaluación: acreditación nacional o internacional.</p></div>'
        '<div class="ua-scope" role="group" aria-label="Tipo de autoevaluación"><span class="ua-scope__label">%s Fin</span>'
        '<button type="button" class="ua-scope__opt" data-sub="nacional" aria-pressed="true">Nacional</button>'
        '<button type="button" class="ua-scope__opt" data-sub="internacional" aria-pressed="false">Internacional</button></div></div>'
        '<div data-sub-panel="nacional"><div class="ua-factor-grid" data-ua-factor-grid data-source="programa-nacional"></div>'
        '<div class="ua-subsection"><div class="ua-tabs" id="tabs-prog-nacional" data-ua-tabs data-source="programa-nacional" data-initial="p1" data-label="Factores del programa"></div></div></div>'
        '<div data-sub-panel="internacional" hidden><div class="ua-factor-grid ua-factor-grid--four" data-ua-factor-grid data-source="programa-internacional"></div>'
        '<div class="ua-subsection"><div class="ua-tabs" id="tabs-prog-internacional" data-ua-tabs data-source="programa-internacional" data-initial="i1" data-label="Dimensiones del programa"></div></div></div>'
        '</div>') % ic("target", "ua-icon--sm")

    plan = ('<div data-view-panel="plan" role="tabpanel" hidden>'
        '<div class="ua-panel-intro"><div><span class="ua-eyebrow">Plan de mejoramiento</span><h2 class="ua-h2">Acciones del programa</h2><p>Reúne las acciones de mejora de la autoevaluación nacional (F) e internacional (D).</p></div></div>'
        '<div data-ua-plan-all></div></div>')

    body = head + ('<section class="ua-section"><div class="ua-container" data-ua-views>%s%s%s%s</div></section>') % (tabs, registro, auto, plan)
    page("programa-ejemplo.html", "Ingeniería Electrónica", "programas", "prog-ficha", body)

# ---------------------------------------------------------------- DOCUMENTOS
def build_documentos():
    head = pagehead([("Inicio", "index.html"), ("Documentos", "")], "Documentos", "Normatividad y <span>documentos base</span>",
        "Las normas que orientan la autoevaluación y los documentos que sustentan el proceso.")
    normas = [
        ("Acuerdo 01 de 2025 CESU", "Lineamientos y aspectos por evaluar para la acreditación en alta calidad de programas, unidades académicas e instituciones", "PDF · 1,0 MB"),
        ("Acuerdo CESU 02 de 2020", "Modelo de acreditación en alta calidad (referente anterior)", "PDF · 1,2 MB"),
        ("Decreto 1330 de 2019", "Registro calificado y condiciones de calidad de programas e instituciones", "PDF · 860 KB"),
        ("Ley 1188 de 2008", "Regula el registro calificado de programas de educación superior", "PDF · 310 KB"),
        ("Ley 30 de 1992", "Organiza el servicio público de la educación superior", "PDF · 540 KB"),
        ("Ley 1581 de 2012", "Protección de datos personales", "PDF · 290 KB"),
    ]
    base = [
        ("Proyecto Educativo Institucional (PEI)", "PDF · 2,1 MB · 10 feb 2025"),
        ("Lineamientos de autoevaluación institucional", "PDF · 1,8 MB · 03 feb 2025"),
        ("Plan de Desarrollo Institucional", "PDF · 3,4 MB · ejemplo"),
        ("Guía metodológica de autoevaluación", "PDF · 2,3 MB · 18 mar 2025"),
    ]
    n_items = "".join(('<li class="ua-doc"><span class="ua-doc__icon">%sPDF</span><div class="ua-doc__main"><span class="ua-doc__cat">Normatividad</span><h3 class="ua-doc__title">%s</h3><p class="ua-doc__desc">%s</p><p class="ua-meta">%s</p></div>'
                       '<a class="ua-icon-btn" href="#">%s<span class="ua-sr">Descargar %s</span></a></li>') % (ic("file"), esc(t), esc(d), esc(m), ic("download"), esc(t)) for t, d, m in normas)
    b_items = "".join(doc_item("Documento base", t, m) for t, m in base)
    cuerpo = ('<section class="ua-section" id="normatividad"><div class="ua-container">%s<ul class="ua-docs">%s</ul></div></section>'
              '<section class="ua-section ua-section--alt" id="documentos-base"><div class="ua-container">%s<ul class="ua-docs">%s</ul></div></section>') % (
        section_head("Normatividad", "Normas que orientan el proceso", "Leyes, decretos y acuerdos del sector. Los enlaces de descarga son de ejemplo."), n_items,
        section_head("Documentos base", "Documentos que sustentan el proceso", "Los documentos institucionales se irán definiendo con el comité de autoevaluación."), b_items)
    page("documentos.html", "Documentos", "documentos", "doc-normatividad", head + cuerpo)

# ---------------------------------------------------------------- PARTICIPA
def build_participa():
    head = pagehead([("Inicio", "index.html"), ("Participa", "")], "Participa", "Tu voz hace parte del <span>proceso</span>",
        "Conoce las herramientas con las que la comunidad ha participado en la autoevaluación y envía tus propias recomendaciones.")
    def tool(icon_name, title, text, stats, estam):
        s = "".join('<li><span class="ua-tool__v">%s</span><span class="ua-tool__l">%s</span></li>' % (v, l) for v, l in stats)
        e = "".join('<span class="ua-tag">%s</span>' % x for x in estam)
        return ('<article class="ua-tool"><span class="ua-card__icon">%s</span><h3 class="ua-card__title">%s</h3><p class="ua-card__text">%s</p>'
                '<ul class="ua-tool__stats">%s</ul><div class="ua-tool__estamentos">%s</div>'
                '<a class="ua-btn ua-btn--texto" href="#">Ver instrumento%s</a></article>') % (ic(icon_name), title, text, s, e, ic("arrow-right"))
    herramientas = ('<section class="ua-section"><div class="ua-container">%s<div class="ua-tools">%s%s%s</div></div></section>') % (
        section_head("Herramientas de participación", "Cómo hemos escuchado a la comunidad", "Estos son los instrumentos aplicados durante la recolección de información."),
        tool("clipboard", "Encuestas", "Cuestionarios estructurados para conocer la percepción de la comunidad sobre cada factor.", [("4.812", "personas"), ("5", "estamentos")], ["Estudiantes", "Profesores", "Egresados", "Administrativos", "Sector externo"]),
        tool("users", "Grupos focales", "Conversaciones moderadas para profundizar en las razones detrás de las percepciones.", [("18", "sesiones"), ("216", "participantes")], ["Estudiantes", "Profesores", "Egresados"]),
        tool("mic", "Entrevistas", "Diálogos individuales con directivos y actores clave de la Universidad y su entorno.", [("42", "entrevistas")], ["Directivos", "Sector externo"]))
    page("participa.html", "Participa", "participa", "participa", head + herramientas + reco_band())

def write_data():
    d = datos.build()
    js = "/* Datos de ejemplo del prototipo (contenido ilustrativo, no oficial). En Drupal llegan desde Views / JSON:API. */\nwindow.UnillanosData = %s;\n" % json.dumps(d, ensure_ascii=False, indent=1)
    open(os.path.join(OUT, "datos-ejemplo.js"), "w", encoding="utf-8").write(js)
    app = open(os.path.join(os.path.dirname(__file__), "app_template.js"), encoding="utf-8").read().replace("__ICONS__", json.dumps(ICONS, ensure_ascii=False))
    open(os.path.join(OUT, "app.js"), "w", encoding="utf-8").write(app)

if __name__ == "__main__":
    write_data()
    build_inicio(); build_inst_registro(); build_inst_autoevaluacion(); build_programas(); build_programa(); build_documentos(); build_participa()
    print("ok", sorted(f for f in os.listdir(OUT) if f.endswith((".html", ".js", ".css"))))
