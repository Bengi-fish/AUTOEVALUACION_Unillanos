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
 * Valida que no se repita la pareja (proceso, elemento).
 */
final class ValoracionUnicaConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

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
    if (!$constraint instanceof ValoracionUnicaConstraint) {
      throw new UnexpectedTypeException($constraint, ValoracionUnicaConstraint::class);
    }
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'valoracion') {
      return;
    }
    $proceso = $entity->get('field_proceso')->target_id;
    $elemento = $entity->get('field_elemento')->target_id;
    if (!$proceso || !$elemento) {
      return;
    }
    $consulta = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'valoracion')
      ->condition('field_proceso', $proceso)
      ->condition('field_elemento', $elemento)
      ->range(0, 1);
    if (!$entity->isNew()) {
      $consulta->condition('nid', $entity->id(), '<>');
    }
    if ($consulta->execute()) {
      $this->context->buildViolation($constraint->message)
        ->atPath('field_elemento')
        ->addViolation();
    }
  }

}
