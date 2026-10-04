<?php

namespace Drupal\unillanos_autoeval\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * El campo de estado solo admite los valores de su tipo de contenido.
 */
#[Constraint(
  id: 'UnillanosEstadoPorTipo',
  label: new TranslatableMarkup('Estado válido según el tipo de contenido', [], ['context' => 'Validation']),
)]
class EstadoPorTipoConstraint extends SymfonyConstraint {

  /**
   * Valores permitidos de `field_estado` por tipo de contenido.
   */
  const VALORES = [
    'proceso' => ['planeado', 'en_curso', 'finalizado'],
    'seguimiento' => ['borrador', 'reportado', 'verificado'],
  ];

  /**
   * Mensaje cuando el valor no corresponde al tipo.
   */
  public string $message = 'El estado %estado no es válido para este tipo de contenido.';

}
