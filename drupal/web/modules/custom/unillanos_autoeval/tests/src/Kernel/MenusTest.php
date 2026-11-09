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
 * Prueba los menús principal y del pie, el elemento activo y el marco.
 *
 * El marco es la franja superior, la cabecera y el pie que arma la
 * configuración `unillanos_autoeval.micrositio` (servicio DatosMarco).
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
   * El HTML de la página en la ruta dada.
   */
  protected function pagina(string $ruta): string {
    return (string) $this->container->get('http_kernel')->handle(Request::create($ruta))->getContent();
  }

  /**
   * El elemento <nav> del menú principal.
   *
   * No depende de su largo ni de lo que haya antes (franja, logo, buscador).
   */
  protected function menu(string $html): string {
    $this->assertSame(1, preg_match('~<nav [^>]*id="ua-menu".*?</nav>~s', $html, $coincidencia));
    return $coincidencia[0];
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
    $this->assertSame(1, substr_count($this->menu($html), 'aria-current="page"'));
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
    $this->assertStringNotContainsString('aria-current="page"', $this->menu($html));
    // Las migas llevan «Programas».
    $this->assertMatchesRegularExpression('~<a href="/programas">Programas</a>~', $html);
  }

  /**
   * El pie se lee del menú: con varios, uno y ningún enlace.
   */
  public function testPieSegunLaCantidadDeEnlaces(): void {
    $this->installConfig(['unillanos_autoeval']);
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
    $this->assertStringNotContainsString('ua-footer__explora', $html);
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

  /**
   * El menú principal conserva su marcado y sus enlaces dentro del encabezado.
   */
  public function testElMenuVaDentroDelEncabezado(): void {
    $html = $this->pagina('/programas');
    $this->assertSame(1, preg_match('~<header .*?</header>~s', $html, $encabezado));
    $this->assertStringContainsString('class="ua-nav"', $encabezado[0]);
    // Programas y Documentos: ya no hay «Ingresar» en el menú (expectativa
    // cambiada a propósito, la cabecera lleva GOV.CO y redes en su lugar).
    $this->assertSame(2, substr_count($this->menu($html), 'class="ua-nav__link'));
    $this->assertStringNotContainsString('Ingresar', $encabezado[0]);
    $this->assertStringContainsString('aria-controls="ua-menu"', $html);
    $this->assertSame(1, substr_count($html, 'id="ua-menu"'));
  }

  /**
   * La cabecera lleva GOV.CO y redes; ninguna página dice «Ingresar».
   */
  public function testCabeceraSinIngresarConGovcoMasRedes(): void {
    $this->installConfig(['unillanos_autoeval']);
    foreach (['/programas', '/documentos'] as $ruta) {
      $html = $this->pagina($ruta);
      $this->assertSame(1, preg_match('~<header .*?</header>~s', $html, $encabezado), $ruta);
      $cabecera = $encabezado[0];
      $this->assertStringNotContainsString('Ingresar', $html, $ruta);
      $this->assertStringNotContainsString('Mi cuenta', $html, $ruta);
      $this->assertStringNotContainsString('/user/login', $html, $ruta);
      $this->assertStringContainsString('href="https://www.gov.co/"', $cabecera);
      $this->assertSame(4, substr_count($cabecera, 'ua-redes__enlace"'), $ruta);
      foreach (['Facebook', 'X', 'Instagram', 'YouTube'] as $red) {
        $this->assertStringContainsString('>' . $red . ' de la Universidad de los Llanos</span>', $cabecera);
      }
    }
  }

  /**
   * El buscador del encabezado lleva a /programas con el campo q.
   */
  public function testElBuscadorDelEncabezadoLlevaHaciaProgramas(): void {
    $html = $this->pagina('/documentos');
    $this->assertMatchesRegularExpression('~<form class="ua-search ua-header__search" action="/programas" method="get" role="search">~', $html);
    $this->assertMatchesRegularExpression('~<input id="ua-header-q" type="search" name="q" maxlength="100"~', $html);
    $this->assertMatchesRegularExpression('~<label class="ua-sr" for="ua-header-q">~', $html);
  }

  /**
   * La fuente se precarga una sola vez, con crossorigin.
   */
  public function testPrecargaDeLaFuente(): void {
    $html = $this->pagina('/programas');
    $this->assertSame(1, preg_match_all('~<link rel="preload" as="font" type="font/woff2" crossorigin href="[^"]*/fonts/public-sans-latin-wght-normal\.woff2">~', $html));
    $this->assertStringNotContainsString('latin-ext-wght-normal.woff2">', substr($html, 0, (int) strpos($html, '</head>')));
  }

  /**
   * Sin datos no se pinta lo vacío; con lo de fábrica sale el pie completo.
   */
  public function testSinDatosNoSePintaLoVacio(): void {
    $this->installConfig(['unillanos_autoeval']);
    // Con lo de fábrica la franja solo lleva redes y GOV.CO (expectativa
    // cambiada a propósito en el commit de la cabecera): sin enlaces propios
    // ni accesibilidad.
    $con_marca = $this->pagina('/programas');
    $this->assertStringContainsString('ua-topbar__derecha', $con_marca);
    $this->assertStringNotContainsString('ua-topbar__list', $con_marca);
    $this->assertStringNotContainsString('ua-topbar__acceso', $con_marca);
    // El pie de fábrica: cuatro grupos, fila de logos y derechos (expectativa
    // cambiada a propósito: antes llevaba columnas de sedes y contacto vacías).
    foreach (['Ubícanos', 'Contacto', 'Líneas de atención'] as $titulo) {
      $this->assertStringContainsString('<h3>' . $titulo . '</h3>', $con_marca, $titulo);
    }
    $this->assertStringContainsString('<h3 class="ua-footer__redes-titulo">Redes</h3>', $con_marca);
    $this->assertStringContainsString('Nit: 892.000.757-3', $con_marca);
    // Sin GOV.CO ni redes tampoco hay franja; sin grupos, el pie no los pinta.
    $this->config('unillanos_autoeval.micrositio')
      ->set('url_govco', '')
      ->set('redes_sociales', ['facebook' => '', 'x' => '', 'instagram' => '', 'youtube' => ''])
      ->set('pie', [
        'ubicanos' => ['titulo' => '', 'sedes' => [], 'horario' => ['titulo' => '', 'texto' => '']],
        'contacto' => ['titulo' => '', 'items' => []],
        'lineas' => ['titulo' => '', 'items' => []],
        'redes_titulo' => '',
        'politicas' => ['titulo' => '', 'enlaces' => []],
        'logos' => [],
        'derechos' => ['institucion' => '', 'url' => '', 'desde' => 0, 'texto' => '', 'nit' => ''],
      ])
      ->save();
    $html = $this->pagina('/programas');
    $this->assertStringNotContainsString('ua-topbar', $html);
    $this->assertStringNotContainsString('ua-footer__top', $html);
    $this->assertStringNotContainsString('ua-footer__logos', $html);
    $this->assertStringNotContainsString('ua-footer__bottom', $html);
    $this->assertStringNotContainsString('<h3>', substr($html, (int) strpos($html, '<footer')));
    // Lo que sí hay: nombre en dos líneas y los enlaces del menú del pie.
    $this->assertStringContainsString('<span class="ua-brand__kicker">Sistema de</span><span class="ua-brand__name">Autoevaluación</span>', $html);
    $this->assertStringContainsString('ua-footer__explora', $html);
    // Un solo logo en el encabezado.
    $this->assertSame(1, preg_match('~<header .*?</header>~s', $html, $encabezado));
    $this->assertSame(1, substr_count($encabezado[0], 'ua-logos__img--unillanos'));
    $this->assertSame(0, substr_count($encabezado[0], 'ua-logos__img--acreditacion'));
  }

  /**
   * Con datos aparecen la franja y los grupos del pie, y solo ellos.
   */
  public function testConDatosSePintaElMarco(): void {
    $this->installConfig(['unillanos_autoeval']);
    $this->config('unillanos_autoeval.micrositio')
      ->set('franja_superior', [
        ['titulo' => 'Biblioteca de prueba', 'url' => 'https://example.org/b'],
        ['titulo' => 'Interno de prueba', 'url' => '/documentos'],
      ])
      ->set('accesibilidad.url', '/programas')
      ->set('pie.ubicanos.sedes', [['titulo' => 'Sede de prueba', 'texto' => 'Calle de prueba 1']])
      ->set('pie.lineas', ['titulo' => '', 'items' => []])
      ->save();
    $html = $this->pagina('/programas');
    $this->assertStringContainsString('<div data-component-id="unillanos:topbar" class="ua-topbar">', $html);
    $this->assertMatchesRegularExpression('~<a class="ua-topbar__link" href="https://example.org/b" rel="noopener">~', $html);
    $this->assertMatchesRegularExpression('~<a class="ua-topbar__link" href="/documentos">~', $html);
    $this->assertMatchesRegularExpression('~<a class="ua-topbar__link ua-topbar__acceso" href="/programas">~', $html);
    $this->assertStringContainsString('<strong>Sede de prueba</strong><span class="ua-footer__valor">Calle de prueba 1</span>', $html);
    $this->assertStringNotContainsString('Campus Barcelona', $html);
    // Sin líneas de atención la columna sigue con las redes; sin el grupo no
    // hay título de líneas.
    $this->assertStringNotContainsString('<h3>Líneas de atención</h3>', $html);
    $this->assertStringContainsString('<h3 class="ua-footer__redes-titulo">Redes</h3>', $html);
  }

  /**
   * Una URL peligrosa de la configuración no llega a ningún href.
   */
  public function testUrlPeligrosaNoLlegaAlHtml(): void {
    $this->installConfig(['unillanos_autoeval']);
    $this->config('unillanos_autoeval.micrositio')
      ->set('franja_superior', [
        ['titulo' => 'Peligrosa uno', 'url' => 'javascript:alert(1)'],
        ['titulo' => 'Peligrosa dos', 'url' => '//example.org/x'],
        ['titulo' => 'Peligrosa tres', 'url' => 'data:text/html,hola'],
      ])
      ->set('pie.politicas.enlaces', [['titulo' => 'Política peligrosa', 'url' => 'JaVaScRiPt:alert(2)']])
      ->set('pie.contacto.items', [
        ['titulo' => 'Dato peligroso', 'texto' => '', 'url' => 'javascript:alert(5)', 'icono' => ''],
      ])
      ->set('pie.logos', [['clave' => 'govco', 'titulo' => 'Logo peligroso', 'url' => 'data:text/html,hola']])
      ->set('url_govco', 'javascript:alert(3)')
      ->set('redes_sociales', [
        'facebook' => 'javascript:alert(4)',
        'x' => '//example.org/x',
        'instagram' => 'data:text/html,hola',
        'youtube' => '',
      ])
      ->save();
    $html = $this->pagina('/programas');
    $this->assertStringNotContainsString('javascript:', strtolower($html));
    $this->assertStringNotContainsString('data:text/html', $html);
    $this->assertStringNotContainsString('Peligrosa', $html);
    $this->assertStringNotContainsString('Política peligrosa', $html);
    $this->assertStringNotContainsString('Dato peligroso', $html);
    $this->assertStringNotContainsString('Logo peligroso', $html);
    $this->assertStringNotContainsString('ua-topbar', $html);
  }

  /**
   * La página depende de la configuración del micrositio (caché).
   */
  public function testCacheDeLaConfiguracionDelMarco(): void {
    $this->installConfig(['unillanos_autoeval']);
    $respuesta = $this->container->get('http_kernel')->handle(Request::create('/programas'));
    $this->assertInstanceOf(CacheableResponseInterface::class, $respuesta);
    $this->assertContains('config:unillanos_autoeval.micrositio', $respuesta->getCacheableMetadata()->getCacheTags());
    // Cambiar la configuración cambia lo que se pinta.
    $this->assertStringNotContainsString('Cambio de prueba', $this->pagina('/programas'));
    $this->config('unillanos_autoeval.micrositio')
      ->set('pie.ubicanos.sedes', [['titulo' => 'Cambio de prueba', 'texto' => '']])
      ->save();
    $this->assertStringContainsString('Cambio de prueba', $this->pagina('/programas'));
  }

}
