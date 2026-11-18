<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Base de las pruebas de «Institucional»: tipos, campos y datos de prueba.
 */
abstract class InstitucionalBaseKernel extends UnillanosKernelTestBase {

  use UserCreationTrait;

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
    'file',
    'image',
    'link',
    'media',
    'sdc',
    'unillanos_autoeval',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $tipo = MediaType::create(['id' => 'documento', 'label' => 'Documento', 'source' => 'file']);
    $tipo->save();
    $campo = $tipo->getSource()->createSourceField($tipo);
    $almacen = $campo->getFieldStorageDefinition();
    if ($almacen instanceof FieldStorageConfig) {
      $almacen->save();
    }
    $campo->set('required', FALSE)->save();
    $tipo->set('source_configuration', ['source_field' => $campo->getName()])->save();
    $categorias = [
      'informe' => 'Informe',
      'soporte' => 'Soporte',
      'directivos' => 'Directivos',
      'dependencia' => 'Dependencia',
    ];
    $this->crearCampo('media', 'documento', 'field_categoria', 'list_string', ['allowed_values' => $categorias]);
    $this->crearCampo('media', 'documento', 'field_fecha_publicacion', 'string');
    $this->crearCampo('media', 'documento', 'field_lineamiento', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('media', 'documento', 'field_enlace', 'link');
    $this->crearCampo('media', 'documento', 'field_proceso', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('media', 'documento', 'field_dependencia', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('media', 'documento', 'field_programa', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('media', 'documento', 'field_facultad', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'dependencia', 'field_descripcion', 'string_long');
    $this->crearCampo('node', 'dependencia', 'field_orden', 'integer');
    $origenes = ['externo' => 'Externo', 'interno' => 'Interno'];
    $this->crearCampo('node', 'norma', 'field_origen_norma', 'list_string', ['allowed_values' => $origenes]);
    foreach (['tipo_norma', 'numero_fecha', 'entidad', 'capitulos'] as $nombre) {
      $this->crearCampo('node', 'norma', 'field_' . $nombre, 'string');
    }
    $this->crearCampo('node', 'norma', 'field_epigrafe', 'string_long');
    $this->crearCampo('node', 'norma', 'field_aplicacion', 'string_long');
    $this->crearCampo('node', 'norma', 'field_documento', 'entity_reference', ['target_type' => 'media']);
    $this->crearCampo('node', 'programa', 'field_nivel', 'list_string', [
      'allowed_values' => ['pregrado' => 'Pregrado', 'maestria' => 'Maestría'],
    ]);
    $this->crearCampo('node', 'proceso', 'field_periodo_evaluado', 'string');
    $this->crearCampo('node', 'proceso', 'field_resultado', 'string');
    $this->crearCampo('node', 'proceso', 'field_tipo_proceso', 'string');
    Vocabulary::create(['vid' => 'facultad', 'name' => 'Facultad'])->save();
    $this->crearCampo('taxonomy_term', 'facultad', 'field_sigla', 'string');
    $this->setUpCurrentUser([], ['access content', 'view media']);
  }

  /**
   * Crea un documento con enlace externo.
   *
   * @param string $nombre
   *   Nombre del documento.
   * @param array<string, mixed> $extra
   *   Otros valores del medio.
   * @param string $url
   *   Dirección https del enlace.
   */
  protected function enlace(string $nombre, array $extra = [], string $url = 'https://drive.google.com/drive/folders/abc'): Media {
    $medio = Media::create($extra + [
      'bundle' => 'documento',
      'name' => $nombre,
      'status' => 1,
      'field_categoria' => 'dependencia',
      'field_enlace' => ['uri' => $url],
    ]);
    $medio->save();
    return $medio;
  }

  /**
   * Crea una facultad.
   */
  protected function facultad(string $nombre, string $sigla): Term {
    $termino = Term::create(['vid' => 'facultad', 'name' => $nombre, 'field_sigla' => $sigla]);
    $termino->save();
    return $termino;
  }

}
