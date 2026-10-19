<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\unillanos_autoeval\Controller\ProgramasController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Prueba la página /programas: controlador, plantilla y caché.
 *
 * Renderiza la página con el tema `unillanos` para un visitante anónimo y
 * revisa el HTML y los metadatos de caché de cada caso.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class ProgramasPaginaTest extends UnillanosKernelTestBase {

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
    'entity_reference_revisions',
    'paragraphs',
    'unillanos_autoeval',
  ];

  /**
   * Facultades de la prueba.
   *
   * @var \Drupal\taxonomy\Entity\Term[]
   */
  protected array $facultades = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpCurrentUser([], ['access content']);
    $this->container->get('theme_installer')->install(['unillanos']);
    $this->config('system.theme')->set('default', 'unillanos')->save();
    $this->container->get('theme.manager')->setActiveTheme(
      $this->container->get('theme.initialization')->initTheme('unillanos')
    );
    Vocabulary::create(['vid' => 'facultad', 'name' => 'facultad'])->save();
    $this->crearCampo('node', 'proceso', 'field_periodo_evaluado', 'string');
    $this->crearCampo('node', 'proceso', 'field_resultado', 'string');
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_tipo_elemento', 'string');
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_numero', 'integer');
    $this->crearCampo('node', 'hallazgo', 'field_tipo_hallazgo', 'string');
    $this->crearCampo('node', 'programa', 'field_facultad', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_sede', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_nivel', 'list_string', [
      'allowed_values' => ['pregrado' => 'Pregrado', 'maestria' => 'Maestría'],
    ]);
    foreach (['Facultad de Salud', 'Facultad de Humanas'] as $nombre) {
      $facultad = Term::create(['vid' => 'facultad', 'name' => $nombre]);
      $facultad->save();
      $this->facultades[] = $facultad;
    }
  }

  /**
   * Crea programas de dos facultades y dos niveles.
   *
   * @param int $cantidad
   *   Cuántos crear.
   */
  protected function crearProgramas(int $cantidad): void {
    for ($i = 1; $i <= $cantidad; $i++) {
      $this->crearNodo('programa', [
        'title' => sprintf('Programa %02d', $i),
        'field_clave' => 'programa-' . $i,
        'field_facultad' => $this->facultades[$i % 2]->id(),
        'field_nivel' => $i % 4 === 0 ? 'maestria' : 'pregrado',
      ]);
    }
  }

  /**
   * Renderiza la página con una URL de búsqueda.
   *
   * @param array<string, mixed> $consulta
   *   Parámetros de la URL.
   *
   * @return array{html: string, build: array<string, mixed>}
   *   El HTML y el arreglo de render del controlador.
   */
  protected function pagina(array $consulta = []): array {
    $controlador = ProgramasController::create($this->container);
    $build = $controlador->programas(Request::create('/programas', 'GET', $consulta));
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    return ['html' => $html, 'build' => $build];
  }

  /**
   * Cuántas tarjetas ocultas (hidden) trae el HTML.
   */
  protected function ocultas(string $html): int {
    return preg_match_all('~<li hidden data-ua-prog-item~', $html);
  }

  /**
   * Sin programas: la cabecera y el aviso, sin controles.
   */
  public function testSinProgramas(): void {
    $html = $this->pagina()['html'];
    $this->assertSame(1, substr_count($html, '<h1'));
    $this->assertStringContainsString('Encuentra un', $html);
    $this->assertStringContainsString('Todavía no hay programas publicados.', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertStringNotContainsString('data-ua-prog-item', $html);
  }

  /**
   * Un programa con autoevaluación: una tarjeta con su marca.
   */
  public function testUnProgramaConAutoevaluacion(): void {
    $programa = $this->crearNodo('programa', ['title' => 'Electrónica', 'field_clave' => 'electronica']);
    $this->crearNodo('proceso', ['title' => 'Proceso', 'field_programa' => $programa->id()]);
    $html = $this->pagina()['html'];
    $this->assertSame(1, substr_count($html, 'data-ua-prog-item'));
    $this->assertStringContainsString('Se muestra 1 programa.', $html);
    $this->assertSame(1, substr_count($html, 'Con autoevaluación'));
    $this->assertSame(1, substr_count($html, '<h1'));
    $this->assertStringContainsString('<h2 class="ua-prog__name"', $html);
  }

  /**
   * Cuarenta programas sin búsqueda: todos visibles y sin ocultos.
   */
  public function testCuarentaProgramasSinFiltro(): void {
    $this->crearProgramas(40);
    $pagina = $this->pagina();
    $html = $pagina['html'];
    $this->assertSame(40, substr_count($html, 'data-ua-prog-item'));
    $this->assertSame(0, $this->ocultas($html));
    $this->assertStringContainsString('Se muestran 40 programas.', $html);
    $this->assertStringNotContainsString(' de 40', $html);
    $this->assertSame(1, substr_count($html, '<h1'));
    $this->assertStringContainsString('name="facultad"', $html);
    $this->assertStringContainsString('name="nivel"', $html);
    $this->assertStringNotContainsString('name="sede"', $html);
    // Sin búsqueda: caché permanente.
    $this->assertSame(Cache::PERMANENT, $pagina['build']['#cache']['max-age']);
  }

  /**
   * Con texto: la lista no se recorta, los que no coinciden van ocultos.
   */
  public function testConTextoMarcaOcultosSinRecortar(): void {
    $this->crearProgramas(40);
    $pagina = $this->pagina(['q' => 'programa 07']);
    $html = $pagina['html'];
    $this->assertSame(40, substr_count($html, 'data-ua-prog-item'));
    $this->assertSame(39, $this->ocultas($html));
    $this->assertStringContainsString('Se muestra 1 programa de 40.', $html);
    $this->assertStringContainsString('value="programa 07"', $html);
    $this->assertSame(1, substr_count($html, '<h1'));
    // Con texto: la respuesta caduca (3600 s).
    $this->assertSame(ProgramasController::MAX_AGE_CON_TEXTO, $pagina['build']['#cache']['max-age']);
  }

  /**
   * Con filtros válidos: se combinan, sin recortar y con caché permanente.
   */
  public function testFiltrosValidos(): void {
    $this->crearProgramas(40);
    $salud = (string) $this->facultades[0]->id();
    $pagina = $this->pagina(['facultad' => $salud, 'nivel' => 'maestria']);
    $html = $pagina['html'];
    $this->assertSame(40, substr_count($html, 'data-ua-prog-item'));
    // Facultad 0 son los pares y maestría los múltiplos de 4: 10 coinciden.
    $this->assertSame(30, $this->ocultas($html));
    $this->assertStringContainsString('Se muestran 10 programas de 40.', $html);
    $this->assertMatchesRegularExpression('~<option value="' . $salud . '" selected>~', $html);
    $this->assertMatchesRegularExpression('~<option value="maestria" selected>~', $html);
    $this->assertSame(Cache::PERMANENT, $pagina['build']['#cache']['max-age']);
  }

  /**
   * Valores inválidos: se ignoran, sin ocultar nada y sin guardar caché.
   */
  public function testValoresInvalidosSeIgnoran(): void {
    $this->crearProgramas(12);
    $casos = [
      ['facultad' => 'xyz'],
      ['nivel' => ['a']],
      ['nivel' => 'doctorado'],
      ['sede' => '999'],
      ['q' => ['x']],
      ['facultad' => ['0' => 'a', '1' => ['b']]],
    ];
    foreach ($casos as $consulta) {
      $pagina = $this->pagina($consulta);
      $this->assertSame(12, substr_count($pagina['html'], 'data-ua-prog-item'), json_encode($consulta));
      $this->assertSame(0, $this->ocultas($pagina['html']), json_encode($consulta));
      $this->assertStringContainsString('Se muestran 12 programas.', $pagina['html']);
      $this->assertSame(0, $pagina['build']['#cache']['max-age'], json_encode($consulta));
      $this->assertSame(1, substr_count($pagina['html'], '<h1'));
    }
  }

  /**
   * Un texto de 500 caracteres se recorta a 100.
   */
  public function testTextoLargoSeRecorta(): void {
    $this->crearProgramas(3);
    $html = $this->pagina(['q' => str_repeat('a', 500)])['html'];
    $this->assertSame(1, preg_match('~name="q" value="([^"]*)"~', $html, $coincidencia));
    $this->assertSame(100, strlen($coincidencia[1]));
    $this->assertStringContainsString('maxlength="100"', $html);
    $this->assertSame(3, substr_count($html, 'data-ua-prog-item'));
  }

  /**
   * Un texto con etiquetas no se refleja sin escapar.
   */
  public function testTextoConEtiquetaSeEscapa(): void {
    $this->crearProgramas(3);
    $html = $this->pagina(['q' => '"><script>alert(1)</script>'])['html'];
    $this->assertStringNotContainsString('<script>alert', $html);
    $this->assertStringNotContainsString('"><script', $html);
    $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $html);
    $this->assertSame(3, $this->ocultas($html));
  }

  /**
   * La página declara un contexto por parámetro, no el genérico.
   */
  public function testContextosDeCache(): void {
    $this->crearProgramas(2);
    $contextos = $this->pagina()['build']['#cache']['contexts'];
    foreach (['q', 'facultad', 'nivel', 'sede'] as $parametro) {
      $this->assertContains('url.query_args:' . $parametro, $contextos);
    }
    $this->assertNotContains('url.query_args', $contextos);
    $this->assertContains('user.permissions', $contextos);
    $etiquetas = $this->pagina()['build']['#cache']['tags'];
    $this->assertContains('node_list:programa', $etiquetas);
    $this->assertContains('node_list:proceso', $etiquetas);
  }

  /**
   * La ruta /programas responde 200 a un visitante anónimo.
   */
  public function testLaRutaRespondeParaAnonimo(): void {
    $this->crearProgramas(5);
    $respuesta = $this->container->get('http_kernel')->handle(Request::create('/programas'));
    $this->assertSame(200, $respuesta->getStatusCode());
    $html = (string) $respuesta->getContent();
    // La cabecera de la página trae su título. En esta prueba no hay bloques
    // colocados y Drupal imprime además su título de reserva (page-title); en
    // el sitio, con sus bloques, el h1 es solo el de la cabecera (se comprueba
    // sobre el sitio real en los datos del paso).
    $this->assertSame(1, substr_count($html, 'class="ua-pagehead__title"'));
    $this->assertSame(5, substr_count($html, 'data-ua-prog-item'));
    $invalida = $this->container->get('http_kernel')->handle(Request::create('/programas?facultad=xyz&nivel[]=a'));
    $this->assertSame(200, $invalida->getStatusCode());
  }

}
