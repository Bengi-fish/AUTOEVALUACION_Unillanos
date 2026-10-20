<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Comprueba que el presentador delega en PlanMejoramientoPrograma.
 *
 * El presentador es una fachada: da el mismo resultado que el servicio.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class DelegacionPlanTest extends UnillanosKernelTestBase {

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
    'entity_reference_revisions',
    'paragraphs',
    'unillanos_autoeval',
  ];

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
    $this->installEntitySchema('paragraph');
    foreach (['proyecto_institucional', 'responsable'] as $vid) {
      Vocabulary::create(['vid' => $vid, 'name' => $vid])->save();
    }
    foreach (['indicador', 'programacion_anual'] as $tipo) {
      ParagraphsType::create(['id' => $tipo, 'label' => $tipo])->save();
    }
    $paragraph = ['target_type' => 'paragraph'];
    foreach (['nombre', 'unidad', 'linea_base'] as $campo) {
      $this->crearCampo('paragraph', 'indicador', 'field_' . $campo, 'string');
    }
    $this->crearCampo('paragraph', 'indicador', 'field_programacion', 'entity_reference_revisions', $paragraph, -1);
    $this->crearCampo('paragraph', 'programacion_anual', 'field_anio', 'integer');
    $decimal = ['precision' => 12, 'scale' => 4];
    $this->crearCampo('paragraph', 'programacion_anual', 'field_programado', 'decimal', $decimal);
    $this->crearCampo('paragraph', 'programacion_anual', 'field_logrado', 'decimal', $decimal);
    $this->crearCampo('node', 'meta', 'body', 'text_long');
    $this->crearCampo('node', 'meta', 'field_codigo', 'string');
    $this->crearCampo('node', 'meta', 'field_numero', 'integer');
    $this->crearCampo('node', 'meta', 'field_actividades', 'text_long');
    $this->crearCampo('node', 'meta', 'field_recursos', 'string');
    $this->crearCampo('node', 'meta', 'field_tipo_meta', 'string');
    $this->crearCampo('node', 'meta', 'field_proyecto', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'meta', 'field_responsables', 'entity_reference', ['target_type' => 'taxonomy_term'], -1);
    $this->crearCampo('node', 'meta', 'field_indicadores', 'entity_reference_revisions', $paragraph, -1);
    foreach (['codigo_formato', 'version_formato', 'periodo', 'fecha_suscripcion'] as $campo) {
      $this->crearCampo('node', 'plan_mejoramiento', 'field_' . $campo, 'string');
    }
    $this->crearCampo('node', 'seguimiento', 'field_actividades', 'text_long');
    $this->crearCampo('node', 'seguimiento', 'field_medios_verificacion', 'string');
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
   * Crea un hallazgo (aspecto por mejorar) de un factor.
   */
  protected function hallazgo(Node $proceso, Term $factor, string $texto): Node {
    return $this->crearNodo('hallazgo', [
      'field_proceso' => $proceso->id(),
      'field_tipo_hallazgo' => 'aspecto_por_mejorar',
      'field_elemento' => $factor->id(),
      'body' => ['value' => $texto, 'format' => 'plain_text'],
    ]);
  }

  /**
   * El plan da lo mismo por la fachada y por el servicio.
   */
  public function testLaFachadaDelegaEnPlanMejoramientoPrograma(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $servicio = $this->container->get('unillanos_autoeval.plan_mejoramiento_programa');
    $modelo = $this->crearLineamiento('Modelo');
    $f1 = $this->factor('Estudiantes', 1, (int) $modelo->id());
    $f2 = $this->factor('Profesores', 2, (int) $modelo->id());
    $programa = $this->crearNodo('programa', ['title' => 'Programa', 'field_clave' => 'p']);
    $proceso = $this->crearNodo('proceso', [
      'field_programa' => $programa->id(),
      'field_lineamiento' => $modelo->id(),
    ]);
    $plan = $this->crearNodo('plan_mejoramiento', [
      'field_proceso' => $proceso->id(),
      'field_codigo_formato' => 'FO-GCL-20',
      'field_version_formato' => '03',
      'field_periodo' => '2024-2 – 2030',
      'field_fecha_suscripcion' => '2024-06-19',
    ]);
    $h1 = $this->hallazgo($proceso, $f1, 'Situación uno');
    $h2 = $this->hallazgo($proceso, $f2, 'Situación dos');

    // Un indicador con su programación anual.
    $parrafos = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $anio = $parrafos->create([
      'type' => 'programacion_anual',
      'field_anio' => 2025,
      'field_programado' => '0.6000',
      'field_logrado' => '0.5000',
    ]);
    $anio->save();
    $indicador = $parrafos->create([
      'type' => 'indicador',
      'field_nombre' => 'Indicador uno',
      'field_linea_base' => '153 grupo',
      'field_programacion' => [$anio],
    ]);
    $indicador->save();
    $m1 = $this->crearNodo('meta', [
      'title' => 'Meta',
      'field_plan' => $plan->id(),
      'field_numero' => 1,
      'field_codigo' => '1',
      'body' => ['value' => 'Acción 1', 'format' => 'plain_text'],
      'field_hallazgos' => [$h1->id(), $h2->id()],
      'field_indicadores' => [$indicador],
    ]);
    $m2 = $this->crearNodo('meta', [
      'title' => 'Meta',
      'field_plan' => $plan->id(),
      'field_numero' => 2,
      'field_codigo' => '2',
      'body' => ['value' => 'Acción 2', 'format' => 'plain_text'],
      'field_hallazgos' => [$h2->id()],
    ]);
    foreach ([[$m1, '2024-2', '0.80'], [$m1, '2025-2', NULL], [$m2, '2025-1', '0.50']] as [$meta, $periodo, $avance]) {
      $this->crearNodo('seguimiento', [
        'field_meta' => $meta->id(),
        'field_periodo' => $periodo,
        'field_avance' => $avance,
        'field_estado' => 'reportado',
        'field_actividades' => ['value' => 'Hecho en ' . $periodo, 'format' => 'plain_text'],
        'field_medios_verificacion' => 'Evidencia ' . $periodo,
      ]);
    }

    $factores = $presentador->factoresDelPrograma($programa, new CacheableMetadata());
    $cache_fachada = new CacheableMetadata();
    $cache_servicio = new CacheableMetadata();
    $por_fachada = $presentador->planDelPrograma($programa, $factores, $cache_fachada);
    $por_servicio = $servicio->delPrograma($programa, $factores, $cache_servicio);
    $this->assertEquals($por_servicio, $por_fachada);
    $this->assertEquals($cache_servicio->getCacheTags(), $cache_fachada->getCacheTags());
    // No es una comparación vacía: dos metas con su factor y el seguimiento.
    $this->assertCount(2, $por_fachada['plan']['filas']);
    $this->assertTrue($por_fachada['plan']['aviso']);
    // El factor 1 tiene una meta (la primera) y el 2 tiene dos (ambas).
    $this->assertSame(['f01' => 1, 'f02' => 2], array_column($por_fachada['factores']['pestanas'], 'plan_count', 'key'));
    $this->assertContains('node_list:meta', $cache_fachada->getCacheTags());

    // Un programa sin plan: la misma forma vacía por ambos lados.
    $sin = $this->crearNodo('programa', ['title' => 'Sin plan', 'field_clave' => 's']);
    $vacio_fachada = $presentador->planDelPrograma($sin, [], new CacheableMetadata());
    $this->assertSame(['factores' => [], 'plan' => []], $vacio_fachada);
    $this->assertSame($vacio_fachada, $servicio->delPrograma($sin, [], new CacheableMetadata()));
  }

}
