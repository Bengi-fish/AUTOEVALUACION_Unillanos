<?php

namespace Drupal\Tests\unillanos_migrate\Unit;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Tests\UnitTestCase;
use Drupal\unillanos_migrate\Plugin\migrate\process\RutaDeArchivo;
use PHPUnit\Framework\Attributes\Group;

/**
 * Prueba la validación de rutas de archivos de los CSV.
 */
#[Group('unillanos_migrate')]
class RutaDeArchivoTest extends UnitTestCase {

  /**
   * Carpeta temporal que hace de «raíz del repositorio».
   */
  protected string $base;

  /**
   * Raíz del repositorio de prueba.
   */
  protected string $raiz;

  /**
   * Archivo fuera de la raíz.
   */
  protected string $fuera;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->base = realpath(sys_get_temp_dir()) . '/unillanos_ruta_' . bin2hex(random_bytes(4));
    $this->raiz = $this->base . '/repo';
    mkdir($this->raiz . '/docs/fuentes', 0777, TRUE);
    mkdir($this->base . '/fuera', 0777, TRUE);
    $this->fuera = $this->base . '/fuera/secreto.pdf';
    file_put_contents($this->fuera, 'x');
    file_put_contents($this->raiz . '/docs/fuentes/informe.pdf', 'x');
    file_put_contents($this->raiz . '/docs/fuentes/Plan.XLSX', 'x');
    file_put_contents($this->raiz . '/docs/fuentes/programa.exe', 'x');
    file_put_contents($this->raiz . '/docs/fuentes/sin-extension', 'x');
    file_put_contents($this->raiz . '/docs/fuentes/falso.exe.pdf', 'x');
    mkdir($this->raiz . '/docs/.oculta', 0777, TRUE);
    file_put_contents($this->raiz . '/docs/.oculta/secreto.pdf', 'x');
    mkdir($this->raiz . '/datos/semillas', 0777, TRUE);
    file_put_contents($this->raiz . '/datos/semillas/nota.pdf', 'x');
    mkdir($this->raiz . '/drupal', 0777, TRUE);
    file_put_contents($this->raiz . '/drupal/composer.pdf', 'x');
    file_put_contents($this->raiz . '/raiz.pdf', 'x');
    symlink($this->fuera, $this->raiz . '/enlace.pdf');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    @unlink($this->raiz . '/enlace.pdf');
    foreach (['/docs/fuentes/*', '/docs/.oculta/*', '/datos/semillas/*', '/drupal/*', '/*.pdf'] as $patron) {
      foreach (glob($this->raiz . $patron) ?: [] as $archivo) {
        @unlink($archivo);
      }
    }
    @unlink($this->fuera);
    $carpetas = ['/docs/fuentes', '/docs/.oculta', '/docs', '/datos/semillas', '/datos', '/drupal'];
    foreach (array_map(fn($c) => $this->raiz . $c, $carpetas) as $carpeta) {
      @rmdir($carpeta);
    }
    foreach ([$this->raiz, $this->base . '/fuera', $this->base] as $carpeta) {
      @rmdir($carpeta);
    }
    parent::tearDown();
  }

  /**
   * Ejecuta el plugin con una ruta.
   *
   * @param string $ruta
   *   Valor del CSV.
   * @param string[] $extensiones
   *   Extensiones permitidas.
   *
   * @return string
   *   La ruta absoluta que devuelve el plugin.
   */
  protected function validar(string $ruta, array $extensiones = ['pdf', 'xlsx', 'docx']): string {
    $plugin = new RutaDeArchivo(
      ['raiz' => $this->raiz, 'extensiones' => $extensiones],
      'unillanos_ruta_archivo',
      [],
      $this->createMock(EntityTypeManagerInterface::class),
    );
    return $plugin->transform($ruta, $this->createMock(MigrateExecutableInterface::class), new Row([], []), 'ruta');
  }

  /**
   * Comprueba que una ruta se rechaza con un mensaje que contenga un texto.
   */
  protected function assertRechaza(string $ruta, string $mensaje, array $extensiones = ['pdf', 'xlsx', 'docx']): void {
    try {
      $this->validar($ruta, $extensiones);
      $this->fail('Se esperaba una excepción para «' . $ruta . '».');
    }
    catch (MigrateException $e) {
      $this->assertStringContainsString($mensaje, $e->getMessage());
    }
  }

  /**
   * Una ruta válida devuelve la ruta absoluta (y no distingue mayúsculas).
   */
  public function testRutaValida(): void {
    $this->assertSame($this->raiz . '/docs/fuentes/informe.pdf', $this->validar('docs/fuentes/informe.pdf'));
    $this->assertSame($this->raiz . '/docs/fuentes/Plan.XLSX', $this->validar('docs/fuentes/Plan.XLSX'));
    // Un «..» que se queda dentro de la raíz es válido.
    $this->assertSame($this->raiz . '/docs/fuentes/informe.pdf', $this->validar('docs/fuentes/../fuentes/informe.pdf'));
  }

  /**
   * Solo se copia de docs/ y datos/, y no de elementos ocultos.
   */
  public function testSoloCarpetasPermitidasSinOcultos(): void {
    $this->assertSame($this->raiz . '/datos/semillas/nota.pdf', $this->validar('datos/semillas/nota.pdf'));
    $this->assertRechaza('drupal/composer.pdf', 'no está en una carpeta permitida');
    $this->assertRechaza('raiz.pdf', 'no está en una carpeta permitida');
    $this->assertRechaza('docs/.oculta/secreto.pdf', 'elemento oculto');
  }

  /**
   * Las carpetas permitidas se pueden cambiar con la opción «bases».
   */
  public function testBasesConfigurables(): void {
    $plugin = new RutaDeArchivo(
      ['raiz' => $this->raiz, 'extensiones' => ['pdf'], 'bases' => ['drupal']],
      'unillanos_ruta_archivo',
      [],
      $this->createMock(EntityTypeManagerInterface::class),
    );
    $ejecutable = $this->createMock(MigrateExecutableInterface::class);
    $this->assertSame($this->raiz . '/drupal/composer.pdf', $plugin->transform('drupal/composer.pdf', $ejecutable, new Row([], []), 'ruta'));
    $this->expectException(MigrateException::class);
    $plugin->transform('docs/fuentes/informe.pdf', $ejecutable, new Row([], []), 'ruta');
  }

  /**
   * Una ruta con «..» que sale de la raíz se rechaza.
   */
  public function testRutaConPuntosPuntosQueSale(): void {
    $this->assertRechaza('docs/../../fuera/secreto.pdf', 'sale de la raíz');
    $this->assertRechaza('../fuera/secreto.pdf', 'sale de la raíz');
  }

  /**
   * Un enlace simbólico que apunta fuera de la raíz se rechaza.
   */
  public function testEnlaceSimbolicoHaciaFuera(): void {
    $this->assertRechaza('enlace.pdf', 'sale de la raíz');
  }

  /**
   * Una ruta absoluta se rechaza aunque apunte dentro de la raíz.
   */
  public function testRutaAbsoluta(): void {
    $this->assertRechaza($this->raiz . '/docs/fuentes/informe.pdf', 'es absoluta');
    $this->assertRechaza($this->fuera, 'es absoluta');
    $this->assertRechaza('C:\\Windows\\x.pdf', 'es absoluta');
    $this->assertRechaza('\\\\servidor\\x.pdf', 'es absoluta');
    // Una barra invertida en medio es parte del nombre en Linux: no existe.
    $this->assertRechaza('docs\\..\\..\\x.pdf', 'no existe');
  }

  /**
   * Un archivo inexistente, una carpeta o una ruta vacía se rechazan.
   */
  public function testArchivoInexistenteRutasVacias(): void {
    $this->assertRechaza('docs/fuentes/no-existe.pdf', 'no existe');
    $this->assertRechaza('docs/fuentes', 'no existe');
    $this->assertRechaza('', 'está vacía');
    $this->assertRechaza('   ', 'está vacía');
    $this->assertRechaza("docs/fuentes/informe.pdf\0.txt", 'caracteres no válidos');
  }

  /**
   * Una extensión no permitida (o ninguna) se rechaza.
   */
  public function testExtensionNoPermitida(): void {
    $this->assertRechaza('docs/fuentes/programa.exe', 'no está permitida');
    // Cuenta la última extensión: «.exe.pdf» es un PDF por nombre (igual que
    // las cargas de Drupal) y «.pdf.exe» no.
    $this->assertSame($this->raiz . '/docs/fuentes/falso.exe.pdf', $this->validar('docs/fuentes/falso.exe.pdf'));
    $this->assertRechaza('docs/fuentes/sin-extension', 'no está permitida');
    // Con otra lista, el mismo archivo cambia de resultado.
    $this->assertRechaza('docs/fuentes/informe.pdf', 'no está permitida', ['docx']);
  }

  /**
   * Sin la opción «extensiones», se usan las del campo del tipo de medio.
   */
  public function testExtensionesDelCampo(): void {
    $campo = new class() {

      /**
       * Devuelve el ajuste del campo.
       */
      public function getSetting(string $nombre): string {
        return 'pdf xlsx';
      }

    };
    $almacen = $this->createMock(ConfigEntityStorageInterface::class);
    $almacen->method('load')->with('media.documento.field_media_file')->willReturn($campo);
    $gestor = $this->createMock(EntityTypeManagerInterface::class);
    $gestor->method('getStorage')->with('field_config')->willReturn($almacen);
    $plugin = new RutaDeArchivo(['raiz' => $this->raiz], 'unillanos_ruta_archivo', [], $gestor);
    $ejecutable = $this->createMock(MigrateExecutableInterface::class);
    $this->assertSame($this->raiz . '/docs/fuentes/informe.pdf', $plugin->transform('docs/fuentes/informe.pdf', $ejecutable, new Row([], []), 'ruta'));
    $this->expectException(MigrateException::class);
    $plugin->transform('docs/fuentes/programa.exe', $ejecutable, new Row([], []), 'ruta');
  }

  /**
   * Una raíz que no existe se avisa con claridad.
   */
  public function testRaizInexistente(): void {
    $plugin = new RutaDeArchivo(['raiz' => $this->base . '/nada', 'extensiones' => ['pdf']], 'unillanos_ruta_archivo', [], $this->createMock(EntityTypeManagerInterface::class));
    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('raíz del repositorio');
    $plugin->transform('docs/fuentes/informe.pdf', $this->createMock(MigrateExecutableInterface::class), new Row([], []), 'ruta');
  }

}
