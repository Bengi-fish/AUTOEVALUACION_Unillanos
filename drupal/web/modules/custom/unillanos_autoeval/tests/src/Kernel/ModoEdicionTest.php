<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\MediaType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\unillanos_autoeval\Hook\UnillanosAutoevalHooks;
use Drupal\unillanos_autoeval\Service\EnlacesEdicion;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Prueba el modo edición: interruptor, lápices y «+» del sitio público.
 *
 * Solo lo ve quien tiene el permiso «access acreditacion panel»; cada enlace
 * solo se pinta si la persona tiene acceso real a su destino; las páginas de
 * anónimos no cambian en nada.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class ModoEdicionTest extends UnillanosKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'sdc',
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
    Vocabulary::create(['vid' => 'facultad', 'name' => 'facultad'])->save();
    $this->crearCampo('node', 'programa', 'field_facultad', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_sede', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_nivel', 'list_string', [
      'allowed_values' => ['pregrado' => 'Pregrado'],
    ]);
    $this->crearCampo('node', 'proceso', 'field_periodo_evaluado', 'string');
    $this->crearCampo('node', 'proceso', 'field_resultado', 'string');
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_tipo_elemento', 'string');
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_numero', 'integer');
    $this->crearCampo('node', 'hallazgo', 'field_tipo_hallazgo', 'string');
    $this->container->get('theme_installer')->install(['unillanos']);
    $this->config('system.theme')->set('default', 'unillanos')->save();
    $this->container->get('theme.manager')->setActiveTheme(
      $this->container->get('theme.initialization')->initTheme('unillanos')
    );
    $this->setCurrentUser(new AnonymousUserSession());
  }

  /**
   * Crea el rol Acreditación (de la configuración real) y un usuario con él.
   */
  protected function usuarioAcreditacion(): User {
    $real = Yaml::decode((string) file_get_contents(DRUPAL_ROOT . '/../config/sync/user.role.acreditacion.yml'));
    $permisos = array_values(array_diff($real['permissions'], ['access navigation']));
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
   * El HTML de una página.
   */
  protected function pagina(string $ruta): string {
    return (string) $this->container->get('http_kernel')->handle(Request::create($ruta))->getContent();
  }

  /**
   * El servicio de enlaces de edición.
   */
  protected function edicion(): EnlacesEdicion {
    return $this->container->get('unillanos_autoeval.enlaces_edicion');
  }

  /**
   * Datos de factores y plan de ejemplo para la página de un programa.
   *
   * @param int $valoracion
   *   Id de la valoración del primer factor.
   * @param int $meta
   *   Id de la meta de la primera fila del plan.
   * @param int $plan
   *   Id del plan.
   *
   * @return array{0: array<string, mixed>, 1: array<string, mixed>}
   *   Factores y plan.
   */
  protected function datosDePrograma(int $valoracion, int $meta, int $plan): array {
    $factores = [
      'proceso_id' => 49,
      'pestanas' => [
        [
          'key' => 'f01',
          'number' => '01',
          'name' => 'Proyecto',
          'label' => 'Factor 01',
          'factor_id' => 10,
          'valoracion_id' => $valoracion,
        ],
        [
          'key' => 'f02',
          'number' => '02',
          'name' => 'Estudiantes',
          'label' => 'Factor 02',
          'factor_id' => 11,
          'valoracion_id' => 0,
        ],
        ['key' => 'sin-factor', 'number' => '', 'name' => 'Sin factor asignado', 'label' => ''],
      ],
    ];
    $celdas = [['type' => 'text', 'value' => 'a'], ['type' => 'text', 'value' => 'b', 'eyebrow' => 'Meta 1']];
    $plan = [
      'plan_id' => $plan,
      'filas' => [
        ['meta_id' => $meta, 'cells' => $celdas],
        ['meta_id' => 0, 'cells' => []],
      ],
    ];
    return [$factores, $plan];
  }

  /**
   * Sin el permiso (anónimo o autenticado) no hay modo edición.
   */
  public function testSinPermisoNoHayModoEdicion(): void {
    $this->crearNodo('programa', ['title' => 'Programa uno', 'field_clave' => 'uno']);
    $edicion = $this->edicion();
    $this->assertFalse($edicion->activo());
    $this->assertNull($edicion->ficha(1, 'X'));
    $this->assertNull($edicion->programaNuevo());
    $this->assertNull($edicion->micrositio());
    $this->assertNull($edicion->cerrarSesion());
    [$factores, $plan] = $this->datosDePrograma(2, 3, 4);
    $resultado = $edicion->paraPrograma(1, 'X', $factores, $plan);
    $this->assertSame($factores, $resultado['factores']);
    $this->assertSame($plan, $resultado['plan']);
    $this->assertSame([], $resultado['edicion']);

    foreach ([new AnonymousUserSession(), $this->usuario(['authenticated'])] as $cuenta) {
      $this->setCurrentUser($cuenta);
      $html = $this->pagina('/programas');
      $this->assertStringNotContainsString('ua-edit', $html);
      $this->assertStringNotContainsString('data-ua-edicion', $html);
      $this->assertStringNotContainsString('Modo edición', $html);
      $this->assertStringNotContainsString('Cerrar sesión', $html);
    }
  }

  /**
   * Con permiso pero sin acceso a ninguna pantalla solo hay interruptor.
   */
  public function testElPermisoSinAccesoNoPintaLapices(): void {
    $programa = $this->crearNodo('programa', ['title' => 'Programa uno', 'field_clave' => 'uno']);
    $this->setCurrentUser($this->usuarioConPermisos(['access content', 'access acreditacion panel']));
    $edicion = $this->edicion();
    $this->assertTrue($edicion->activo());
    $this->assertNull($edicion->ficha((int) $programa->id(), 'Programa uno'));
    $this->assertNull($edicion->programaNuevo());
    $this->assertNull($edicion->micrositio());
    $this->assertNull($edicion->documentoNuevo(0));
    $html = $this->pagina('/programas');
    $this->assertStringContainsString('data-ua-edicion-toggle', $html);
    $this->assertStringContainsString('aria-pressed="false"', $html);
    $this->assertStringNotContainsString('data-ua-edit', $html);
    $this->assertStringNotContainsString('Añadir programa', $html);
  }

  /**
   * Acreditación ve el interruptor, «+ Añadir programa» y los lápices.
   */
  public function testAcreditacionVeElModoEdicionEnProgramas(): void {
    $uno = $this->crearNodo('programa', ['title' => 'Programa uno', 'field_clave' => 'uno']);
    $dos = $this->crearNodo('programa', ['title' => 'Programa dos', 'field_clave' => 'dos']);
    $this->setCurrentUser($this->usuarioAcreditacion());
    $respuesta = $this->container->get('http_kernel')->handle(Request::create('/programas'));
    $html = (string) $respuesta->getContent();
    // Barra: interruptor con aria-pressed y «Cerrar sesión».
    $this->assertMatchesRegularExpression('~<button class="ua-edicion-barra__boton" type="button" aria-pressed="false" data-ua-edicion-toggle>~', $html);
    $this->assertStringContainsString('Modo edición', $html);
    $this->assertMatchesRegularExpression('~<a class="ua-topbar__link" href="/user/logout/confirm">Cerrar sesión</a>~', $html);
    // «+ Añadir programa» arriba de la lista.
    $this->assertMatchesRegularExpression('~href="/node/add/programa\\?destination=/programas"~', $html);
    $this->assertStringContainsString('>Añadir programa</span>', $html);
    $this->assertLessThan(strpos($html, 'data-ua-prog-list'), strpos($html, 'Añadir programa'));
    // Lápiz de cada tarjeta, fuera del enlace de la tarjeta.
    foreach ([$uno, $dos] as $programa) {
      $this->assertStringContainsString('href="/admin/acreditacion/programa/' . $programa->id() . '/ficha?destination=/programas"', $html);
      $this->assertStringContainsString('>Editar la ficha de ' . $programa->label() . '</span>', $html);
    }
    $this->assertDoesNotMatchRegularExpression('~<a[^>]*ua-prog[^>]*>(?:(?!</a>).)*data-ua-edit~s', $html);
    // Los enlaces de edición del micrositio (cabecera y pie).
    $this->assertSame(2, substr_count($html, 'href="/admin/config/unillanos/micrositio?destination=/programas"'));
    // La página varía por permisos y por la URL (el destino).
    $this->assertInstanceOf(CacheableResponseInterface::class, $respuesta);
    $contextos = $respuesta->getCacheableMetadata()->getCacheContexts();
    $this->assertContains('user.permissions', $contextos);
    $this->assertContains('url.query_args', $contextos);
  }

  /**
   * Cada enlace de edición apunta a una pantalla con acceso.
   */
  public function testCadaEnlaceVaHaciaRutaConAcceso(): void {
    $programa = $this->crearNodo('programa', ['title' => 'Programa uno', 'field_clave' => 'uno']);
    $valoracion = $this->crearNodo('valoracion', ['title' => 'Valoración']);
    $plan = $this->crearNodo('plan_mejoramiento', ['title' => 'Plan']);
    $meta = $this->crearNodo('meta', ['title' => 'Meta']);
    $cuenta = $this->usuarioAcreditacion();
    $this->setCurrentUser($cuenta);
    [$factores, $filas] = $this->datosDePrograma((int) $valoracion->id(), (int) $meta->id(), (int) $plan->id());
    $resultado = $this->edicion()->paraPrograma((int) $programa->id(), 'Programa uno', $factores, $filas);
    $enlaces = array_values($resultado['edicion']);
    foreach ($resultado['factores']['pestanas'] as $pestana) {
      $enlaces = array_merge($enlaces, $pestana['edicion'] ?? []);
    }
    foreach ($resultado['plan']['filas'] as $fila) {
      $enlaces = array_merge($enlaces, $fila['edicion'] ?? []);
    }
    // Ficha, plan, documento, valoración editable, valoración nueva y meta.
    $this->assertCount(6, $enlaces);
    foreach ($enlaces as $enlace) {
      $this->assertStringStartsWith('/', $enlace['url']);
      $this->assertStringContainsString('destination=', $enlace['url']);
      $this->assertTrue(Url::fromUserInput($enlace['url'])->access($cuenta), $enlace['url']);
      $this->assertNotSame('', trim($enlace['title']));
    }
    $titulos = array_column($enlaces, 'title');
    $this->assertContains('Editar la ficha de Programa uno', $titulos);
    $this->assertContains('Editar el plan de mejoramiento', $titulos);
    $this->assertContains('Añadir un documento', $titulos);
    $this->assertContains('Editar la valoración del Factor 01: Proyecto', $titulos);
    $this->assertContains('Añadir la valoración del Factor 02: Estudiantes', $titulos);
    $this->assertContains('Editar Meta 1 del plan de mejoramiento', $titulos);
    // Los «+» precargan el proceso y el factor; el documento, el proceso.
    $urls = array_column($enlaces, 'url');
    $this->assertCount(1, array_filter($urls, fn($u) => str_contains($u, '/node/add/valoracion') && str_contains($u, 'proceso=49') && str_contains($u, 'elemento=11')));
    $this->assertCount(1, array_filter($urls, fn($u) => str_contains($u, '/admin/acreditacion/documento/nuevo') && str_contains($u, 'proceso=49')));
    // El factor «sin factor asignado» y la fila sin meta no llevan enlace.
    $this->assertArrayNotHasKey('edicion', $resultado['factores']['pestanas'][2]);
    $this->assertArrayNotHasKey('edicion', $resultado['plan']['filas'][1]);
  }

  /**
   * Un lápiz sin acceso a su destino no se pinta (acceso real, no el permiso).
   */
  public function testUnLapizSinAccesoNoSePinta(): void {
    $programa = $this->crearNodo('programa', ['title' => 'Programa uno', 'field_clave' => 'uno']);
    $valoracion = $this->crearNodo('valoracion', ['title' => 'Valoración']);
    // Puede abrir el panel y editar programas, pero no valoraciones ni planes.
    $this->setCurrentUser($this->usuarioConPermisos([
      'access content',
      'access acreditacion panel',
      'edit any programa content',
      'create programa content',
    ]));
    $resultado = $this->edicion()->paraPrograma((int) $programa->id(), 'Programa uno', ...$this->datosDePrograma((int) $valoracion->id(), 0, 5));
    $this->assertNotNull($resultado['edicion']['ficha']);
    $this->assertArrayNotHasKey('plan', $resultado['edicion']);
    $this->assertArrayNotHasKey('documento', $resultado['edicion']);
    $this->assertArrayNotHasKey('edicion', $resultado['factores']['pestanas'][0]);
    $this->assertArrayNotHasKey('edicion', $resultado['factores']['pestanas'][1]);
    $this->assertNotNull($this->edicion()->programaNuevo());
    // Sin el permiso propio del micrositio tampoco hay lápiz de cabecera y pie.
    $this->assertNull($this->edicion()->micrositio());
  }

  /**
   * La precarga ?elemento= solo acepta un factor del modelo que se pueda ver.
   */
  public function testLaPrecargaDelFactorEsEstricta(): void {
    Vocabulary::create(['vid' => 'otro_vocabulario', 'name' => 'otro'])->save();
    $factor = Term::create(['vid' => 'elemento_modelo', 'name' => 'Factor 1']);
    $factor->save();
    $ajeno = Term::create(['vid' => 'otro_vocabulario', 'name' => 'Ajeno']);
    $ajeno->save();
    $this->setCurrentUser($this->usuarioAcreditacion());
    $gancho = $this->container->get(UnillanosAutoevalHooks::class);
    $precargado = function (array $consulta) use ($gancho): string {
      $solicitud = Request::create('/node/add/valoracion', 'GET', $consulta);
      $this->container->get('request_stack')->push($solicitud);
      $nodo = $this->container->get('entity_type.manager')->getStorage('node')->create(['type' => 'valoracion']);
      $gancho->entityPrepareForm($nodo, 'add', new FormState());
      $this->container->get('request_stack')->pop();
      return (string) $nodo->get('field_elemento')->target_id;
    };
    // Un factor del vocabulario del modelo se precarga.
    $this->assertSame((string) $factor->id(), $precargado(['elemento' => (string) $factor->id()]));
    // Un término de otro vocabulario, un valor no numérico o un arreglo, no.
    $this->assertSame('', $precargado(['elemento' => (string) $ajeno->id()]));
    $this->assertSame('', $precargado(['elemento' => 'abc']));
    $this->assertSame('', $precargado(['elemento' => '1; DROP']));
    $this->assertSame('', $precargado(['elemento' => [(string) $factor->id()]]));
    $this->assertSame('', $precargado(['elemento' => '99999']));
    // Sin el parámetro, nada.
    $this->assertSame('', $precargado([]));
    // Quien no puede ver los términos (sin «access content») no precarga nada.
    Role::load('anonymous')->revokePermission('access content')->save();
    $this->setCurrentUser(new AnonymousUserSession());
    $this->assertSame('', $precargado(['elemento' => (string) $factor->id()]));
  }

  /**
   * Los enlaces abren su pantalla en el panel lateral y siguen siendo enlaces.
   */
  public function testElEnlaceAbreElPanelConservandoSuHref(): void {
    $html = $this->renderizar('unillanos:edit-link', [
      'url' => '/node/5/edit?destination=/programas/x',
      'label' => 'Editar la valoración',
    ]);
    $this->assertStringContainsString('class="ua-edit ua-edit--lapiz use-ajax"', $html);
    $this->assertStringContainsString('data-dialog-type="dialog"', $html);
    $this->assertStringContainsString('data-dialog-renderer="off_canvas"', $html);
    $this->assertStringContainsString('data-dialog-options="{&quot;width&quot;:520}"', $html);
    // Sin JavaScript sigue funcionando como un enlace normal.
    $this->assertStringContainsString('href="/node/5/edit?destination=/programas/x"', $html);
    // La biblioteca del modo edición trae las herramientas de diálogo de core.
    $tema = $this->container->get('extension.list.theme')->getPath('unillanos');
    $bibliotecas = Yaml::decode((string) file_get_contents(DRUPAL_ROOT . '/' . $tema . '/unillanos.libraries.yml'));
    $this->assertContains('core/drupal.dialog.ajax', $bibliotecas['edicion']['dependencies']);
    $this->assertContains('core/drupal.dialog.off_canvas', $bibliotecas['edicion']['dependencies']);
  }

  /**
   * Guardar se envía por AJAX solo en el panel y solo con permiso.
   */
  public function testGuardarEnElPanelActuaSoloEnElPanel(): void {
    $gancho = $this->container->get(UnillanosAutoevalHooks::class);
    $panel = '/admin/acreditacion/programa/1/ficha?destination=/programas&_wrapper_format=drupal_dialog.off_canvas'
      . '&ajax_form=1&_wrapper_format=drupal_ajax';
    $formulario = fn(): array => ['actions' => ['submit' => ['#type' => 'submit', '#value' => 'Guardar']]];
    $estado = new FormState();
    $alterar = function (string $uri, array $form) use ($gancho, $estado): array {
      $this->container->get('request_stack')->push(Request::create($uri, 'POST'));
      $gancho->formAlter($form, $estado, 'node_valoracion_form');
      $this->container->get('request_stack')->pop();
      return $form;
    };
    $this->setCurrentUser($this->usuarioAcreditacion());
    $con = $alterar($panel, $formulario());
    $this->assertSame(UnillanosAutoevalHooks::class . '::devolverFormularioAlPanel', $con['actions']['submit']['#ajax']['callback']);
    $this->assertSame('ua-panel-formulario', $con['actions']['submit']['#ajax']['wrapper']);
    $this->assertStringContainsString('<div id="ua-panel-formulario">', $con['#prefix']);
    $this->assertSame('</div>', $con['#suffix']);
    // En la pantalla completa (sin panel) el formulario no cambia.
    $this->assertSame($formulario(), $alterar('/admin/acreditacion/programa/1/ficha?destination=/programas', $formulario()));
    // Un formulario sin botón de guardar tampoco cambia.
    $this->assertSame(['actions' => []], $alterar($panel, ['actions' => []]));
    // Sin el permiso del modo edición, ni siquiera en una petición del panel.
    $this->setCurrentUser($this->usuario(['authenticated']));
    $this->assertSame($formulario(), $alterar($panel, $formulario()));
    $this->setCurrentUser(new AnonymousUserSession());
    $this->assertSame($formulario(), $alterar($panel, $formulario()));
  }

  /**
   * Sin errores recarga la página; con errores, vuelve a pintar el panel.
   */
  public function testElPanelRecargaSinErroresMuestraConErrores(): void {
    $gancho = $this->container->get(UnillanosAutoevalHooks::class);
    $this->setCurrentUser($this->usuarioAcreditacion());
    $guardar = function (string $destino, bool $con_errores) use ($gancho): mixed {
      $uri = '/admin/acreditacion/programa/1/ficha?destination=' . rawurlencode($destino);
      $this->container->get('request_stack')->push(Request::create($uri, 'POST', [], [], [], [], NULL));
      $this->container->get('request_stack')->getCurrentRequest()->query->set('destination', $destino);
      $form = ['#id' => 'f'];
      $estado = new FormState();
      if ($con_errores) {
        $estado->setErrorByName('title', 'El campo Título es obligatorio.');
      }
      else {
        // El indicador de errores es global (estático): se limpia entre casos.
        $estado->clearErrors();
      }
      $resultado = $gancho->devolverFormularioAlPanel($form, $estado);
      $this->container->get('request_stack')->pop();
      return $resultado;
    };
    // Con errores: el formulario, con los mensajes al principio.
    $con_errores = $guardar('/programas', TRUE);
    $this->assertIsArray($con_errores);
    $this->assertSame('status_messages', $con_errores['mensajes_del_panel']['#type']);
    // Sin errores: cierra el panel y redirige a la página de origen.
    $respuesta = $guardar('/programas/ingenieria-electronica?x=1', FALSE);
    $this->assertInstanceOf(AjaxResponse::class, $respuesta);
    $ordenes = $respuesta->getCommands();
    $this->assertSame('closeDialog', $ordenes[0]['command']);
    $this->assertSame('#drupal-off-canvas', $ordenes[0]['selector']);
    $this->assertSame('redirect', $ordenes[1]['command']);
    $this->assertSame('/programas/ingenieria-electronica?x=1', $ordenes[1]['url']);
    // Un destino externo o raro no se sigue: se vuelve a la portada.
    foreach (['//otro.sitio/x', 'https://otro.sitio/x', 'javascript:alert(1)', '', '/\\otro.sitio', '/\\/otro.sitio'] as $malo) {
      $ordenes = $guardar($malo, FALSE)->getCommands();
      $this->assertSame(Url::fromRoute('<front>')->toString(), $ordenes[1]['url'], $malo);
    }
  }

  /**
   * Renderiza un componente y devuelve su HTML.
   *
   * @param string $componente
   *   Por ejemplo `unillanos:edit-link`.
   * @param array<string, mixed> $props
   *   Props del componente.
   */
  protected function renderizar(string $componente, array $props): string {
    $construccion = ['#type' => 'component', '#component' => $componente, '#props' => $props];
    return (string) $this->container->get('renderer')->renderInIsolation($construccion);
  }

  /**
   * El enlace de edición, la barra y el JS: marcado, ocultamiento y estado.
   */
  public function testComponentesDelModoEdicion(): void {
    $construir = fn(array $props): string => $this->renderizar('unillanos:edit-link', $props);
    $lapiz = $construir(['url' => '/node/5/edit?destination=/programas/x', 'label' => 'Editar el factor 3: Egresados']);
    $this->assertMatchesRegularExpression('~<a data-component-id="unillanos:edit-link" class="ua-edit ua-edit--lapiz use-ajax" href="/node/5/edit\\?destination=/programas/x" data-ua-edit~', $lapiz);
    $this->assertStringContainsString('<span class="ua-sr">Editar el factor 3: Egresados</span>', $lapiz);
    $this->assertStringContainsString('aria-hidden="true"', $lapiz);
    $mas = $construir([
      'url' => '/node/add/programa',
      'label' => 'Añadir programa',
      'variant' => 'mas',
      'show_label' => TRUE,
    ]);
    $this->assertStringContainsString('ua-edit--mas ua-edit--texto', $mas);
    $this->assertStringContainsString('<span>Añadir programa</span>', $mas);
    // Un destino inseguro no imprime nada.
    $this->assertSame('', trim($construir(['url' => 'javascript:alert(1)', 'label' => 'X'])));
    // CSS (comprobación estática del archivo): oculto hasta activar el modo y
    // objetivo de 44 px.
    $tema = $this->container->get('extension.list.theme')->getPath('unillanos');
    $css = (string) file_get_contents(DRUPAL_ROOT . '/' . $tema . '/components/edit-link/edit-link.css');
    $this->assertMatchesRegularExpression('~\\.ua-edit\\s*\\{\\s*display:\\s*none~', $css);
    $this->assertStringContainsString('html[data-ua-edicion="on"] .ua .ua-edit', $css);
    $this->assertStringContainsString('min-height: 44px', $css);
    $this->assertStringNotContainsString('!important', $css);
    // JS (comprobación estática del archivo, no se ejecuta aquí): botón con
    // aria-pressed y estado en localStorage dentro de try/catch.
    $js = (string) file_get_contents(DRUPAL_ROOT . '/' . $tema . '/js/edicion.js');
    $this->assertStringContainsString('aria-pressed', $js);
    $this->assertMatchesRegularExpression('~try\\s*\\{[^}]*localStorage\\.getItem[^}]*\\}\\s*catch~s', $js);
    $this->assertMatchesRegularExpression('~try\\s*\\{[^}]*localStorage\\.setItem[^}]*\\}\\s*catch~s', $js);
    $this->assertStringContainsString("once('ua-edicion'", $js);
    $this->assertDoesNotMatchRegularExpression('~\\bvar\\b|console\\.log~', $js);
  }

  /**
   * Los componentes con edición imprimen los enlaces; sin ellos, nada cambia.
   */
  public function testFactorTabsMasPlanImprimenEnlaces(): void {
    $render = fn(string $componente, array $props): string => $this->renderizar($componente, $props);
    $enlace = [
      'url' => '/node/50/edit?destination=/programas/x',
      'title' => 'Editar la valoración del Factor 01: Proyecto',
      'tipo' => 'lapiz',
    ];
    $item = ['key' => 'f01', 'number' => '01', 'name' => 'Proyecto', 'label' => 'Factor 01'];
    $base = ['id' => 'f', 'items' => [$item], 'report' => FALSE];
    $sin = $render('unillanos:factor-tabs', $base);
    $this->assertStringNotContainsString('ua-edit', $sin);
    $this->assertStringNotContainsString('ua-panel__edicion', $sin);
    $base['items'][0]['edicion'] = [$enlace];
    $con = $render('unillanos:factor-tabs', $base);
    $this->assertStringContainsString('ua-panel__edicion', $con);
    $this->assertStringContainsString('href="/node/50/edit?destination=/programas/x"', $con);
    $this->assertStringContainsString('>Editar la valoración del Factor 01: Proyecto</span>', $con);

    $columnas = [['label' => 'A'], ['label' => 'B']];
    $fila = ['keys' => ['f01'], 'cells' => [['type' => 'text', 'value' => 'a'], ['type' => 'text', 'value' => 'b']]];
    $sin = $render('unillanos:plan-table', ['columns' => $columnas, 'rows' => [$fila]]);
    $this->assertStringNotContainsString('ua-edit', $sin);
    $fila['edicion'] = [
      [
        'url' => '/node/148/edit?destination=/programas/x',
        'title' => 'Editar Meta 1 del plan de mejoramiento',
        'tipo' => 'lapiz',
      ],
    ];
    $con = $render('unillanos:plan-table', ['columns' => $columnas, 'rows' => [$fila]]);
    $this->assertSame(1, substr_count($con, 'data-ua-edit'));
    $this->assertStringContainsString('>Editar Meta 1 del plan de mejoramiento</span>', $con);
  }

}
