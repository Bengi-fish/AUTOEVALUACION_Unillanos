<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\unillanos_autoeval\Service\CarruselPortada;
use Drupal\unillanos_autoeval\Service\PortalInicio;
use Drupal\unillanos_autoeval\Service\SitioAcreditacion;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Portada del portal de Autoevaluación y Acreditación (página de inicio).
 *
 * Entrega los datos; el tema los pinta con la plantilla del gancho
 * `unillanos_portal`.
 */
final class PortalController extends ControllerBase {

  /**
   * Constructor.
   */
  public function __construct(
    protected PortalInicio $portal,
    protected CarruselPortada $carrusel,
    protected SitioAcreditacion $sitio,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(PortalInicio::class),
      $container->get(CarruselPortada::class),
      $container->get(SitioAcreditacion::class),
    );
  }

  /**
   * Construye la portada del portal.
   *
   * @return array<string, mixed>
   *   Arreglo de render.
   */
  public function portal(): array {
    $cache = new CacheableMetadata();
    $construccion = [
      '#theme' => 'unillanos_portal',
      '#datos' => $this->portal->datos($cache) + [
        'carrusel' => $this->carrusel->datos($cache),
        'noticias' => $this->sitio->noticias($cache, 3),
      ],
    ];
    $cache->applyTo($construccion);
    return $construccion;
  }

}
