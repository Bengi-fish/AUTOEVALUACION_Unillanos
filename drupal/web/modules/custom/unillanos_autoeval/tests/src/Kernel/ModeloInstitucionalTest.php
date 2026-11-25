<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\taxonomy\Entity\Term;
use Drupal\unillanos_autoeval\Service\EntidadesDependientes;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el ámbito del modelo de un proceso (Fase 9, etapa 2).
 *
 * Un proceso de programa usa un modelo de programa; uno institucional (con
 * sede), un modelo institucional. Un modelo sin ámbito guardado no se
 * restringe, y un proceso institucional puede quedar sin modelo.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class ModeloInstitucionalTest extends UnillanosKernelTestBase {

  /**
   * Modelo de programa.
   */
  protected int $modeloPrograma;

  /**
   * Modelo institucional.
   */
  protected int $modeloInstitucional;

  /**
   * Un programa y una sede.
   *
   * @var array<string, int|string>
   */
  protected array $ids;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->crearCampo('node', 'lineamiento', 'field_ambito', 'list_string', [
      'allowed_values' => ['programa' => 'Programa', 'institucional' => 'Institucional'],
    ]);
    $this->modeloPrograma = (int) $this->crearNodo('lineamiento', [
      'title' => 'Modelo de programa',
      'field_norma' => 'Acuerdo CESU 01 de 2025',
      'field_tipo_proceso' => 'acreditacion',
      'field_ambito' => 'programa',
    ])->id();
    $this->modeloInstitucional = (int) $this->crearNodo('lineamiento', [
      'title' => 'Modelo institucional',
      'field_norma' => 'Acuerdo CESU 01 de 2025 (instituciones)',
      'field_tipo_proceso' => 'acreditacion',
      'field_ambito' => 'institucional',
    ])->id();
    $this->ids = [
      'programa' => (int) $this->crearNodo('programa', ['title' => 'Programa', 'field_clave' => 'p'])->id(),
    ];
    $sede = Term::create(['vid' => 'sede', 'name' => 'Sede Granada']);
    $sede->save();
    $this->ids['sede'] = (int) $sede->id();
  }

  /**
   * Devuelve los mensajes de las infracciones del modelo de un proceso.
   *
   * @param array<string, mixed> $valores
   *   Valores del proceso.
   *
   * @return string[]
   *   Mensajes de las infracciones de `field_lineamiento`.
   */
  protected function infracciones(array $valores): array {
    $proceso = $this->container->get('entity_type.manager')->getStorage('node')
      ->create(['type' => 'proceso', 'title' => 'Proceso'] + $valores);
    $mensajes = [];
    foreach ($proceso->validate() as $infraccion) {
      if ($infraccion->getPropertyPath() === 'field_lineamiento.0' || $infraccion->getPropertyPath() === 'field_lineamiento') {
        $mensajes[] = (string) $infraccion->getMessage();
      }
    }
    return $mensajes;
  }

  /**
   * Un proceso con sede y modelo institucional es válido.
   */
  public function testProcesoInstitucionalConModeloInstitucional(): void {
    $this->assertSame([], $this->infracciones([
      'field_sede' => $this->ids['sede'],
      'field_lineamiento' => $this->modeloInstitucional,
    ]));
  }

  /**
   * Un proceso de programa con el modelo institucional no es válido.
   */
  public function testProgramaConModeloInstitucionalEsError(): void {
    $mensajes = $this->infracciones([
      'field_programa' => $this->ids['programa'],
      'field_lineamiento' => $this->modeloInstitucional,
    ]);
    $this->assertCount(1, $mensajes);
    $this->assertStringContainsString('es de otro ámbito', $mensajes[0]);
  }

  /**
   * Un proceso con sede y el modelo de programa tampoco.
   */
  public function testSedeConModeloDeProgramaEsError(): void {
    $mensajes = $this->infracciones([
      'field_sede' => $this->ids['sede'],
      'field_lineamiento' => $this->modeloPrograma,
    ]);
    $this->assertCount(1, $mensajes);
    $this->assertStringContainsString('es de otro ámbito', $mensajes[0]);
  }

  /**
   * Un proceso de programa con su modelo de programa es válido.
   */
  public function testProgramaConModeloDeProgramaEsValido(): void {
    $this->assertSame([], $this->infracciones([
      'field_programa' => $this->ids['programa'],
      'field_lineamiento' => $this->modeloPrograma,
    ]));
  }

  /**
   * Un proceso institucional puede quedar sin modelo.
   */
  public function testProcesoInstitucionalSinModelo(): void {
    $this->assertSame([], $this->infracciones(['field_sede' => $this->ids['sede']]));
  }

  /**
   * Un modelo sin ámbito guardado no se restringe (compatibilidad).
   */
  public function testModeloSinAmbitoNoSeRestringe(): void {
    $modelo = (int) $this->crearNodo('lineamiento', [
      'title' => 'Modelo sin ámbito',
      'field_norma' => 'Acuerdo CESU 02 de 2020',
      'field_tipo_proceso' => 'acreditacion',
    ])->id();
    $de_programa = ['field_programa' => $this->ids['programa'], 'field_lineamiento' => $modelo];
    $de_sede = ['field_sede' => $this->ids['sede'], 'field_lineamiento' => $modelo];
    $this->assertSame([], $this->infracciones($de_programa));
    $this->assertSame([], $this->infracciones($de_sede));
  }

  /**
   * El borrado de una dependencia o un programa mira los documentos.
   */
  public function testReglasDeDependientes(): void {
    $reglas = EntidadesDependientes::REGLAS;
    $this->assertContains(['media', 'documento', 'field_dependencia'], $reglas['node:dependencia']);
    $this->assertContains(['media', 'documento', 'field_programa'], $reglas['node:programa']);
  }

}
