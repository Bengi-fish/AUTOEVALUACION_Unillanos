/* UNILLANOS · Sistema de Información de Autoevaluación
   Comportamientos de interfaz del prototipo (sin dependencias). En Drupal cada
   función se registra como Drupal.behaviors.<nombre>.attach(context).
   Los datos de ejemplo viven en datos-ejemplo.js (window.UnillanosData): en
   producción llegan desde Views/JSON:API. */
(function () {
  "use strict";
  var ICONS = {"search": "<circle cx=\"11\" cy=\"11\" r=\"7\"/><path d=\"m20 20-3.5-3.5\"/>", "user": "<circle cx=\"12\" cy=\"8\" r=\"4\"/><path d=\"M4 21c0-4 3.6-7 8-7s8 3 8 7\"/>", "chevron-down": "<path d=\"m6 9 6 6 6-6\"/>", "chevron-right": "<path d=\"m9 6 6 6-6 6\"/>", "chevron-left": "<path d=\"m15 6-6 6 6 6\"/>", "arrow-right": "<path d=\"M5 12h14M13 6l6 6-6 6\"/>", "check": "<path d=\"M5 12.5 10 17l9-10\"/>", "download": "<path d=\"M12 4v11M7 10l5 5 5-5M5 20h14\"/>", "file": "<path d=\"M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z\"/><path d=\"M14 3v5h5\"/>", "menu": "<path d=\"M4 7h16M4 12h16M4 17h16\"/>", "close": "<path d=\"M6 6l12 12M18 6 6 18\"/>", "message": "<path d=\"M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z\"/><path d=\"M8.5 12h.01M12 12h.01M15.5 12h.01\"/>", "image": "<rect x=\"3\" y=\"4\" width=\"18\" height=\"16\" rx=\"2\"/><circle cx=\"9\" cy=\"10\" r=\"2\"/><path d=\"m21 16-5-5-9 9\"/>", "map-pin": "<path d=\"M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z\"/><circle cx=\"12\" cy=\"10\" r=\"2.5\"/>", "phone": "<path d=\"M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z\"/>", "mail": "<rect x=\"3\" y=\"5\" width=\"18\" height=\"14\" rx=\"2\"/><path d=\"m3 7 9 6 9-6\"/>", "external": "<path d=\"M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5\"/>", "calendar": "<rect x=\"3\" y=\"5\" width=\"18\" height=\"16\" rx=\"2\"/><path d=\"M3 10h18M8 3v4M16 3v4\"/>", "users": "<circle cx=\"9\" cy=\"8\" r=\"3.5\"/><path d=\"M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6\"/><path d=\"M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2.2.6 3.5 2.8 3.5 6\"/>", "shield": "<path d=\"M12 3 4 6v6c0 5 3.4 8 8 9 4.6-1 8-4 8-9V6z\"/><path d=\"m9 12 2 2 4-4\"/>", "info": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M12 11v5M12 8h.01\"/>", "clock": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M12 7v5l3 2\"/>", "building": "<path d=\"M3 21h18M5 21V10M9 21V10M15 21V10M19 21V10M2 10l10-6 10 6z\"/>", "cap": "<path d=\"M2 9l10-5 10 5-10 5z\"/><path d=\"M6 11.5V16c0 1.5 3 3 6 3s6-1.5 6-3v-4.5M22 9v6\"/>", "clipboard": "<rect x=\"6\" y=\"4\" width=\"12\" height=\"17\" rx=\"2\"/><path d=\"M9 4h6v3H9zM9 12h6M9 16h4\"/>", "mic": "<rect x=\"9\" y=\"3\" width=\"6\" height=\"11\" rx=\"3\"/><path d=\"M5 11a7 7 0 0 0 14 0M12 18v3\"/>", "book": "<path d=\"M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z\"/><path d=\"M4 21V5\"/>", "scale": "<path d=\"M12 3v18M6 21h12M5 7h14\"/><path d=\"m5 7-3 7a3 3 0 0 0 6 0zM19 7l-3 7a3 3 0 0 0 6 0z\"/>", "award": "<circle cx=\"12\" cy=\"9\" r=\"6\"/><path d=\"m8.5 14 -1.5 7 5-3 5 3-1.5-7\"/>", "target": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><circle cx=\"12\" cy=\"12\" r=\"5\"/><circle cx=\"12\" cy=\"12\" r=\"1\"/>", "layers": "<path d=\"m12 3 9 5-9 5-9-5z\"/><path d=\"m3 13 9 5 9-5\"/>"};
  var DATA = window.UnillanosData || { institucional: { factores: [], adendas: [] }, sedes: {}, programa: { nacional: [], internacional: [] }, programas: [] };
  var PLAZOS = { corto: "Corto plazo", mediano: "Mediano plazo", largo: "Largo plazo" };
  var state = { sede: "villavicencio" };

  function icon(name, cls) {
    return '<svg class="ua-icon' + (cls ? " " + cls : "") + '" viewBox="0 0 24 24" aria-hidden="true">' + (ICONS[name] || "") + "</svg>";
  }
  function esc(s) {
    return String(s).replace(/[&<>"]/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]; });
  }
  function kw(s) { return esc(s).replace(/\*([^*]+)\*/g, '<strong class="ua-kw">$1</strong>'); }
  function num(n) { return typeof n === "number" ? (n < 10 ? "0" + n : String(n)) : n; }
  function dec(n) { return n.toFixed(1).replace(".", ","); }
  function round1(x) { return Math.round(x * 10) / 10; }
  function each(root, sel, fn) { Array.prototype.forEach.call(root.querySelectorAll(sel), fn); }
  function extend(a, b) { for (var k in b) if (Object.prototype.hasOwnProperty.call(b, k)) a[k] = b[k]; return a; }

  /* Grado de cumplimiento (se calcula, no se guarda): escala 0,0 a 5,0 */
  function gradoFor(s) {
    if (s >= 4.5) return "Se cumple plenamente";
    if (s >= 4.0) return "Se cumple en alto grado";
    if (s >= 3.5) return "Se cumple aceptablemente";
    if (s >= 3.0) return "Se cumple insatisfactoriamente";
    return "No se cumple";
  }
  function withGrado(f) { return extend(extend({}, f), { grado: gradoFor(f.score) }); }

  /* Conjuntos de datos que alimentan las pestañas y las tarjetas */
  function itemsFor(source) {
    if (source === "institucional") {
      var delta = (DATA.sedes[state.sede] && DATA.sedes[state.sede].delta) || {};
      return DATA.institucional.factores.map(function (f) {
        var s = round1(f.score + (delta[f.key] || 0));
        return extend(extend({}, f), { score: s, grado: gradoFor(s) });
      }).concat(DATA.institucional.adendas);
    }
    if (source === "programa-nacional") return DATA.programa.nacional.map(withGrado);
    if (source === "programa-internacional") return DATA.programa.internacional.map(withGrado);
    return [];
  }
  function groupOf(source, it) {
    if (source === "institucional") return typeof it.n === "number" ? "Factores" : "Adendas";
    return source === "programa-internacional" ? "Dimensiones" : "Factores";
  }
  function findIn(items, key) {
    for (var i = 0; i < items.length; i++) if (items[i].key === key) return items[i];
    return items[0];
  }

  /* ---------- Piezas de interfaz ---------- */
  function meter(item, large) {
    var pct = Math.round((item.score / 5) * 100);
    return '<div class="ua-meter' + (large ? " ua-meter--lg" : "") + '">' +
      '<div class="ua-meter__row"><span>Grado de cumplimiento</span><span class="ua-meter__value">' + dec(item.score) + ' <span class="ua-meta">/ 5</span></span></div>' +
      '<div class="ua-meter__track" role="meter" aria-valuemin="0" aria-valuemax="5" aria-valuenow="' + item.score + '" aria-label="Grado de cumplimiento ' + dec(item.score) + ' de 5"><div class="ua-meter__fill" style="width:' + pct + '%"></div></div>' +
      '<span class="ua-meter__grade">' + esc(item.grado) + "</span></div>";
  }

  function placeholder(label, size, cls) {
    return '<div class="ua-ph ' + (cls || "") + '" role="img" aria-label="Espacio para imagen: ' + esc(label) + '">' + icon("image", "ua-icon--lg") +
      '<span class="ua-ph__label">' + esc(label) + '</span><span class="ua-ph__size">' + esc(size) + "</span></div>";
  }

  function legend() {
    return '<div class="ua-legend">' +
      '<span><span class="ua-plazo ua-plazo--corto">Corto</span> 1 a 6 meses</span>' +
      '<span><span class="ua-plazo ua-plazo--mediano">Mediano</span> 7 a 12 meses</span>' +
      '<span><span class="ua-plazo ua-plazo--largo">Largo</span> 13 a 18 meses</span></div>';
  }

  /* rows: [situación, acción, [responsables], plazo, etiqueta opcional del factor] */
  function planTable(rows, pageSize) {
    var body = rows.map(function (r, i) {
      return '<tr data-row="' + i + '">' +
        '<td data-label="Situación para intervenir">' + (r[4] ? '<span class="ua-plan__tag">' + esc(r[4]) + "</span>" : "") + esc(r[0]) + "</td>" +
        '<td data-label="Acción de mejora">' + esc(r[1]) + "</td>" +
        '<td data-label="Responsables"><ul>' + r[2].map(function (x) { return "<li>" + esc(x) + "</li>"; }).join("") + "</ul></td>" +
        '<td data-label="Plazo"><span class="ua-plazo ua-plazo--' + r[3] + '">' + PLAZOS[r[3]] + "</span></td></tr>";
    }).join("");
    return '<div class="ua-plan" data-ua-plan data-page-size="' + (pageSize || 3) + '">' +
      '<table><caption class="ua-sr">Acciones del plan de mejoramiento</caption><thead><tr><th scope="col">Situación para intervenir</th><th scope="col">Acción de mejora</th><th scope="col">Responsables</th><th scope="col">Plazo</th></tr></thead><tbody>' + body + "</tbody></table>" +
      '<div class="ua-plan__nav"><span class="ua-plan__count" aria-live="polite"></span><div class="ua-plan__buttons">' +
      '<button type="button" class="ua-btn ua-btn--secundario ua-btn--sm" data-dir="-1">' + icon("chevron-left", "ua-icon--sm") + "Anterior</button>" +
      '<button type="button" class="ua-btn ua-btn--secundario ua-btn--sm" data-dir="1">Siguiente' + icon("chevron-right", "ua-icon--sm") + "</button></div></div></div>";
  }

  function panelHTML(item) {
    var isAdenda = typeof item.score !== "number";
    var label = item.label;
    var strengths = item.strengths.map(function (s, i) {
      return '<article class="ua-strength">' + placeholder("Fotografía " + (i + 1), "800 × 600") +
        '<div class="ua-strength__body"><span class="ua-strength__icon">' + icon("check", "ua-icon--sm") + '</span><p class="ua-strength__text">' + esc(s) + "</p></div></article>";
    }).join("");
    return '<div class="ua-panel__banner">' + placeholder("Banner de " + label.toLowerCase(), "1600 × 530 px") + "</div>" +
      '<div class="ua-panel__body">' +
      '<div class="ua-panel__head"><div><span class="ua-eyebrow">' + esc(label) + '</span><h3 class="ua-h3">' + esc(item.name) + "</h3></div>" + (isAdenda ? "" : meter(item, true)) + "</div>" +
      '<p class="ua-panel__desc">' + kw(item.desc) + "</p>" +
      '<section aria-label="Fortalezas destacadas"><div class="ua-panel__block-title"><h4 class="ua-h4">Fortalezas destacadas</h4></div><div class="ua-strengths">' + strengths + "</div></section>" +
      '<section aria-label="Plan de mejoramiento"><div class="ua-panel__block-title"><h4 class="ua-h4">Plan de mejoramiento</h4></div>' +
      '<a class="ua-planlink" href="#plan" data-ua-plan-link="' + esc(item.key) + '">' + icon("layers") +
      '<span class="ua-planlink__text"><strong>' + item.plan.length + (item.plan.length === 1 ? " acción de mejora" : " acciones de mejora") + '</strong><span>formuladas para ' + (isAdenda ? "esta adenda" : "este " + (label.indexOf("Dimensión") === 0 ? "dimensión" : "factor")) + "</span></span>" +
      '<span class="ua-planlink__go">Ver en el plan de mejoramiento' + icon("arrow-right", "ua-icon--sm") + "</span></a></section>" +
      '<div class="ua-panel__foot"><span class="ua-meta">' + icon("file", "ua-icon--sm") + " Informe de " + esc(label.toLowerCase()) + " · PDF · 2,4 MB</span>" +
      '<a class="ua-btn ua-btn--primario" href="#">' + icon("download") + "Ver informe completo · " + esc(label) + "</a></div></div>";
  }

  function factorCardHTML(item) {
    return '<a class="ua-factor-card" href="#detalle" data-ua-goto="' + item.key + '"><div class="ua-factor-card__media">' +
      placeholder("Imagen del factor", "800 × 400 px") + '<span class="ua-factor-card__num">' + num(item.n) + "</span></div>" +
      '<div class="ua-factor-card__body"><h3 class="ua-factor-card__title">' + esc(item.name) + "</h3>" + meter(item, false) +
      '<span class="ua-factor-card__foot">Ver ' + (item.label.indexOf("Dimensión") === 0 ? "dimensión" : "factor") + icon("arrow-right", "ua-icon--sm") + "</span></div></a>";
  }

  function adendaHTML(item) {
    return '<a class="ua-adenda" href="#detalle" data-ua-goto="' + item.key + '"><span class="ua-adenda__tag"><small>ADENDA</small>' + esc(item.n.slice(1)) + "</span>" +
      '<span><span class="ua-adenda__title">' + esc(item.name) + '</span><span class="ua-meta" style="display:block">Informe complementario</span></span></a>';
  }

  /* ---------- Plan de mejoramiento ---------- */
  function initPlan(plan) {
    if (plan.dataset.ready) return;
    plan.dataset.ready = "1";
    var size = parseInt(plan.getAttribute("data-page-size"), 10) || 3;
    var rows = plan.querySelectorAll("tbody tr");
    var count = plan.querySelector(".ua-plan__count");
    var prev = plan.querySelector('[data-dir="-1"]');
    var next = plan.querySelector('[data-dir="1"]');
    var page = 0, pages = Math.max(1, Math.ceil(rows.length / size));
    function draw() {
      for (var i = 0; i < rows.length; i++) rows[i].hidden = Math.floor(i / size) !== page;
      var from = page * size + 1, to = Math.min(rows.length, from + size - 1);
      count.innerHTML = "Acciones <strong>" + from + "–" + to + "</strong> de <strong>" + rows.length + "</strong>";
      prev.disabled = page === 0;
      next.disabled = page >= pages - 1;
    }
    prev.addEventListener("click", function () { if (page > 0) { page--; draw(); } });
    next.addEventListener("click", function () { if (page < pages - 1) { page++; draw(); } });
    draw();
  }

  /* Plan de mejoramiento completo (institucional o de programa): KPIs + filtros + tabla */
  function planSource(src) {
    return src === "institucional" ? DATA.institucional.factores.concat(DATA.institucional.adendas) : DATA.programa.nacional.concat(DATA.programa.internacional);
  }
  function initPlanAll(el) {
    if (el.dataset.ready) return;
    el.dataset.ready = "1";
    var items = planSource(el.getAttribute("data-plan-source"));
    var optsK = '<option value="">Todos los ' + (el.getAttribute("data-plan-source") === "institucional" ? "factores" : "factores y dimensiones") + "</option>" +
      items.map(function (it) { return '<option value="' + esc(it.key) + '">' + esc((it.tag || it.n) + " · " + it.name) + "</option>"; }).join("");
    el.innerHTML = '<div class="ua-planall-filter">' +
      '<label class="ua-selectbar"><span class="ua-planall-filter__lab">Factor</span><select class="ua-select" data-f="key">' + optsK + "</select></label>" +
      '<label class="ua-selectbar"><span class="ua-planall-filter__lab">Plazo</span><select class="ua-select" data-f="plazo"><option value="">Todos los plazos</option><option value="corto">Corto plazo</option><option value="mediano">Mediano plazo</option><option value="largo">Largo plazo</option></select></label>' +
      '<button type="button" class="ua-btn ua-btn--texto" data-f-clear hidden>Quitar filtros</button></div><div data-out></div>';
    var selK = el.querySelector('[data-f="key"]'), selP = el.querySelector('[data-f="plazo"]'), clear = el.querySelector("[data-f-clear]"), out = el.querySelector("[data-out]");
    function draw() {
      var rows = [], counts = { corto: 0, mediano: 0, largo: 0 };
      items.forEach(function (f) {
        if (selK.value && f.key !== selK.value) return;
        f.plan.forEach(function (r) {
          if (selP.value && r[3] !== selP.value) return;
          rows.push([r[0], r[1], r[2], r[3], f.tag || f.n]); counts[r[3]]++;
        });
      });
      clear.hidden = !(selK.value || selP.value);
      out.innerHTML = '<ul class="ua-kpis">' +
        '<li class="ua-kpi"><span class="ua-kpi__value" data-count>' + rows.length + '</span><span class="ua-kpi__label">Acciones de mejora</span></li>' +
        '<li class="ua-kpi"><span class="ua-kpi__value" data-count>' + counts.corto + '</span><span class="ua-kpi__label"><span class="ua-plazo ua-plazo--corto">Corto</span> plazo</span></li>' +
        '<li class="ua-kpi"><span class="ua-kpi__value" data-count>' + counts.mediano + '</span><span class="ua-kpi__label"><span class="ua-plazo ua-plazo--mediano">Mediano</span> plazo</span></li>' +
        '<li class="ua-kpi"><span class="ua-kpi__value" data-count>' + counts.largo + '</span><span class="ua-kpi__label"><span class="ua-plazo ua-plazo--largo">Largo</span> plazo</span></li></ul>' +
        (rows.length ? '<div class="ua-panel__block-title"><h3 class="ua-h4">Acciones del plan</h3>' + legend() + "</div>" + planTable(rows, 6)
                     : '<p class="ua-prog-empty">No hay acciones con esos filtros.</p>');
      each(out, "[data-ua-plan]", initPlan);
      flourish(out, ".ua-kpi, .ua-plan, .ua-panel__block-title");
    }
    [selK, selP].forEach(function (x) { x.addEventListener("change", draw); });
    clear.addEventListener("click", function () { selK.value = ""; selP.value = ""; draw(); });
    el._ua = { setKey: function (k) { selK.value = k || ""; selP.value = ""; draw(); } };
    draw();
  }

  /* ---------- Movimiento: entrada de paneles, cascada y conteo ---------- */
  var REDUCED = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var RISE = ".ua-panel-intro, .ua-section__head, .ua-concept > *, .ua-cond, .ua-kpi, .ua-factor-card, .ua-adenda, .ua-doc, .ua-ficha, .ua-stat, .ua-tool, .ua-plan, .ua-planlink, .ua-strength, .ua-planall-filter, .ua-cta-row, .ua-tabs";
  function flourish(el, sel) {
    if (REDUCED) return;
    var list = [], n = 0;
    each(el, sel || RISE, function (x) { list.push(x); x.classList.remove("ua-rise"); });
    void el.offsetWidth;
    list.forEach(function (x) { x.style.setProperty("--i", Math.min(n++, 12)); x.classList.add("ua-rise"); });
    each(el, "[data-count]", function (c) {
      var to = parseInt(c.textContent, 10);
      if (isNaN(to)) return;
      var t0 = null;
      function step(t) {
        if (t0 === null) t0 = t;
        var k = Math.min(1, (t - t0) / 800);
        c.textContent = Math.round(to * (1 - Math.pow(1 - k, 3)));
        if (k < 1) requestAnimationFrame(step);
      }
      c.textContent = "0";
      requestAnimationFrame(step);
    });
  }
  function enter(pane, dir) {
    if (REDUCED) return;
    pane.classList.remove("is-in-next", "is-in-prev", "is-in-fade");
    void pane.offsetWidth;
    pane.classList.add(dir > 0 ? "is-in-next" : dir < 0 ? "is-in-prev" : "is-in-fade");
    flourish(pane);
  }

  /* ---------- Pestañas de factores ---------- */
  function buildTabs(tabs) {
    var source = tabs.getAttribute("data-source");
    var items = itemsFor(source);
    var html = "", group = "";
    items.forEach(function (it) {
      var g = groupOf(source, it);
      if (g !== group) { group = g; html += '<div class="ua-tabs__group">' + g + "</div>"; }
      html += '<button class="ua-tab" role="tab" id="' + tabs.id + "-" + it.key + '" data-key="' + it.key + '" aria-selected="false" aria-controls="' + tabs.id + '-panel"><span class="ua-tab__num">' + esc(num(it.n)) + '</span><span class="ua-tab__label">' + esc(it.name) + "</span></button>";
    });
    tabs.innerHTML = '<div class="ua-tabs__list" role="tablist" aria-label="' + esc(tabs.getAttribute("data-label") || "Factores") + '" aria-orientation="vertical">' + html +
      '</div><div class="ua-panel" role="tabpanel" id="' + tabs.id + '-panel" tabindex="0"></div>';
  }

  function selectTab(tabs, key, focus, animate) {
    var items = itemsFor(tabs.getAttribute("data-source"));
    var list = tabs.querySelectorAll('[role="tab"]');
    var panel = tabs.querySelector('[role="tabpanel"]');
    var tl = tabs.querySelector(".ua-tabs__list");
    for (var i = 0; i < list.length; i++) {
      var on = list[i].getAttribute("data-key") === key;
      list[i].setAttribute("aria-selected", on ? "true" : "false");
      list[i].tabIndex = on ? 0 : -1;
      if (on) {
        panel.setAttribute("aria-labelledby", list[i].id);
        if (focus) list[i].focus();
        if (list[i].scrollIntoView && tl.scrollWidth > tl.clientWidth) list[i].scrollIntoView({ block: "nearest", inline: "center" });
      }
    }
    var prevKey = tabs.dataset.current;
    tabs.dataset.current = key;
    panel.innerHTML = panelHTML(findIn(items, key));
    each(panel, "[data-ua-plan]", initPlan);
    if (animate) {
      var ks = items.map(function (x) { return x.key; });
      enter(panel, ks.indexOf(key) - ks.indexOf(prevKey) || 1);
    }
  }

  function initTabs(tabs) {
    if (tabs.dataset.ready) return;
    tabs.dataset.ready = "1";
    buildTabs(tabs);
    var list = Array.prototype.slice.call(tabs.querySelectorAll('[role="tab"]'));
    list.forEach(function (t, idx) {
      t.addEventListener("click", function () { selectTab(tabs, t.getAttribute("data-key"), false, true); });
      t.addEventListener("keydown", function (e) {
        var d = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[e.key];
        if (e.key === "Home") d = -idx;
        if (e.key === "End") d = list.length - 1 - idx;
        if (d === undefined) return;
        e.preventDefault();
        selectTab(tabs, list[(idx + d + list.length) % list.length].getAttribute("data-key"), true, true);
      });
    });
    selectTab(tabs, tabs.getAttribute("data-initial") || list[0].getAttribute("data-key"));
  }

  function renderGrids(root) {
    each(root, "[data-ua-factor-grid]", function (g) {
      g.innerHTML = itemsFor(g.getAttribute("data-source")).filter(function (it) { return typeof it.score === "number"; }).map(factorCardHTML).join("");
    });
    each(root, "[data-ua-adendas]", function (g) {
      g.innerHTML = itemsFor(g.getAttribute("data-source")).filter(function (it) { return typeof it.score !== "number"; }).map(adendaHTML).join("");
    });
  }

  /* ---------- Selector de sede (autoevaluación institucional) ---------- */
  function setSede(root, sede) {
    if (!DATA.sedes[sede]) return;
    state.sede = sede;
    var s = DATA.sedes[sede];
    each(root, "[data-ua-sede]", function (b) { b.setAttribute("aria-pressed", b.getAttribute("data-ua-sede") === sede ? "true" : "false"); });
    each(root, "[data-ua-sede-name]", function (el) { el.textContent = s.nombre; });
    each(root, "[data-ua-stat]", function (el) { el.textContent = s.stats[el.getAttribute("data-ua-stat")]; });
    renderGrids(root);
    each(root, '[data-ua-tabs][data-source="institucional"]', function (tabs) {
      if (tabs.dataset.ready) selectTab(tabs, tabs.dataset.current || tabs.getAttribute("data-initial"));
    });
  }

  /* ---------- Pestañas de página (Institucional · Programa · Documentos) ----------
     data-routes="ruta=vista[/sub];…" define las rutas (hash). La primera es la de inicio. */
  function initViews(box) {
    if (box.dataset.ready) return;
    box.dataset.ready = "1";
    var routes = {}, first = null;
    (box.getAttribute("data-routes") || "").split(";").forEach(function (s) {
      var p = s.split("="), v = p[1].split("/");
      routes[p[0]] = { view: v[0], sub: v[1] };
      if (!first) first = p[0];
    });
    var views = [], subs = [];
    each(box, "[data-view]", function (b) { views.push(b.getAttribute("data-view")); });
    each(box, "[data-sub]", function (b) { subs.push(b.getAttribute("data-sub")); });
    var bar = box.querySelector(".ua-viewtabs"), ink = box.querySelector(".ua-viewtabs__ink");
    var curView = null, curSub = subs[0] || null, curRoute = null;

    function moveInk() {
      var on = box.querySelector('[data-view][aria-selected="true"]');
      if (!on || !ink) return;
      ink.style.setProperty("--x", on.offsetLeft + "px");
      ink.style.setProperty("--w", on.offsetWidth + "px");
      bar.classList.add("ua-viewtabs--ink");
      if (bar.scrollWidth > bar.clientWidth && on.scrollIntoView) on.scrollIntoView({ block: "nearest", inline: "center" });
    }
    function apply(route, updateHash, animate) {
      var r = routes[route];
      if (!r) return;
      var prevView = curView, prevSub = curSub;
      if (r.sub) curSub = r.sub;
      curView = r.view;
      curRoute = route;
      each(box, "[data-view]", function (b) {
        var on = b.getAttribute("data-view") === r.view;
        b.setAttribute("aria-selected", on ? "true" : "false");
        b.tabIndex = on ? 0 : -1;
      });
      each(box, "[data-view-panel]", function (p) {
        var show = p.getAttribute("data-view-panel") === r.view;
        var was = !p.hidden;
        p.hidden = !show;
        if (show && !was && animate) enter(p, views.indexOf(r.view) - views.indexOf(prevView) || 1);
      });
      each(box, "[data-sub]", function (b) { b.setAttribute("aria-pressed", b.getAttribute("data-sub") === curSub ? "true" : "false"); });
      each(box, "[data-sub-panel]", function (p) {
        var show = p.getAttribute("data-sub-panel") === curSub;
        var was = !p.hidden;
        p.hidden = !show;
        if (show && !was && animate && prevView === r.view) enter(p, subs.indexOf(curSub) - subs.indexOf(prevSub) || 1);
      });
      moveInk();
      if (updateHash && window.history && history.replaceState) history.replaceState(null, "", "#" + route);
      if (animate) {
        var top = box.getBoundingClientRect().top;
        if (top < 0) window.scrollTo({ top: window.pageYOffset + top, behavior: REDUCED ? "auto" : "smooth" });
      }
    }
    function routeFor(view) {
      var found = null;
      Object.keys(routes).forEach(function (k) {
        if (routes[k].view !== view) return;
        if (!found || (routes[k].sub && routes[k].sub === curSub)) found = k;
      });
      return found;
    }
    function goView(view) { apply(routeFor(view), true, true); }
    each(box, "[data-view]", function (b) {
      b.addEventListener("click", function () { goView(b.getAttribute("data-view")); });
      b.addEventListener("keydown", function (e) {
        var i = views.indexOf(b.getAttribute("data-view")), d = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
        if (!d) return;
        e.preventDefault();
        var nb = box.querySelector('[data-view="' + views[(i + d + views.length) % views.length] + '"]');
        nb.focus();
        goView(nb.getAttribute("data-view"));
      });
    });
    each(box, "[data-sub]", function (b) {
      b.addEventListener("click", function () { apply(routeFor("autoevaluacion") && Object.keys(routes).filter(function (k) { return routes[k].sub === b.getAttribute("data-sub"); })[0], true, true); });
    });
    each(document, "[data-ua-view-go]", function (a) {
      a.addEventListener("click", function (e) { e.preventDefault(); goView(a.getAttribute("data-ua-view-go")); });
    });
    window.addEventListener("hashchange", function () {
      var h = (location.hash || "").replace("#", "");
      if (routes[h] && h !== curRoute) apply(h, false, true);
    });
    window.addEventListener("resize", moveInk);
    if (window.ResizeObserver && bar) new ResizeObserver(moveInk).observe(bar);
    box._ua = {
      goView: goView,
      goPlan: function (key) {
        var pa = box.querySelector("[data-ua-plan-all]");
        if (pa) { initPlanAll(pa); pa._ua.setKey(key); }
        goView("plan");
      }
    };
    var h0 = (location.hash || "").replace("#", "");
    apply(routes[h0] ? h0 : first, false, false);
    if (routes[h0]) setTimeout(function () { window.scrollTo(0, box.getBoundingClientRect().top + window.pageYOffset); }, 60);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(moveInk);
    window.addEventListener("load", moveInk);
  }

  function norm(s) { return String(s).toLowerCase().normalize ? String(s).toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "") : String(s).toLowerCase(); }

  function initProgramas(box) {
    if (box.dataset.ready) return;
    box.dataset.ready = "1";
    var q = box.querySelector("[data-ua-prog-q]");
    var fac = box.querySelector("[data-ua-prog-facultad]");
    var sed = box.querySelector("[data-ua-prog-sede]");
    var list = box.querySelector("[data-ua-prog-list]");
    var count = box.querySelector("[data-ua-prog-count]");
    var params = (location.search.match(/[?&]q=([^&]*)/) || [])[1];
    if (params) q.value = decodeURIComponent(params.replace(/\+/g, " "));

    var facs = {};
    DATA.programas.forEach(function (p) { facs[p.facultad] = 1; });
    Object.keys(facs).sort().forEach(function (f) { fac.innerHTML += '<option value="' + esc(f) + '">' + esc(f) + "</option>"; });

    function draw() {
      var term = norm(q.value.trim());
      var found = DATA.programas.filter(function (p) {
        return (!term || norm(p.nombre + " " + p.facultad).indexOf(term) > -1) && (!fac.value || p.facultad === fac.value) && (!sed.value || p.sede === sed.value);
      });
      count.innerHTML = "<strong>" + found.length + "</strong> " + (found.length === 1 ? "programa" : "programas");
      list.innerHTML = found.length ? found.map(function (p) {
        return '<li><a class="ua-prog" href="programa-ejemplo.html">' +
          '<span class="ua-prog__icon">' + icon("cap") + "</span>" +
          '<span class="ua-prog__body"><span class="ua-prog__faculty">Facultad de ' + esc(p.facultad) + "</span>" +
          '<span class="ua-prog__name">' + esc(p.nombre) + "</span>" +
          '<span class="ua-prog__meta"><span class="ua-tag">' + esc(p.sede) + '</span><span class="ua-tag">' + esc(p.nivel) + "</span>" + (p.ejemplo ? '<span class="ua-tag ua-tag--acento">Ejemplo completo</span>' : "") + "</span>" +
          '<span class="ua-prog__cta">Ver programa' + icon("arrow-right", "ua-icon--sm") + "</span></span></a></li>";
      }).join("") : '<li class="ua-prog-empty">No encontramos programas con esos criterios. Prueba con otro nombre o quita los filtros.</li>';
      flourish(list, ".ua-prog");
    }
    [q, fac, sed].forEach(function (el) { el.addEventListener("input", draw); el.addEventListener("change", draw); });
    draw();
  }

  /* Formularios que llevan al buscador de programas (menú y ficha del programa) */
  function initProgSearch(form) {
    if (form.dataset.ready) return;
    form.dataset.ready = "1";
    var input = form.querySelector("input[type=search]");
    var dl = form.querySelector("datalist");
    if (dl) dl.innerHTML = DATA.programas.map(function (p) { return '<option value="' + esc(p.nombre) + '"></option>'; }).join("");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      location.href = "programas.html" + (input.value.trim() ? "?q=" + encodeURIComponent(input.value.trim()) : "");
    });
  }

  /* ---------- Comportamientos generales ---------- */
  function attach(context) {
    var root = context || document;
    each(root, "[data-ua-menu-toggle]", function (btn) {
      btn.addEventListener("click", function () {
        var nav = document.getElementById(btn.getAttribute("aria-controls"));
        var open = btn.getAttribute("aria-expanded") !== "true";
        btn.setAttribute("aria-expanded", open ? "true" : "false");
        btn.innerHTML = icon(open ? "close" : "menu") + '<span class="ua-sr">' + (open ? "Cerrar" : "Abrir") + " menú</span>";
        if (nav) nav.classList.toggle("is-open", open);
      });
    });
    each(root, "[data-ua-subtoggle]", function (btn) {
      if (btn.dataset.ready) return;
      btn.dataset.ready = "1";
      btn.addEventListener("click", function () {
        var item = btn.closest(".ua-nav__item");
        var open = !item.classList.contains("is-open");
        closeMenus(document);
        item.classList.toggle("is-open", open);
        btn.setAttribute("aria-expanded", open ? "true" : "false");
      });
    });
    if (!window.__uaMenusReady) {
      window.__uaMenusReady = true;
      document.addEventListener("keydown", function (e) {
        if (e.key !== "Escape") return;
        var open = document.querySelector(".ua-nav__item.is-open [data-ua-subtoggle]");
        closeMenus(document);
        if (open) open.focus();
      });
      document.addEventListener("click", function (e) {
        if (!e.target.closest(".ua-nav")) closeMenus(document);
        var pl = e.target.closest("[data-ua-plan-link]");
        if (pl) {
          var vb = document.querySelector("[data-ua-views]");
          if (vb && vb._ua) { e.preventDefault(); vb._ua.goPlan(pl.getAttribute("data-ua-plan-link")); }
          return;
        }
        var go = e.target.closest("[data-ua-goto]");
        if (go) {
          var scope = go.closest("[data-source]");
          var tabs = document.querySelector('[data-ua-tabs][data-source="' + (scope ? scope.getAttribute("data-source") : "institucional") + '"]');
          if (!tabs) return;
          e.preventDefault();
          selectTab(tabs, go.getAttribute("data-ua-goto"), false, true);
          tabs.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });
    }
    renderGrids(root);
    each(root, "[data-ua-tabs]", initTabs);
    each(root, "[data-ua-plan]", initPlan);
    each(root, "[data-ua-sede]", function (b) {
      if (b.dataset.ready) return;
      b.dataset.ready = "1";
      b.addEventListener("click", function () { setSede(document, b.getAttribute("data-ua-sede")); });
    });
    each(root, "[data-ua-plan-all]", initPlanAll);
    each(root, "[data-ua-views]", initViews);
    each(root, "[data-ua-programas]", initProgramas);
    each(root, "[data-ua-prog-search]", initProgSearch);
    each(root, "[data-ua-gallery]", function (g) {
      var track = g.querySelector(".ua-gallery__track");
      each(g, "[data-dir]", function (b) {
        b.addEventListener("click", function () {
          track.scrollBy({ left: parseInt(b.getAttribute("data-dir"), 10) * track.clientWidth * 0.8, behavior: "smooth" });
        });
      });
    });
    each(root, "[data-ua-form]", function (f) {
      var ta = f.querySelector("textarea[maxlength]");
      var counter = f.querySelector("[data-ua-count]");
      if (ta && counter) ta.addEventListener("input", function () { counter.textContent = ta.value.length + " / " + ta.getAttribute("maxlength"); });
      f.addEventListener("submit", function (e) {
        e.preventDefault();
        var ok = f.querySelector("[data-ua-ok]");
        if (ok) { ok.hidden = false; ok.focus(); }
      });
    });
  }
  function closeMenus(root) {
    each(root, ".ua-nav__item.is-open", function (item) {
      item.classList.remove("is-open");
      var b = item.querySelector("[data-ua-subtoggle]");
      if (b) b.setAttribute("aria-expanded", "false");
    });
  }

  window.Unillanos = { attach: attach, icon: icon, panelHTML: panelHTML, planTable: planTable, meter: meter, datosEjemplo: DATA };
})();
