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
 * Valida `field_estado` contra los valores del tipo de contenido.
 */
final class EstadoPorTipoConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

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
    if (!$constraint instanceof EstadoPorTipoConstraint) {
      throw new UnexpectedTypeException($constraint, EstadoPorTipoConstraint::class);
    }
    if (!$entity instanceof NodeInterface || !isset($constraint::VALORES[$entity->bundle()])) {
      return;
    }
    $estado = $entity->get('field_estado')->value;
    if ($estado !== NULL && !in_array($estado, $constraint::VALORES[$entity->bundle()], TRUE)) {
      $this->context->buildViolation($constraint->message, ['%estado' => $estado])
        ->atPath('field_estado')
        ->addViolation();
    }
  }

}
