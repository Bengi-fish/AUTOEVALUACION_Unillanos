<?php

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Calcula el avance de una meta y el avance ponderado de un plan.
 */
class AvanceMeta {

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Avance de una meta: el del último seguimiento verificado.
   *
   * El orden se toma del periodo (`2025-1` < `2025-2`).
   *
   * @param int|string $meta_id
   *   ID del nodo `meta`.
   *
   * @return float
   *   Avance entre 0 y 1; 0 si no hay seguimientos verificados.
   */
  public function avanceMeta(int|string $meta_id): float {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'seguimiento')
      ->condition('field_meta', $meta_id)
      ->condition('field_estado', 'verificado')
      ->sort('field_periodo', 'DESC')
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return 0.0;
    }
    $seguimiento = $storage->load(reset($ids));
    return (float) $seguimiento->get('field_avance')->value;
  }

  /**
   * Avance de un plan: suma de (peso × avance) de sus metas.
   *
   * @param int|string $plan_id
   *   ID del nodo `plan_mejoramiento`.
   *
   * @return float
   *   Avance ponderado, redondeado a 4 decimales.
   */
  public function avancePlan(int|string $plan_id): float {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'meta')
      ->condition('field_plan', $plan_id)
      ->execute();
    $total = 0.0;
    foreach ($storage->loadMultiple($ids) as $meta) {
      $peso = (float) $meta->get('field_peso')->value;
      $total += $peso * $this->avanceMeta($meta->id());
    }
    return round($total, 4);
  }

}
