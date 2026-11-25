<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Datos de /institucional/procesos.
 *
 * El proceso institucional más reciente (con sede y sin programa, que no sea de
 * condiciones de calidad) se muestra con la estructura de la página de
 * programa: factores y plan si existen, y sus documentos. Debajo, las
 * condiciones institucionales por sede. Solo se leen valores guardados y lo
 * vacío se omite.
 */
class InstitucionalProcesos extends InstitucionalBase {

  /**
   * Tipo de proceso de las «condiciones institucionales» por sede.
   */
  const TIPO_CONDICIONES = 'registro_calificado';

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    DocumentosPublicados $documentos,
    EnlacesEdicion $edicion,
    protected FactoresPrograma $factores,
    protected PlanMejoramientoPrograma $plan,
    protected GaleriaImagenes $galeria,
  ) {
    parent::__construct($entityTypeManager, $documentos, $edicion);
  }

  /**
   * Arma los datos de la página.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: principal (NULL o el proceso con titulo, periodo, estado, sede,
   *   resultado, factores, plan, documentos, galeria y edicion), condiciones
   *   (una por sede: id, titulo y procesos con titulo, periodo y items),
   *   edicion y vacio (TRUE si no hay nada que mostrar).
   */
  public function datos(CacheableMetadata $cache): array {
    $cache->addCacheTags(['node_list:proceso', 'node_list:programa', 'taxonomy_term_list:sede']);
    $cache->addCacheContexts(['user.permissions']);
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'proceso')
      ->condition('status', 1)
      ->exists('field_sede')
      ->notExists('field_programa')
      ->sort('nid', 'DESC')
      ->execute();
    $procesos = [];
    foreach ($almacen->loadMultiple($ids) as $proceso) {
      if ($proceso->access('view')) {
        $procesos[] = $proceso;
      }
    }
    $principal = NULL;
    $condiciones = [];
    foreach ($procesos as $proceso) {
      $tipo = (string) $proceso->get('field_tipo_proceso')->value;
      if ($tipo === self::TIPO_CONDICIONES) {
        $condiciones[] = $proceso;
      }
      elseif ($principal === NULL) {
        $principal = $proceso;
      }
    }
    $salida = [
      'principal' => $principal ? $this->principal($principal, $cache) : NULL,
      'condiciones' => $this->condiciones($condiciones, $cache),
      'edicion' => [],
    ];
    $salida['vacio'] = $salida['principal'] === NULL && !$salida['condiciones'];
    return $salida;
  }

  /**
   * Arma el proceso principal.
   *
   * @param \Drupal\node\NodeInterface $proceso
   *   Nodo `proceso`.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché.
   *
   * @return array<string, mixed>
   *   Ver ::datos().
   */
  protected function principal(NodeInterface $proceso, CacheableMetadata $cache): array {
    $cache->addCacheableDependency($proceso);
    $factores = $this->factores->delProceso($proceso, $cache);
    $con_plan = $this->plan->delProceso($proceso, $factores, $cache);
    $items = $this->elementos($this->mediosQueApuntan('field_proceso', [(int) $proceso->id()], $cache), $cache, TRUE);
    $sede = $proceso->get('field_sede')->entity;
    $edicion = array_filter([
      'proceso' => $this->edicion->contenido((int) $proceso->id(), (string) $this->t('Editar el proceso')),
      'documento' => $this->edicion->documentoPara('proceso', (int) $proceso->id(), (string) $this->t('Añadir un documento')),
    ]);
    return [
      'titulo' => (string) $proceso->label(),
      'periodo' => trim((string) $proceso->get('field_periodo_evaluado')->value),
      'estado' => $this->etiquetaLista($proceso->get('field_estado')),
      'sede' => $sede ? (string) $sede->label() : '',
      'resultado' => trim((string) $proceso->get('field_resultado')->value),
      'factores' => $con_plan['factores'],
      'plan' => $con_plan['plan'],
      'documentos' => $items,
      // Imágenes propias del proceso institucional (field_galeria): el
      // carrusel de la página «Institucional». Nunca las de la portada.
      'galeria' => $this->galeria->delNodo($proceso, $cache),
      'edicion' => $edicion,
    ];
  }

  /**
   * Agrupa las condiciones institucionales por sede.
   *
   * @param \Drupal\node\NodeInterface[] $procesos
   *   Procesos de condiciones (más recientes primero).
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché.
   *
   * @return array<int, array<string, mixed>>
   *   Una por sede con documentos o con edición disponible.
   */
  protected function condiciones(array $procesos, CacheableMetadata $cache): array {
    $por_sede = [];
    foreach ($procesos as $proceso) {
      $cache->addCacheableDependency($proceso);
      $sede = $proceso->get('field_sede')->entity;
      if (!$sede) {
        continue;
      }
      $items = $this->elementos($this->mediosQueApuntan('field_proceso', [(int) $proceso->id()], $cache), $cache, TRUE);
      $edicion = $this->edicion->documentoPara('proceso', (int) $proceso->id(), (string) $this->t('Añadir un documento a @proceso', ['@proceso' => $proceso->label()]));
      if (!$items && !$edicion) {
        continue;
      }
      $id = (int) $sede->id();
      $por_sede[$id]['id'] = 'sede-' . $id;
      $por_sede[$id]['titulo'] = (string) $sede->label();
      $por_sede[$id]['procesos'][] = [
        'titulo' => (string) $proceso->label(),
        'periodo' => trim((string) $proceso->get('field_periodo_evaluado')->value),
        'items' => $items,
        'edicion' => $edicion,
      ];
    }
    return array_values($por_sede);
  }

}
