# Modelo de contenido en Drupal (MER v2 → Drupal 11)

Este documento traduce cada entidad del [MER v2](../mer/MER.md) a una pieza de Drupal. Es la **fuente de verdad para los nombres de máquina**: si algo cambia aquí, se cambia también en el MER y en `config/sync`.

## Criterio general

| Tipo de dato del MER | Pieza de Drupal | Por qué |
|---|---|---|
| Catálogos que casi no cambian y sirven para filtrar | **Vocabulario de taxonomía** | Listas jerárquicas, filtros en Views y edición sencilla. |
| Registros con página propia, revisiones o permisos por programa | **Tipo de contenido (nodo)** | Revisiones, URL, permisos, Views, moderación. |
| Registros que solo existen dentro de otro (sin página propia) | **Paragraph** | Se editan dentro del padre, sin ensuciar el listado de contenido. |
| Archivos descargables | **Media (tipo Documento)** | Biblioteca de archivos estándar de Drupal. |
| Formulario público | **Webform** | Consentimiento, correo, estados y exportación sin programar. |

Todo con módulos del núcleo, salvo: `paragraphs`, `webform`, `pathauto`, `field_group`, `auto_entitylabel`, `migrate_plus`, `migrate_tools`, `migrate_source_csv`, `better_exposed_filters`, `admin_toolbar`.

La lógica que Drupal no trae se programa en el módulo propio **`unillanos_autoeval`** (cálculo del grado, validación de unicidad, avance de metas).

## Mapa entidad → Drupal

| Entidad MER | Pieza | Nombre de máquina |
|---|---|---|
| SEDE | Taxonomía | `sede` |
| FACULTAD | Taxonomía | `facultad` |
| PROGRAMA | Nodo | `programa` |
| LINEAMIENTO | Nodo | `lineamiento` |
| ELEMENTO_MODELO | Taxonomía jerárquica | `elemento_modelo` |
| GRADO_CUMPLIMIENTO | Taxonomía | `grado_cumplimiento` |
| ESTAMENTO | Taxonomía | `estamento` |
| PROCESO | Nodo | `proceso` |
| VALORACION | Nodo | `valoracion` |
| HALLAZGO | Nodo | `hallazgo` |
| PARTICIPACION | Paragraph (dentro de `proceso`) | `participacion` |
| PLAN_MEJORAMIENTO | Nodo | `plan_mejoramiento` |
| META | Nodo | `meta` |
| INDICADOR | Paragraph (dentro de `meta`) | `indicador` |
| PROGRAMACION_ANUAL | Paragraph (dentro de `indicador`) | `programacion_anual` |
| SEGUIMIENTO | Nodo | `seguimiento` |
| RESPONSABLE | Taxonomía | `responsable` |
| PROYECTO_INSTITUCIONAL | Taxonomía | `proyecto_institucional` |
| DOCUMENTO | Media | `documento` |
| RECOMENDACION | Webform | `participa` |
| META ↔ HALLAZGO (N:M) | Campo multivalor en `meta` | `field_hallazgos` |
| META ↔ RESPONSABLE (N:M) | Campo multivalor en `meta` | `field_responsables` |

`id` del MER = ID de Drupal (nid, tid, mid…). Las columnas `*_id` de los CSV (`CNA20-F02`, `M01`…) son **claves de importación**; Migrate guarda el mapeo y no se crean campos para ellas, salvo `field_clave` en `elemento_modelo` y `programa` (útil para buscar y para URLs estables).

## Taxonomías

| Vocabulario | Campo | Tipo Drupal | Notas |
|---|---|---|---|
| `sede` | `field_municipio` | Texto (simple) | |
| | `field_activa` | Booleano | |
| `facultad` | `field_sigla` | Texto (simple) | FCS, FCBI, FCE, FCARN, FCHYE |
| `estamento` | — | | Solo nombre |
| `grado_cumplimiento` | `field_valoracion_min` | Decimal (2,1) | |
| | `field_valoracion_max` | Decimal (2,1) | |
| | `field_porcentaje_min` | Entero | |
| | (peso del término) | | = `orden` |
| `responsable` | `field_tipo_responsable` | Lista (texto) | `dependencia`, `organo_colegiado`, `comunidad` |
| | `field_sigla` | Texto (simple) | |
| `proyecto_institucional` | `field_activo` | Booleano | |
| `elemento_modelo` | (padre del término) | | = `padre_id` (jerarquía nativa) |
| | `field_clave` | Texto (simple), único | `CNA20-F02`, `CESU25-C10`, `RC-P3` |
| | `field_lineamiento` | Referencia → nodo `lineamiento` | obligatorio |
| | `field_tipo_elemento` | Lista (texto) | `factor`, `caracteristica`, `aspecto`, `condicion` |
| | `field_numero` | Entero | |
| | `field_propio` | Booleano | aspecto agregado por la Universidad |
| | `field_equivale_a` | Referencia → término `elemento_modelo` | |
| | (descripción del término) | | = `descripcion` |

