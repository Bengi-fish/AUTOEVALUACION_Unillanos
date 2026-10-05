<?php

namespace Drupal\unillanos_autoeval\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\unillanos_autoeval\Service\ModeloProceso;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Valida el modelo del proceso y que sus elementos sean de ese modelo.
 *
 * - `proceso`: `field_lineamiento` es uno de los modelos permitidos.
 * - `valoracion` y `hallazgo`: `field_elemento` es del modelo del proceso. Un
 *   hallazgo sin elemento es válido (el campo es opcional).
 * - `meta`: los elementos de los hallazgos que atiende son del modelo del
 *   proceso de su plan. La meta no guarda elementos propios.
 */
final class ModeloDelProcesoConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModeloProceso $modeloProceso,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get(ModeloProceso::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $entity, Constraint $constraint): void {
    if (!$constraint instanceof ModeloDelProcesoConstraint) {
      throw new UnexpectedTypeException($constraint, ModeloDelProcesoConstraint::class);
    }
    if (!$entity instanceof NodeInterface) {
      return;
    }
    match ($entity->bundle()) {
      'proceso' => $this->validarProceso($entity, $constraint),
      'valoracion', 'hallazgo' => $this->validarElemento($entity, $constraint),
      'meta' => $this->validarMeta($entity, $constraint),
      default => NULL,
    };
  }

  /**
   * Comprueba que el modelo del proceso sea permitido y compatible.
   */
  protected function validarProceso(NodeInterface $proceso, ModeloDelProcesoConstraint $constraint): void {
    $lineamiento = $proceso->get('field_lineamiento')->target_id;
    // Que el campo esté vacío lo valida el propio campo (obligatorio).
    if (!$lineamiento) {
      return;
    }
    $permitidos = $this->modeloProceso->modelosPermitidos();
    if (!isset($permitidos[(int) $lineamiento])) {
      $this->context->buildViolation($constraint->mensajeModelo)
        ->setParameter('%modelos', implode(' o ', $permitidos))
        ->atPath('field_lineamiento')
        ->addViolation();
      return;
    }
    if ($proceso->isNew()) {
      return;
    }
    $almacen = $this->entityTypeManager->getStorage('node');
    foreach (['valoracion', 'hallazgo'] as $tipo) {
      $ids = $almacen->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $tipo)
        ->condition('field_proceso', $proceso->id())
        ->condition('field_elemento.entity.field_lineamiento', $lineamiento, '<>')
        ->range(0, 1)
        ->execute();
      if ($ids) {
        $this->context->buildViolation($constraint->mensajeCambio)
          ->setParameter('%modelo', $this->modeloProceso->nombre($lineamiento))
          ->setParameter('%tipo', $tipo)
          ->setParameter('%id', (string) reset($ids))
          ->atPath('field_lineamiento')
          ->addViolation();
        return;
      }
    }
  }

  /**
   * Comprueba que el elemento de una valoración o un hallazgo sea del modelo.
   */
  protected function validarElemento(NodeInterface $nodo, ModeloDelProcesoConstraint $constraint): void {
    $elemento_id = $nodo->get('field_elemento')->target_id;
    $proceso_id = $nodo->get('field_proceso')->target_id;
    if (!$elemento_id || !$proceso_id) {
      return;
    }
    $modelo_proceso = $this->modeloProceso->lineamientoDelProceso($proceso_id);
    $modelo_elemento = $this->modeloProceso->lineamientoDelElemento($elemento_id);
    if (!$modelo_proceso || $modelo_proceso === $modelo_elemento) {
      return;
    }
    $this->context->buildViolation($constraint->mensajeElemento)
      ->setParameter('%elemento', $this->etiqueta('taxonomy_term', $elemento_id))
      ->setParameter('%modelo_elemento', $this->modeloProceso->nombre($modelo_elemento))
      ->setParameter('%proceso', $this->etiqueta('node', $proceso_id))
      ->setParameter('%modelo_proceso', $this->modeloProceso->nombre($modelo_proceso))
      ->atPath('field_elemento')
      ->addViolation();
  }

  /**
   * Comprueba que los hallazgos de una meta sean del modelo del proceso.
   */
  protected function validarMeta(NodeInterface $meta, ModeloDelProcesoConstraint $constraint): void {
    $plan = $meta->get('field_plan')->entity;
    $modelo_proceso = $plan instanceof NodeInterface
      ? $this->modeloProceso->lineamientoDelProceso($plan->get('field_proceso')->target_id)
      : NULL;
    if (!$modelo_proceso) {
      return;
    }
    foreach ($meta->get('field_hallazgos')->referencedEntities() as $hallazgo) {
      $elemento_id = $hallazgo->get('field_elemento')->target_id;
      $modelo_elemento = $this->modeloProceso->lineamientoDelElemento($elemento_id);
      if (!$elemento_id || $modelo_elemento === $modelo_proceso) {
        continue;
      }
      $this->context->buildViolation($constraint->mensajeHallazgo)
        ->setParameter('%hallazgo', (string) $hallazgo->label())
        ->setParameter('%elemento', $this->etiqueta('taxonomy_term', $elemento_id))
        ->setParameter('%modelo_elemento', $this->modeloProceso->nombre($modelo_elemento))
        ->setParameter('%modelo_proceso', $this->modeloProceso->nombre($modelo_proceso))
        ->atPath('field_hallazgos')
        ->addViolation();
    }
  }

  /**
   * Devuelve el nombre de una entidad para los mensajes.
   */
  protected function etiqueta(string $tipo_entidad, int|string $id): string {
    $entidad = $this->entityTypeManager->getStorage($tipo_entidad)->load($id);
    return (string) ($entidad?->label() ?? $id);
  }

}
