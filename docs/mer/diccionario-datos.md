# Diccionario de datos · MER v2

Tipos: `int` número entero · `varchar(n)` texto de hasta n caracteres · `text` texto largo · `decimal(p,e)` número con decimales · `date` fecha · `datetime` fecha y hora · `bool` sí/no · `enum` lista fija de opciones.

Llaves: **PK** llave primaria · **FK** llave foránea · **UK** valor único (si aparece en dos campos, la combinación es única).

## Institución

### `SEDE` · Sede

Lugar donde se ofrecen los programas.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `nombre` | `varchar(80)` | UK |
| `municipio` | `varchar(80)` |  |
| `activa` | `bool` |  |

### `FACULTAD` · Facultad

Unidad académica.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `nombre` | `varchar(150)` | UK |
| `sigla` | `varchar(20)` |  |

### `PROGRAMA` · Programa académico

Oferta con registro calificado.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `codigo_snies` | `varchar(20)` | UK |
| `nombre` | `varchar(200)` |  |
| `nivel` | `enum` |  |
| `modalidad` | `enum` |  |
| `facultad_id` | `int` | FK |
| `sede_id` | `int` | FK |
| `director` | `varchar(150)` |  |
| `resolucion_registro` | `varchar(60)` |  |
| `registro_hasta` | `date` |  |
| `resolucion_acreditacion` | `varchar(60)` |  |
| `acreditacion_hasta` | `date` |  |
| `activo` | `bool` |  |

## Modelo de calidad

### `LINEAMIENTO` · Lineamiento

Norma que define qué se evalúa.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `nombre` | `varchar(150)` |  |
| `norma` | `varchar(150)` |  |
| `ambito` | `enum` |  |
| `tipo_proceso` | `enum` |  |
| `vigente_desde` | `date` |  |
| `activo` | `bool` |  |

### `ELEMENTO_MODELO` · Elemento del modelo

Factor, característica, aspecto o condición.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `lineamiento_id` | `int` | FK |
| `padre_id` | `int` | FK |
| `tipo` | `enum` |  |
| `numero` | `int` |  |
| `nombre` | `varchar(250)` |  |
| `descripcion` | `text` |  |
| `propio` | `bool` |  |
| `equivale_a_id` | `int` | FK |

### `GRADO_CUMPLIMIENTO` · Grado de cumplimiento

Escala de la Universidad.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `nombre` | `varchar(40)` | UK |
| `valoracion_min` | `decimal(2,1)` |  |
| `valoracion_max` | `decimal(2,1)` |  |
| `porcentaje_min` | `int` |  |
| `orden` | `int` |  |

### `ESTAMENTO` · Estamento

Grupo de la comunidad.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `nombre` | `varchar(80)` | UK |

## Autoevaluación

### `PROCESO` · Proceso de autoevaluación

Ejercicio concreto de autoevaluación.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `nombre` | `varchar(200)` |  |
| `tipo` | `enum` |  |
| `ambito` | `enum` |  |
| `sede_id` | `int` | FK |
| `programa_id` | `int` | FK |
| `lineamiento_id` | `int` | FK |
| `periodo_evaluado` | `varchar(20)` |  |
| `fase_actual` | `int` |  |
| `estado` | `enum` |  |
| `resultado` | `varchar(120)` |  |
| `publicado` | `bool` |  |

### `VALORACION` · Valoración

Resultado de un elemento (factor, característica o condición).

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `proceso_id` | `int` | FK, UK |
| `elemento_id` | `int` | FK, UK |
| `valoracion` | `decimal(3,2)` |  |
| `porcentaje` | `decimal(4,1)` |  |
| `ponderacion` | `decimal(5,2)` |  |
| `estado_condicion` | `enum` |  |
| `grado_id` | `int` | FK |
| `documento_id` | `int` | FK |
| `juicio` | `text` |  |
| `sintesis` | `text` |  |

### `HALLAZGO` · Hallazgo

