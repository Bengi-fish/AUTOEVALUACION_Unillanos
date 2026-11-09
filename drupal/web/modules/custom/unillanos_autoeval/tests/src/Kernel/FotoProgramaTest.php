<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\unillanos_autoeval\Hook\UnillanosAutoevalHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba «Cambiar foto»: modo de formulario, límites de la imagen y acceso.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class FotoProgramaTest extends EliminarKernelBase {

  /**
   * Lee un archivo de configuración del sitio o del módulo.
   *
   * @return array<string, mixed>
   *   El contenido.
   */
  protected function configuracion(string $nombre, bool $sitio): array {
    $ruta = $sitio
      ? DRUPAL_ROOT . "/../config/sync/$nombre.yml"
      : $this->container->get('extension.list.module')->getPath('unillanos_autoeval') . "/config/optional/$nombre.yml";
    $this->assertFileExists($ruta);
    return Yaml::decode((string) file_get_contents($ruta));
  }

  /**
   * El modo del módulo y el del sitio son el mismo (salvo el uuid).
   */
  public function testModuloSitioCoinciden(): void {
    foreach (['core.entity_form_mode.node.foto', 'core.entity_form_display.node.programa.foto'] as $nombre) {
      $sitio = $this->configuracion($nombre, TRUE);
      $modulo = $this->configuracion($nombre, FALSE);
      unset($sitio['uuid'], $modulo['uuid']);
      $this->assertSame($sitio, $modulo, $nombre);
    }
  }

  /**
   * La pantalla del modo «foto» solo muestra el campo de la foto.
   */
  public function testSoloMuestraFoto(): void {
    $pantalla = $this->configuracion('core.entity_form_display.node.programa.foto', TRUE);
    $this->assertSame('foto', $pantalla['mode']);
    $this->assertSame(['field_imagen'], array_keys($pantalla['content']));
    $this->assertSame('image_image', $pantalla['content']['field_imagen']['type']);
    $this->assertArrayHasKey('title', $pantalla['hidden']);
    $this->assertArrayHasKey('status', $pantalla['hidden']);
  }

  /**
   * La imagen exige descripción y limita formato, peso y ancho.
   */
  public function testLimitesImagen(): void {
    $campo = $this->configuracion('field.field.node.programa.field_imagen', TRUE);
    $ajustes = $campo['settings'];
    $this->assertTrue($ajustes['alt_field']);
    $this->assertTrue($ajustes['alt_field_required']);
    $this->assertSame('png jpg jpeg webp', $ajustes['file_extensions']);
    $this->assertSame('5 MB', $ajustes['max_filesize']);
    $this->assertSame('1280x1', $ajustes['min_resolution']);
    $this->assertSame('', $ajustes['max_resolution']);
  }

  /**
   * Solo quien puede editar el programa entra al formulario de foto.
   */
  public function testAccesoRuta(): void {
    $yo = (int) $this->acreditacion->id();
    $programa = $this->nodoDe('programa', $yo);
    $ruta = $this->container->get('router.route_provider')->getRouteByName('unillanos_autoeval.acreditacion_foto');
    $this->assertSame('/admin/acreditacion/programa/{node}/foto', $ruta->getPath());
    $this->assertSame('node.update', $ruta->getRequirement('_entity_access'));
    $gestor = $this->container->get('access_manager');
    $parametros = ['node' => $programa->id()];
    $this->assertTrue($gestor->checkNamedRoute('unillanos_autoeval.acreditacion_foto', $parametros, $this->acreditacion));
    $this->assertFalse($gestor->checkNamedRoute('unillanos_autoeval.acreditacion_foto', $parametros, new AnonymousUserSession()));
    $this->assertFalse($gestor->checkNamedRoute('unillanos_autoeval.acreditacion_foto', $parametros, $this->usuario(['authenticated'])));
  }

  /**
   * El botón dice «Cambiar foto» con foto y «Añadir foto» sin ella.
   */
  public function testBotonSegunFoto(): void {
    $programa = $this->nodoDe('programa', (int) $this->acreditacion->id());
    $enlaces = $this->container->get('unillanos_autoeval.enlaces_edicion');
    $con = $enlaces->foto((int) $programa->id(), TRUE);
    $sin = $enlaces->foto((int) $programa->id(), FALSE);
    $this->assertSame('Cambiar foto', $con['title'] ?? NULL);
    $this->assertSame('Añadir foto', $sin['title'] ?? NULL);
    $this->assertStringContainsString('/admin/acreditacion/programa/' . $programa->id() . '/foto', $con['url']);
  }

  /**
   * El modo «foto» tiene clase de formulario y el texto alternativo, su ayuda.
   */
  public function testFormularioAyudaTextoAlterno(): void {
    $tipo = $this->container->get('entity_type.manager')->getDefinition('node');
    $this->assertNotEmpty($tipo->getFormClass('foto'));
    $elemento = UnillanosAutoevalHooks::procesarAyudaDeLaFoto(['alt' => ['#type' => 'textfield']]);
    $this->assertSame('Descripción de la foto', (string) $elemento['alt']['#title']);
    $this->assertStringContainsString('estudiantes en el laboratorio de electrónica', (string) $elemento['alt']['#description']);
    // Sin campo de texto alternativo no se inventa uno.
    $this->assertSame([], UnillanosAutoevalHooks::procesarAyudaDeLaFoto([]));
  }

  /**
   * La foto va en un solo paso: sube sola y un único botón la guarda.
   */
  public function testFotoEnUnSoloPaso(): void {
    $elemento = UnillanosAutoevalHooks::procesarAyudaDeLaFoto([
      'alt' => ['#type' => 'textfield'],
      'upload_button' => ['#type' => 'submit', '#ajax' => ['callback' => 'x']],
      'remove_button' => ['#type' => 'submit', '#value' => 'Remove'],
    ]);
    $this->assertSame('Subiendo…', (string) $elemento['upload_button']['#ajax']['progress']['message']);
    $this->assertSame('throbber', $elemento['upload_button']['#ajax']['progress']['type']);
    $this->assertSame('Quitar foto', (string) $elemento['remove_button']['#value']);
    // El formulario de la foto tiene un único botón de guardar y la hoja que
    // oculta «Subir al servidor» solo con JavaScript.
    /** @var array<string, mixed> $form */
    $form = [
      'field_imagen' => ['widget' => [0 => ['#description' => '']]],
      'actions' => ['submit' => ['#type' => 'submit', '#value' => 'Guardar']],
    ];
    $objeto = $this->container->get('entity_type.manager')
      ->getFormObject('node', 'default')
      ->setEntity($this->nodoDe('programa', (int) $this->acreditacion->id()));
    $estado = new FormState();
    $estado->setFormObject($objeto);
    $this->container->get(UnillanosAutoevalHooks::class)->formAlter($form, $estado, 'node_programa_foto_form');
    $this->assertSame('Guardar foto', (string) $form['actions']['submit']['#value']);
    $this->assertContains('unillanos/panel_foto', $form['#attached']['library']);
    $this->assertStringContainsString('arrástrala', (string) $form['field_imagen']['widget'][0]['#description']['#value']);
    $css = (string) file_get_contents(DRUPAL_ROOT . '/themes/custom/unillanos/css/panel-foto.css');
    $this->assertStringContainsString('html.js', $css);
    $this->assertStringContainsString('[name$="_upload_button"]', $css);
    $this->assertStringContainsString('prefers-reduced-motion', $css);
  }

}
