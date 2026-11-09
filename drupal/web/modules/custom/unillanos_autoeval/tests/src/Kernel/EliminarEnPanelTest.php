<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Url;
use Drupal\unillanos_autoeval\Hook\UnillanosAutoevalHooks;
use Drupal\unillanos_autoeval\Service\PertenenciaDeContenido;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Prueba «Eliminar» dentro del panel lateral: enlace, confirmación y bloqueo.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class EliminarEnPanelTest extends EliminarKernelBase {

  /**
   * Construye el formulario de un contenido como lo pide el panel lateral.
   *
   * @param object $entidad
   *   El contenido.
   * @param string $operacion
   *   Operación: `edit` o `delete`.
   * @param string $destino
   *   Valor de `?destination=`.
   *
   * @return array<string, mixed>
   *   El formulario ya alterado.
   */
  protected function formulario(object $entidad, string $operacion, string $destino = '/programas/x'): array {
    $uri = '/node/' . $entidad->id() . '/' . $operacion . '?destination=' . $destino . '&_wrapper_format=drupal_dialog.off_canvas';
    $peticion = Request::create($uri);
    $peticion->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($peticion);
    $this->reiniciarAcceso();
    $form = $this->container->get('entity.form_builder')->getForm($entidad, $operacion);
    $this->container->get('request_stack')->pop();
    return $form;
  }

  /**
   * Reinicia el acceso guardado en memoria (se guarda por petición).
   */
  protected function reiniciarAcceso(): void {
    foreach (['node', 'media'] as $tipo) {
      $this->container->get('entity_type.manager')->getAccessControlHandler($tipo)->resetCache();
    }
  }

  /**
   * Texto de un arreglo de render, sin etiquetas.
   *
   * @param array<string, mixed> $elemento
   *   El elemento.
   */
  protected function texto(array $elemento): string {
    $html = (string) $this->container->get('renderer')->renderInIsolation($elemento);
    return trim((string) preg_replace('~\s+~', ' ', strip_tags($html)));
  }

  /**
   * El servicio describe qué es un contenido y a qué pertenece.
   */
  public function testDescribeLaPertenencia(): void {
    $yo = (int) $this->acreditacion->id();
    $programa = $this->nodoDe('programa', $yo, ['title' => 'Electrónica demo']);
    $proceso = $this->nodoDe('proceso', $yo, ['title' => 'Proceso 2025', 'field_programa' => $programa->id()]);
    $plan = $this->nodoDe('plan_mejoramiento', $yo, ['title' => 'Plan', 'field_proceso' => $proceso->id()]);
    $meta = $this->nodoDe('meta', $yo, ['title' => 'Meta 3', 'field_plan' => $plan->id()]);
    $seguimiento = $this->nodoDe('seguimiento', $yo, ['title' => 'M03 · 2025-1', 'field_meta' => $meta->id()]);
    $servicio = $this->container->get(PertenenciaDeContenido::class);
    $datos = $servicio->describir($seguimiento);
    $this->assertSame('M03 · 2025-1', $datos['titulo']);
    $this->assertSame('Electrónica demo', $datos['programa']);
    $this->assertSame('Proceso 2025', $datos['proceso']);
    $this->assertSame('seguimiento', strtolower($datos['tipo']));
    $this->assertSame('Electrónica demo', $servicio->describir($plan)['programa']);
    $this->assertSame('Proceso 2025', $servicio->describir($proceso)['proceso']);
    $this->assertSame('', $servicio->describir($programa)['proceso']);
    // Un programa no pertenece a sí mismo.
    $this->assertSame('', $servicio->describir($programa)['programa']);
    // Sin padre conocido, la pertenencia queda vacía.
    $suelto = $this->nodoDe('hallazgo', $yo, ['title' => 'Suelto']);
    $vacios = array_intersect_key($servicio->describir($suelto), ['programa' => 1, 'proceso' => 1]);
    $this->assertSame(['programa' => '', 'proceso' => ''], $vacios);
  }

  /**
   * El enlace «Eliminar» del formulario de edición se abre en el panel.
   */
  public function testElEnlaceEliminarSeAbreEnElPanel(): void {
    $hallazgo = $this->nodoDe('hallazgo', (int) $this->acreditacion->id(), ['title' => 'Fortaleza']);
    $form = $this->formulario($hallazgo, 'edit');
    $eliminar = $form['actions']['delete'];
    $this->assertTrue($eliminar['#access']);
    $this->assertSame('Eliminar', (string) $eliminar['#title']);
    $this->assertSame('dialog', $eliminar['#attributes']['data-dialog-type']);
    $this->assertSame('off_canvas', $eliminar['#attributes']['data-dialog-renderer']);
    $this->assertContains('use-ajax', $eliminar['#attributes']['class']);
    $this->assertArrayNotHasKey('ua_bloqueo', $form);
    // Fuera del panel, el formulario conserva el diálogo modal de núcleo.
    $normal_peticion = Request::create('/node/' . $hallazgo->id() . '/edit?destination=/programas/x');
    $normal_peticion->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($normal_peticion);
    $normal = $this->container->get('entity.form_builder')->getForm($hallazgo, 'edit');
    $this->container->get('request_stack')->pop();
    $this->assertSame('modal', $normal['actions']['delete']['#attributes']['data-dialog-type']);
  }

  /**
   * Si se borra la página actual, se vuelve al listado de programas.
   */
  public function testBorrarLaPaginaActualVuelveAlListado(): void {
    $programa = $this->nodoDe('programa', (int) $this->acreditacion->id(), ['title' => 'Programa demo']);
    $propia = '/node/' . $programa->id();
    $form = $this->formulario($programa, 'edit', $propia);
    $consulta = $form['actions']['delete']['#url']->getOption('query');
    $this->assertSame(Url::fromRoute('unillanos_autoeval.programas')->toString(), $consulta['destination']);
    // Si estaba en otra página, vuelve a ella.
    $otra = $this->formulario($programa, 'edit', '/programas/otro');
    $this->assertSame('/programas/otro', $otra['actions']['delete']['#url']->getOption('query')['destination']);
  }

  /**
   * La confirmación dice qué se elimina, a qué pertenece y que no se deshace.
   */
  public function testLaConfirmacionEsClara(): void {
    $yo = (int) $this->acreditacion->id();
    $programa = $this->nodoDe('programa', $yo, ['title' => 'Programa demo']);
    $proceso = $this->nodoDe('proceso', $yo, ['title' => 'Proceso demo', 'field_programa' => $programa->id()]);
    $hallazgo = $this->nodoDe('hallazgo', $yo, ['title' => 'Fortaleza A', 'field_proceso' => $proceso->id()]);
    $form = $this->formulario($hallazgo, 'delete');
    $texto = $this->texto($form['description']);
    $this->assertStringContainsStringIgnoringCase('Vas a eliminar el hallazgo «Fortaleza A».', $texto);
    $this->assertStringContainsStringIgnoringCase('Eliminar hallazgo', (string) $form['#title']);
    $this->assertStringContainsString('Pertenece al programa «Programa demo», proceso «Proceso demo».', $texto);
    $this->assertStringContainsString('Esta acción no se puede deshacer.', $texto);
    $this->assertSame('Sí, eliminar', (string) $form['actions']['submit']['#value']);
    $this->assertSame('danger', $form['actions']['submit']['#button_type']);
    $this->assertSame('Cancelar', (string) $form['actions']['cancel']['#title']);
    $this->assertSame('true', $form['actions']['cancel']['#attributes']['data-ua-cerrar-panel']);
    // El botón se envía por AJAX dentro del panel.
    $callback = UnillanosAutoevalHooks::class . '::devolverFormularioAlPanel';
    $this->assertSame($callback, $form['actions']['submit']['#ajax']['callback']);
    // Un proceso sin programa conocido dice solo el proceso.
    $suelto = $this->nodoDe('proceso', $yo, ['title' => 'Proceso suelto']);
    $hijo = $this->nodoDe('hallazgo', $yo, ['title' => 'H', 'field_proceso' => $suelto->id()]);
    $descripcion = $this->texto($this->formulario($hijo, 'delete')['description']);
    $this->assertStringContainsString('Pertenece al proceso «Proceso suelto».', $descripcion);
  }

  /**
   * Bloqueado: no hay botón y se lista lo que cuelga, con enlaces.
   */
  public function testBloqueadoListaLosDependientes(): void {
    $yo = (int) $this->acreditacion->id();
    $proceso = $this->nodoDe('proceso', $yo, ['title' => 'Proceso demo']);
    $this->nodoDe('hallazgo', $yo, ['title' => 'Fortaleza A', 'field_proceso' => $proceso->id()]);
    $this->nodoDe('valoracion', $yo, ['title' => 'Valoración F1', 'field_proceso' => $proceso->id()]);
    $form = $this->formulario($proceso, 'edit');
    $this->assertFalse($form['actions']['delete']['#access']);
    $this->assertArrayHasKey('ua_bloqueo', $form);
    $texto = $this->texto($form['ua_bloqueo']);
    $this->assertStringContainsString('Todavía no se puede eliminar.', $texto);
    $this->assertStringContainsString('Elimina primero:', $texto);
    $this->assertStringContainsString('Fortaleza A', $texto);
    $this->assertStringContainsString('Valoración F1', $texto);
    $enlaces = array_filter($form['ua_bloqueo']['lista']['#items'], fn($i) => ($i['#type'] ?? '') === 'link');
    $this->assertCount(2, $enlaces);
    foreach ($enlaces as $enlace) {
      $this->assertStringContainsString('/edit', $enlace['#url']->toString());
      $this->assertSame('off_canvas', $enlace['#attributes']['data-dialog-renderer']);
    }
    // Lo que no es de esta persona no muestra el aviso (no es suyo).
    $ajeno = $this->nodoDe('proceso', 0, ['title' => 'Migrado']);
    $this->nodoDe('hallazgo', 0, ['title' => 'H migrado', 'field_proceso' => $ajeno->id()]);
    $this->assertArrayNotHasKey('ua_bloqueo', $this->formulario($ajeno, 'edit'));
  }

  /**
   * Un título con código no se interpreta: se muestra como texto.
   */
  public function testElTituloSeEscapa(): void {
    $yo = (int) $this->acreditacion->id();
    $hallazgo = $this->nodoDe('hallazgo', $yo, ['title' => '<script>alert(1)</script>']);
    $descripcion = $this->formulario($hallazgo, 'delete')['description'];
    $html = (string) $this->container->get('renderer')->renderInIsolation($descripcion);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
  }

  /**
   * Con más de diez dependientes se listan diez y se avisa de que hay más.
   */
  public function testBloqueoConMuchosDependientes(): void {
    $yo = (int) $this->acreditacion->id();
    $proceso = $this->nodoDe('proceso', $yo, ['title' => 'Proceso grande']);
    for ($i = 1; $i <= 12; $i++) {
      $this->nodoDe('hallazgo', $yo, ['title' => 'Hallazgo ' . $i, 'field_proceso' => $proceso->id()]);
    }
    $form = $this->formulario($proceso, 'edit');
    $this->assertCount(10, $form['ua_bloqueo']['lista']['#items']);
    $this->assertArrayHasKey('mas', $form['ua_bloqueo']);
  }

  /**
   * Lo migrado (otro autor) no ofrece «Eliminar» ni aviso.
   */
  public function testLoMigradoNoOfreceEliminar(): void {
    $migrado = $this->nodoDe('hallazgo', 0, ['title' => 'Migrado']);
    $form = $this->formulario($migrado, 'edit');
    $this->assertFalse($form['actions']['delete']['#access']);
    $this->assertArrayNotHasKey('ua_bloqueo', $form);
  }

}
