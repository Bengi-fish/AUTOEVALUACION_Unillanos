<?php

namespace Drupal\unillanos\Hook;

use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for unillanos.
 */
class UnillanosHooks {

  /**
   * Constructor.
   */
  public function __construct(
    protected ExtensionPathResolver $pathResolver,
  ) {}

  /**
   * Implements hook_preprocess_html().
   *
   * Añade la clase raíz `ua` que acota los estilos del prototipo, el color
   * del navegador y el favicon del tema.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $variables['attributes']['class'][] = 'ua';
    $ruta = '/' . $this->pathResolver->getPath('theme', 'unillanos');
    $variables['page']['#attached']['html_head'][] = [
      [
        '#tag' => 'meta',
        '#attributes' => ['name' => 'theme-color', 'content' => '#e3061d'],
      ],
      'unillanos_theme_color',
    ];
    $variables['page']['#attached']['html_head_link'][] = [
      [
        'rel' => 'icon',
        'type' => 'image/png',
        'href' => $ruta . '/images/favicon.png',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_image_widget().
   */
  #[Hook('preprocess_image_widget')]
  public function preprocessImageWidget(array &$variables): void {
    $data = &$variables['data'];
    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo revisit in https://drupal.org/node/953034
    // @todo revisit in https://drupal.org/node/3114318
    if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
      unset($data['preview']);
    }
  }

}
