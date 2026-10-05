<?php

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba que los elementos de un proceso sean del modelo de ese proceso.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class ModeloPorProcesoTest extends UnillanosKernelTestBase {

  use UserCreationTrait;

  /**
   * Lineamiento 2020 (modelo de acreditación).
   */
  protected Node $modelo2020;

  /**
   * Lineamiento 2025 (modelo de acreditación).
   */
  protected Node $modelo2025;

  /**
   * Lineamiento del Decreto 1330 (no es modelo de un proceso).
   */
  protected Node $decreto;

  /**
   * Proceso hecho con el modelo 2020.
   */
  protected Node $proceso;

  /**
   * Factor del modelo 2020.
   */
  protected Term $factor2020;

  /**
   * Factor del modelo 2025.
   */
  protected Term $factor2025;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->modelo2020 = $this->crearLineamiento('Acuerdo CESU 02 de 2020');
    $this->modelo2025 = $this->crearLineamiento('Acuerdo CESU 01 de 2025');
    $this->decreto = $this->crearLineamiento('Decreto 1330 de 2019', 'registro_calificado');
    $this->proceso = $this->crearNodo('proceso', [
      'title' => 'Proceso IE',
      'field_lineamiento' => $this->modelo2020->id(),
    ]);
    $this->factor2020 = $this->crearElemento('Estudiantes', 'CNA20-F02', [], $this->modelo2020->id());
    $this->factor2025 = $this->crearElemento('Comunidad de estudiantes', 'CESU25-F02', [], $this->modelo2025->id());
  }

  /**
   * Devuelve los mensajes de violación por ruta de propiedad.
   *
   * @return string[][]
   *   Mensajes agrupados por la ruta del campo.
   */
  protected function mensajes(ContentEntityInterface $entidad): array {
    $mensajes = [];
    foreach ($entidad->validate() as $violacion) {
      $mensajes[$violacion->getPropertyPath()][] = strip_tags((string) $violacion->getMessage());
    }
    return $mensajes;
  }

  /**
   * Crea una valoración o un hallazgo sin guardarlo.
   */
  protected function crear(string $tipo, Node $proceso, ?Term $elemento): Node {
    return Node::create([
      'type' => $tipo,
      'title' => $tipo,
      'field_proceso' => $proceso->id(),
      'field_elemento' => $elemento?->id(),
    ]);
  }

  /**
   * Caso correcto: elementos del modelo del proceso.
   */
  public function testElementoDelModeloDelProceso(): void {
    $this->assertArrayNotHasKey('field_elemento', $this->mensajes($this->crear('valoracion', $this->proceso, $this->factor2020)));
    $this->assertArrayNotHasKey('field_elemento', $this->mensajes($this->crear('hallazgo', $this->proceso, $this->factor2020)));
  }

  /**
   * Un elemento de otro modelo se rechaza con un mensaje claro.
   */
  public function testElementoDeOtroModelo(): void {
    foreach (['valoracion', 'hallazgo'] as $tipo) {
      $mensajes = $this->mensajes($this->crear($tipo, $this->proceso, $this->factor2025));
      $this->assertArrayHasKey('field_elemento', $mensajes, $tipo);
      $mensaje = $mensajes['field_elemento'][0];
      $this->assertStringContainsString('«Comunidad de estudiantes»', $mensaje);
      $this->assertStringContainsString('Acuerdo CESU 01 de 2025', $mensaje);
      $this->assertStringContainsString('Acuerdo CESU 02 de 2020', $mensaje);
    }
  }

  /**
   * Un hallazgo sin elemento es válido: el campo es opcional.
   */
  public function testHallazgoSinElemento(): void {
    $this->assertArrayNotHasKey('field_elemento', $this->mensajes($this->crear('hallazgo', $this->proceso, NULL)));
  }

  /**
   * Los hallazgos de una meta deben ser del modelo del proceso de su plan.
   */
  public function testMetaConHallazgos(): void {
    $plan = $this->crearNodo('plan_mejoramiento', ['field_proceso' => $this->proceso->id()]);
    $bueno = $this->crearNodo('hallazgo', [
      'field_proceso' => $this->proceso->id(),
      'field_elemento' => $this->factor2020->id(),
    ]);
    $malo = $this->crearNodo('hallazgo', [
      'title' => 'Hallazgo de otro modelo',
      'field_proceso' => $this->proceso->id(),
      'field_elemento' => $this->factor2025->id(),
    ]);
    $sin_elemento = $this->crearNodo('hallazgo', ['field_proceso' => $this->proceso->id()]);

    $valida = Node::create([
      'type' => 'meta',
      'title' => 'm',
      'field_plan' => $plan->id(),
      'field_hallazgos' => [$bueno->id(), $sin_elemento->id()],
    ]);
    $this->assertArrayNotHasKey('field_hallazgos', $this->mensajes($valida));

    $invalida = Node::create([
      'type' => 'meta',
      'title' => 'm',
      'field_plan' => $plan->id(),
      'field_hallazgos' => [$bueno->id(), $malo->id()],
    ]);
    $mensajes = $this->mensajes($invalida);
    $this->assertArrayHasKey('field_hallazgos', $mensajes);
    $this->assertStringContainsString('Hallazgo de otro modelo', $mensajes['field_hallazgos'][0]);
  }

  /**
   * El proceso solo declara los modelos permitidos (sin el Decreto 1330).
   */
  public function testModeloPermitidoDelProceso(): void {
    foreach ([$this->modelo2020, $this->modelo2025] as $modelo) {
      $proceso = Node::create(['type' => 'proceso', 'title' => 'p', 'field_lineamiento' => $modelo->id()]);
      $this->assertArrayNotHasKey('field_lineamiento', $this->mensajes($proceso));
    }
    $proceso = Node::create(['type' => 'proceso', 'title' => 'p', 'field_lineamiento' => $this->decreto->id()]);
    $mensajes = $this->mensajes($proceso);
    $this->assertArrayHasKey('field_lineamiento', $mensajes);
    $this->assertStringContainsString('Acuerdo CESU 02 de 2020 o Acuerdo CESU 01 de 2025', $mensajes['field_lineamiento'][0]);
    $this->assertStringNotContainsString('Decreto', $mensajes['field_lineamiento'][0]);
  }

  /**
   * No se cambia el modelo de un proceso que ya tiene datos de otro modelo.
   */
  public function testCambioDeModeloConDatos(): void {
    $this->crearNodo('valoracion', [
      'field_proceso' => $this->proceso->id(),
      'field_elemento' => $this->factor2020->id(),
    ]);
    $this->proceso->set('field_lineamiento', $this->modelo2025->id());
    $mensajes = $this->mensajes($this->proceso);
    $this->assertArrayHasKey('field_lineamiento', $mensajes);
    $this->assertStringContainsString('No se puede cambiar el modelo', $mensajes['field_lineamiento'][0]);
  }

  /**
   * El selector solo ofrece los elementos del modelo del proceso.
   */
  public function testSelectorDeElementos(): void {
    $this->setUpCurrentUser([], ['administer taxonomy', 'access content']);

    $this->assertSame(['Estudiantes'], $this->ofrecidos((int) $this->modelo2020->id()));
    $this->assertSame(['Comunidad de estudiantes'], $this->ofrecidos((int) $this->modelo2025->id()));
    // Sin proceso (sin modelo) no se ofrece nada.
    $this->assertSame([], $this->ofrecidos(NULL));
  }

  /**
   * Devuelve los nombres de elementos que ofrece el selector para un modelo.
   *
   * @return string[]
   *   Nombres de los elementos ofrecidos.
   */
  protected function ofrecidos(?int $modelo): array {
    $selector = $this->container->get('plugin.manager.entity_reference_selection')->getInstance([
      'target_type' => 'taxonomy_term',
      'handler' => 'unillanos_elemento_modelo',
      'target_bundles' => ['elemento_modelo' => 'elemento_modelo'],
      'lineamiento' => $modelo,
    ]);
    $this->assertIsObject($selector);
    $nombres = [];
    foreach ($selector->getReferenceableEntities() as $terminos) {
      $nombres = array_merge($nombres, array_values($terminos));
    }
    return $nombres;
  }

}
