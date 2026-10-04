<?php

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el cálculo de `field_grado` al guardar una valoración.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class CalculoGradoTest extends UnillanosKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->cargarGrados();
  }

  /**
   * Valoraciones de la escala y el grado que les corresponde.
   */
  public static function proveedorValoraciones(): array {
    return [
      'pleno' => ['5.0', 'Pleno'],
      'alto' => ['4.27', 'Alto'],
      'aceptable' => ['3.5', 'Aceptable'],
      'insatisfactorio' => ['2.8', 'Insatisfactorio'],
      'no se cumple' => ['2.0', 'No se cumple'],
      // Los huecos de la tabla se resuelven redondeando a un decimal.
      'hueco 4,7–4,8 hacia pleno' => ['4.75', 'Pleno'],
      'hueco 4,7–4,8 hacia alto' => ['4.74', 'Alto'],
      'hueco 3,9–4,0 hacia alto' => ['3.95', 'Alto'],
      'hueco 3,9–4,0 hacia aceptable' => ['3.94', 'Aceptable'],
      'mínimo' => ['1.0', 'No se cumple'],
    ];
  }

  /**
   * El grado se calcula en el presave, sin que nadie lo digite.
   */
  #[DataProvider('proveedorValoraciones')]
  public function testGradoSeCalculaAlGuardar(string $valoracion, string $esperado): void {
    $nodo = $this->crearNodo('valoracion', ['field_valoracion' => $valoracion]);
    $this->assertSame($esperado, $nodo->get('field_grado')->entity->label());
  }

  /**
   * Un grado digitado a mano se reemplaza por el calculado.
   */
  public function testGradoDigitadoSeSobrescribe(): void {
    $pleno = $this->container->get('unillanos_autoeval.calculador_grado')->calcular(5.0);
    $nodo = $this->crearNodo('valoracion', [
      'field_valoracion' => '2.0',
      'field_grado' => $pleno->id(),
    ]);
    $this->assertSame('No se cumple', $nodo->get('field_grado')->entity->label());
  }

  /**
   * Sin valoración no hay grado.
   */
  public function testSinValoracionNoHayGrado(): void {
    $nodo = $this->crearNodo('valoracion');
    $this->assertTrue($nodo->get('field_grado')->isEmpty());
  }

  /**
   * Al cambiar la valoración el grado se recalcula.
   */
  public function testGradoSeRecalculaAlEditar(): void {
    $nodo = $this->crearNodo('valoracion', ['field_valoracion' => '3.5']);
    $nodo->set('field_valoracion', '4.27')->save();
    $this->assertSame('Alto', $nodo->get('field_grado')->entity->label());
  }

}
