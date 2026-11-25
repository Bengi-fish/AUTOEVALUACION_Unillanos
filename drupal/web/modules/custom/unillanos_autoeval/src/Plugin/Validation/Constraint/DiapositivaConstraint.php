<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Reglas de una diapositiva del carrusel: titular corto y enlace seguro.
 */
#[Constraint(
  id: 'UnillanosDiapositiva',
  label: new TranslatableMarkup('Diapositiva del carrusel', [], ['context' => 'Validation']),
)]
class DiapositivaConstraint extends SymfonyConstraint {

  /**
   * Largo máximo del titular.
   */
  const MAX_TITULAR = 90;

  /**
   * Mensaje cuando el titular es muy largo.
   */
  public string $mensajeTitular = 'El titular puede tener hasta 90 caracteres; este tiene @largo.';

  /**
   * Mensaje cuando el enlace no es una página del sitio ni una dirección https.
   */
  public string $mensajeEnlace = 'El enlace debe ser una página del sitio (por ejemplo /programas) o una dirección que empiece por https://.';

}
