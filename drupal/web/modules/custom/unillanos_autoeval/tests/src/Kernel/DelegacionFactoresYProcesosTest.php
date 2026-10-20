<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\unillanos_autoeval\Service\ProgramasYProcesos;

/**
 * Comprueba que el presentador delega en los servicios de factores y procesos.
 *
 * El presentador es una fachada: da el mismo resultado que los servicios
 * ProgramasYProcesos y FactoresPrograma.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class DelegacionFactoresYProcesosTest extends UnillanosKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->crearCampo('node', 'proceso', 'field_periodo_evaluado', 'string');
    $this->crearCampo('node', 'proceso', 'field_resultado', 'string');
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_tipo_elemento', 'string');
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_numero', 'integer');
    $this->crearCampo('node', 'hallazgo', 'field_tipo_hallazgo', 'string');
    $this->crearCampo('node', 'hallazgo', 'body', 'text_long');
  }

  /**
   * Crea un factor del modelo.
   */
  protected function factor(string $nombre, int $numero, int $modelo): Term {
    $termino = Term::create([
      'vid' => 'elemento_modelo',
      'name' => $nombre,
      'field_clave' => $nombre,
      'field_tipo_elemento' => 'factor',
      'field_numero' => $numero,
      'field_lineamiento' => $modelo,
    ]);
    $termino->save();
    return $termino;
  }

  /**
   * Buscar el programa y sus procesos da lo mismo por la fachada y el servicio.
   */
  public function testLaFachadaDelegaEnLosProcesos(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $servicio = $this->container->get('unillanos_autoeval.programas_y_procesos');
    $programa = $this->crearNodo('programa', ['title' => 'Electrónica', 'field_clave' => 'electronica']);
    $otro = $this->crearNodo('programa', ['title' => 'Otro', 'field_clave' => 'otro']);
    $antiguo = $this->crearNodo('proceso', ['title' => 'Antiguo', 'field_programa' => $programa->id()]);
    $reciente = $this->crearNodo('proceso', ['title' => 'Reciente', 'field_programa' => $programa->id()]);
    $this->crearNodo('proceso', ['title' => 'Borrador', 'field_programa' => $programa->id(), 'status' => 0]);

    $this->assertSame($programa->id(), $presentador->programaPorClave('electronica')?->id());
    $this->assertSame($servicio->programaPorClave('electronica')?->id(), $presentador->programaPorClave('electronica')?->id());
    $this->assertNull($presentador->programaPorClave('no-existe'));
    $this->assertNull($servicio->programaPorClave('no-existe'));

    // El orden es «más reciente primero» y el borrador no cuenta.
    $ids = array_keys($presentador->procesosDelPrograma($programa));
    $this->assertSame([(int) $reciente->id(), (int) $antiguo->id()], array_map('intval', $ids));
    $this->assertSame($ids, array_keys($servicio->procesosDelPrograma($programa)));
    $this->assertSame([], $presentador->procesosDelPrograma($otro));

    $this->assertSame($reciente->id(), $presentador->procesoDelPrograma($programa)?->id());
    $this->assertSame($servicio->procesoDelPrograma($programa)?->id(), $presentador->procesoDelPrograma($programa)?->id());
    $this->assertNull($presentador->procesoDelPrograma($otro));
    $this->assertNull($servicio->procesoDelPrograma($otro));

    // La constante de etiquetas es la misma (alias).
    $this->assertSame(ProgramasYProcesos::ETIQUETAS_AUTOEVALUACION, $presentador::ETIQUETAS_AUTOEVALUACION);
  }

  /**
   * Los factores dan lo mismo por la fachada y por el servicio.
   */
  public function testLaFachadaDelegaEnFactoresPrograma(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $servicio = $this->container->get('unillanos_autoeval.factores_programa');
    $this->cargarGrados();
    $modelo = $this->crearLineamiento('Modelo A');
    $f1 = $this->factor('Proyecto educativo', 1, (int) $modelo->id());
    $f2 = $this->factor('Estudiantes', 2, (int) $modelo->id());
    $this->factor('Profesores', 3, (int) $modelo->id());
    $programa = $this->crearNodo('programa', ['title' => 'Programa', 'field_clave' => 'p']);
    $proceso = $this->crearNodo('proceso', [
      'title' => 'Proceso 2018–2022',
      'field_programa' => $programa->id(),
      'field_lineamiento' => $modelo->id(),
      'field_resultado' => 'Cumplimiento en alto grado (4,508 · 90 %)',
    ]);
    $terminos = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $pleno = array_values($terminos->loadByProperties(['name' => 'Pleno']));
    $this->crearNodo('valoracion', [
      'field_proceso' => $proceso->id(),
      'field_elemento' => $f1->id(),
      'field_valoracion' => '4.90',
      'field_grado' => $pleno[0]->id(),
    ]);
    $this->crearNodo('hallazgo', [
      'field_proceso' => $proceso->id(),
      'field_tipo_hallazgo' => 'fortaleza',
      'field_elemento' => $f2->id(),
      'body' => ['value' => 'Una fortaleza', 'format' => 'plain_text'],
    ]);

    $cache_fachada = new CacheableMetadata();
    $cache_servicio = new CacheableMetadata();
    $por_fachada = $presentador->factoresDelPrograma($programa, $cache_fachada);
    $por_servicio = $servicio->delPrograma($programa, $cache_servicio);
    $this->assertEquals($por_servicio, $por_fachada);
    $this->assertEquals($cache_servicio->getCacheTags(), $cache_fachada->getCacheTags());
    // No es una comparación vacía: hay dos factores con datos.
    $this->assertSame(['f01', 'f02'], array_column($por_fachada['pestanas'], 'key'));
    $this->assertSame('Proceso 2018–2022', $por_fachada['proceso']);

    // Los factores del modelo (con todos los del modelo, también el sin datos).
    $del_modelo = array_map(fn(TermInterface $t): string => (string) $t->label(), $presentador->factoresDelModelo((int) $modelo->id()));
    $this->assertSame(
      array_map(fn(TermInterface $t): string => (string) $t->label(), $servicio->delModelo((int) $modelo->id())),
      $del_modelo,
    );
    $this->assertSame(['Proyecto educativo', 'Estudiantes', 'Profesores'], array_values($del_modelo));

    // Un programa sin proceso: vacío por ambos lados.
    $sin = $this->crearNodo('programa', ['title' => 'Sin proceso', 'field_clave' => 's']);
    $this->assertSame([], $presentador->factoresDelPrograma($sin, new CacheableMetadata()));
    $this->assertSame([], $servicio->delPrograma($sin, new CacheableMetadata()));
  }

}
