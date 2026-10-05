<?php

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Base de las pruebas Kernel: crea el subconjunto del modelo que se usa.
 */
abstract class UnillanosKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'options',
    'node',
    'taxonomy',
    'unillanos_autoeval',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node']);

    foreach (['grado_cumplimiento', 'elemento_modelo', 'sede'] as $vid) {
      Vocabulary::create(['vid' => $vid, 'name' => $vid])->save();
    }
    $tipos = [
      'lineamiento',
      'programa',
      'proceso',
      'valoracion',
      'hallazgo',
      'plan_mejoramiento',
      'meta',
      'seguimiento',
    ];
    foreach ($tipos as $tipo) {
      NodeType::create(['type' => $tipo, 'name' => $tipo])->save();
    }

    $this->crearCampo('taxonomy_term', 'grado_cumplimiento', 'field_valoracion_min', 'decimal', [
      'precision' => 2,
      'scale' => 1,
    ]);
    $this->crearCampo('taxonomy_term', 'grado_cumplimiento', 'field_valoracion_max', 'decimal', [
      'precision' => 2,
      'scale' => 1,
    ]);
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_clave', 'string');
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_lineamiento', 'entity_reference', ['target_type' => 'node']);

    $this->crearCampo('node', 'lineamiento', 'field_norma', 'string');
    $this->crearCampo('node', 'lineamiento', 'field_tipo_proceso', 'list_string', [
      'allowed_values' => ['acreditacion' => 'Acreditación', 'registro_calificado' => 'Registro calificado'],
    ]);
    $this->crearCampo('node', 'proceso', 'field_lineamiento', 'entity_reference', ['target_type' => 'node']);

    $this->crearCampo('node', 'programa', 'field_clave', 'string');
    $this->crearCampo('node', 'proceso', 'field_sede', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'proceso', 'field_programa', 'entity_reference', ['target_type' => 'node']);
    $estados = [
      'planeado' => 'Planeado',
      'en_curso' => 'En curso',
      'finalizado' => 'Finalizado',
      'borrador' => 'Borrador',
      'reportado' => 'Reportado',
      'verificado' => 'Verificado',
    ];
    $this->crearCampo('node', 'proceso', 'field_estado', 'list_string', ['allowed_values' => $estados]);
    $this->crearCampo('node', 'seguimiento', 'field_estado', 'list_string', ['allowed_values' => $estados]);

    $this->crearCampo('node', 'valoracion', 'field_proceso', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('node', 'valoracion', 'field_elemento', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'valoracion', 'field_valoracion', 'decimal', ['precision' => 3, 'scale' => 2]);
    $this->crearCampo('node', 'valoracion', 'field_grado', 'entity_reference', ['target_type' => 'taxonomy_term']);

    $this->crearCampo('node', 'hallazgo', 'field_proceso', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('node', 'hallazgo', 'field_elemento', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'hallazgo', 'field_destino', 'list_string', [
      'allowed_values' => [
        'plan_mejoramiento' => 'Plan de mejoramiento',
        'plan_accion' => 'Plan de acción',
        'ninguno' => 'Ninguno',
      ],
    ]);

    $this->crearCampo('node', 'plan_mejoramiento', 'field_proceso', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('node', 'meta', 'field_plan', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('node', 'meta', 'field_hallazgos', 'entity_reference', ['target_type' => 'node'], FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $this->crearCampo('node', 'meta', 'field_peso', 'decimal', ['precision' => 6, 'scale' => 4]);
    $this->crearCampo('node', 'seguimiento', 'field_meta', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('node', 'seguimiento', 'field_periodo', 'string');
    $this->crearCampo('node', 'seguimiento', 'field_avance', 'decimal', ['precision' => 5, 'scale' => 2]);
  }

  /**
   * Crea un campo con su almacenamiento si todavía no existe.
   *
   * @param string $tipo_entidad
   *   Tipo de entidad (node, taxonomy_term).
   * @param string $bundle
   *   Tipo de contenido o vocabulario.
   * @param string $nombre
   *   Nombre de máquina del campo.
   * @param string $tipo_campo
   *   Tipo de campo.
   * @param array $ajustes
   *   Ajustes del almacenamiento.
   * @param int $cardinalidad
   *   Cardinalidad del campo.
   */
  protected function crearCampo(string $tipo_entidad, string $bundle, string $nombre, string $tipo_campo, array $ajustes = [], int $cardinalidad = 1): void {
    if (!FieldStorageConfig::loadByName($tipo_entidad, $nombre)) {
      FieldStorageConfig::create([
        'field_name' => $nombre,
        'entity_type' => $tipo_entidad,
        'type' => $tipo_campo,
        'settings' => $ajustes,
        'cardinality' => $cardinalidad,
      ])->save();
    }
    FieldConfig::create([
      'field_name' => $nombre,
      'entity_type' => $tipo_entidad,
      'bundle' => $bundle,
    ])->save();
  }

  /**
   * Carga los grados de cumplimiento desde el CSV de semillas.
   */
  protected function cargarGrados(): void {
    $ruta = DRUPAL_ROOT . '/../../datos/semillas/grados_cumplimiento.csv';
    $this->assertFileExists($ruta);
    $archivo = fopen($ruta, 'r');
    $encabezado = fgetcsv($archivo, 0, ',', '"', '');
    while (($fila = fgetcsv($archivo, 0, ',', '"', '')) !== FALSE) {
      $datos = array_combine($encabezado, $fila);
      Term::create([
        'vid' => 'grado_cumplimiento',
        'name' => $datos['nombre'],
        'weight' => (int) $datos['orden'],
        'field_valoracion_min' => $datos['valoracion_min'],
        'field_valoracion_max' => $datos['valoracion_max'],
      ])->save();
    }
    fclose($archivo);
  }

  /**
   * Crea un término de elemento del modelo.
   *
   * @param string $nombre
   *   Nombre del elemento.
   * @param string $clave
   *   Clave de importación del elemento.
   * @param array $padres
   *   IDs de los términos padre.
   * @param int|string|null $lineamiento
   *   ID del lineamiento (modelo) al que pertenece, si se indica.
   */
  protected function crearElemento(string $nombre, string $clave, array $padres = [], int|string|NULL $lineamiento = NULL): Term {
    $termino = Term::create([
      'vid' => 'elemento_modelo',
      'name' => $nombre,
      'field_clave' => $clave,
      'parent' => $padres,
      'field_lineamiento' => $lineamiento,
    ]);
    $termino->save();
    return $termino;
  }

  /**
   * Crea un lineamiento (modelo) con su norma y tipo de proceso.
   */
  protected function crearLineamiento(string $norma, string $tipo_proceso = 'acreditacion'): Node {
    return $this->crearNodo('lineamiento', [
      'title' => $norma,
      'field_norma' => $norma,
      'field_tipo_proceso' => $tipo_proceso,
    ]);
  }

  /**
   * Crea un nodo con los valores indicados.
   */
  protected function crearNodo(string $tipo, array $valores = []): Node {
    $nodo = Node::create($valores + ['type' => $tipo, 'title' => $tipo]);
    $nodo->save();
    return $nodo;
  }

}
