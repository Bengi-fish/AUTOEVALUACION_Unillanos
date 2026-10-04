<?php

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Sugiere el destino de un hallazgo según la valoración de su elemento.
 *
 * Regla del informe: valoración menor que 4 va al plan de mejoramiento;
 * valoración de 4 va al plan de acción del programa; mayor que 4 no va a
 * ningún plan (`ninguno`). La sugerencia es editable.
 */
class SugerenciaDestino {

  /**
   * Máximo de niveles que se suben por la jerarquía del elemento.
   */
  const NIVELES_MAXIMOS = 10;

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Devuelve el destino sugerido para un hallazgo.
   *
   * Las valoraciones solo existen para factor y característica; si el
   * elemento del hallazgo es un aspecto, se usa la de su ancestro más
   * cercano que tenga valoración en el proceso.
   *
   * @param int|string|null $proceso_id
   *   ID del proceso del hallazgo.
   * @param int|string|null $elemento_id
   *   ID del término `elemento_modelo` del hallazgo.
   *
   * @return string|null
   *   `plan_mejoramiento`, `plan_accion`, `ninguno` o NULL si no hay
   *   valoración de la cual partir.
   */
  public function sugerir(int|string|NULL $proceso_id, int|string|NULL $elemento_id): ?string {
    if (!$proceso_id || !$elemento_id) {
      return NULL;
    }
    $valoracion = $this->buscarValoracion($proceso_id, $elemento_id);
    if ($valoracion === NULL) {
      return NULL;
    }
    if ($valoracion < 4) {
      return 'plan_mejoramiento';
    }
    return abs($valoracion - 4) < 0.00001 ? 'plan_accion' : 'ninguno';
  }

  /**
   * Busca la valoración del elemento o, si no tiene, la de sus ancestros.
   *
   * @return float|null
   *   La valoración, o NULL si ningún nivel la tiene.
   */
  protected function buscarValoracion(int|string $proceso_id, int|string $elemento_id): ?float {
    $nodos = $this->entityTypeManager->getStorage('node');
    $terminos = $this->entityTypeManager->getStorage('taxonomy_term');
    $actual = $elemento_id;
    for ($nivel = 0; $nivel < self::NIVELES_MAXIMOS && $actual; $nivel++) {
      $ids = $nodos->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'valoracion')
        ->condition('field_proceso', $proceso_id)
        ->condition('field_elemento', $actual)
        ->range(0, 1)
        ->execute();
      if ($ids) {
        $valor = $nodos->load(reset($ids))->get('field_valoracion')->value;
        return $valor === NULL ? NULL : (float) $valor;
      }
      $padres = $terminos->loadParents($actual);
      $actual = $padres ? (string) array_key_first($padres) : NULL;
    }
    return NULL;
  }

}
