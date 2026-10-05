<?php

namespace Drupal\unillanos_autoeval\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Ofrece solo los elementos de un modelo (lineamiento).
 *
 * El modelo se indica con el ajuste `lineamiento` (ID del nodo). Si el ajuste
 * está presente pero vacío, no se ofrece ningún elemento. Si no está, se
 * comporta como la selección normal de entidades.
 *
 * No extiende TermSelection porque esa clase lista el árbol completo del
 * vocabulario cuando no hay texto de búsqueda y se salta la consulta.
 */
#[EntityReferenceSelection(
  id: 'unillanos_elemento_modelo',
  label: new TranslatableMarkup('Elementos de un modelo'),
  entity_types: ['taxonomy_term'],
  group: 'unillanos_elemento_modelo',
  weight: 0,
)]
class ElementoDelModeloSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    // Como en TermSelection: quien no administra la taxonomía no ve los
    // términos sin publicar.
    if (!$this->currentUser->hasPermission('administer taxonomy')) {
      $query->condition('status', 1);
    }
    if (array_key_exists('lineamiento', $this->configuration)) {
      // El ID 0 no existe: sin modelo no se ofrece nada.
      $query->condition('field_lineamiento', $this->configuration['lineamiento'] ?: 0);
    }
    return $query;
  }

}
