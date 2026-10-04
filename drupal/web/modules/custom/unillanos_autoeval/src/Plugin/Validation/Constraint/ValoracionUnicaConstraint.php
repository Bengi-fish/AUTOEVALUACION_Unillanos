<?php

namespace Drupal\unillanos_autoeval\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * La pareja (proceso, elemento) es única entre las valoraciones.
 */
#[Constraint(
  id: 'UnillanosValoracionUnica',
  label: new TranslatableMarkup('Valoración única por proceso y elemento', [], ['context' => 'Validation']),
)]
class ValoracionUnicaConstraint extends SymfonyConstraint {

  /**
   * Mensaje cuando la valoración ya existe.
   */
  public string $message = 'Ya existe una valoración para este proceso y este elemento.';

}
