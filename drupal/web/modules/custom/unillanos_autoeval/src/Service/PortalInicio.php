<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Datos de la portada del portal (`/`): texto, accesos y sitios de interés.
 *
 * Los datos salen de la configuración del micrositio. Solo se entrega lo que
 * está completo y es seguro: lo vacío se omite.
 */
class PortalInicio {

  /**
   * Constructor.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EnlacesEdicion $edicion,
  ) {}

  /**
   * Arma los datos de la portada del portal.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: titulo, parrafos, accesos (title, text, url, principal, external),
   *   sitios (title, url) y edicion.
   */
  public function datos(CacheableMetadata $cache): array {
    $config = $this->configFactory->get(DatosMarco::CONFIG);
    $cache->addCacheableDependency($config);
    $cache->addCacheContexts(['user.permissions']);
    $portal = (array) ($config->get('portal') ?? []);
    $parrafos = array_values(array_filter(array_map(
      'trim',
      preg_split('/\R{2,}/u', trim((string) ($portal['texto'] ?? ''))) ?: [],
    ), fn(string $parrafo): bool => $parrafo !== ''));
    $accesos = [];
    foreach ((array) ($portal['accesos'] ?? []) as $acceso) {
      $titulo = trim((string) ($acceso['titulo'] ?? ''));
      $url = trim((string) ($acceso['url'] ?? ''));
      if ($titulo !== '' && $this->urlValida($url)) {
        $accesos[] = [
          'title' => $titulo,
          'text' => trim((string) ($acceso['texto'] ?? '')),
          'url' => $url,
          'principal' => $accesos === [],
          'external' => str_starts_with($url, 'https://'),
        ];
      }
    }
    $sitios = [];
    foreach ((array) ($portal['sitios'] ?? []) as $sitio) {
      $titulo = trim((string) ($sitio['titulo'] ?? ''));
      $url = trim((string) ($sitio['url'] ?? ''));
      if ($titulo !== '' && str_starts_with($url, 'https://') && $this->urlValida($url)) {
        $sitios[] = ['title' => $titulo, 'url' => $url];
      }
    }
    return [
      'titulo' => trim((string) ($portal['titulo'] ?? '')),
      'parrafos' => $parrafos,
      'accesos' => $accesos,
      'sitios' => $sitios,
      'edicion' => array_filter(['micrositio' => $this->edicion->micrositio()]),
    ];
  }

  /**
   * Indica si una URL es un destino interno («/ruta») o https.
   *
   * @param string $url
   *   La URL guardada.
   *
   * @return bool
   *   TRUE si es segura de imprimir.
   */
  protected function urlValida(string $url): bool {
    return (bool) preg_match('~^(?:/(?![/\\\\\s])|https://[^\s/]+)~i', $url);
  }

}
