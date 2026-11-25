<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Plugin\Validation\Constraint;

use Drupal\node\NodeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Valida el titular (hasta 90 caracteres) y el enlace de una diapositiva.
 */
final class DiapositivaConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $entity, Constraint $constraint): void {
    if (!$constraint instanceof DiapositivaConstraint) {
      throw new UnexpectedTypeException($constraint, DiapositivaConstraint::class);
    }
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'diapositiva') {
      return;
    }
    $largo = mb_strlen((string) $entity->label());
    if ($largo > DiapositivaConstraint::MAX_TITULAR) {
      $this->context->buildViolation($constraint->mensajeTitular, ['@largo' => $largo])
        ->atPath('title')
        ->addViolation();
    }
    if ($entity->hasField('field_enlace') && !$entity->get('field_enlace')->isEmpty()) {
      $uri = trim((string) $entity->get('field_enlace')->uri);
      // Una página del sitio (internal:/ruta o entity:node/1) o https; nada de
      // http, javascript:, data: ni rutas sin barra.
      $valido = preg_match('~^https://[^\s/]+~i', $uri)
        || preg_match('~^internal:/(?![/\\\\\s])~', $uri)
        || preg_match('~^entity:node/\d+$~', $uri);
      if (!$valido) {
        $this->context->buildViolation($constraint->mensajeEnlace)
          ->atPath('field_enlace')
          ->addViolation();
      }
    }
  }

}
