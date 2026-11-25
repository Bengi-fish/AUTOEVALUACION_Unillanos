<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Datos de la cabecera de «Institucional»: la frase de introducción.
 *
 * La frase sale de la configuración del micrositio. Las secciones de la página
 * las reúne `InstitucionalPagina`.
 */
class InstitucionalPortada {

  /**
   * Constructor.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EnlacesEdicion $edicion,
  ) {}

  /**
   * Arma los datos de la cabecera.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: intro (texto, puede estar vacío) y edicion.
   */
  public function datos(CacheableMetadata $cache): array {
    $config = $this->configFactory->get(DatosMarco::CONFIG);
    $cache->addCacheableDependency($config);
    return [
      'intro' => trim((string) ($config->get('institucional.introduccion') ?? '')),
      'edicion' => array_filter(['micrositio' => $this->edicion->micrositio()]),
    ];
  }

}
