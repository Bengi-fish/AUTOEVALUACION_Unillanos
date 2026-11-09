<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Dice qué es un contenido y a qué programa o proceso pertenece.
 *
 * Sigue las referencias del modelo hacia arriba: seguimiento → meta → plan →
 * proceso → programa; valoración, hallazgo, plan y documento → proceso →
 * programa. Solo lee y devuelve textos; sirve para explicar, en lenguaje
 * sencillo, qué se va a eliminar.
 */
class PertenenciaDeContenido {

  /**
   * Hacia dónde apunta cada tipo: campo y tipo de entidad destino.
   *
   * @var array<string, array{0: string, 1: string}>
   */
  const PADRES = [
    'node:seguimiento' => ['field_meta', 'node'],
    'node:meta' => ['field_plan', 'node'],
    'node:plan_mejoramiento' => ['field_proceso', 'node'],
    'node:valoracion' => ['field_proceso', 'node'],
    'node:hallazgo' => ['field_proceso', 'node'],
    'media:documento' => ['field_proceso', 'node'],
    'node:proceso' => ['field_programa', 'node'],
  ];

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $infoDeTipos,
  ) {}

  /**
   * Nombre legible del tipo de una entidad («Plan de mejoramiento»).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entidad
   *   La entidad.
   *
   * @return string
   *   La etiqueta del tipo de contenido o de medio.
   */
  public function tipo(EntityInterface $entidad): string {
    $info = $this->infoDeTipos->getBundleInfo($entidad->getEntityTypeId());
    return (string) ($info[$entidad->bundle()]['label'] ?? $entidad->bundle());
  }

  /**
   * Describe una entidad y su pertenencia.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entidad
   *   La entidad.
   *
   * @return array{tipo: string, titulo: string, programa: string, proceso: string}
   *   Tipo, título y, si se encuentran, el título del programa y del proceso a
   *   los que pertenece (cadena vacía si no aplica).
   */
  public function describir(EntityInterface $entidad): array {
    $resultado = [
      'tipo' => $this->tipo($entidad),
      'titulo' => trim((string) $entidad->label()),
      'programa' => '',
      'proceso' => '',
    ];
    $actual = $entidad;
    // Máximo cuatro saltos (seguimiento → meta → plan → proceso → programa).
    for ($i = 0; $i < 5 && $actual instanceof FieldableEntityInterface; $i++) {
      $clave = $actual->getEntityTypeId() . ':' . $actual->bundle();
      if ($clave === 'node:programa') {
        // Un programa no «pertenece» a sí mismo.
        if ($i > 0) {
          $resultado['programa'] = trim((string) $actual->label());
        }
        break;
      }
      if ($clave === 'node:proceso') {
        $resultado['proceso'] = trim((string) $actual->label());
      }
      [$campo, $tipo] = self::PADRES[$clave] ?? [NULL, NULL];
      if (!$campo || !$actual->hasField($campo) || $actual->get($campo)->isEmpty()) {
        break;
      }
      $actual = $this->entityTypeManager->getStorage($tipo)->load($actual->get($campo)->target_id);
    }
    return $resultado;
  }

}
