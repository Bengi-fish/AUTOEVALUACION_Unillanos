<?php

/**
 * @file
 * Genera las tres ilustraciones abstractas de prueba del carrusel.
 *
 * Formas y colores del sistema de diseño; sin personas, logos ni texto.
 * Salida: datos/semillas/imagenes/prueba-carrusel-N.jpg (1920 x 720 px).
 * Uso (desde la raíz del repositorio): ddev exec php scripts/generar-imagenes-carrusel.php
 */

$destino = __DIR__ . '/../datos/semillas/imagenes';
if (!is_dir($destino)) {
  mkdir($destino, 0775, TRUE);
}
$paleta = [
  'verde' => [23, 67, 73],
  'oscuro' => [10, 34, 38],
  'rojo' => [227, 6, 19],
  'rosa' => [250, 215, 218],
  'gris' => [241, 240, 236],
  'borde' => [211, 208, 199],
];
$ancho = 1920;
$alto = 720;

/**
 * Crea un color en la imagen.
 */
function ua_color($imagen, array $rgb, int $alfa = 0) {
  return imagecolorallocatealpha($imagen, $rgb[0], $rgb[1], $rgb[2], $alfa);
}

$disenos = [
  1 => function ($im) use ($paleta, $ancho, $alto) {
    imagefilledrectangle($im, 0, 0, $ancho, $alto, ua_color($im, $paleta['verde']));
    for ($i = 0; $i < 9; $i++) {
      $r = 420 - $i * 44;
      imagefilledellipse($im, 1500, 330, $r * 2, $r * 2, ua_color($im, $i % 2 ? $paleta['oscuro'] : $paleta['verde']));
    }
    imagefilledellipse($im, 1500, 330, 360, 360, ua_color($im, $paleta['rojo']));
    imagefilledrectangle($im, 0, 600, 900, 720, ua_color($im, $paleta['oscuro']));
    imagefilledrectangle($im, 120, 150, 560, 168, ua_color($im, $paleta['rosa']));
  },
  2 => function ($im) use ($paleta, $ancho, $alto) {
    imagefilledrectangle($im, 0, 0, $ancho, $alto, ua_color($im, $paleta['gris']));
    $colores = [$paleta['verde'], $paleta['rojo'], $paleta['oscuro'], $paleta['borde'], $paleta['rosa']];
    for ($i = 0; $i < 14; $i++) {
      $x = 120 + $i * 125;
      $h = 140 + (($i * 97) % 380);
      imagefilledrectangle($im, $x, $alto - $h, $x + 90, $alto, ua_color($im, $colores[$i % 5]));
    }
    imagefilledrectangle($im, 0, 0, $ancho, 70, ua_color($im, $paleta['oscuro']));
  },
  3 => function ($im) use ($paleta, $ancho, $alto) {
    imagefilledrectangle($im, 0, 0, $ancho, $alto, ua_color($im, $paleta['oscuro']));
    for ($i = 0; $i < 18; $i++) {
      $puntos = [
        $i * 120 - 100, $alto,
        $i * 120 + 60, 120 + ($i * 41) % 260,
        $i * 120 + 220, $alto,
      ];
      imagefilledpolygon($im, $puntos, ua_color($im, $i % 3 === 0 ? $paleta['verde'] : ($i % 3 === 1 ? $paleta['borde'] : $paleta['rojo']), 40));
    }
    imagefilledrectangle($im, 0, 640, $ancho, $alto, ua_color($im, $paleta['verde']));
  },
];

foreach ($disenos as $n => $dibujar) {
  $imagen = imagecreatetruecolor($ancho, $alto);
  imagealphablending($imagen, TRUE);
  $dibujar($imagen);
  $ruta = $destino . '/prueba-carrusel-' . $n . '.jpg';
  imagejpeg($imagen, $ruta, 82);
  imagedestroy($imagen);
  echo $ruta . ' ' . filesize($ruta) . " bytes\n";
}
