<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\unillanos_autoeval\Service\SitioAcreditacion;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Páginas de noticias y de equipo del sitio de Acreditación.
 *
 * Entregan los datos; el tema los pinta con los ganchos `unillanos_noticias`
 * y `unillanos_equipo`.
 */
final class SitioAcreditacionController extends ControllerBase {

  /**
   * Constructor.
   */
  public function __construct(protected SitioAcreditacion $sitio) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get(SitioAcreditacion::class));
  }

  /**
   * Lista de noticias.
   *
   * @return array<string, mixed>
   *   Arreglo de render.
   */
  public function noticias(): array {
    $cache = new CacheableMetadata();
    $construccion = [
      '#theme' => 'unillanos_noticias',
      '#noticias' => $this->sitio->noticias($cache),
    ];
    $cache->applyTo($construccion);
    return $construccion;
  }

  /**
   * Página «Nuestro equipo».
   *
   * @return array<string, mixed>
   *   Arreglo de render.
   */
  public function equipo(): array {
    $cache = new CacheableMetadata();
    $construccion = [
      '#theme' => 'unillanos_equipo',
      '#grupos' => $this->sitio->equipo($cache),
    ];
    $cache->applyTo($construccion);
    return $construccion;
  }

}
