<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Traits;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Etiquetas de campos de lista y orden de textos en español.
 *
 * Funciones sin estado que comparten el presentador y el listado de programas.
 */
trait EtiquetasYOrdenTrait {

  /**
   * Devuelve la etiqueta de un campo de lista (texto) con su valor guardado.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $campo
   *   Campo de tipo lista de texto.
   *
   * @return string
   *   La etiqueta, o una cadena vacía si el campo está vacío.
   */
  public function etiquetaLista(FieldItemListInterface $campo): string {
    $valor = $campo->value;
    if ($valor === NULL || $valor === '') {
      return '';
    }
    $permitidos = $campo->getFieldDefinition()->getFieldStorageDefinition()->getSetting('allowed_values') ?? [];
    return (string) ($permitidos[$valor] ?? $valor);
  }

  /**
   * Compara dos textos en español (con acentos) para ordenarlos.
   *
   * Usa el ordenamiento de `intl` cuando está disponible: con el idioma «C» de
   * PHP, `strcoll` deja «Álgebra» o «Économía» después de «Zoología».
   *
   * @param string $a
   *   Primer texto.
   * @param string $b
   *   Segundo texto.
   *
   * @return int
   *   Negativo, cero o positivo, como `strcmp`.
   */
  protected function compararTextos(string $a, string $b): int {
    if (class_exists('\Collator')) {
      $comparacion = (new \Collator('es'))->compare($a, $b);
      if ($comparacion !== FALSE) {
        return (int) $comparacion;
      }
    }
    return strcmp(mb_strtolower($a), mb_strtolower($b));
  }

}
