<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\unillanos_autoeval\Traits\EtiquetasYOrdenTrait;

/**
 * Lo común de las páginas de «Institucional»: leer documentos visibles.
 *
 * Solo lee valores guardados. Cada documento se comprueba (publicado y con
 * acceso de lectura) y sale como elemento de `doc-list` (ver
 * DocumentosPublicados::item()).
 */
abstract class InstitucionalBase {

  use StringTranslationTrait;
  use EtiquetasYOrdenTrait;

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DocumentosPublicados $documentos,
    protected EnlacesEdicion $edicion,
  ) {}

  /**
   * Carga los documentos publicados que apuntan a un contenido.
   *
   * @param string $campo
   *   Campo del medio (`field_dependencia`, `field_facultad`, `field_proceso`).
   * @param int[] $ids
   *   Identificadores a los que apunta; vacío devuelve todos los que tengan
   *   el campo lleno.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return \Drupal\media\MediaInterface[]
   *   Medios `documento` publicados y visibles, por ID.
   */
  protected function mediosQueApuntan(string $campo, array $ids, CacheableMetadata $cache): array {
    $cache->addCacheTags(['media_list']);
    $cache->addCacheContexts(['user.permissions']);
    $almacen = $this->entityTypeManager->getStorage('media');
    $consulta = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('bundle', 'documento')
      ->condition('status', 1);
    if ($ids) {
      $consulta->condition($campo, $ids, 'IN');
    }
    else {
      $consulta->exists($campo);
    }
    $medios = [];
    foreach ($almacen->loadMultiple($consulta->execute()) as $id => $medio) {
      // La consulta no filtra los medios por permiso: se comprueba cada uno.
      $acceso = $medio->access('view', NULL, TRUE);
      $cache->addCacheableDependency($acceso);
      if ($acceso->isAllowed()) {
        $medios[$id] = $medio;
      }
    }
    return $medios;
  }

  /**
   * Convierte medios en elementos de `doc-list`, ordenados por título.
   *
   * @param \Drupal\media\MediaInterface[] $medios
   *   Medios `documento`.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché.
   * @param bool $con_categoria
   *   Si se muestra la categoría como etiqueta del elemento y se ordena por
   *   ella (informe, documento base, acto administrativo, soporte…).
   *
   * @return array<int, array<string, mixed>>
   *   Elementos con title, cat, desc, meta, kind, rotulo, url y externo.
   */
  protected function elementos(array $medios, CacheableMetadata $cache, bool $con_categoria = FALSE): array {
    $items = [];
    foreach ($medios as $medio) {
      $item = $this->documentos->item($medio, $cache);
      if ($item === NULL) {
        continue;
      }
      if (!$con_categoria) {
        $item['cat'] = '';
      }
      $items[] = $item;
    }
    $orden = array_flip(DocumentosPublicados::CATEGORIAS_PROGRAMA);
    $posicion = fn(array $item): int => $con_categoria ? ($orden[$item['categoria']] ?? count($orden)) : 0;
    usort($items, fn(array $a, array $b): int => $posicion($a) <=> $posicion($b)
      ?: $this->compararTextos((string) $a['title'], (string) $b['title']));
    return $items;
  }

}
