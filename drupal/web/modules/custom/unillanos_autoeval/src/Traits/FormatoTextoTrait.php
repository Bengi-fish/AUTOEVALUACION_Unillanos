<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Traits;

use Drupal\Component\Utility\Html;

/**
 * Formato puro de valores: números, textos y fechas.
 *
 * Funciones sin estado que reciben un escalar (no un campo de Drupal; para eso
 * está EtiquetasYOrdenTrait) y que comparten los servicios que arman las
 * páginas.
 */
trait FormatoTextoTrait {

  /**
   * Da formato numérico con coma decimal (4,27).
   *
   * @param float|int|string|null $valor
   *   Valor guardado.
   * @param int $decimales
   *   Cantidad de decimales.
   *
   * @return string
   *   El número formateado, o una cadena vacía si no es numérico.
   */
  public function numero(float|int|string|NULL $valor, int $decimales = 2): string {
    if ($valor === NULL || !is_numeric($valor)) {
      return '';
    }
    return number_format((float) $valor, $decimales, ',', '');
  }

  /**
   * Devuelve una cadena traducida como texto plano, sin escapar.
   *
   * Los marcadores `@` de t() escapan el HTML, y la plantilla vuelve a
   * escapar al imprimir (un «&» se vería «&amp;amp;»). Aquí se decodifican
   * para que el escape ocurra una sola vez, en la plantilla.
   *
   * @param \Stringable|string $texto
   *   El resultado de $this->t().
   *
   * @return string
   *   El texto plano.
   */
  protected function plano(\Stringable|string $texto): string {
    return Html::decodeEntities((string) $texto);
  }

  /**
   * Evita que un salto de línea parta un periodo como «2024-2 – 2030».
   *
   * Solo cambia la presentación (espacios sin salto y unión de palabras);
   * el título guardado no se modifica. Si el periodo no cabe en una línea,
   * el único corte posible queda antes del guion largo («2024-2» y
   * «– 2030»), nunca en medio de un año.
   *
   * @param string $titulo
   *   El título tal como está guardado.
   *
   * @return string
   *   El título con el periodo indivisible.
   */
  public function sinCortesEnPeriodo(string $titulo): string {
    return preg_replace_callback(
      '/\d{4}-\d(?:\s+[–-]\s+\d{4}(?:-\d)?)?/u',
      static fn(array $m): string => preg_replace(
        ['/\s+([–-])\s+/u', '/(?<=\d)-(?=\d)/'],
        [" $1\u{2060}\u{00A0}", "-\u{2060}"],
        $m[0],
      ),
      $titulo,
    ) ?? $titulo;
  }

  /**
   * Escribe una fecha (AAAA-MM-DD) como «diciembre de 2029».
   *
   * @param string|null $fecha
   *   Fecha guardada.
   *
   * @return string
   *   El mes y el año, o una cadena vacía si no hay fecha válida.
   */
  public function fechaMesAnio(?string $fecha): string {
    if (!$fecha || !preg_match('/^(\d{4})-(\d{2})-\d{2}/', $fecha, $partes)) {
      return '';
    }
    $meses = [
      1 => 'enero',
      2 => 'febrero',
      3 => 'marzo',
      4 => 'abril',
      5 => 'mayo',
      6 => 'junio',
      7 => 'julio',
      8 => 'agosto',
      9 => 'septiembre',
      10 => 'octubre',
      11 => 'noviembre',
      12 => 'diciembre',
    ];
    $mes = $meses[(int) $partes[2]] ?? '';
    return $mes === '' ? '' : $mes . ' de ' . $partes[1];
  }

}
