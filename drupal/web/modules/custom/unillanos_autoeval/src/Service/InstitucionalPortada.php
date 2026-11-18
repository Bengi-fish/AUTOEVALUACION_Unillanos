<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Datos de la página «Institucional»: frase de introducción y cinco tarjetas.
 *
 * La frase sale de la configuración del micrositio; los títulos y textos de
 * las tarjetas son de este servicio (traducibles).
 */
class InstitucionalPortada {

  use StringTranslationTrait;

  /**
   * Constructor.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected UrlGeneratorInterface $urlGenerator,
    protected EnlacesEdicion $edicion,
  ) {}

  /**
   * Arma los datos de la página.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: intro (texto, puede estar vacío) y tarjetas (icon, title, text,
   *   url y cta).
   */
  public function datos(CacheableMetadata $cache): array {
    $config = $this->configFactory->get(DatosMarco::CONFIG);
    $cache->addCacheableDependency($config);
    $cta = (string) $this->t('Ver');
    $tarjetas = [
      [
        'clipboard',
        $this->t('Procesos institucionales'),
        $this->t('Autoevaluación con fines de acreditación institucional y condiciones institucionales por sede.'),
        'unillanos_autoeval.institucional_procesos',
      ],
      [
        'building',
        $this->t('Dependencias'),
        $this->t('Formatos que llena cada dependencia, por nivel y programa.'),
        'unillanos_autoeval.institucional_dependencias',
      ],
      [
        'users',
        $this->t('Directivos'),
        $this->t('Información de procesos de cada facultad.'),
        'unillanos_autoeval.institucional_directivos',
      ],
      [
        'scale',
        $this->t('Normatividad'),
        $this->t('Matriz legal aplicable a los procesos de la Universidad.'),
        'unillanos_autoeval.institucional_normatividad',
      ],
      [
        'target',
        $this->t('Cifras'),
        $this->t('Cifras y estadísticas nacionales e institucionales.'),
        'unillanos_autoeval.institucional_cifras',
      ],
    ];
    $salida = [];
    foreach ($tarjetas as [$icono, $titulo, $texto, $ruta]) {
      $salida[] = [
        'icon' => $icono,
        'title' => (string) $titulo,
        'text' => (string) $texto,
        'url' => $this->urlGenerator->generateFromRoute($ruta),
        'cta' => $cta,
      ];
    }
    return [
      'intro' => trim((string) ($config->get('institucional.introduccion') ?? '')),
      'tarjetas' => $salida,
      'edicion' => array_filter(['micrositio' => $this->edicion->micrositio()]),
    ];
  }

}
