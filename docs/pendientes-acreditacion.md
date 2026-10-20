# Pendientes con Acreditación

Recoge de dónde salen las equivalencias de factores del plan de mejoramiento y la regla de destino de los hallazgos, y qué falta confirmar. Solo quedan abiertos la regla del 4 y el redondeo (sección 4).

## 1. Fuentes de factores y elementos

Los 210 elementos están en `datos/semillas/elementos_modelo.csv`:

| Lineamiento | Cantidad |
|---|---|
| CNA 2020 (Acuerdo CESU 02 de 2020) | 12 factores + 48 características |
| CESU 2025 (Acuerdo CESU 01 de 2025) | 12 factores + 51 características + 70 aspectos |
| Decreto 1330 | 17 condiciones |

Los lineamientos están en `datos/semillas/lineamientos.csv`.

**Decisión de diseño: el modelo se declara por proceso.** Cada proceso guarda en `field_lineamiento` el modelo con el que se hizo, y sus valoraciones, hallazgos y metas solo pueden usar elementos de ese modelo. Ingeniería Electrónica (PR-IE-2022) usa el Acuerdo CESU 02 de 2020; los procesos nuevos usarán el Acuerdo CESU 01 de 2025. **No hay equivalencias entre el modelo 2020 y el 2025**: los datos de cada proceso se quedan en su modelo (se retiró `equivale_a_id`).

Los nombres del CNA 2020 se contrastaron con el Acuerdo CESU 02 de 2020 (`docs/fuentes/acuerdo-cesu-002-2020.pdf`, capítulo de programas, págs. 24 a 33): 12 factores y 48 características coinciden. Solo se corrigió C45 a "Financiación del programa académico".

Documentos en el repositorio (`docs/fuentes/`):

- `informe-autoevaluacion-2022-ingenieria-electronica.pdf`: origen de `valoraciones_factores.csv` y `proceso.csv` (Tabla 5.1).
- `plan-mejoramiento-ingenieria-electronica-2024-2-2030.xlsx`: origen de `metas.csv`, `hallazgos_plan.csv` y los demás CSV del plan.
- `acuerdo-cesu-001-2025.docx` y `acuerdo-cesu-002-2020.pdf`.

No están cargados: el Decreto 1330 y el modelo CNA 2013.

## 2. Equivalencias de factores del plan: respaldadas por el informe

`datos/importacion/ingenieria-electronica/equivalencias_factores_plan.csv` (9 filas) traduce los textos de factor del Excel del plan, que usan la numeración CNA 2013, a códigos CNA 2020. **No es una equivalencia entre modelos del sistema**: solo sirve para importar el plan. El emparejamiento es por texto completo y exacto, lo hace el plugin `unillanos_equivalencia` desde `migrations/hallazgos_plan.yml`, y un destino vacío da NULL.

El informe lo confirma. La introducción (pág. 14 del PDF) dice que el proceso 2018–2022 se hizo con el Acuerdo CESU 02 de 2020 y los lineamientos 2013 se usaron en 2017. Las tablas "Balance Proceso Autoevaluación 2017 vs 2022" comparan los factores de una y otra numeración.

| Texto del plan | Código asignado | Certeza |
|---|---|---|
| Factor 9. Bienestar de la Comunidad Académica del Programa | CNA20-F09 | Mismo nombre y número en 2020 |
| Factor 9 - Impacto de los egresados en el medio | CNA20-F04 | Respaldado por el informe: Factor 9 de 2013 equivale al F04 (Balance, pág. 127) |
| Factor 5 - Visibilidad nacional e internacional | CNA20-F07 | Respaldado por el informe: Factor 5 de 2013 equivale al F07 (Balance, pág. 172) |
| Factor 10 - Recursos físicos y financieros | CNA20-F12 | Respaldado por el informe: lo físico va al F12 (pág. 225) y lo financiero al F11 (pág. 219). Las metas de laboratorios son de lo físico |
| Todos los factores | (vacío) | Decisión de diseño: meta transversal, sin elemento |

## 3. Cómo se hizo la conversión

- Archivo manual creado en el commit `3dc8377`; las notas se actualizaron con las páginas del informe.
- Si una equivalencia cambia, se reimportan las metas M14, M15 y M16 y los hallazgos H13 y H14.

## 4. Pendiente: regla del 4 y redondeo

Sin fuente documental. Es una decisión de diseño marcada como "pendiente de confirmar con Acreditación".

- Regla de destino (< 4 `plan_mejoramiento`, = 4 `plan_accion`, > 4 `ninguno`): `docs/drupal/modelo-de-contenido.md`, `docs/mer/MER.md`, `CLAUDE.md` y `docs/IMPLEMENTACION_DRUPAL.md`. Código: `unillanos_autoeval/src/Service/SugerenciaDestino.php`.
- Redondeo a un decimal: `docs/drupal/modelo-de-contenido.md` y `docs/IMPLEMENTACION_DRUPAL.md`. Código: `CalculadorGrado.php`. Resuelve los huecos de la Tabla 3.1 (4,7–4,8 y 3,9–4,0).
- El redondeo se aplica al grado, no al destino: `SugerenciaDestino` compara el valor exacto (3,96 va a `plan_mejoramiento`; 4,27 va a `ninguno`).
- `plan_excel_a_csv.py` fija `destino = plan_mejoramiento` para las oportunidades del plan, por la naturaleza del archivo, no por la regla del 4.
- `modelo-de-contenido.md` dice "Regla del informe", pero no se pudo comprobar en el PDF.

**Preguntas para Acreditación institucional / oficina de autoevaluación** (los archivos no nombran una dependencia ni una persona; hay que confirmarla):

1. Un hallazgo valorado en menos de 4 va al plan de mejoramiento, igual a 4 al plan de acción y más de 4 a ninguno. ¿Es correcto?
2. ¿Un 4,27 cuenta como "igual a 4" o como "mayor que 4"? ¿Y un 3,96?
3. Para el grado (Pleno, Alto, etc.), ¿se redondea la nota a un decimal antes de compararla con la escala?

## 5. Pendientes técnicos del modelo por proceso

No requieren a Acreditación. Quedan para fases posteriores.

- **PHPStan en scripts (baja prioridad):** `drupal/scripts/crear_modelo.php` tiene 2 errores previos de nivel 5, en las líneas ~554 y ~555 (`FieldStorageDefinitionInterface::save()` y `FieldConfigInterface::setLabel()`). Los scripts quedan fuera de la compuerta de PHPStan hasta corregirlos.
- **Webform de participación (Fase 8):** su elemento `elemento` no se filtra por el modelo del proceso.
- **Decreto 1330:** la restricción del modelo del proceso (`UnillanosModeloDelProceso`, servicio `ModeloProceso`) solo admite lineamientos de tipo `acreditacion`, así que excluye el Decreto 1330. Si se necesitan procesos de registro calificado, hay que ampliarla.
- **Catálogo CESU 2025:** comparar fila por fila `elementos_modelo.csv` (12 factores, 51 características y 70 aspectos) contra el Acuerdo CESU 01 de 2025 cuando se cree el primer proceso con ese modelo. El CNA 2020 ya se contrastó con el Acuerdo 02 de 2020.
- **Autor de los contenidos importados:** salen con autor "Anónimo (no verificado)". Decidir en la Fase 7 o la 9 si se les asigna un usuario.
- **Título de los procesos (Fase 7):** configurar el título automático. Los procesos deben tener un título corto y reconocible; el nombre largo actual ("Autoevaluación con fines de renovación de la acreditación en alta calidad 2018–2022") hace incómoda la búsqueda por autocompletado, que busca por título.

## 6. Confirmaciones registradas en la Fase 6

Solo texto; no cambian datos.

- **Sede de Ingeniería Electrónica:** la sede **Barcelona** está confirmada. Antes figuraba como deducida de la dirección "Vda. Barcelona" (Tabla 1.1, pág. 17 del informe).
- **Verificación visual contra la Tabla 5.1:** el sitio se validó contra la Tabla 5.1 del informe: valoración global **4,508**, grado **ALTO (90 %)** y los **12 factores**.

## 7. Pendientes de la Fase 6 (tema visual)

**Fase 7**

- [ ] **Obligatorio: mover el menú principal fuera de `unillanos_guia`.** Los cinco enlaces temporales (Inicio, Institucional, Programas, Documentos, Participa) se declaran en `unillanos_guia.links.menu.yml` con las URL definitivas, que dan 404 hasta que existan las páginas. `unillanos_guia` se desactiva en la Fase 9: si el menú sigue ahí, desaparece con el módulo. En la Fase 7 el menú se arma con el contenido real (enlaces de menú o módulo propio).
- [ ] Plantillas de nodos (`node--programa--full`, `node--proceso--full`) y vista del plan de mejoramiento: se movieron de la Fase 6 a la 7 porque necesitan datos reales y vistas. El SDC `plan-table` recibe `columns` y `rows` genéricos para no atar la vista del FO-GCL-20 a las cuatro columnas del prototipo.
- [ ] Pie de página: el SDC `footer` trae sus enlaces fijos; llevarlos a un menú (`footer`) cuando exista el contenido.

