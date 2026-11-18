<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Un documento tiene un archivo o un enlace externo https, no ambos.
 */
#[Constraint(
  id: 'UnillanosArchivoOEnlace',
  label: new TranslatableMarkup('Documento con archivo o enlace', [], ['context' => 'Validation']),
)]
class ArchivoOEnlaceConstraint extends SymfonyConstraint {

  /**
   * Mensaje cuando no hay ni archivo ni enlace.
   */
  public string $mensajeNinguno = 'Suba un archivo o escriba un enlace externo.';

  /**
   * Mensaje cuando hay ambos.
   */
  public string $mensajeAmbos = 'Un documento lleva un archivo o un enlace, no ambos. Deje solo uno.';

  /**
   * Mensaje cuando el enlace no es https.
   */
  public string $mensajeEsquema = 'El enlace debe empezar por https://.';

}
