<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Datos de /institucional/normatividad (la matriz legal aplicable).
 *
 * Una fila por norma publicada, en el orden de la matriz (el de carga), con
 * las siete columnas del normograma. También entrega los valores de los dos
 * filtros (origen y tipo de norma) con sus conteos. Solo se leen valores
 * guardados: no se cuenta ni se calcula nada que no esté en las filas.
 */
class InstitucionalNormas extends InstitucionalBase {

  /**
   * Arma los datos de la página.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: filas (cada una con origen, origen_clave, tipo, numero, entidad,
   *   epigrafe, capitulos, aplicacion, link y buscar), origenes y tipos
   *   (opciones con valor, etiqueta y conteo), total, edicion y vacio.
   */
  public function datos(CacheableMetadata $cache): array {
    $cache->addCacheTags(['node_list:norma', 'media_list']);
    $cache->addCacheContexts(['user.permissions']);
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'norma')
      ->condition('status', 1)
      ->sort('nid')
      ->execute();
    $filas = [];
    $origenes = [];
    $tipos = [];
    foreach ($almacen->loadMultiple($ids) as $norma) {
      if (!$norma->access('view')) {
        continue;
      }
      $cache->addCacheableDependency($norma);
      $origen_clave = (string) $norma->get('field_origen_norma')->value;
      $origen = $this->etiquetaLista($norma->get('field_origen_norma'));
      $tipo = trim((string) $norma->get('field_tipo_norma')->value);
      $fila = [
        'id' => (int) $norma->id(),
        'origen' => $origen,
        'origen_clave' => $origen_clave,
        'tipo' => $tipo,
        'tipo_clave' => $this->clave($tipo),
        'numero' => trim((string) $norma->get('field_numero_fecha')->value),
        'entidad' => trim((string) $norma->get('field_entidad')->value),
        'epigrafe' => trim((string) $norma->get('field_epigrafe')->value),
        'capitulos' => trim((string) $norma->get('field_capitulos')->value),
        'aplicacion' => trim((string) $norma->get('field_aplicacion')->value),
        'link' => $this->enlaceDelDocumento($norma, $cache),
        'edicion' => $this->edicion->contenido(
          (int) $norma->id(),
          (string) $this->t('Editar la norma @norma', ['@norma' => $norma->label()]),
        ),
      ];
      $fila['buscar'] = mb_strtolower(implode(' ', [
        $fila['tipo'],
        $fila['numero'],
        $fila['entidad'],
        $fila['epigrafe'],
        $fila['capitulos'],
        $fila['aplicacion'],
      ]));
      $filas[] = $fila;
      if ($origen_clave !== '') {
        $origenes[$origen_clave]['valor'] = $origen_clave;
        $origenes[$origen_clave]['etiqueta'] = $origen;
        $origenes[$origen_clave]['conteo'] = ($origenes[$origen_clave]['conteo'] ?? 0) + 1;
      }
      if ($tipo !== '') {
        $tipos[$fila['tipo_clave']]['valor'] = $fila['tipo_clave'];
        $tipos[$fila['tipo_clave']]['etiqueta'] = $tipo;
        $tipos[$fila['tipo_clave']]['conteo'] = ($tipos[$fila['tipo_clave']]['conteo'] ?? 0) + 1;
      }
    }
    return [
      'filas' => $filas,
      'origenes' => array_values($origenes),
      'tipos' => array_values($tipos),
      'total' => count($filas),
      'edicion' => array_filter(['nueva' => $this->edicion->normaNueva()]),
      'vacio' => !$filas,
    ];
  }

  /**
   * Clave segura (letras, números y guion) de un tipo de norma.
   *
   * @param string $texto
   *   Tipo de norma tal como está guardado.
   *
   * @return string
   *   La clave, por ejemplo «acuerdo-superior».
   */
  protected function clave(string $texto): string {
    $sin_tildes = transliterator_transliterate('Any-Latin; Latin-ASCII', $texto) ?: $texto;
    $limpio = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $sin_tildes));
    return trim($limpio, '-') ?: 'otro';
  }

  /**
   * Enlace al documento de la norma (archivo o enlace externo), si lo hay.
   *
   * @param \Drupal\node\NodeInterface $norma
   *   Nodo `norma`.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché.
   *
   * @return array<string, mixed>|null
   *   Con title, url y externo; NULL si no hay documento visible.
   */
  protected function enlaceDelDocumento(NodeInterface $norma, CacheableMetadata $cache): ?array {
    $medio = $norma->get('field_documento')->entity;
    if (!$medio instanceof MediaInterface || !$medio->isPublished()) {
      return NULL;
    }
    $acceso = $medio->access('view', NULL, TRUE);
    $cache->addCacheableDependency($acceso);
    if (!$acceso->isAllowed()) {
      return NULL;
    }
    $item = $this->documentos->item($medio, $cache);
    if ($item === NULL) {
      return NULL;
    }
    return [
      'title' => (string) $item['title'],
      'url' => (string) $item['url'],
      'externo' => (bool) $item['externo'],
    ];
  }

}
