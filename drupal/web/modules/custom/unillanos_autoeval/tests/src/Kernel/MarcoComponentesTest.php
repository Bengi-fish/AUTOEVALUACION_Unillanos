<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el marcado de la franja superior, el encabezado, el pie y los logos.
 *
 * Renderiza los componentes del tema `unillanos`: lo vacío se omite, un
 * destino inseguro se imprime sin href y el texto se escapa.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class MarcoComponentesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'sdc', 'unillanos_autoeval'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('theme_installer')->install(['unillanos']);
    $this->config('system.theme')->set('default', 'unillanos')->save();
    $this->container->get('theme.manager')->setActiveTheme(
      $this->container->get('theme.initialization')->initTheme('unillanos')
    );
  }

  /**
   * Renderiza un componente y devuelve su HTML.
   *
   * @param string $componente
   *   Por ejemplo `unillanos:topbar`.
   * @param array<string, mixed> $props
   *   Props del componente.
   *
   * @return string
   *   El HTML.
   */
  protected function renderizar(string $componente, array $props): string {
    $construccion = ['#type' => 'component', '#component' => $componente, '#props' => $props];
    return (string) $this->container->get('renderer')->renderInIsolation($construccion);
  }

  /**
   * Sin enlaces ni accesibilidad la franja no imprime nada.
   */
  public function testFranjaVaciaNoImprimeNada(): void {
    $this->assertSame('', trim($this->renderizar('unillanos:topbar', [])));
    $this->assertSame('', trim($this->renderizar('unillanos:topbar', ['links' => [], 'accessibility' => NULL])));
    // Accesibilidad sin URL o con una URL peligrosa tampoco cuenta.
    foreach (['', 'javascript:alert(1)'] as $url) {
      $props = ['accessibility' => ['title' => 'Accesibilidad', 'url' => $url]];
      $this->assertSame('', trim($this->renderizar('unillanos:topbar', $props)));
    }
  }

  /**
   * La franja con redes y GOV.CO: enlaces accesibles, solo los que tienen URL.
   */
  public function testFranjaConRedesMasGovco(): void {
    $propio = 'de la Universidad de los Llanos';
    $html = $this->renderizar('unillanos:topbar', [
      'social' => [
        [
          'red' => 'facebook',
          'title' => "Facebook $propio",
          'url' => 'https://www.facebook.com/UnillanosOficial/',
        ],
        ['red' => 'x', 'title' => "X $propio", 'url' => 'javascript:alert(1)'],
        ['red' => 'instagram', 'title' => "Instagram $propio", 'url' => ''],
        ['red' => 'youtube', 'title' => "YouTube $propio", 'url' => 'https://www.youtube.com/channel/UCx'],
        ['red' => 'tiktok', 'title' => 'TikTok', 'url' => 'https://example.org'],
      ],
      'govco' => ['title' => 'GOV.CO, portal único del Estado colombiano', 'url' => 'https://www.gov.co/'],
    ]);
    $this->assertSame(2, substr_count($html, 'ua-redes__enlace"'));
    $this->assertStringContainsString('href="https://www.facebook.com/UnillanosOficial/"', $html);
    $this->assertStringContainsString(">Facebook $propio</span>", $html);
    $this->assertStringContainsString(">YouTube $propio</span>", $html);
    $this->assertStringNotContainsString('javascript', $html);
    $this->assertStringNotContainsString('tiktok', $html);
    $this->assertStringNotContainsString('target=', $html);
    $this->assertLessThan(strpos($html, 'ua-topbar__govco'), strpos($html, 'ua-redes__enlace'));
    $this->assertMatchesRegularExpression(
      '~<a class="ua-topbar__govco" href="https://www.gov.co/"[^>]*>\\s*<img src="[^"]*govco-blanco\\.svg" alt="GOV\\.CO, portal único del Estado colombiano">~',
      $html,
    );
    // Sin ninguna red válida ni GOV.CO la franja no imprime nada.
    $invalidas = [
      'social' => [['red' => 'x', 'title' => 'X', 'url' => 'javascript:x']],
      'govco' => ['title' => 'G', 'url' => ''],
    ];
    $this->assertSame('', trim($this->renderizar('unillanos:topbar', $invalidas)));
    // Las redes solas tampoco se marcan como «solo enlaces» (se ven en móvil).
    $this->assertStringNotContainsString('ua-topbar--solo-enlaces', $html);
  }

  /**
   * Con enlaces: lista, ícono y texto para lectores en los externos.
   */
  public function testFranjaConEnlaces(): void {
    $html = $this->renderizar('unillanos:topbar', [
      'links' => [
        ['title' => 'Biblioteca', 'url' => 'https://example.org/b', 'externo' => TRUE],
        ['title' => 'Interno', 'url' => '/programas'],
      ],
      'accessibility' => ['title' => 'Accesibilidad', 'url' => '/documentos'],
    ]);
    $this->assertStringContainsString('class="ua-topbar"', $html);
    $this->assertStringNotContainsString('ua-topbar--solo-enlaces', $html);
    $this->assertStringContainsString('aria-label="Enlaces de la Universidad"', $html);
    $this->assertSame(2, substr_count($html, 'class="ua-topbar__item"'));
    $this->assertMatchesRegularExpression('~<a class="ua-topbar__link" href="https://example.org/b" rel="noopener">~', $html);
    $this->assertStringContainsString('(enlace externo)', $html);
    $this->assertMatchesRegularExpression('~<a class="ua-topbar__link ua-topbar__acceso" href="/documentos">~', $html);
  }

  /**
   * Sin accesibilidad la franja se marca para ocultarla en móvil.
   */
  public function testFranjaSoloConEnlacesSeMarca(): void {
    $html = $this->renderizar('unillanos:topbar', ['links' => [['title' => 'Biblioteca', 'url' => '/programas']]]);
    $this->assertStringContainsString('ua-topbar--solo-enlaces', $html);
    $this->assertStringNotContainsString('ua-topbar__acceso', $html);
  }

  /**
   * Un destino inseguro se imprime sin href y el título se escapa.
   */
  public function testFranjaFiltraLaUrlMasEscapeDelTexto(): void {
    $html = $this->renderizar('unillanos:topbar', [
      'links' => [
        ['title' => 'Peligroso', 'url' => 'javascript:alert(1)'],
        ['title' => '<script>alert(1)</script>', 'url' => '/programas'],
      ],
    ]);
    $this->assertStringNotContainsString('javascript:', $html);
    $this->assertStringContainsString('<span class="ua-topbar__link">Peligroso</span>', $html);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
  }

  /**
   * Con redes pero sin título de grupo no queda un encabezado vacío.
   */
  public function testPieSinTituloDeRedesNoImprimeH3Vacio(): void {
    $html = $this->renderizar('unillanos:footer', [
      'redes' => [['red' => 'x', 'title' => 'X de la Universidad', 'url' => 'https://example.org/x']],
    ]);
    $this->assertStringContainsString('ua-redes__enlace', $html);
    $this->assertStringNotContainsString('<h3', $html);
  }

  /**
   * El pie omite las columnas, la fila de logos y la línea final vacías.
   */
  public function testPieOmiteLoVacio(): void {
    $html = $this->renderizar('unillanos:footer', ['rights' => ['institucion' => 'Universidad', 'anios' => '2026']]);
    $this->assertStringNotContainsString('ua-footer__top', $html);
    $this->assertStringNotContainsString('<h3>', $html);
    $this->assertStringNotContainsString('ua-footer__logos', $html);
    $this->assertStringNotContainsString('ua-footer__explora', $html);
    $this->assertStringNotContainsString('Nit', $html);
    $this->assertStringContainsString('ua-footer__bottom', $html);
    $this->assertSame('', trim(strip_tags($this->renderizar('unillanos:footer', []))));
  }

  /**
   * El pie imprime las cuatro columnas, los logos y la línea de derechos.
   */
  public function testPieConDatos(): void {
    $html = $this->renderizar('unillanos:footer', [
      'ubicanos' => [
        'title' => 'Ubícanos',
        'sedes' => [['title' => 'Campus Uno', 'text' => 'Km 1']],
        'horario' => ['title' => 'Horario', 'text' => '8 a 5'],
      ],
      'contacto' => [
        'title' => 'Contacto',
        'items' => [
          ['title' => 'Correo', 'text' => 'a@example.org', 'url' => 'mailto:a@example.org', 'icon' => 'mail'],
          ['title' => 'Chat', 'text' => '', 'url' => 'https://example.org/chat'],
          ['title' => 'Texto', 'text' => 'solo texto', 'url' => 'javascript:alert(1)'],
        ],
      ],
      'lines' => [
        'title' => 'Líneas',
        'items' => [['title' => 'PBX', 'text' => '123', 'url' => 'tel:123', 'icon' => 'phone']],
      ],
      'redes_title' => 'Redes',
      'redes' => [['red' => 'x', 'title' => 'X de la Universidad', 'url' => 'https://example.org/x']],
      'politicas' => [
        'title' => 'Políticas',
        'links' => [
          ['title' => 'Términos', 'url' => 'https://example.org/t', 'externo' => TRUE],
          ['title' => 'Peligrosa', 'url' => 'javascript:alert(1)'],
        ],
      ],
      'explora' => [['title' => 'Programas', 'url' => '/programas']],
      'logos' => [
        ['clave' => 'unillanos', 'title' => 'Universidad de los Llanos', 'url' => 'https://www.unillanos.edu.co/'],
        ['clave' => 'colombia', 'title' => 'CO Colombia', 'url' => 'https://www.colombia.co/'],
        ['clave' => 'inventado', 'title' => 'Otro', 'url' => 'https://example.org'],
        ['clave' => 'govco', 'title' => 'GOV.CO', 'url' => 'javascript:alert(1)'],
      ],
      'rights' => [
        'institucion' => 'Universidad de los Llanos',
        'url' => 'https://www.unillanos.edu.co/',
        'anios' => '1974 - 2026',
        'texto' => 'Reservados todos los derechos',
        'nit' => 'Nit: 892.000.757-3',
      ],
    ]);
    foreach (['Ubícanos', 'Contacto', 'Líneas', 'Políticas'] as $titulo) {
      $this->assertStringContainsString('<h3>' . $titulo . '</h3>', $html, $titulo);
    }
    $this->assertStringContainsString('<h3 class="ua-footer__redes-titulo">Redes</h3>', $html);
    // Un título de grupo (h3) por columna; las redes bajo las líneas.
    $this->assertSame(4, substr_count($html, '<div class="ua-footer__col">'));
    $this->assertLessThan(strpos($html, 'ua-redes'), strpos($html, '<h3>Líneas</h3>'));
    $this->assertStringContainsString('<strong>Campus Uno</strong>', $html);
    $this->assertStringContainsString('<a href="mailto:a@example.org">a@example.org</a>', $html);
    $this->assertStringContainsString('<a href="tel:123">123</a>', $html);
    // Sin valor, el título es el enlace; una URL peligrosa deja solo el texto.
    $this->assertMatchesRegularExpression('~<a href="https://example.org/chat" rel="noopener">Chat</a>~', $html);
    $this->assertStringContainsString('solo texto', $html);
    $this->assertStringNotContainsString('javascript:', $html);
    $this->assertStringContainsString('<a href="https://example.org/t" rel="noopener">Términos</a>', $html);
    $this->assertStringContainsString('<li>Peligrosa</li>', $html);
    $this->assertMatchesRegularExpression('~<a href="/programas">Programas</a>~', $html);
    // Logos: solo los conocidos con URL segura, cada uno como enlace con alt.
    $this->assertSame(2, substr_count($html, 'class="ua-footer__logo ua-footer__logo--'));
    $this->assertMatchesRegularExpression('~<a class="ua-footer__logo ua-footer__logo--unillanos" href="https://www.unillanos.edu.co/" rel="noopener">\\s*<img src="[^"]*unillanos-blanco\\.png" alt="Universidad de los Llanos">~', $html);
    $this->assertMatchesRegularExpression('~<img src="[^"]*colombia-marca-pais\\.png" alt="CO Colombia">~', $html);
    $this->assertStringNotContainsString('Otro', $html);
    // Línea final: © institución enlazada, años, texto y NIT.
    $this->assertStringContainsString('<a href="https://www.unillanos.edu.co/" rel="noopener">Universidad de los Llanos</a>', $html);
    $this->assertStringContainsString('1974 - 2026 Reservados todos los derechos', preg_replace('~\\s+~', ' ', strip_tags($html)));
    $this->assertStringContainsString('Nit: 892.000.757-3', $html);
  }

  /**
   * El encabezado sin franja ni buscador solo trae logo, menú y herramientas.
   */
  public function testEncabezadoMinimo(): void {
    $html = $this->renderizar('unillanos:header', []);
    $this->assertStringNotContainsString('ua-topbar', $html);
    $this->assertStringNotContainsString('ua-header__search', $html);
    $this->assertStringContainsString('<div class="ua-header__bar">', $html);
    // Sin `solo_logo` salen los tres logos (la guía de estilos los usa así).
    $this->assertSame(3, substr_count($html, 'ua-logos__img--'));
  }

  /**
   * Un solo logo, nombre en dos líneas y franja con datos.
   */
  public function testEncabezadoConFranjaMasUnLogo(): void {
    $html = $this->renderizar('unillanos:header', [
      'solo_logo' => 'unillanos',
      'kicker' => 'Sistema de',
      'name' => 'Autoevaluación',
      'topbar' => ['links' => [['title' => 'Biblioteca', 'url' => '/programas']], 'accessibility' => NULL],
    ]);
    $this->assertSame(1, substr_count($html, 'ua-logos__img--'));
    $this->assertStringContainsString('<span class="ua-brand__kicker">Sistema de</span><span class="ua-brand__name">Autoevaluación</span>', $html);
    $this->assertStringContainsString('data-component-id="unillanos:topbar"', $html);
  }

  /**
   * Con el nombre vacío no se imprime el bloque del nombre.
   */
  public function testEncabezadoSinNombre(): void {
    $html = $this->renderizar('unillanos:header', ['kicker' => '', 'name' => '']);
    $this->assertStringNotContainsString('ua-brand__system', $html);
  }

  /**
   * El buscador con URL es un formulario GET; sin ella, el campo suelto.
   */
  public function testBuscadorDelEncabezado(): void {
    $con = $this->renderizar('unillanos:header', ['show_search' => TRUE, 'search_url' => '/programas']);
    $this->assertMatchesRegularExpression('~<form class="ua-search ua-header__search" action="/programas" method="get" role="search">~', $con);
    $this->assertStringContainsString('name="q" maxlength="100"', $con);
    $sin = $this->renderizar('unillanos:header', ['show_search' => TRUE]);
    $this->assertStringNotContainsString('<form', $sin);
    $this->assertStringContainsString('<label class="ua-search">', $sin);
    // Una URL del buscador que no es segura cae al campo suelto.
    $mala = $this->renderizar('unillanos:header', ['show_search' => TRUE, 'search_url' => 'javascript:alert(1)']);
    $this->assertStringNotContainsString('<form', $mala);
  }

}
