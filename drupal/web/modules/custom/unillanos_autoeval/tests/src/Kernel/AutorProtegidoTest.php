<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Acreditación no puede cambiar el autor (uid) de ningún contenido.
 *
 * El autor decide qué puede borrar Acreditación («lo propio»): si pudiera
 * cambiarlo, podría adueñarse de contenido migrado o ajeno y borrarlo.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class AutorProtegidoTest extends EliminarKernelBase {

  /**
   * El campo «autor» no se puede editar en ningún tipo ni medio.
   */
  #[DataProvider('tipos')]
  public function testElCampoAutorNoEsEditable(string $tipo): void {
    $nodo = $this->nodoDe($tipo, (int) $this->acreditacion->id());
    $this->assertFalse($nodo->get('uid')->access('edit', $this->acreditacion), $tipo);
    $this->assertFalse($nodo->get('created')->access('edit', $this->acreditacion), "$tipo: fecha de creación");
    $medio = $this->documentoDe((int) $this->acreditacion->id());
    $this->assertFalse($medio->get('uid')->access('edit', $this->acreditacion), 'documento');
  }

  /**
   * Enviar otro autor en el formulario normal, la ficha o la foto no lo cambia.
   */
  #[DataProvider('operaciones')]
  public function testEnviarOtroAutorNoLoCambia(string $operacion): void {
    $this->prepararPantallas();
    $otro = $this->usuario(['authenticated']);
    $programa = $this->nodoDe('programa', (int) $this->acreditacion->id());
    $estado = new FormState();
    $estado->setValues([
      'title' => [['value' => 'Título nuevo']],
      'uid' => [['target_id' => $otro->id()]],
      'op' => 'Save',
    ]);
    $objeto = $this->container->get('entity_type.manager')
      ->getFormObject('node', $operacion)
      ->setEntity($programa);
    $this->container->get('form_builder')->submitForm($objeto, $estado);
    $recargado = Node::load($programa->id());
    $this->assertEquals($this->acreditacion->id(), $recargado->getOwnerId(), $operacion);
    // El autor tampoco aparece como campo editable en el formulario.
    $form = $this->container->get('entity.form_builder')->getForm(Node::load($programa->id()), $operacion);
    $this->assertFalse($form['uid']['#access'] ?? FALSE, $operacion);
  }

  /**
   * Operaciones de formulario del programa.
   *
   * @return array<string, array{0: string}>
   *   Un caso por operación.
   */
  public static function operaciones(): array {
    return [
      'formulario completo' => ['default'],
      'ficha simple' => ['acreditacion'],
      'foto' => ['foto'],
    ];
  }

  /**
   * Pantallas de formulario del programa con título y autor (como el sitio).
   */
  protected function prepararPantallas(): void {
    foreach (['default', 'acreditacion', 'foto'] as $modo) {
      $pantalla = EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'programa',
        'mode' => $modo,
        'status' => TRUE,
      ]);
      $pantalla->setComponent('title', ['type' => 'string_textfield', 'weight' => 0]);
      $pantalla->setComponent('uid', ['type' => 'entity_reference_autocomplete', 'weight' => 5]);
      $pantalla->save();
    }
  }

  /**
   * Quien administra medios sí puede cambiar el autor de un documento.
   */
  public function testElAdministradorDeMediosConservaElAutor(): void {
    $rol = Role::create(['id' => 'admin_medios', 'label' => 'Admin medios', 'permissions' => ['administer media']]);
    $rol->save();
    $admin = $this->usuario(['admin_medios']);
    $medio = $this->documentoDe((int) $this->acreditacion->id());
    $this->assertTrue($medio->get('uid')->access('edit', $admin));
    $this->assertFalse($medio->get('uid')->access('edit', $this->acreditacion));
  }

}
