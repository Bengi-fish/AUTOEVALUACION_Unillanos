<?php

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Calcula el grado de cumplimiento a partir de una valoración numérica.
 *
 * Usa los rangos del vocabulario `grado_cumplimiento` (Tabla 3.1 del
 * informe). Esos rangos dejan huecos (4,7–4,8 y 3,9–4,0), por lo que la
 * valoración se redondea a un decimal antes de compararla.
 */
class CalculadorGrado {

  /**
   * Nombre de máquina del vocabulario de grados.
   */
  const VOCABULARIO = 'grado_cumplimiento';

  /**
   * Términos de grado ordenados por peso, cargados una sola vez.
   *
   * @var \Drupal\taxonomy\TermInterface[]|null
   */
  protected ?array $grados = NULL;

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Devuelve el término de grado que corresponde a una valoración.
   *
   * @param float|int|string|null $valoracion
   *   Valoración entre 1,00 y 5,00. NULL o vacío no tiene grado.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   El término de grado, o NULL si no hay valoración o queda fuera de
   *   @todo s los rangos.
   */
  public function calcular(float|int|string|NULL $valoracion): ?TermInterface {
    if ($valoracion === NULL || $valoracion === '') {
      return NULL;
    }
    $valor = round((float) $valoracion, 1);
    foreach ($this->cargarGrados() as $grado) {
      $minimo = round((float) $grado->get('field_valoracion_min')->value, 1);
      $maximo = round((float) $grado->get('field_valoracion_max')->value, 1);
      if ($valor >= $minimo && $valor <= $maximo) {
        return $grado;
      }
    }
    return NULL;
  }

  /**
   * Carga los términos de grado ordenados por peso.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Los términos del vocabulario.
   */
  protected function cargarGrados(): array {
    if ($this->grados === NULL) {
      $this->grados = array_values($this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties(['vid' => self::VOCABULARIO]));
      usort($this->grados, fn(TermInterface $a, TermInterface $b): int => $a->getWeight() <=> $b->getWeight());
    }
    return $this->grados;
  }

}
