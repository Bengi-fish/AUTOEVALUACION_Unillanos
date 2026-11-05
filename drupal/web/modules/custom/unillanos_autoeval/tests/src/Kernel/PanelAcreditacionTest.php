<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormState;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\unillanos_autoeval\Controller\AcreditacionController;
use Drupal\unillanos_autoeval\Hook\UnillanosAutoevalHooks;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ExceptionInterface;

/**
 * Prueba el panel de Acreditación (/admin/acreditacion).
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class PanelAcreditacionTest extends UnillanosKernelTestBase {

  const RUTA = 'unillanos_autoeval.acreditacion';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'options',
    'node',
    'taxonomy',
    'file',
    'image',
    'media',
    'unillanos_autoeval',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['filter']);
    // El primer usuario (uid 1) tiene todos los permisos: se reserva.
    User::create(['name' => 'superusuario', 'status' => 1])->save();
    $existentes = array_keys($this->container->get('user.permissions')->getPermissions());
    foreach (['anonymous', 'authenticated'] as $id) {
      $real = Yaml::decode((string) file_get_contents(DRUPAL_ROOT . "/../config/sync/user.role.$id.yml"));
      Role::create([
        'id' => $id,
        'label' => $id,
        'weight' => $real['weight'],
        'permissions' => array_values(array_intersect($real['permissions'], $existentes)),
      ])->save();
    }
    $tipo = MediaType::create(['id' => 'documento', 'label' => 'Documento', 'source' => 'file']);
    $tipo->save();
    $campo = $tipo->getSource()->createSourceField($tipo);
    $almacen = $campo->getFieldStorageDefinition();
    if ($almacen instanceof FieldStorageConfig) {
      $almacen->save();
    }
    $campo->save();
    $tipo->set('source_configuration', ['source_field' => $campo->getName()])->save();
    $this->crearCampo('media', 'documento', 'field_proceso', 'entity_reference', ['target_type' => 'node']);
    // Lo que usa el listado de programas.
    Vocabulary::create(['vid' => 'facultad', 'name' => 'facultad'])->save();
    $this->crearCampo('node', 'programa', 'field_facultad', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_sede', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_nivel', 'list_string', [
      'allowed_values' => ['pregrado' => 'Pregrado', 'maestria' => 'Maestría'],
    ]);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
  }

  /**
   * Crea un usuario con un rol propio con los permisos dados.
   *
   * @param string[] $permisos
   *   Permisos del rol.
   */
  protected function usuarioConPermisos(array $permisos): User {
    static $n = 0;
    $id = 'rol_panel_' . ++$n;
    Role::create(['id' => $id, 'label' => $id, 'permissions' => $permisos])->save();
    $usuario = User::create(['name' => 'usuario' . $n, 'status' => 1, 'roles' => [$id]]);
    $usuario->save();
    return $usuario;
  }

  /**
   * Usuario con el rol Acreditación (menos «access navigation»).
   */
  protected function acreditacion(): User {
    $yaml = Yaml::decode((string) file_get_contents(
      $this->container->get('extension.list.module')->getPath('unillanos_autoeval') . '/config/optional/user.role.acreditacion.yml'
    ));
    return $this->usuarioConPermisos(array_values(array_diff($yaml['permissions'], ['access navigation'])));
  }

  /**
   * Crea programas publicados.
   *
   * @return \Drupal\node\Entity\Node[]
   *   Los programas.
   */
  protected function programas(int $cuantos): array {
    $facultad = Term::create(['vid' => 'facultad', 'name' => 'Facultad de Pruebas']);
    $facultad->save();
    $programas = [];
    for ($i = 1; $i <= $cuantos; $i++) {
      $programas[] = $this->crearNodo('programa', [
        'title' => sprintf('Programa %02d', $i),
        'field_clave' => 'programa-' . $i,
        'field_facultad' => $facultad->id(),
        'field_nivel' => 'pregrado',
      ]);
    }
    return $programas;
  }

  /**
   * Pinta el panel para un usuario y devuelve el HTML y el render array.
   *
   * @param \Drupal\user\Entity\User $usuario
   *   Quien mira el panel.
   * @param array<string, mixed> $consulta
   *   Parámetros de la URL.
   *
   * @return array{0: string, 1: array<string, mixed>}
   *   El HTML y el render array sin pintar.
   */
  protected function panel(User $usuario, array $consulta = []): array {
    $this->container->get('current_user')->setAccount($usuario);
    $controlador = AcreditacionController::create($this->container);
    $construccion = $controlador->panel(Request::create('/admin/acreditacion', 'GET', $consulta));
    $copia = $construccion;
    $html = (string) $this->container->get('renderer')->renderInIsolation($copia);
    return [$html, $construccion];
  }

  /**
   * Los hrefs del panel.
   *
   * @return string[]
   *   Las URL de todos los enlaces.
   */
  protected function enlaces(string $html): array {
    preg_match_all('/<a href="([^"]+)"/', $html, $m);
    return array_map('html_entity_decode', $m[1]);
  }

  /**
   * Acceso: el rol sí; anónimo, autenticado sin rol y otros, no.
   */
  public function testAccesoAlPanel(): void {
    $acceso = $this->container->get('access_manager');
    $this->assertTrue($acceso->checkNamedRoute(self::RUTA, [], $this->acreditacion()), 'rol Acreditación');
    $this->assertFalse($acceso->checkNamedRoute(self::RUTA, [], User::getAnonymousUser()), 'anónimo');
    $sin_rol = User::create(['name' => 'sinrol', 'status' => 1]);
    $sin_rol->save();
    $this->assertFalse($acceso->checkNamedRoute(self::RUTA, [], $sin_rol), 'autenticado sin rol');
    $solo_configuracion = $this->usuarioConPermisos(['administer site configuration', 'access content overview']);
    $this->assertFalse($acceso->checkNamedRoute(self::RUTA, [], $solo_configuracion), 'otros permisos');
    $ruta = $this->container->get('router.route_provider')->getRouteByName(self::RUTA);
    $this->assertSame('/admin/acreditacion', $ruta->getPath());
    $this->assertSame('access acreditacion panel', $ruta->getRequirement('_permission'));
    // Las páginas de administración usan el tema de administración.
    $this->assertTrue($acceso->checkNamedRoute(self::RUTA, [], $this->usuarioConPermisos(['access acreditacion panel'])));
  }

  /**
   * Aparecen los 40 programas y cada uno con su enlace de edición.
   */
  public function testAparecenLosCuarentaProgramas(): void {
    $programas = $this->programas(40);
    [$html] = $this->panel($this->acreditacion());
    $this->assertSame(40, substr_count($html, '<th scope="row">'));
    $this->assertStringContainsString('Se muestran 40 programas.', $html);
    $enlaces = $this->enlaces($html);
    foreach ($programas as $programa) {
      // Cambio deliberado (8.3): la ficha se edita con el formulario simple.
      $this->assertContains('/admin/acreditacion/programa/' . $programa->id() . '/ficha', $enlaces, $programa->label());
      $this->assertNotContains('/node/' . $programa->id() . '/edit', $enlaces, $programa->label());
      $this->assertStringContainsString($programa->label(), $html);
    }
    $this->assertStringContainsString('Facultad de Pruebas', $html);
    $this->assertStringContainsString('Pregrado', $html);
  }

  /**
   * Cada enlace del panel apunta a una ruta que existe.
   */
  public function testCadaEnlaceSeResuelveEnUnaRuta(): void {
    [$programa] = $this->programas(2);
    $proceso = $this->crearNodo('proceso', ['title' => 'Proceso', 'field_programa' => $programa->id()]);
    $plan = $this->crearNodo('plan_mejoramiento', ['title' => 'Plan', 'field_proceso' => $proceso->id()]);
    [$html] = $this->panel($this->acreditacion());
    $enlaces = $this->enlaces($html);
    $this->assertNotEmpty($enlaces);
    $coincidencia = $this->container->get('router.no_access_checks');
    foreach (array_unique($enlaces) as $url) {
      $ruta = parse_url($url, PHP_URL_PATH);
      try {
        $coincidencia->match($ruta);
      }
      catch (ExceptionInterface $e) {
        $this->fail("El enlace $url no apunta a una ruta existente.");
      }
    }
    // Los enlaces de creación llevan la relación ya elegida.
    $this->assertContains('/node/add/valoracion?proceso=' . $proceso->id(), $enlaces);
    $this->assertContains('/node/add/hallazgo?proceso=' . $proceso->id(), $enlaces);
    $this->assertContains('/node/add/meta?plan=' . $plan->id(), $enlaces);
    $this->assertContains('/node/add/proceso?programa=' . $programa->id(), $enlaces);
    // Cambio deliberado (8.3): los documentos se suben con el simple.
    $this->assertContains('/admin/acreditacion/documento/nuevo?proceso=' . $proceso->id(), $enlaces);
    $this->assertNotContains('/media/add/documento?proceso=' . $proceso->id(), $enlaces);
    $this->assertContains('/node/' . $plan->id() . '/edit', $enlaces);
    $this->assertContains('/node/' . $proceso->id() . '/edit', $enlaces);
    // Sin plan ni proceso, el segundo programa solo enlaza a la lista del tipo.
    $this->assertContains('/admin/content?type=valoracion', $enlaces);
    $this->assertContains('/admin/content?type=seguimiento', $enlaces);
  }

  /**
   * Solo se enlaza lo que la persona puede abrir.
   */
  public function testSoloEnlazaLoQueSePuedeAbrir(): void {
    $this->programas(3);
    $solo_panel = $this->usuarioConPermisos(['access acreditacion panel']);
    [$html] = $this->panel($solo_panel);
    $this->assertSame(3, substr_count($html, '<th scope="row">'));
    $enlaces = $this->enlaces($html);
    foreach ($enlaces as $url) {
      $this->assertStringNotContainsString('/edit', $url);
      $this->assertStringNotContainsString('/node/add/', $url);
    }
    $this->assertStringNotContainsString('Editar la ficha', $html);
    // Sin ningún acceso, un programa no muestra el bloque «Accesos de edición».
    $this->assertStringNotContainsString('<details>', $html);
  }

  /**
   * Los indicadores salen solo cuando hay dato y nunca dicen «Pendiente».
   */
  public function testIndicadoresSoloSiHayDato(): void {
    [$con, $sin] = $this->programas(2);
    $proceso = $this->crearNodo('proceso', ['title' => 'Proceso', 'field_programa' => $con->id()]);
    $this->crearNodo('plan_mejoramiento', ['title' => 'Plan', 'field_proceso' => $proceso->id()]);
    [$html] = $this->panel($this->acreditacion());
    $this->assertSame(1, substr_count($html, 'Con proceso'));
    $this->assertSame(1, substr_count($html, 'Con plan'));
    $this->assertStringNotContainsStringIgnoringCase('pendiente', $html);
    $this->assertStringNotContainsString('Sin proceso', $html);
    // El programa sin proceso tiene una celda de estado vacía.
    $this->assertSame('Programa 02', $sin->label());
    $this->assertMatchesRegularExpression('~Programa 02</th>.*?<td></td>\s*<td>~s', $html);
  }

  /**
   * La búsqueda por nombre filtra y valida.
   */
  public function testBusquedaPorNombre(): void {
    $this->programas(12);
    $acreditacion = $this->acreditacion();
    [$html] = $this->panel($acreditacion, ['q' => 'Programa 07']);
    $this->assertSame(1, substr_count($html, '<th scope="row">'));
    $this->assertStringContainsString('Se muestra 1 programa.', $html);
    $this->assertStringContainsString('value="Programa 07"', $html);
    // Sin coincidencias.
    [$html] = $this->panel($acreditacion, ['q' => 'zzzz']);
    $this->assertStringContainsString('Ningún programa coincide con la búsqueda.', $html);
    $this->assertStringNotContainsString('<table', $html);
    // Un texto con HTML se escapa.
    [$html] = $this->panel($acreditacion, ['q' => '<script>alert(1)</script>']);
    $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    // Un texto demasiado largo se recorta a 100 caracteres (y no coincide).
    [$html] = $this->panel($acreditacion, ['q' => str_repeat('a', 300)]);
    $this->assertStringContainsString('value="' . str_repeat('a', 100) . '"', $html);
    $this->assertStringNotContainsString(str_repeat('a', 101), $html);
    // Un parámetro en forma de arreglo no produce una petición mala.
    [$html] = $this->panel($acreditacion, ['q' => ['uno', 'dos']]);
    $this->assertStringContainsString('Programas', $html);
    $this->assertStringNotContainsString('Excepción', $html);
  }

  /**
   * Sin programas muestra un estado vacío claro.
   */
  public function testEstadoVacio(): void {
    [$html] = $this->panel($this->acreditacion());
    $this->assertStringContainsString('Todavía no hay programas publicados.', $html);
    $this->assertStringNotContainsString('<table', $html);
    $this->assertStringNotContainsString('role="search"', $html);
    // Los accesos generales siguen disponibles.
    $this->assertStringContainsString('Todo el contenido', $html);
  }

  /**
   * Tabla utilizable con teclado y lectores: cabeceras con alcance, sin saltos.
   */
  public function testTablaAccesible(): void {
    $this->programas(2);
    [$html] = $this->panel($this->acreditacion());
    $this->assertSame(6, substr_count($html, '<th scope="col">'));
    $this->assertSame(2, substr_count($html, '<th scope="row">'));
    $this->assertStringContainsString('<caption', $html);
    $this->assertStringContainsString('role="region"', $html);
    $this->assertStringContainsString('tabindex="0"', $html);
    $this->assertStringContainsString('<summary>', $html);
    $this->assertStringContainsString('<label for="acreditacion-q">', $html);
    // El panel no añade encabezados propios (solo el h1 de la página).
    $this->assertDoesNotMatchRegularExpression('~<h[1-6][ >]~', $html);
    // El texto de usuario sale escapado: un nombre con HTML no se interpreta.
    $this->crearNodo('programa', ['title' => '<b>Negrita</b>', 'field_clave' => 'x']);
    [$html] = $this->panel($this->acreditacion());
    $this->assertStringNotContainsString('<b>Negrita</b>', $html);
    $this->assertStringContainsString('&lt;b&gt;Negrita&lt;/b&gt;', $html);
    // Lo mismo con el título de un proceso dentro del texto de un enlace.
    $programa = $this->crearNodo('programa', ['title' => 'Con proceso', 'field_clave' => 'cp']);
    $this->crearNodo('proceso', ['title' => '<i>Cursiva</i>', 'field_programa' => $programa->id()]);
    [$html] = $this->panel($this->acreditacion());
    $this->assertStringNotContainsString('<i>Cursiva</i>', $html);
    $this->assertStringContainsString('&lt;i&gt;Cursiva&lt;/i&gt;', $html);
  }

  /**
   * Caché por usuario y con las etiquetas del contenido; nunca pública.
   */
  public function testCachePorUsuarioMasEtiquetas(): void {
    $this->programas(1);
    [, $construccion] = $this->panel($this->acreditacion());
    $cache = $construccion['#cache'];
    $this->assertContains('user', $cache['contexts']);
    $this->assertContains('url.query_args:q', $cache['contexts']);
    foreach (['node_list:programa', 'node_list:proceso', 'node_list:plan_mejoramiento'] as $etiqueta) {
      $this->assertContains($etiqueta, $cache['tags']);
    }
    $this->assertNotSame(0, $cache['max-age'] ?? -1);
    $this->assertContains('unillanos_autoeval/acreditacion', $construccion['#attached']['library']);
  }

  /**
   * Los enlaces que un usuario ve en el menú «Administración».
   *
   * Como el bloque «Administración» de Navigation: desde el segundo nivel.
   * El gestor de menús no quita los enlaces sin acceso: marca `access` en
   * cada elemento y el pintado omite los que no se pueden abrir. Aquí se
   * filtra igual.
   *
   * @return string[]
   *   Identificadores de los enlaces visibles.
   */
  protected function enlacesDelMenu(UserInterface $usuario): array {
    $this->container->get('plugin.manager.menu.link')->rebuild();
    $this->container->get('current_user')->setAccount($usuario);
    $menu = $this->container->get('menu.link_tree');
    $parametros = new MenuTreeParameters();
    $parametros->setMinDepth(2)->onlyEnabledLinks();
    $arbol = $menu->transform($menu->load('admin', $parametros), [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:flatten'],
    ]);
    $visibles = array_filter($arbol, fn($elemento) => $elemento->access === NULL || $elemento->access->isAllowed());
    return array_values(array_map(fn($elemento) => $elemento->link->getPluginId(), $visibles));
  }

  /**
   * Con el permiso, el enlace del menú está.
   */
  public function testEnlaceDelMenuConPermiso(): void {
    $this->assertContains('unillanos_autoeval.admin.acreditacion', $this->enlacesDelMenu($this->acreditacion()));
    // Hijo directo de «Administración»: no cuelga de «Configuración» ni de
    // «Contenido» (que Navigation quita de su árbol).
    $definicion = $this->container->get('plugin.manager.menu.link')->getDefinition('unillanos_autoeval.admin.acreditacion');
    $this->assertSame('admin', $definicion['menu_name']);
    $this->assertSame('system.admin', $definicion['parent']);
    $this->assertSame(self::RUTA, $definicion['route_name']);
  }

  /**
   * Sin el permiso, el enlace del menú no está.
   */
  #[DataProvider('usuariosSinPermiso')]
  public function testEnlaceDelMenuSinPermiso(string $caso): void {
    $usuario = match ($caso) {
      'otros permisos' => $this->usuarioConPermisos(['access content overview', 'view the administration theme']),
      'autenticado sin rol' => User::create(['name' => 'sinrol', 'status' => 1]),
      default => User::getAnonymousUser(),
    };
    if (!$usuario->id() && $caso === 'autenticado sin rol') {
      $usuario->save();
    }
    $this->assertNotContains('unillanos_autoeval.admin.acreditacion', $this->enlacesDelMenu($usuario), $caso);
  }

  /**
   * Casos de usuario sin el permiso del panel.
   *
   * @return array<string, array{0: string}>
   *   Un caso por tipo de usuario.
   */
  public static function usuariosSinPermiso(): array {
    return [
      'otros permisos' => ['otros permisos'],
      'autenticado sin rol' => ['autenticado sin rol'],
      'anónimo' => ['anónimo'],
    ];
  }

  /**
   * La precarga de relaciones: solo con un entero que apunte al tipo esperado.
   */
  public function testPrecargaDeRelaciones(): void {
    [$programa] = $this->programas(1);
    $proceso = $this->crearNodo('proceso', ['title' => 'Proceso', 'field_programa' => $programa->id()]);
    $plan = $this->crearNodo('plan_mejoramiento', ['title' => 'Plan', 'field_proceso' => $proceso->id()]);
    $this->container->get('current_user')->setAccount($this->acreditacion());
    $hooks = $this->container->get(UnillanosAutoevalHooks::class);
    $cargar = function (string $tipo, array $consulta) use ($hooks): Node {
      $this->container->get('request_stack')->push(Request::create('/node/add/' . $tipo, 'GET', $consulta));
      $nuevo = Node::create(['type' => $tipo, 'title' => 'Nuevo']);
      $hooks->entityPrepareForm($nuevo, 'default', new FormState());
      $this->container->get('request_stack')->pop();
      return $nuevo;
    };
    $this->assertSame((string) $programa->id(), (string) $cargar('proceso', ['programa' => (string) $programa->id()])->get('field_programa')->target_id);
    foreach (['valoracion', 'hallazgo', 'plan_mejoramiento'] as $tipo) {
      $this->assertSame((string) $proceso->id(), (string) $cargar($tipo, ['proceso' => (string) $proceso->id()])->get('field_proceso')->target_id, $tipo);
    }
    $this->assertSame((string) $plan->id(), (string) $cargar('meta', ['plan' => (string) $plan->id()])->get('field_plan')->target_id);
    // Un identificador de otro tipo de contenido no se precarga.
    $this->assertTrue($cargar('valoracion', ['proceso' => (string) $programa->id()])->get('field_proceso')->isEmpty());
    $this->assertTrue($cargar('meta', ['plan' => (string) $proceso->id()])->get('field_plan')->isEmpty());
    // Valores que no son un entero, o que no existen.
    foreach (['abc', '-1', '1.5', '0x1', '999999', '', '49; DROP TABLE node'] as $malo) {
      $this->assertTrue($cargar('valoracion', ['proceso' => $malo])->get('field_proceso')->isEmpty(), $malo);
    }
    // Un parámetro en forma de arreglo se ignora.
    $this->container->get('request_stack')->push(Request::create('/node/add/valoracion', 'GET', ['proceso' => ['1']]));
    $nuevo = Node::create(['type' => 'valoracion', 'title' => 'Nuevo']);
    $hooks->entityPrepareForm($nuevo, 'default', new FormState());
    $this->container->get('request_stack')->pop();
    $this->assertTrue($nuevo->get('field_proceso')->isEmpty());
    // Otros tipos de contenido no se tocan.
    $this->assertFalse($cargar('seguimiento', ['proceso' => (string) $proceso->id(), 'meta' => '1'])->hasField('field_proceso'));
  }

  /**
   * La precarga no cambia un contenido existente ni un valor ya escrito.
   */
  public function testPrecargaNoSobrescribeNiTocaLoExistente(): void {
    [$a, $b] = $this->programas(2);
    $proceso_a = $this->crearNodo('proceso', ['title' => 'A', 'field_programa' => $a->id()]);
    $proceso_b = $this->crearNodo('proceso', ['title' => 'B', 'field_programa' => $b->id()]);
    $this->container->get('current_user')->setAccount($this->acreditacion());
    $hooks = $this->container->get(UnillanosAutoevalHooks::class);
    $this->container->get('request_stack')->push(Request::create('/node/add/valoracion', 'GET', ['proceso' => (string) $proceso_b->id()]));
    // Ya tiene valor: no se sobrescribe.
    $nuevo = Node::create(['type' => 'valoracion', 'title' => 'Nuevo', 'field_proceso' => $proceso_a->id()]);
    $hooks->entityPrepareForm($nuevo, 'default', new FormState());
    $this->assertSame((string) $proceso_a->id(), (string) $nuevo->get('field_proceso')->target_id);
    // Un contenido ya guardado no se modifica (es la edición).
    $existente = $this->crearNodo('valoracion', ['title' => 'Existente']);
    $hooks->entityPrepareForm($existente, 'default', new FormState());
    $this->assertTrue($existente->get('field_proceso')->isEmpty());
    // Otra operación (por ejemplo borrar) tampoco precarga.
    $otro = Node::create(['type' => 'valoracion', 'title' => 'Otro']);
    $hooks->entityPrepareForm($otro, 'delete', new FormState());
    $this->assertTrue($otro->get('field_proceso')->isEmpty());
    $this->container->get('request_stack')->pop();
  }

  /**
   * La precarga no revela contenido que la persona no puede ver.
   */
  public function testPrecargaRespetaElAcceso(): void {
    [$programa] = $this->programas(1);
    $oculto = $this->crearNodo('proceso', ['title' => 'Oculto', 'status' => 0, 'field_programa' => $programa->id()]);
    $sin_acceso = $this->usuarioConPermisos(['create valoracion content']);
    $this->container->get('current_user')->setAccount($sin_acceso);
    $this->container->get('request_stack')->push(Request::create('/node/add/valoracion', 'GET', ['proceso' => (string) $oculto->id()]));
    $nuevo = Node::create(['type' => 'valoracion', 'title' => 'Nuevo']);
    $this->container->get(UnillanosAutoevalHooks::class)->entityPrepareForm($nuevo, 'default', new FormState());
    $this->container->get('request_stack')->pop();
    $this->assertTrue($nuevo->get('field_proceso')->isEmpty());
  }

  /**
   * El medio «documento» también recibe el proceso.
   */
  public function testPrecargaDelDocumento(): void {
    [$programa] = $this->programas(1);
    $proceso = $this->crearNodo('proceso', ['title' => 'Proceso', 'field_programa' => $programa->id()]);
    $this->container->get('current_user')->setAccount($this->acreditacion());
    $this->container->get('request_stack')->push(Request::create('/media/add/documento', 'GET', ['proceso' => (string) $proceso->id()]));
    $medio = Media::create(['bundle' => 'documento', 'name' => 'Informe']);
    $this->container->get(UnillanosAutoevalHooks::class)->entityPrepareForm($medio, 'add', new FormState());
    $this->container->get('request_stack')->pop();
    $this->assertSame((string) $proceso->id(), (string) $medio->get('field_proceso')->target_id);
  }

}