## Tipos de contenido (nodos)

`title` del nodo = `nombre` del MER, salvo donde se indique. `status` (publicado) reemplaza a `activo` / `publicado` / `vigente` cuando aplica.

### `programa`
`field_clave` (texto, único, ej. `ingenieria-electronica`) · `field_codigo_snies` (texto) · `field_nivel` (lista: `pregrado`, `especializacion`, `maestria`, `doctorado`) · `field_modalidad` (lista: `presencial`, `distancia`, `virtual`, `dual`, `hibrida`) · `field_facultad` (→ `facultad`) · `field_sede` (→ `sede`) · `field_director` (texto) · `field_resolucion_registro` (texto) · `field_registro_hasta` (fecha) · `field_resolucion_acreditacion` (texto) · `field_acreditacion_hasta` (fecha) · `field_imagen` (imagen, para la cabecera del prototipo).

### `lineamiento`
`field_norma` (texto) · `field_ambito` (lista: `programa`, `unidad_academica`, `institucional`) · `field_tipo_proceso` (lista, ver abajo) · `field_vigente_desde` (fecha).

### `proceso`
`field_tipo_proceso` (lista: `registro_calificado`, `acreditacion`, `renovacion_acreditacion`, `acreditacion_internacional`) · `field_ambito` (lista como en lineamiento) · `field_sede` (→ `sede`, procesos institucionales) · `field_programa` (→ `programa`) · `field_lineamiento` (→ `lineamiento`, obligatorio) · `field_periodo_evaluado` (texto, ej. `2018–2022`) · `field_fase_actual` (entero 1–n) · `field_estado` (lista: `planeado`, `en_curso`, `finalizado`) · `field_resultado` (texto) · `field_participaciones` (Paragraphs → `participacion`).
Regla: exactamente uno de `field_sede` o `field_programa` (validación en `unillanos_autoeval`).

### `valoracion`
Título automático con `auto_entitylabel`: `[proceso] · [elemento]`.
`field_proceso` (→ `proceso`, obligatorio) · `field_elemento` (→ `elemento_modelo`, obligatorio) · `field_valoracion` (decimal, precisión 3 y escala 2; 1.00–5.00) (el informe publica valores como 4,27 y 4,39) · `field_porcentaje` (decimal 4,1) · `field_ponderacion` (decimal 5,2) · `field_estado_condicion` (lista: `cumple`, `cumple_parcialmente`, `no_cumple`; solo para condiciones) · `field_grado` (→ `grado_cumplimiento`, **calculado** en presave desde `field_valoracion`) · `field_documento` (→ media `documento`) · `field_juicio` (texto largo) · `field_sintesis` (texto largo).
Regla: la pareja (proceso, elemento) es única (restricción de validación en `unillanos_autoeval`).

### `hallazgo`
Título automático: primeras palabras de la descripción.
`field_proceso` (→ `proceso`) · `field_elemento` (→ `elemento_modelo`) · `field_tipo_hallazgo` (lista: `fortaleza`, `aspecto_por_mejorar`) · `body` (= descripción) · `field_origen` (lista: `determinacion_cna`, `autoevaluacion`) · `field_destino` (lista: `plan_mejoramiento`, `plan_accion`, `ninguno`) · `field_destacado` (booleano, para la portada) · `field_imagen` (imagen).

### `plan_mejoramiento`
`field_proceso` (→ `proceso`) · `field_tipo_plan` (lista: `institucional`, `programa`) · `field_codigo_formato` (texto, `FO-GCL-20`) · `field_version_formato` (texto) · `field_periodo` (texto, ej. `2024-2 – 2030`) · `field_fecha_suscripcion` (fecha) · `field_alta_calidad` (lista: `acreditacion`, `reacreditacion`, `no_aplica`) · `field_aprobaciones` (texto largo: elaboró/aprobó, actas y fechas).

