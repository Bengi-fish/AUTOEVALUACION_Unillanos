<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\unillanos_autoeval\Hook\UnillanosAutoevalHooks;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\Form\UserLoginForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Prueba la ruta propia de acceso (/acceso-acreditacion).
 *
 * Muestra el formulario de inicio de sesión de Drupal solo a anónimos, redirige
 * a quien ya tiene sesión, lleva a la portada al entrar y no se enlaza desde
 * ninguna página pública. /user/login sigue funcionando.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class AccesoAcreditacionTest extends UnillanosKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'sdc',
    'block',
    'field',
    'text',
    'filter',
    'options',
    'node',
    'taxonomy',
    'file',
    'image',
    'media',
    'entity_reference_revisions',
    'paragraphs',
    'unillanos_autoeval',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'block', 'user']);
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('user', ['users_data']);
    // El primer usuario (uid 1) tiene todos los permisos: se reserva.
    User::create(['name' => 'superusuario', 'status' => 1])->save();
    // Los roles anónimo y autenticado (los trae la configuración del módulo
    // de usuarios) con los permisos que tienen en el sitio.
    $existentes = array_keys($this->container->get('user.permissions')->getPermissions());
    foreach (['anonymous', 'authenticated'] as $id) {
      $real = Yaml::decode((string) file_get_contents(DRUPAL_ROOT . "/../config/sync/user.role.$id.yml"));
      $rol = Role::load($id);
      foreach (array_intersect($real['permissions'], $existentes) as $permiso) {
        $rol->grantPermission($permiso);
      }
      $rol->save();
    }
    // Por defecto quien llega es anónimo (uid 0).
    $this->setCurrentUser(new AnonymousUserSession());
    $this->container->get('theme_installer')->install(['unillanos']);
    $this->config('system.theme')->set('default', 'unillanos')->save();
    $this->container->get('theme.manager')->setActiveTheme(
      $this->container->get('theme.initialization')->initTheme('unillanos')
    );
  }

  /**
   * Pide una ruta al núcleo.
   *
   * @param string $ruta
   *   Ruta con su consulta.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   La respuesta.
   */
  protected function pedir(string $ruta) {
    return $this->container->get('http_kernel')->handle(Request::create($ruta));
  }

  /**
   * Pone una ruta como la actual para construir el formulario en ella.
   *
   * @param string $ruta
   *   Nombre de la ruta.
   * @param array<string, string> $consulta
   *   Parámetros de la URL.
   */
  protected function enRuta(string $ruta, array $consulta = []): void {
    $solicitud = Request::create('/', 'GET', $consulta);
    $solicitud->setSession(new Session(new MockArraySessionStorage()));
    $solicitud->attributes->set(RouteObjectInterface::ROUTE_NAME, $ruta);
    $solicitud->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $this->container->get('router.route_provider')->getRouteByName($ruta));
    $solicitud->attributes->set('_raw_variables', new InputBag([]));
    $this->container->get('request_stack')->push($solicitud);
  }

  /**
   * El anónimo ve el formulario de inicio de sesión de Drupal.
   */
  public function testElAnonimoVeElFormularioDeDrupal(): void {
    $respuesta = $this->pedir('/acceso-acreditacion');
    $this->assertSame(200, $respuesta->getStatusCode());
    $html = (string) $respuesta->getContent();
    $this->assertStringContainsString('id="user-login-form"', $html);
    $this->assertStringContainsString('data-drupal-selector="user-login-form"', $html);
    $this->assertStringContainsString('name="name"', $html);
    $this->assertStringContainsString('name="pass"', $html);
    $this->assertStringContainsString('name="form_id" value="user_login_form"', $html);
    $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $html);
    // Es el formulario de Drupal, no una copia.
    $formulario = $this->container->get('form_builder')->getForm(UserLoginForm::class);
    $this->assertSame('user_login_form', $formulario['#form_id']);
  }

  /**
   * Quien ya tiene sesión va a la portada y no ve el formulario.
   */
  public function testElAutenticadoVaHaciaLaPortada(): void {
    $this->setCurrentUser($this->createUser());
    $respuesta = $this->pedir('/acceso-acreditacion');
    $this->assertSame(302, $respuesta->getStatusCode());
    $this->assertSame(Url::fromRoute('<front>')->toString(), $respuesta->headers->get('Location'));
    $this->assertStringNotContainsString('user-login-form', (string) $respuesta->getContent());
  }

  /**
   * La ruta /user/login sigue funcionando como respaldo.
   */
  public function testUserLoginSigueFuncionando(): void {
    $respuesta = $this->pedir('/user/login');
    $this->assertSame(200, $respuesta->getStatusCode());
    $this->assertStringContainsString('id="user-login-form"', (string) $respuesta->getContent());
  }

  /**
   * Ninguna página pública enlaza a una de las dos rutas de acceso.
   */
  public function testNingunEnlacePublicoAlAcceso(): void {
    foreach (['/inicio', '/programas', '/documentos'] as $ruta) {
      $html = (string) $this->pedir($ruta)->getContent();
      $this->assertStringNotContainsString('acceso-acreditacion', $html, $ruta);
      $this->assertStringNotContainsString('user/login', $html, $ruta);
      $this->assertStringNotContainsString('Ingresar', $html, $ruta);
    }
    // Ningún enlace de menú definido apunta a ellas.
    foreach ($this->container->get('plugin.manager.menu.link')->getDefinitions() as $id => $definicion) {
      if (($definicion['menu_name'] ?? '') === 'main' || ($definicion['menu_name'] ?? '') === 'footer') {
        $this->assertNotContains($definicion['route_name'] ?? '', ['unillanos_autoeval.acceso', 'user.login'], $id);
      }
    }
    // La ruta no figura en el mapa del sitio ni en los menús públicos.
    $this->assertStringNotContainsString('acceso', (string) json_encode($this->config('unillanos_autoeval.micrositio')->get()));
  }

  /**
   * Tras iniciar sesión en la ruta propia se va a la portada.
   */
  public function testTrasEntrarVaHaciaLaPortada(): void {
    $usuario = User::create(['name' => 'persona_acreditacion', 'pass' => 'clave-de-prueba', 'status' => 1]);
    $usuario->save();
    $this->enRuta('unillanos_autoeval.acceso');
    $formulario = $this->container->get('form_builder')->getForm(UserLoginForm::class);
    $this->assertContains([UnillanosAutoevalHooks::class, 'alPortada'], $formulario['#submit']);
    $this->container->get('request_stack')->pop();

    $this->enRuta('unillanos_autoeval.acceso');
    $estado = new FormState();
    $estado->setValues(['name' => 'persona_acreditacion', 'pass' => 'clave-de-prueba']);
    $this->container->get('form_builder')->submitForm(UserLoginForm::class, $estado);
    $this->assertSame([], $estado->getErrors());
    $this->assertSame((int) $usuario->id(), (int) $this->container->get('current_user')->id());
    // En un envío programático getRedirect() siempre es FALSE: se prueba el
    // manejador que el formulario lleva en esta ruta.
    $manejador = new FormState();
    $formulario = [];
    UnillanosAutoevalHooks::alPortada($formulario, $manejador);
    $redireccion = $manejador->getRedirect();
    $this->assertInstanceOf(Url::class, $redireccion);
    $this->assertSame('<front>', $redireccion->getRouteName());
  }

  /**
   * La ruta /user/login y un destino explícito no cambian su comportamiento.
   */
  public function testElManejadorSoloVaEnLaRutaPropiaSinDestino(): void {
    $this->enRuta('user.login');
    $formulario = $this->container->get('form_builder')->getForm(UserLoginForm::class);
    $this->assertNotContains([UnillanosAutoevalHooks::class, 'alPortada'], $formulario['#submit']);
    $this->container->get('request_stack')->pop();

    $this->enRuta('unillanos_autoeval.acceso', ['destination' => '/programas']);
    $formulario = $this->container->get('form_builder')->getForm(UserLoginForm::class);
    $this->assertNotContains([UnillanosAutoevalHooks::class, 'alPortada'], $formulario['#submit']);
  }

  /**
   * La protección contra intentos repetidos del formulario de Drupal aplica.
   */
  public function testLaProteccionContraIntentosRepetidosAplica(): void {
    $usuario = User::create(['name' => 'persona_flood', 'pass' => 'clave-correcta', 'status' => 1]);
    $usuario->save();
    $this->enRuta('unillanos_autoeval.acceso');
    $limite = (int) $this->config('user.flood')->get('user_limit');
    $this->assertGreaterThan(0, $limite);
    for ($i = 0; $i < $limite; $i++) {
      $estado = new FormState();
      $estado->setValues(['name' => 'persona_flood', 'pass' => 'incorrecta-' . $i]);
      $this->container->get('form_builder')->submitForm(UserLoginForm::class, $estado);
      $this->assertNotEmpty($estado->getErrors(), 'intento ' . $i);
    }
    // Superado el límite, ni la contraseña correcta entra: Drupal responde con
    // la página de bloqueo (403) y no inicia la sesión.
    $estado = new FormState();
    $estado->setValues(['name' => 'persona_flood', 'pass' => 'clave-correcta']);
    $this->container->get('form_builder')->submitForm(UserLoginForm::class, $estado);
    $respuesta = $estado->getResponse();
    $this->assertNotNull($respuesta);
    $this->assertSame(403, $respuesta->getStatusCode());
    $this->assertStringContainsString('temporarily blocked', (string) $respuesta->getContent());
    $this->assertSame(0, (int) $this->container->get('current_user')->id());
  }

}
