<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\unillanos_autoeval\Twig\UnillanosTwigExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Prueba el filtro `sin_cortes`: un rango de años no se parte de línea.
 */
#[Group('unillanos_autoeval')]
class UnillanosTwigExtensionTest extends UnitTestCase {

  /**
   * Quita las uniones y los espacios sin salto para comparar con el original.
   */
  protected function limpio(string $texto): string {
    return str_replace(["\u{2060}", "\u{00A0}"], ['', ' '], $texto);
  }

  /**
   * Los rangos quedan indivisibles y el texto visible no cambia.
   */
  #[DataProvider('rangos')]
  public function testRangoIndivisible(string $original, string $esperado): void {
    $resultado = UnillanosTwigExtension::sinCortes($original);
    $this->assertSame($esperado, $resultado);
    $this->assertSame($original, $this->limpio($resultado));
    // Aplicarlo dos veces da lo mismo.
    $this->assertSame($resultado, UnillanosTwigExtension::sinCortes($resultado));
  }

  /**
   * Casos con rango.
   *
   * @return array<string, array{0: string, 1: string}>
   *   Texto original y resultado esperado.
   */
  public static function rangos(): array {
    return [
      'años con guion largo' => ['Periodo 2018–2022 evaluado', "Periodo 2018–\u{2060}2022 evaluado"],
      'años con guion corto' => ['2018-2022', "2018-\u{2060}2022"],
      'periodo con espacios' => ['FO-GCL-20 · 2024-2 – 2030', "FO-GCL-20 · 2024-\u{2060}2\u{00A0}–\u{2060}\u{00A0}2030"],
      'periodo sin espacios' => ['2024-2–2030', "2024-\u{2060}2–\u{2060}2030"],
      'dos rangos' => ['2018–2022 y 2023–2024', "2018–\u{2060}2022 y 2023–\u{2060}2024"],
    ];
  }

  /**
   * Lo que no es un rango no cambia, y lo que no es texto pasa tal cual.
   */
  public function testNoTocaLoDemas(): void {
    foreach (['', 'Sin años', 'Meta 2a.2b', '2025-2 · 100 %', 'Código 12345-6789', 'Resolución 2020'] as $texto) {
      $this->assertSame($texto, UnillanosTwigExtension::sinCortes($texto), $texto);
    }
    $this->assertNull(UnillanosTwigExtension::sinCortes(NULL));
    $this->assertSame(5, UnillanosTwigExtension::sinCortes(5));
    $marca = new class() implements \Stringable {

      /**
       * {@inheritdoc}
       */
      public function __toString(): string {
        return '2018–2022';
      }

    };
    $this->assertSame($marca, UnillanosTwigExtension::sinCortes($marca));
  }

  /**
   * El filtro se llama `sin_cortes`.
   */
  public function testNombreDelFiltro(): void {
    $filtros = (new UnillanosTwigExtension())->getFilters();
    $this->assertSame(['sin_cortes'], array_map(fn($f) => $f->getName(), $filtros));
  }

}
