<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;

/**
 * Diapositivas del carrusel de la portada.
 *
 * Hasta cinco diapositivas publicadas, por el campo orden (las sin orden al
 * final) y luego por antigüedad. Solo lee valores guardados: titular, texto
 * corto, enlace y la imagen con sus tamaños. Sin diapositivas devuelve una
 * lista vacía y la portada no pinta el carrusel.
 */
class CarruselPortada {

  use StringTranslationTrait;

  /**
   * Máximo de diapositivas que se muestran.
   */
  const MAXIMO = 5;

  /**
   * Estilos de imagen del carrusel (WebP) y su ancho.
   */
  const ESTILOS = [
    'unillanos_carrusel_800' => 800,
    'unillanos_carrusel_1280' => 1280,
    'unillanos_carrusel_1920' => 1920,
  ];

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileUrlGeneratorInterface $generadorUrlArchivo,
    protected AccountInterface $usuario,
    protected EnlacesEdicion $edicion,
  ) {}

  /**
   * Lee las diapositivas que se muestran.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: diapositivas (title, text, url, externo, imagen con src, srcset,
   *   type, width, height y alt, y edicion) y edicion (el enlace «Añadir una
   *   diapositiva»).
   */
  public function datos(CacheableMetadata $cache): array {
    $cache->addCacheTags(['node_list:diapositiva']);
    $cache->addCacheContexts(['user.permissions']);
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'diapositiva')
      ->condition('status', 1)
      ->sort('nid')
      ->execute();
    // Por orden (las sin orden al final) y luego por antigüedad: la base de
    // datos pone los vacíos primero, así que se ordena aquí.
    $nodos = $almacen->loadMultiple($ids);
    uasort($nodos, function (NodeInterface $a, NodeInterface $b): int {
      $orden = fn(NodeInterface $n): int => $n->get('field_orden')->isEmpty() ? PHP_INT_MAX : (int) $n->get('field_orden')->value;
      return [$orden($a), (int) $a->id()] <=> [$orden($b), (int) $b->id()];
    });
    $diapositivas = [];
    foreach ($nodos as $nodo) {
      if (count($diapositivas) >= self::MAXIMO) {
        break;
      }
      $cache->addCacheableDependency($nodo);
      $imagen = $this->imagen($nodo, $cache);
      // Sin imagen visible no hay diapositiva: el carrusel no muestra huecos.
      if ($imagen === NULL) {
        continue;
      }
      [$url, $externo] = $this->destino($nodo);
      $diapositivas[] = [
        'title' => trim((string) $nodo->label()),
        'text' => trim((string) $nodo->get('field_texto_corto')->value),
        'url' => $url,
        'externo' => $externo,
        'imagen' => $imagen,
        'edicion' => $this->edicion->contenido(
          (int) $nodo->id(),
          (string) $this->t('Editar la diapositiva «@titulo»', ['@titulo' => $nodo->label()]),
        ),
      ];
    }
    return [
      'diapositivas' => $diapositivas,
      'edicion' => array_filter(['nueva' => $this->edicion->diapositivaNueva()]),
    ];
  }

  /**
   * Arma la imagen de una diapositiva.
   *
   * @param \Drupal\node\NodeInterface $nodo
   *   Nodo `diapositiva`.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché.
   *
   * @return array<string, mixed>|null
   *   Claves src, srcset, type, width, height y alt; NULL sin imagen o sin
   *   acceso al archivo.
   */
  protected function imagen(NodeInterface $nodo, CacheableMetadata $cache): ?array {
    if ($nodo->get('field_imagen')->isEmpty()) {
      return NULL;
    }
    $elemento = $nodo->get('field_imagen')->first();
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
    $estilos = $this->entityTypeManager->getStorage('image_style')->loadMultiple(array_keys(self::ESTILOS));
    foreach (self::ESTILOS as $nombre => $ancho) {
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

  /**
   * Resuelve el destino del enlace de una diapositiva.
   *
   * @param \Drupal\node\NodeInterface $nodo
   *   Nodo `diapositiva`.
   *
   * @return array{0: string, 1: bool}
   *   La URL (vacía si no hay enlace o no es segura) y si es de otro sitio.
   */
  protected function destino(NodeInterface $nodo): array {
    if ($nodo->get('field_enlace')->isEmpty()) {
      return ['', FALSE];
    }
    $uri = trim((string) $nodo->get('field_enlace')->uri);
    if (preg_match('~^https://[^\s/]+~i', $uri)) {
      return [$uri, TRUE];
    }
    if (preg_match('~^(?:internal:/(?![/\\\\\s])|entity:node/\d+$)~', $uri)) {
      try {
        return [Url::fromUri($uri)->toString(), FALSE];
      }
      catch (\InvalidArgumentException) {
        return ['', FALSE];
      }
    }
    return ['', FALSE];
  }

}