**Fase 9**

- [ ] **Recortar el starterkit:** eliminar del tema `unillanos` las plantillas y el CSS del starterkit que no se hayan personalizado (el tema parte de ~90 plantillas genéricas y CSS de componentes de core).
- [ ] **Desactivar `unillanos_guia`** (`/guia-estilos` es público y muestra datos inventados) antes de mostrar el sitio.
- [ ] **Desinstalar Olivero** y limpiar sus bloques (`block.block.olivero_*`), que se dejaron en la configuración al poner `unillanos` como tema por defecto.

**Notas**

- **Comparación visual (resuelto):** la variante inicial de DM Sans no tenía el eje `opsz` y los títulos grandes salían un 9–10 % más anchos que en la plantilla. Se cambió a la variante con `opsz` (sección 12); ver la medición de `hero` y `pagehead` en el informe del cambio de fuente.
- **Guía de implementación (sección 10):** en Drupal 11.4 el tema se genera con `ddev exec -d /var/www/html/drupal php vendor/bin/dr generate-theme unillanos --path themes/custom`. El comando `core/scripts/drupal generate-theme` está obsoleto y falla con la estructura de este repositorio (busca `vendor/` bajo `web/`).
- **SDC:** en 11.4 los componentes funcionan sin habilitar el módulo `sdc` (está marcado `obsolete` y el gestor de plugins vive en core). No se habilita.
- **Merge:** `fase-6-tema` **no se fusiona con `main`** hasta que exista el commit con la configuración exportada (`drush cex`) y `config:status` quede limpio.

## 8. Revisión de seguridad y accesibilidad del grupo 1: hallazgos no aplicados

La revisión (agente revisor, 15 hallazgos: 0 críticos, 6 importantes, 9 menores) se aplicó en 9 puntos. Estos 6 quedan **a propósito** sin aplicar:

- [ ] **La guía menciona rutas internas** (`plantilla/datos-ejemplo.js`) a visitantes anónimos: exposición mínima y esperada; se resuelve al desactivar `unillanos_guia` en la Fase 9.
- [ ] **`white-space: nowrap` en `estado` y `plazo`** puede desbordar con textos largos a 320 px o zoom del 400 %: es el comportamiento del prototipo y las etiquetas son cortas. Revisar si en la Fase 7 aparecen textos largos reales.
- [ ] **El foco global (`.ua :focus-visible`) fuerza `border-radius: 4px`**, y por eso un botón de 8 px «salta» al recibir foco: es el estilo del prototipo y es cosmético; el anillo de foco es visible y suficiente.
- [ ] **`.ug-frame { overflow: hidden }` puede recortar el anillo de foco** de controles pegados al borde del marco: no se nota con el relleno actual; no se pudo comprobar en navegador real.
- [ ] **Muchas `<figure>`/`<figcaption>` en la guía** hacen que los lectores de pantalla anuncien «figura» repetidamente: la semántica es válida y solo afecta a una página de desarrollo que se desactiva en la Fase 9.
- [ ] **Objetivos táctiles de 40 px** en `sm`, `texto` y los enlaces del índice: cumplen WCAG 2.1 AA (el mínimo de 44 px es AAA y el de 24 px de WCAG 2.2 AA); sin corrección obligatoria.

### Segunda revisión (tras las correcciones): pendientes

Resultado: las 11 correcciones comprobadas salieron OK, salvo el filtro de URL (PARCIAL), que se corrigió después (se bloquean `/\host`, `/<TAB>/host`, `//host`, `javascript:` y variantes; se prueba con un caso por vector). Quedan como pendientes:

- [ ] **Bloques del tema sin exportar:** los `block.block.unillanos_*` (incluido el título de página que imprime el único `<h1>`) no están en `drupal/config/sync`. En una instalación limpia `/guia-estilos` quedaría sin `<h1>`. Se resuelve con el commit de configuración exportada; comprobar además que el título quede dentro de `<main>`.
- [ ] **Rutas relativas sin barra** (`institucional`, `./x`) dejan el botón como «no disponible», sin aviso. Es un fallo seguro: la regla es que `url` empiece por `/`, `#`, `?`, `http(s)://`, `mailto:` o `tel:`. Documentarlo en `button.component.yml` o admitir rutas sin esquema si hace falta.
- [ ] **`scroll-margin-top`** en `.ug-group` y `.ug-block` si el encabezado queda fijo (`sticky`), para que los anclajes `#grupo-N` no queden tapados. Comprobar con la página base.
- [ ] **Botón `texto` desactivado:** hereda fondo gris y subrayado en `:hover`; añadir `.ua-btn--texto[disabled]` (fondo transparente, sin subrayado).
- [ ] **`url` vacío o `"0"`:** `{% if url %}` trata `"0"` como falso; usar `is not empty` si algún día llega un destino así.
- [ ] **Clases por `variant`** (`tag`, `estado`, `plazo`) sin lista blanca en Twig: no es XSS y el `enum` del `.component.yml` lo cubre cuando SDC valida. Valorar una lista blanca en Twig igual que en `section-head`.
- [ ] **Enlaces desactivados fuera del orden de tabulación:** patrón habitual y aceptable; acompañarlos de un texto que explique por qué están inactivos cuando se usen con contenido real.
- [ ] **Botón `texto` de 40 px de alto:** cumple WCAG 2.1 AA; valorar 44 px para uso táctil.

## 9. Revisión de seguridad y accesibilidad del grupo 2 (página base)

Aplicado: el botón flotante conserva su nombre accesible en móvil (el texto se oculta con la técnica `.ua-sr`, no con `display: none`), el destino del logo del encabezado pasa por el filtro de URL y la lista blanca del nivel de encabezado compara como texto. Pendiente de decisión o para otras fases:

- [x] **«Ingresar» en móvil:** resuelto; `nav` recibe `login` y lo pinta como último elemento del menú móvil (visible solo con el menú abierto por debajo de 860 px; en escritorio sigue el botón del encabezado). El buscador sigue oculto en móvil, como en el prototipo.
- [x] **Nombre del botón del menú móvil:** resuelto; nombre fijo «Menú» y solo cambian `aria-expanded` y el ícono.
- [x] **Menú móvil, Escape:** resuelto y comprobado en Edge/Chromium (Escape cierra y devuelve el foco al botón, con o sin el foco dentro del menú). Pendiente menor: reajustar `aria-expanded` si se agranda la ventana con el menú abierto.
- [ ] **Filtro de URL repetido en cinco plantillas** (`button`, `nav`, `breadcrumb`, `footer`, `fab`, más `header`): hoy son idénticos; extraerlo a un único macro o función Twig del tema en el commit de página base para evitar que se desvíen.
- [ ] **`id` del menú:** `header` (`menu_id`) y `nav` (`id`) deben coincidir a mano; en la página base se pasa el mismo valor a ambos desde un único preprocess.
- [ ] **Encabezados del pie:** usa `<h3>` sin `<h2>` previo (igual que el prototipo). Decidir en la página base: prop `heading` o un `<h2>` oculto («Información del sitio»).
- [ ] **Botón flotante:** `position: fixed` puede tapar el aviso legal y los enlaces del pie al final de la página; valorar un `padding-bottom` extra en el pie.
- [ ] **Objetivos táctiles de 20–24 px** en migas y enlaces del pie: cumplen WCAG 2.1 AA; valorar `min-height: 24px` (WCAG 2.2, criterio 2.5.8).
- [ ] **Enlace de la marca:** su nombre accesible concatena los tres `alt` y el nombre del sistema (largo, igual que el prototipo); se puede acortar con `alt=""` en los logos secundarios.
- [x] **Fidelidad con el prototipo:** aprobado: `header` y `footer` hacen opcionales el buscador, «Ingresar», el botón del menú, la columna de contacto, la descripción y los enlaces legales; un elemento intermedio de las migas sin URL se imprime como `<span>`; el título de `pagehead` se escapa.
- [x] **El campo de búsqueda recortaba su texto (también en el prototipo):** `.ua-search` medía 260 px; el campo útil era de 202 px y el marcador «Buscar factores, documentos…» mide 211,9 px en DM Sans 15 px (prototipo: 211,8 px), así que se cortaban ≈10 px sin «…» (`text-overflow: clip`). **Diferencia intencional con el prototipo:** el campo mide **280 px** (en `css/base.css`, `.ua-search`) en lugar de 260 px. Por debajo de 1080 px sigue en 200 px, como en el prototipo (allí el marcador sigue recortándose; el buscador no se muestra en la página base hasta que exista una búsqueda).
- [ ] **404 de `logo.svg`:** el bloque de marca del starterkit (`unillanos_site_branding`) pide `/themes/custom/unillanos/logo.svg`, que se eliminó del tema. Se resuelve en el commit de página base (los logos salen del componente `logos`).

## 10. Verificación en navegador real (Edge/Chromium 155, headless, 1440 y 390 px)

