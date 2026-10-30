<?php

namespace Drupal\unillanos_autoeval\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\unillanos_autoeval\Service\DocumentosPublicados;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Página de documentos (normatividad y documentos de los programas).
 *
 * Entrega los datos; el tema los pinta con la plantilla del gancho
 * `unillanos_documentos`.
 */
final class DocumentosController extends ControllerBase {

  /**
   * Constructor.
   */
  public function __construct(
    protected DocumentosPublicados $documentos,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('unillanos_autoeval.documentos_publicados'));
  }

  /**
   * Construye la página.
   *
   * @return array
   *   Arreglo de render.
   */
  public function documentos(): array {
    $cache = new CacheableMetadata();
    $construccion = [
      '#theme' => 'unillanos_documentos',
      '#datos' => $this->documentos->publicados($cache),
    ];
    $cache->applyTo($construccion);
    return $construccion;
  }

}
