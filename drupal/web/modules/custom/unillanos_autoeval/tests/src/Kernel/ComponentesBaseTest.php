<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el marcado de los componentes base del tema.
 *
 * Botón, etiqueta, estado, encabezado de sección y tarjeta. Renderiza los
 * componentes del tema `unillanos`: lo vacío se omite, un destino inseguro se
 * imprime sin href, el texto se escapa y el nivel del título sale de una lista
 * blanca.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class ComponentesBaseTest extends KernelTestBase {

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
   *   Por ejemplo `unillanos:estado`.
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
   * Un nivel de título fuera de la lista no llega a imprimirse.
   *
   * @param string $componente
   *   Componente con la prop `heading`.
   * @param array<string, mixed> $props
   *   Props con un nivel no admitido.
   */
  protected function assertNivelRechazado(string $componente, array $props): void {
    try {
      $html = $this->renderizar($componente, $props);
    }
    catch (\Throwable $excepcion) {
      // El esquema del componente (enum) lo rechaza antes de imprimir nada.
      $this->assertStringContainsString('enumeration', $excepcion->getMessage());
      return;
    }
    // Si no lo rechaza, la lista blanca de la plantilla lo reemplaza.
    $this->assertStringNotContainsString('<' . $props['heading'], $html);
  }

  /**
   * El estado «ok» lleva el ícono de check y el texto.
   */
  public function testEstadoOkLlevaCheck(): void {
    $html = $this->renderizar('unillanos:estado', ['label' => 'Con evidencia', 'variant' => 'ok']);
    $this->assertStringContainsString('class="ua-estado ua-estado--ok"', $html);
    $this->assertSame(1, substr_count($html, 'data-component-id="unillanos:icon"'));
    $this->assertStringContainsString('aria-hidden="true"', $html);
    // El check de unillanos:icon.
    $this->assertStringContainsString('<path d="M5 12.5 10 17l9-10"/>', $html);
    $this->assertStringContainsString('Con evidencia', $html);
  }

  /**
   * Los demás estados llevan el ícono de información, no el check.
   */
  public function testOtrosEstadosLlevanInfo(): void {
    foreach (['proceso', 'pendiente'] as $variante) {
      $html = $this->renderizar('unillanos:estado', ['label' => 'Etiqueta', 'variant' => $variante]);
      $this->assertStringContainsString('ua-estado--' . $variante, $html);
      $this->assertStringContainsString('<circle cx="12" cy="12" r="9"/>', $html, $variante);
      $this->assertStringNotContainsString('M5 12.5 10 17', $html, $variante);
    }
  }

  /**
   * El texto del estado se escapa.
   */
  public function testEstadoEscapaElTexto(): void {
    $html = $this->renderizar('unillanos:estado', ['label' => '<script>alert(1)</script>', 'variant' => 'ok']);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
  }

  /**
   * El encabezado de sección: antetítulo, nivel, bajada y variante.
   */
  public function testEncabezadoDeSeccion(): void {
    $html = $this->renderizar('unillanos:section-head', [
      'eyebrow' => 'Programa',
      'title' => 'Lo que se evalúa',
      'lead' => 'Una bajada.',
    ]);
    $this->assertStringContainsString('class="ua-section__head"', $html);
    $this->assertStringContainsString('<span class="ua-eyebrow">Programa</span>', $html);
    $this->assertStringContainsString('<h2 class="ua-h2">Lo que se evalúa</h2>', $html);
    $this->assertStringContainsString('<p>Una bajada.</p>', $html);
    // La regla roja de 48 × 4 px es CSS: no añade marcado.
    $this->assertStringNotContainsString('ua-section__rule', $html);
    $panel = $this->renderizar('unillanos:section-head', ['title' => 'Panel', 'variant' => 'panel']);
    $this->assertStringContainsString('class="ua-panel-intro"', $panel);
  }

  /**
   * Lo vacío del encabezado de sección se omite.
   */
  public function testEncabezadoDeSeccionOmiteLoVacio(): void {
    $html = $this->renderizar('unillanos:section-head', ['title' => 'Solo título']);
    $this->assertStringNotContainsString('ua-eyebrow', $html);
    $this->assertStringNotContainsString('<p>', $html);
    $this->assertStringContainsString('<h2 class="ua-h2">Solo título</h2>', $html);
  }

  /**
   * El nivel del título sale de una lista blanca y el texto se escapa.
   */
  public function testEncabezadoDeSeccionNivelEscape(): void {
    $h3 = $this->renderizar('unillanos:section-head', ['title' => 'T', 'heading' => 'h3']);
    $this->assertStringContainsString('<h3 class="ua-h2">T</h3>', $h3);
    $this->assertNivelRechazado('unillanos:section-head', ['title' => 'T', 'heading' => 'script']);
    $html = $this->renderizar('unillanos:section-head', [
      'title' => '<b>x</b>',
      'eyebrow' => '<i>y</i>',
      'lead' => '<u>z</u>',
    ]);
    $this->assertStringNotContainsString('<b>', $html);
    $this->assertStringNotContainsString('<i>', $html);
    $this->assertStringNotContainsString('<u>', $html);
  }

  /**
   * La etiqueta: variante de acento y escape.
   */
  public function testEtiqueta(): void {
    $simple = $this->renderizar('unillanos:tag', ['label' => 'Pregrado']);
    $this->assertMatchesRegularExpression('~<span [^>]*class="ua-tag">Pregrado</span>~', $simple);
    $acento = $this->renderizar('unillanos:tag', [
      'label' => 'Con autoevaluación',
      'variant' => 'acento',
    ]);
    $this->assertStringContainsString('class="ua-tag ua-tag--acento"', $acento);
    $this->assertStringNotContainsString('<script>', $this->renderizar('unillanos:tag', ['label' => '<script>x</script>']));
  }

  /**
   * Las tres variantes del botón y el tamaño pequeño.
   */
  public function testBotonVariantes(): void {
    $primario = $this->renderizar('unillanos:button', ['label' => 'Ver', 'url' => '/programas']);
    $this->assertMatchesRegularExpression('~<a [^>]*class="ua-btn ua-btn--primario" href="/programas">Ver</a>~', $primario);
    $secundario = $this->renderizar('unillanos:button', [
      'label' => 'Ver',
      'url' => '/programas',
      'variant' => 'secundario',
    ]);
    $this->assertStringContainsString('ua-btn--secundario', $secundario);
    $texto = $this->renderizar('unillanos:button', [
      'label' => 'Quitar',
      'url' => '/programas',
      'variant' => 'texto',
      'size' => 'sm',
    ]);
    $this->assertStringContainsString('ua-btn--texto', $texto);
    $this->assertStringContainsString('ua-btn--sm', $texto);
    // Sin URL es un <button type="button">.
    $boton = $this->renderizar('unillanos:button', ['label' => 'Buscar', 'type' => 'submit']);
    $this->assertMatchesRegularExpression('~<button [^>]*class="ua-btn ua-btn--primario" type="submit">~', $boton);
  }

  /**
   * Un destino inseguro o un enlace desactivado no llevan href.
   */
  public function testBotonSinHrefSiElDestinoNoEsSeguro(): void {
    foreach (['javascript:alert(1)', '//example.org', '/\\example.org', 'programas'] as $url) {
      $html = $this->renderizar('unillanos:button', ['label' => 'Ver', 'url' => $url]);
      $this->assertStringNotContainsString('href', $html, $url);
      $this->assertStringContainsString('aria-disabled="true"', $html, $url);
    }
    $desactivado = $this->renderizar('unillanos:button', [
      'label' => 'Ver',
      'url' => '/programas',
      'disabled' => TRUE,
    ]);
    $this->assertStringNotContainsString('href', $desactivado);
    $this->assertStringContainsString('aria-disabled="true"', $desactivado);
    $this->assertStringNotContainsString('<script>', $this->renderizar('unillanos:button', ['label' => '<script>x</script>']));
  }

  /**
   * La tarjeta no pinta lo que no tiene: ícono, texto ni llamado vacíos.
   */
  public function testTarjetaOmiteLoVacio(): void {
    $html = $this->renderizar('unillanos:card', [
      'items' => [['title' => 'Solo título', 'url' => '/documentos']],
    ]);
    $this->assertStringContainsString('<h3 class="ua-card__title">Solo título</h3>', $html);
    $this->assertStringContainsString('href="/documentos"', $html);
    foreach (['ua-card__icon', 'ua-card__text', 'ua-card__cta'] as $clase) {
      $this->assertStringNotContainsString($clase, $html);
    }
    $completa = $this->renderizar('unillanos:card', [
      'items' => [
        [
          'title' => 'Documentos',
          'text' => 'Normatividad',
          'cta' => 'Ver documentos',
          'icon' => 'file',
          'url' => '/documentos',
        ],
      ],
    ]);
    $this->assertStringContainsString('<p class="ua-card__text">Normatividad</p>', $completa);
    $this->assertStringContainsString('ua-card__icon', $completa);
    $this->assertStringContainsString('class="ua-card__cta">Ver documentos', $completa);
  }

  /**
   * La tarjeta filtra el destino, escapa el texto y limita el nivel.
   */
  public function testTarjetaFiltraEscapaNivel(): void {
    $this->assertNivelRechazado('unillanos:card', ['heading' => 'h1', 'items' => [['title' => 'T']]]);
    $html = $this->renderizar('unillanos:card', [
      'items' => [
        [
          'title' => '<script>x</script>',
          'url' => 'javascript:alert(1)',
          'text' => '<b>t</b>',
        ],
      ],
    ]);
    $this->assertStringNotContainsString('href', $html);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringNotContainsString('<b>', $html);
    $this->assertStringContainsString('<h3 class="ua-card__title">', $html);
    $h2 = $this->renderizar('unillanos:card', ['heading' => 'h2', 'items' => [['title' => 'T']]]);
    $this->assertStringContainsString('<h2 class="ua-card__title">T</h2>', $h2);
  }

}