Hecha con `playwright-core` sobre la guía y sobre el prototipo servido en local; capturas y datos en `md_and_capture/`. Un fallo que solo apareció en el navegador: los `<svg>` no tienen la propiedad `hidden`, así que `el.hidden = …` no cambiaba el ícono del botón del menú; `header.js` usa ahora `toggleAttribute('hidden')`.

## 11. Grupo 3, tanda 3A (hero, stats, steps, concept, factor-chips, card)

Revisión de seguridad y accesibilidad (agente) y verificación en Edge/Chromium a 1440 y 390 px: sin críticos. Aplicado: el anillo de foco de la tarjeta ya no se pierde bajo la sombra del hover; el hover de la tarjeta solo actúa si tiene `href`; `steps` imprime un texto oculto («Completada» o «En curso») cuando no lleva `state_label`, y su nivel de título es configurable (`heading`); el `hero` no recorta palabras largas a 320 px (`overflow-wrap: anywhere`); espacios entre los `span` de cifra/etiqueta y número/nombre; `key` de `stats` con patrón `^[a-z0-9_-]+$`. Pendiente o decidido no aplicar:

- [ ] **Alto contraste de Windows (global):** `.ua :focus-visible` usa `outline: none` y `box-shadow`, que `forced-colors` no pinta; añadir `outline: 2px solid transparent` junto al `box-shadow` en `css/base.css` (afecta a todos los componentes; hacerlo en la página base).
- [ ] **`concept`, `aside_label`:** sin él, la tarjeta lateral es un `complementary` sin nombre. Pasarlo siempre que se use (la guía lo hace); valorar un valor por defecto.
- [ ] **`card`:** el nombre accesible del enlace incluye título, texto y llamada a la acción (largo). Igual que el prototipo; valorar `aria-labelledby` con id único si resulta verboso.
- [ ] **`stats`:** el `<section aria-label>` crea un landmark `region` extra (igual que el prototipo).
- [ ] **`hero`:** `ua-hero__actions` (acciones bajo la bajada) no se portó porque ninguna página lo usa; si hace falta, traer esa regla y su variante móvil de `plantilla/style.css`.
- [ ] **Título del `hero` a 320 px:** se queda en 34 px (el prototipo igual); cabe sin recorte, pero justo.
- Diferencias intencionales con el prototipo: sin chip «Prototipo» ni estilos en línea; `role="list"` en las listas; el `h3` de `steps` y de `card` y el título de `concept` y `hero` son configurables con lista blanca; `card` sin `href` seguro no cambia al pasar el puntero.

## 12. Ajustes posteriores a la tanda 3A

- **Diferencia intencional con el prototipo, número de factor alineado:** `.ua-factor-chip__n` lleva `min-width: 2ch` (≈ 19,7 px en DM Sans 700 de 14 px). El `font-variant-numeric: tabular-nums` del prototipo no surte efecto porque DM Sans no tiene cifras tabulares: los números medían entre 10,2 y 19,0 px y los nombres arrancaban en 10 posiciones distintas (de 20,2 a 29,0 px). Con el ancho mínimo (mayor que el par más ancho, «04» = 18,97 px) todos arrancan en la misma columna (29,7 px). Aplica a `factor-chips` y a la lista de factores del aside de `concept` (misma clase). Medido en Edge a 1440 y 390 px.
- **Peso de DM Sans: variante con el eje `opsz` frente a la actual** (paquete `@fontsource-variable/dm-sans` 5.3.0, mismo SHA-1 verificado; solo se midió, el tema no cambió):

  | Archivo | Actual (`wght`) | Con `opsz` (ejes `[opsz,wght]`) | Diferencia |
  |---|---|---|---|
  | latin | 36.932 B (36,1 KiB) | 62.724 B (61,3 KiB) | +25.792 B (+25,2 KiB, +69,8 %) |
  | latin-ext | 18.228 B (17,8 KiB) | 31.292 B (30,6 KiB) | +13.064 B (+12,8 KiB, +71,7 %) |
  | **Total** | **55.160 B (53,9 KiB)** | **94.016 B (91,8 KiB)** | **+38.856 B (+37,9 KiB, +70,4 %)** |

  El navegador solo descarga latin-ext si la página usa caracteres de ese rango, así que en español lo habitual es descargar solo `latin`: +25,2 KiB. Con `opsz` el trazo coincidiría con el de Google Fonts del prototipo (la diferencia visible hoy es mínima, ver sección 7).

## 13. Grupo 3, tanda 3B (doc-list, cond-grid, ficha, sede-card, cta-row, gallery)

Revisión de seguridad y accesibilidad (agente) y verificación en Edge/Chromium a 1440 y 390 px (con prueba de interacción y de teclado de la galería): sin críticos. Aplicado: columnas `minmax(0, 1fr)` y `overflow-wrap: anywhere` para que palabras largas (correos, códigos) no se recorten a 320 px; las descargas desactivadas anuncian «(aún no disponible)»; los botones de la galería pasan a `aria-disabled` en los extremos (sin perder el foco) y apuntan a la pista con `aria-controls`; el marcador de las figuras de la galería es decorativo (`aria-hidden`, prop nueva `decorative` en `placeholder`); `doc-list` admite la clase `ua-docs--spaced` del prototipo (prop `spaced`).

Diferencias intencionales con el prototipo:
- `doc-list`: el botón de descarga es el componente `button` (variante icono); sin url segura queda **desactivado y sin `href`** (los documentos aún no existen). Con url segura es un enlace.
- `gallery`: envoltorio `div.ua-gallery[data-ua-gallery]` dentro de la sección, pista con `role="group"`, botones con `aria-controls` y `aria-disabled` en los extremos, desplazamiento inmediato con `prefers-reduced-motion`.
- Salida condicional de `ua-doc__cat`, `ua-doc__desc`, `ua-doc__meta`, `ua-cond__text`, `ua-cond__estados` y de la ficha compacta de `sede-card` (el prototipo los imprime siempre); encabezados configurables con lista blanca; `role="list"`.

Pendiente o decidido no aplicar:
- [ ] **Semántica del estado «no disponible»:** sin url, `button` imprime `<button disabled>`; con url insegura, `<a role="link" aria-disabled="true">`. Son dos formas para el mismo estado; unificar si molesta.
- [ ] **Descargas sin explicación visible:** solo el texto oculto («aún no disponible») y el aspecto gris; valorar un «Próximamente» visible cuando la lista de documentos sea real (Fase 7).
- [ ] **Datos de contacto reales (Fase 7):** la guía solo usa datos falsos (`.invalid`, ceros); `ficha` imprime lo que reciba, así que el correo y el teléfono reales deben venir de campos del CMS.
- [ ] **`null` y `''`:** una preproceso de la Fase 7 debe omitir las claves vacías (`desc`, `cat`, `meta`, `text`, `url`) en vez de pasar `null` (SDC con validación lo rechaza). El valor `''` en el `enum` de `heading` equivale a «el valor por defecto».
- [ ] **`cond-grid`:** la sede y su estado son dos `span` seguidos sin separador (los lectores dicen «Villavicencio Cumple»); un `: ` oculto o un `<dl>` mejoraría la asociación.
- [ ] **`gallery`:** `role="group"` frente a `role="region"` (defendible); cuando existan fotografías, el `alt` de cada `<img>` debe ser corto o vacío si el pie ya la describe.
- [ ] **Relleno de `.ua-doc` a 320 px:** deja unos 124 px al texto; valorar `--espacio-4` por debajo de 560 px.
- [ ] **Limpieza de CSS copiado del prototipo:** reglas repetidas en `doc-list.css` (dos `.ua-docs` seguidas en el mismo `@media`) y en `ficha.css` (dos columnas definidas dos veces); `sede-card.css` repite `list-style` y `padding` que ya da `base.css`.

## 14. Grupo 4, tanda 4A (meter, factor-card, adenda, strength)

Revisión de seguridad y accesibilidad (agente) y verificación en Edge/Chromium a 1440 y 390 px: sin críticos. **Regla respetada:** ningún componente calcula grados ni destinos de hallazgos; solo muestran el valor y la etiqueta que reciben. Aplicado tras la revisión: `meter` sanea sus entradas (un valor no numérico, vacío, `NaN` o coma decimal se muestra como «Sin valoración» y no se pinta barra; `max` ≤ 0 o inválido usa 5; `decimals` fuera de 0–3 usa 1) y se probó con `zend.assertions=-1` (como producción, donde SDC no valida las props); `aria-label` del medidor ahora es solo el rótulo (el valor y el grado van en `aria-valuetext`) y dentro de un enlace (`in_link`) la barra se oculta a las tecnologías de apoyo, así el nombre del enlace deja de repetir el grado tres veces; el anillo de foco ya no se pierde con el puntero encima en `factor-card` y `adenda` (esquinas de 8 px); sin `url` segura pero con `goto`, el destino por defecto es `#detalle` (como el prototipo) y sin ninguno de los dos la tarjeta es un `div`, no un enlace sin `href`; `meter` tiene reglas `forced-colors`.

