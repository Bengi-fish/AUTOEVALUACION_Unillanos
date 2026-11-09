<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Busca qué contenido apunta a otro, para no dejar huérfanos al borrar.
 *
 * Las reglas salen de los campos de referencia reales del modelo: por tipo de
 * contenido (o de medio) se dice quién lo referencia y por qué campo. Se
 * consulta con entity queries, sin SQL a mano, y sin filtrar por acceso: un
 * dependiente que la persona no puede ver también bloquea el borrado.
 */
class EntidadesDependientes {

  /**
   * Quién referencia a cada tipo: tipo de entidad, bundle y campo.
   *
   * @var array<string, array<int, array{0: string, 1: string, 2: string}>>
   */
  const REGLAS = [
    'node:programa' => [['node', 'proceso', 'field_programa']],
    'node:proceso' => [
      ['node', 'valoracion', 'field_proceso'],
      ['node', 'hallazgo', 'field_proceso'],
      ['node', 'plan_mejoramiento', 'field_proceso'],
      ['media', 'documento', 'field_proceso'],
    ],
    'node:hallazgo' => [['node', 'meta', 'field_hallazgos']],
    'node:plan_mejoramiento' => [['node', 'meta', 'field_plan']],
    'node:meta' => [['node', 'seguimiento', 'field_meta']],
    'node:valoracion' => [],
    'node:seguimiento' => [],
    'media:documento' => [['node', 'valoracion', 'field_documento']],
  ];

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $campos,
  ) {}

  /**
   * Indica si la entidad está en el alcance del bloqueo por dependientes.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entidad
   *   La entidad.
   *
   * @return bool
   *   TRUE para los tipos de autoevaluación y el medio `documento`.
   */
  public function enAlcance(EntityInterface $entidad): bool {
    return isset(self::REGLAS[$entidad->getEntityTypeId() . ':' . $entidad->bundle()]);
  }

  /**
   * Etiquetas de caché que invalidan el resultado si cambian los dependientes.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entidad
   *   La entidad.
   *
   * @return string[]
   *   Las listas de los tipos que pueden depender de ella.
   */
  public function etiquetasDeCache(EntityInterface $entidad): array {
    $etiquetas = [];
    foreach (self::REGLAS[$entidad->getEntityTypeId() . ':' . $entidad->bundle()] ?? [] as [$tipo, $bundle]) {
      $etiquetas[] = $tipo . '_list:' . $bundle;
    }
    return $etiquetas;
  }

  /**
   * Devuelve el contenido que apunta a la entidad.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entidad
   *   La entidad que se quiere borrar.
   * @param int $limite
   *   Máximo de dependientes a cargar por regla.
   *
   * @return array<int, array{entidad: \Drupal\Core\Entity\EntityInterface, campo: string}>
   *   Cada dependiente con el campo por el que apunta; vacío si ninguno.
   */
  public function dependientes(EntityInterface $entidad, int $limite = 50): array {
    $dependientes = [];
    foreach (self::REGLAS[$entidad->getEntityTypeId() . ':' . $entidad->bundle()] ?? [] as [$tipo, $bundle, $campo]) {
      if (!isset($this->campos->getFieldStorageDefinitions($tipo)[$campo])) {
        // Un sitio sin ese campo no puede tener dependientes por él.
        continue;
      }
      $almacen = $this->entityTypeManager->getStorage($tipo);
      $clave = $this->entityTypeManager->getDefinition($tipo)->getKey('bundle');
      $ids = $almacen->getQuery()
        ->accessCheck(FALSE)
        ->condition($clave, $bundle)
        ->condition($campo, $entidad->id())
        ->range(0, $limite)
        ->execute();
      foreach ($ids ? $almacen->loadMultiple($ids) : [] as $dependiente) {
        $dependientes[] = ['entidad' => $dependiente, 'campo' => $campo];
      }
    }
    return $dependientes;
  }

  /**
   * Indica si algo apunta a la entidad (sin cargar nada).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entidad
   *   La entidad.
   *
   * @return bool
   *   TRUE si hay al menos un dependiente.
   */
  public function tieneDependientes(EntityInterface $entidad): bool {
    if ($entidad->isNew()) {
      return FALSE;
    }
    foreach (self::REGLAS[$entidad->getEntityTypeId() . ':' . $entidad->bundle()] ?? [] as [$tipo, $bundle, $campo]) {
      if (!isset($this->campos->getFieldStorageDefinitions($tipo)[$campo])) {
        continue;
      }
      $clave = $this->entityTypeManager->getDefinition($tipo)->getKey('bundle');
      $hay = $this->entityTypeManager->getStorage($tipo)->getQuery()
        ->accessCheck(FALSE)
        ->condition($clave, $bundle)
        ->condition($campo, $entidad->id())
        ->range(0, 1)
        ->count()
        ->execute();
      if ($hay) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
