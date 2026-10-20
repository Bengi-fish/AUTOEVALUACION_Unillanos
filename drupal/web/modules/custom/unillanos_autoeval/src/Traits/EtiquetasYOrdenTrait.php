<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Traits;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Lectura de campos de Drupal y orden de textos en español.
 *
 * Funciones sin estado que reciben un campo o un ítem de campo (o comparan
 * textos) y que comparten el presentador y el listado de programas. El formato
 * de escalares está en FormatoTextoTrait.
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

  /**
   * Devuelve el texto de un campo de texto largo como texto plano.
   *
   * Un campo `plain_text` (o de texto corto, sin formato) ya es texto: solo
   * se recortan los espacios (así un «puntaje < 4 y > 3» no pierde lo que
   * queda entre los signos). Con otro
   * formato se quitan las etiquetas HTML y se decodifican las entidades. En
   * ambos casos la plantilla imprime escapado.
   *
   * @param \Drupal\Core\Field\FieldItemInterface|null $item
   *   Primer valor del campo, o NULL si está vacío.
   *
   * @return string
   *   El texto, o una cadena vacía.
   */
  protected function textoDeCampo(?FieldItemInterface $item): string {
    if (!$item) {
      return '';
    }
    $texto = (string) $item->get('value')->getValue();
    // Un campo de texto corto no tiene formato: es texto plano.
    $formato = isset($item->getProperties()['format']) ? (string) $item->get('format')->getValue() : 'plain_text';
    if ($formato === 'plain_text') {
      return trim($texto);
    }
    return trim(Html::decodeEntities(strip_tags($texto)));
  }

}
