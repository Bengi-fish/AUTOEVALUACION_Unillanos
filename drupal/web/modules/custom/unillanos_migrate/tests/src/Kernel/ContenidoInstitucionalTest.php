<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_migrate\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\MediaType;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba la carga de lo institucional con las semillas (Fase 9, etapa 2).
 *
 * Dependencias, normas, procesos institucionales (con sede, sin programa) y
 * los documentos de esos procesos. Correr la migración dos veces no duplica.
 */
#[Group('unillanos_migrate')]
#[RunTestsInSeparateProcesses]
class ContenidoInstitucionalTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'sdc',
    'field',
    'text',
    'filter',
    'options',
    'node',
    'taxonomy',
    'file',
    'link',
    'media',
    'image',
    'entity_reference_revisions',
    'paragraphs',
    'migrate',
    'migrate_plus',
    'migrate_tools',
    'migrate_source_csv',
    'unillanos_autoeval',
    'unillanos_migrate',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('migrate_tools', ['migrate_tools_sync_source_ids']);
    $this->installConfig(['field', 'node']);
    foreach (['dependencia', 'norma', 'proceso'] as $tipo) {
      NodeType::create(['type' => $tipo, 'name' => $tipo])->save();
    }
    Vocabulary::create(['vid' => 'sede', 'name' => 'Sede'])->save();
    $medio = MediaType::create(['id' => 'documento', 'label' => 'Documento', 'source' => 'file']);
    $medio->save();
    $campo = $medio->getSource()->createSourceField($medio);
    $almacen = $campo->getFieldStorageDefinition();
    if ($almacen instanceof FieldStorageConfig) {
      $almacen->save();
    }
    $campo->set('required', FALSE)->save();
    $medio->set('source_configuration', ['source_field' => $campo->getName()])->save();
    $origenes = ['externo' => 'Externo', 'interno' => 'Interno'];
    $a_termino = ['target_type' => 'taxonomy_term'];
    $a_nodo = ['target_type' => 'node'];
    $campos = [
      ['node', 'dependencia', 'field_descripcion', 'string_long', []],
      ['node', 'dependencia', 'field_orden', 'integer', []],
      ['node', 'norma', 'field_origen_norma', 'list_string', ['allowed_values' => $origenes]],
      ['node', 'norma', 'field_tipo_norma', 'string', []],
      ['node', 'norma', 'field_numero_fecha', 'string', []],
      ['node', 'norma', 'field_entidad', 'string', []],
      ['node', 'norma', 'field_epigrafe', 'string_long', []],
      ['node', 'norma', 'field_capitulos', 'string', []],
      ['node', 'norma', 'field_aplicacion', 'string_long', []],
      ['node', 'proceso', 'field_tipo_proceso', 'string', []],
      ['node', 'proceso', 'field_ambito', 'string', []],
      ['node', 'proceso', 'field_sede', 'entity_reference', $a_termino],
      ['node', 'proceso', 'field_programa', 'entity_reference', $a_nodo],
      ['node', 'proceso', 'field_lineamiento', 'entity_reference', $a_nodo],
      ['node', 'proceso', 'field_periodo_evaluado', 'string', []],
      ['node', 'proceso', 'field_estado', 'string', []],
      ['taxonomy_term', 'sede', 'field_municipio', 'string', []],
      ['taxonomy_term', 'sede', 'field_activa', 'boolean', []],
      ['media', 'documento', 'field_categoria', 'string', []],
      ['media', 'documento', 'field_enlace', 'link', []],
      ['media', 'documento', 'field_proceso', 'entity_reference', $a_nodo],
    ];
    foreach ($campos as [$entidad, $bundle, $nombre, $tipo, $ajustes]) {
      if (!FieldStorageConfig::loadByName($entidad, $nombre)) {
        FieldStorageConfig::create([
          'field_name' => $nombre,
          'entity_type' => $entidad,
          'type' => $tipo,
          'settings' => $ajustes,
        ])->save();
      }
      FieldConfig::create([
        'field_name' => $nombre,
        'entity_type' => $entidad,
        'bundle' => $bundle,
        'label' => $nombre,
      ])->save();
    }
  }

  /**
   * Importa una migración y comprueba que terminó bien.
   */
  protected function importar(string $id): void {
    $migracion = $this->container->get('plugin.manager.migration')->createInstance($id);
    $resultado = (new MigrateExecutable($migracion, new MigrateMessage()))->import();
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, $resultado, $id);
  }

  /**
   * Cuenta los nodos de un tipo.
   */
  protected function contar(string $tipo): int {
    return (int) $this->container->get('entity_type.manager')->getStorage('node')
      ->getQuery()->accessCheck(FALSE)->condition('type', $tipo)->count()->execute();
  }

  /**
   * Cuenta los medios documento.
   */
  protected function contarDocumentos(): int {
    return (int) $this->container->get('entity_type.manager')->getStorage('media')
      ->getQuery()->accessCheck(FALSE)->condition('bundle', 'documento')->count()->execute();
  }

  /**
   * Carga todo lo institucional (dos veces) y comprueba lo guardado.
   */
  public function testCargaIdempotente(): void {
    $ids = ['dependencias', 'normas', 'sedes', 'procesos_institucionales', 'documentos_procesos_institucionales'];
    foreach ([1, 2] as $vuelta) {
      foreach ($ids as $id) {
        $this->importar($id);
      }
      $this->assertSame(25, $this->contar('dependencia'), "vuelta $vuelta");
      $this->assertSame(40, $this->contar('norma'), "vuelta $vuelta");
      $this->assertSame(4, $this->contar('proceso'), "vuelta $vuelta");
      $this->assertSame(14, $this->contarDocumentos(), "vuelta $vuelta");
    }
    $almacen = $this->container->get('entity_type.manager')->getStorage('node');
    // Las dependencias salen en el orden del sitio de Acreditación.
    $ids_dep = $almacen->getQuery()->accessCheck(FALSE)->condition('type', 'dependencia')->sort('field_orden')->execute();
    $primera = $almacen->load((int) reset($ids_dep));
    $this->assertSame('Admisiones', $primera->label());
    $this->assertSame(1, (int) $primera->get('field_orden')->value);
    // La norma conserva sus textos y su título sale del tipo y el número.
    $ids_norma = $almacen->getQuery()->accessCheck(FALSE)->condition('type', 'norma')
      ->condition('title', 'Ley 30 de 1992')->execute();
    $this->assertCount(1, $ids_norma);
    $ley = $almacen->load((int) reset($ids_norma));
    $this->assertSame('externo', $ley->get('field_origen_norma')->value);
    $this->assertSame('Congreso de la República', $ley->get('field_entidad')->value);
    // Los procesos tienen sede, ningún programa y ningún modelo.
    foreach ($almacen->loadMultiple($almacen->getQuery()->accessCheck(FALSE)->condition('type', 'proceso')->execute()) as $proceso) {
      $this->assertFalse($proceso->get('field_sede')->isEmpty(), $proceso->label());
      $this->assertTrue($proceso->get('field_programa')->isEmpty(), $proceso->label());
      $this->assertTrue($proceso->get('field_lineamiento')->isEmpty(), $proceso->label());
      $this->assertSame('institucional', $proceso->get('field_ambito')->value);
    }
    // Cada documento lleva enlace https y ningún archivo.
    foreach ($this->container->get('entity_type.manager')->getStorage('media')->loadMultiple() as $documento) {
      $this->assertTrue($documento->get('field_media_file')->isEmpty(), $documento->label());
      $this->assertStringStartsWith('https://drive.google.com/', $documento->get('field_enlace')->uri, $documento->label());
      $this->assertFalse($documento->get('field_proceso')->isEmpty(), $documento->label());
    }
    $this->assertInstanceOf(Node::class, $primera);
  }

}
