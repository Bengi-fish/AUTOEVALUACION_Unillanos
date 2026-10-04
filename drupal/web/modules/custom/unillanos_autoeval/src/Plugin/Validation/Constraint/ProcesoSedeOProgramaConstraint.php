<?php

namespace Drupal\unillanos_autoeval\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Un proceso tiene sede o programa, exactamente uno de los dos.
 */
#[Constraint(
  id: 'UnillanosProcesoSedeOPrograma',
  label: new TranslatableMarkup('Proceso con sede o programa', [], ['context' => 'Validation']),
)]
class ProcesoSedeOProgramaConstraint extends SymfonyConstraint {

  /**
   * Mensaje cuando no hay ninguno.
   */
  public string $mensajeNinguno = 'Indique la sede o el programa del proceso.';

  /**
   * Mensaje cuando hay ambos.
   */
  public string $mensajeAmbos = 'Un proceso tiene sede o programa, no ambos.';

}
