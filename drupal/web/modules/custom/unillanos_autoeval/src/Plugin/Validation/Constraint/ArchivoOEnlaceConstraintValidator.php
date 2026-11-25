<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Plugin\Validation\Constraint;

use Drupal\media\MediaInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Valida que el documento tenga un archivo o un enlace https, no ambos.
 */
final class ArchivoOEnlaceConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $entity, Constraint $constraint): void {
    if (!$constraint instanceof ArchivoOEnlaceConstraint) {
      throw new UnexpectedTypeException($constraint, ArchivoOEnlaceConstraint::class);
    }
    if (!$entity instanceof MediaInterface || $entity->bundle() !== 'documento') {
      return;
    }
    // Un sitio sin los campos (antes de importar la configuración) no valida.
    if (!$entity->hasField('field_media_file')) {
      return;
    }
    $tiene_archivo = !$entity->get('field_media_file')->isEmpty();
    $tiene_enlace = $entity->hasField('field_enlace') && !$entity->get('field_enlace')->isEmpty();
    if ($tiene_archivo && $tiene_enlace) {
      $this->context->buildViolation($constraint->mensajeAmbos)
        ->atPath('field_enlace')
        ->addViolation();
    }
    elseif (!$tiene_archivo && !$tiene_enlace) {
      $this->context->buildViolation($constraint->mensajeNinguno)
        ->atPath('field_media_file')
        ->addViolation();
    }
    elseif ($tiene_enlace) {
      $uri = (string) $entity->get('field_enlace')->uri;
      if (!preg_match('~^https://[^\s/]+~i', $uri)) {
        $this->context->buildViolation($constraint->mensajeEsquema)
          ->atPath('field_enlace')
          ->addViolation();
      }
    }
  }

}