Diferencias intencionales con el prototipo: **el valor y la escala del medidor («4,4 / 5») no se parten** (`.ua-meter__value { flex: none; margin-left: auto; white-space: nowrap }` y `.ua-meter__row { flex-wrap: wrap }` en `meter.css`): en el prototipo, en las tarjetas de factor en móvil, el valor ocupaba dos líneas (alto 36–40 px, «4,4 /» y «5»); ahora ocupa una (18–20 px) y, si no cabe junto al rótulo, baja a su propia línea alineado a la derecha; además el medidor y la columna de texto de la tarjeta no pueden ser más anchos que la tarjeta (`min-width: 0`, `max-width: 100 %`, `grid-template-columns: minmax(0, 1fr)`) y el título de la tarjeta de factor parte palabras largas (`overflow-wrap: anywhere`, `hyphens: auto`) en lugar de desbordar. **Comprobación de recorte interno** (la tarjeta tiene `overflow: hidden`, por eso medir solo el desplazamiento de la página no lo detectaba): la caja del valor, del rótulo, de la etiqueta de grado, de la barra, del título y del pie de cada tarjeta debe quedar dentro de la caja de la tarjeta y del cuerpo (`scrollWidth ≤ clientWidth`). Antes del arreglo, a 320 px 4 de 5 tarjetas tenían el valor y la barra 3,1 px fuera (y 1 de 5 a 390 px); después, 0 de 5 a 390, 360, 340 y 320 px (y en todos los anchos probados de 1440 a 280 px); `aria-valuetext` añadido y `aria-label` reducido al rótulo; `aria-valuenow` limitado a la escala; marcadores de imagen decorativos (`aria-hidden`); el `display:block` en línea de la adenda pasó a una clase; encabezado de tarjeta configurable con lista blanca; sin `.ua-factor-grid--four` (el prototipo lo emite pero no tiene CSS); `meter` omite `ua-meter__grade` si no hay etiqueta; el único estilo en línea es el ancho de la barra (número saneado 0–100).

Pendiente:
- [ ] **Etiquetas de grado de la guía:** vienen de `datos-ejemplo.js` («Se cumple plenamente» desde 4,5) y **no siguen la Tabla 3.1** (Pleno desde 4,8). Son solo de ejemplo; el grado real lo calcula `unillanos_autoeval` y sigue pendiente de Acreditación (sección 4).
- [ ] **Estilo en línea del medidor y CSP:** si en el futuro se activa una Content-Security-Policy sin `style-src 'unsafe-inline'`, el ancho de la barra necesitará otra solución (por ejemplo, clases `ua-meter__fill--p0…p100`).
- [ ] **Imágenes reales:** `factor-card` y `strength` usan el marcador; en la Fase 7 hay que decidir cómo llegan las fotografías (un `<img>` por slot o campo de imagen) con `alt` corto o vacío si el texto ya la describe.
- [ ] **Destino de las tarjetas y adendas:** la guía apunta a `#grupo-4`; en las páginas reales `url` es el ancla de la pestaña (`#detalle`) y `goto` la clave que usará el JS de pestañas (tanda 4B).
- [ ] **`meter` con valor ausente:** en la Fase 7 las preprocesos deben omitir la clave `score` (no pasar `null`; SDC con validación lo rechaza) cuando aún no hay valoración.
- [ ] **«ADENDA» a 10 px** (fiel al prototipo; contraste 17:1); valorar 11–12 px.
- [ ] **Limpieza:** `adenda.css` tiene dos reglas `.ua-adendas` seguidas en el mismo `@media` (copia del prototipo).
- [ ] **Guía por debajo de 320 px:** a 300 y 280 px la página de la guía tiene desplazamiento horizontal (los componentes de la 4A siguen dentro de su caja); no es objetivo de reflujo (WCAG 1.4.10 pide 320 px) y queda por identificar qué sección lo causa.
- **Guía:** en pantallas de hasta 560 px el relleno de los marcos (`.ug-frame__body`) baja de 24 a 12 px para no estrechar los componentes más que una página real (contenedor de 16 px).

## 15. Grupo 4, tanda 4B (factor-tabs, planlink, plan-table, plan-filter)

Revisión de seguridad y accesibilidad (agente), prueba de interacción y de teclado en Edge/Chromium (82 comprobaciones) y comprobación de contención a 1440, 390, 360 y 320 px: sin críticos. **Regla respetada:** los componentes solo muestran, cuentan y filtran los valores que reciben; ninguno calcula grados ni destinos de hallazgos.

Aplicado tras la revisión: la página ya no salta al cargar en pantallas estrechas (la lista de pestañas se desplaza sola, sin `scrollIntoView` de la página); las flechas siguen la orientación (arriba/abajo si la lista es vertical, izquierda/derecha si es horizontal) y no se tocan las combinaciones con Alt, Ctrl, Cmd o Mayús; los paneles se buscan dentro de su componente (varios `factor-tabs` conviven aunque compartan claves) y `data-ua-goto-tabs` indica el conjunto de destino; ids de pestaña (`…-tab-clave`) distintos de los de panel (`…-panel-clave`); `initial` inválido cae en la primera pestaña; un asterisco suelto o `**` en `desc` se imprimen tal cual; el recuento de la tabla ya no es región viva (solo se anuncia al cambiar de página; al filtrar anuncia el propio filtro, una sola vez); los enlaces `data-ua-goto` y `data-ua-plan-link` no se interceptan con Ctrl, Cmd, Mayús, Alt ni botón distinto del principal, y `planlink` solo filtra si su destino es un ancla; claves inexistentes o eventos sin `detail` se ignoran; `detach` retira el aviso de cambio de tamaño; el panel enfocado conserva su radio.

Corregido por la comprobación de contención (recortes que no se veían porque el panel y las tarjetas tienen `overflow: hidden`): el panel del factor, las cifras del plan y las tarjetas de fortaleza se salían de su caja a 360 y 320 px (**el prototipo también recorta el panel a 320 px**: `scrollWidth` 309 frente a `clientWidth` 286); ahora las columnas usan `minmax(0, 1fr)`, los títulos y textos largos pueden partirse, la llamada del `planlink` puede pasar a dos líneas, las cifras llevan menos relleno lateral hasta 360 px y, por debajo de 340 px, las tarjetas de fortaleza se apilan (en fila dejaban una columna de texto de unos 50 px).

Corregido en una captura: a 390 px el encabezado de la tabla del plan estaba con `display: none` y los encabezados desaparecían del árbol de accesibilidad (0 `columnheader`); ahora se oculta solo a la vista (`position: absolute` y recorte) y la etiqueta repetida de cada celda usa `content: attr(data-label) / ""` para no leerse dos veces.

Diferencias intencionales con el prototipo: paneles ya renderizados y JS que solo alterna; `role="presentation"` en las cabeceras de grupo de la lista; tabindex itinerante; roles ARIA de tabla explícitos; nav de paginación oculta hasta que el JS la revela; `aria-disabled` en lugar de `disabled` en Anterior/Siguiente; región `role="status"` para anunciar los resultados; el enlace al plan filtra en el sitio (en lugar de cambiar de pestaña de página); cifras del plan **sin conteo animado desde 0** (no están en región viva); el estado vacío mantiene el título y la leyenda y solo oculta la tabla; el botón del informe queda desactivado (sin `href`) si no hay `report_url`; encabezados configurables con lista blanca.

Pendiente o decidido no aplicar:
- [ ] **`role="presentation"` dentro del `tablist`:** ARIA 1.2 lo tolera, pero conviene probarlo con NVDA o VoiceOver; alternativa: quitar las cabeceras del `tablist` y añadir el grupo al nombre de cada pestaña.
- [ ] **Sin JS solo se ve el primer panel:** las pestañas no funcionan sin JavaScript (el prototipo igual); valorar mostrar todos los paneles sin JS y ocultarlos desde el JS.
- [ ] **Etiquetas largas de pestaña** truncadas a 220 px con puntos suspensivos en pantallas estrechas, sin `title`; el nombre accesible es completo.
- [ ] **Robustez menor:** `flourish(null)` y `contar()` solapado si algún día se vuelve a usar `data-count`; `data-label` queda vacío si `columns` tiene menos elementos que `cells` en `plan-table`.
- [ ] **Imágenes:** los banners del panel y las fotos de las fortalezas son marcadores decorativos; en la Fase 7 hay que decidir cómo llegan las fotografías reales.
- [ ] **Informes sin URL:** el botón «Ver informe completo» queda desactivado hasta que existan los informes (Fase 7).
- [ ] **Guía a 360 px:** los marcos de la guía restan ancho, y las tarjetas de fortaleza en fila quedan con una columna de texto de ≈98 px (en una página real ≈126 px); no se cambió el punto de apilado (340 px).

## 16. Página base (page.html.twig, bloques y menú)

`page.html.twig` monta el encabezado (menú principal en el slot `nav`, «Saltar al contenido» a `#main-content`), las migas, `main`, el pie y el botón flotante. Verificada en Edge a 1440, 390 y 320 px: sin desborde, un solo `h1`, menú móvil con «Ingresar» como último elemento, Escape devuelve el foco al botón.

