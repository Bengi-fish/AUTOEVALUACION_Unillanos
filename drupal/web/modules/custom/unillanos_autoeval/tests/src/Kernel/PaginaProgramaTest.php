<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Prueba la página de un programa sin autoevaluación y su bajada.
 *
 * Renderiza la página canónica del nodo con el tema `unillanos` para un
 * visitante anónimo.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class PaginaProgramaTest extends UnillanosKernelTestBase {

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
   * Facultades de la prueba (0 = Salud, 1 = Humanas).
   *
   * @var \Drupal\taxonomy\Entity\Term[]
   */
  protected array $facultades = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // La página del programa lee los documentos (medios).
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->setUpCurrentUser([], ['access content', 'view media']);
    $tipo = MediaType::create(['id' => 'documento', 'label' => 'Documento', 'source' => 'file']);
    $tipo->save();
    $campo = $tipo->getSource()->createSourceField($tipo);
    $almacen = $campo->getFieldStorageDefinition();
    if ($almacen instanceof FieldStorageConfig) {
      $almacen->save();
    }
    $campo->save();
    $tipo->set('source_configuration', ['source_field' => $campo->getName()])->save();
    $this->crearCampo('media', 'documento', 'field_categoria', 'list_string', [
      'allowed_values' => ['informe' => 'Informe'],
    ]);
    $this->crearCampo('media', 'documento', 'field_fecha_publicacion', 'string');
    $this->crearCampo('media', 'documento', 'field_proceso', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('media', 'documento', 'field_lineamiento', 'entity_reference', ['target_type' => 'node']);
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
    foreach (['Facultad de Salud', 'Facultad de Humanas'] as $nombre) {
      $facultad = Term::create(['vid' => 'facultad', 'name' => $nombre]);
      $facultad->save();
      $this->facultades[] = $facultad;
    }
  }

  /**
   * Crea un programa.
   *
   * @param string $nombre
   *   Título.
   * @param int|null $facultad
   *   Índice de $this->facultades, o NULL sin facultad.
   * @param array<string, mixed> $extra
   *   Valores de campos.
   */
  protected function programa(string $nombre, ?int $facultad = 0, array $extra = []): Node {
    $valores = $extra + [
      'title' => $nombre,
      'field_clave' => strtolower(str_replace(' ', '-', $nombre)),
      'field_nivel' => 'pregrado',
    ];
    if ($facultad !== NULL) {
      $valores['field_facultad'] = $this->facultades[$facultad]->id();
    }
    return $this->crearNodo('programa', $valores);
  }

  /**
   * Crea varios programas más de la misma facultad.
   *
   * @param int $cantidad
   *   Cuántos.
   */
  protected function otros(int $cantidad): void {
    for ($i = 1; $i <= $cantidad; $i++) {
      $this->programa('Otro ' . $i);
    }
  }

  /**
   * El HTML de la página canónica del programa para un anónimo.
   */
  protected function pagina(Node $programa): string {
    $respuesta = $this->container->get('http_kernel')->handle(Request::create('/node/' . $programa->id()));
    $this->assertSame(200, $respuesta->getStatusCode());
    return (string) $respuesta->getContent();
  }

  /**
   * Con cinco otros programas: salen cuatro, el título en plural y los enlaces.
   */
  public function testSinProcesoMuestraCuatroRelacionados(): void {
    $actual = $this->programa('Enfermería');
    $this->otros(5);
    $html = $this->pagina($actual);
    $this->assertSame(1, substr_count($html, 'class="ua-pagehead__title"'));
    $this->assertStringContainsString('Otros programas de la facultad', $html);
    $this->assertStringContainsString('Facultad de Salud', $html);
    $this->assertSame(4, substr_count($html, 'class="ua-prog"'));
    // Las tarjetas son h3 bajo el título de la sección (h2).
    $this->assertSame(4, substr_count($html, '<h3 class="ua-prog__name"'));
    $this->assertSame(0, substr_count($html, '<h2 class="ua-prog__name"'));
    $this->assertStringContainsString('href="/programas"', $html);
    $this->assertStringContainsString('href="/documentos"', $html);
    $this->assertStringContainsString('Ver todos los programas', $html);
    $this->assertStringContainsString('Ver documentos', $html);
    // El programa actual no se lista a sí mismo.
    $this->assertSame(0, substr_count($html, 'ua-prog__name" id="ua-prog-' . $actual->id() . '-n"'));
    // Sin ninguna sección no hay bajada ni etiquetas de pendiente.
    $this->assertStringNotContainsString('ua-pagehead__lead', $html);
    $this->assertStringNotContainsString('endiente', $html);
  }

  /**
   * Con un solo otro programa el título va en singular.
   */
  public function testUnSoloOtroProgramaTituloEnSingular(): void {
    $actual = $this->programa('Enfermería');
    $this->otros(1);
    $html = $this->pagina($actual);
    $this->assertStringContainsString('Otro programa de la facultad', $html);
    $this->assertStringNotContainsString('Otros programas de la facultad', $html);
    $this->assertSame(1, substr_count($html, 'class="ua-prog"'));
  }

  /**
   * Con dos otros programas el título va en plural.
   */
  public function testDosOtrosProgramasTituloEnPlural(): void {
    $actual = $this->programa('Enfermería');
    $this->otros(2);
    $html = $this->pagina($actual);
    $this->assertStringContainsString('Otros programas de la facultad', $html);
    $this->assertSame(2, substr_count($html, 'class="ua-prog"'));
  }

  /**
   * Con ningún otro programa: sin sección, pero con los enlaces.
   */
  public function testSinOtrosProgramasSoloLosEnlaces(): void {
    $actual = $this->programa('Enfermería');
    // Programas de otra facultad no cuentan.
    $this->programa('De otra facultad', 1);
    $html = $this->pagina($actual);
    $this->assertStringNotContainsString('de la facultad', $html);
    $this->assertSame(0, substr_count($html, 'class="ua-prog"'));
    $this->assertStringContainsString('href="/programas"', $html);
    $this->assertStringContainsString('href="/documentos"', $html);
  }

  /**
   * Sin facultad: sin sección de relacionados y con los enlaces.
   */
  public function testSinFacultadSoloLosEnlaces(): void {
    $actual = $this->programa('Sin facultad', NULL);
    $this->programa('Otro con facultad');
    $html = $this->pagina($actual);
    $this->assertStringNotContainsString('de la facultad', $html);
    $this->assertSame(0, substr_count($html, 'class="ua-prog"'));
    $this->assertStringContainsString('href="/programas"', $html);
    $this->assertSame(1, substr_count($html, 'class="ua-pagehead__title"'));
  }

  /**
   * Un relacionado con autoevaluación va primero y lleva su marca.
   */
  public function testRelacionadoConAutoevaluacionVaPrimero(): void {
    $actual = $this->programa('Enfermería');
    $this->otros(3);
    $evaluado = $this->programa('Zeta evaluado');
    $this->crearNodo('proceso', ['title' => 'Proceso', 'field_programa' => $evaluado->id()]);
    $html = $this->pagina($actual);
    $this->assertSame(1, substr_count($html, 'Con autoevaluación'));
    $this->assertLessThan(
      strpos($html, 'Otro 1'),
      strpos($html, 'Zeta evaluado'),
    );
  }

  /**
   * Con datos de registro la bajada nombra el registro calificado.
   */
  public function testBajadaConRegistroCalificado(): void {
    $actual = $this->programa('Con registro', 0, ['field_resolucion_registro' => 'Resolución 123']);
    $html = $this->pagina($actual);
    $this->assertStringContainsString('Registro calificado del programa, en un solo lugar.', $html);
  }

  /**
   * La bajada nombra solo las secciones que existen.
   *
   * @param bool $registro
   *   El programa tiene datos de registro.
   * @param bool $factores
   *   Tiene factores.
   * @param bool $plan
   *   Tiene plan.
   * @param bool $documentos
   *   Tiene documentos.
   * @param string $esperado
   *   La bajada esperada.
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('proveedorDeBajadas')]
  public function testBajadaSegunLasSecciones(bool $registro, bool $factores, bool $plan, bool $documentos, string $esperado): void {
    $valores = $registro ? ['field_registro_hasta' => '2029-12-31'] : [];
    $programa = $this->programa('Programa', 0, $valores);
    $servicio = $this->container->get('unillanos_autoeval.pagina_programa');
    $this->assertSame($esperado, $servicio->lead($programa, $factores, $plan, $documentos));
  }

  /**
   * Casos: ninguna, una, dos, tres secciones y con autoevaluación.
   *
   * @return array<string, array{bool, bool, bool, bool, string}>
   *   Secciones y bajada esperada.
   */
  public static function proveedorDeBajadas(): array {
    $fija = 'Registro calificado, autoevaluación y plan de mejoramiento del programa, en un solo lugar.';
    return [
      'ninguna: se omite' => [FALSE, FALSE, FALSE, FALSE, ''],
      'solo registro' => [TRUE, FALSE, FALSE, FALSE, 'Registro calificado del programa, en un solo lugar.'],
      'solo plan' => [FALSE, FALSE, TRUE, FALSE, 'Plan de mejoramiento del programa, en un solo lugar.'],
      'solo documentos' => [FALSE, FALSE, FALSE, TRUE, 'Documentos del programa, en un solo lugar.'],
      'plan y documentos' => [
        FALSE, FALSE, TRUE, TRUE,
        'Plan de mejoramiento y documentos del programa, en un solo lugar.',
      ],
      'registro, plan y documentos' => [
        TRUE, FALSE, TRUE, TRUE,
        'Registro calificado, plan de mejoramiento y documentos del programa, en un solo lugar.',
      ],
      'con factores: la frase de siempre' => [FALSE, TRUE, FALSE, FALSE, $fija],
      'con factores y todo: la misma frase' => [TRUE, TRUE, TRUE, TRUE, $fija],
    ];
  }

  /**
   * Con contenido de autoevaluación no hay relacionados ni enlaces.
   */
  public function testConContenidoNoHayRelacionadosNiEnlaces(): void {
    $programa = $this->programa('Con contenido');
    $this->otros(3);
    $servicio = $this->container->get('unillanos_autoeval.pagina_programa');
    $casos = [
      [['pestanas' => [['key' => 'f01']]], [], []],
      [[], ['filas' => [['key' => 'f01']]], []],
      [[], [], ['items' => [['title' => 'Informe']]]],
    ];
    foreach ($casos as [$factores, $plan, $documentos]) {
      $pagina = $servicio->armar($programa, $factores, $plan, $documentos, new CacheableMetadata());
      $this->assertSame([], $pagina['otros']);
      $this->assertSame([], $pagina['enlaces']);
    }
    // Sin nada: relacionados (3) y enlaces (2).
    $pagina = $servicio->armar($programa, [], [], [], new CacheableMetadata());
    $this->assertCount(3, $pagina['otros']['programas']);
    $this->assertSame('Otros programas de la facultad', $pagina['otros']['titulo']);
    $this->assertSame(['Ver todos los programas', 'Ver documentos'], array_column($pagina['enlaces'], 'label'));
    $this->assertSame(['/programas', '/documentos'], array_column($pagina['enlaces'], 'url'));
  }

  /**
   * Un enlace solo sale si el visitante puede entrar a su ruta.
   */
  public function testEnlacesSegunElAcceso(): void {
    $servicio = $this->container->get('unillanos_autoeval.pagina_programa');
    $this->assertCount(2, $servicio->enlaces(new CacheableMetadata()));
    $this->setUpCurrentUser([], []);
    $servicio = $this->container->get('unillanos_autoeval.pagina_programa');
    $this->assertSame([], $servicio->enlaces(new CacheableMetadata()));
  }

  /**
   * La página declara las etiquetas de lo que lee para los relacionados.
   */
  public function testEtiquetasDeCache(): void {
    $actual = $this->programa('Enfermería');
    $constructor = $this->container->get('entity_type.manager')->getViewBuilder('node');
    if (!$constructor instanceof EntityViewBuilder) {
      $this->fail('El constructor de vistas del nodo no es un EntityViewBuilder.');
    }
    $construccion = $constructor->build($constructor->view($actual, 'full'));
    $etiquetas = $construccion['#cache']['tags'];
    foreach ([
      'node_list:programa',
      'node_list:proceso',
      'taxonomy_term_list:facultad',
      'taxonomy_term_list:sede',
      'config:field.storage.node.field_nivel',
    ] as $etiqueta) {
      $this->assertContains($etiqueta, $etiquetas);
    }
  }

  /**
   * Un programa con cuatro datos de ficha.
   */
  protected function programaConFicha(): Node {
    $sede = Term::create(['vid' => 'sede', 'name' => 'Sede Barcelona']);
    $sede->save();
    return $this->programa('Ingeniería Electrónica', 0, [
      'field_modalidad' => 'presencial',
      'field_sede' => $sede->id(),
      'field_director' => 'Persona Directora',
    ]);
  }

  /**
   * La página del programa lleva el hero de color sólido (el caso normal).
   */
  public function testHeroSinFotoEnLaPagina(): void {
    $html = $this->pagina($this->programaConFicha());
    $this->assertSame(1, substr_count($html, 'class="ua-pagehead ua-pagehead--hero"'));
    $this->assertStringNotContainsString('ua-pagehead--foto', $html);
    $this->assertStringNotContainsString('<picture', $html);
    // El título del hero es el h1 de la página (el h1 «page-title» que se ve
    // en estas pruebas lo agrega la vista por defecto del nodo, no el tema).
    $this->assertSame(1, substr_count($html, '<h1 class="ua-pagehead__title">'));
  }

  /**
   * La ficha es un aside con las filas que existen, a la derecha del contenido.
   */
  public function testLaFichaEsUnAside(): void {
    $html = $this->pagina($this->programaConFicha());
    $this->assertSame(1, substr_count($html, '<aside'));
    $this->assertMatchesRegularExpression('~<aside [^>]*class="ua-ficha-aside"[^>]*id="ficha"|<aside [^>]*id="ficha"[^>]*class="ua-ficha-aside"~', $html);
    $this->assertSame(1, substr_count($html, 'id="ficha"'));
    $this->assertStringContainsString('Ficha del programa', $html);
    foreach (['Nivel', 'Modalidad', 'Sede', 'Dirección del programa'] as $termino) {
      $this->assertStringContainsString('<dt class="ua-kv__term">' . $termino . '</dt>', $html);
    }
    // Sin datos de registro no hay esas filas (nada de «Pendiente»).
    $this->assertStringNotContainsString('Código SNIES', $html);
    $this->assertStringNotContainsString('endiente', $html);
    // Va después del contenido de la izquierda (en móvil queda debajo).
    $this->assertLessThan(strpos($html, '<aside'), strpos($html, 'ua-programa__principal'));
    $this->assertStringNotContainsString('class="ua-ficha"', $html);
  }

  /**
   * Con un solo dato la ficha no se imprime (repetiría las etiquetas).
   */
  public function testSinFichaNoHayAside(): void {
    $html = $this->pagina($this->programa('Enfermería'));
    $this->assertStringNotContainsString('<aside', $html);
    $this->assertStringNotContainsString('Ficha del programa', $html);
    $this->assertStringNotContainsString('id="ficha"', $html);
  }

  /**
   * Sin factores, la ficha va a la derecha del primer bloque.
   */
  public function testFichaJuntoOtrosProgramas(): void {
    $actual = $this->programaConFicha();
    $this->otros(2);
    $html = $this->pagina($actual);
    $this->assertStringContainsString('ua-programa__zona ua-programa__zona--ficha', $html);
    $this->assertSame(1, substr_count($html, '<aside'));
    $this->assertSame(2, substr_count($html, 'class="ua-prog"'));
    $this->assertLessThan(strpos($html, '<aside'), strpos($html, 'class="ua-prog"'));
  }

  /**
   * El listado y los documentos conservan su cabecera gris (sin hero).
   */
  public function testLasOtrasPaginasNoUsanElHero(): void {
    $this->programaConFicha();
    foreach (['/programas', '/documentos'] as $ruta) {
      $html = (string) $this->container->get('http_kernel')->handle(Request::create($ruta))->getContent();
      $this->assertStringContainsString('class="ua-pagehead"', $html, $ruta);
      $this->assertStringNotContainsString('ua-pagehead--hero', $html, $ruta);
      $this->assertStringNotContainsString('<aside', $html, $ruta);
    }
  }

  /**
   * Crea un programa con la foto de la cabecera (en la base de la prueba).
   *
   * @param bool $con_estilos
   *   Si existen los estilos de imagen de la cabecera.
   */
  protected function programaConFoto(bool $con_estilos): Node {
    $this->crearCampo('node', 'programa', 'field_imagen', 'image');
    if ($con_estilos) {
      foreach (['unillanos_hero_640', 'unillanos_hero_1280', 'unillanos_hero_1920'] as $nombre) {
        ImageStyle::create(['name' => $nombre, 'label' => $nombre])->save();
      }
    }
    $png = imagecreatetruecolor(20, 10);
    ob_start();
    imagepng($png);
    $datos = (string) ob_get_clean();
    $uri = $this->container->get('file_system')->saveData($datos, 'public://programa-prueba.png');
    $archivo = File::create(['uri' => $uri, 'status' => 1, 'uid' => 0]);
    $archivo->save();
    return $this->programa('Ingeniería Electrónica', 0, [
      'field_modalidad' => 'presencial',
      'field_imagen' => ['target_id' => $archivo->id(), 'alt' => 'Estudiantes de prueba', 'width' => 20, 'height' => 10],
    ]);
  }

  /**
   * Con foto, PaginaPrograma entrega la imagen ya armada.
   */
  public function testImagenDelProgramaConEstilos(): void {
    $programa = $this->programaConFoto(TRUE);
    $imagen = $this->paginaPrograma()->datosPrograma($programa)['imagen'];
    $this->assertIsArray($imagen);
    $this->assertStringEndsWith('/programa-prueba.png', $imagen['src']);
    $this->assertSame(3, preg_match_all('~ (?:640|1280|1920)w~', $imagen['srcset']));
    foreach ([640, 1280, 1920] as $ancho) {
      $this->assertStringContainsString(' ' . $ancho . 'w', $imagen['srcset']);
    }
    $this->assertSame('image/webp', $imagen['type']);
    $this->assertSame(20, $imagen['width']);
    $this->assertSame(10, $imagen['height']);
    $this->assertSame('Estudiantes de prueba', $imagen['alt']);
  }

  /**
   * Sin estilos de imagen no hay srcset, pero la foto sigue saliendo.
   */
  public function testImagenDelProgramaSinEstilos(): void {
    $imagen = $this->paginaPrograma()->datosPrograma($this->programaConFoto(FALSE))['imagen'];
    $this->assertIsArray($imagen);
    $this->assertSame('', $imagen['srcset']);
    $this->assertNotSame('', $imagen['src']);
  }

  /**
   * La página con foto pinta el hero con imagen y velo, y el alt del campo.
   */
  public function testLaPaginaConFotoPintaElHeroConImagen(): void {
    $html = $this->pagina($this->programaConFoto(TRUE));
    $this->assertStringContainsString('ua-pagehead--hero ua-pagehead--foto', $html);
    $this->assertSame(1, substr_count($html, '<picture'));
    $this->assertStringContainsString('alt="Estudiantes de prueba"', $html);
    $this->assertStringContainsString('fetchpriority="high"', $html);
    $this->assertStringContainsString('ua-pagehead__velo', $html);
    $this->assertStringContainsString(' 1280w', $html);
  }

  /**
   * Sin imagen en el programa, no hay imagen: la cabecera es sólida.
   */
  public function testSinImagenDatosPrograma(): void {
    $this->assertNull($this->paginaPrograma()->datosPrograma($this->programa('Enfermería'))['imagen']);
    $this->crearCampo('node', 'programa', 'field_imagen', 'image');
    $this->assertNull($this->paginaPrograma()->datosPrograma($this->programa('Medicina', 0, ['field_clave' => 'medicina']))['imagen']);
  }

}
