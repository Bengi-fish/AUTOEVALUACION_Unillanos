<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_migrate\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba la columna opcional «enlace» de documentos.csv (Fase 9, etapa 1).
 *
 * Un documento con enlace no lleva archivo; sin la columna, o con la celda
 * vacía, el documento es un archivo como siempre.
 */
#[Group('unillanos_migrate')]
#[RunTestsInSeparateProcesses]
class DocumentosEnlaceTest extends KernelTestBase {

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
   * Raíz temporal del repositorio de prueba.
   */
  protected string $raiz;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('migrate_tools', ['migrate_tools_sync_source_ids']);
    $this->installConfig(['field', 'node']);
    NodeType::create(['type' => 'lineamiento', 'name' => 'Lineamiento'])->save();
    $tipo = MediaType::create(['id' => 'documento', 'label' => 'Documento', 'source' => 'file']);
    $tipo->save();
    $campo = $tipo->getSource()->createSourceField($tipo);
    $almacen = $campo->getFieldStorageDefinition();
    if ($almacen instanceof FieldStorageConfig) {
      $almacen->save();
    }
    $campo->set('required', FALSE)->set('settings', ['file_extensions' => 'pdf'] + $campo->getSettings())->save();
    $tipo->set('source_configuration', ['source_field' => $campo->getName()])->save();
    $campos = [
      'field_categoria' => ['string', []],
      'field_version' => ['string', []],
      'field_fecha_publicacion' => ['string', []],
      'field_enlace' => ['link', []],
      'field_lineamiento' => ['entity_reference', ['target_type' => 'node']],
    ];
    foreach ($campos as $nombre => [$tipo_campo, $ajustes]) {
      FieldStorageConfig::create([
        'field_name' => $nombre,
        'entity_type' => 'media',
        'type' => $tipo_campo,
        'settings' => $ajustes,
      ])->save();
      FieldConfig::create([
        'field_name' => $nombre,
        'entity_type' => 'media',
        'bundle' => 'documento',
        'label' => $nombre,
      ])->save();
    }
    $this->raiz = realpath(sys_get_temp_dir()) . '/unillanos_enlace_' . bin2hex(random_bytes(4));
    mkdir($this->raiz . '/docs', 0777, TRUE);
    mkdir($this->raiz . '/datos/semillas', 0777, TRUE);
    file_put_contents($this->raiz . '/docs/informe.pdf', '%PDF-1.4 prueba');
    file_put_contents($this->raiz . '/datos/semillas/lineamientos.csv', "id,nombre,norma,ambito,tipo_proceso,vigente_desde,activo\n");
    new Settings([
      'unillanos_datos' => $this->raiz . '/datos',
      'unillanos_raiz' => $this->raiz,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach (glob($this->raiz . '/datos/semillas/*') ?: [] as $archivo) {
      @unlink($archivo);
    }
    @unlink($this->raiz . '/docs/informe.pdf');
    @rmdir($this->raiz . '/datos/semillas');
    @rmdir($this->raiz . '/datos');
    @rmdir($this->raiz . '/docs');
    @rmdir($this->raiz);
    parent::tearDown();
  }

  /**
   * Importa documentos.csv con el contenido dado.
   *
   * @param string $csv
   *   Contenido del archivo.
   */
  protected function importar(string $csv): void {
    file_put_contents($this->raiz . '/datos/semillas/documentos.csv', $csv);
    foreach (['archivos_generales', 'documentos_generales'] as $id) {
      $migracion = $this->container->get('plugin.manager.migration')->createInstance($id);
      $resultado = (new MigrateExecutable($migracion, new MigrateMessage()))->import();
      $this->assertSame(MigrationInterface::RESULT_COMPLETED, $resultado, $id);
    }
  }

  /**
   * Carga un documento por su nombre.
   */
  protected function documento(string $nombre): Media {
    $ids = $this->container->get('entity_type.manager')->getStorage('media')->getQuery()
      ->accessCheck(FALSE)
      ->condition('name', $nombre)
      ->execute();
    $this->assertCount(1, $ids, $nombre);
    return Media::load((int) reset($ids));
  }

  /**
   * Devuelve los mensajes de validación de un medio.
   *
   * Se ignora el acceso a los archivos referenciados: la prueba corre con un
   * usuario anónimo y solo interesa la regla de archivo o enlace.
   *
   * @return string[]
   *   Ruta y mensaje de cada infracción.
   */
  protected function mensajes(Media $medio): array {
    $mensajes = [];
    foreach ($medio->validate() as $infraccion) {
      if (!str_contains((string) $infraccion->getMessage(), 'referenced entity')) {
        $mensajes[] = $infraccion->getPropertyPath() . ': ' . $infraccion->getMessage();
      }
    }
    return $mensajes;
  }

  /**
   * Sin la columna «enlace» el documento sigue siendo un archivo.
   */
  public function testSinLaColumnaImportaIgual(): void {
    $this->importar("id,nombre,categoria,archivo,version,fecha_publicacion,proceso_id,lineamiento_id,publicado\nINF,Informe,informe,docs/informe.pdf,,,,,1\n");
    $medio = $this->documento('Informe');
    $this->assertFalse($medio->get('field_media_file')->isEmpty());
    $this->assertTrue($medio->get('field_enlace')->isEmpty());
    $this->assertSame([], $this->mensajes($medio));
  }

  /**
   * Con la columna: la fila con enlace no lleva archivo y la vacía sí.
   */
  public function testColumnaConEnlaceConArchivo(): void {
    $this->importar(
      "id,nombre,categoria,archivo,enlace,version,fecha_publicacion,proceso_id,lineamiento_id,publicado\n"
      . "INF,Informe,informe,docs/informe.pdf,,,,,,1\n"
      . "DRV,Carpeta de evidencias,soporte,,https://drive.google.com/drive/folders/abc123,,,,,1\n"
    );
    $informe = $this->documento('Informe');
    $this->assertFalse($informe->get('field_media_file')->isEmpty());
    $this->assertTrue($informe->get('field_enlace')->isEmpty());

    $carpeta = $this->documento('Carpeta de evidencias');
    $this->assertTrue($carpeta->get('field_media_file')->isEmpty());
    $this->assertSame('https://drive.google.com/drive/folders/abc123', $carpeta->get('field_enlace')->uri);
    $this->assertSame([], $this->mensajes($carpeta));
  }

  /**
   * Correr la migración dos veces no duplica los documentos.
   */
  public function testEsIdempotente(): void {
    $csv = "id,nombre,categoria,archivo,enlace,version,fecha_publicacion,proceso_id,lineamiento_id,publicado\n"
      . "DRV,Carpeta de evidencias,soporte,,https://drive.google.com/drive/folders/abc123,,,,,1\n";
    $this->importar($csv);
    $this->importar($csv);
    $this->assertSame(1, (int) $this->container->get('entity_type.manager')->getStorage('media')
      ->getQuery()->accessCheck(FALSE)->count()->execute());
  }

}
