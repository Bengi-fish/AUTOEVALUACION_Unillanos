<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Prueba el logo de la Universidad del pie: solo la marca, sin recuadro.
 *
 * El archivo original traía un fondo gris oscuro opaco que se veía como un
 * recuadro sobre el pie negro. La versión del tema es un PNG con fondo
 * transparente: la marca blanca conserva su color y sus proporciones.
 */
#[Group('unillanos_autoeval')]
class LogoUniversidadTest extends UnitTestCase {

  /**
   * Ruta del logo del tema.
   */
  protected function ruta(): string {
    return $this->root . '/themes/custom/unillanos/images/logos/unillanos-blanco.png';
  }

  /**
   * Conserva las proporciones del original (555 × 99) y es PNG con alfa.
   */
  public function testProporcionesMasCanalAlfa(): void {
    $this->assertFileExists($this->ruta());
    $info = getimagesize($this->ruta());
    $this->assertSame([555, 99], [$info[0], $info[1]]);
    $this->assertSame('image/png', $info['mime']);
    // Tipo de color 6 del PNG: RGB con canal alfa.
    $this->assertSame(6, ord((string) file_get_contents($this->ruta(), FALSE, NULL, 25, 1)));
  }

  /**
   * El fondo es transparente y la marca sigue siendo blanca.
   */
  public function testSinRecuadroDeFondo(): void {
    $imagen = imagecreatefrompng($this->ruta());
    $ancho = imagesx($imagen);
    $alto = imagesy($imagen);
    // Las esquinas y el borde: totalmente transparentes (127 en el alfa de GD).
    $puntos = [[0, 0], [$ancho - 1, 0], [0, $alto - 1], [$ancho - 1, $alto - 1], [5, 5], [(int) ($ancho / 2), 2]];
    foreach ($puntos as [$x, $y]) {
      $this->assertSame(127, (imagecolorat($imagen, $x, $y) >> 24) & 127, "($x,$y)");
    }
    $transparentes = 0;
    $total = $ancho * $alto;
    for ($y = 0; $y < $alto; $y++) {
      for ($x = 0; $x < $ancho; $x++) {
        $color = imagecolorat($imagen, $x, $y);
        $alfa = ($color >> 24) & 127;
        if ($alfa === 127) {
          $transparentes++;
          continue;
        }
        // Lo visible es blanco: la marca no cambia de color.
        $this->assertSame([255, 255, 255], [($color >> 16) & 255, ($color >> 8) & 255, $color & 255]);
      }
    }
    // El fondo opaco ocupaba cerca del 73 % del archivo.
    $this->assertGreaterThan(0.6, $transparentes / $total);
    $this->assertLessThan(0.9, $transparentes / $total);
  }

}
