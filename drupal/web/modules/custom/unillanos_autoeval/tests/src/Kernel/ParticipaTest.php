<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\unillanos_autoeval\Controller\ParticipaController;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\webform\Entity\Webform;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el formulario Participa, la página y los roles de la Fase 8.
 *
 * El webform se importa del archivo de `config/sync`, el mismo que usa el
 * sitio: lo que se prueba es la configuración real.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class ParticipaTest extends UnillanosKernelTestBase {

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
    'path',
    'path_alias',
    'webform',
    'unillanos_autoeval',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');
    $this->installSchema('webform', ['webform']);
    $this->installEntitySchema('webform_submission');
    $this->installConfig(['webform']);
    // El primer usuario (uid 1) tiene todos los permisos: se reserva.
    User::create(['name' => 'superusuario', 'status' => 1])->save();
    foreach (['anonymous', 'authenticated'] as $id) {
      Role::create(['id' => $id, 'label' => $id])->save();
    }
    $existentes = array_keys($this->container->get('user.permissions')->getPermissions());
    $rol = $this->archivo('user.role.gestor_participacion.yml');
    Role::create([
      'id' => 'gestor_participacion',
      'label' => $rol['label'],
      'permissions' => array_values(array_intersect($rol['permissions'], $existentes)),
    ])->save();
    Role::create(['id' => 'acreditacion', 'label' => 'acreditacion'])->save();
    $webform = $this->archivo('webform.webform.participa.yml');
    unset($webform['uuid']);
    Webform::create($webform)->save();
  }

  /**
   * Lee un archivo YAML de config/sync.
   *
   * @param string $nombre
   *   Nombre del archivo.
   *
   * @return array<string, mixed>
   *   El contenido.
   */
  protected function archivo(string $nombre): array {
    $ruta = DRUPAL_ROOT . '/../config/sync/' . $nombre;
    $this->assertFileExists($ruta);
    return Yaml::decode((string) file_get_contents($ruta));
  }

  /**
   * Crea un usuario con los roles dados y lo deja como el actual.
   *
   * @param string[] $roles
   *   Ids de rol.
   *
   * @return \Drupal\user\Entity\User
   *   El usuario.
   */
  protected function usuario(array $roles): User {
    $usuario = User::create(['name' => 'u' . random_int(1000, 999999), 'status' => 1, 'roles' => $roles]);
    $usuario->save();
    return $usuario;
  }

  /**
   * Los elementos del formulario son los del modelo de contenido.
   */
  public function testElementos(): void {
    $webform = Webform::load('participa');
    $this->assertNotNull($webform);
    $elementos = $webform->getElementsDecoded();
    $this->assertSame([
      'proceso',
      'elemento',
      'estamento',
      'nombre',
      'texto',
      'consentimiento',
      'estado_gestion',
      'respuesta',
      'actions',
    ], array_keys($elementos));
    // Obligatorios: estamento, texto y consentimiento; el resto es opcional.
    foreach (['estamento', 'texto', 'consentimiento'] as $clave) {
      $this->assertTrue($elementos[$clave]['#required'], $clave);
    }
    foreach (['proceso', 'elemento', 'nombre'] as $clave) {
      $this->assertArrayNotHasKey('#required', $elementos[$clave], $clave);
    }
    // Límites de longitud del diccionario de datos.
    $this->assertSame(1000, $elementos['texto']['#maxlength']);
    $this->assertSame(120, $elementos['nombre']['#maxlength']);
    $this->assertSame('estamento', $elementos['estamento']['#vocabulary']);
    $this->assertSame('elemento_modelo', $elementos['elemento']['#vocabulary']);
    $this->assertSame(
      ['recibida', 'en_revision', 'respondida', 'descartada'],
      array_keys($elementos['estado_gestion']['#options']),
    );
    // Los resultados se guardan y el formulario está abierto.
    $this->assertSame('open', $webform->get('status'));
    $this->assertFalse($webform->getSetting('results_disabled'));
  }

  /**
   * Anónimos y autenticados envían; solo el gestor ve y edita los envíos.
   */
  public function testAccesoAlFormulario(): void {
    $webform = Webform::load('participa');
    $anonimo = User::getAnonymousUser();
    $this->assertTrue($webform->access('submission_create', $anonimo));
    $autenticado = $this->usuario([]);
    $this->assertTrue($webform->access('submission_create', $autenticado));

    $gestor = $this->usuario(['gestor_participacion']);
    $this->assertTrue($webform->access('submission_view_any', $gestor));
    $this->assertTrue($webform->access('submission_update_any', $gestor));
    // Nadie borra envíos salvo un administrador del webform.
    $this->assertFalse($webform->access('submission_delete_any', $gestor));
    $this->assertFalse($webform->access('submission_purge_any', $gestor));
    // El gestor no administra el formulario.
    $this->assertFalse($webform->access('update', $gestor));
    $this->assertFalse($webform->access('delete', $gestor));

    foreach ([$anonimo, $autenticado, $this->usuario(['acreditacion'])] as $otro) {
      $this->assertFalse($webform->access('submission_view_any', $otro));
      $this->assertFalse($webform->access('submission_update_any', $otro));
      $this->assertFalse($webform->access('update', $otro));
    }
  }

  /**
   * El estado y la respuesta no se piden al enviar; los gestiona el gestor.
   */
  public function testCamposDeGestion(): void {
    $webform = Webform::load('participa');
    $gestor_participacion = $this->usuario(['gestor_participacion']);
    $manejador = $this->container->get('plugin.manager.webform.element');
    foreach (['estado_gestion', 'respuesta'] as $clave) {
      $elemento = $webform->getElement($clave);
      $this->assertIsArray($elemento);
      // El plugin necesita saber a qué formulario pertenece el elemento.
      $instancia = $manejador->getElementInstance($elemento);
      $instancia->setWebform($webform);
      foreach ([User::getAnonymousUser(), $this->usuario([])] as $otro) {
        $this->assertFalse($instancia->checkAccessRules('create', $elemento, $otro), $clave);
        $this->assertFalse($instancia->checkAccessRules('update', $elemento, $otro), $clave);
        $this->assertFalse($instancia->checkAccessRules('view', $elemento, $otro), $clave);
      }
      $this->assertFalse($instancia->checkAccessRules('create', $elemento, $gestor_participacion), $clave);
      $this->assertTrue($instancia->checkAccessRules('update', $elemento, $gestor_participacion), $clave);
      $this->assertTrue($instancia->checkAccessRules('view', $elemento, $gestor_participacion), $clave);
    }
  }

  /**
   * Los permisos exactos de los dos roles nuevos y su falta de poder de admin.
   */
  public function testRoles(): void {
    $gestor = $this->archivo('user.role.gestor_participacion.yml');
    $this->assertSame(['view the administration theme'], $gestor['permissions']);
    $this->assertFalse($gestor['is_admin']);

    $editor = $this->archivo('user.role.editor_documentos.yml');
    $this->assertFalse($editor['is_admin']);
    $this->assertSame([
      'access media overview',
      'change publication status on acreditacion content',
      'create documento media',
      'delete own documento media',
      'edit any documento media',
      'view all media revisions',
      'view own unpublished media',
      'view the administration theme',
    ], $editor['permissions']);
    foreach ([$gestor, $editor] as $rol) {
      foreach ($rol['permissions'] as $permiso) {
        $this->assertStringNotContainsString('administer', $permiso);
        $this->assertStringNotContainsString('bypass', $permiso);
      }
    }
    // El editor de documentos no toca nodos ni envíos del webform.
    foreach ($editor['permissions'] as $permiso) {
      $this->assertStringNotContainsString('content', str_replace('acreditacion content', '', $permiso));
      $this->assertStringNotContainsString('webform', $permiso);
    }
  }

  /**
   * La página entrega el tema `unillanos_participa` con el webform dentro.
   */
  public function testPagina(): void {
    $pagina = (new ParticipaController())->participa();
    $this->assertSame('unillanos_participa', $pagina['#theme']);
    $this->assertSame('webform', $pagina['#formulario']['#type']);
    $this->assertSame('participa', $pagina['#formulario']['#webform']);
    $ruta = $this->container->get('router.route_provider')->getRouteByName('unillanos_autoeval.participa');
    $this->assertSame('/participa', $ruta->getPath());
    $this->assertSame('access content', $ruta->getRequirement('_permission'));
  }

}
