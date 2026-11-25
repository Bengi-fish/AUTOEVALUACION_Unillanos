<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\unillanos_autoeval\Traits\ImagenesDeCarruselTrait;

/**
 * Galería de imágenes propia de un nodo (carrusel de su página).
 *
 * Lee SOLO el campo de imágenes del nodo que se le pasa (`field_galeria`): no
 * toca las diapositivas de la portada ni las imágenes de otros nodos. Entrega
 * los elementos listos para el componente `carousel`, en el orden del campo
 * (que la persona define arrastrando) y sin las imágenes a las que el
 * visitante no tiene acceso ni las que no se pueden mostrar. Sin imágenes
 * devuelve una lista vacía y la página no pinta el carrusel. Solo lee valores
 * guardados.
 */
class GaleriaImagenes {

  use ImagenesDeCarruselTrait;

  /**
   * Campo de imágenes de la galería.
   */
  const CAMPO = 'field_galeria';

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileUrlGeneratorInterface $generadorUrlArchivo,
    protected AccountInterface $usuario,
  ) {}

  /**
   * Arma las diapositivas de la galería de un nodo.
   *
   * @param \Drupal\node\NodeInterface $nodo
   *   Nodo con el campo `field_galeria` (programa o proceso institucional).
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de lo que se lee.
   *
   * @return array<int, array<string, mixed>>
   *   Una por imagen: title (la leyenda, o vacía), text vacío, url vacía,
   *   external FALSE e image (src, srcset, type, width, height y alt).
   */
  public function delNodo(NodeInterface $nodo, CacheableMetadata $cache): array {
    $cache->addCacheableDependency($nodo);
    $cache->addCacheContexts(['url.site']);
    if (!$nodo->hasField(self::CAMPO) || $nodo->get(self::CAMPO)->isEmpty()) {
      return [];
    }
    $diapositivas = [];
    foreach ($nodo->get(self::CAMPO) as $elemento) {
      $imagen = $this->imagenDeCarrusel($elemento, $cache);
      // Sin imagen visible no hay diapositiva: el carrusel no muestra huecos.
      if ($imagen === NULL) {
        continue;
      }
      $diapositivas[] = [
        'title' => trim((string) $elemento->get('title')->getValue()),
        'text' => '',
        'url' => '',
        'external' => FALSE,
        'image' => $imagen,
      ];
    }
    return $diapositivas;
  }

}
