<?php

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba AvanceMeta y la sugerencia de `field_destino`.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class ServiciosTest extends UnillanosKernelTestBase {

  /**
   * El avance de la meta es el del último seguimiento verificado.
   */
  public function testAvanceMetaUsaUltimoVerificado(): void {
    $plan = $this->crearNodo('plan_mejoramiento');
    $meta = $this->crearNodo('meta', ['field_plan' => $plan->id(), 'field_peso' => '0.5']);
    $servicio = $this->container->get('unillanos_autoeval.avance_meta');
    $this->assertSame(0.0, $servicio->avanceMeta($meta->id()));

    $this->seguimiento($meta->id(), '2024-2', 'verificado', '0.20');
    $this->seguimiento($meta->id(), '2025-1', 'verificado', '0.40');
    // Un seguimiento más reciente pero sin verificar no cuenta.
    $this->seguimiento($meta->id(), '2025-2', 'reportado', '0.90');
    $this->assertSame(0.4, $servicio->avanceMeta($meta->id()));
  }

  /**
   * El avance del plan es la suma de peso por avance.
   */
  public function testAvancePlanPonderado(): void {
    $plan = $this->crearNodo('plan_mejoramiento');
    $meta_a = $this->crearNodo('meta', ['field_plan' => $plan->id(), 'field_peso' => '0.6']);
    $meta_b = $this->crearNodo('meta', ['field_plan' => $plan->id(), 'field_peso' => '0.4']);
    $this->seguimiento($meta_a->id(), '2025-1', 'verificado', '0.50');
    $this->seguimiento($meta_b->id(), '2025-1', 'verificado', '1.00');
    $servicio = $this->container->get('unillanos_autoeval.avance_meta');
    // 0,6 × 0,5 + 0,4 × 1,0 = 0,7.
    $this->assertSame(0.7, $servicio->avancePlan($plan->id()));
  }

  /**
   * Menor que 4 sugiere mejoramiento; 4, acción; mayor que 4, ninguno.
   */
  public function testDestinoSugeridoSegunValoracion(): void {
    $proceso = $this->crearNodo('proceso');
    $bajo = $this->crearElemento('Factor 1', 'CNA20-F01');
    $cuatro = $this->crearElemento('Factor 2', 'CNA20-F02');
    $alto = $this->crearElemento('Factor 3', 'CNA20-F03');
    $this->crearNodo('valoracion', [
      'field_proceso' => $proceso->id(),
      'field_elemento' => $bajo->id(),
      'field_valoracion' => '3.20',
    ]);
    $this->crearNodo('valoracion', [
      'field_proceso' => $proceso->id(),
      'field_elemento' => $cuatro->id(),
      'field_valoracion' => '4.00',
    ]);
    $this->crearNodo('valoracion', [
      'field_proceso' => $proceso->id(),
      'field_elemento' => $alto->id(),
      'field_valoracion' => '4.60',
    ]);

    $this->assertSame('plan_mejoramiento', $this->destino($proceso->id(), $bajo->id()));
    $this->assertSame('plan_accion', $this->destino($proceso->id(), $cuatro->id()));
    $this->assertSame('ninguno', $this->destino($proceso->id(), $alto->id()));
    // Sin valoración no hay sugerencia.
    $sin_valorar = $this->crearElemento('Factor 4', 'CNA20-F04');
    $this->assertNull($this->destino($proceso->id(), $sin_valorar->id()));
  }

  /**
   * Un aspecto sin valoración propia usa la de su factor.
   */
  public function testDestinoUsaValoracionDelAncestro(): void {
    $proceso = $this->crearNodo('proceso');
    $factor = $this->crearElemento('Factor 1', 'CNA20-F01');
    $aspecto = $this->crearElemento('Aspecto 1', 'CNA20-A01', [$factor->id()]);
    $this->crearNodo('valoracion', [
      'field_proceso' => $proceso->id(),
      'field_elemento' => $factor->id(),
      'field_valoracion' => '3.00',
    ]);
    $this->assertSame('plan_mejoramiento', $this->destino($proceso->id(), $aspecto->id()));
  }

  /**
   * La sugerencia se aplica al crear, y un destino elegido se respeta.
   */
  public function testHallazgoSugiereDestinoAlCrear(): void {
    $proceso = $this->crearNodo('proceso');
    $elemento = $this->crearElemento('Factor 1', 'CNA20-F01');
    $this->crearNodo('valoracion', [
      'field_proceso' => $proceso->id(),
      'field_elemento' => $elemento->id(),
      'field_valoracion' => '3.00',
    ]);
    $datos = ['field_proceso' => $proceso->id(), 'field_elemento' => $elemento->id()];

    $sugerido = $this->crearNodo('hallazgo', $datos);
    $this->assertSame('plan_mejoramiento', $sugerido->get('field_destino')->value);

    $elegido = $this->crearNodo('hallazgo', $datos + ['field_destino' => 'ninguno']);
    $this->assertSame('ninguno', $elegido->get('field_destino')->value);

    // Al editar no se vuelve a sugerir.
    $sugerido->set('field_destino', NULL)->save();
    $this->assertTrue($sugerido->get('field_destino')->isEmpty());
  }

  /**
   * Devuelve el destino sugerido.
   */
  protected function destino(int|string $proceso, int|string $elemento): ?string {
    return $this->container->get('unillanos_autoeval.sugerencia_destino')->sugerir($proceso, $elemento);
  }

  /**
   * Crea un seguimiento.
   */
  protected function seguimiento(int|string $meta, string $periodo, string $estado, string $avance): void {
    $this->crearNodo('seguimiento', [
      'field_meta' => $meta,
      'field_periodo' => $periodo,
      'field_estado' => $estado,
      'field_avance' => $avance,
    ]);
  }

}
