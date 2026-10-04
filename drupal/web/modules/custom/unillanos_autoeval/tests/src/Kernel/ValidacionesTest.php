<?php

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\taxonomy\Entity\Term;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba las restricciones de validación: un caso válido y uno inválido.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class ValidacionesTest extends UnillanosKernelTestBase {

  /**
   * Devuelve los mensajes de violación de una entidad, por campo.
   *
   * @return string[]
   *   Rutas de propiedad con violación.
   */
  protected function campos(ContentEntityInterface $entidad): array {
    $rutas = [];
    foreach ($entidad->validate() as $violacion) {
      $rutas[] = $violacion->getPropertyPath();
    }
    return $rutas;
  }

  /**
   * Proceso + elemento únicos en valoración.
   */
  public function testValoracionUnica(): void {
    $proceso = $this->crearNodo('proceso');
    $elemento = $this->crearElemento('Factor 1', 'CNA20-F01');
    $otro = $this->crearElemento('Factor 2', 'CNA20-F02');
    $datos = [
      'field_proceso' => $proceso->id(),
      'field_elemento' => $elemento->id(),
    ];
    $primera = $this->crearNodo('valoracion', $datos);

    // Inválido: la misma pareja en otro nodo.
    $repetida = $this->container->get('entity_type.manager')->getStorage('node')
      ->create($datos + ['type' => 'valoracion', 'title' => 'x']);
    $this->assertContains('field_elemento', $this->campos($repetida));

    // Válido: otro elemento en el mismo proceso.
    $distinta = $this->container->get('entity_type.manager')->getStorage('node')
      ->create(['field_elemento' => $otro->id()] + $datos + ['type' => 'valoracion', 'title' => 'x']);
    $this->assertNotContains('field_elemento', $this->campos($distinta));

    // Válido: editar la valoración existente no choca consigo misma.
    $this->assertNotContains('field_elemento', $this->campos($primera));
  }

  /**
   * El proceso tiene sede o programa, exactamente uno.
   */
  public function testSedeExcluyenteConPrograma(): void {
    $sede = Term::create(['vid' => 'sede', 'name' => 'Barcelona']);
    $sede->save();
    $programa = $this->crearNodo('programa', ['field_clave' => 'enfermeria']);
    $storage = $this->container->get('entity_type.manager')->getStorage('node');

    $solo_sede = $storage->create(['type' => 'proceso', 'title' => 'p', 'field_sede' => $sede->id()]);
    $this->assertNotContains('field_programa', $this->campos($solo_sede));

    $solo_programa = $storage->create(['type' => 'proceso', 'title' => 'p', 'field_programa' => $programa->id()]);
    $this->assertNotContains('field_programa', $this->campos($solo_programa));

    $ambos = $storage->create([
      'type' => 'proceso',
      'title' => 'p',
      'field_sede' => $sede->id(),
      'field_programa' => $programa->id(),
    ]);
    $this->assertContains('field_programa', $this->campos($ambos));

    $ninguno = $storage->create(['type' => 'proceso', 'title' => 'p']);
    $this->assertContains('field_programa', $this->campos($ninguno));
  }

  /**
   * La clave es única en programa.
   */
  public function testClaveUnicaEnPrograma(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $existente = $this->crearNodo('programa', ['field_clave' => 'enfermeria']);

    $repetido = $storage->create(['type' => 'programa', 'title' => 'p', 'field_clave' => 'enfermeria']);
    $this->assertContains('field_clave', $this->campos($repetido));

    $nuevo = $storage->create(['type' => 'programa', 'title' => 'p', 'field_clave' => 'medicina']);
    $this->assertNotContains('field_clave', $this->campos($nuevo));

    // Editar el existente no cuenta como repetido.
    $this->assertNotContains('field_clave', $this->campos($existente));
  }

  /**
   * La clave es única en elemento_modelo.
   */
  public function testClaveUnicaEnElementoModelo(): void {
    $existente = $this->crearElemento('Factor 1', 'CNA20-F01');
    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');

    $repetido = $storage->create(['vid' => 'elemento_modelo', 'name' => 'x', 'field_clave' => 'CNA20-F01']);
    $this->assertContains('field_clave', $this->campos($repetido));

    $nuevo = $storage->create(['vid' => 'elemento_modelo', 'name' => 'x', 'field_clave' => 'CNA20-F02']);
    $this->assertNotContains('field_clave', $this->campos($nuevo));

    $this->assertNotContains('field_clave', $this->campos($existente));
  }

  /**
   * Casos de `field_estado`: tipo, valor y si es válido.
   */
  public static function proveedorEstados(): array {
    return [
      'proceso planeado' => ['proceso', 'planeado', TRUE],
      'proceso en curso' => ['proceso', 'en_curso', TRUE],
      'proceso finalizado' => ['proceso', 'finalizado', TRUE],
      'proceso con estado de seguimiento' => ['proceso', 'verificado', FALSE],
      'proceso borrador' => ['proceso', 'borrador', FALSE],
      'seguimiento borrador' => ['seguimiento', 'borrador', TRUE],
      'seguimiento reportado' => ['seguimiento', 'reportado', TRUE],
      'seguimiento verificado' => ['seguimiento', 'verificado', TRUE],
      'seguimiento con estado de proceso' => ['seguimiento', 'en_curso', FALSE],
    ];
  }

  /**
   * El estado solo acepta los valores de su tipo de contenido.
   */
  #[DataProvider('proveedorEstados')]
  public function testEstadoPorTipo(string $tipo, string $estado, bool $valido): void {
    $nodo = $this->container->get('entity_type.manager')->getStorage('node')
      ->create(['type' => $tipo, 'title' => 'x', 'field_estado' => $estado]);
    $campos = $this->campos($nodo);
    if ($valido) {
      $this->assertNotContains('field_estado', $campos);
    }
    else {
      $this->assertContains('field_estado', $campos);
    }
  }

}
