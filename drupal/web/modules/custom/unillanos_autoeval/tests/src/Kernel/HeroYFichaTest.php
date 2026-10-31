<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el hero de la página del programa, la imagen, la ficha y kv-list.
 *
 * Renderiza los componentes del tema `unillanos`. El caso con foto se prueba
 * aquí con datos de prueba (no hay fotos en el sitio ni se escribe en la base
 * de contenido).
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class HeroYFichaTest extends KernelTestBase {

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
   *   Por ejemplo `unillanos:media`.
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
   * Props del hero de la página del programa.
   *
   * @param array<string, mixed> $extra
   *   Props que se suman o reemplazan.
   *
   * @return array<string, mixed>
   *   Props del pagehead con variante hero.
   */
  protected function propsHero(array $extra = []): array {
    return $extra + [
      'variant' => 'hero',
      'title' => 'Ingeniería',
      'title_accent' => 'Electrónica',
      'eyebrow' => 'Facultad de Ciencias · Sede Barcelona',
      'lead' => 'Una bajada.',
      'breadcrumb' => [['title' => 'Inicio', 'url' => '/'], ['title' => 'Programa']],
    ];
  }

  /**
   * Una foto de prueba.
   *
   * @param array<string, mixed> $extra
   *   Claves que se suman o reemplazan.
   *
   * @return array<string, mixed>
   *   Lo que entrega PaginaPrograma::datosPrograma() en `imagen`.
   */
  protected function foto(array $extra = []): array {
    return $extra + [
      'src' => '/sites/default/files/programa.jpg',
      'srcset' => '/sites/default/files/styles/a/640.webp?itok=x 640w, /sites/default/files/styles/b/1280.webp?itok=y 1280w',
      'type' => 'image/webp',
      'width' => 1920,
      'height' => 700,
      'alt' => '',
    ];
  }

  /**
   * Sin foto (el caso normal) el hero es de color sólido y no lleva imagen.
   */
  public function testHeroSinFoto(): void {
    $html = $this->renderizar('unillanos:pagehead', $this->propsHero());
    $this->assertMatchesRegularExpression('~class="ua-pagehead ua-pagehead--hero"~', $html);
    $this->assertStringNotContainsString('ua-pagehead--foto', $html);
    foreach (['<picture', '<img', 'ua-pagehead__velo', 'ua-pagehead__media'] as $trozo) {
      $this->assertStringNotContainsString($trozo, $html, $trozo);
    }
    $this->assertSame(1, substr_count($html, '<h1 '));
    $this->assertStringContainsString('<h1 class="ua-pagehead__title">Ingeniería <span>Electrónica</span></h1>', $html);
    $this->assertStringContainsString('<span class="ua-eyebrow">Facultad de Ciencias · Sede Barcelona</span>', $html);
    $this->assertStringContainsString('<p class="ua-pagehead__lead">Una bajada.</p>', $html);
    // Las migas van arriba, en su propia franja, antes que el título.
    $this->assertLessThan(strpos($html, 'ua-pagehead__fondo'), strpos($html, 'ua-pagehead__migas'));
    $this->assertLessThan(strpos($html, '<h1 '), strpos($html, 'ua-breadcrumb'));
  }

  /**
   * Lo vacío del hero se omite: bajada, antetítulo y migas.
   */
  public function testHeroOmiteLoVacio(): void {
    $html = $this->renderizar('unillanos:pagehead', ['variant' => 'hero', 'title' => 'Solo título']);
    $this->assertStringNotContainsString('ua-pagehead__lead', $html);
    $this->assertStringNotContainsString('ua-eyebrow', $html);
    $this->assertStringNotContainsString('ua-pagehead__migas', $html);
    $this->assertStringContainsString('Solo título', $html);
  }

  /**
   * Con foto el hero lleva imagen de prioridad alta, velo y la clase de foto.
   */
  public function testHeroConFoto(): void {
    $html = $this->renderizar('unillanos:pagehead', $this->propsHero(['image' => $this->foto()]));
    $this->assertStringContainsString('ua-pagehead--foto', $html);
    $this->assertSame(1, substr_count($html, '<picture'));
    $this->assertSame(1, substr_count($html, '<img '));
    $this->assertStringContainsString('<source type="image/webp" srcset="/sites/default/files/styles/a/640.webp?itok=x 640w, /sites/default/files/styles/b/1280.webp?itok=y 1280w" sizes="100vw">', $html);
    $this->assertStringContainsString('src="/sites/default/files/programa.jpg"', $html);
    $this->assertStringContainsString('width="1920" height="700"', $html);
    // Sobre el pliegue: prioridad alta y sin carga diferida.
    $this->assertStringContainsString('fetchpriority="high"', $html);
    $this->assertStringNotContainsString('loading="lazy"', $html);
    $this->assertStringContainsString('<div class="ua-pagehead__velo"></div>', $html);
    // El texto sigue estando una sola vez.
    $this->assertSame(1, substr_count($html, '<h1 '));
  }

  /**
   * Sin texto alternativo la foto es decorativa; con él, se dice.
   */
  public function testHeroFotoDecorativaMasTexto(): void {
    $decorativa = $this->renderizar('unillanos:pagehead', $this->propsHero(['image' => $this->foto()]));
    $this->assertStringContainsString('alt=""', $decorativa);
    $this->assertStringContainsString('<div class="ua-pagehead__media" aria-hidden="true">', $decorativa);
    $this->assertMatchesRegularExpression('~<picture[^>]*aria-hidden="true"~', $decorativa);
    $conAlt = $this->renderizar('unillanos:pagehead', $this->propsHero(['image' => $this->foto(['alt' => 'Estudiantes en el laboratorio'])]));
    $this->assertStringContainsString('alt="Estudiantes en el laboratorio"', $conAlt);
    $this->assertDoesNotMatchRegularExpression('~<picture[^>]*aria-hidden~', $conAlt);
    $this->assertDoesNotMatchRegularExpression('~<div class="ua-pagehead__media"[^>]*aria-hidden~', $conAlt);
  }

  /**
   * Un src inseguro o vacío no deja una imagen rota ni un velo sobre nada.
   */
  public function testHeroConFotoInsegura(): void {
    foreach (['javascript:alert(1)', '//example.org/x.jpg', '/\\example.org', 'foto.jpg', ''] as $src) {
      $html = $this->renderizar('unillanos:pagehead', $this->propsHero(['image' => $this->foto(['src' => $src])]));
      foreach (['<picture', '<img', 'ua-pagehead--foto', 'ua-pagehead__velo', 'javascript:'] as $trozo) {
        $this->assertStringNotContainsString($trozo, $html, "[$src] $trozo");
      }
    }
    $nulo = $this->renderizar('unillanos:pagehead', $this->propsHero(['image' => NULL]));
    $this->assertStringNotContainsString('<img', $nulo);
    $this->assertStringContainsString('ua-pagehead--hero', $nulo);
  }

  /**
   * El texto del hero y el texto alternativo se escapan.
   */
  public function testHeroEscapaElTexto(): void {
    $html = $this->renderizar('unillanos:pagehead', $this->propsHero([
      'title' => '<script>alert(1)</script>',
      'lead' => '<b>x</b>',
      'image' => $this->foto(['alt' => '"><script>alert(2)</script>']),
    ]));
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringNotContainsString('<b>', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
  }

  /**
   * Sin la variante hero, la cabecera es la de siempre (listado, documentos).
   */
  public function testSinVarianteLaCabeceraNoCambia(): void {
    $html = $this->renderizar('unillanos:pagehead', ['title' => 'Programas', 'lead' => 'Bajada']);
    $this->assertMatchesRegularExpression('~<section [^>]*class="ua-pagehead">~', $html);
    foreach (['ua-pagehead--hero', 'ua-pagehead__fondo', 'ua-pagehead__migas', 'ua-pagehead__velo'] as $trozo) {
      $this->assertStringNotContainsString($trozo, $html, $trozo);
    }
    // Una foto sin la variante hero se ignora.
    $con = $this->renderizar('unillanos:pagehead', ['title' => 'Programas', 'image' => $this->foto()]);
    $this->assertStringNotContainsString('<img', $con);
  }

  /**
   * La imagen: texto alternativo, carga diferida por defecto y escape.
   */
  public function testMedia(): void {
    $html = $this->renderizar('unillanos:media', [
      'src' => '/a.jpg',
      'alt' => 'Un "texto" <alt>',
      'width' => 10,
      'height' => 5,
    ]);
    $this->assertStringContainsString('loading="lazy"', $html);
    $this->assertStringNotContainsString('fetchpriority', $html);
    $this->assertStringNotContainsString('<source', $html);
    $this->assertStringContainsString('alt="Un &quot;texto&quot; &lt;alt&gt;"', $html);
    $this->assertStringNotContainsString('aria-hidden', $html);
    $decorativa = $this->renderizar('unillanos:media', ['src' => '/a.jpg']);
    $this->assertStringContainsString('alt=""', $decorativa);
    $this->assertStringContainsString('aria-hidden="true"', $decorativa);
    $this->assertStringNotContainsString('width=', $decorativa);
  }

  /**
   * La imagen no imprime nada con un destino que no sea seguro.
   */
  public function testMediaFiltraElDestino(): void {
    foreach (['javascript:alert(1)', 'data:image/svg+xml,<svg/>', '//example.org/a.jpg', 'a.jpg'] as $src) {
      $html = $this->renderizar('unillanos:media', ['src' => $src]);
      $this->assertSame('', trim($html), $src);
    }
    $this->assertStringContainsString('src="https://example.org/a.jpg"', $this->renderizar('unillanos:media', ['src' => 'https://example.org/a.jpg']));
  }

  /**
   * Una lista de datos no imprime filas sin término o sin valor.
   */
  public function testKvListaOmiteLasFilasSinDato(): void {
    $html = $this->renderizar('unillanos:kv-list', [
      'items' => [
        ['term' => 'Nivel', 'value' => 'Pregrado'],
        ['term' => 'Sede', 'value' => ''],
        ['term' => '', 'value' => 'Sin término'],
        ['term' => 'Modalidad', 'value' => 'Presencial'],
      ],
    ]);
    $this->assertSame(2, substr_count($html, 'class="ua-kv__row"'));
    $this->assertStringContainsString('<dt class="ua-kv__term">Nivel</dt><dd class="ua-kv__value">Pregrado</dd>', $html);
    $this->assertStringNotContainsString('Sede', $html);
    $this->assertStringNotContainsString('Sin término', $html);
  }

  /**
   * Sin filas la lista no imprime nada, ni siquiera el dl.
   */
  public function testKvListaVaciaNoImprimeNada(): void {
    $this->assertSame('', trim($this->renderizar('unillanos:kv-list', ['items' => []])));
    $sin = $this->renderizar('unillanos:kv-list', ['items' => [['term' => 'Nivel', 'value' => '']]]);
    $this->assertSame('', trim($sin));
  }

  /**
   * El texto de la lista se escapa.
   */
  public function testKvListaEscapa(): void {
    $html = $this->renderizar('unillanos:kv-list', [
      'items' => [['term' => '<i>t</i>', 'value' => '<script>x</script>']],
    ]);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringNotContainsString('<i>', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
  }

  /**
   * El aside de la ficha: título, antetítulo y las filas con dato.
   */
  public function testFichaAside(): void {
    $html = $this->renderizar('unillanos:ficha', [
      'variant' => 'aside',
      'eyebrow' => 'Programa académico',
      'title' => 'Ficha del programa',
      'items' => [
        ['term' => 'Nivel', 'value' => 'Pregrado'],
        ['term' => 'Sede', 'value' => 'Sede Barcelona'],
      ],
    ]);
    $this->assertMatchesRegularExpression('~<aside [^>]*class="ua-ficha-aside"[^>]*aria-labelledby="ua-ficha-aside-titulo"~', $html);
    $this->assertStringContainsString('<span class="ua-eyebrow">Programa académico</span>', $html);
    $this->assertStringContainsString('<h2 class="ua-ficha-aside__title" id="ua-ficha-aside-titulo">Ficha del programa</h2>', $html);
    $this->assertSame(2, substr_count($html, 'class="ua-kv__row"'));
    $this->assertStringNotContainsString('class="ua-ficha"', $html);
  }

  /**
   * Una ficha sin datos no imprime el aside (ni su título).
   */
  public function testFichaVaciaNoImprimeElAside(): void {
    foreach ([[], [['term' => 'Nivel', 'value' => '']], [['term' => '', 'value' => 'x']]] as $items) {
      $html = $this->renderizar('unillanos:ficha', [
        'variant' => 'aside',
        'title' => 'Ficha del programa',
        'items' => $items,
      ]);
      $this->assertSame('', trim($html));
    }
  }

  /**
   * Sin variante la ficha sigue siendo la rejilla de siempre.
   */
  public function testFichaRejillaNoCambia(): void {
    $html = $this->renderizar('unillanos:ficha', ['items' => [['term' => 'Nivel', 'value' => 'Pregrado']]]);
    $this->assertStringContainsString('class="ua-ficha"', $html);
    $this->assertStringContainsString('<div><dt>Nivel</dt><dd>Pregrado</dd></div>', $html);
    $this->assertStringNotContainsString('<aside', $html);
  }

}
