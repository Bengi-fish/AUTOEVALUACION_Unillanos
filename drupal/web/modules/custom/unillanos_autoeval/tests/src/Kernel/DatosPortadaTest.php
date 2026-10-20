<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\unillanos_autoeval\Service\DatosPortada;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba DatosPortada: la portada y sus enlaces.
 *
 * Los enlaces de la portada salen de la comprobación de acceso por nombre de
 * ruta. El servicio da lo mismo en llamadas seguidas (sin estado oculto).
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class DatosPortadaTest extends UnillanosKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpCurrentUser([], ['access content']);
    $this->crearCampo('node', 'proceso', 'field_periodo_evaluado', 'string');
    $this->crearCampo('node', 'proceso', 'field_resultado', 'string');
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_tipo_elemento', 'string');
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_numero', 'integer');
    $this->crearCampo('node', 'hallazgo', 'field_tipo_hallazgo', 'string');
    // Los datos de cada tarjeta leen la facultad y la sede del programa.
    Vocabulary::create(['vid' => 'facultad', 'name' => 'facultad'])->save();
    $this->crearCampo('node', 'programa', 'field_facultad', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_sede', 'entity_reference', ['target_type' => 'taxonomy_term']);
  }

  /**
   * Crea un programa con su proceso y una fortaleza.
   */
  protected function programaConProceso(string $nombre): void {
    $modelo = $this->crearLineamiento('Modelo ' . $nombre);
    $programa = $this->crearNodo('programa', ['title' => $nombre, 'field_clave' => strtolower($nombre)]);
    $proceso = $this->crearNodo('proceso', [
      'title' => 'Proceso ' . $nombre,
      'field_programa' => $programa->id(),
      'field_lineamiento' => $modelo->id(),
      'field_periodo_evaluado' => '2018–2022',
    ]);
    $this->crearNodo('hallazgo', ['field_proceso' => $proceso->id(), 'field_tipo_hallazgo' => 'fortaleza']);
  }

  /**
   * La portada da lo mismo en dos llamadas seguidas y con datos completos.
   */
  public function testArmaLaPortadaDeFormaEstable(): void {
    $servicio = $this->container->get('unillanos_autoeval.datos_portada');

    // Sin programas: la misma portada vacía, sin enlace a «Ver todos».
    $vacia = $this->portada()->armar(new CacheableMetadata());
    $this->assertEquals($servicio->armar(new CacheableMetadata()), $vacia);
    $this->assertSame('', $vacia['ver_todos']);
    $this->assertSame([], $vacia['cifras']);

    $this->programaConProceso('Uno');
    $this->programaConProceso('Dos');
    $cache_primera = new CacheableMetadata();
    $cache_segunda = new CacheableMetadata();
    $primera = $this->portada()->armar($cache_primera);
    $segunda = $servicio->armar($cache_segunda);
    $this->assertEquals($segunda, $primera);
    $this->assertEquals($cache_segunda->getCacheTags(), $cache_primera->getCacheTags());
    // No es una comparación vacía.
    $this->assertSame(2, $primera['total_programas']);
    $this->assertCount(2, $primera['programas']);
    $this->assertNotEmpty($primera['cifras']);
    $this->assertContains('routes', $cache_primera->getCacheTags());
    // Los enlaces salen de las rutas del sitio, con acceso.
    $this->assertSame('/programas', $primera['ver_todos']);
    $this->assertSame('/documentos', $primera['documentos']);
    // Nunca más tarjetas que el tope de la portada.
    $this->assertLessThanOrEqual(DatosPortada::MAX_PROGRAMAS_PORTADA, count($primera['programas']));
  }

  /**
   * Sin permiso para ver las rutas no hay enlaces.
   */
  public function testSinPermisoNoHayEnlaces(): void {
    $this->programaConProceso('Uno');
    $this->setUpCurrentUser(['uid' => 7], []);
    $datos = $this->container->get('unillanos_autoeval.datos_portada')->armar(new CacheableMetadata());
    $this->assertSame('', $datos['ver_todos']);
    $this->assertSame('', $datos['documentos']);
  }

}
