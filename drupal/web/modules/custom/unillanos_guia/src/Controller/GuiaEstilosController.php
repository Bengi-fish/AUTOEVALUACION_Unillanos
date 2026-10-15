<?php

namespace Drupal\unillanos_guia\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\unillanos_guia\Service\DatosEjemplo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Página /guia-estilos: todos los componentes del tema con datos de ejemplo.
 */
final class GuiaEstilosController extends ControllerBase {

  /**
   * Constructor.
   */
  public function __construct(
    protected DatosEjemplo $datosEjemplo,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get(DatosEjemplo::class));
  }

  /**
   * Construye la guía de estilos.
   *
   * @return array<string, mixed>
   *   Render array de la guía.
   */
  public function build(): array {
    $datos = $this->datosEjemplo->obtener();
    return [
      '#theme' => 'unillanos_guia_estilos',
      '#datos' => $datos,
      '#attached' => [
        'library' => ['unillanos_guia/guia'],
        // La guía es pública pero no debe indexarse.
        'html_head' => [
          [
            [
              '#tag' => 'meta',
              '#attributes' => ['name' => 'robots', 'content' => 'noindex, nofollow'],
            ],
            'unillanos_guia_robots',
          ],
        ],
      ],
    ];
  }

}
