<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\unillanos_autoeval\Service\InstitucionalPagina;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Página de «Institucional»: la Universidad con la interfaz de un programa.
 *
 * Entrega los datos de `InstitucionalPagina`; el tema los pinta con la
 * plantilla del gancho `unillanos_institucional`. Las cinco rutas de la
 * etapa 3 (`/institucional/procesos`, etc.) redirigen a su sección.
 */
final class InstitucionalController extends ControllerBase {

  /**
   * Constructor.
   */
  public function __construct(protected InstitucionalPagina $pagina) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get(InstitucionalPagina::class));
  }

  /**
   * Página de «Institucional» (/institucional).
   *
   * @return array<string, mixed>
   *   Arreglo de render.
   */
  public function portada(): array {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user.permissions']);
    $construccion = [
      '#theme' => 'unillanos_institucional',
      '#datos' => $this->pagina->datos($cache),
    ];
    $cache->applyTo($construccion);
    return $construccion;
  }

}
