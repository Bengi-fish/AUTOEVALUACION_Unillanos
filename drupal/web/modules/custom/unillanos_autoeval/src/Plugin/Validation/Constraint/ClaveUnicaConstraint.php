<?php

namespace Drupal\unillanos_autoeval\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * La clave (`field_clave`) es único en `programa` y en `elemento_modelo`.
 */
#[Constraint(
  id: 'UnillanosClaveUnica',
  label: new TranslatableMarkup('Clave única', [], ['context' => 'Validation']),
)]
class ClaveUnicaConstraint extends SymfonyConstraint {

  /**
   * Mensaje cuando la clave se repite.
   */
  public string $message = 'La clave %clave ya está en uso.';

}
