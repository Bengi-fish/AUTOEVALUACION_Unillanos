<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\user\Form\UserLoginForm;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Entrada propia al inicio de sesión del personal de Acreditación.
 *
 * `/acceso-acreditacion` muestra el formulario de inicio de sesión de Drupal
 * (el mismo `UserLoginForm`, con sus validaciones y la protección contra
 * intentos repetidos) solo a visitantes anónimos. Quien ya tiene sesión va a
 * la portada. Ningún enlace público apunta a esta ruta ni a `/user/login`,
 * que sigue funcionando como respaldo del administrador. Tras iniciar sesión
 * se redirige a la portada (ver UnillanosAutoevalHooks::formAlter()).
 */
final class AccesoController extends ControllerBase {

  /**
   * Muestra el formulario de inicio de sesión o redirige a la portada.
   *
   * @return array<string, mixed>|\Symfony\Component\HttpFoundation\RedirectResponse
   *   El formulario, o la redirección para quien ya inició sesión.
   */
  public function acceso(): array|RedirectResponse {
    if ($this->currentUser()->isAuthenticated()) {
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }
    $build = $this->formBuilder()->getForm(UserLoginForm::class);
    // La página no es para buscadores y depende de que no haya sesión.
    $build['#attached']['html_head'][] = [
      ['#tag' => 'meta', '#attributes' => ['name' => 'robots', 'content' => 'noindex, nofollow']],
      'unillanos_acceso_noindex',
    ];
    $build['#cache']['contexts'][] = 'user.roles:authenticated';
    return $build;
  }

}