### `meta`
Título = `Meta {codigo}`.
`field_plan` (→ `plan_mejoramiento`, obligatorio) · `field_numero` (entero, orden) · `field_codigo` (texto, ej. `2a.2b, 2c`) · `body` (= descripción de la meta) · `field_proyecto` (→ `proyecto_institucional`) · `field_tipo_meta` (lista: `institucional`, `programa`, `institucional_programa`) · `field_peso` (decimal 6,4) · `field_actividades` (texto largo) · `field_recursos` (texto) · `field_hallazgos` (→ `hallazgo`, multivalor) · `field_responsables` (→ `responsable`, multivalor) · `field_indicadores` (Paragraphs → `indicador`).
El factor de una meta se obtiene por sus hallazgos (`field_hallazgos` → `field_elemento`); así funciona el enlace "ver acciones de este factor" del prototipo.

### `seguimiento`
Título automático: `[meta] · [periodo]`.
`field_meta` (→ `meta`, obligatorio) · `field_periodo` (texto `2025-1`) · `field_avance` (decimal 5,2; 0–1) · `field_actividades` · `field_medios_verificacion` · `field_verificacion` · `field_observaciones` (texto largo) · `field_reportado_por` · `field_verificado_por` (texto) · `field_estado` (lista: `borrador`, `reportado`, `verificado`).

## Paragraphs

| Tipo | Campos |
|---|---|
| `participacion` | `field_estamento` (→ `estamento`) · `field_instrumento` (lista: `encuesta`, `taller`, `grupo_focal`, `entrevista`) · `field_participantes` (entero) · `field_fecha_aplicacion` (fecha) |
| `indicador` | `field_nombre` (texto) · `field_unidad` (texto) · `field_linea_base` (texto) · `field_programacion` (Paragraphs → `programacion_anual`) |
| `programacion_anual` | `field_anio` (entero) · `field_programado` (decimal 12,4) · `field_logrado` (decimal 12,4) |

## Media `documento`
Fuente: archivo. `field_categoria` (lista: `normatividad`, `documento_base`, `informe`, `acto_administrativo`, `soporte`) · `field_version` (texto) · `field_fecha_publicacion` (fecha) · `field_proceso` (→ `proceso`) · `field_lineamiento` (→ `lineamiento`) · `field_enlace` (enlace, para documentos externos). Publicado = `publico`.

## Webform `participa`
Elementos: `proceso` (entidad → nodo `proceso`) · `elemento` (término `elemento_modelo`, opcional) · `estamento` (término `estamento`) · `nombre` (texto, opcional) · `texto` (área de texto, máx. 1000) · `consentimiento` (casilla obligatoria). Solo administradores: `estado_gestion` (`recibida`, `en_revision`, `respondida`, `descartada`) y `respuesta`. `fecha_envio` = fecha de envío del webform.

## Lógica del módulo `unillanos_autoeval`

1. `hook_ENTITY_TYPE_presave()` de `valoracion`: calcula `field_grado` con los rangos de `grado_cumplimiento`. Los rangos de la Tabla 3.1 dejan huecos (4,7–4,8; 3,9–4,0): se redondea a 1 decimal antes de comparar. Confirmar la regla con Acreditación.
2. Restricciones de validación: (proceso, elemento) único en `valoracion`; `proceso` con sede **o** programa.
3. Servicio `AvanceMeta`: avance de una meta = último `seguimiento.field_avance` verificado; avance del plan = Σ(peso × avance).
4. Regla del informe: hallazgo con valoración < 4 → `field_destino = plan_mejoramiento`; = 4 → `plan_accion` (sugerencia al crear, editable).

## Páginas del prototipo → Drupal

| Prototipo (`plantilla/`) | Drupal |
|---|---|
| `index.html` | Portada (`/`): bloques de Views (indicadores, procesos, hallazgos destacados) |
| `institucional.html` | Página `/institucional` con 3 pestañas = 3 bloques de Views filtrados por procesos institucionales |
| `programas.html` | View `programas` (`/programas`) con filtros expuestos (facultad, nivel, sede, texto) |
| `programa-ejemplo.html` | Nodo `programa` en modo completo; pestañas con bloques de Views que reciben el nid por contexto |
| `documentos.html` | View `documentos` (`/documentos`) de media `documento`, pestañas por categoría |
| `participa.html` | Página `/participa` con el webform `participa` |

Las pestañas animadas del prototipo (`data-ua-views`, `data-routes`, `data-view`) se conservan en las plantillas Twig del tema; `app.js` pasa a `Drupal.behaviors.unillanos`.
