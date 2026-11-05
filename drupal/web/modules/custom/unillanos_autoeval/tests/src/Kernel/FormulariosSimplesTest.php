<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\unillanos_autoeval\Controller\AcreditacionController;
use Drupal\unillanos_autoeval\Controller\AcreditacionFormulariosController;
use Drupal\unillanos_autoeval\Hook\UnillanosAutoevalHooks;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Prueba los formularios simples de Acreditación (ficha y documentos).
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class FormulariosSimplesTest extends UnillanosKernelTestBase {

  /**
   * Campos de la ficha simple, en el orden en que se muestran.
   */
  const CAMPOS_FICHA = [
    'title',
    'field_facultad',
    'field_nivel',
    'field_modalidad',
    'field_sede',
    'field_director',
    'field_codigo_snies',
    'field_resolucion_registro',
    'field_registro_hasta',
    'field_resolucion_acreditacion',
    'field_acreditacion_hasta',
    'field_imagen',
    'status',
  ];

  /**
   * Campos del documento simple, en el orden en que se muestran.
   */
  const CAMPOS_DOCUMENTO = [
    'name',
    'field_media_file',
    'field_categoria',
    'field_fecha_publicacion',
    'field_version',
    'field_proceso',
    'field_lineamiento',
    'status',
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
    'datetime',
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
    Vocabulary::create(['vid' => 'facultad', 'name' => 'facultad'])->save();
    // Campos de la ficha.
    $this->crearCampo('node', 'programa', 'field_facultad', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_sede', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_nivel', 'list_string', [
      'allowed_values' => ['pregrado' => 'Pregrado', 'maestria' => 'Maestría'],
    ]);
    $this->crearCampo('node', 'programa', 'field_modalidad', 'list_string', [
      'allowed_values' => ['presencial' => 'Presencial', 'virtual' => 'Virtual'],
    ]);
    foreach (['field_director', 'field_codigo_snies', 'field_resolucion_registro', 'field_resolucion_acreditacion'] as $campo) {
      $this->crearCampo('node', 'programa', $campo, 'string');
    }
    foreach (['field_registro_hasta', 'field_acreditacion_hasta'] as $campo) {
      $this->crearCampo('node', 'programa', $campo, 'datetime', ['datetime_type' => 'date']);
    }
    $this->crearCampo('node', 'programa', 'field_imagen', 'image');
    // En la prueba no se sube imagen: el texto alternativo no es obligatorio.
    $imagen = FieldConfig::loadByName('node', 'programa', 'field_imagen');
    if ($imagen instanceof FieldConfig) {
      $imagen->setSetting('alt_field_required', FALSE)->save();
    }
    // Medio «documento» y sus campos.
    $tipo = MediaType::create(['id' => 'documento', 'label' => 'Documento', 'source' => 'file']);
    $tipo->save();
    $campo = $tipo->getSource()->createSourceField($tipo);
    $almacen = $campo->getFieldStorageDefinition();
    if ($almacen instanceof FieldStorageConfig) {
      $almacen->save();
    }
    $campo->save();
    $tipo->set('source_configuration', ['source_field' => $campo->getName()])->save();
    // El archivo es obligatorio en el sitio; aquí no se sube uno por prueba.
    if ($campo instanceof FieldConfig) {
      $campo->setRequired(FALSE)->save();
    }
    $this->crearCampo('media', 'documento', 'field_categoria', 'list_string', [
      'allowed_values' => ['informe' => 'Informe', 'normatividad' => 'Normatividad'],
    ]);
    $this->crearCampo('media', 'documento', 'field_fecha_publicacion', 'datetime', ['datetime_type' => 'date']);
    $this->crearCampo('media', 'documento', 'field_version', 'string');
    $this->crearCampo('media', 'documento', 'field_proceso', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('media', 'documento', 'field_lineamiento', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('media', 'documento', 'field_enlace', 'string');
    // Los modos y las pantallas de formulario simples: los de la configuración.
    // El módulo instala solo el modo de la ficha (config/optional); el del
    // medio se crea aquí desde el mismo archivo si el módulo no lo instaló.
    $this->assertNotNull(EntityFormMode::load('node.acreditacion'), 'modo node.acreditacion instalado por el módulo');
    if (!EntityFormMode::load('media.acreditacion')) {
      $modo = $this->configuracion('core.entity_form_mode.media.acreditacion');
      unset($modo['uuid']);
      EntityFormMode::create($modo)->save();
    }
    foreach (['node.programa', 'media.documento'] as $pantalla) {
      $datos = $this->configuracion("core.entity_form_display.$pantalla.acreditacion");
      unset($datos['uuid'], $datos['dependencies']);
      EntityFormDisplay::create($datos)->save();
    }
    // Las pantallas completas (con los campos administrativos).
    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'programa',
      'mode' => 'default',
      'status' => TRUE,
    ])
      ->setComponent('title', ['type' => 'string_textfield', 'weight' => 0])
      ->setComponent('field_clave', ['type' => 'string_textfield', 'weight' => 1])
      ->setComponent('status', ['type' => 'boolean_checkbox', 'weight' => 100])
      ->save();
    EntityFormDisplay::create([
      'targetEntityType' => 'media',
      'bundle' => 'documento',
      'mode' => 'default',
      'status' => TRUE,
    ])
      ->setComponent('name', ['type' => 'string_textfield', 'weight' => 0])
      ->setComponent('uid', ['type' => 'entity_reference_autocomplete', 'weight' => 5])
      ->save();
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
  }

  /**
   * Lee un archivo de configuración del sitio o del módulo.
   *
   * @param string $nombre
   *   Nombre de la configuración (sin .yml).
   * @param string $donde
   *   Cadena `sync` (config/sync) o `modulo` (config/optional del módulo).
   *
   * @return array<string, mixed>
   *   El contenido.
   */
  protected function configuracion(string $nombre, string $donde = 'sync'): array {
    $ruta = $donde === 'sync'
      ? DRUPAL_ROOT . "/../config/sync/$nombre.yml"
      : $this->container->get('extension.list.module')->getPath('unillanos_autoeval') . "/config/optional/$nombre.yml";
    $this->assertFileExists($ruta);
    return Yaml::decode((string) file_get_contents($ruta));
  }

  /**
   * Crea un usuario con un rol propio con los permisos dados.
   *
   * @param string[] $permisos
   *   Permisos del rol.
   */
  protected function usuarioConPermisos(array $permisos): User {
    static $n = 0;
    $id = 'rol_simple_' . ++$n;
    Role::create(['id' => $id, 'label' => $id, 'permissions' => $permisos])->save();
    $usuario = User::create(['name' => 'usuario' . $n, 'status' => 1, 'roles' => [$id]]);
    $usuario->save();
    return $usuario;
  }

  /**
   * Usuario con el rol Acreditación (menos «access navigation»).
   */
  protected function acreditacion(): User {
    $yaml = $this->configuracion('user.role.acreditacion', 'modulo');
    return $this->usuarioConPermisos(array_values(array_diff($yaml['permissions'], ['access navigation'])));
  }

  /**
   * Un programa con valores en todos los campos de la ficha.
   */
  protected function programa(): Node {
    $facultad = Term::create(['vid' => 'facultad', 'name' => 'Facultad de Pruebas']);
    $facultad->save();
    return $this->crearNodo('programa', [
      'title' => 'Programa de prueba',
      'field_clave' => 'programa-de-prueba',
      'field_facultad' => $facultad->id(),
      'field_nivel' => 'pregrado',
      'field_director' => 'Persona Uno',
      'field_codigo_snies' => '1234',
    ]);
  }

  /**
   * Un documento publicado.
   */
  protected function documento(int $proceso = 0): Media {
    $medio = Media::create([
      'bundle' => 'documento',
      'name' => 'Informe de prueba',
      'status' => 1,
      'field_version' => '1',
      'field_proceso' => $proceso ?: NULL,
    ]);
    $medio->save();
    return $medio;
  }

  /**
   * Comprueba que un campo no se muestra: no tiene ningún control visible.
   *
   * Algunos campos (como el idioma) quedan en el formulario como un
   * contenedor vacío, un valor oculto o con el acceso denegado.
   *
   * @param array<mixed> $formulario
   *   El formulario.
   * @param string $campo
   *   El campo.
   */
  protected function assertOculto(array $formulario, string $campo): void {
    $this->assertFalse(isset($formulario[$campo]) && $this->hayControles($formulario[$campo]), "$campo no debe mostrarse");
  }

  /**
   * Indica si un elemento del formulario tiene algún control visible.
   *
   * @param array<mixed> $elemento
   *   El elemento y sus hijos.
   */
  protected function hayControles(array $elemento): bool {
    if (($elemento['#access'] ?? TRUE) === FALSE) {
      return FALSE;
    }
    $tipo = $elemento['#type'] ?? '';
    // El selector de idioma lo añade el núcleo a todo formulario de contenido y
    // se oculta solo en un sitio de un único idioma (se comprueba en el
    // navegador).
    $ocultos = ['container', 'value', 'hidden', 'token', 'details', 'fieldset', 'actions', 'language_select'];
    if ($tipo !== '' && !in_array($tipo, $ocultos, TRUE)) {
      return TRUE;
    }
    foreach ($elemento as $clave => $hijo) {
      if (is_array($hijo) && !str_starts_with((string) $clave, '#') && $this->hayControles($hijo)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * A dónde envía el manejador que corre al guardar un formulario simple.
   *
   * @return \Drupal\Core\Url|null
   *   El destino, o NULL si el usuario actual no puede abrir el panel.
   */
  protected function destinoAlGuardar(): ?Url {
    $formulario = [];
    $estado = new FormState();
    UnillanosAutoevalHooks::volverAlPanel($formulario, $estado);
    $destino = $estado->getRedirect();
    return $destino instanceof Url ? $destino : NULL;
  }

  /**
   * Devuelve el formulario simple de la ficha para el usuario actual.
   *
   * @return array<string, mixed>
   *   El formulario.
   */
  protected function formularioDeLaFicha(Node $programa): array {
    $controlador = AcreditacionFormulariosController::create($this->container);
    return $controlador->ficha($programa)['formulario'];
  }

  /**
   * Envía el formulario simple de un contenido con unos valores.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entidad
   *   El contenido.
   * @param array<string, mixed> $valores
   *   Valores del formulario.
   */
  protected function enviar($entidad, array $valores): FormStateInterface {
    $estado = new FormState();
    $estado->setValues($valores + ['op' => 'Save']);
    $objeto = $this->container->get('entity_type.manager')
      ->getFormObject($entidad->getEntityTypeId(), 'acreditacion')
      ->setEntity($entidad);
    $this->container->get('form_builder')->submitForm($objeto, $estado);
    return $estado;
  }

  /**
   * Modos y pantallas simples: contenido exacto, sin lo administrativo.
   */
  public function testConfiguracionExactaDelModo(): void {
    $nombres = [
      'core.entity_form_mode.node.acreditacion',
      'core.entity_form_mode.media.acreditacion',
      'core.entity_form_display.node.programa.acreditacion',
      'core.entity_form_display.media.documento.acreditacion',
    ];
    foreach ($nombres as $nombre) {
      $sync = $this->configuracion($nombre);
      $modulo = $this->configuracion($nombre, 'modulo');
      unset($sync['uuid']);
      $this->assertEquals($sync, $modulo, "$nombre: el módulo y el sitio declaran lo mismo");
    }
    $ficha = $this->configuracion('core.entity_form_display.node.programa.acreditacion');
    $documento = $this->configuracion('core.entity_form_display.media.documento.acreditacion');
    $ordenados = function (array $pantalla): array {
      $pesos = array_map(fn($c) => $c['weight'], $pantalla['content']);
      asort($pesos);
      return array_keys($pesos);
    };
    $this->assertSame(self::CAMPOS_FICHA, $ordenados($ficha));
    $this->assertSame(self::CAMPOS_DOCUMENTO, $ordenados($documento));
    $this->assertSame('acreditacion', $ficha['mode']);
    $this->assertSame('acreditacion', $documento['mode']);
    // Lo administrativo no está en la pantalla simple.
    foreach (['field_clave', 'uid', 'created', 'path', 'langcode', 'promote', 'sticky'] as $campo) {
      $this->assertArrayNotHasKey($campo, $ficha['content'], $campo);
    }
    foreach (['uid', 'created', 'path', 'langcode', 'field_enlace'] as $campo) {
      $this->assertArrayNotHasKey($campo, $documento['content'], $campo);
    }
    // Las pantallas completas no se tocaron: siguen con sus campos
    // administrativos.
    $completa = $this->configuracion('core.entity_form_display.node.programa.default');
    foreach (['field_clave', 'uid', 'created', 'path', 'langcode', 'status'] as $campo) {
      $this->assertArrayHasKey($campo, $completa['content'], $campo);
    }
    $completa_documento = $this->configuracion('core.entity_form_display.media.documento.default');
    foreach (['uid', 'created', 'path', 'langcode', 'field_enlace'] as $campo) {
      $this->assertArrayHasKey($campo, $completa_documento['content'], $campo);
    }
  }

  /**
   * El rol ve la ficha simple: los campos de la ficha y nada administrativo.
   */
  public function testElRolVeLaFichaSimple(): void {
    $programa = $this->programa();
    $this->container->get('current_user')->setAccount($this->acreditacion());
    $formulario = $this->formularioDeLaFicha($programa);
    foreach (self::CAMPOS_FICHA as $campo) {
      $this->assertArrayHasKey($campo, $formulario, $campo);
      $this->assertNotFalse($formulario[$campo]['#access'] ?? TRUE, $campo);
    }
    foreach (['field_clave', 'uid', 'created', 'path', 'langcode', 'promote', 'sticky'] as $campo) {
      $this->assertOculto($formulario, $campo);
    }
    $this->assertArrayHasKey('submit', $formulario['actions']);
    // El botón de borrar existe pero sin acceso: el rol no borra.
    $this->assertOculto($formulario['actions'], 'delete');
    // Los campos salen en el orden de la pantalla.
    $presentes = array_values(array_filter(self::CAMPOS_FICHA, fn($c) => isset($formulario[$c])));
    $pesos = array_map(fn($c) => $formulario[$c]['#weight'] ?? 0, $presentes);
    $ordenados = $pesos;
    sort($ordenados);
    $this->assertSame($ordenados, $pesos);
  }

  /**
   * El rol guarda la ficha simple: cambia lo editado, crea revisión y vuelve.
   */
  public function testElRolGuardaLaFichaSimple(): void {
    $programa = $this->programa();
    $revision_antes = $programa->getRevisionId();
    $this->container->get('current_user')->setAccount($this->acreditacion());
    $estado = $this->enviar($programa, [
      'title' => [['value' => 'Programa de prueba (nuevo nombre)']],
      'field_director' => [['value' => 'Persona Dos']],
      'field_nivel' => 'maestria',
      'field_facultad' => $programa->get('field_facultad')->target_id,
      'field_registro_hasta' => [['value' => ['date' => '2030-12-31']]],
      'status' => ['value' => 1],
    ]);
    $this->assertSame([], array_map('strval', $estado->getErrors()));
    $guardado = Node::load($programa->id());
    $this->assertSame('Programa de prueba (nuevo nombre)', $guardado->label());
    $this->assertSame('Persona Dos', $guardado->get('field_director')->value);
    $this->assertSame('maestria', $guardado->get('field_nivel')->value);
    $this->assertSame('2030-12-31', $guardado->get('field_registro_hasta')->value);
    // La clave (que el formulario simple no muestra) no cambia.
    $this->assertSame('programa-de-prueba', $guardado->get('field_clave')->value);
    // Crea una revisión nueva.
    $this->assertGreaterThan($revision_antes, $guardado->getRevisionId());
    // Vuelve al panel (la persona puede abrirlo). FormState::getRedirect()
    // siempre es FALSE en un envío programático: se prueba el manejador y que
    // el botón de guardar lo lleve.
    $this->assertSame('unillanos_autoeval.acreditacion', $this->destinoAlGuardar()?->getRouteName());
    $formulario = $this->formularioDeLaFicha($guardado);
    $this->assertContains([UnillanosAutoevalHooks::class, 'volverAlPanel'], $formulario['actions']['submit']['#submit']);
  }

  /**
   * Despublicar y volver a publicar desde el formulario simple.
   */
  public function testSeDespublicaMasTardeSePublica(): void {
    $programa = $this->programa();
    $this->container->get('current_user')->setAccount($this->acreditacion());
    foreach ([FALSE, TRUE] as $publicado) {
      $this->enviar(Node::load($programa->id()), [
        'title' => [['value' => 'Programa de prueba']],
        'field_nivel' => 'pregrado',
        'status' => ['value' => $publicado ? 1 : NULL],
      ]);
      $this->assertSame($publicado, Node::load($programa->id())->isPublished());
    }
  }

  /**
   * Quien edita pero no puede abrir el panel no es enviado a una página 403.
   */
  public function testSinPanelNoVuelveAlPanel(): void {
    $programa = $this->programa();
    $editor = $this->usuarioConPermisos(['edit any programa content', 'create programa content']);
    $this->container->get('current_user')->setAccount($editor);
    $this->enviar($programa, ['title' => [['value' => 'Otro nombre']], 'field_nivel' => 'pregrado']);
    $this->assertNull($this->destinoAlGuardar());
  }

  /**
   * El formulario simple del documento: campos propios y nada administrativo.
   */
  public function testElRolVeGuardaElDocumentoSimple(): void {
    $medio = $this->documento();
    $this->container->get('current_user')->setAccount($this->acreditacion());
    $controlador = AcreditacionFormulariosController::create($this->container);
    $formulario = $controlador->documento($medio)['formulario'];
    foreach (self::CAMPOS_DOCUMENTO as $campo) {
      $this->assertArrayHasKey($campo, $formulario, $campo);
    }
    foreach (['uid', 'created', 'path', 'langcode', 'field_enlace'] as $campo) {
      $this->assertOculto($formulario, $campo);
    }
    $estado = $this->enviar($medio, [
      'name' => [['value' => 'Informe de prueba 2']],
      'field_version' => [['value' => '2']],
      'field_categoria' => 'informe',
      'status' => ['value' => NULL],
    ]);
    $this->assertSame([], array_map('strval', $estado->getErrors()));
    $guardado = Media::load($medio->id());
    $this->assertSame('Informe de prueba 2', $guardado->label());
    $this->assertSame('2', $guardado->get('field_version')->value);
    $this->assertFalse($guardado->isPublished());
    $this->assertSame('unillanos_autoeval.acreditacion', $this->destinoAlGuardar()?->getRouteName());
  }

  /**
   * Subir un documento nuevo: el formulario trae el proceso ya elegido.
   */
  public function testElDocumentoNuevoTraeElProceso(): void {
    $programa = $this->programa();
    $proceso = $this->crearNodo('proceso', ['title' => 'Proceso', 'field_programa' => $programa->id()]);
    $this->container->get('current_user')->setAccount($this->acreditacion());
    $peticion = Request::create('/admin/acreditacion/documento/nuevo', 'GET', ['proceso' => (string) $proceso->id()]);
    $peticion->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($peticion);
    $controlador = AcreditacionFormulariosController::create($this->container);
    $formulario = $controlador->documentoNuevo()['formulario'];
    $this->container->get('request_stack')->pop();
    $elegido = $formulario['field_proceso']['widget'][0]['target_id']['#default_value'] ?? NULL;
    $this->assertNotNull($elegido);
    $elegido = is_array($elegido) ? reset($elegido) : $elegido;
    $this->assertSame((string) $proceso->id(), (string) $elegido->id());
    $this->assertOculto($formulario, 'uid');
  }

  /**
   * Sin permiso de editar: 403. Con el de editar: sí (mismo control de acceso).
   */
  public function testSinPermisoRecibe403(): void {
    $programa = $this->programa();
    $medio = $this->documento();
    $acceso = $this->container->get('access_manager');
    $rutas = [
      ['unillanos_autoeval.acreditacion_ficha', ['node' => $programa->id()]],
      ['unillanos_autoeval.acreditacion_documento', ['media' => $medio->id()]],
      ['unillanos_autoeval.acreditacion_documento_nuevo', []],
    ];
    $sin_rol = User::create(['name' => 'sinrol', 'status' => 1]);
    $sin_rol->save();
    $solo_panel = $this->usuarioConPermisos(['access acreditacion panel']);
    $solo_ver = $this->usuarioConPermisos(['access content overview', 'view the administration theme']);
    $rol = $this->acreditacion();
    foreach ($rutas as [$ruta, $parametros]) {
      $this->assertFalse($acceso->checkNamedRoute($ruta, $parametros, User::getAnonymousUser()), "$ruta: anónimo");
      $this->assertFalse($acceso->checkNamedRoute($ruta, $parametros, $sin_rol), "$ruta: autenticado sin rol");
      $this->assertFalse($acceso->checkNamedRoute($ruta, $parametros, $solo_panel), "$ruta: solo el panel");
      $this->assertFalse($acceso->checkNamedRoute($ruta, $parametros, $solo_ver), "$ruta: solo ver");
      $this->assertTrue($acceso->checkNamedRoute($ruta, $parametros, $rol), "$ruta: rol Acreditación");
    }
    // Es el mismo control que el de editar: quien solo puede editar programas
    // abre la ficha, pero no los documentos.
    $editor = $this->usuarioConPermisos(['edit any programa content']);
    $this->assertTrue($acceso->checkNamedRoute('unillanos_autoeval.acreditacion_ficha', ['node' => $programa->id()], $editor));
    $this->assertFalse($acceso->checkNamedRoute('unillanos_autoeval.acreditacion_documento', ['media' => $medio->id()], $editor));
  }

  /**
   * Un contenido de otro tipo no se abre con estas pantallas (404).
   */
  public function testOtroTipoDeContenidoDa404(): void {
    $proceso = $this->crearNodo('proceso', ['title' => 'Proceso']);
    $this->container->get('current_user')->setAccount($this->acreditacion());
    $controlador = AcreditacionFormulariosController::create($this->container);
    $this->expectException(NotFoundHttpException::class);
    $controlador->ficha($proceso);
  }

  /**
   * Un medio que no es documento tampoco (404).
   */
  public function testOtroTipoDeMedioDa404(): void {
    MediaType::create(['id' => 'otro', 'label' => 'Otro', 'source' => 'file'])->save();
    $otro = Media::create(['bundle' => 'otro', 'name' => 'Otro']);
    $this->container->get('current_user')->setAccount($this->acreditacion());
    $controlador = AcreditacionFormulariosController::create($this->container);
    $this->expectException(NotFoundHttpException::class);
    $controlador->documento($otro);
  }

  /**
   * El formulario completo no cambia: conserva sus campos y sin las ayudas.
   */
  public function testElFormularioCompletoNoCambia(): void {
    $programa = $this->programa();
    $administrador = $this->usuarioConPermisos(['administer nodes', 'edit any programa content', 'access content']);
    $this->container->get('current_user')->setAccount($administrador);
    $completo = $this->container->get('entity.form_builder')->getForm($programa, 'default');
    $this->assertArrayHasKey('field_clave', $completo);
    $this->assertArrayHasKey('status', $completo);
    $this->assertArrayNotHasKey('field_director', $completo, 'la pantalla completa de la prueba no lo incluye');
    $this->assertStringNotContainsString('Nombre completo del programa', serialize($completo['title']));
    // El simple, en cambio, lleva su ayuda.
    $simple = $this->formularioDeLaFicha($programa);
    $this->assertStringContainsString('Nombre completo del programa', serialize($simple['title']));
    // Y el administrador también abre el simple.
    $this->assertOculto($simple, 'field_clave');
    // El documento: la pantalla completa conserva «uid» y la simple no.
    $medio = $this->documento();
    $completo_documento = $this->container->get('entity.form_builder')->getForm($medio, 'default');
    $this->assertArrayHasKey('uid', $completo_documento);
  }

  /**
   * Ayudas en lenguaje sencillo: cada campo tiene la suya y sin tecnicismos.
   */
  public function testAyudasEnLenguajeSencillo(): void {
    $programa = $this->programa();
    $this->container->get('current_user')->setAccount($this->acreditacion());
    $ficha = $this->formularioDeLaFicha($programa);
    $documento = AcreditacionFormulariosController::create($this->container)->documento($this->documento())['formulario'];
    $prohibidas = [
      'entidad',
      'campo ',
      'nodo',
      'taxonom',
      'bundle',
      'api',
      'url',
      'html',
      'sql',
      'json',
      'uuid',
      'id de',
      'widget',
      'metadato',
    ];
    foreach ([[$ficha, self::CAMPOS_FICHA], [$documento, self::CAMPOS_DOCUMENTO]] as [$formulario, $campos]) {
      foreach ($campos as $campo) {
        $ayuda = $this->ayuda($formulario[$campo]);
        $this->assertNotSame('', $ayuda, "$campo debe tener una ayuda");
        $this->assertLessThan(200, mb_strlen($ayuda), "$campo: la ayuda debe ser breve");
        foreach ($prohibidas as $palabra) {
          $this->assertStringNotContainsStringIgnoringCase($palabra, $ayuda, "$campo: tecnicismo «{$palabra}»");
        }
      }
    }
  }

  /**
   * Busca el texto de ayuda de un campo del formulario.
   *
   * @param array<mixed> $elemento
   *   El campo.
   */
  protected function ayuda(array $elemento): string {
    foreach ($elemento as $clave => $valor) {
      if ($clave === '#description' && $valor !== NULL && (string) $valor !== '') {
        return (string) $valor;
      }
      if (is_array($valor) && !str_starts_with((string) $clave, '#')) {
        $ayuda = $this->ayuda($valor);
        if ($ayuda !== '') {
          return $ayuda;
        }
      }
    }
    return '';
  }

  /**
   * El panel enlaza a los formularios simples de la ficha y de los documentos.
   */
  public function testElPanelEnlazaFormulariosSimples(): void {
    $programa = $this->programa();
    $proceso = $this->crearNodo('proceso', ['title' => 'Proceso', 'field_programa' => $programa->id()]);
    $medio = $this->documento((int) $proceso->id());
    $this->container->get('current_user')->setAccount($this->acreditacion());
    $controlador = AcreditacionController::create($this->container);
    $construccion = $controlador->panel(Request::create('/admin/acreditacion'));
    $html = (string) $this->container->get('renderer')->renderInIsolation($construccion);
    preg_match_all('/<a href="([^"]+)"/', $html, $m);
    $enlaces = array_map('html_entity_decode', $m[1]);
    $this->assertContains('/admin/acreditacion/programa/' . $programa->id() . '/ficha', $enlaces);
    $this->assertContains('/admin/acreditacion/documento/' . $medio->id() . '/editar', $enlaces);
    $this->assertContains('/admin/acreditacion/documento/nuevo?proceso=' . $proceso->id(), $enlaces);
    $this->assertContains('/admin/acreditacion/documento/nuevo', $enlaces);
    // El resto de los accesos sigue como estaba.
    $this->assertContains('/node/' . $proceso->id() . '/edit', $enlaces);
    $this->assertContains('/node/add/valoracion?proceso=' . $proceso->id(), $enlaces);
    $this->assertStringContainsString('Editar el documento «Informe de prueba»', $html);
  }

  /**
   * Cada ruta de los formularios simples existe y es de administración.
   */
  public function testLasRutasUsanElControlDeEditar(): void {
    $rutas = $this->container->get('router.route_provider');
    $ficha = $rutas->getRouteByName('unillanos_autoeval.acreditacion_ficha');
    $this->assertSame('/admin/acreditacion/programa/{node}/ficha', $ficha->getPath());
    $this->assertSame('node.update', $ficha->getRequirement('_entity_access'));
    $documento = $rutas->getRouteByName('unillanos_autoeval.acreditacion_documento');
    $this->assertSame('media.update', $documento->getRequirement('_entity_access'));
    $nuevo = $rutas->getRouteByName('unillanos_autoeval.acreditacion_documento_nuevo');
    $this->assertSame('media:documento', $nuevo->getRequirement('_entity_create_access'));
    foreach ([$ficha, $documento, $nuevo] as $ruta) {
      $this->assertStringStartsWith('/admin/acreditacion/', $ruta->getPath());
      $this->assertNull($ruta->getRequirement('_permission'));
    }
  }

  /**
   * Los campos de FieldConfig usados existen (la prueba parte de lo real).
   */
  public function testLosCamposDeLaPruebaCoincidenConLaConfiguracion(): void {
    foreach (['node.programa' => self::CAMPOS_FICHA, 'media.documento' => self::CAMPOS_DOCUMENTO] as $clave => $campos) {
      [$entidad, $tipo] = explode('.', $clave);
      foreach ($campos as $campo) {
        if (str_starts_with($campo, 'field_')) {
          $this->assertNotNull(FieldConfig::loadByName($entidad, $tipo, $campo), "$clave.$campo");
        }
      }
    }
  }

}
