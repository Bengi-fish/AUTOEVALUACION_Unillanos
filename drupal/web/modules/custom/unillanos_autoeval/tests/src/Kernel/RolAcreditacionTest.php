<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el rol «Acreditación» y los permisos propios del módulo.
 *
 * Cubre la foto exacta del rol, lo que puede y no puede hacer, las rutas de
 * administración, el formulario del micrositio y el permiso que habilita el
 * campo «Publicado».
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class RolAcreditacionTest extends UnillanosKernelTestBase {

  /**
   * Permiso propio que habilita «Publicado».
   */
  const PUBLICACION = 'change publication status on acreditacion content';

  /**
   * Tipos de nodo que el rol crea y edita (lineamiento queda fuera).
   */
  const TIPOS_DEL_ROL = [
    'programa',
    'proceso',
    'valoracion',
    'hallazgo',
    'plan_mejoramiento',
    'meta',
    'seguimiento',
  ];

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
    // Los roles anónimo y autenticado del sitio (con «access content» y
    // «view media»): quien tiene cuenta los hereda.
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
    // Un tipo que no es de autoevaluación, para comprobar que no cambia.
    NodeType::create(['type' => 'noticia', 'name' => 'noticia'])->save();
  }

  /**
   * Los permisos del rol, tal como están en los dos archivos de configuración.
   *
   * @param string $donde
   *   Cadena `modulo` (config/optional) o `sync` (config/sync).
   *
   * @return array<string, mixed>
   *   El contenido del archivo del rol.
   */
  protected function archivoDelRol(string $donde): array {
    $ruta = $donde === 'modulo'
      ? $this->container->get('extension.list.module')->getPath('unillanos_autoeval') . '/config/optional/user.role.acreditacion.yml'
      : DRUPAL_ROOT . '/../config/sync/user.role.acreditacion.yml';
    $this->assertFileExists($ruta);
    return Yaml::decode((string) file_get_contents($ruta));
  }

  /**
   * La lista exacta de permisos que debe tener el rol.
   *
   * @return string[]
   *   Los permisos, ordenados.
   */
  protected static function permisosEsperados(): array {
    $permisos = [
      'access acreditacion panel',
      'access content overview',
      'access media overview',
      'access navigation',
      self::PUBLICACION,
      'create documento media',
      'edit any documento media',
      'edit unillanos micrositio',
      'view all media revisions',
      'view all revisions',
      'view own unpublished content',
      'view own unpublished media',
      'view the administration theme',
    ];
    foreach (self::TIPOS_DEL_ROL as $tipo) {
      $permisos[] = "create $tipo content";
      $permisos[] = "edit any $tipo content";
      // Solo «propio» (el autor es quien eliminó); nunca «cualquier».
      $permisos[] = "delete own $tipo content";
    }
    $permisos[] = 'delete own documento media';
    sort($permisos);
    return $permisos;
  }

  /**
   * Crea el rol en la base de la prueba y devuelve un usuario que lo tiene.
   *
   * «access navigation» es del módulo navigation, que no está en la prueba.
   */
  protected function usuarioAcreditacion(): User {
    $permisos = array_values(array_diff($this->archivoDelRol('modulo')['permissions'], ['access navigation']));
    Role::create(['id' => 'acreditacion', 'label' => 'Acreditación', 'permissions' => $permisos])->save();
    return $this->usuario(['acreditacion']);
  }

  /**
   * Crea un usuario activo con los roles dados.
   *
   * @param string[] $roles
   *   Ids de rol.
   */
  protected function usuario(array $roles = []): User {
    static $n = 0;
    $usuario = User::create(['name' => 'usuario' . ++$n, 'status' => 1, 'roles' => $roles]);
    $usuario->save();
    return $usuario;
  }

  /**
   * Crea un usuario con un rol propio con los permisos dados.
   *
   * @param string[] $permisos
   *   Permisos del rol.
   */
  protected function usuarioConPermisos(array $permisos): User {
    static $n = 0;
    $id = 'rol_prueba_' . ++$n;
    Role::create(['id' => $id, 'label' => $id, 'permissions' => $permisos])->save();
    return $this->usuario([$id]);
  }

  /**
   * Foto exacta del rol: añadir un permiso sin querer rompe la prueba.
   */
  public function testFotoExactaDelRol(): void {
    $esperados = self::permisosEsperados();
    $modulo = $this->archivoDelRol('modulo');
    $sync = $this->archivoDelRol('sync');
    $this->assertSame($esperados, $modulo['permissions']);
    $this->assertSame($esperados, $sync['permissions']);
    $this->assertCount(35, $esperados);
    // El módulo y el sitio declaran el mismo rol.
    unset($sync['uuid']);
    $this->assertEquals($modulo, $sync);
    $this->assertSame('acreditacion', $modulo['id']);
    $this->assertSame('Acreditación', $modulo['label']);
    $this->assertFalse($modulo['is_admin']);
    // Sin ningún «administer» ni saltarse el control de acceso; solo se borra
    // lo propio (cambio deliberado del paso 14: antes el rol no borraba nada).
    $propios = array_merge(
      array_map(fn($t) => "delete own $t content", self::TIPOS_DEL_ROL),
      ['delete own documento media'],
    );
    foreach ($modulo['permissions'] as $permiso) {
      $this->assertStringNotContainsString('administer', $permiso, $permiso);
      $this->assertStringNotContainsString('bypass', $permiso, $permiso);
      if (str_contains($permiso, 'delete')) {
        $this->assertContains($permiso, $propios, $permiso);
      }
    }
    $this->assertSame([], array_values(array_diff($propios, $modulo['permissions'])));
    $this->assertNotContains('delete any programa content', $modulo['permissions']);
    $this->assertNotContains('delete own lineamiento content', $modulo['permissions']);
    // Incluye el permiso nuevo de publicación y los dos propios del módulo.
    foreach (['access acreditacion panel', 'edit unillanos micrositio', self::PUBLICACION] as $propio) {
      $this->assertContains($propio, $modulo['permissions']);
    }
    // Cada permiso del rol existe; navigation no está en esta prueba.
    $existentes = array_keys($this->container->get('user.permissions')->getPermissions());
    foreach (array_diff($modulo['permissions'], ['access navigation']) as $permiso) {
      $this->assertContains($permiso, $existentes, $permiso);
    }
    // La lista de tipos del rol no incluye lineamiento ni otros medios.
    $this->assertNotContains('create lineamiento content', $modulo['permissions']);
    $this->assertNotContains('edit any lineamiento content', $modulo['permissions']);
  }

  /**
   * Crea y edita cada tipo de autoevaluación; no borra lo que no es suyo.
   */
  #[DataProvider('tiposDelRol')]
  public function testCreaEditaSinBorrar(string $tipo): void {
    $acreditacion = $this->usuarioAcreditacion();
    $this->assertTrue(Node::create(['type' => $tipo, 'title' => 'x'])->access('create', $acreditacion), "crear $tipo");
    $nodo = $this->crearNodo($tipo, ['title' => 'Existente ' . $tipo]);
    $this->assertTrue($nodo->access('update', $acreditacion), "editar $tipo");
    $this->assertFalse($nodo->access('delete', $acreditacion), "no borrar $tipo ajeno");
  }

  /**
   * Tipos de nodo del rol.
   *
   * @return array<string, array{0: string}>
   *   Un caso por tipo.
   */
  public static function tiposDelRol(): array {
    return array_combine(self::TIPOS_DEL_ROL, array_map(fn($t) => [$t], self::TIPOS_DEL_ROL));
  }

  /**
   * El lineamiento y los demás tipos no los edita el rol.
   */
  public function testNoEditaLineamientoNiOtrosTipos(): void {
    $acreditacion = $this->usuarioAcreditacion();
    $this->assertFalse(Node::create(['type' => 'lineamiento', 'title' => 'x'])->access('create', $acreditacion));
    $lineamiento = $this->crearNodo('lineamiento', ['title' => 'Modelo']);
    $this->assertFalse($lineamiento->access('update', $acreditacion));
    $noticia = $this->crearNodo('noticia', ['title' => 'Noticia']);
    $this->assertFalse($noticia->access('update', $acreditacion));
    $this->assertFalse(Node::create(['type' => 'noticia', 'title' => 'x'])->access('create', $acreditacion));
  }

  /**
   * Los documentos (medio «documento»): crea y edita, no borra lo ajeno.
   */
  public function testDocumentosCreaEditaSinBorrar(): void {
    $acreditacion = $this->usuarioAcreditacion();
    $this->assertTrue($this->container->get('entity_type.manager')->getAccessControlHandler('media')->createAccess('documento', $acreditacion));
    $medio = Media::create(['bundle' => 'documento', 'name' => 'Informe', 'status' => 1]);
    $medio->save();
    $this->assertTrue($medio->access('update', $acreditacion));
    $this->assertFalse($medio->access('delete', $acreditacion));
  }

  /**
   * Sin acceso a configuración, módulos, personas, estructura ni informes.
   */
  public function testSinEntradaEnLaAdministracion(): void {
    $acreditacion = $this->usuarioAcreditacion();
    $acceso = $this->container->get('access_manager');
    foreach ([
      'system.admin_config',
      'system.modules_list',
      'entity.user.collection',
      'system.admin_structure',
      'system.admin_reports',
    ] as $ruta) {
      $this->assertFalse($acceso->checkNamedRoute($ruta, [], $acreditacion), $ruta);
    }
    // Sí entra a la lista de contenido y al formulario del micrositio.
    $this->assertTrue($acceso->checkNamedRoute('system.admin_content', [], $acreditacion));
    $this->assertTrue($acceso->checkNamedRoute('unillanos_autoeval.micrositio', [], $acreditacion));
    $this->assertTrue($acreditacion->hasPermission('view the administration theme'));
  }

  /**
   * El formulario del micrositio exige su permiso propio.
   *
   * Diferencia deliberada: antes lo abría quien tuviera «administer site
   * configuration»; ahora solo quien tenga «edit unillanos micrositio».
   */
  public function testFormularioDelMicrositioExigeSuPermisoPropio(): void {
    $acceso = $this->container->get('access_manager');
    $ruta = 'unillanos_autoeval.micrositio';
    $this->assertFalse($acceso->checkNamedRoute($ruta, [], User::getAnonymousUser()), 'anónimo');
    $this->assertFalse($acceso->checkNamedRoute($ruta, [], $this->usuario()), 'autenticado sin rol');
    $solo_configuracion = $this->usuarioConPermisos(['administer site configuration']);
    $this->assertFalse($acceso->checkNamedRoute($ruta, [], $solo_configuracion), 'solo administer site configuration');
    $otros = $this->usuarioConPermisos(['access content overview', 'view the administration theme']);
    $this->assertFalse($acceso->checkNamedRoute($ruta, [], $otros), 'otros permisos');
    $propio = $this->usuarioConPermisos(['edit unillanos micrositio']);
    $this->assertTrue($acceso->checkNamedRoute($ruta, [], $propio), 'permiso propio');
    $this->assertTrue($acceso->checkNamedRoute($ruta, [], $this->usuarioAcreditacion()), 'rol Acreditación');
    $this->assertSame('edit unillanos micrositio', $this->container->get('router.route_provider')->getRouteByName($ruta)->getRequirement('_permission'));
  }

  /**
   * Los tres permisos propios existen.
   */
  public function testPermisosPropiosDefinidos(): void {
    $permisos = $this->container->get('user.permissions')->getPermissions();
    foreach (['edit unillanos micrositio', 'access acreditacion panel', self::PUBLICACION] as $permiso) {
      $this->assertArrayHasKey($permiso, $permisos);
      $this->assertSame('unillanos_autoeval', $permisos[$permiso]['provider']);
    }
  }

  /**
   * Con el permiso, el campo «Publicado» se puede editar en los 8 tipos.
   */
  public function testPublicadoConPermisoEnLosOchoTipos(): void {
    $con = $this->usuarioConPermisos([self::PUBLICACION]);
    foreach (array_merge(self::TIPOS_DEL_ROL, ['lineamiento']) as $tipo) {
      $nodo = $this->crearNodo($tipo, ['title' => $tipo]);
      $this->assertTrue($nodo->get('status')->access('edit', $con), "con permiso, $tipo");
    }
    $medio = Media::create(['bundle' => 'documento', 'name' => 'Informe', 'status' => 1]);
    $this->assertTrue($medio->get('status')->access('edit', $con), 'con permiso, documento');
  }

  /**
   * Sin el permiso, el campo «Publicado» de los nodos no se puede editar.
   */
  public function testPublicadoSinPermisoNoSeEdita(): void {
    $sin = $this->usuarioConPermisos([
      'create programa content',
      'edit any programa content',
      'edit any proceso content',
    ]);
    foreach (['programa', 'proceso', 'lineamiento'] as $tipo) {
      $nodo = $this->crearNodo($tipo, ['title' => $tipo]);
      $this->assertFalse($nodo->get('status')->access('edit', $sin), "sin permiso, $tipo");
    }
  }

  /**
   * Solo suma: quien ya tiene acceso por otra vía lo conserva.
   */
  public function testPublicadoSoloSuma(): void {
    $nodo = $this->crearNodo('programa', ['title' => 'P']);
    foreach (['administer nodes', 'administer node published status'] as $permiso) {
      $usuario = $this->usuarioConPermisos([$permiso]);
      $this->assertTrue($nodo->get('status')->access('edit', $usuario), $permiso);
    }
    // Con ambos (el del núcleo y el propio) sigue permitido.
    $ambos = $this->usuarioConPermisos(['administer node published status', self::PUBLICACION]);
    $this->assertTrue($nodo->get('status')->access('edit', $ambos));
  }

  /**
   * El permiso no cambia nada fuera de «Publicado» ni de los tipos listados.
   */
  public function testPublicadoNoAfectaOtrosTiposNiCampos(): void {
    $con = $this->usuarioConPermisos([self::PUBLICACION]);
    // Otro tipo de contenido.
    $noticia = $this->crearNodo('noticia', ['title' => 'N']);
    $this->assertFalse($noticia->get('status')->access('edit', $con), 'otro tipo de nodo');
    // Otros campos de un tipo cubierto.
    $programa = $this->crearNodo('programa', ['title' => 'P']);
    foreach (['uid', 'promote', 'sticky', 'created'] as $campo) {
      $this->assertFalse($programa->get($campo)->access('edit', $con), $campo);
    }
    // El permiso no concede editar ni crear contenido.
    $this->assertFalse($programa->access('update', $con));
    $this->assertFalse(Node::create(['type' => 'programa', 'title' => 'x'])->access('create', $con));
    // Otro tipo de medio.
    MediaType::create(['id' => 'otro', 'label' => 'Otro', 'source' => 'file'])->save();
    $otro = Media::create(['bundle' => 'otro', 'name' => 'Otro', 'status' => 1]);
    $sin = $this->usuarioConPermisos(['edit any otro media']);
    $this->assertSame(
      $otro->get('status')->access('edit', $sin),
      $otro->get('status')->access('edit', $con),
      'otro tipo de medio: el permiso no cambia el resultado'
    );
  }

  /**
   * Con el permiso, el formulario muestra «Publicado» y se puede despublicar.
   */
  #[DataProvider('tiposDelRol')]
  public function testFormularioMuestraPublicadoDespublica(string $tipo): void {
    $this->container->get('current_user')->setAccount($this->usuarioAcreditacion());
    EntityFormDisplay::create(['targetEntityType' => 'node', 'bundle' => $tipo, 'mode' => 'default', 'status' => TRUE])
      ->setComponent('title', ['type' => 'string_textfield', 'weight' => 0])
      ->setComponent('status', ['type' => 'boolean_checkbox', 'weight' => 10, 'settings' => ['display_label' => TRUE]])
      ->save();
    $nodo = $this->crearNodo($tipo, ['title' => 'Publicado ' . $tipo, 'status' => 1]);
    $formulario = $this->container->get('entity.form_builder')->getForm($nodo, 'default');
    $this->assertTrue($formulario['status']['#access'] ?? FALSE, "el formulario de $tipo muestra «Publicado»");

    foreach ([FALSE, TRUE] as $publicado) {
      $estado = new FormState();
      // FormBuilder::submitForm() toma la entrada de los valores. Un casillero
      // sin marcar llega como valor nulo (con FALSE el formulario usa el valor
      // por defecto, que es «publicado»).
      $estado->setValues([
        'title' => [['value' => 'Publicado ' . $tipo]],
        'status' => ['value' => $publicado ? 1 : NULL],
        'op' => 'Save',
      ]);
      $objeto = $this->container->get('entity_type.manager')->getFormObject('node', 'default')->setEntity(Node::load($nodo->id()));
      $this->container->get('form_builder')->submitForm($objeto, $estado);
      $this->assertSame([], $estado->getErrors());
      $this->assertSame($publicado, Node::load($nodo->id())->isPublished(), $publicado ? 'vuelve a publicarse' : 'se despublica');
    }
  }

  /**
   * Sin el permiso, el formulario no muestra «Publicado».
   */
  public function testFormularioSinPermisoNoMuestraPublicado(): void {
    $sin = $this->usuarioConPermisos(['create programa content', 'edit any programa content']);
    $this->container->get('current_user')->setAccount($sin);
    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'programa',
      'mode' => 'default',
      'status' => TRUE,
    ])
      ->setComponent('title', ['type' => 'string_textfield', 'weight' => 0])
      ->setComponent('status', ['type' => 'boolean_checkbox', 'weight' => 10])
      ->save();
    $nodo = $this->crearNodo('programa', ['title' => 'P']);
    $formulario = $this->container->get('entity.form_builder')->getForm($nodo, 'default');
    $this->assertFalse($formulario['status']['#access'] ?? FALSE);
  }

  /**
   * Los documentos: con el permiso el campo es editable; el medio no lo exige.
   *
   * El núcleo no restringe «Publicado» en los medios: quien puede editar un
   * documento ya puede cambiarlo. El permiso propio lo cubre igualmente y no
   * quita nada.
   */
  public function testPublicadoDelMedioDocumento(): void {
    $medio = Media::create(['bundle' => 'documento', 'name' => 'Informe', 'status' => 1]);
    $medio->save();
    $solo_editar = $this->usuarioConPermisos(['edit any documento media']);
    $this->assertTrue($medio->get('status')->access('edit', $solo_editar), 'el núcleo no restringe «Publicado» en los medios');
    $this->assertTrue($medio->get('status')->access('edit', $this->usuarioAcreditacion()));
  }

  /**
   * El rol de Acreditación no es de administrador ni lo parece.
   */
  public function testElRolNoEsAdministrador(): void {
    $acreditacion = $this->usuarioAcreditacion();
    $this->assertFalse($acreditacion->hasPermission('administer nodes'));
    $this->assertFalse($acreditacion->hasPermission('administer media'));
    $this->assertFalse($acreditacion->hasPermission('administer site configuration'));
    $this->assertFalse($acreditacion->hasPermission('administer users'));
    $this->assertFalse($acreditacion->hasPermission('bypass node access'));
    $this->assertInstanceOf(RoleInterface::class, Role::load('acreditacion'));
    $this->assertFalse(Role::load('acreditacion')->isAdmin());
  }

}
