<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Página «Participa»: introducción y formulario de recomendaciones.
 *
 * El tema pinta la introducción y el formulario con el gancho
 * `unillanos_participa`; el webform `participa` define los campos, el
 * consentimiento y quién puede enviar.
 */
final class ParticipaController extends ControllerBase {

  /**
   * Página con el formulario de recomendaciones.
   *
   * @return array<string, mixed>
   *   Arreglo de render.
   */
  public function participa(): array {
    return [
      '#theme' => 'unillanos_participa',
      '#formulario' => [
        '#type' => 'webform',
        '#webform' => 'participa',
      ],
    ];
  }

}
