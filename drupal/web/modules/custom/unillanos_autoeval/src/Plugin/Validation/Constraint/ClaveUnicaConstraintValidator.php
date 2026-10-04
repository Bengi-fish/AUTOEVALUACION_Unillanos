<?php

namespace Drupal\unillanos_autoeval\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Valida que `field_clave` no se repita dentro de su tipo.
 */
final class ClaveUnicaConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

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
    if (!$constraint instanceof ClaveUnicaConstraint) {
      throw new UnexpectedTypeException($constraint, ClaveUnicaConstraint::class);
    }
    $tipo = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $aplica = ($tipo === 'node' && $bundle === 'programa')
      || ($tipo === 'taxonomy_term' && $bundle === 'elemento_modelo');
    if (!$aplica || $entity->get('field_clave')->isEmpty()) {
      return;
    }
    $clave = $entity->get('field_clave')->value;
    $storage = $this->entityTypeManager->getStorage($tipo);
    $definicion = $storage->getEntityType();
    $consulta = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($definicion->getKey('bundle'), $bundle)
      ->condition('field_clave', $clave)
      ->range(0, 1);
    if (!$entity->isNew()) {
      $consulta->condition($definicion->getKey('id'), $entity->id(), '<>');
    }
    if ($consulta->execute()) {
      $this->context->buildViolation($constraint->message, ['%clave' => $clave])
        ->atPath('field_clave')
        ->addViolation();
    }
  }

}
