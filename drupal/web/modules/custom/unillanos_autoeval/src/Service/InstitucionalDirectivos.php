<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Datos de /institucional/directivos.
 *
 * Una tarjeta por facultad con documentos. Con un solo documento la tarjeta
 * lleva directo a él; con varios, a una lista de esa facultad en la misma
 * página. Solo se leen valores guardados y lo vacío se omite.
 */
class InstitucionalDirectivos extends InstitucionalBase {

  /**
   * Arma los datos de la página.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: tarjetas (title, text, url, cta y external), listas (id, titulo e
   *   items de las facultades con varios documentos), edicion y vacio.
   */
  public function datos(CacheableMetadata $cache): array {
    $cache->addCacheTags(['taxonomy_term_list:facultad']);
    $cache->addCacheContexts(['user.permissions']);
    $terminos = $this->entityTypeManager->getStorage('taxonomy_term');
    $ids = $terminos->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', 'facultad')
      ->sort('weight')
      ->sort('name')
      ->execute();
    $por_facultad = [];
    foreach ($this->mediosQueApuntan('field_facultad', [], $cache) as $medio) {
      $por_facultad[(int) $medio->get('field_facultad')->target_id][] = $medio;
    }
    $tarjetas = [];
    $listas = [];
    foreach ($terminos->loadMultiple($ids) as $facultad) {
      $cache->addCacheableDependency($facultad);
      $items = $this->elementos($por_facultad[(int) $facultad->id()] ?? [], $cache, TRUE);
      $edicion = $this->edicion->documentoPara('facultad', (int) $facultad->id(), (string) $this->t('Añadir un documento a @facultad', ['@facultad' => $facultad->label()]));
      if (!$items && !$edicion) {
        continue;
      }
      $sigla = $facultad->hasField('field_sigla') ? trim((string) $facultad->get('field_sigla')->value) : '';
      $tarjeta = [
        'title' => (string) $facultad->label(),
        'text' => $sigla,
        'edicion' => $edicion,
      ];
      if (count($items) === 1) {
        $tarjeta['url'] = (string) $items[0]['url'];
        $tarjeta['external'] = (bool) $items[0]['externo'];
        $tarjeta['cta'] = (string) $this->t('Abrir');
      }
      elseif (count($items) > 1) {
        $tarjeta['url'] = '#facultad-' . $facultad->id();
        $tarjeta['cta'] = (string) $this->t('Ver documentos');
        $listas[] = [
          'id' => 'facultad-' . $facultad->id(),
          'titulo' => (string) $facultad->label(),
          'items' => $items,
        ];
      }
      $tarjetas[] = $tarjeta;
    }
    return [
      'tarjetas' => $tarjetas,
      'listas' => $listas,
      'vacio' => !$tarjetas,
    ];
  }

}
