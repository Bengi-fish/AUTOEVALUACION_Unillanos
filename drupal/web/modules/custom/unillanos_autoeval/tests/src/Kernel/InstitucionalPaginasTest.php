<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\unillanos_autoeval\Service\InstitucionalPagina;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Prueba el marcado de las páginas de «Institucional» (Fase 9, etapa 3).
 *
 * Renderiza cada plantilla con el tema `unillanos` y los datos reales de los
 * servicios: acordeones accesibles, filtros de normatividad, el tablero que
 * no se carga solo, enlaces externos y lápices solo para quien puede editar.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class InstitucionalPaginasTest extends InstitucionalBaseKernel {

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
   * Renderiza la página de «Institucional» con los datos reales.
   *
   * @return string
   *   El HTML.
   */
  protected function pagina(): string {
    $datos = $this->container->get(InstitucionalPagina::class)->datos(new CacheableMetadata());
    $construccion = ['#theme' => 'unillanos_institucional', '#datos' => $datos];
    return (string) $this->container->get('renderer')->renderInIsolation($construccion);
  }

  /**
   * Las seis rutas existen y las ve cualquier visitante; cinco redirigen.
   */
  public function testRutasPublicas(): void {
    $rutas = [
      'unillanos_autoeval.institucional' => '/institucional',
      'unillanos_autoeval.institucional_procesos' => '/institucional/procesos',
      'unillanos_autoeval.institucional_dependencias' => '/institucional/dependencias',
      'unillanos_autoeval.institucional_directivos' => '/institucional/directivos',
      'unillanos_autoeval.institucional_normatividad' => '/institucional/normatividad',
      'unillanos_autoeval.institucional_cifras' => '/institucional/cifras',
    ];
    $anonimo = $this->container->get('current_user');
    foreach ($rutas as $nombre => $ruta) {
      $this->assertSame($ruta, Url::fromRoute($nombre)->toString(), $nombre);
      $this->assertTrue(Url::fromRoute($nombre)->access($anonimo), $nombre);
    }
    // Las cinco rutas de la etapa 3 redirigen (301) a su sección.
    foreach (['procesos', 'dependencias', 'directivos', 'normatividad', 'cifras'] as $modulo) {
      $respuesta = $this->container->get('http_kernel')->handle(Request::create('/institucional/' . $modulo));
      $this->assertSame(301, $respuesta->getStatusCode(), $modulo);
      $this->assertSame('/institucional#' . $modulo, parse_url((string) $respuesta->headers->get('Location'), PHP_URL_PATH) . '#' . parse_url((string) $respuesta->headers->get('Location'), PHP_URL_FRAGMENT), $modulo);
    }
  }

  /**
   * La página: cabecera de programa, un h1, migas y navegación por secciones.
   */
  public function testPaginaConNavegacionPorSecciones(): void {
    $this->config('unillanos_autoeval.micrositio')->set('institucional', ['introduccion' => 'Frase de prueba.'])->save();
    $otra = $this->crearNodo('dependencia', ['title' => 'Admisiones', 'field_orden' => 2]);
    $this->enlace('Formato', ['field_dependencia' => $otra->id()]);
    $html = $this->pagina();
    $this->assertSame(1, substr_count($html, '<h1'));
    $this->assertStringContainsString('Universidad de los', $html);
    $this->assertStringContainsString('Frase de prueba.', $html);
    $this->assertStringContainsString('ua-pagehead--hero', $html);
    $this->assertStringContainsString('aria-label="Ruta de navegación"', $html);
    // Cada sección con contenido lleva su h2 y su ancla; la del programa no.
    $this->assertSame(1, preg_match_all('~<h2[^>]*>~', $html));
    $this->assertStringContainsString('id="dependencias"', $html);
    foreach (['procesos', 'directivos', 'normatividad', 'cifras', 'autoevaluacion', 'plan'] as $ausente) {
      $this->assertStringNotContainsString('id="' . $ausente . '"', $html, $ausente);
    }
  }

  /**
   * Con varias secciones hay navegación interna; con una, no.
   */
  public function testLaNavegacionInternaSoloConVariasSecciones(): void {
    $dep = $this->crearNodo('dependencia', ['title' => 'Biblioteca', 'field_orden' => 1]);
    $this->enlace('Formato', ['field_dependencia' => $dep->id()]);
    $html = $this->pagina();
    $this->assertStringNotContainsString('aria-label="Secciones de Institucional"', $html);
    $this->config('unillanos_autoeval.micrositio')->set('institucional', [
      'cifras' => ['enlaces' => [['titulo' => 'Cifras MEN', 'texto' => 'Texto', 'url' => 'https://example.org/c']]],
    ])->save();
    $html = $this->pagina();
    $this->assertSame(1, preg_match('~<nav [^>]*aria-label="Secciones de Institucional">.*?</nav>~s', $html, $nav));
    $this->assertStringContainsString('href="#dependencias"', $nav[0]);
    $this->assertStringContainsString('href="#cifras"', $nav[0]);
    $this->assertStringContainsString('id="cifras"', $html);
  }

  /**
   * Dependencias: un botón por dependencia con aria-expanded y su panel.
   */
  public function testDependenciasAcordeon(): void {
    $dep = $this->crearNodo('dependencia', ['title' => 'Biblioteca', 'field_orden' => 1]);
    $otra = $this->crearNodo('dependencia', ['title' => 'Admisiones', 'field_orden' => 2]);
    $programa = $this->crearNodo('programa', ['title' => 'Mercadeo', 'field_clave' => 'm', 'field_nivel' => 'pregrado']);
    $this->enlace('Mercadeo', ['field_dependencia' => $dep->id(), 'field_programa' => $programa->id()]);
    $this->enlace('Biología', ['field_dependencia' => $otra->id()]);
    $html = $this->pagina();
    $this->assertSame(2, substr_count($html, 'data-ua-acc-btn'));
    $this->assertSame(2, substr_count($html, 'aria-expanded="true"'));
    $this->assertSame(2, substr_count($html, 'role="region"'));
    // Cada botón controla un panel que existe.
    preg_match_all('/aria-controls="([^"]+)"/', $html, $controles);
    $this->assertCount(2, $controles[1]);
    foreach ($controles[1] as $id) {
      $this->assertStringContainsString('id="' . $id . '"', $html);
    }
    // El buscador nace oculto; los enlaces son externos y seguros.
    $this->assertStringContainsString('data-ua-acc-tools hidden', $html);
    $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    $this->assertStringContainsString('se abre en una pestaña nueva', $html);
    $this->assertStringContainsString('Biblioteca', $html);
    // Los formatos van agrupados por nivel, con su título de grupo.
    $this->assertStringContainsString('ua-acc__group">Pregrados', $html);
    $this->assertStringContainsString('ua-acc__group">Otros', $html);
  }

  /**
   * Sin contenido la página lo dice una vez y no pinta ninguna sección.
   */
  public function testPaginaVacia(): void {
    $html = $this->pagina();
    $this->assertSame(1, substr_count($html, '<h1'));
    $this->assertStringContainsString('Todavía no hay información institucional publicada.', $html);
    $this->assertStringNotContainsString('data-ua-acc-btn', $html);
    $this->assertStringNotContainsString('aria-label="Secciones de Institucional"', $html);
    $secciones = ['autoevaluacion', 'plan', 'documentos', 'procesos', 'dependencias', 'directivos', 'normatividad'];
    foreach (array_merge($secciones, ['cifras']) as $seccion) {
      $this->assertStringNotContainsString('id="' . $seccion . '"', $html, $seccion);
    }
    // Ya no hay mensajes de vacío por módulo.
    $this->assertStringNotContainsString('Todavía no hay dependencias publicadas.', $html);
    $this->assertStringNotContainsString('Todavía no hay normas publicadas.', $html);
    $this->assertStringNotContainsString('Todavía no hay cifras publicadas.', $html);
  }

  /**
   * Directivos: tarjeta directa con un documento y a una lista con varios.
   */
  public function testDirectivos(): void {
    $una = $this->facultad('Facultad Una', 'FU');
    $varias = $this->facultad('Facultad Varias', 'FV');
    $this->enlace('Uno', ['field_facultad' => $una->id()], 'https://drive.google.com/drive/folders/una');
    $this->enlace('Varios 1', ['field_facultad' => $varias->id()]);
    $this->enlace('Varios 2', ['field_facultad' => $varias->id()]);
    $html = $this->pagina();
    $this->assertStringContainsString('href="https://drive.google.com/drive/folders/una" target="_blank" rel="noopener noreferrer"', $html);
    $this->assertStringContainsString('href="#facultad-' . $varias->id() . '"', $html);
    $this->assertStringContainsString('id="facultad-' . $varias->id() . '"', $html);
    $this->assertStringContainsString('Varios 2', $html);
  }

  /**
   * Normatividad: siete columnas, filtros ocultos y la fila con sus datos.
   */
  public function testNormatividadTabla(): void {
    $this->crearNodo('norma', [
      'title' => 'Ley 30 de 1992',
      'field_origen_norma' => 'externo',
      'field_tipo_norma' => 'Ley',
      'field_numero_fecha' => '30 de 1992',
      'field_entidad' => 'Congreso',
      'field_epigrafe' => 'Por la cual <b>se organiza</b>',
      'field_capitulos' => 'Toda',
      'field_aplicacion' => 'Aplica',
    ]);
    $html = $this->pagina();
    $this->assertSame(7, substr_count($html, '<th scope="col">'));
    $this->assertSame(1, substr_count($html, 'data-ua-norm-fila'));
    $this->assertStringContainsString('data-ua-norm-filtros hidden', $html);
    $this->assertStringContainsString('data-ua-norm-origen', $html);
    $this->assertStringContainsString('data-ua-norm-tipo', $html);
    $this->assertStringContainsString('Se muestra 1 norma.', $html);
    // Etiquetas de cada dato para la vista de tarjetas.
    $this->assertStringContainsString('data-label="Entidad que la expide"', $html);
    // El texto se escapa: nada de HTML de usuario.
    $this->assertStringNotContainsString('<b>se organiza</b>', $html);
    $this->assertStringContainsString('&lt;b&gt;se organiza&lt;/b&gt;', $html);
  }

  /**
   * Cifras: ningún iframe en el HTML inicial; botón y enlace externo.
   */
  public function testCifrasSinIframeInicial(): void {
    $this->config('unillanos_autoeval.micrositio')->set('institucional', [
      'cifras' => [
        'enlaces' => [['titulo' => 'SNIES', 'texto' => 'Consulta', 'url' => 'https://example.org/a']],
        'tablero_url' => 'https://public.tableau.com/views/Tablero/Hoja1',
      ],
    ])->save();
    $html = $this->pagina();
    $this->assertStringNotContainsString('<iframe', $html);
    $this->assertStringContainsString('data-ua-embed-cargar', $html);
    $this->assertStringContainsString('Cargar el tablero', $html);
    $this->assertStringContainsString('Abrir en Tableau Public', $html);
    $this->assertStringContainsString('El tablero es de otro sitio', $html);
    $this->assertStringContainsString('data-url="https://public.tableau.com/views/Tablero/Hoja1?:embed=y', $html);
    // El enlace de cifras abre en pestaña nueva con aviso.
    $this->assertStringContainsString('href="https://example.org/a" target="_blank" rel="noopener noreferrer"', $html);
    $this->assertStringContainsString('se abre en una pestaña nueva', $html);
  }

  /**
   * Procesos: acordeón de sedes y el proceso principal con sus documentos.
   */
  public function testProcesos(): void {
    $sede = $this->container->get('entity_type.manager')->getStorage('taxonomy_term')
      ->create(['vid' => 'sede', 'name' => 'Sede Granada']);
    $sede->save();
    $principal = $this->crearNodo('proceso', [
      'title' => 'Acreditación institucional',
      'field_sede' => $sede->id(),
      'field_tipo_proceso' => 'acreditacion',
    ]);
    $cond = $this->crearNodo('proceso', [
      'title' => 'Condiciones Granada',
      'field_sede' => $sede->id(),
      'field_tipo_proceso' => 'registro_calificado',
    ]);
    $this->enlace('Informe', ['field_proceso' => $principal->id(), 'field_categoria' => 'informe']);
    $this->enlace('Documento de condiciones', ['field_proceso' => $cond->id(), 'field_categoria' => 'informe']);
    $html = $this->pagina();
    $this->assertStringContainsString('Acreditación institucional', $html);
    $this->assertStringContainsString('Condiciones institucionales por sede', $html);
    $this->assertSame(1, substr_count($html, 'data-ua-acc-btn'));
    $this->assertStringContainsString('Documento de condiciones', $html);
    // Sin valoraciones no hay factores ni plan (lo vacío se omite).
    $this->assertStringNotContainsString('Factores y valoración', $html);
  }

  /**
   * Los lápices y los «+» solo los ve quien puede editar.
   */
  public function testEdicionSoloConPermiso(): void {
    $this->crearNodo('dependencia', ['title' => 'Biblioteca', 'field_orden' => 1]);
    $this->crearNodo('norma', [
      'title' => 'Ley',
      'field_origen_norma' => 'externo',
      'field_tipo_norma' => 'Ley',
      'field_epigrafe' => 'x',
    ]);

    $anonimo = $this->pagina();
    $this->assertStringNotContainsString('data-ua-edit', $anonimo);
    $anonimo = $this->pagina();
    $this->assertStringNotContainsString('data-ua-edit', $anonimo);

    $this->setUpCurrentUser([], [
      'access content',
      'view media',
      'access acreditacion panel',
      'create dependencia content',
      'edit any dependencia content',
      'create norma content',
      'edit any norma content',
    ]);
    $html = $this->pagina();
    $this->assertStringContainsString('Añadir una dependencia', $html);
    $this->assertStringContainsString('Editar Biblioteca', $html);
    $html = $this->pagina();
    $this->assertStringContainsString('Añadir una norma', $html);
    $this->assertStringContainsString('Editar la norma Ley', $html);
    // Sin permiso sobre normas, esa página no muestra «Añadir una norma».
    $this->setUpCurrentUser([], [
      'access content',
      'view media',
      'access acreditacion panel',
      'create dependencia content',
    ]);
    $html = $this->pagina();
    $this->assertStringNotContainsString('Añadir una norma', $html);
  }

}
