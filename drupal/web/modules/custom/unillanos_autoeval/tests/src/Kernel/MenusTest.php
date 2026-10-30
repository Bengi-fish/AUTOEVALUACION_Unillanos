<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\block\Entity\Block;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\MediaType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Prueba los menús principal y del pie y el elemento activo.
 *
 * «Inicio» lo aporta el perfil `standard` y no existe en estas pruebas.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class MenusTest extends UnillanosKernelTestBase {

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
    $this->installConfig(['system', 'block']);
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->setUpCurrentUser([], ['access content', 'view media']);
    Vocabulary::create(['vid' => 'facultad', 'name' => 'facultad'])->save();
    foreach (['proceso' => ['field_periodo_evaluado', 'field_resultado']] as $tipo => $campos) {
      foreach ($campos as $campo) {
        $this->crearCampo('node', $tipo, $campo, 'string');
      }
    }
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_tipo_elemento', 'string');
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_numero', 'integer');
    $this->crearCampo('node', 'hallazgo', 'field_tipo_hallazgo', 'string');
    $this->crearCampo('node', 'programa', 'field_facultad', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_sede', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_nivel', 'list_string', [
      'allowed_values' => ['pregrado' => 'Pregrado'],
    ]);
    $this->crearCampo('node', 'programa', 'field_modalidad', 'list_string', [
      'allowed_values' => ['presencial' => 'Presencial'],
    ]);
    $campos = [
      'director',
      'codigo_snies',
      'resolucion_registro',
      'registro_hasta',
      'resolucion_acreditacion',
      'acreditacion_hasta',
    ];
    foreach ($campos as $campo) {
      $this->crearCampo('node', 'programa', 'field_' . $campo, 'string');
    }
    // La página del programa lee los documentos (medios).
    $tipo = MediaType::create(['id' => 'documento', 'label' => 'Documento', 'source' => 'file']);
    $tipo->save();
    $campo_fuente = $tipo->getSource()->createSourceField($tipo);
    $almacen = $campo_fuente->getFieldStorageDefinition();
    if ($almacen instanceof FieldStorageConfig) {
      $almacen->save();
    }
    $campo_fuente->save();
    $tipo->set('source_configuration', ['source_field' => $campo_fuente->getName()])->save();
    $this->crearCampo('media', 'documento', 'field_categoria', 'list_string', ['allowed_values' => ['informe' => 'Informe']]);
    $this->crearCampo('media', 'documento', 'field_fecha_publicacion', 'string');
    $this->crearCampo('media', 'documento', 'field_proceso', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('media', 'documento', 'field_lineamiento', 'entity_reference', ['target_type' => 'node']);
    $this->container->get('theme_installer')->install(['unillanos']);
    $this->config('system.theme')->set('default', 'unillanos')->save();
    $this->container->get('theme.manager')->setActiveTheme(
      $this->container->get('theme.initialization')->initTheme('unillanos')
    );
    Block::create([
      'id' => 'unillanos_main_menu',
      'theme' => 'unillanos',
      'region' => 'primary_menu',
      'plugin' => 'system_menu_block:main',
      'settings' => [
        'id' => 'system_menu_block:main',
        'label' => 'Menú principal',
        'label_display' => '0',
        'provider' => 'system',
        'level' => 1,
        'depth' => 1,
        'expand_all_items' => FALSE,
      ],
    ])->save();
    $this->container->get('plugin.manager.menu.link')->rebuild();
  }

  /**
   * Los títulos y rutas de los enlaces de un menú, en orden.
   *
   * @return array<int, array{string, string}>
   *   Título y nombre de ruta de cada enlace.
   */
  protected function enlaces(string $menu): array {
    $arbol = $this->container->get('menu.link_tree');
    $parametros = new MenuTreeParameters();
    $parametros->setMaxDepth(1)->onlyEnabledLinks();
    $elementos = $arbol->transform($arbol->load($menu, $parametros), [
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);
    $lista = [];
    foreach ($elementos as $elemento) {
      $lista[] = [(string) $elemento->link->getTitle(), $elemento->link->getRouteName()];
    }
    return $lista;
  }

  /**
   * El menú principal lleva solo Programas y Documentos, por ruta.
   */
  public function testMenuPrincipal(): void {
    $this->assertSame([
      ['Programas', 'unillanos_autoeval.programas'],
      ['Documentos', 'unillanos_autoeval.documentos'],
    ], $this->enlaces('main'));
  }

  /**
   * El menú del pie lleva Programas y Documentos.
   */
  public function testMenuDelPie(): void {
    $this->assertSame([
      ['Programas', 'unillanos_autoeval.programas'],
      ['Documentos', 'unillanos_autoeval.documentos'],
    ], $this->enlaces('footer'));
  }

  /**
   * Ningún enlace de los menús apunta a una ruta que no existe.
   */
  public function testNingunEnlaceSeDejaSinRuta(): void {
    $proveedor = $this->container->get('router.route_provider');
    foreach (['main', 'footer'] as $menu) {
      $this->assertNotEmpty($this->enlaces($menu));
      foreach ($this->enlaces($menu) as [$titulo, $ruta]) {
        $this->assertNotEmpty($proveedor->getRoutesByNames([$ruta]), "«{$titulo}» de {$menu} apunta a {$ruta}.");
      }
    }
    // Institucional y Participa no están hasta que existan sus rutas.
    $titulos = array_merge(array_column($this->enlaces('main'), 0), array_column($this->enlaces('footer'), 0));
    $this->assertNotContains('Institucional', $titulos);
    $this->assertNotContains('Participa', $titulos);
  }

  /**
   * Pone una ruta como la actual para consultar el rastro activo.
   *
   * @param string $ruta
   *   Nombre de la ruta.
   * @param array<string, mixed> $parametros
   *   Parámetros de la ruta (por ejemplo, el nodo).
   */
  protected function enRuta(string $ruta, array $parametros = []): void {
    $solicitud = Request::create('/');
    $solicitud->setSession(new Session(new MockArraySessionStorage()));
    $solicitud->attributes->set(RouteObjectInterface::ROUTE_NAME, $ruta);
    $solicitud->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $this->container->get('router.route_provider')->getRouteByName($ruta));
    $solicitud->attributes->set('_raw_variables', new InputBag(array_map(fn($v) => $v->id(), $parametros)));
    foreach ($parametros as $nombre => $valor) {
      $solicitud->attributes->set($nombre, $valor);
    }
    $this->container->get('request_stack')->push($solicitud);
  }

  /**
   * El rastro del menú principal en /programas.
   */
  public function testRastroEnElListado(): void {
    $this->enRuta('unillanos_autoeval.programas');
    $rastro = $this->container->get('menu.active_trail')->getActiveTrailIds('main');
    $this->assertArrayHasKey('unillanos_autoeval.menu.programas', $rastro);
    $this->assertArrayNotHasKey('unillanos_autoeval.menu.documentos', $rastro);
  }

  /**
   * El rastro del menú principal en /documentos.
   */
  public function testRastroEnDocumentos(): void {
    $this->enRuta('unillanos_autoeval.documentos');
    $rastro = $this->container->get('menu.active_trail')->getActiveTrailIds('main');
    $this->assertArrayHasKey('unillanos_autoeval.menu.documentos', $rastro);
    $this->assertArrayNotHasKey('unillanos_autoeval.menu.programas', $rastro);
  }

  /**
   * La página de un programa pone «Programas» en el rastro.
   */
  public function testRastroEnLaPaginaDeUnPrograma(): void {
    $programa = $this->crearNodo('programa', ['title' => 'Enfermería', 'field_clave' => 'enfermeria']);
    $this->enRuta('entity.node.canonical', ['node' => $programa]);
    $rastro = $this->container->get('menu.active_trail')->getActiveTrailIds('main');
    $this->assertArrayHasKey('unillanos_autoeval.menu.programas', $rastro);
    $this->assertArrayNotHasKey('unillanos_autoeval.menu.documentos', $rastro);
    // Solo el menú principal lo recibe.
    $this->assertArrayNotHasKey('unillanos_autoeval.menu.programas', $this->container->get('menu.active_trail')->getActiveTrailIds('footer'));
  }

  /**
   * La página de un nodo de otro tipo no marca nada.
   */
  public function testRastroEnLaPaginaDeOtroTipo(): void {
    $otro = $this->crearNodo('lineamiento', ['title' => 'Un lineamiento']);
    $this->enRuta('entity.node.canonical', ['node' => $otro]);
    $rastro = $this->container->get('menu.active_trail')->getActiveTrailIds('main');
    $this->assertArrayNotHasKey('unillanos_autoeval.menu.programas', $rastro);
    $this->assertArrayNotHasKey('unillanos_autoeval.menu.documentos', $rastro);
  }

  /**
   * El HTML marca «Programas» como la página actual en /programas.
   */
  public function testHtmlDelListado(): void {
    $html = (string) $this->container->get('http_kernel')->handle(Request::create('/programas'))->getContent();
    $this->assertMatchesRegularExpression('~<a class="ua-nav__link" href="/programas" aria-current="page">Programas</a>~', $html);
    $this->assertDoesNotMatchRegularExpression('~href="/documentos" aria-current~', $html);
    // En el menú solo «Programas» es la actual (las migas llevan la suya).
    $menu = substr($html, (int) strpos($html, 'ua-nav__list'), 700);
    $this->assertSame(1, substr_count($menu, 'aria-current="page"'));
  }

  /**
   * El HTML marca «Documentos» como la página actual en /documentos.
   */
  public function testHtmlDeDocumentos(): void {
    $html = (string) $this->container->get('http_kernel')->handle(Request::create('/documentos'))->getContent();
    $this->assertMatchesRegularExpression('~<a class="ua-nav__link" href="/documentos" aria-current="page">Documentos</a>~', $html);
    $this->assertDoesNotMatchRegularExpression('~href="/programas" aria-current~', $html);
  }

  /**
   * En un programa «Programas» está en el recorrido y no es la actual.
   */
  public function testHtmlDeLaPaginaDeUnPrograma(): void {
    $programa = $this->crearNodo('programa', ['title' => 'Enfermería', 'field_clave' => 'enfermeria']);
    $html = (string) $this->container->get('http_kernel')->handle(Request::create('/node/' . $programa->id()))->getContent();
    $this->assertMatchesRegularExpression('~<a class="ua-nav__link is-current" href="/programas" aria-current="true">Programas</a>~', $html);
    $this->assertDoesNotMatchRegularExpression('~href="/documentos" aria-current~', $html);
    // Ningún elemento del menú es «la página actual».
    $this->assertStringNotContainsString('aria-current="page"', substr($html, (int) strpos($html, 'ua-nav__list'), 700));
    // Las migas llevan «Programas».
    $this->assertMatchesRegularExpression('~<a href="/programas">Programas</a>~', $html);
  }

  /**
   * El pie se lee del menú: con varios, uno y ningún enlace.
   */
  public function testPieSegunLaCantidadDeEnlaces(): void {
    $nucleo = $this->container->get('http_kernel');
    $html = (string) $nucleo->handle(Request::create('/programas'))->getContent();
    $this->assertStringContainsString('Explora', $html);
    $this->assertSame(2, preg_match_all('~<li><a href="/(programas|documentos)">(Programas|Documentos)</a></li>~', $html));

    $gestor = $this->container->get('plugin.manager.menu.link');
    // Con uno.
    $gestor->updateDefinition('unillanos_autoeval.pie.documentos', ['enabled' => 0]);
    $html = (string) $nucleo->handle(Request::create('/programas'))->getContent();
    $this->assertStringContainsString('Explora', $html);
    $this->assertSame(1, preg_match_all('~<li><a href="/programas">Programas</a></li>~', $html));
    $this->assertStringNotContainsString('<li><a href="/documentos">Documentos</a></li>', $html);

    // Con ninguno: ni la columna ni su título.
    $gestor->updateDefinition('unillanos_autoeval.pie.programas', ['enabled' => 0]);
    $html = (string) $nucleo->handle(Request::create('/programas'))->getContent();
    $this->assertStringNotContainsString('Explora', $html);
    $this->assertStringNotContainsString('ua-footer__links', $html);
    $this->assertStringContainsString('ua-footer__bottom', $html);
  }

  /**
   * El servicio del pie omite enlaces sin acceso y respeta el orden.
   */
  public function testEnlacesDelPieRespetanElOrdenElAcceso(): void {
    $gestor = $this->container->get('plugin.manager.menu.link');
    $gestor->addDefinition('prueba.c', [
      'title' => 'Tercero',
      'route_name' => 'unillanos_autoeval.documentos',
      'menu_name' => 'pruebas',
      'weight' => 3,
    ]);
    $gestor->addDefinition('prueba.a', [
      'title' => 'Primero',
      'route_name' => 'unillanos_autoeval.programas',
      'menu_name' => 'pruebas',
      'weight' => 1,
    ]);
    // Ruta que un anónimo no puede ver.
    $gestor->addDefinition('prueba.b', [
      'title' => 'Sin acceso',
      'route_name' => 'system.admin',
      'menu_name' => 'pruebas',
      'weight' => 2,
    ]);
    $servicio = $this->container->get('unillanos_autoeval.enlaces_del_pie');
    $cache = new CacheableMetadata();
    $enlaces = $servicio->enlaces($cache, 'pruebas');
    $this->assertSame(['Primero', 'Tercero'], array_column($enlaces, 'title'));
    $this->assertSame(['/programas', '/documentos'], array_column($enlaces, 'url'));
    $this->assertContains('config:system.menu.pruebas', $cache->getCacheTags());
    $this->assertContains('user.permissions', $cache->getCacheContexts());
    // El pie no marca activo: no depende de la ruta.
    $this->assertNotContains('route', $cache->getCacheContexts());
    $this->assertNotContains('url.path', $cache->getCacheContexts());
    // Un menú sin enlaces no devuelve nada.
    $this->assertSame([], $servicio->enlaces(new CacheableMetadata(), 'vacio'));
  }

  /**
   * La página declara la caché de los dos menús.
   */
  public function testCacheDeLosMenus(): void {
    $respuesta = $this->container->get('http_kernel')->handle(Request::create('/programas'));
    $this->assertInstanceOf(CacheableResponseInterface::class, $respuesta);
    $metadatos = $respuesta->getCacheableMetadata();
    // Menú principal: la configuración del menú y el rastro activo.
    $this->assertContains('config:system.menu.main', $metadatos->getCacheTags());
    $this->assertContains('route.menu_active_trails:main', $metadatos->getCacheContexts());
    // Pie: la configuración del menú y el acceso de los enlaces.
    $this->assertContains('config:system.menu.footer', $metadatos->getCacheTags());
    $this->assertContains('user.permissions', $metadatos->getCacheContexts());
  }

}
