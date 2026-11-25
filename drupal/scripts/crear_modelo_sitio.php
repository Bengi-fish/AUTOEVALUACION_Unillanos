<?php

/**
 * @file
 * Crea los tipos de contenido del sitio de Acreditación (Fase 9). Idempotente.
 *
 * Uso (desde la raíz del repositorio):
 *   ddev drush php:script scripts/crear_modelo_sitio.php.
 *
 * Tipos: noticia, pagina_informativa y miembro_equipo. Reutilizan los campos
 * ya existentes `body`, `field_imagen`, `field_enlace` y `field_orden`, y
 * crean `field_cargo`, `field_grupo`, `field_telefono` y `field_correo`.
 * Después: `ddev drush cex -y`.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

$tipos = [
  'noticia' => ['Noticia', 'Noticia del sitio de Acreditación (portal y SIAAI).'],
  'pagina_informativa' => ['Página informativa', 'Página de texto: SAC, esquema organizacional, boletines y similares.'],
  'miembro_equipo' => ['Miembro del equipo', 'Persona del comité o del equipo de trabajo de Acreditación.'],
];
foreach ($tipos as $clave => [$nombre, $descripcion]) {
  if (!NodeType::load($clave)) {
    NodeType::create([
      'type' => $clave,
      'name' => $nombre,
      'description' => $descripcion,
      'new_revision' => TRUE,
      'display_submitted' => FALSE,
      'preview_mode' => 0,
    ])->save();
    print "Tipo $clave creado.\n";
  }
}

// Campos nuevos: almacenamiento.
$almacenamientos = [
  'field_cargo' => ['string', ['max_length' => 255]],
  'field_grupo' => ['string', ['max_length' => 255]],
  'field_telefono' => ['string', ['max_length' => 100]],
  'field_correo' => ['email', []],
];
foreach ($almacenamientos as $campo => [$tipo, $ajustes]) {
  if (!FieldStorageConfig::loadByName('node', $campo)) {
    FieldStorageConfig::create([
      'field_name' => $campo,
      'entity_type' => 'node',
      'type' => $tipo,
      'settings' => $ajustes,
    ])->save();
    print "Almacenamiento $campo creado.\n";
  }
}

// Instancias: [tipo => [campo => [etiqueta, requerido, descripción, ajustes]]].
$campos = [
  'noticia' => [
    'body' => ['Texto', TRUE, '', []],
    'field_imagen' => ['Imagen', FALSE, 'JPG, PNG o WebP, de hasta 5 MB. Si no tiene imagen, déjelo en blanco.', [
      'file_directory' => 'noticias',
      'file_extensions' => 'png jpg jpeg webp',
      'max_filesize' => '5 MB',
      'alt_field' => TRUE,
      'alt_field_required' => TRUE,
    ]],
    'field_enlace' => ['Enlace de origen', FALSE, 'Dirección de la noticia en el sitio de origen (https://).', [
      'link_type' => 17,
      'title' => 0,
    ]],
  ],
  'pagina_informativa' => [
    'body' => ['Texto', TRUE, '', []],
    'field_enlace' => ['Enlace de origen', FALSE, 'Dirección de la página en el sitio de origen (https://).', [
      'link_type' => 17,
      'title' => 0,
    ]],
  ],
  'miembro_equipo' => [
    'field_cargo' => ['Dependencia o cargo', TRUE, 'Por ejemplo: «Vicerrectoría Académica».', []],
    'field_grupo' => ['Grupo', FALSE, 'Por ejemplo: «Comité Autoevaluación y Acreditación Institucional».', []],
    'field_telefono' => ['Teléfono', FALSE, 'Por ejemplo: «PBX: 6616800 Ext. 102».', []],
    'field_correo' => ['Correo electrónico', FALSE, 'Correo institucional.', []],
    'field_orden' => ['Orden', FALSE, 'Número: el menor sale primero.', []],
  ],
];
foreach ($campos as $tipo => $lista) {
  foreach ($lista as $campo => [$etiqueta, $requerido, $ayuda, $ajustes]) {
    if (FieldConfig::loadByName('node', $tipo, $campo)) {
      continue;
    }
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('node', $campo),
      'bundle' => $tipo,
      'label' => $etiqueta,
      'required' => $requerido,
      'description' => $ayuda,
      'settings' => $ajustes,
    ])->save();
    print "Campo $tipo.$campo creado.\n";
  }
}

// Formulario y visualización.
$formulario = [
  'noticia' => ['title' => -5, 'body' => 0, 'field_imagen' => 1, 'field_enlace' => 2, 'status' => 100],
  'pagina_informativa' => ['title' => -5, 'body' => 0, 'field_enlace' => 2, 'status' => 100],
  'miembro_equipo' => [
    'title' => -5, 'field_cargo' => 0, 'field_grupo' => 1, 'field_telefono' => 2,
    'field_correo' => 3, 'field_orden' => 4, 'status' => 100,
  ],
];
$widgets = [
  'title' => ['string_textfield', ['size' => 60, 'placeholder' => '']],
  'body' => ['text_textarea_with_summary', ['rows' => 12, 'summary_rows' => 3, 'placeholder' => '', 'show_summary' => FALSE]],
  'field_imagen' => ['image_image', ['progress_indicator' => 'throbber', 'preview_image_style' => 'thumbnail']],
  'field_enlace' => ['link_default', ['placeholder_url' => '', 'placeholder_title' => '']],
  'field_cargo' => ['string_textfield', ['size' => 60, 'placeholder' => '']],
  'field_grupo' => ['string_textfield', ['size' => 60, 'placeholder' => '']],
  'field_telefono' => ['string_textfield', ['size' => 40, 'placeholder' => '']],
  'field_correo' => ['email_default', ['placeholder' => '', 'size' => 60]],
  'field_orden' => ['number', ['placeholder' => '']],
  'status' => ['boolean_checkbox', ['display_label' => TRUE]],
];
$visualizaciones = [
  'noticia' => ['body' => ['text_default', []], 'field_imagen' => ['image', ['image_link' => '', 'image_style' => '']]],
  'pagina_informativa' => ['body' => ['text_default', []]],
  'miembro_equipo' => [],
];
foreach ($formulario as $tipo => $pesos) {
  $form = EntityFormDisplay::load("node.$tipo.default") ?? EntityFormDisplay::create([
    'targetEntityType' => 'node', 'bundle' => $tipo, 'mode' => 'default', 'status' => TRUE,
  ]);
  foreach ($pesos as $campo => $peso) {
    [$widget, $ajustes] = $widgets[$campo];
    $form->setComponent($campo, ['type' => $widget, 'weight' => $peso, 'region' => 'content', 'settings' => $ajustes]);
  }
  foreach (['created', 'uid', 'promote', 'sticky', 'path'] as $oculto) {
    $form->removeComponent($oculto);
  }
  $form->save();
  $vista = EntityViewDisplay::load("node.$tipo.default") ?? EntityViewDisplay::create([
    'targetEntityType' => 'node', 'bundle' => $tipo, 'mode' => 'default', 'status' => TRUE,
  ]);
  foreach ($vista->getComponents() as $campo => $unused) {
    $vista->removeComponent($campo);
  }
  $peso = 0;
  foreach ($visualizaciones[$tipo] as $campo => [$formateador, $ajustes]) {
    $vista->setComponent($campo, ['type' => $formateador, 'label' => 'hidden', 'weight' => $peso++, 'settings' => $ajustes]);
  }
  $vista->save();
}
print "Listo. Ahora: ddev drush cex -y\n";
