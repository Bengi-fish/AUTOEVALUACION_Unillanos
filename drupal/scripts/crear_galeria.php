<?php

/**
 * @file
 * Crea el campo `field_galeria` (imágenes del carrusel propio). Idempotente.
 *
 * Uso (desde la raíz del repositorio):
 *   ddev drush php:script drupal/scripts/crear_galeria.php.
 *
 * Campo de imagen de valor múltiple (hasta 10, con `alt` obligatorio y leyenda
 * opcional) que se arrastra para ordenar en el formulario del nodo. Se crea en
 * los tipos listados en $bundles (`programa`: carrusel de la página del
 * programa; `proceso`: carrusel de la página «Institucional», que lee el proceso
 * institucional que muestra); cada nodo guarda sus propias imágenes: no se
 * comparten con la portada ni con otros nodos. Después: `ddev drush cex -y`.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$bundles = ['programa', 'proceso'];
$aviso = [
  'proceso' => ' Solo se usa en el proceso institucional (el que no tiene programa): sus imágenes forman el carrusel de la página «Institucional». En los procesos de un programa no se usa; las del programa van en la ficha del programa.',
];
// Peso del campo en el formulario de cada modo (después de la foto).
$formularios = ['default' => 14, 'acreditacion' => 23];
// Pesos propios de cada tipo en su formulario por defecto.
$pesos_por_bundle = ['proceso' => ['default' => 30]];

if (!FieldStorageConfig::loadByName('node', 'field_galeria')) {
  FieldStorageConfig::create([
    'field_name' => 'field_galeria',
    'entity_type' => 'node',
    'type' => 'image',
    'cardinality' => 10,
    'settings' => ['uri_scheme' => 'public', 'default_image' => ['uuid' => NULL, 'alt' => '', 'title' => '', 'width' => NULL, 'height' => NULL]],
  ])->save();
  print "Almacenamiento field_galeria creado.\n";
}

foreach ($bundles as $bundle) {
  if (!FieldConfig::loadByName('node', $bundle, 'field_galeria')) {
    FieldConfig::create([
      'field_name' => 'field_galeria',
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => 'Galería de imágenes',
      'description' => 'Imágenes del carrusel de esta página (hasta 10). Se ordenan arrastrando. JPG, PNG o WebP de hasta 5 MB y al menos 1280 píxeles de ancho; se muestran con proporción 16:6 (se recorta al centro). Escriba siempre el texto alternativo; la leyenda es opcional. Sin imágenes, no se muestra el carrusel.' . ($aviso[$bundle] ?? ''),
      'required' => FALSE,
      'settings' => [
        'file_directory' => 'galeria/' . $bundle,
        'file_extensions' => 'png jpg jpeg webp',
        'max_filesize' => '5 MB',
        'max_resolution' => '',
        'min_resolution' => '1280x1',
        'alt_field' => TRUE,
        'alt_field_required' => TRUE,
        'title_field' => TRUE,
        'title_field_required' => FALSE,
        'default_image' => ['uuid' => NULL, 'alt' => '', 'title' => '', 'width' => NULL, 'height' => NULL],
        'handler' => 'default:file',
        'handler_settings' => [],
      ],
    ])->save();
    print "Campo field_galeria creado en $bundle.\n";
  }
  foreach ($formularios as $modo => $peso) {
    $peso = $pesos_por_bundle[$bundle][$modo] ?? $peso;
    $formulario = EntityFormDisplay::load("node.$bundle.$modo");
    if ($formulario) {
      $formulario->setComponent('field_galeria', [
        'type' => 'image_image',
        'weight' => $peso,
        'region' => 'content',
        'settings' => ['progress_indicator' => 'throbber', 'preview_image_style' => 'thumbnail'],
        'third_party_settings' => [],
      ])->save();
    }
  }
}
print "Listo.\n";
