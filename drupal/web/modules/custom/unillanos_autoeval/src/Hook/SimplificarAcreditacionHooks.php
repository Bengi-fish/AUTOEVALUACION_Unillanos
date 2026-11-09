<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Hook;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\unillanos_autoeval\Service\EnlacesEdicion;

/**
 * Menos ruido para Acreditación: pestañas, revisión y campos administrativos.
 *
 * Esto vale solo para quien edita desde el modo edición (permiso «access
 * acreditacion panel») y NO administra el sitio: el administrador conserva las
 * pestañas y los formularios completos. No se borra ningún campo: solo se
 * ocultan en la pantalla de formulario «acreditacion» de cada tipo.
 */
class SimplificarAcreditacionHooks {

  /**
   * Tipos que usan la pantalla de formulario «acreditacion».
   *
   * @var array<string, string[]>
   */
  const TIPOS = [
    'node' => [
      'programa',
      'proceso',
      'valoracion',
      'hallazgo',
      'plan_mejoramiento',
      'meta',
      'seguimiento',
    ],
    'media' => ['documento'],
  ];

  /**
   * Constructor.
   */
  public function __construct(
    protected AccountInterface $usuarioActual,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AdminContext $contextoAdmin,
  ) {}

  /**
   * Indica si la cuenta es de Acreditación (edita sin administrar el sitio).
   */
  protected function esAcreditacion(): bool {
    return $this->usuarioActual->hasPermission(EnlacesEdicion::PERMISO)
      && !$this->usuarioActual->hasPermission('administer nodes')
      && !$this->usuarioActual->hasPermission('bypass node access');
  }

  /**
   * Implements hook_menu_local_tasks_alter().
   *
   * Quita «Ver · Editar · Revisiones» del sitio público para Acreditación: sus
   * lápices ya llevan a cada pantalla de edición.
   *
   * @param array<string, mixed> $data
   *   Pestañas y acciones locales.
   * @param string $route_name
   *   Ruta actual.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Metadatos de caché.
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(array &$data, string $route_name, RefinableCacheableDependencyInterface &$cacheability): void {
    $cacheability->addCacheContexts(['user.permissions', 'route']);
    // Solo las pestañas del contenido (ver, editar, revisiones): las de otras
    // rutas, como la cuenta de la persona, se conservan.
    if (!str_starts_with($route_name, 'entity.node.')
      || !$this->esAcreditacion()
      || $this->contextoAdmin->isAdminRoute()) {
      return;
    }
    $data['tabs'] = [];
  }

  /**
   * Implements hook_entity_form_display_alter().
   *
   * Para Acreditación, el formulario de edición y de creación de los tipos de
   * autoevaluación usa la pantalla «acreditacion» (sin uid, fecha, alias,
   * idioma ni lo que no se edita a mano). Los datos no se tocan: un campo que
   * no está en la pantalla no se modifica al guardar.
   *
   * @param \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null $form_display
   *   La pantalla que se usará (se reemplaza por la simple).
   * @param array<string, mixed> $context
   *   Claves entity_type, bundle y form_mode.
   */
  #[Hook('entity_form_display_alter')]
  public function entityFormDisplayAlter(&$form_display, array $context): void {
    if (($context['form_mode'] ?? '') !== 'default'
      || !in_array($context['bundle'] ?? '', self::TIPOS[$context['entity_type'] ?? ''] ?? [], TRUE)
      || !$this->esAcreditacion()) {
      return;
    }
    $simple = $this->entityTypeManager->getStorage('entity_form_display')
      ->load($context['entity_type'] . '.' . $context['bundle'] . '.acreditacion');
    if ($simple) {
      $form_display = $simple;
    }
  }

  /**
   * Implements hook_form_alter().
   *
   * Oculta «Información de la revisión» a Acreditación. La revisión se sigue
   * creando sola: el campo «Crear nueva revisión» queda marcado y sin mensaje.
   *
   * @param array<mixed> $form
   *   El formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    $objeto = $form_state->getBuildInfo()['callback_object'] ?? NULL;
    if (!$objeto instanceof EntityFormInterface || !$this->esAcreditacion()) {
      return;
    }
    $entidad = $objeto->getEntity();
    if (!in_array($entidad->bundle(), self::TIPOS[$entidad->getEntityTypeId()] ?? [], TRUE)) {
      return;
    }
    if (isset($form['revision'])) {
      $form['revision']['#default_value'] = TRUE;
    }
    foreach (['revision_information', 'revision_log'] as $clave) {
      if (isset($form[$clave])) {
        $form[$clave]['#access'] = FALSE;
      }
    }
  }

}
