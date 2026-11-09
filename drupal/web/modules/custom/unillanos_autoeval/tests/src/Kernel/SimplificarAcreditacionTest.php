<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\node\Entity\Node;
use Drupal\unillanos_autoeval\Hook\SimplificarAcreditacionHooks;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Menos ruido para Acreditación: pestañas, revisión y pantallas simples.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class SimplificarAcreditacionTest extends EliminarKernelBase {

  /**
   * Campos que la pantalla simple oculta (están en la completa).
   */
  const OCULTOS = ['uid', 'created', 'path', 'langcode', 'promote', 'sticky', 'menu'];

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
   * Crea una pantalla de formulario de proceso con título y, opcional, autor.
   */
  protected function pantallaDeProceso(string $modo, bool $con_autor): void {
    $pantalla = EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'proceso',
      'mode' => $modo,
      'status' => TRUE,
    ]);
    $pantalla->setComponent('title', ['type' => 'string_textfield', 'weight' => 0]);
    if ($con_autor) {
      $pantalla->setComponent('uid', ['type' => 'entity_reference_autocomplete', 'weight' => 5]);
    }
    else {
      // Como en la configuración real: el autor queda «oculto», no ausente.
      $pantalla->removeComponent('uid');
    }
    $pantalla->save();
  }

  /**
   * Cambia a un usuario con permisos de administrador completo.
   */
  protected function comoAdministrador(): void {
    $permisos = ['bypass node access', 'administer nodes', 'access acreditacion panel'];
    Role::create(['id' => 'admin_total', 'label' => 'Admin', 'permissions' => $permisos])->save();
    $this->setCurrentUser($this->usuario(['admin_total']));
  }

  /**
   * Las pantallas simples: módulo y sitio iguales, sin lo administrativo.
   */
  #[DataProvider('tiposSimples')]
  public function testPantallasSimples(string $tipo): void {
    $nombre = "core.entity_form_display.node.$tipo.acreditacion";
    $sitio = $this->configuracion($nombre, TRUE);
    $modulo = $this->configuracion($nombre, FALSE);
    unset($sitio['uuid']);
    $this->assertEquals($sitio, $modulo, $nombre);
    $completa = $this->configuracion("core.entity_form_display.node.$tipo.default", TRUE);
    $quitados = self::OCULTOS;
    if ($tipo === 'proceso') {
      $quitados[] = 'field_participaciones';
    }
    foreach ($quitados as $campo) {
      $this->assertArrayNotHasKey($campo, $sitio['content'], "$tipo: $campo");
    }
    // No se pierde ningún otro campo: todo lo demás sigue en la pantalla.
    $esperados = array_values(array_diff(array_keys($completa['content']), $quitados));
    $this->assertEqualsCanonicalizing($esperados, array_keys($sitio['content']), $tipo);
    // Lo que sale de la pantalla queda oculto, no borrado.
    foreach (array_intersect($quitados, array_keys($completa['content'])) as $campo) {
      $this->assertTrue($sitio['hidden'][$campo], "$tipo: $campo oculto");
    }
    $this->assertSame('acreditacion', $sitio['mode']);
  }

  /**
   * Tipos de nodo con pantalla simple nueva.
   *
   * @return array<string, array{0: string}>
   *   Un caso por tipo.
   */
  public static function tiposSimples(): array {
    $tipos = ['hallazgo', 'meta', 'plan_mejoramiento', 'proceso', 'seguimiento', 'valoracion'];
    return array_combine($tipos, array_map(fn($t) => [$t], $tipos));
  }

  /**
   * Acreditación usa la pantalla simple; el administrador, la completa.
   */
  public function testPantallaSegunQuienEdita(): void {
    $this->pantallaDeProceso('default', TRUE);
    $this->pantallaDeProceso('acreditacion', FALSE);
    $proceso = $this->nodoDe('proceso', (int) $this->acreditacion->id());
    $this->assertSame('acreditacion', EntityFormDisplay::collectRenderDisplay($proceso, 'default')->getMode());
    $this->assertNull(EntityFormDisplay::collectRenderDisplay($proceso, 'default')->getComponent('uid'));
    $this->comoAdministrador();
    $this->assertSame('default', EntityFormDisplay::collectRenderDisplay($proceso, 'default')->getMode());
    $this->assertNotNull(EntityFormDisplay::collectRenderDisplay($proceso, 'default')->getComponent('uid'));
  }

  /**
   * Guardar con la pantalla simple no pierde los datos que no muestra.
   */
  public function testGuardarNoPierdeDatos(): void {
    $this->pantallaDeProceso('default', TRUE);
    $this->pantallaDeProceso('acreditacion', FALSE);
    $programa = $this->nodoDe('programa', (int) $this->acreditacion->id());
    $proceso = $this->nodoDe('proceso', (int) $this->acreditacion->id(), ['field_programa' => $programa->id()]);
    $estado = new FormState();
    $estado->setValues(['title' => [['value' => 'Título corregido']], 'op' => 'Save']);
    $objeto = $this->container->get('entity_type.manager')
      ->getFormObject('node', 'default')
      ->setEntity($proceso);
    $this->container->get('form_builder')->submitForm($objeto, $estado);
    $this->assertSame([], $estado->getErrors());
    $recargado = Node::load($proceso->id());
    $this->assertSame('Título corregido', $recargado->label());
    $this->assertEquals($programa->id(), $recargado->get('field_programa')->target_id);
    $this->assertEquals($this->acreditacion->id(), $recargado->getOwnerId());
    // Se creó una revisión nueva sin que la persona lo pida.
    $this->assertNotEquals($proceso->getRevisionId(), $recargado->getRevisionId());
  }

  /**
   * Las pestañas locales se quitan a Acreditación, no al administrador.
   */
  public function testPestanasSoloParaElAdministrador(): void {
    /** @var array<string, mixed> $datos */
    $datos = ['tabs' => [0 => ['entity.node.canonical' => ['#link' => 'Ver']]], 'actions' => []];
    $gancho = $this->container->get(SimplificarAcreditacionHooks::class);
    $cache = new CacheableMetadata();
    /** @var array<string, mixed> $propio */
    $propio = $datos;
    $gancho->menuLocalTasksAlter($propio, 'entity.node.canonical', $cache);
    $this->assertSame([], $propio['tabs']);
    $this->assertContains('user.permissions', $cache->getCacheContexts());
    // Las pestañas de otras rutas (la cuenta de la persona) se conservan.
    $cuenta = $datos;
    $cache_cuenta = new CacheableMetadata();
    $gancho->menuLocalTasksAlter($cuenta, 'entity.user.canonical', $cache_cuenta);
    $this->assertSame($datos['tabs'], $cuenta['tabs']);
    $this->comoAdministrador();
    $gancho = new SimplificarAcreditacionHooks(
      $this->container->get('current_user'),
      $this->container->get('entity_type.manager'),
      $this->container->get('router.admin_context'),
    );
    $ajeno = $datos;
    $otro_cache = new CacheableMetadata();
    $gancho->menuLocalTasksAlter($ajeno, 'entity.node.canonical', $otro_cache);
    $this->assertSame($datos['tabs'], $ajeno['tabs']);
  }

  /**
   * La revisión se oculta para Acreditación y se sigue creando sola.
   */
  public function testRevisionOcultaPeroActiva(): void {
    $proceso = $this->nodoDe('proceso', (int) $this->acreditacion->id());
    /** @var array<string, array<string, mixed>> $form */
    $form = [
      'revision' => ['#type' => 'checkbox', '#default_value' => FALSE],
      'revision_information' => ['#type' => 'details'],
      'revision_log' => ['#type' => 'textarea'],
    ];
    $estado = new FormState();
    $objeto = $this->container->get('entity_type.manager')
      ->getFormObject('node', 'default')
      ->setEntity($proceso);
    $estado->setFormObject($objeto);
    $this->container->get(SimplificarAcreditacionHooks::class)->formAlter($form, $estado);
    $this->assertFalse($form['revision_information']['#access']);
    $this->assertFalse($form['revision_log']['#access']);
    $this->assertTrue($form['revision']['#default_value']);
  }

}
