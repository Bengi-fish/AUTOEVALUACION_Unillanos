<?php

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
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
    Vocabulary::create(['vid' => 'facultad', 'name' => 'facultad'])->save();
    $this->crearCampo('node', 'programa', 'field_facultad', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_sede', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_nivel', 'list_string', [
      'allowed_values' => ['pregrado' => 'Pregrado', 'maestria' => 'Maestría'],
    ]);
    $this->crearCampo('node', 'programa', 'field_modalidad', 'list_string', [
      'allowed_values' => ['presencial' => 'Presencial'],
    ]);
    $campos = [
      'director',
      'codigo_snies',
      'resolucion_registro',
      'registro_hasta',
      'resolucion_acreditacion',
      'acreditacion_hasta',
    ];
    foreach ($campos as $campo) {
      $this->crearCampo('node', 'programa', 'field_' . $campo, 'string');
    }
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

  /**
   * La ficha del programa solo trae los datos que existen.
   */
  public function testDatosProgramaOmiteLoVacio(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $facultad = Term::create(['vid' => 'facultad', 'name' => 'Facultad de Ciencias']);
    $facultad->save();
    $sede = Term::create(['vid' => 'sede', 'name' => 'Sede Barcelona']);
    $sede->save();
    $programa = $this->crearNodo('programa', [
      'title' => 'Ingeniería Electrónica',
      'field_clave' => 'ingenieria-electronica',
      'field_facultad' => $facultad->id(),
      'field_sede' => $sede->id(),
      'field_nivel' => 'pregrado',
      'field_modalidad' => 'presencial',
      'field_director' => 'Persona Directora',
      'field_registro_hasta' => '2029-12-31',
    ]);
    $datos = $presentador->datosPrograma($programa);
    $this->assertSame('Ingeniería', $datos['nombre_inicio']);
    $this->assertSame('Electrónica', $datos['nombre_acento']);
    $this->assertSame('Facultad de Ciencias · Sede Barcelona', $datos['eyebrow']);
    $this->assertSame(['Pregrado', 'Presencial'], $datos['etiquetas']);
    $terminos = array_column($datos['ficha'], 'term');
    $this->assertSame([
      'Nivel',
      'Modalidad',
      'Sede',
      'Dirección del programa',
      'Registro vigente hasta',
    ], $terminos);
    $this->assertSame('diciembre de 2029', $datos['ficha'][4]['value']);

    // Una referencia rota (término borrado) se trata como dato vacío.
    $roto = $this->crearNodo('programa', [
      'title' => 'Doctorado en Ciencias del Mar y de la Costa',
      'field_clave' => 'd',
      'field_facultad' => 99999,
    ]);
    $datos = $presentador->datosPrograma($roto);
    $this->assertSame('', $datos['eyebrow']);
    // Más de cuatro palabras: solo las dos últimas van en acento.
    $this->assertSame('Doctorado en Ciencias del Mar y de', $datos['nombre_inicio']);
    $this->assertSame('la Costa', $datos['nombre_acento']);

    $vacio = $this->crearNodo('programa', ['title' => 'Maestría', 'field_clave' => 'm']);
    $datos = $presentador->datosPrograma($vacio);
    $this->assertSame('Maestría', $datos['nombre_inicio']);
    $this->assertSame('', $datos['nombre_acento']);
    $this->assertSame('', $datos['eyebrow']);
    $this->assertSame([], $datos['etiquetas']);
    $this->assertSame([], $datos['ficha']);
  }

  /**
   * Las fechas se escriben como mes y año, y lo inválido queda vacío.
   */
  public function testFechaMesAnio(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $this->assertSame('enero de 2030', $presentador->fechaMesAnio('2030-01-15'));
    $this->assertSame('diciembre de 2029', $presentador->fechaMesAnio('2029-12-31T00:00:00'));
    $this->assertSame('', $presentador->fechaMesAnio(NULL));
    $this->assertSame('', $presentador->fechaMesAnio(''));
    $this->assertSame('', $presentador->fechaMesAnio('2029-13-01'));
    $this->assertSame('', $presentador->fechaMesAnio('texto'));
  }

}
