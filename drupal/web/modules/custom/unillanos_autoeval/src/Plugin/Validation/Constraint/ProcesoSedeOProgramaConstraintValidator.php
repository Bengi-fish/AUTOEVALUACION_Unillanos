<?php

namespace Drupal\unillanos_autoeval\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Valida que el proceso tenga sede o programa, no ambos.
 */
final class ProcesoSedeOProgramaConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $entity, Constraint $constraint): void {
    if (!$constraint instanceof ProcesoSedeOProgramaConstraint) {
      throw new UnexpectedTypeException($constraint, ProcesoSedeOProgramaConstraint::class);
    }
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'proceso') {
      return;
    }
    $tiene_sede = !$entity->get('field_sede')->isEmpty();
    $tiene_programa = !$entity->get('field_programa')->isEmpty();
    if ($tiene_sede && $tiene_programa) {
      $this->context->buildViolation($constraint->mensajeAmbos)
        ->atPath('field_programa')
        ->addViolation();
    }
    elseif (!$tiene_sede && !$tiene_programa) {
      $this->context->buildViolation($constraint->mensajeNinguno)
        ->atPath('field_programa')
        ->addViolation();
    }
  }

}
