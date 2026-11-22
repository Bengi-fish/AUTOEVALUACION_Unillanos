<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Traits;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\file\FileInterface;

/**
 * Arma la imagen de una diapositiva del carrusel a partir de un campo.
 *
 * Lo comparten el carrusel de la portada y las galerías de las páginas, para
 * que todos usen los mismos estilos de imagen (800, 1280 y 1920 px) y las
 * mismas reglas de acceso. La clase que lo usa debe tener las propiedades
 * `$entityTypeManager` (EntityTypeManagerInterface), `$generadorUrlArchivo`
 * (FileUrlGeneratorInterface) y `$usuario` (AccountInterface).
 */
trait ImagenesDeCarruselTrait {

  /**
   * Estilos de imagen del carrusel (WebP) y su ancho.
   */
  public const ESTILOS_CARRUSEL = [
    'unillanos_carrusel_800' => 800,
    'unillanos_carrusel_1280' => 1280,
    'unillanos_carrusel_1920' => 1920,
  ];

  /**
   * Arma la imagen de un elemento de un campo de imagen.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $elemento
   *   Elemento de un campo de imagen (con archivo, ancho, alto y alt).
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché del archivo y de los estilos.
   *
   * @return array<string, mixed>|null
   *   Claves src, srcset, type, width, height y alt; NULL sin archivo o sin
   *   acceso a él.
   */
  protected function imagenDeCarrusel(FieldItemInterface $elemento, CacheableMetadata $cache): ?array {
    $archivo = $elemento->entity ?? NULL;
    if (!$archivo instanceof FileInterface || !$archivo->access('view', $this->usuario)) {
      return NULL;
    }
    $cache->addCacheableDependency($archivo);
    $cache->addCacheTags(['config:image_style_list']);
    $uri = (string) $archivo->getFileUri();
    if ($uri === '') {
      return NULL;
    }
    $partes = [];
    $estilos = $this->entityTypeManager->getStorage('image_style')->loadMultiple(array_keys(self::ESTILOS_CARRUSEL));
    foreach (self::ESTILOS_CARRUSEL as $nombre => $ancho) {
      if (isset($estilos[$nombre])) {
        $partes[] = $estilos[$nombre]->buildUrl($uri) . ' ' . $ancho . 'w';
      }
    }
    return [
      'src' => $this->generadorUrlArchivo->generateString($uri),
      'srcset' => implode(', ', $partes),
      'type' => 'image/webp',
      'width' => (int) $elemento->get('width')->getValue(),
      'height' => (int) $elemento->get('height')->getValue(),
      'alt' => trim((string) $elemento->get('alt')->getValue()),
    ];
  }

}
