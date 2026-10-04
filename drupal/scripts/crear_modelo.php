<?php

/**
 * @file
 * Crea el modelo de contenido (MER v2 → Drupal 11). Idempotente.
 *
 * Uso (desde la raíz del repositorio):
 *   ddev drush php:script scripts/crear_modelo.php
 *
 * Fuente de verdad de los nombres: docs/drupal/modelo-de-contenido.md.
 * Orden: 1) vocabularios, 2) tipos de contenido, 3) paragraphs, 4) media,
 * 5) campos (almacenamiento + instancia), 6) formularios y visualización.
 * Lo que ya existe no se toca: se puede correr las veces que haga falta.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\taxonomy\Entity\Vocabulary;

$resumen = [];
$contar = function (string $clave, string $detalle) use (&$resumen): void {
  $resumen[$clave][] = $detalle;
};

// ---------------------------------------------------------------------------
// Listas de valores permitidos (clave => etiqueta).
// ---------------------------------------------------------------------------
$ambito = [
  'programa' => 'Programa',
  'unidad_academica' => 'Unidad académica',
  'institucional' => 'Institucional',
];
$tipo_proceso = [
  'registro_calificado' => 'Registro calificado',
  'acreditacion' => 'Acreditación',
  'renovacion_acreditacion' => 'Renovación de la acreditación',
  'acreditacion_internacional' => 'Acreditación internacional',
];
// Aviso: `field_estado` existe en `proceso` y en `seguimiento` con valores
// distintos. Los nodos comparten el almacenamiento de un campo del mismo
// nombre, así que la lista permitida es la unión de ambas.
$estado_union = [
  'planeado' => 'Planeado',
  'en_curso' => 'En curso',
  'finalizado' => 'Finalizado',
  'borrador' => 'Borrador',
  'reportado' => 'Reportado',
  'verificado' => 'Verificado',
];

// ---------------------------------------------------------------------------
// Constructores de especificación de campos.
// ---------------------------------------------------------------------------
$texto = fn(string $etiqueta, array $extra = [], int $largo = 255): array => [
  'tipo' => 'string',
  'etiqueta' => $etiqueta,
  'storage' => ['max_length' => $largo],
] + $extra;
$largo_texto = fn(string $etiqueta, array $extra = []): array => [
  'tipo' => 'text_long',
  'etiqueta' => $etiqueta,
] + $extra;
$entero = fn(string $etiqueta, array $extra = [], ?int $min = NULL, ?int $max = NULL): array => [
  'tipo' => 'integer',
  'etiqueta' => $etiqueta,
  'instancia' => ['min' => $min, 'max' => $max],
] + $extra;
$decimal = fn(string $etiqueta, int $precision, int $escala, array $extra = [], ?float $min = NULL, ?float $max = NULL): array => [
  'tipo' => 'decimal',
  'etiqueta' => $etiqueta,
  'storage' => ['precision' => $precision, 'scale' => $escala],
  'instancia' => ['min' => $min, 'max' => $max],
] + $extra;
$booleano = fn(string $etiqueta, bool $defecto = FALSE, array $extra = []): array => [
  'tipo' => 'boolean',
  'etiqueta' => $etiqueta,
  'valor_defecto' => (int) $defecto,
] + $extra;
$lista = fn(string $etiqueta, array $valores, array $extra = []): array => [
  'tipo' => 'list_string',
  'etiqueta' => $etiqueta,
  'storage' => ['allowed_values' => $valores],
] + $extra;
$fecha = fn(string $etiqueta, array $extra = []): array => [
  'tipo' => 'datetime',
  'etiqueta' => $etiqueta,
  'storage' => ['datetime_type' => 'date'],
] + $extra;
$ref_termino = fn(string $etiqueta, array $vocabularios, array $extra = []): array => [
  'tipo' => 'entity_reference',
  'etiqueta' => $etiqueta,
  'storage' => ['target_type' => 'taxonomy_term'],
  'instancia' => ['handler' => 'default:taxonomy_term', 'handler_settings' => [
    'target_bundles' => array_combine($vocabularios, $vocabularios),
    'sort' => ['field' => 'name', 'direction' => 'asc'],
    'auto_create' => FALSE,
  ]],
] + $extra;
$ref_nodo = fn(string $etiqueta, array $tipos, array $extra = []): array => [
  'tipo' => 'entity_reference',
  'etiqueta' => $etiqueta,
  'storage' => ['target_type' => 'node'],
  'instancia' => ['handler' => 'default:node', 'handler_settings' => [
    'target_bundles' => array_combine($tipos, $tipos),
    'sort' => ['field' => '_none', 'direction' => 'ASC'],
    'auto_create' => FALSE,
  ]],
] + $extra;
$ref_media = fn(string $etiqueta, array $tipos, array $extra = []): array => [
  'tipo' => 'entity_reference',
  'etiqueta' => $etiqueta,
  'storage' => ['target_type' => 'media'],
  'instancia' => ['handler' => 'default:media', 'handler_settings' => [
    'target_bundles' => array_combine($tipos, $tipos),
    'sort' => ['field' => '_none', 'direction' => 'ASC'],
    'auto_create' => FALSE,
  ]],
  'widget' => ['type' => 'media_library_widget', 'settings' => ['media_types' => []]],
] + $extra;
$parrafos = fn(string $etiqueta, string $tipo_paragraph, string $singular, string $plural): array => [
  'tipo' => 'entity_reference_revisions',
  'etiqueta' => $etiqueta,
  'cardinalidad' => -1,
  'storage' => ['target_type' => 'paragraph'],
  'instancia' => ['handler' => 'default:paragraph', 'handler_settings' => [
    'target_bundles' => [$tipo_paragraph => $tipo_paragraph],
    'negate' => 0,
    'target_bundles_drag_drop' => [$tipo_paragraph => ['weight' => 0, 'enabled' => TRUE]],
  ]],
  'widget' => ['type' => 'paragraphs', 'settings' => [
    'title' => $singular,
    'title_plural' => $plural,
    'edit_mode' => 'open',
    'closed_mode' => 'summary',
    'autocollapse' => 'none',
    'closed_mode_threshold' => 0,
    'add_mode' => 'button',
    'form_display_mode' => 'default',
    'default_paragraph_type' => $tipo_paragraph,
    'features' => ['duplicate' => 'duplicate', 'collapse_edit_all' => 'collapse_edit_all'],
  ]],
  'formatter' => ['type' => 'entity_reference_revisions_entity_view', 'settings' => [
    'view_mode' => 'default',
    'link' => FALSE,
  ]],
];
$imagen = fn(string $etiqueta, string $directorio, array $extra = []): array => [
  'tipo' => 'image',
  'etiqueta' => $etiqueta,
  'storage' => ['uri_scheme' => 'public'],
  'instancia' => [
    'file_directory' => $directorio,
    'file_extensions' => 'png jpg jpeg webp',
    'alt_field' => TRUE,
    'alt_field_required' => FALSE,
    'title_field' => FALSE,
    'max_filesize' => '5 MB',
  ],
] + $extra;
$enlace = fn(string $etiqueta, array $extra = []): array => [
  'tipo' => 'link',
  'etiqueta' => $etiqueta,
  'instancia' => ['link_type' => 16, 'title' => 0],
] + $extra;
$requerido = ['requerido' => TRUE];
$multiple = ['cardinalidad' => -1];

// ---------------------------------------------------------------------------
// 1) Vocabularios (7).
// ---------------------------------------------------------------------------
$vocabularios = [
  'sede' => 'Sede',
  'facultad' => 'Facultad',
  'estamento' => 'Estamento',
  'grado_cumplimiento' => 'Grado de cumplimiento',
  'responsable' => 'Responsable',
  'proyecto_institucional' => 'Proyecto institucional',
  'elemento_modelo' => 'Elemento del modelo',
];

// ---------------------------------------------------------------------------
// 2) Tipos de contenido (8). `lineamiento` va primero: otros lo referencian.
// ---------------------------------------------------------------------------
$tipos_nodo = [
  'lineamiento' => 'Lineamiento',
  'programa' => 'Programa',
  'proceso' => 'Proceso',
  'valoracion' => 'Valoración',
  'hallazgo' => 'Hallazgo',
  'plan_mejoramiento' => 'Plan de mejoramiento',
  'meta' => 'Meta',
  'seguimiento' => 'Seguimiento',
];

// ---------------------------------------------------------------------------
// 3) Tipos de paragraph (3).
// ---------------------------------------------------------------------------
$tipos_paragraph = [
  'participacion' => 'Participación',
  'indicador' => 'Indicador',
  'programacion_anual' => 'Programación anual',
];

// ---------------------------------------------------------------------------
// 4) Tipo de media (1).
// ---------------------------------------------------------------------------
$tipos_media = ['documento' => 'Documento'];

// ---------------------------------------------------------------------------
// 5) Campos: [tipo de entidad][bundle][nombre de máquina] => especificación.
// ---------------------------------------------------------------------------
$campos = [];

$campos['taxonomy_term'] = [
  'sede' => [
    'field_municipio' => $texto('Municipio', [], 80),
    'field_activa' => $booleano('Activa', TRUE),
  ],
  'facultad' => [
    'field_sigla' => $texto('Sigla', [], 20),
  ],
  'estamento' => [],
  'grado_cumplimiento' => [
    'field_valoracion_min' => $decimal('Valoración mínima', 2, 1, $requerido),
    'field_valoracion_max' => $decimal('Valoración máxima', 2, 1, $requerido),
    'field_porcentaje_min' => $entero('Porcentaje mínimo', [], 0, 100),
  ],
  'responsable' => [
    'field_tipo_responsable' => $lista('Tipo de responsable', [
      'dependencia' => 'Dependencia',
      'organo_colegiado' => 'Órgano colegiado',
      'comunidad' => 'Comunidad',
    ], $requerido),
    'field_sigla' => $texto('Sigla', [], 20),
  ],
  'proyecto_institucional' => [
    'field_activo' => $booleano('Activo', TRUE),
  ],
  'elemento_modelo' => [
    'field_clave' => $texto('Clave', $requerido + [
      'descripcion' => 'Clave de importación estable. Ej.: CNA20-F02, CESU25-C10, RC-P3.',
    ], 64),
    'field_lineamiento' => $ref_nodo('Lineamiento', ['lineamiento'], $requerido + [
      'widget' => ['type' => 'options_select', 'settings' => []],
    ]),
    'field_tipo_elemento' => $lista('Tipo de elemento', [
      'factor' => 'Factor',
      'caracteristica' => 'Característica',
      'aspecto' => 'Aspecto',
      'condicion' => 'Condición',
    ], $requerido),
    'field_numero' => $entero('Número'),
    'field_propio' => $booleano('Propio de la Universidad', FALSE, [
      'descripcion' => 'Marcar si es un aspecto agregado por la Universidad.',
    ]),
    'field_equivale_a' => $ref_termino('Equivale a', ['elemento_modelo'], [
      'descripcion' => 'Elemento equivalente en otro lineamiento.',
    ]),
  ],
];

$campos['node'] = [
  'programa' => [
    'field_clave' => $texto('Clave', $requerido + [
      'descripcion' => 'Identificador estable para URLs. Ej.: ingenieria-electronica.',
    ], 128),
    'field_codigo_snies' => $texto('Código SNIES', [], 20),
    'field_nivel' => $lista('Nivel', [
      'pregrado' => 'Pregrado',
      'especializacion' => 'Especialización',
      'maestria' => 'Maestría',
      'doctorado' => 'Doctorado',
    ], $requerido),
    'field_modalidad' => $lista('Modalidad', [
      'presencial' => 'Presencial',
      'distancia' => 'Distancia',
      'virtual' => 'Virtual',
      'dual' => 'Dual',
      'hibrida' => 'Híbrida',
    ], $requerido),
    'field_facultad' => $ref_termino('Facultad', ['facultad'], $requerido),
    'field_sede' => $ref_termino('Sede', ['sede'], $requerido),
    'field_director' => $texto('Director', [], 150),
    'field_resolucion_registro' => $texto('Resolución de registro calificado', [], 60),
    'field_registro_hasta' => $fecha('Registro calificado vigente hasta'),
    'field_resolucion_acreditacion' => $texto('Resolución de acreditación', [], 60),
    'field_acreditacion_hasta' => $fecha('Acreditación vigente hasta'),
    'field_imagen' => $imagen('Imagen de cabecera', 'programas'),
  ],
  'lineamiento' => [
    'field_norma' => $texto('Norma', [], 150),
    'field_ambito' => $lista('Ámbito', $ambito, $requerido),
    'field_tipo_proceso' => $lista('Tipo de proceso', $tipo_proceso, $requerido),
    'field_vigente_desde' => $fecha('Vigente desde'),
  ],
  'proceso' => [
    'field_tipo_proceso' => $lista('Tipo de proceso', $tipo_proceso, $requerido),
    'field_ambito' => $lista('Ámbito', $ambito, $requerido),
    'field_sede' => $ref_termino('Sede', ['sede'], [
      'descripcion' => 'Solo para procesos institucionales. Sede o programa, no ambos.',
    ]),
    'field_programa' => $ref_nodo('Programa', ['programa'], [
      'descripcion' => 'Solo para procesos de programa. Sede o programa, no ambos.',
    ]),
    'field_lineamiento' => $ref_nodo('Lineamiento', ['lineamiento'], $requerido + [
      'widget' => ['type' => 'options_select', 'settings' => []],
    ]),
    'field_periodo_evaluado' => $texto('Periodo evaluado', ['descripcion' => 'Ej.: 2018–2022.'], 20),
    'field_fase_actual' => $entero('Fase actual', [], 1),
    'field_estado' => $lista('Estado', $estado_union, $requerido),
    'field_resultado' => $texto('Resultado', [], 120),
    'field_participaciones' => $parrafos('Participaciones', 'participacion', 'Participación', 'Participaciones'),
  ],
  'valoracion' => [
    'field_proceso' => $ref_nodo('Proceso', ['proceso'], $requerido),
    'field_elemento' => $ref_termino('Elemento del modelo', ['elemento_modelo'], $requerido),
    'field_valoracion' => $decimal('Valoración', 3, 2, ['descripcion' => 'Entre 1,00 y 5,00.'], 1, 5),
    'field_porcentaje' => $decimal('Porcentaje', 4, 1),
    'field_ponderacion' => $decimal('Ponderación', 5, 2),
    'field_estado_condicion' => $lista('Estado de la condición', [
      'cumple' => 'Cumple',
      'cumple_parcialmente' => 'Cumple parcialmente',
      'no_cumple' => 'No cumple',
    ], ['descripcion' => 'Solo para condiciones de registro calificado.']),
    'field_grado' => $ref_termino('Grado de cumplimiento', ['grado_cumplimiento'], [
      'descripcion' => 'Se calcula a partir de la valoración; no se digita.',
      'oculto_formulario' => TRUE,
    ]),
    'field_documento' => $ref_media('Documento soporte', ['documento']),
    'field_juicio' => $largo_texto('Juicio'),
    'field_sintesis' => $largo_texto('Síntesis'),
  ],
  'hallazgo' => [
    'body' => [
      'tipo' => 'text_with_summary',
      'etiqueta' => 'Descripción',
      'requerido' => TRUE,
      'instancia' => ['display_summary' => FALSE, 'allowed_formats' => []],
      'widget' => ['type' => 'text_textarea', 'settings' => ['rows' => 5, 'placeholder' => '']],
      'formatter' => ['type' => 'text_default', 'settings' => []],
    ],
    'field_proceso' => $ref_nodo('Proceso', ['proceso'], $requerido),
    'field_elemento' => $ref_termino('Elemento del modelo', ['elemento_modelo']),
    'field_tipo_hallazgo' => $lista('Tipo de hallazgo', [
      'fortaleza' => 'Fortaleza',
      'aspecto_por_mejorar' => 'Aspecto por mejorar',
    ], $requerido),
    'field_origen' => $lista('Origen', [
      'determinacion_cna' => 'Determinación del CNA',
      'autoevaluacion' => 'Autoevaluación',
    ]),
    'field_destino' => $lista('Destino', [
      'plan_mejoramiento' => 'Plan de mejoramiento',
      'plan_accion' => 'Plan de acción',
      'ninguno' => 'Ninguno',
    ]),
    'field_destacado' => $booleano('Destacado en la portada'),
    'field_imagen' => $imagen('Imagen', 'hallazgos'),
  ],
  'plan_mejoramiento' => [
    'field_proceso' => $ref_nodo('Proceso', ['proceso'], $requerido),
    'field_tipo_plan' => $lista('Tipo de plan', [
      'institucional' => 'Institucional',
      'programa' => 'Programa',
    ], $requerido),
    'field_codigo_formato' => $texto('Código del formato', ['valor_defecto_texto' => 'FO-GCL-20'], 20),
    'field_version_formato' => $texto('Versión del formato', [], 10),
    'field_periodo' => $texto('Periodo', ['descripcion' => 'Ej.: 2024-2 – 2030.'], 20),
    'field_fecha_suscripcion' => $fecha('Fecha de suscripción'),
    'field_alta_calidad' => $lista('Alta calidad', [
      'acreditacion' => 'Acreditación',
      'reacreditacion' => 'Reacreditación',
      'no_aplica' => 'No aplica',
    ]),
    'field_aprobaciones' => $largo_texto('Aprobaciones', [
      'descripcion' => 'Quién elaboró y aprobó, con actas y fechas.',
    ]),
  ],
  'meta' => [
    'field_plan' => $ref_nodo('Plan de mejoramiento', ['plan_mejoramiento'], $requerido),
    'field_numero' => $entero('Número', $requerido + ['descripcion' => 'Orden de la meta dentro del plan.']),
    'field_codigo' => $texto('Código', ['descripcion' => 'Ej.: 2a.2b, 2c.'], 40),
    'body' => [
      'tipo' => 'text_with_summary',
      'etiqueta' => 'Descripción de la meta',
      'requerido' => TRUE,
      'instancia' => ['display_summary' => FALSE, 'allowed_formats' => []],
      'widget' => ['type' => 'text_textarea', 'settings' => ['rows' => 5, 'placeholder' => '']],
      'formatter' => ['type' => 'text_default', 'settings' => []],
    ],
    'field_proyecto' => $ref_termino('Proyecto institucional', ['proyecto_institucional']),
    'field_tipo_meta' => $lista('Tipo de meta', [
      'institucional' => 'Institucional',
      'programa' => 'Programa',
      'institucional_programa' => 'Institucional y de programa',
    ]),
    'field_peso' => $decimal('Peso', 6, 4),
    'field_actividades' => $largo_texto('Actividades'),
    'field_recursos' => $texto('Recursos', [], 250),
    'field_hallazgos' => $ref_nodo('Hallazgos que atiende', ['hallazgo'], $multiple + [
      'widget' => ['type' => 'entity_reference_autocomplete', 'settings' => [
        'match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => '',
      ]],
    ]),
    'field_responsables' => $ref_termino('Responsables', ['responsable'], $multiple + [
      'widget' => ['type' => 'entity_reference_autocomplete_tags', 'settings' => [
        'match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => '',
      ]],
    ]),
    'field_indicadores' => $parrafos('Indicadores', 'indicador', 'Indicador', 'Indicadores'),
  ],
  'seguimiento' => [
    'field_meta' => $ref_nodo('Meta', ['meta'], $requerido),
    'field_periodo' => $texto('Periodo', $requerido + ['descripcion' => 'Ej.: 2025-1.'], 20),
    'field_avance' => $decimal('Avance', 5, 2, ['descripcion' => 'Entre 0 y 1.'], 0, 1),
    'field_actividades' => $largo_texto('Actividades'),
    'field_medios_verificacion' => $largo_texto('Medios de verificación'),
    'field_verificacion' => $largo_texto('Verificación'),
    'field_observaciones' => $largo_texto('Observaciones'),
    'field_reportado_por' => $texto('Reportado por', [], 120),
    'field_verificado_por' => $texto('Verificado por', [], 120),
    'field_estado' => $lista('Estado', $estado_union, $requerido),
  ],
];

$campos['paragraph'] = [
  'participacion' => [
    'field_estamento' => $ref_termino('Estamento', ['estamento'], $requerido),
    'field_instrumento' => $lista('Instrumento', [
      'encuesta' => 'Encuesta',
      'taller' => 'Taller',
      'grupo_focal' => 'Grupo focal',
      'entrevista' => 'Entrevista',
    ]),
    'field_participantes' => $entero('Participantes', [], 0),
    'field_fecha_aplicacion' => $fecha('Fecha de aplicación'),
  ],
  'indicador' => [
    'field_nombre' => $texto('Nombre', $requerido, 250),
    'field_unidad' => $texto('Unidad', [], 40),
    'field_linea_base' => $texto('Línea base', [], 120),
    'field_programacion' => $parrafos('Programación anual', 'programacion_anual', 'Programación', 'Programaciones'),
  ],
  'programacion_anual' => [
    'field_anio' => $entero('Año', $requerido, 2000, 2100),
    'field_programado' => $decimal('Programado', 12, 4),
    'field_logrado' => $decimal('Logrado', 12, 4),
  ],
];

$campos['media'] = [
  'documento' => [
    'field_categoria' => $lista('Categoría', [
      'normatividad' => 'Normatividad',
      'documento_base' => 'Documento base',
      'informe' => 'Informe',
      'acto_administrativo' => 'Acto administrativo',
      'soporte' => 'Soporte',
    ], $requerido),
    'field_version' => $texto('Versión', [], 20),
    'field_fecha_publicacion' => $fecha('Fecha de publicación'),
    'field_proceso' => $ref_nodo('Proceso', ['proceso']),
    'field_lineamiento' => $ref_nodo('Lineamiento', ['lineamiento'], [
      'widget' => ['type' => 'options_select', 'settings' => []],
    ]),
    'field_enlace' => $enlace('Enlace externo', [
      'descripcion' => 'Para documentos que no se suben al sitio.',
    ]),
  ],
];

// ---------------------------------------------------------------------------
// Ejecución de los pasos 1 a 4: contenedores (bundles).
// ---------------------------------------------------------------------------
$etapa = 'Vocabularios';
foreach ($vocabularios as $id => $nombre) {
  if (!Vocabulary::load($id)) {
    Vocabulary::create(['vid' => $id, 'name' => $nombre, 'description' => ''])->save();
    $contar('Vocabularios', $id);
  }
}

foreach ($tipos_nodo as $id => $nombre) {
  if (!NodeType::load($id)) {
    NodeType::create([
      'type' => $id,
      'name' => $nombre,
      'description' => '',
      'new_revision' => TRUE,
      'preview_mode' => 0,
      'display_submitted' => FALSE,
    ])->save();
    $contar('Tipos de contenido', $id);
  }
}

foreach ($tipos_paragraph as $id => $nombre) {
  if (!ParagraphsType::load($id)) {
    ParagraphsType::create(['id' => $id, 'label' => $nombre])->save();
    $contar('Tipos de paragraph', $id);
  }
}

foreach ($tipos_media as $id => $nombre) {
  if (!MediaType::load($id)) {
    $tipo_media = MediaType::create([
      'id' => $id,
      'label' => $nombre,
      'description' => '',
      'source' => 'file',
      'queue_thumbnail_downloads' => FALSE,
      'new_revision' => TRUE,
      'field_map' => ['name' => 'name'],
    ]);
    $tipo_media->save();
    // Campo de origen (archivo) con la configuración propia del documento.
    $campo_origen = $tipo_media->getSource()->createSourceField($tipo_media);
    $campo_origen->getFieldStorageDefinition()->save();
    $campo_origen->setLabel('Archivo')
      ->setSetting('file_extensions', 'pdf doc docx xls xlsx ppt pptx odt ods txt zip')
      ->setSetting('file_directory', 'documentos/[date:custom:Y-m]')
      ->save();
    $tipo_media->set('source_configuration', ['source_field' => $campo_origen->getName()])->save();
    $contar('Tipos de media', $id);
  }
}

// ---------------------------------------------------------------------------
// Paso 5: almacenamiento e instancia de cada campo.
// ---------------------------------------------------------------------------
$repositorio = \Drupal::service('entity_display.repository');
foreach ($campos as $tipo_entidad => $por_bundle) {
  foreach ($por_bundle as $bundle => $lista_campos) {
    $peso = 0;
    foreach ($lista_campos as $nombre => $spec) {
      $peso += 1;

      $almacenamiento = FieldStorageConfig::loadByName($tipo_entidad, $nombre);
      if (!$almacenamiento) {
        $almacenamiento = FieldStorageConfig::create([
          'field_name' => $nombre,
          'entity_type' => $tipo_entidad,
          'type' => $spec['tipo'],
          'cardinality' => $spec['cardinalidad'] ?? 1,
          'settings' => $spec['storage'] ?? [],
        ]);
        $almacenamiento->save();
        $contar('Almacenamientos de campo', "$tipo_entidad.$nombre");
      }

      if (!FieldConfig::loadByName($tipo_entidad, $bundle, $nombre)) {
        $configuracion = [
          'field_storage' => $almacenamiento,
          'bundle' => $bundle,
          'label' => $spec['etiqueta'],
          'required' => !empty($spec['requerido']),
          'description' => $spec['descripcion'] ?? '',
          'settings' => array_filter($spec['instancia'] ?? [], fn($valor) => $valor !== NULL),
        ];
        if (isset($spec['valor_defecto'])) {
          $configuracion['default_value'] = [['value' => $spec['valor_defecto']]];
        }
        if (isset($spec['valor_defecto_texto'])) {
          $configuracion['default_value'] = [['value' => $spec['valor_defecto_texto']]];
        }
        FieldConfig::create($configuracion)->save();
        $contar('Instancias de campo', "$tipo_entidad.$bundle.$nombre");
      }
    }
  }
}

// ---------------------------------------------------------------------------
// Paso 6: visualización de formulario y visualización por defecto.
// ---------------------------------------------------------------------------
$widget_por_tipo = [
  'string' => ['type' => 'string_textfield', 'settings' => ['size' => 60, 'placeholder' => '']],
  'text_long' => ['type' => 'text_textarea', 'settings' => ['rows' => 5, 'placeholder' => '']],
  'integer' => ['type' => 'number', 'settings' => ['placeholder' => '']],
  'decimal' => ['type' => 'number', 'settings' => ['placeholder' => '']],
  'boolean' => ['type' => 'boolean_checkbox', 'settings' => ['display_label' => TRUE]],
  'list_string' => ['type' => 'options_select', 'settings' => []],
  'datetime' => ['type' => 'datetime_default', 'settings' => []],
  'image' => ['type' => 'image_image', 'settings' => [
    'progress_indicator' => 'throbber',
    'preview_image_style' => 'thumbnail',
  ]],
  'link' => ['type' => 'link_default', 'settings' => ['placeholder_url' => '', 'placeholder_title' => '']],
];
$widget_ref_nodo = ['type' => 'entity_reference_autocomplete', 'settings' => [
  'match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => '',
]];
// Vocabularios pequeños (catálogos) se eligen de una lista; `elemento_modelo`
// es extenso y se busca con autocompletado.
$widget_ref_termino = fn(array $objetivos): array => in_array('elemento_modelo', $objetivos, TRUE)
  ? $widget_ref_nodo
  : ['type' => 'options_select', 'settings' => []];

$formatter_por_tipo = [
  'string' => ['type' => 'string', 'settings' => ['link_to_entity' => FALSE]],
  'text_long' => ['type' => 'text_default', 'settings' => []],
  'boolean' => ['type' => 'boolean', 'settings' => ['format' => 'default', 'format_custom_false' => '', 'format_custom_true' => '']],
  'list_string' => ['type' => 'list_default', 'settings' => []],
  'datetime' => ['type' => 'datetime_custom', 'settings' => ['date_format' => 'd/m/Y', 'timezone_override' => '']],
  'integer' => ['type' => 'number_integer', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => TRUE]],
  'image' => ['type' => 'image', 'settings' => ['image_link' => '', 'image_style' => '', 'image_loading' => ['attribute' => 'lazy']]],
  'link' => ['type' => 'link', 'settings' => [
    'trim_length' => 80, 'url_only' => FALSE, 'url_plain' => FALSE, 'rel' => '', 'target' => '',
  ]],
  'entity_reference' => ['type' => 'entity_reference_label', 'settings' => ['link' => TRUE]],
];

// Componentes base que cada tipo de entidad necesita en sus pantallas.
$base_formulario = [
  'node' => [
    'title' => ['type' => 'string_textfield', 'weight' => -5, 'settings' => ['size' => 60, 'placeholder' => '']],
    'status' => ['type' => 'boolean_checkbox', 'weight' => 120, 'settings' => ['display_label' => TRUE]],
    'path' => ['type' => 'path', 'weight' => 30, 'settings' => []],
  ],
  'taxonomy_term' => [
    'name' => ['type' => 'string_textfield', 'weight' => -5, 'settings' => ['size' => 60, 'placeholder' => '']],
    'description' => ['type' => 'text_textarea', 'weight' => 0, 'settings' => ['rows' => 5, 'placeholder' => '']],
    'status' => ['type' => 'boolean_checkbox', 'weight' => 100, 'settings' => ['display_label' => TRUE]],
    'path' => ['type' => 'path', 'weight' => 30, 'settings' => []],
  ],
  'media' => [
    'name' => ['type' => 'string_textfield', 'weight' => -5, 'settings' => ['size' => 60, 'placeholder' => '']],
    'status' => ['type' => 'boolean_checkbox', 'weight' => 100, 'settings' => ['display_label' => TRUE]],
  ],
  'paragraph' => [],
];
$base_vista = [
  'node' => [
    'links' => ['weight' => 100, 'settings' => [], 'label' => 'hidden'],
  ],
  'taxonomy_term' => [
    'description' => ['type' => 'text_default', 'weight' => 0, 'label' => 'hidden', 'settings' => []],
  ],
  'media' => [],
  'paragraph' => [],
];

foreach ($campos as $tipo_entidad => $por_bundle) {
  foreach ($por_bundle as $bundle => $lista_campos) {
    $form = $repositorio->getFormDisplay($tipo_entidad, $bundle, 'default');
    $vista = $repositorio->getViewDisplay($tipo_entidad, $bundle, 'default');
    $form_cambiado = $form->isNew();
    $vista_cambiada = $vista->isNew();

    foreach ($base_formulario[$tipo_entidad] as $nombre => $componente) {
      if (!$form->getComponent($nombre)) {
        $form->setComponent($nombre, $componente);
        $form_cambiado = TRUE;
      }
    }
    foreach ($base_vista[$tipo_entidad] as $nombre => $componente) {
      if (!$vista->getComponent($nombre)) {
        $vista->setComponent($nombre, $componente);
        $vista_cambiada = TRUE;
      }
    }

    // El campo de origen del media (archivo) también se muestra.
    if ($tipo_entidad === 'media') {
      $origen = MediaType::load($bundle)->getSource()->getConfiguration()['source_field'] ?? NULL;
      if ($origen && !$form->getComponent($origen)) {
        $form->setComponent($origen, ['type' => 'file_generic', 'weight' => -4, 'settings' => ['progress_indicator' => 'throbber']]);
        $form_cambiado = TRUE;
      }
      if ($origen && !$vista->getComponent($origen)) {
        $vista->setComponent($origen, ['type' => 'file_default', 'weight' => -4, 'label' => 'visually_hidden', 'settings' => ['use_description_as_link_text' => TRUE]]);
        $vista_cambiada = TRUE;
      }
    }

    $peso = 0;
    foreach ($lista_campos as $nombre => $spec) {
      $peso += 1;
      $tipo = $spec['tipo'];

      if (!$form->getComponent($nombre) && empty($spec['oculto_formulario'])) {
        if (isset($spec['widget'])) {
          $widget = $spec['widget'];
        }
        elseif ($tipo === 'entity_reference') {
          $objetivo = $spec['storage']['target_type'];
          $widget = $objetivo === 'taxonomy_term'
            ? $widget_ref_termino(array_keys($spec['instancia']['handler_settings']['target_bundles']))
            : $widget_ref_nodo;
        }
        else {
          $widget = $widget_por_tipo[$tipo];
        }
        $form->setComponent($nombre, $widget + ['weight' => $peso]);
        $form_cambiado = TRUE;
      }

      if (!$vista->getComponent($nombre)) {
        $formatter = $spec['formatter'] ?? $formatter_por_tipo[$tipo] ?? NULL;
        if ($tipo === 'decimal') {
          $formatter = ['type' => 'number_decimal', 'settings' => [
            'thousand_separator' => '',
            'decimal_separator' => ',',
            'scale' => $spec['storage']['scale'],
            'prefix_suffix' => TRUE,
          ]];
        }
        $vista->setComponent($nombre, $formatter + [
          'weight' => $peso,
          'label' => in_array($tipo, ['text_long', 'text_with_summary', 'entity_reference_revisions', 'image'], TRUE)
            ? 'above' : 'inline',
        ]);
        $vista_cambiada = TRUE;
      }
    }

    if ($form_cambiado) {
      $form->save();
      $contar('Visualizaciones de formulario', "$tipo_entidad.$bundle");
    }
    if ($vista_cambiada) {
      $vista->save();
      $contar('Visualizaciones por defecto', "$tipo_entidad.$bundle");
    }
  }
}

// ---------------------------------------------------------------------------
// Resumen.
// ---------------------------------------------------------------------------
print "\n=== Resumen de lo creado en esta ejecución ===\n";
$secciones = [
  'Vocabularios', 'Tipos de contenido', 'Tipos de paragraph', 'Tipos de media',
  'Almacenamientos de campo', 'Instancias de campo',
  'Visualizaciones de formulario', 'Visualizaciones por defecto',
];
foreach ($secciones as $seccion) {
  $creados = $resumen[$seccion] ?? [];
  printf("%-32s %3d\n", $seccion, count($creados));
}
if (!$resumen) {
  print "(Nada que crear: el modelo ya estaba completo.)\n";
}
print "\n=== Totales del modelo en el sitio ===\n";
printf("%-32s %3d\n", 'Vocabularios', count(array_intersect_key(Vocabulary::loadMultiple(), $vocabularios)));
printf("%-32s %3d\n", 'Tipos de contenido', count(array_intersect_key(NodeType::loadMultiple(), $tipos_nodo)));
printf("%-32s %3d\n", 'Tipos de paragraph', count(array_intersect_key(ParagraphsType::loadMultiple(), $tipos_paragraph)));
printf("%-32s %3d\n", 'Tipos de media', count(array_intersect_key(MediaType::loadMultiple(), $tipos_media)));
$total_instancias = 0;
foreach (array_keys($campos) as $tipo_entidad) {
  foreach (array_keys($campos[$tipo_entidad]) as $bundle) {
    $total_instancias += count(\Drupal::service('entity_field.manager')
      ->getFieldDefinitions($tipo_entidad, $bundle)) - count(\Drupal::service('entity_field.manager')
      ->getBaseFieldDefinitions($tipo_entidad));
  }
}
printf("%-32s %3d\n", 'Instancias de campo', $total_instancias);
