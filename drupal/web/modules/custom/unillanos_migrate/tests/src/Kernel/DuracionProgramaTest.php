<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_migrate\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba que la columna opcional «duracion» de programas.csv llena el campo.
 */
#[Group('unillanos_migrate')]
#[RunTestsInSeparateProcesses]
class DuracionProgramaTest extends KernelTestBase {

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
   * Carpeta temporal con semillas/programas.csv.
   */
  protected string $datos;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('migrate_tools', ['migrate_tools_sync_source_ids']);
    $this->installConfig(['field', 'node']);
    NodeType::create(['type' => 'programa', 'name' => 'Programa'])->save();
    $campos = [
      'field_clave' => ['string', []],
      'field_nivel' => ['list_string', ['allowed_values' => ['pregrado' => 'Pregrado']]],
      'field_duracion' => ['integer', ['unsigned' => TRUE]],
    ];
    foreach ($campos as $nombre => [$tipo, $ajustes]) {
      FieldStorageConfig::create([
        'field_name' => $nombre,
        'entity_type' => 'node',
        'type' => $tipo,
        'settings' => $ajustes,
      ])->save();
      FieldConfig::create([
        'field_name' => $nombre,
        'entity_type' => 'node',
        'bundle' => 'programa',
        'label' => $nombre,
      ])->save();
    }
    $this->datos = realpath(sys_get_temp_dir()) . '/unillanos_duracion_' . bin2hex(random_bytes(4));
    mkdir($this->datos . '/semillas', 0777, TRUE);
    // Las migraciones de facultades y sedes se consultan aunque no haya filas.
    file_put_contents($this->datos . '/semillas/facultades.csv', "id,nombre,sigla\n");
    file_put_contents($this->datos . '/semillas/sedes.csv', "id,nombre,municipio,activa\n");
    new Settings(['unillanos_datos' => $this->datos]);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach (['programas', 'facultades', 'sedes'] as $nombre) {
      @unlink($this->datos . '/semillas/' . $nombre . '.csv');
    }
    @rmdir($this->datos . '/semillas');
    @rmdir($this->datos);
    parent::tearDown();
  }

  /**
   * Importa programas.csv con el contenido dado.
   *
   * @param string $csv
   *   Contenido del archivo.
   */
  protected function importar(string $csv): void {
    file_put_contents($this->datos . '/semillas/programas.csv', $csv);
    $migracion = $this->container->get('plugin.manager.migration')->createInstance('programas');
    $resultado = (new MigrateExecutable($migracion, new MigrateMessage()))->import();
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, $resultado);
  }

  /**
   * Carga un programa por su clave.
   */
  protected function programa(string $clave): Node {
    $ids = $this->container->get('entity_type.manager')->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_clave', $clave)
      ->execute();
    $this->assertCount(1, $ids, $clave);
    return Node::load((int) reset($ids));
  }

  /**
   * Un CSV sin la columna importa igual y deja la duración vacía.
   */
  public function testFilaSinLaColumnaImportaIgual(): void {
    $this->importar("id,nombre,nivel,activo\nelectronica,Electrónica,pregrado,1\n");
    $programa = $this->programa('electronica');
    $this->assertSame('Electrónica', $programa->label());
    $this->assertTrue($programa->get('field_duracion')->isEmpty());
  }

  /**
   * Con la columna, la fila llena el campo y la fila vacía lo deja vacío.
   */
  public function testColumnaLlenaElCampo(): void {
    $this->importar("id,nombre,nivel,duracion,activo\nelectronica,Electrónica,pregrado,10,1\nenfermeria,Enfermería,pregrado,,1\n");
    $this->assertSame(10, (int) $this->programa('electronica')->get('field_duracion')->value);
    $this->assertTrue($this->programa('enfermeria')->get('field_duracion')->isEmpty());
  }

}
