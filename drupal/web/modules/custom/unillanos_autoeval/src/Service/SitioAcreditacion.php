<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;

/**
 * Noticias y equipo del sitio de Acreditación (Fase 9).
 *
 * Solo lee valores guardados de los tipos `noticia` y `miembro_equipo`; lo
 * vacío se omite. Las noticias salen en el orden en que se cargaron.
 */
class SitioAcreditacion {

  /**
   * Largo máximo del extracto de una noticia.
   */
  const EXTRACTO = 200;

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileUrlGeneratorInterface $generadorUrlArchivo,
    protected AccountInterface $usuario,
  ) {}

  /**
   * Noticias publicadas.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de lo que se lee.
   * @param int $limite
   *   Máximo de noticias; 0 para todas.
   *
   * @return array<int, array<string, mixed>>
   *   Cada noticia: title, url, extracto e imagen (src y alt, o NULL).
   */
  public function noticias(CacheableMetadata $cache, int $limite = 0): array {
    $cache->addCacheTags(['node_list:noticia']);
    $cache->addCacheContexts(['user.permissions', 'url.site']);
    $almacen = $this->entityTypeManager->getStorage('node');
    $consulta = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'noticia')
      ->condition('status', 1)
      ->sort('nid');
    if ($limite > 0) {
      $consulta->range(0, $limite);
    }
    $noticias = [];
    foreach ($almacen->loadMultiple($consulta->execute()) as $nodo) {
      /** @var \Drupal\node\NodeInterface $nodo */
      $cache->addCacheableDependency($nodo);
      $noticias[] = [
        'title' => (string) $nodo->label(),
        'url' => $nodo->toUrl()->toString(),
        'extracto' => $this->extracto($nodo),
        'imagen' => $this->imagen($nodo, $cache),
      ];
    }
    return $noticias;
  }

  /**
   * Personas del comité y del equipo, agrupadas.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de lo que se lee.
   *
   * @return array<int, array{title: string, personas: array<int, array<string, string>>}>
   *   Los grupos en el orden de aparición, con nombre, cargo, telefono y
   *   correo de cada persona (los vacíos se omiten).
   */
  public function equipo(CacheableMetadata $cache): array {
    $cache->addCacheTags(['node_list:miembro_equipo']);
    $cache->addCacheContexts(['user.permissions']);
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'miembro_equipo')
      ->condition('status', 1)
      ->sort('field_orden')
      ->sort('nid')
      ->execute();
    $grupos = [];
    foreach ($almacen->loadMultiple($ids) as $nodo) {
      /** @var \Drupal\node\NodeInterface $nodo */
      $cache->addCacheableDependency($nodo);
      $grupo = $this->valor($nodo, 'field_grupo');
      $persona = array_filter([
        'nombre' => (string) $nodo->label(),
        'cargo' => $this->valor($nodo, 'field_cargo'),
        'telefono' => $this->valor($nodo, 'field_telefono'),
        'correo' => $this->correo($nodo),
      ], fn(string $dato): bool => $dato !== '');
      $grupos[$grupo]['title'] = $grupo;
      $grupos[$grupo]['personas'][] = $persona;
    }
    return array_values($grupos);
  }

  /**
   * Valor de texto de un campo, o cadena vacía.
   */
  protected function valor(NodeInterface $nodo, string $campo): string {
    return $nodo->hasField($campo) ? trim((string) $nodo->get($campo)->value) : '';
  }

  /**
   * Correo guardado, solo si es una dirección válida.
   */
  protected function correo(NodeInterface $nodo): string {
    $correo = $this->valor($nodo, 'field_correo');
    return filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : '';
  }

  /**
   * Extracto del texto de una noticia (sin etiquetas).
   */
  protected function extracto(NodeInterface $nodo): string {
    $texto = trim((string) preg_replace('/\s+/u', ' ', Html::decodeEntities(strip_tags((string) $nodo->get('body')->value))));
    return $texto === '' ? '' : Unicode::truncate($texto, self::EXTRACTO, TRUE, TRUE);
  }

  /**
   * Imagen de una noticia.
   *
   * @return array{src: string, alt: string, width: int, height: int}|null
   *   NULL sin imagen o sin acceso al archivo.
   */
  protected function imagen(NodeInterface $nodo, CacheableMetadata $cache): ?array {
    if (!$nodo->hasField('field_imagen') || $nodo->get('field_imagen')->isEmpty()) {
      return NULL;
    }
    $elemento = $nodo->get('field_imagen')->first();
    $archivo = $elemento->entity ?? NULL;
    if (!$archivo instanceof FileInterface || !$archivo->access('view', $this->usuario)) {
      return NULL;
    }
    $cache->addCacheableDependency($archivo);
    $uri = (string) $archivo->getFileUri();
    if ($uri === '') {
      return NULL;
    }
    return [
      'src' => $this->generadorUrlArchivo->generateString($uri),
      'alt' => trim((string) $elemento->get('alt')->getValue()),
      'width' => (int) $elemento->get('width')->getValue(),
      'height' => (int) $elemento->get('height')->getValue(),
    ];
  }

}
