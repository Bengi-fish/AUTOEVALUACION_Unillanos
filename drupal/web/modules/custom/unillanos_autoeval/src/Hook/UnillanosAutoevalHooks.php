<?php

namespace Drupal\unillanos_autoeval\Hook;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\unillanos_autoeval\Service\CalculadorGrado;
use Drupal\unillanos_autoeval\Service\ModeloProceso;
use Drupal\unillanos_autoeval\Service\SugerenciaDestino;

/**
 * Hooks del módulo de autoevaluación.
 */
class UnillanosAutoevalHooks {

  use StringTranslationTrait;

  /**
   * Constructor.
   */
  public function __construct(
    protected CalculadorGrado $calculadorGrado,
    protected SugerenciaDestino $sugerenciaDestino,
    protected ModeloProceso $modeloProceso,
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
      $entity_types['node']->addConstraint('UnillanosModeloDelProceso');
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() para node_form.
   *
   * - Proceso: el selector de modelo ofrece solo los modelos permitidos.
   * - Valoración y hallazgo: el elemento se busca solo entre los elementos
   *   del modelo del proceso elegido. Al elegir otro proceso, el campo se
   *   refresca por AJAX. La restricción UnillanosModeloDelProceso sigue
   *   siendo la garantía al guardar.
   */
  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(array &$form, FormStateInterface $form_state): void {
    $formulario = $form_state->getFormObject();
    if (!$formulario instanceof EntityFormInterface) {
      return;
    }
    $nodo = $formulario->getEntity();
    if (!$nodo instanceof NodeInterface) {
      return;
    }
    match ($nodo->bundle()) {
      'proceso' => $this->limitarModelos($form),
      'valoracion', 'hallazgo' => $this->limitarElementos($form, $form_state, $nodo),
      default => NULL,
    };
  }

  /**
   * Deja en el selector de modelo solo los lineamientos permitidos.
   */
  protected function limitarModelos(array &$form): void {
    if (!isset($form['field_lineamiento']['widget']['#options'])) {
      return;
    }
    $permitidos = $this->modeloProceso->modelosPermitidos();
    $form['field_lineamiento']['widget']['#options'] = array_filter(
      $form['field_lineamiento']['widget']['#options'],
      fn($clave): bool => $clave === '_none' || isset($permitidos[(int) $clave]),
      ARRAY_FILTER_USE_KEY,
    );
  }

  /**
   * Filtra el autocompletado del elemento por el modelo del proceso.
   */
  protected function limitarElementos(array &$form, FormStateInterface $form_state, NodeInterface $nodo): void {
    $elemento = &$form['field_elemento']['widget'][0]['target_id'];
    $proceso = &$form['field_proceso']['widget'][0]['target_id'];
    if (!isset($elemento, $proceso)) {
      return;
    }
    // Lo que el usuario acaba de escribir manda sobre lo guardado.
    $entrada = $form_state->getUserInput()['field_proceso'][0]['target_id'] ?? NULL;
    $proceso_id = $entrada !== NULL
      ? EntityAutocomplete::extractEntityIdFromAutocompleteInput($entrada)
      : $nodo->get('field_proceso')->target_id;

    $elemento['#selection_handler'] = 'unillanos_elemento_modelo';
    $elemento['#selection_settings']['lineamiento'] = $this->modeloProceso->lineamientoDelProceso($proceso_id);
    $elemento['#description'] = $this->t('Elija primero el proceso: solo se ofrecen los elementos de su modelo.');
    $form['field_elemento']['#prefix'] = '<div id="unillanos-elemento-wrapper">';
    $form['field_elemento']['#suffix'] = '</div>';
    $proceso['#ajax'] = [
      'callback' => [static::class, 'refrescarElemento'],
      'event' => 'autocompleteclose change',
      'wrapper' => 'unillanos-elemento-wrapper',
      'disable-refocus' => TRUE,
    ];
  }

  /**
   * Callback AJAX: devuelve el campo del elemento ya filtrado.
   */
  public static function refrescarElemento(array $form): array {
    return $form['field_elemento'];
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