Bloques del tema (config exportada): se retiraron `site_branding` (apuntaba a un `logo.svg` inexistente), `account_menu` y `powered`; el título pasó a la región nueva `page_title`; el menú principal se limita a un nivel. Las migas añaden la página actual como último elemento (Drupal no la incluye).

- [ ] **Datos de contacto del pie:** el pie no imprime la columna «Contacto» porque no hay dirección, teléfono ni correo reales (el prototipo y la guía usan texto de ejemplo). Falta el dato de la Universidad.
- [ ] **Enlaces legales del pie** («Política de datos personales», «Mapa del sitio», «Accesibilidad») salen como texto sin enlace hasta que existan las páginas.
- [ ] **Pie fijo en la plantilla:** columnas y textos están en `page.html.twig`; en la Fase 7 pasan a un menú `footer` editable.
- [ ] **Portada de Drupal sin estilo:** la vista `frontpage` por defecto se reemplaza con la página de inicio (paso siguiente).
- [ ] **Ancho del contenido:** la portada va a todo el ancho; el resto de páginas, en `ua-container`. Las páginas de nodo decidirán el suyo cuando existan sus plantillas.
- [ ] **Pestañas locales y formularios de Drupal** (`/user/login`, administración) conservan el estilo del starterkit; se ajustan en la Fase 9.
- [ ] **Botón flotante sobre el pie a 320 px (igual que el prototipo):** el botón (`position: fixed`, 56 × 52 px, esquina inferior derecha) tapa parte del contenido que queda debajo mientras se desplaza la página. Con la página al final, a 320 px cubre el enlace «Mapa del sitio» del aviso legal (a 390 px no cubre nada). Medido igual en el sitio y en `plantilla/`; no se cambia. Posible arreglo (decisión de diseño): relleno inferior de unos 72 px en el pie por debajo de 860 px, o hacer que el botón no flote sobre el pie.
- **Logos del pie a 320 px:** no se recortan. Los tres logos ocupan de x = 16 a x = 281,5 en una ventana de 320 px y la fila no desborda (`scrollWidth` = `clientWidth` = 265), con las mismas cifras que el prototipo a 390, 360 y 320 px. Lo que parecía un recorte en `inicio-320.png` y `login-320.png` era el botón flotante, que en una captura de página completa queda fijo a mitad de la imagen y tapa el tercer logo.

Revisión de seguridad y accesibilidad de la página base (agente): 0 críticos, 3 importantes, 6 menores.

Aplicado: (1) el título de la última miga ya no se escapa dos veces ni se recorta con «<» (render array → `Html::decodeEntities(strip_tags(…))`; texto → tal cual); (2) el bloque de migas varía por ruta y lleva las etiquetas de caché de las entidades de la ruta, y el título renderizado propaga su metadato; (3) el menú y la página varían por `user.roles:authenticated`, para que «Ingresar» / «Mi cuenta» no se sirvan cruzados (probado con sesión real: sin cruce en ambos sentidos); (5) `{% if page.breadcrumb %}` en lugar de renderizar la región dos veces; (8) en `forced-colors` el foco usa `outline` (el `box-shadow` desaparece en alto contraste; cubre el pendiente global anterior).

Pendiente o decidido no aplicar:
- [ ] **`ua-main__top` siempre impreso:** la región `highlighted` trae siempre el contenedor de mensajes de Drupal y deja 24 px de relleno vacío (sobre todo en la portada).
- [ ] **`item.url.toString` en el menú:** funciona y el filtro del componente bloquea `javascript:` y `//host`; más robusto sería `url.toString(TRUE)`. Un enlace externo del menú se imprime sin `rel` (contenido de administración).
- [ ] **`main` sin indicador de foco** tras «Saltar al contenido»: aceptable (solo recibe foco por programa), a probar con lector de pantalla.
- [ ] **Probar con usuario autenticado real** el botón de cuenta en el encabezado (solo se verificó el menú móvil y la página por curl).

## 17. Demostración local con datos reales de Ingeniería Electrónica

Alcance (solo entorno local, prioriza el aspecto): plantillas de nodo más servicios que preparan los datos de cada página (hoy `PaginaPrograma`, `FactoresPrograma`, `PlanMejoramientoPrograma`, `DatosPortada` y `DocumentosPublicados`; nacieron de un solo servicio presentador que se dividió y retiró en la Fase 7), **sin vistas**. Pasos: 0 base común, 1 programa (cabecera y ficha), 2 programa (factores), 3 programa (plan de mejoramiento), 4 portada, 5 documentos. En pantalla no se escribe «Pendiente»: lo que falta se omite. Lo único visible es la nota discreta «regla por confirmar» junto a las etiquetas de grado. Los componentes solo muestran valores; no calculan grados, destinos ni avances.

**Paso 0 (base común):** presentador (`numero`, `etiquetaLista`, `programaPorClave`, `procesoDelPrograma`, `procesosPublicados`); portada en `/inicio` (página de inicio del sitio); menú principal con «Programa de Electrónica» y «Documentos» (movido de `unillanos_guia` a `unillanos_autoeval.links.menu.yml`, lo que cierra el pendiente obligatorio de la sección 7); botón flotante retirado de la página base hasta la Fase 8; pie con destinos reales (columna «Explora») y sin enlaces legales; Pathauto activado para nodos con el patrón `/programas/[node:field_clave]`.

- [ ] **Texto editorial de la portada** («¿Qué es la autoevaluación?», fases del proceso): se conserva el del prototipo; **pendiente de confirmar por Acreditación** (paso 4).
- [ ] **Datos de la demostración sin confirmar:** etiquetas de grado (regla de redondeo de la Tabla 3.1, huecos 4,7–4,8 y 3,9–4,0), destino de los hallazgos, avance de las metas (los 51 seguimientos están «reportado», ninguno «verificado»). Se muestran los valores guardados.
- [ ] **Menú de la demostración:** «Programa de Electrónica» enlaza por alias (`/programas/ingenieria-electronica`), que depende de Pathauto; el enlace de «Documentos» da 404 hasta el paso 5. Sustituir por «Programas» (`/programas`) en la Fase 7 y añadir Institucional y Participa (Fase 8).
- [ ] **Pie de página:** sin columna «Contacto» (faltan datos reales), sin enlaces legales (no hay páginas) y sin las columnas «Institucional» ni «Programas y documentos» del prototipo (apuntaban a páginas inexistentes); pasa a un menú `footer` editable en la Fase 7. El texto del pie aún habla de «participar».
- [ ] **Botón flotante de recomendaciones:** vuelve con la página «Participa» (Fase 8).
- [ ] **Ficha con celdas vacías:** con 5 datos en 4 columnas queda una celda gris sin contenido (la rejilla pinta el fondo); el prototipo no tiene fichas de 5 datos. Valorar `:empty`/relleno del último grupo si aparece en una página real.
- [ ] **Portada provisional (paso 0):** lista de procesos con una ficha; se reemplaza en el paso 4.

Revisión del agente (paso 0): 0 críticos, 3 importantes, 6 menores. Aplicado: la portada propaga los metadatos de caché de cada proceso y programa, del acceso `view` al programa y de su URL (`toString(TRUE)`); el programa referenciado se comprueba con `access('view')` y se carga con `loadMultiple` (sin N+1); pruebas nuevas para el programa sin publicar, sin estado y para las etiquetas de caché; la ficha pasa a una columna por debajo de 360 px (a 320 px «Autoevaluación» se partía a mitad de palabra).

- [x] **Alias del menú:** el enlace «Programa de Electrónica» depende de que Pathauto haya generado el alias. La configuración exportada ya activa Pathauto para nodos y trae el patrón; si se migra **antes** de importar la configuración hay que correr `drush pathauto:aliases-generate create canonical_entities:node`. Alternativa robusta: un enlace por `entity.node.canonical` con un deriver que busque el programa por `field_clave`. Comando documentado en `IMPLEMENTACION_DRUPAL.md` (Fase 6).
- [ ] **`/inicio` y `/` devuelven lo mismo** (sin `canonical`): redirigir o añadir `canonical` si el sitio sale del entorno local.
- [ ] **Un solo `h1` en la portada definitiva (paso 4):** hoy lo imprime el bloque `page_title`; si el `hero` trae su propio `h1`, hay que ocultar el bloque en la portada.

**Paso 1 (programa: cabecera y ficha):** plantilla `node--programa--full.html.twig` con los datos que arma `PaginaPrograma::datosPrograma()`: cabecera (`pagehead`) con migas, etiquetas de nivel y modalidad, línea «facultad · sede», título (primera palabra en negro y el resto en acento; en nombres de más de cuatro palabras, solo las dos últimas) y ficha (nivel, modalidad, sede, dirección, y SNIES, resoluciones y vigencias solo cuando existan). En la ruta canónica la página no repite el bloque de migas ni el del título (`pagina_propia`). Componente `ficha`: el último dato ocupa el resto de su fila; el menú marca «Programa de Electrónica» como página actual.

