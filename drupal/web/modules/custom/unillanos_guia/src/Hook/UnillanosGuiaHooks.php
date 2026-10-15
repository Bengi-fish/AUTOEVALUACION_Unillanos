<?php

namespace Drupal\unillanos_guia\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hooks del módulo de la guía de estilos.
 */
class UnillanosGuiaHooks {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Definición de la plantilla de la guía.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'unillanos_guia_estilos' => [
        'variables' => ['datos' => []],
        'template' => 'guia-estilos',
      ],
    ];
  }

}
