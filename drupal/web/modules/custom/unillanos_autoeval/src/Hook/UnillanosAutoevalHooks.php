<?php

namespace Drupal\unillanos_autoeval\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\unillanos_autoeval\Service\CalculadorGrado;
use Drupal\unillanos_autoeval\Service\SugerenciaDestino;

/**
 * Hooks del módulo de autoevaluación.
 */
class UnillanosAutoevalHooks {

  /**
   * Constructor.
   */
  public function __construct(
    protected CalculadorGrado $calculadorGrado,
    protected SugerenciaDestino $sugerenciaDestino,
  ) {}

  /**
   * Implements hook_node_presave().
   */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    match ($node->bundle()) {
      'valoracion' => $this->calcularGrado($node),
      'hallazgo' => $this->sugerirDestino($node),
      default => NULL,
    };
  }

  /**
   * Implements hook_entity_type_alter().
   *
   * Agrega las restricciones de validación. Cada validador comprueba el
   * tipo de contenido o vocabulario y no hace nada si no le corresponde.
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    foreach (['node', 'taxonomy_term'] as $tipo) {
      if (isset($entity_types[$tipo])) {
        $entity_types[$tipo]->addConstraint('UnillanosClaveUnica');
      }
    }
    if (isset($entity_types['node'])) {
      $entity_types['node']->addConstraint('UnillanosValoracionUnica');
      $entity_types['node']->addConstraint('UnillanosProcesoSedeOPrograma');
      $entity_types['node']->addConstraint('UnillanosEstadoPorTipo');
    }
  }

  /**
   * Calcula `field_grado` desde `field_valoracion`; no se digita.
   */
  protected function calcularGrado(NodeInterface $valoracion): void {
    $grado = $this->calculadorGrado->calcular($valoracion->get('field_valoracion')->value);
    $valoracion->set('field_grado', $grado ? $grado->id() : NULL);
  }

  /**
   * Sugiere `field_destino` al crear el hallazgo, si no se eligió uno.
   */
  protected function sugerirDestino(NodeInterface $hallazgo): void {
    if (!$hallazgo->isNew() || !$hallazgo->get('field_destino')->isEmpty()) {
      return;
    }
    $destino = $this->sugerenciaDestino->sugerir(
      $hallazgo->get('field_proceso')->target_id,
      $hallazgo->get('field_elemento')->target_id,
    );
    if ($destino) {
      $hallazgo->set('field_destino', $destino);
    }
  }

}