- [ ] **Ficha con 6 datos a 1440 px:** con la regla nueva la última fila de dos datos se rellena (el prototipo dejaba dos celdas grises); con 4 u 8 datos, y con cualquier cantidad par en dos columnas, es idéntica al prototipo (medido para 1 a 8 datos a 1440, 1000, 390, 340 y 320 px en `datos/ficha-sitio-vs-proto.txt`). Por debajo de 360 px la ficha pasa a una columna (el prototipo mantiene dos y parte las palabras).
- [ ] **Pestañas de página del prototipo** (Registro calificado / Autoevaluación / Plan de mejoramiento, componente `view-tabs`, grupo 5): no están construidas; el programa apila sus secciones. Decidir en el paso 2 si se construyen.
- [ ] **Buscador de programas del prototipo** (`progsearch`, grupo 5) no se muestra en la cabecera; tampoco el chip «Prototipo».
- [ ] **Datos del programa sin cargar:** código SNIES, resoluciones de registro y acreditación, vigencias, imagen y duración/créditos (no existen como campos). Se omiten de la ficha. Los otros 39 programas solo tienen nivel, facultad (y la ficha no se muestra con menos de dos datos).
- [ ] **Nombres largos:** el título repite el nombre completo en las migas y en el `h1`; a 320 px la miga ocupa dos líneas. Sin acción.
- [ ] **`:has()`** da aire superior a la región del título/mensajes/ayuda; en navegadores sin `:has` (anteriores a 2023) no hay ese aire. Degradación aceptable.
- [ ] **Previsualización y revisiones** de un programa se pintan con la plantilla normal de nodo (sin cabecera propia); las migas y el título los dan los bloques.
- [ ] **Facultad y sede** se imprimen sin comprobar acceso a los términos (vocabularios públicos); una referencia rota se omite (cubierto por prueba).

Revisión del agente (paso 1): 0 críticos, 2 importantes, 5 menores. Aplicado: la ficha solo se muestra con 2 o más datos; previsualización y revisión ya no pintan dos `h1`; `.block-help-block` en el selector `:has`; `url.path` como contexto del bloque de menú; acento limitado en nombres largos; prueba de referencia rota. Nota del proceso: el agente, que debía ser de solo lectura, modificó `UnillanosHooks.php` (`url.path` en el menú, que se conservó, y un `preprocess_page` que aceptaba previsualización y revisión, que se revirtió porque dejaba esas páginas sin `h1`).

**Paso 2 (programa: factores, valoración, fortalezas y aspectos por mejorar):** `FactoresPrograma::delPrograma()` arma, para **cualquier programa y modelo**, las tarjetas y pestañas de factores: los factores salen del modelo (lineamiento) que declara el proceso, ordenados por número (los que no tienen número, al final); solo se incluyen los que tienen valoración o hallazgos; los hallazgos sobre una característica o un aspecto (modelo 2025) se agrupan en su factor subiendo por el padre; el hallazgo sin factor va a «Sin factor asignado»; se usa el proceso más reciente que tenga factores y datos (un registro calificado más nuevo no oculta la acreditación); las claves de pestaña son únicas aunque los números falten o se repitan. La etiqueta de grado es el nombre del término guardado en `field_grado` (los términos no traen un nombre más largo: «Pleno», «Alto»…), con la nota discreta «regla por confirmar» una vez bajo las tarjetas. Componentes: `placeholder` variante `tema` (fondo propio con los colores del tema), `factor-card` y `factor-tabs` con `image: tema`, `strength` con `plain`, `factor-tabs` con `improvements` y `report: false`. La sección de la guía de estilos se actualizó con las variantes nuevas.

- [ ] **Porcentaje (`field_porcentaje`) no se muestra** (decisión de la demo): existe en los 12 factores, pero su significado no está definido en `modelo-de-contenido.md`.
- [ ] **Enlace de cada factor al plan** (`planlink`, `plan_count`) y **pie del informe** (`report_url`): el primero llega con el plan (paso 3); el segundo, con los documentos (paso 5). Por ahora no se imprimen.
- [ ] **Valoraciones de características** (modelo 2025): el presentador solo muestra las de factor; las de característica quedan sin mostrar. Decidir cómo presentarlas cuando haya un programa con ese modelo.
- [ ] **Hallazgos de otro modelo** (elemento que no llega a un factor del modelo del proceso) caen en «Sin factor asignado»; la restricción `UnillanosModeloDelProceso` lo impide al guardar.
- [ ] **Tarjetas de factor:** con títulos de distinta altura, el medidor queda a alturas distintas entre tarjetas de la misma fila (igual que el prototipo).
- [ ] **Textos fijos del presentador** («Factor NN», «Sin factor asignado») son traducibles con `t()`, pero no hay traducciones cargadas (el sitio es solo en español).
- [ ] **Guía de estilos a 320 px:** las muestras de `placeholder` usan columnas de mínimo 260 px y sobresalen unos píxeles del borde de su marco (no de la ventana); la fila de logos del grupo 2 ya desbordaba. No afecta a las páginas reales.
- [ ] **Alto contraste:** `placeholder` variante `tema` cae en un fondo del sistema con borde (el degradado y el número se pierden; es decorativo).

Revisión del agente (paso 2): 0 críticos, 2 importantes, 8 menores. Aplicado: proceso más reciente con factores y datos (antes podía ocultarlos un registro calificado más nuevo), claves de pestaña únicas, `plain_text` sin `strip_tags` (un «< 4 y > 3» ya no pierde texto), mapa de padres leído una sola vez (sin consulta por nivel), contexto `user.permissions` en el nodo, banda alterna solo si hay ficha, textos con `t()`, alto contraste del fondo del tema y 4 pruebas nuevas (modelo 2025, dos procesos, números repetidos o ausentes, texto plano). Corregido al probar: un factor sin número se ordenaba primero. El agente se limitó a leer (sin ediciones).

**Paso 3 (programa: plan de mejoramiento):** `PlanMejoramientoPrograma::delPrograma()` arma el plan para cualquier programa y modelo. Con factores usa el plan del mismo proceso; sin factores, el más reciente con metas (sin filtro por factor). Una fila por meta: situación = hallazgos enlazados (en lista si son varios), acción = descripción de la meta con «Meta {código}» y su factor, responsables de la meta y avance. El avance es siempre el del periodo más reciente (con porcentaje solo si ese periodo lo tiene; nunca el de uno anterior ni uno global) y el detalle (`<details>`) trae actividades, recursos, proyecto institucional, tipo de meta, indicadores con su programación anual y todos los periodos con su porcentaje, narrativa y medios de verificación. Una meta con hallazgos de varios factores aparece en todos; sin factor, en «Sin factor asignado». Los factores reciben `plan_count`, `plan_note` y `plan_url` para el enlace «Ver en el plan de mejoramiento»; hay enlaces «Ir al plan de mejoramiento» y «Volver a los factores» (solo si existen ambas secciones). Componentes: `plan-table` (celda con `eyebrow` y `details`, filas con `keys`), `plan-filter` (`show_plazo: false`, cifra única compacta, JS tolerante). Retoques del paso 2: título «Factores y valoración», peso 500 en fortalezas largas y aspectos por mejorar, menos aire bajo la ficha. Guía de estilos actualizada.

- [ ] **Programación anual sin unidad:** `field_unidad` está vacío en los 19 indicadores; los valores (165; 0,6; 1) se muestran tal como están guardados, sin convertir a porcentaje. Confirmar la unidad al cargarla.
- [ ] **«Logrado» vacío** en las 133 programaciones: no se muestra; aparecerá solo cuando haya dato.
- [ ] **26 de los 51 seguimientos sin porcentaje:** la celda muestra el periodo más reciente y «Reportado» sin cifra; el detalle conserva el porcentaje de los demás periodos.
- [ ] **Peso de la meta (0,0588 = 1/17)** no se muestra: no informa al visitante.
- [ ] **Plazo corto, mediano y largo:** sigue sin existir; el componente conserva su filtro de plazo (por defecto) para cuando haya datos.
- [ ] **Aviso «no está verificado»:** se muestra mientras algún seguimiento no esté en estado «verificado».
- [ ] **Primera columna** de la tabla del plan con peso 500 (el prototipo usa 600 para frases cortas); diferencia intencional por legibilidad de textos largos.
- [ ] **Guía de estilos a 320 px:** sigue midiendo 326 px por la fila de logos del grupo 2 (preexistente).

Revisión del agente (paso 3): 0 críticos, 1 importante, 6 menores (solo lectura). Aplicado: **doble escape** de textos con `&`, `<` o apóstrofes en los textos con marcadores `@` de `t()` (meta, bajada del plan, medios de verificación, unidad, línea base, programación anual): ahora se decodifican para que escape solo la plantilla (mismo defecto que las migas del paso 0; prueba nueva con `&` y apóstrofes); el resumen de cada fila lleva el contexto «(Meta N)» solo para lectores de pantalla (había 17 resúmenes idénticos); los hallazgos de una meta se exigen del mismo proceso que el plan; la programación anual se ordena por año y muestra el «logrado» aunque falte lo programado; un avance fuera de 0 a 1 no se muestra como porcentaje; 5 pruebas nuevas (54 en la suite, todas OK).

