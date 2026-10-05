<?php

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Consulta el modelo (lineamiento) con el que se hizo un proceso.
 *
 * Cada proceso declara su modelo en `field_lineamiento`, y cada elemento del
 * modelo declara el suyo. Los elementos de un proceso deben pertenecer al
 * modelo del proceso. No hay equivalencias entre modelos.
 */
class ModeloProceso {

  /**
   * Tipo de proceso de los lineamientos que son modelos de un proceso.
   *
   * Excluye, por ejemplo, el Decreto 1330 (`registro_calificado`).
   */
  const TIPO_PROCESO_MODELO = 'acreditacion';

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Lista los modelos que puede declarar un proceso.
   *
   * @return string[]
   *   Norma de cada lineamiento permitido, con el ID del nodo como clave.
   */
  public function modelosPermitidos(): array {
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'lineamiento')
      ->condition('field_tipo_proceso', self::TIPO_PROCESO_MODELO)
      ->sort('nid')
      ->execute();
    $modelos = [];
    foreach ($almacen->loadMultiple($ids) as $lineamiento) {
      $modelos[(int) $lineamiento->id()] = $this->nombre($lineamiento->id());
    }
    return $modelos;
  }

  /**
   * Devuelve el ID del lineamiento (modelo) de un proceso.
   *
   * @param int|string|null $proceso_id
   *   ID del nodo `proceso`.
   *
   * @return int|null
   *   El ID del lineamiento, o NULL si el proceso no existe o no lo declara.
   */
  public function lineamientoDelProceso(int|string|NULL $proceso_id): ?int {
    $proceso = $proceso_id ? $this->entityTypeManager->getStorage('node')->load($proceso_id) : NULL;
    $id = $proceso?->get('field_lineamiento')->target_id;
    return $id ? (int) $id : NULL;
  }

  /**
   * Devuelve el ID del lineamiento (modelo) de un elemento del modelo.
   *
   * @param int|string|null $elemento_id
   *   ID del término `elemento_modelo`.
   *
   * @return int|null
   *   El ID del lineamiento, o NULL si el elemento no existe o no lo tiene.
   */
  public function lineamientoDelElemento(int|string|NULL $elemento_id): ?int {
    $elemento = $elemento_id ? $this->entityTypeManager->getStorage('taxonomy_term')->load($elemento_id) : NULL;
    $id = $elemento?->get('field_lineamiento')->target_id;
    return $id ? (int) $id : NULL;
  }

  /**
   * Devuelve el nombre legible de un modelo: la norma, o el título.
   */
  public function nombre(int|string|NULL $lineamiento_id): string {
    $lineamiento = $lineamiento_id ? $this->entityTypeManager->getStorage('node')->load($lineamiento_id) : NULL;
    if (!$lineamiento) {
      return '(sin modelo)';
    }
    return (string) ($lineamiento->get('field_norma')->value ?: $lineamiento->label());
  }

}
