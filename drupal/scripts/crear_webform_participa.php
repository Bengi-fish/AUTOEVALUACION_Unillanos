<?php

/**
 * @file
 * Crea el webform `participa` (recomendaciones de la comunidad) y los roles
 * `editor_documentos` y `gestor_participacion`.
 *
 * Uso: ddev drush php:script drupal/scripts/crear_webform_participa.php
 * Después: ddev drush cex -y. Es idempotente: reemplaza lo que ya exista.
 */

use Drupal\user\Entity\Role;
use Drupal\webform\Entity\Webform;

$elementos = [
  'proceso' => [
    '#type' => 'entity_select',
    '#title' => 'Proceso de autoevaluación',
    '#description' => 'Opcional. Elige el proceso al que se refiere tu recomendación.',
    '#target_type' => 'node',
    '#selection_handler' => 'default:node',
    '#selection_settings' => ['target_bundles' => ['proceso' => 'proceso']],
    '#empty_option' => 'Selecciona un proceso',
  ],
  'elemento' => [
    '#type' => 'webform_term_select',
    '#title' => 'Factor o característica relacionado',
    '#description' => 'Opcional.',
    '#vocabulary' => 'elemento_modelo',
    '#breadcrumb' => FALSE,
    '#empty_option' => 'Selecciona un factor',
  ],
  'estamento' => [
    '#type' => 'webform_term_select',
    '#title' => 'Estamento',
    '#vocabulary' => 'estamento',
    '#required' => TRUE,
    '#empty_option' => 'Selecciona una opción',
  ],
  'nombre' => [
    '#type' => 'textfield',
    '#title' => 'Nombre (opcional)',
    '#maxlength' => 120,
    '#autocomplete' => 'name',
  ],
  'texto' => [
    '#type' => 'textarea',
    '#title' => 'Tu recomendación',
    '#required' => TRUE,
    '#maxlength' => 1000,
    '#counter_type' => 'character',
    '#counter_maximum' => 1000,
    '#counter_maximum_message' => 'Quedan %d caracteres.',
  ],
  'consentimiento' => [
    '#type' => 'checkbox',
    '#title' => 'Autorizo el tratamiento de mis datos personales conforme a la política de protección de datos de la Universidad.',
    '#required' => TRUE,
  ],
  'estado_gestion' => [
    '#type' => 'select',
    '#title' => 'Estado de gestión',
    '#options' => [
      'recibida' => 'Recibida',
      'en_revision' => 'En revisión',
      'respondida' => 'Respondida',
      'descartada' => 'Descartada',
    ],
    '#default_value' => 'recibida',
    '#access_create_roles' => [],
    '#access_update_roles' => ['gestor_participacion', 'administrator'],
    '#access_view_roles' => ['gestor_participacion', 'administrator'],
  ],
  'respuesta' => [
    '#type' => 'textarea',
    '#title' => 'Respuesta',
    '#access_create_roles' => [],
    '#access_update_roles' => ['gestor_participacion', 'administrator'],
    '#access_view_roles' => ['gestor_participacion', 'administrator'],
  ],
  'actions' => [
    '#type' => 'webform_actions',
    '#submit__label' => 'Enviar recomendación',
    '#submit__attributes' => ['class' => ['ua-btn', 'ua-btn--primario']],
  ],
];

if ($anterior = Webform::load('participa')) {
  $anterior->delete();
}
$webform = Webform::create([
  'id' => 'participa',
  'title' => 'Participa',
  'description' => 'Recomendaciones de la comunidad universitaria para la mejora continua.',
  'langcode' => 'es',
  'status' => 'open',
  'categories' => ['Autoevaluación'],
  'elements' => \Drupal\Component\Serialization\Yaml::encode($elementos),
]);
$webform->setSettings([
  'page' => FALSE,
  'form_novalidate' => FALSE,
  'confirmation_type' => 'inline',
  'confirmation_title' => '',
  'confirmation_message' => 'Gracias. Recibimos tu recomendación; el comité de autoevaluación la revisará.',
  'confirmation_back' => FALSE,
  'limit_user' => 5,
  'limit_user_interval' => 3600,
  'limit_user_message' => 'Enviaste varias recomendaciones seguidas. Inténtalo de nuevo más tarde.',
  'results_disabled' => FALSE,
  'form_disable_remote_addr' => TRUE,
  'submission_log' => FALSE,
]);
$webform->setAccessRules([
  'create' => ['roles' => ['anonymous', 'authenticated'], 'users' => [], 'permissions' => []],
  'view_any' => ['roles' => ['gestor_participacion'], 'users' => [], 'permissions' => []],
  'update_any' => ['roles' => ['gestor_participacion'], 'users' => [], 'permissions' => []],
  'delete_any' => ['roles' => [], 'users' => [], 'permissions' => []],
  'purge_any' => ['roles' => [], 'users' => [], 'permissions' => []],
  'view_own' => ['roles' => [], 'users' => [], 'permissions' => []],
  'update_own' => ['roles' => [], 'users' => [], 'permissions' => []],
  'delete_own' => ['roles' => [], 'users' => [], 'permissions' => []],
  'administer' => ['roles' => [], 'users' => [], 'permissions' => []],
  'test' => ['roles' => [], 'users' => [], 'permissions' => []],
  'configuration' => ['roles' => [], 'users' => [], 'permissions' => []],
]);
$webform->save();

$roles = [
  'editor_documentos' => [
    'label' => 'Editor de documentos',
    'weight' => 5,
    'permissions' => [
      'access media overview',
      'change publication status on acreditacion content',
      'create documento media',
      'delete own documento media',
      'edit any documento media',
      'view all media revisions',
      'view own unpublished media',
      'view the administration theme',
    ],
  ],
  'gestor_participacion' => [
    'label' => 'Gestor de participación',
    'weight' => 6,
    'permissions' => [
      'view the administration theme',
    ],
  ],
];
foreach ($roles as $id => $datos) {
  if ($rol = Role::load($id)) {
    $rol->delete();
  }
  $rol = Role::create(['id' => $id, 'label' => $datos['label'], 'weight' => $datos['weight']]);
  foreach ($datos['permissions'] as $permiso) {
    $rol->grantPermission($permiso);
  }
  $rol->save();
}
echo "Webform participa y roles creados.\n";