Pendiente o decidido no aplicar:
- [ ] **`procesosDelPrograma()` se consulta dos veces por render** (factores y plan); con caché de render es tolerable. Valorar pasarlo de uno a otro.
- [ ] **Las secciones `#autoevaluacion` y `#plan` no tienen nombre accesible** (`aria-labelledby`); no son hitos de página, pero se puede añadir al componente `section`.
- [ ] **Cifra compacta fuera de `[data-out]`:** no se anima al filtrar (solo estético).
- [ ] **Carga de entidades:** ~36 cargas por render para indicadores y programación (paragraphs); tolerable con caché.
- [ ] **Peso 500 de la primera columna** no depende de `show_plazo`: afecta también a la tabla de la guía con plazo (el prototipo usa 600).

> **Nota de numeración:** las secciones de la demostración local saltan de la 17 a la 19 (no existe la 18). Los pasos 1 a 3 de la demo están dentro de la sección 17; los pasos 4, 5a y 5b son las secciones 19, 20 y 21.

## 19. Demostración local: paso 4 (portada definitiva)

`DatosPortada::armar()` arma la portada (reemplaza la provisional del paso 0). Escala de 0 a 40 programas: cada programa aparece una vez, con su proceso más reciente **con datos** (el mismo que usan las cifras, los chips y la página del programa). Orden: hero → cifras → programas → concepto → fases → documentos.

- **Hero:** título «Autoevaluación Institucional» y bajada del prototipo; antetítulo dinámico («Proceso de autoevaluación {periodo}» con un programa; «Programas con autoevaluación publicada» con varios); imagen con la variante `suave` de `placeholder` (rosa claro con el patrón del tema). Se probó antes la variante `tema` (rojo pleno): junto a la tarjeta roja del hero y la banda roja de cifras pesaba demasiado, sobre todo a 390 px. Sin «Última actualización».
- **Cifras:** con un programa, factores evaluados, fortalezas, aspectos por mejorar y acciones de mejora; con dos o más, la primera pasa a «Programas con autoevaluación» y las otras tres son totales; una cifra en cero se omite. Solo metas de planes publicados.
- **Programas:** con uno, fila destacada (`cta-row`); con varios, tarjetas (`card`) hasta 12 con cada dato en su línea; «Ver todos los programas» solo si hay más de 12 y existe `/programas`.
- **Concepto y chips:** los chips son los factores del modelo del proceso más reciente con factores; **con un único programa llevan la valoración y la etiqueta guardadas** (con nota «regla por confirmar»); con varios, solo número y nombre (un valor por factor sería un promedio).
- **Fases:** las cinco del prototipo, sin estado (`steps` admite «sin estado»): no hay dato de fase.
- **Documentos:** tarjeta que aparece sola cuando exista `/documentos` (paso 5).
- **Un solo `h1`:** el del hero; la página omite el bloque de título y las migas en la portada.
- **Retoque del paso 3:** el antetítulo de la meta en `plan-table` ya no se pasa a mayúsculas («Meta 2a.2b, 2c»).
- **Pruebas de datos simulados (entorno temporal):** se crearon y se borraron procesos de prueba (prefijo «ZZ-TEMP») para ver 4 y 15 programas; se verificó que la base quedó idéntica (225 nodos, 48 alias, 152 paragraphs, 266 términos y 0 nodos de prueba). Nada de esto quedó en la base ni en git.

Textos editoriales **pendientes de confirmar por Acreditación** (se conservan los del prototipo):
- [ ] Bajada del hero («Un ejercicio participativo en el que la comunidad universitaria reconoce sus fortalezas…»).
- [ ] «¿Qué es la autoevaluación?»: párrafo principal, segundo párrafo y los tres principios (Participativa, Basada en evidencias, Orientada a la mejora).
- [ ] «El proceso»: título «Así avanza la autoevaluación», su bajada y los títulos y textos de las cinco fases.
- [ ] Título «Lo que se ha evaluado, en un solo lugar» y texto de la tarjeta «Documentos».
- [ ] **Textos adaptados por mí** (el prototipo los escribía para el ámbito institucional): el texto de «¿Qué se evalúa?» («Los factores del modelo con el que se evalúa cada programa.»), la bajada de la sección de programas y el antetítulo del hero.

Otros pendientes:
- [ ] **«Última actualización» omitida:** sería la fecha de carga de los datos, no la del informe; añadirla cuando haya fechas de publicación propias.
- [ ] **Datos que no hay y se omiten:** participantes, valoración global, galería, «Participa», tarjetas Institucional y Participa, estado de las fases (`field_fase_actual` vacío).
- [ ] **Procesos institucionales** (con sede, sin programa) no se muestran en la portada: no hay páginas para ellos.
- [ ] **Programas de modelos distintos** (2020 y 2025): los chips usan el modelo del proceso más reciente con factores; valorar un grupo por modelo.
- [ ] **Portada con 12 tarjetas a 390 px:** unos 3600 px de alto; valorar un diseño más compacto o paginar cuando exista `/programas`.
- [ ] **Procesos con histórico:** `datosPortada()` carga todos los procesos publicados con programa; con muchos años de histórico habrá que limitarlo (consulta agrupada por programa).
- [ ] **Chips y valores:** se asocian por número de factor; un factor sin número cae en la posición. Hoy no ocurre.
- [ ] **Las cifras cuentan hallazgos** con texto vacío o de factores no mostrados, que la página del programa omite (diferencia pequeña).
- [ ] **`pagina_propia` en la página de inicio:** si el inicio fuera otra ruta, omitiría el título sin traer su `h1`.
- [ ] **Guía de estilos a 320 px:** sigue midiendo 326 px por la fila de logos del grupo 2 y las muestras de `placeholder` de 260 px mínimo (preexistente).

Revisión del agente (paso 4): 0 críticos, 2 importantes, 7 menores (solo lectura). Aplicado: cifras, chips y tarjeta usan el mismo proceso (el más reciente con datos); las acciones cuentan solo metas de planes publicados; texto «Valoración» solo para lectores en cada chip; cadena traducible por rama en la bajada; 2 pruebas nuevas. Corregido al probar: la lista de chips con valor crecía más que su contenedor a 320 px en la guía (columna de la rejilla sin `minmax(0, 1fr)`).

## 20. Demostración local: paso 5a (documentos: migración y datos)

Los 4 archivos de `docs/fuentes` entran como medios `documento` por migración desde CSV, sin cambiar el modelo: `datos/semillas/documentos.csv` (los dos Acuerdos del CESU, ligados a su lineamiento) y `datos/importacion/<programa>/documentos.csv` (el informe y el plan de Electrónica, ligados al proceso). Migraciones nuevas en `unillanos_migrate`: `archivos_generales` y `documentos_generales`, y `archivos` y `documentos` (replicadas por programa, `documentos:<programa>`). La copia es de `file_copy` a `public://documentos/<programa>/` (o `public://documentos/general/` para los generales; copia, no movimiento) y la ruta del CSV, relativa a la raíz del repositorio, la valida el plugin `RutaDeArchivo` (rechaza rutas absolutas, con `..` o enlaces simbólicos que salgan del repositorio, rutas fuera de `docs/` y `datos/`, elementos ocultos como `.git`, archivos inexistentes y extensiones no permitidas por el campo). Marcas nuevas en los hooks: `%raiz%` (raíz del repositorio, o `$settings['unillanos_raiz']`) y `unillanos_csv_opcional` (un programa sin `documentos.csv` simplemente no tiene esas migraciones). Un programa nuevo solo necesita su `documentos.csv` y sus archivos.

Verificado: la carpeta de archivos de Drupal está ignorada por git; `docs/fuentes` y los 4 archivos son visibles en el contenedor (`/var/www/html/docs/fuentes`); copias idénticas a los originales (SHA-256); rollback de las 4 migraciones deja 0 archivos en `public://documentos/` (quedan 3 carpetas vacías) y 0 medios, y los originales intactos; reimportar deja exactamente 4 archivos (`general/` ×2 y `ingenieria_electronica/` ×2), sin sufijos `_0`; importar de nuevo sin rollback no crea nada (0 procesados). Una carpeta de programa temporal con filas malas (ruta absoluta, archivo inexistente, extensión no permitida) falló fila por fila con mensajes claros y se borró.

