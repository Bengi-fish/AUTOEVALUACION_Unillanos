<?php

namespace Drupal\unillanos_autoeval\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\unillanos_autoeval\Service\PresentadorAutoevaluacion;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Portada del sitio (página de inicio).
 *
 * Entrega los datos; el tema los pinta con la plantilla del gancho
 * `unillanos_portada`.
 */
final class PortadaController extends ControllerBase {

  /**
   * Constructor.
   */
  public function __construct(
    protected PresentadorAutoevaluacion $presentador,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('unillanos_autoeval.presentador'));
  }

  /**
   * Construye la portada.
   *
   * @return array
   *   Arreglo de render.
   */
  public function portada(): array {
    $cache = new CacheableMetadata();
    $construccion = [
      '#theme' => 'unillanos_portada',
      '#datos' => $this->presentador->datosPortada($cache),
    ];
    $cache->applyTo($construccion);
    return $construccion;
  }

}
