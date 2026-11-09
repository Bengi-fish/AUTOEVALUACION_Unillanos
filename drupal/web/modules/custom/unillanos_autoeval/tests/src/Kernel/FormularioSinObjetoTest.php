<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\unillanos_autoeval\Hook\UnillanosAutoevalHooks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Un estado de formulario armado a mano no debe producir avisos de PHP.
 *
 * FormState::getFormObject() de core avisa «Undefined array key
 * "callback_object"» si el estado no trae objeto de formulario. Nuestro código
 * del panel no lo usa; esta prueba falla si vuelve a hacerlo.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class FormularioSinObjetoTest extends EliminarKernelBase {

  /**
   * Los ganchos de formulario no avisan ni cambian nada sin objeto de form.
   */
  #[DataProvider('formularios')]
  public function testSinAvisosConEstadoVacio(string $form_id): void {
    $avisos = [];
    set_error_handler(function (int $nivel, string $mensaje) use (&$avisos): bool {
      $avisos[] = $mensaje;
      return TRUE;
    });
    try {
      $this->container->get('request_stack')->push(Request::create(
        '/admin/acreditacion/programa/1/foto?_wrapper_format=drupal_dialog.off_canvas',
        'POST',
      ));
      $gancho = $this->container->get(UnillanosAutoevalHooks::class);
      $form = ['actions' => ['submit' => ['#type' => 'submit', '#value' => 'Guardar']]];
      $gancho->formAlter($form, new FormState(), $form_id);
      if (str_starts_with($form_id, 'node_')) {
        $gancho->nodeFormAlter($form, new FormState());
      }
      $this->container->get('request_stack')->pop();
    }
    finally {
      restore_error_handler();
    }
    $this->assertSame([], $avisos);
  }

  /**
   * Formularios que recorren los ganchos del panel.
   *
   * @return array<string, array{0: string}>
   *   Un caso por formulario.
   */
  public static function formularios(): array {
    return [
      'foto' => ['node_programa_foto_form'],
      'ficha' => ['node_programa_acreditacion_form'],
      'edición' => ['node_proceso_edit_form'],
      'borrado' => ['node_hallazgo_delete_form'],
      'documento' => ['media_documento_acreditacion_form'],
    ];
  }

}