- [ ] **Extensiones del campo de archivo** (`pdf doc docx xls xlsx ppt pptx odt ods txt zip`): no se restringen ahora; **revisar en la Fase 9** (revisión de seguridad): `zip` y `txt` no hacen falta para documentos.
- [ ] **Fechas de publicación vacías:** ninguna fuente trae la fecha de publicación (la del plan, 19/06/2024, es la de suscripción). Se muestran solo si existen.
- [ ] **El plan de mejoramiento** va en la categoría `documento_base` (el modelo no trae categoría «plan»); valorar una categoría propia si se amplía el modelo.
- [ ] **Subcarpeta por programa:** hecha (`public://documentos/<programa>/`); dos archivos con el mismo nombre dentro de un mismo programa siguen renombrándose con sufijo (`file_exists: rename`).
- [ ] **`public://` sirve los archivos sin control de acceso:** un documento no publicado sigue accesible por su URL directa si alguien la conoce. Aceptable para la demostración local; para publicar, valorar archivos privados (`private://`) o un control por entidad.
- [ ] **Ícono de miniatura** (`public://media-icons/generic/generic.png`): Drupal lo crea al guardar el primer medio y queda tras un rollback (lo usan todos los medios); no es una copia de los documentos.
- [ ] **`field_enlace`** (documentos externos): no se migra ni se muestra todavía.
- [ ] **`RutaDeArchivo`:** copia solo de `docs/` y `datos/` (opción `bases`); sin tope de tamaño ni comprobación del contenido (la extensión se mira por el nombre, como las cargas de Drupal; `x.exe.pdf` pasa como PDF); la validación y la copia no son atómicas (confianza en el repositorio versionado).

Revisión del agente (paso 5a): 0 críticos, 3 importantes, 8 menores (solo lectura). Aplicado: el origen se acota a `docs/` y `datos/` y se rechazan elementos ocultos (antes cualquier archivo del repositorio con extensión permitida podía acabar en `public://`); subcarpeta por programa y `general/` (antes carpeta plana con renombrado silencioso); `drupal:media` y `drupal:file` en las dependencias del módulo; un `documentos.csv` vacío o con solo la cabecera ya no crea la migración (antes fallaba al importar el grupo); se quitó el `uid: 1` fijo de los archivos; pruebas nuevas (12 en total: carpeta no permitida, oculto, `x.exe.pdf`, `\` interior, CSV vacío o solo cabecera). Anotado sin aplicar: archivos no publicados accesibles por URL directa (`public://`, Fase 9), sin tope de tamaño, `strtr` global de `%raiz%`/`%datos%`, y nombres de archivo muy largos sin prueba.

## 21. Demostración local: paso 5b (página /documentos y documentos del programa)

Ruta `/documentos` (`DocumentosController` + `DocumentosPublicados::publicados()`), plantilla del tema `unillanos-documentos.html.twig` y sección «Documentos del programa» en la página de cada programa. La página muestra, con `pagehead` (h1), la normativa y los demás documentos generales por categoría (normatividad, documento base, informe, acto administrativo, soporte; solo las que existen) y «Documentos de los programas» con un grupo por programa (nombre con enlace a su página; con 6 o más programas, una lista de salto). Los documentos de un programa salen en el orden informe, documento base, acto administrativo, soporte, y dentro de cada categoría por fecha (la más reciente primero) y nombre (ordenado con `intl`, con acentos). Cada tarjeta (`doc-list`) lleva tipo (PDF, XLSX, DOCX), tamaño con coma decimal y fecha solo si existe; el nombre accesible del botón es «Descargar {título} (PDF · 5,2 MB)». Sin documentos: cabecera y «Todavía no hay documentos publicados.». La tarjeta «Documentos» de la portada aparece sola.

En la página del programa: botón «Ver {informe} (PDF)» en la cabecera de la sección de factores y «Descargar {plan} (XLSX)» en la del plan, solo si el programa tiene exactamente un documento de categoría `informe` y uno de categoría `documento_base`; se rotulan con el título del propio documento.

- [ ] **El plan se reconoce como «el único `documento_base` del programa»** (el modelo no trae categoría para el plan): un programa con un único documento base de otro tipo (un PEI, una matriz) lo mostraría como botón de la sección del plan, rotulado con su título. Valorar una categoría propia para el plan.
- [ ] **El informe se reconoce como «el único `informe` del programa»**, igual.
- [ ] **Textos editoriales de `/documentos` pendientes de confirmar por Acreditación** (del prototipo): bajada («Las normas que orientan la autoevaluación…»), «Normas que orientan el proceso», «Leyes, decretos y acuerdos del sector.»; **adaptados por mí:** «Documentos de los programas» y su bajada, «Documentos que sustentan el proceso», «Informes de autoevaluación», «Actos administrativos», «Documentos de soporte» y «Documentos del programa» con su bajada.
- [ ] **Navegador:** el PDF se abre en el visor del navegador (misma pestaña) y XLSX y DOCX se descargan; el botón no lo avisa más allá del tipo en el nombre accesible. No se fuerza `download` ni `target="_blank"`.
- [ ] **`field_enlace`** (documentos externos): no se muestra.
- [ ] **40 programas:** la página puede tener hasta 40 grupos; la lista de salto ayuda, y un filtro por programa o categoría queda para la Fase 7.
- [ ] **Cabecera de sección con botones de título largo:** a 1440 px los botones pasan debajo del título de la sección (antes iban a la derecha); legible, pero distinto de las otras cabeceras.
- [ ] **Sin extensión en el archivo** el rótulo del ícono es «DOC» (genérico, no «PDF»).
- [ ] **`public://` sirve los archivos sin control de acceso** (anotado en el 5a): un documento no publicado sigue accesible por su URL directa.

Revisión del agente (paso 5b): 0 críticos, 2 importantes, 9 menores (solo lectura). Aplicado: los botones del informe y del plan usan el título del documento (antes «Descargar el plan», que el dato no garantizaba); foco visible en alto contraste de los enlaces nuevos; ordenar con acentos (antes `strcoll` dejaba «Álgebra» y «Économía» después de «Zoología»); tamaños en el borde de 1 MB; rótulo «DOC» sin extensión; el título de un lineamiento sin publicar no se usa como descripción; se exige acceso al proceso; la página del programa solo consulta los medios de sus procesos; regla CSS muerta. **Hallazgo propio, de seguridad:** la consulta con `accessCheck(TRUE)` **no filtra los medios por el permiso «ver medios»** (el control de acceso de consultas no cubre este tipo): ahora se comprueba el acceso de cada medio. Pruebas: 11 de documentos (incluida la de un usuario sin permiso) y 12 de migración; la suite completa pasa.

- [ ] **Periodo del título del plan sin cortes (retoque del 5b):** `FormatoTextoTrait::sinCortesEnPeriodo()` (trait de `unillanos_autoeval`) evita que un salto de línea parta «2024-2 – 2030» insertando caracteres de unión de palabras (U+2060) y un espacio sin salto (U+00A0) en el texto que se muestra (el título guardado no cambia). Conviene reemplazarlo por un estilo CSS (p. ej. `white-space: nowrap` en un `<span>` del periodo) sin tocar el texto: los caracteres invisibles se copian con el título y los lectores de pantalla o la búsqueda pueden tratarlos distinto. Las pruebas `testPeriodoSinCortes` y el método se retiran al cambiar.

## 22. Fase 7: textos editoriales de `/programas` y página de programa

Textos que escribió el equipo técnico y que **Acreditación debe confirmar o redactar**. Se muestran tal cual hasta entonces; ninguno se calcula.

- [ ] **Bajada de `/programas`** (plantilla `unillanos-programas.html.twig`): «Busca un programa por su nombre o su facultad y consulta lo que se ha publicado de él.» A 1440 px deja «él.» solo en la segunda línea; conviene acortarla al confirmar el texto.
- [ ] **Bajada de la página de un programa** (`PaginaPrograma::lead()`): con factores de autoevaluación se conserva la frase del prototipo («Registro calificado, autoevaluación y plan de mejoramiento del programa, en un solo lugar.»); sin ellos se arma con las secciones que existen («Registro calificado, plan de mejoramiento y documentos del programa, en un solo lugar.», omitiendo las que faltan; si no hay ninguna, no se muestra bajada). Confirmar la redacción de ambas.
- [ ] **Rótulos del programa sin autoevaluación:** «Otro programa de la facultad» / «Otros programas de la facultad», «Ver todos los programas» y «Ver documentos».
- [ ] **«Registro calificado» sin datos (ajuste 9):** la bajada de Ingeniería Electrónica nombra el registro calificado, pero el programa no tiene resolución ni vigencia de registro cargadas. Se resuelve cuando Acreditación entregue esos datos (resolución de registro y fecha de vigencia); mientras tanto la frase se mantiene idéntica a la del prototipo.
- [ ] **Ortografía de «Especialización en Epidemiologia» (ajuste 11):** la fuente `datos/semillas/programas.csv` (línea 7) no lleva tilde; la línea 8 sí («Maestría en Epidemiología»). Confirmar el nombre oficial; se corrige en el CSV y se vuelve a migrar, no en la base.
- [ ] **Orden de «Otros programas de la facultad» (ajuste 12):** hoy salen primero los programas con autoevaluación y luego el resto en orden alfabético. Decidir si deben salir primero los del mismo nivel (p. ej. Enfermería, de pregrado, hoy muestra cuatro especializaciones).
- [ ] **`/institucional`:** la ruta no se creó en la Fase 7; el menú y el pie solo tienen enlaces a páginas que existen. Definir qué contiene esa página y quién redacta su texto.

Los ajustes técnicos de la Fase 7 (foco del buscador, logos de la guía, placeholder del buscador, etc.) están en `fase-7/ajustes-pendientes.md`, fuera del repositorio.
