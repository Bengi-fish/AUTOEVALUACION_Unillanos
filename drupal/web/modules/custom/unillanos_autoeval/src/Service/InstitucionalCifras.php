<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Datos de /institucional/cifras: enlaces externos y tablero institucional.
 *
 * Los datos salen de la configuración del micrositio. El tablero es un servicio
 * externo: solo se entrega su dirección (https) para que la página lo cargue
 * cuando la persona lo pida.
 */
class InstitucionalCifras {

  use StringTranslationTrait;

  /**
   * Parámetros que Tableau Public necesita para incrustar un tablero.
   */
  const PARAMETROS_TABLERO = ':embed=y&:showVizHome=no&:toolbar=no';

  /**
   * Constructor.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EnlacesEdicion $edicion,
  ) {}

  /**
   * Arma los datos de la página.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: enlaces (title, text, url, cta y external), tablero (NULL o url,
   *   embed_url y host), edicion y vacio.
   */
  public function datos(CacheableMetadata $cache): array {
    $config = $this->configFactory->get(DatosMarco::CONFIG);
    $cache->addCacheableDependency($config);
    $cache->addCacheContexts(['user.permissions']);
    $enlaces = [];
    foreach ((array) $config->get('institucional.cifras.enlaces') as $enlace) {
      $url = trim((string) ($enlace['url'] ?? ''));
      $titulo = trim((string) ($enlace['titulo'] ?? ''));
      if ($titulo !== '' && $this->esHttps($url)) {
        $enlaces[] = [
          'title' => $titulo,
          'text' => trim((string) ($enlace['texto'] ?? '')),
          'url' => $url,
          'external' => TRUE,
          'cta' => (string) $this->t('Abrir'),
        ];
      }
    }
    $tablero = trim((string) $config->get('institucional.cifras.tablero_url'));
    $datos_tablero = NULL;
    if ($this->esHttps($tablero)) {
      $datos_tablero = [
        'url' => $tablero,
        'embed_url' => $tablero . (str_contains($tablero, '?') ? '&' : '?') . self::PARAMETROS_TABLERO,
        'host' => (string) parse_url($tablero, PHP_URL_HOST),
      ];
    }
    return [
      'enlaces' => $enlaces,
      'tablero' => $datos_tablero,
      'edicion' => array_filter(['micrositio' => $this->edicion->micrositio()]),
      'vacio' => !$enlaces && $datos_tablero === NULL,
    ];
  }

  /**
   * Indica si una dirección es https.
   *
   * @param string $url
   *   Dirección.
   *
   * @return bool
   *   TRUE si empieza por https:// y tiene dominio.
   */
  protected function esHttps(string $url): bool {
    return (bool) preg_match('~^https://[^\s/]+~i', $url);
  }

}
