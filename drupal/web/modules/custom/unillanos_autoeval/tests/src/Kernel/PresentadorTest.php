<?php

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el presentador de las páginas públicas.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class PresentadorTest extends UnillanosKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->crearCampo('node', 'proceso', 'field_periodo_evaluado', 'string');
    $this->crearCampo('node', 'proceso', 'field_resultado', 'string');
    $this->setUpCurrentUser([], ['access content']);
  }

  /**
   * Los números salen con coma decimal y lo no numérico queda vacío.
   */
  public function testNumeroConComaDecimal(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $this->assertSame('4,27', $presentador->numero('4.27'));
    $this->assertSame('4,50', $presentador->numero(4.5));
    $this->assertSame('4,5', $presentador->numero('4.50', 1));
    $this->assertSame('', $presentador->numero(NULL));
    $this->assertSame('', $presentador->numero(''));
    $this->assertSame('', $presentador->numero('abc'));
  }

  /**
   * Busca el programa por clave y su proceso más reciente publicado.
   */
  public function testBuscaProgramaConSuProceso(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $programa = $this->crearNodo('programa', [
      'title' => 'Ingeniería Electrónica',
      'field_clave' => 'ingenieria-electronica',
    ]);
    $otro = $this->crearNodo('programa', [
      'title' => 'Otro',
      'field_clave' => 'otro',
    ]);
    $this->crearNodo('proceso', ['title' => 'Antiguo', 'field_programa' => $programa->id()]);
    $reciente = $this->crearNodo('proceso', ['title' => 'Reciente', 'field_programa' => $programa->id()]);
    $this->crearNodo('proceso', ['title' => 'Borrador', 'field_programa' => $programa->id(), 'status' => 0]);

    $this->assertSame($programa->id(), $presentador->programaPorClave('ingenieria-electronica')->id());
    $this->assertNull($presentador->programaPorClave('no-existe'));
    $this->assertSame($reciente->id(), $presentador->procesoDelPrograma($programa)->id());
    $this->assertNull($presentador->procesoDelPrograma($otro));
  }

  /**
   * El resumen de la portada trae lo guardado y deja vacío lo que falta.
   */
  public function testProcesosPublicados(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $programa = $this->crearNodo('programa', [
      'title' => 'Ingeniería Electrónica',
      'field_clave' => 'ingenieria-electronica',
    ]);
    $this->crearNodo('proceso', [
      'title' => 'Renovación 2018–2022',
      'field_programa' => $programa->id(),
      'field_estado' => 'finalizado',
      'field_periodo_evaluado' => '2018–2022',
    ]);
    $cache = new CacheableMetadata();
    $resumen = $presentador->procesosPublicados($cache);
    $this->assertCount(1, $resumen);
    $this->assertSame('Ingeniería Electrónica', $resumen[0]['programa']);
    $this->assertSame('Finalizado', $resumen[0]['estado']);
    $this->assertSame('2018–2022', $resumen[0]['periodo']);
    $this->assertSame('', $resumen[0]['resultado']);
    $this->assertContains('node:' . $programa->id(), $cache->getCacheTags());
  }

  /**
   * Un programa sin publicar no se nombra ni se enlaza; sin estado, vacío.
   */
  public function testProgramaSinPublicarNoSeMuestra(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $oculto = $this->crearNodo('programa', [
      'title' => 'Oculto',
      'field_clave' => 'oculto',
      'status' => 0,
    ]);
    $this->crearNodo('proceso', [
      'title' => 'Proceso sin estado',
      'field_programa' => $oculto->id(),
    ]);
    $resumen = $presentador->procesosPublicados(new CacheableMetadata());
    $this->assertCount(1, $resumen);
    $this->assertSame('', $resumen[0]['programa']);
    $this->assertSame('', $resumen[0]['url_programa']);
    $this->assertSame('', $resumen[0]['estado']);
    $this->assertSame('Proceso sin estado', $resumen[0]['proceso']);
  }

}
