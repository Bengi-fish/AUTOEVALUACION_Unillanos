<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Filtros de Twig del micrositio.
 *
 * `sin_cortes` evita que un salto de línea parta un rango de años («2018–2022»,
 * «2024-2 – 2030»). Solo cambia la presentación: añade uniones de palabras y
 * espacios sin salto; el texto guardado no se modifica.
 */
final class UnillanosTwigExtension extends AbstractExtension {

  /**
   * Rango de años o periodos: AAAA o AAAA-N, un guion y AAAA o AAAA-N.
   *
   * Admite los marcadores (U+2060 y espacio sin salto) que ya haya puesto
   * FormatoTextoTrait::sinCortesEnPeriodo().
   */
  const RANGO = '/(?<![\d-])(\d{4}(?:-\x{2060}?\d)?)([\s\x{00A0}\x{2060}]*)([\x{2013}\x{2014}-])([\s\x{00A0}\x{2060}]*)(\d{4}(?:-\x{2060}?\d)?)(?![\d])/u';

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    return [
      new TwigFilter('sin_cortes', [self::class, 'sinCortes']),
    ];
  }

  /**
   * Hace indivisibles los rangos de años de un texto.
   *
   * @param mixed $texto
   *   El texto. Si no es una cadena (un objeto de marcado, un número o NULL),
   *   se devuelve igual.
   *
   * @return mixed
   *   El texto con los rangos unidos, o el valor recibido si no es una cadena.
   */
  public static function sinCortes(mixed $texto): mixed {
    if (!is_string($texto) || $texto === '') {
      return $texto;
    }
    return preg_replace_callback(self::RANGO, static function (array $m): string {
      $unir = static fn(string $anio): string => (string) preg_replace('/-\x{2060}?(?=\d)/u', "-\u{2060}", $anio);
      // Solo cuentan los espacios (no la unión de palabras), para que aplicar
      // el filtro dos veces dé el mismo resultado.
      $con_espacios = preg_match('/[\s\x{00A0}]/u', $m[2] . $m[4]) === 1;
      $guion = $m[3] . "\u{2060}";
      $medio = $con_espacios ? "\u{00A0}" . $guion . "\u{00A0}" : $guion;
      return $unir($m[1]) . $medio . $unir($m[5]);
    }, $texto) ?? $texto;
  }

}
