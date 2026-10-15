<?php

namespace Drupal\Tests\unillanos_migrate\Unit;

use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use Drupal\unillanos_migrate\Hook\UnillanosMigrateHooks;
use PHPUnit\Framework\Attributes\Group;

/**
 * Prueba la replicación por programa y las marcas de ruta.
 */
#[Group('unillanos_migrate')]
class UnillanosMigrateHooksTest extends UnitTestCase {

  /**
   * Carpeta temporal con datos/importacion/<programa>/.
   */
  protected string $datos;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->datos = realpath(sys_get_temp_dir()) . '/unillanos_datos_' . bin2hex(random_bytes(4));
    mkdir($this->datos . '/importacion/con-documentos', 0777, TRUE);
    mkdir($this->datos . '/importacion/sin-documentos', 0777, TRUE);
    mkdir($this->datos . '/importacion/vacio', 0777, TRUE);
    mkdir($this->datos . '/importacion/solo-cabecera', 0777, TRUE);
    file_put_contents($this->datos . '/importacion/vacio/documentos.csv', '');
    file_put_contents($this->datos . '/importacion/solo-cabecera/documentos.csv', "id,nombre\n\n");
    file_put_contents($this->datos . '/importacion/con-documentos/documentos.csv', "id\nD1\n");
    file_put_contents($this->datos . '/importacion/con-documentos/proceso.csv', "id\n");
    file_put_contents($this->datos . '/importacion/sin-documentos/proceso.csv', "id\n");
    new Settings(['unillanos_datos' => $this->datos, 'unillanos_raiz' => '/repo/raiz/']);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach (glob($this->datos . '/importacion/*/*') ?: [] as $archivo) {
      @unlink($archivo);
    }
    foreach (glob($this->datos . '/importacion/*') ?: [] as $carpeta) {
      @rmdir($carpeta);
    }
    @rmdir($this->datos . '/importacion');
    @rmdir($this->datos);
    parent::tearDown();
  }

  /**
   * Las migraciones por programa se replican y %raiz% se sustituye.
   */
  public function testReplicaPorProgramaSustituyendoRaiz(): void {
    $definiciones = [
      'procesos' => [
        'migration_group' => 'unillanos',
        'unillanos_por_programa' => TRUE,
        'label' => 'Procesos',
        'source' => ['path' => '%datos%/importacion/%carpeta%/proceso.csv'],
      ],
      'archivos_generales' => [
        'migration_group' => 'unillanos',
        'label' => 'Archivos',
        'process' => ['ruta' => ['raiz' => '%raiz%']],
        'source' => ['path' => '%datos%/semillas/documentos.csv'],
      ],
      'ajena' => ['migration_group' => 'otra', 'source' => ['path' => '%datos%']],
    ];
    (new UnillanosMigrateHooks())->migrationPluginsAlter($definiciones);
    $this->assertArrayNotHasKey('procesos', $definiciones);
    $this->assertSame(
      $this->datos . '/importacion/con-documentos/proceso.csv',
      $definiciones['procesos:con_documentos']['source']['path'],
    );
    $this->assertArrayHasKey('procesos:sin_documentos', $definiciones);
    // %raiz% se sustituye (sin la barra final) en las migraciones generales.
    $this->assertSame('/repo/raiz', $definiciones['archivos_generales']['process']['ruta']['raiz']);
    // Una migración de otro grupo no se toca.
    $this->assertSame('%datos%', $definiciones['ajena']['source']['path']);
  }

  /**
   * Con la marca de CSV opcional, un programa sin ese CSV no tiene migración.
   */
  public function testCsvOpcionalSeOmiteSiFalta(): void {
    $definiciones = [
      'documentos' => [
        'migration_group' => 'unillanos',
        'unillanos_por_programa' => TRUE,
        'unillanos_csv_opcional' => TRUE,
        'label' => 'Documentos',
        'source' => ['path' => '%datos%/importacion/%carpeta%/documentos.csv'],
      ],
    ];
    (new UnillanosMigrateHooks())->migrationPluginsAlter($definiciones);
    // Sin CSV, con el CSV vacío o con solo la cabecera: no hay migración.
    $this->assertSame(['documentos:con_documentos'], array_keys($definiciones));
    $this->assertArrayNotHasKey('unillanos_csv_opcional', $definiciones['documentos:con_documentos']);
    $this->assertSame('Documentos (con-documentos)', $definiciones['documentos:con_documentos']['label']);
  }

}
