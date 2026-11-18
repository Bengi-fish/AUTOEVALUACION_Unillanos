<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Hook;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;

/**
 * Selector «¿Qué va a cargar?» del formulario del documento.
 *
 * Un documento lleva un archivo o un enlace externo. El selector muestra solo
 * el campo que toca (con #states, así que sin JavaScript se ven los dos campos
 * con su ayuda) y, al guardar, deja únicamente lo que se eligió. La regla de
 * fondo la garantiza la restricción UnillanosArchivoOEnlace.
 */
class DocumentoArchivoOEnlaceHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_alter().
   *
   * @param array<mixed> $form
   *   El formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    $objeto = $form_state->getBuildInfo()['callback_object'] ?? NULL;
    if (!$objeto instanceof EntityFormInterface) {
      return;
    }
    $medio = $objeto->getEntity();
    if (!$medio instanceof MediaInterface || $medio->bundle() !== 'documento') {
      return;
    }
    if (!isset($form['field_media_file'], $form['field_enlace'])) {
      return;
    }
    $con_enlace = !$medio->get('field_enlace')->isEmpty() && $medio->get('field_media_file')->isEmpty();
    $form['tipo_carga'] = [
      '#type' => 'radios',
      '#title' => $this->t('¿Qué va a cargar?'),
      '#description' => $this->t('Solo se guarda lo que elija aquí: si elige «Enlace», el archivo no se guarda, y al revés.'),
      '#options' => [
        'archivo' => $this->t('Archivo'),
        'enlace' => $this->t('Enlace'),
      ],
      '#default_value' => $con_enlace ? 'enlace' : 'archivo',
      '#weight' => 1,
    ];
    $form['field_media_file']['#states'] = [
      'visible' => [':input[name="tipo_carga"]' => ['value' => 'archivo']],
    ];
    $form['field_enlace']['#states'] = [
      'visible' => [':input[name="tipo_carga"]' => ['value' => 'enlace']],
    ];
    $form['#attached']['library'][] = 'unillanos_autoeval/acreditacion';
    $form['#entity_builders'][] = [static::class, 'conservarLoElegido'];
  }

  /**
   * Entity builder: borra del documento lo que no se eligió.
   *
   * @param string $tipo_entidad
   *   Tipo de entidad.
   * @param \Drupal\Core\Entity\EntityInterface $entidad
   *   El medio que se construye.
   * @param array<mixed> $form
   *   El formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  public static function conservarLoElegido(string $tipo_entidad, EntityInterface $entidad, array &$form, FormStateInterface $form_state): void {
    if (!$entidad instanceof MediaInterface) {
      return;
    }
    $eleccion = $form_state->getValue('tipo_carga');
    if ($eleccion === 'enlace') {
      $entidad->set('field_media_file', NULL);
    }
    elseif ($eleccion === 'archivo') {
      $entidad->set('field_enlace', NULL);
    }
  }

}
