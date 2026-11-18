<?php

namespace Drupal\unillanos_autoeval\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\unillanos_autoeval\Service\CarruselPortada;
use Drupal\unillanos_autoeval\Service\DatosPortada;
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
    protected DatosPortada $portada,
    protected CarruselPortada $carrusel,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('unillanos_autoeval.datos_portada'),
      $container->get(CarruselPortada::class),
    );
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
      '#datos' => $this->portada->armar($cache) + ['carrusel' => $this->carrusel->datos($cache)],
    ];
    $cache->applyTo($construccion);
    return $construccion;
  }

}