Fortaleza o aspecto por mejorar.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `proceso_id` | `int` | FK |
| `elemento_id` | `int` | FK |
| `tipo` | `enum` |  |
| `descripcion` | `varchar(500)` |  |
| `origen` | `enum` |  |
| `destino` | `enum` |  |
| `destacado` | `bool` |  |
| `imagen` | `varchar(255)` |  |

### `PARTICIPACION` · Participación

Instrumento aplicado a un estamento.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `proceso_id` | `int` | FK |
| `estamento_id` | `int` | FK |
| `instrumento` | `enum` |  |
| `participantes` | `int` |  |
| `fecha_aplicacion` | `date` |  |

## Plan de mejoramiento

### `RESPONSABLE` · Responsable

Dependencia, órgano colegiado o comunidad que ejecuta metas.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `nombre` | `varchar(150)` | UK |
| `tipo` | `enum` |  |
| `sigla` | `varchar(20)` |  |

### `PROYECTO_INSTITUCIONAL` · Proyecto institucional

Proyectos a los que contribuyen las metas.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `nombre` | `varchar(250)` | UK |
| `activo` | `bool` |  |

### `PLAN_MEJORAMIENTO` · Plan de mejoramiento

Plan según el formato FO-GCL-20.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `proceso_id` | `int` | FK |
| `tipo` | `enum` |  |
| `codigo_formato` | `varchar(20)` |  |
| `version_formato` | `varchar(10)` |  |
| `periodo` | `varchar(20)` |  |
| `fecha_suscripcion` | `date` |  |
| `alta_calidad` | `enum` |  |
| `aprobaciones` | `text` |  |
| `vigente` | `bool` |  |

### `META` · Meta

Meta de una oportunidad de mejora.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `plan_id` | `int` | FK, UK |
| `numero` | `int` | UK |
| `descripcion` | `text` |  |
| `proyecto_id` | `int` | FK |
| `tipo_meta` | `enum` |  |
| `peso` | `decimal(6,4)` |  |
| `actividades` | `text` |  |
| `recursos` | `varchar(250)` |  |

### `INDICADOR` · Indicador

Indicador de una meta.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `meta_id` | `int` | FK |
| `nombre` | `varchar(250)` |  |
| `unidad` | `varchar(40)` |  |
| `linea_base` | `varchar(120)` |  |

### `PROGRAMACION_ANUAL` · Programación anual

Valor programado y logrado por año.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `indicador_id` | `int` | FK, UK |
| `anio` | `int` | UK |
| `programado` | `decimal(12,4)` |  |
| `logrado` | `decimal(12,4)` |  |

### `SEGUIMIENTO` · Seguimiento

Reporte semestral de una meta.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `meta_id` | `int` | FK |
| `periodo` | `varchar(10)` |  |
| `avance` | `decimal(5,2)` |  |
| `actividades` | `text` |  |
| `medios_verificacion` | `text` |  |
| `verificacion` | `text` |  |
| `observaciones` | `text` |  |
| `reportado_por` | `varchar(120)` |  |
| `verificado_por` | `varchar(120)` |  |
| `estado` | `enum` |  |

## Documentos y participación

### `DOCUMENTO` · Documento

Documento descargable.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `titulo` | `varchar(200)` |  |
| `categoria` | `enum` |  |
| `enlace` | `varchar(255)` |  |
| `version` | `varchar(20)` |  |
| `fecha_publicacion` | `date` |  |
| `proceso_id` | `int` | FK |
| `lineamiento_id` | `int` | FK |
| `publico` | `bool` |  |

### `RECOMENDACION` · Recomendación

Recomendación enviada desde Participa.

| Campo | Tipo | Llave |
|---|---|---|
| `id` | `int` | PK |
| `proceso_id` | `int` | FK |
| `elemento_id` | `int` | FK |
| `estamento_id` | `int` | FK |
| `nombre` | `varchar(120)` |  |
| `texto` | `varchar(1000)` |  |
| `consentimiento` | `bool` |  |
| `fecha_envio` | `datetime` |  |
| `estado_gestion` | `enum` |  |
| `respuesta` | `text` |  |
