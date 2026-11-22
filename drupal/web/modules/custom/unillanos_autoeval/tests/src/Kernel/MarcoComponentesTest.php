<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el marcado de los botones flotantes, el encabezado y el pie.
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
   *   Por ejemplo `unillanos:a11y`.
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
   * El botón de accesibilidad: con título siempre; el enlace, solo con URL.
   *
   * La franja superior ya no existe: el botón es flotante (unillanos:a11y).
   */
  public function testBotonDeAccesibilidad(): void {
    // Con título y sin URL, o con una URL peligrosa, solo hay botón y panel.
    foreach (['', 'javascript:alert(1)', '//otro.sitio'] as $url) {
      $html = $this->renderizar('unillanos:a11y', ['title' => 'Accesibilidad', 'url' => $url]);
      $this->assertStringContainsString('data-ua-a11y-boton', $html);
      $this->assertStringContainsString('data-ua-a11y-panel', $html);
      $this->assertStringNotContainsString('data-ua-a11y-enlace', $html);
      $this->assertStringNotContainsString('ua-a11y__mas', $html);
      $this->assertStringNotContainsString('javascript', $html);
      $this->assertStringNotContainsString('//otro.sitio', $html);
    }
    // Con una URL segura hay además un enlace de respaldo y «Más sobre…».
    $html = $this->renderizar('unillanos:a11y', ['title' => 'Accesibilidad', 'url' => '/documentos']);
    $this->assertMatchesRegularExpression('~<a class="ua-a11y__enlace" href="/documentos" data-ua-a11y-enlace>~', $html);
    $this->assertStringContainsString('data-ua-a11y-boton', $html);
    $this->assertStringContainsString('class="ua-a11y__mas" href="/documentos"', $html);
    // El panel: título, tamaño del texto, cuatro opciones y cierre.
    $this->assertStringContainsString('role="group" aria-labelledby="ua-a11y-titulo"', $html);
    $this->assertSame(4, substr_count($html, 'data-ua-a11y-opcion='));
    $this->assertSame(4, substr_count($html, 'aria-pressed="false"'));
    $this->assertStringContainsString('data-ua-a11y-cerrar', $html);
    $this->assertStringContainsString('data-ua-a11y-restablecer', $html);
    // El título se escapa.
    $html = $this->renderizar('unillanos:a11y', ['title' => '<script>alert(1)</script>']);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
  }

  /**
   * Las redes: enlaces accesibles, solo los que tienen URL segura.
   *
   * Ahora van solo en el pie (unillanos:redes); el componente filtra por sí
   * mismo, como lo hacía la franja superior.
   */
  public function testRedesFiltranLoInseguroMasLoDesconocido(): void {
    $propio = 'de la Universidad de los Llanos';
    $html = $this->renderizar('unillanos:redes', [
      'redes' => [
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
    ]);
    $this->assertSame(2, substr_count($html, 'ua-redes__enlace"'));
    $this->assertStringContainsString('href="https://www.facebook.com/UnillanosOficial/"', $html);
    $this->assertStringContainsString(">Facebook $propio</span>", $html);
    $this->assertStringContainsString(">YouTube $propio</span>", $html);
    $this->assertStringNotContainsString('javascript', $html);
    $this->assertStringNotContainsString('tiktok', $html);
    $this->assertStringNotContainsString('target=', $html);
    // Sin ninguna red válida no se imprime nada.
    $invalidas = ['redes' => [['red' => 'x', 'title' => 'X', 'url' => 'javascript:x']]];
    $this->assertSame('', trim($this->renderizar('unillanos:redes', $invalidas)));
  }

  /**
   * Datos de la barra del modo edición.
   *
   * @return array<string, mixed>
   *   Props de `unillanos:edicion-barra`.
   */
  protected function datosEdicion(): array {
    return [
      'edicion' => [
        'logout_url' => '/user/logout/confirm',
        'micrositio' => ['title' => 'Editar la cabecera y el pie', 'url' => '/admin/config/unillanos/micrositio'],
        'avanzado' => ['title' => 'Panel avanzado', 'url' => '/admin/acreditacion'],
      ],
    ];
  }

  /**
   * La barra del modo edición: interruptor, enlaces y cierre de sesión.
   */
  public function testBarraDelModoEdicion(): void {
    $html = $this->renderizar('unillanos:edicion-barra', $this->datosEdicion());
    $this->assertStringContainsString('class="ua-edicion-franja"', $html);
    $this->assertStringContainsString('aria-label="Modo edición"', $html);
    $this->assertMatchesRegularExpression('~<button class="ua-edicion-barra__boton" type="button" aria-pressed="false" data-ua-edicion-toggle>~', $html);
    $this->assertStringContainsString('href="/admin/config/unillanos/micrositio"', $html);
    $this->assertStringContainsString('href="/admin/acreditacion"', $html);
    $this->assertMatchesRegularExpression('~<a class="ua-edicion-barra__enlace" href="/user/logout/confirm">Cerrar sesión</a>~', $html);
    // Ya no hay franja superior ni sus clases.
    $this->assertStringNotContainsString('ua-topbar', $html);
    // Sin cierre de sesión seguro, o sin datos, no se imprime nada.
    foreach ([[], ['edicion' => NULL], ['edicion' => ['logout_url' => 'javascript:alert(1)']]] as $props) {
      $this->assertSame('', trim($this->renderizar('unillanos:edicion-barra', $props)));
    }
  }

  /**
   * Un destino inseguro se imprime sin href y el título se escapa.
   */
  public function testBarraDelModoEdicionFiltraLaUrlMasEscapeDelTexto(): void {
    $props = $this->datosEdicion();
    $props['edicion']['avanzado'] = ['title' => '<script>alert(1)</script>', 'url' => 'javascript:alert(1)'];
    $props['edicion']['micrositio'] = ['title' => '<b>x</b>', 'url' => '/admin/config/unillanos/micrositio'];
    $html = $this->renderizar('unillanos:edicion-barra', $props);
    $this->assertStringNotContainsString('javascript:', $html);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringNotContainsString('<b>x</b>', $html);
    $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
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
   * Un solo logo, nombre en dos líneas y barra de edición solo si hay datos.
   */
  public function testEncabezadoConBarraDeEdicionMasUnLogo(): void {
    $html = $this->renderizar('unillanos:header', [
      'solo_logo' => 'unillanos',
      'kicker' => 'Sistema de',
      'name' => 'Autoevaluación',
      'edicion' => $this->datosEdicion()['edicion'],
    ]);
    $this->assertSame(1, substr_count($html, 'ua-logos__img--'));
    $this->assertStringContainsString('<span class="ua-brand__kicker">Sistema de</span><span class="ua-brand__name">Autoevaluación</span>', $html);
    $this->assertStringContainsString('data-component-id="unillanos:edicion-barra"', $html);
    // Antes de la barra del encabezado, no dentro de ella.
    $this->assertLessThan(strpos($html, 'class="ua-header__bar"'), strpos($html, 'ua-edicion-franja'));
    // Sin datos de edición no hay barra, y la franja superior no existe.
    $html = $this->renderizar('unillanos:header', ['kicker' => 'Sistema de', 'name' => 'Autoevaluación']);
    $this->assertStringNotContainsString('ua-edicion-franja', $html);
    $this->assertStringNotContainsString('ua-topbar', $html);
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
