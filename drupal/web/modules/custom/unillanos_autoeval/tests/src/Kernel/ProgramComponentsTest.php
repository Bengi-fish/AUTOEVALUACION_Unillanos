<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el marcado de los componentes program-card y program-search.
 *
 * Renderiza los componentes del tema `unillanos` y revisa lo que imprimen:
 * lo vacío se omite, la marca solo sale con `evaluated`, el enlace se nombra
 * por el programa, el contador concuerda con la cantidad y el servidor
 * oculta (hidden) lo que no coincide.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class ProgramComponentsTest extends KernelTestBase {

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
   *   Por ejemplo `unillanos:program-card`.
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
   * La tarjeta completa: un solo enlace, nombrado por el programa.
   */
  public function testTarjetaCompleta(): void {
    $html = $this->renderizar('unillanos:program-card', [
      'name' => 'Ingeniería Electrónica',
      'id' => 18,
      'url' => '/programas/ingenieria-electronica',
      'faculty' => 'Facultad de Ciencias Básicas e Ingeniería',
      'level' => 'Pregrado',
      'campus' => 'Sede Barcelona',
      'evaluated' => TRUE,
    ]);
    $this->assertSame(1, substr_count($html, '<a '));
    $this->assertStringContainsString('href="/programas/ingenieria-electronica"', $html);
    $this->assertStringContainsString('aria-labelledby="ua-prog-18-n"', $html);
    $this->assertStringContainsString('aria-describedby="ua-prog-18-f ua-prog-18-e"', $html);
    $this->assertStringContainsString('id="ua-prog-18-n"', $html);
    $this->assertStringContainsString('id="ua-prog-18-f"', $html);
    $this->assertStringContainsString('id="ua-prog-18-e"', $html);
    $this->assertStringContainsString('Facultad de Ciencias Básicas e Ingeniería', $html);
    $this->assertStringNotContainsString('Facultad de Facultad', $html);
    $this->assertStringContainsString('Con autoevaluación', $html);
    $this->assertStringContainsString('Sede Barcelona', $html);
    $this->assertStringContainsString('Pregrado', $html);
    $this->assertMatchesRegularExpression('~<h3 class="ua-prog__name" id="ua-prog-18-n">Ingeniería Electrónica</h3>~', $html);
  }

  /**
   * Lo vacío se omite y la marca solo sale con evaluated.
   */
  public function testTarjetaOmiteLoVacio(): void {
    $html = $this->renderizar('unillanos:program-card', [
      'name' => 'Enfermería',
      'id' => 5,
      'url' => '/programas/enfermeria',
      'level' => 'Pregrado',
      'evaluated' => FALSE,
    ]);
    $this->assertStringNotContainsString('Con autoevaluación', $html);
    $this->assertStringNotContainsString('ua-prog__faculty', $html);
    $this->assertStringNotContainsString('Sede', $html);
    $this->assertStringNotContainsString('endiente', $html);
    $this->assertStringContainsString('aria-describedby="ua-prog-5-e"', $html);

    $minimo = $this->renderizar('unillanos:program-card', ['name' => 'Solo nombre']);
    $this->assertStringNotContainsString('ua-prog__meta', $minimo);
    $this->assertStringNotContainsString('href', $minimo);
    $this->assertStringNotContainsString('aria-labelledby', $minimo);
    $this->assertStringNotContainsString('aria-describedby', $minimo);
  }

  /**
   * Un destino inseguro o un id inválido no se imprimen.
   */
  public function testTarjetaFiltraDestinoInseguro(): void {
    $html = $this->renderizar('unillanos:program-card', [
      'name' => 'Programa',
      'id' => 'a" onclick="x',
      'url' => 'javascript:alert(1)',
    ]);
    $this->assertStringNotContainsString('href', $html);
    $this->assertStringNotContainsString('onclick', $html);
    $this->assertStringNotContainsString('aria-labelledby', $html);
  }

  /**
   * Un programa por tarjeta con los datos de filtro y el contador concuerda.
   */
  public function testBuscadorConcuerdaConLaCantidad(): void {
    $uno_evaluado = [
      'name' => 'Uno',
      'id' => 1,
      'url' => '/uno',
      'faculty' => 'Facultad A',
      'faculty_id' => '1',
      'level' => 'Pregrado',
      'level_key' => 'pregrado',
      'search' => 'uno facultad a pregrado',
      'evaluated' => TRUE,
    ];
    $dos = [
      'name' => 'Dos',
      'id' => 2,
      'url' => '/dos',
      'faculty' => 'Facultad B',
      'faculty_id' => '2',
      'level' => 'Maestría',
      'level_key' => 'maestria',
      'search' => 'dos facultad b maestria',
    ];
    $programas = [$uno_evaluado, $dos];
    $filtros = [
      'faculty' => [['value' => '1', 'label' => 'Facultad A'], ['value' => '2', 'label' => 'Facultad B']],
      'level' => [['value' => 'pregrado', 'label' => 'Pregrado'], ['value' => 'maestria', 'label' => 'Maestría']],
    ];
    $uno = $this->renderizar('unillanos:program-search', ['programs' => [$uno_evaluado], 'filters' => $filtros]);
    $this->assertStringContainsString('Se muestra 1 programa.', $uno);
    $this->assertStringNotContainsString('Se muestran', $uno);
    $this->assertSame(1, substr_count($uno, 'data-ua-prog-item'));

    $varios = $this->renderizar('unillanos:program-search', ['programs' => $programas, 'filters' => $filtros]);
    $this->assertStringContainsString('Se muestran 2 programas.', $varios);
    $this->assertStringContainsString('data-search="dos facultad b maestria"', $varios);
    $this->assertStringContainsString('data-faculty="2"', $varios);
    $this->assertStringContainsString('data-level="maestria"', $varios);
    // Sede sin opciones: ese filtro no se imprime.
    $this->assertStringContainsString('name="facultad"', $varios);
    $this->assertStringContainsString('name="nivel"', $varios);
    $this->assertStringNotContainsString('name="sede"', $varios);
    // Etiquetas visibles y región de estado.
    $this->assertMatchesRegularExpression('~<label class="ua-searchbar__lab" for="prog-q">Buscar un programa</label>~', $varios);
    $this->assertMatchesRegularExpression('~<label class="ua-searchbar__lab" for="prog-facultad">Facultad</label>~', $varios);
    $this->assertStringContainsString('role="status" aria-live="polite" aria-atomic="true"', $varios);
    $this->assertStringContainsString('method="get"', $varios);
    $this->assertStringContainsString('name="q"', $varios);
    // La marca solo en el programa evaluado; ids de tarjeta únicos.
    $this->assertSame(1, substr_count($varios, 'Con autoevaluación'));
    $this->assertStringContainsString('id="ua-prog-1-n"', $varios);
    $this->assertStringContainsString('id="ua-prog-2-n"', $varios);
    // Sin filtro activo no hay «Quitar filtros» visible.
    $this->assertMatchesRegularExpression('~data-ua-prog-clear[^>]*hidden~', $varios);
  }

  /**
   * Cero programas: sin controles, solo el aviso.
   */
  public function testBuscadorSinProgramas(): void {
    $html = $this->renderizar('unillanos:program-search', ['programs' => []]);
    $this->assertStringContainsString('Todavía no hay programas publicados.', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertStringNotContainsString('data-ua-programas', $html);
  }

  /**
   * Lo que el servidor oculta sale con hidden y el contador dice «de M».
   */
  public function testBuscadorDelServidorOcultaLoQueNoCoincide(): void {
    $programas = [
      ['name' => 'Ingeniería Electrónica', 'id' => 1, 'search' => 'ingenieria electronica'],
      ['name' => 'Enfermería', 'id' => 2, 'search' => 'enfermeria', 'hidden' => TRUE],
      ['name' => 'Biología', 'id' => 3, 'search' => 'biologia', 'hidden' => TRUE],
    ];
    $html = $this->renderizar('unillanos:program-search', [
      'programs' => $programas,
      'total' => 3,
      'values' => ['q' => 'ingenieria'],
    ]);
    $this->assertStringContainsString('Se muestra 1 programa de 3.', $html);
    $this->assertSame(2, preg_match_all('~<li hidden data-ua-prog-item~', $html));
    $this->assertStringContainsString('value="ingenieria"', $html);
    // Con un filtro activo «Quitar filtros» se ve (sin hidden).
    $this->assertDoesNotMatchRegularExpression('~data-ua-prog-clear[^>]*hidden~', $html);
    // El texto escrito se escapa.
    $peligroso = $this->renderizar('unillanos:program-search', [
      'programs' => $programas,
      'values' => ['q' => '"><script>alert(1)</script>'],
    ]);
    $this->assertStringNotContainsString('<script>', $peligroso);
  }

  /**
   * Sin resultados visibles se muestra el mensaje de «sin resultados».
   */
  public function testBuscadorTodoOcultoMuestraMensaje(): void {
    $html = $this->renderizar('unillanos:program-search', [
      'programs' => [['name' => 'Uno', 'id' => 1, 'search' => 'uno', 'hidden' => TRUE]],
      'total' => 1,
      'values' => ['q' => 'zzz'],
    ]);
    $this->assertStringContainsString('Se muestran 0 programas de 1.', $html);
    $this->assertMatchesRegularExpression('~data-ua-prog-empty>No encontramos programas~', $html);
  }

}
