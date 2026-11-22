<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\unillanos_autoeval\Service\CarruselPortada;
use Drupal\unillanos_autoeval\Service\GaleriaImagenes;
use Drupal\unillanos_autoeval\Service\InstitucionalProcesos;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba la galería propia de cada programa y de la página institucional.
 *
 * Cada nodo lee SOLO su campo `field_galeria`: nunca las diapositivas de la
 * portada ni las imágenes de otro programa. Sin imágenes, no hay carrusel; con
 * una sola, imagen fija sin controles; el orden es el del campo.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class GaleriaImagenesTest extends InstitucionalBaseKernel {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('image_style');
    // Las diapositivas de la portada existen para comprobar que no se mezclan.
    $this->crearCampo('node', 'diapositiva', 'field_texto_corto', 'string', ['max_length' => 160]);
    $this->crearCampo('node', 'diapositiva', 'field_enlace', 'link');
    $this->crearCampo('node', 'diapositiva', 'field_orden', 'integer');
    $this->crearCampo('node', 'diapositiva', 'field_imagen', 'image');
    // La galería: imágenes múltiples con alt obligatorio y leyenda opcional.
    $this->crearCampo('node', 'programa', 'field_galeria', 'image', [], 10);
    // El proceso institucional guarda la galería de la página «Institucional».
    $this->crearCampo('node', 'proceso', 'field_galeria', 'image', [], 10);
    foreach (['programa', 'proceso'] as $bundle) {
      $this->container->get('entity_type.manager')->getStorage('field_config')
        ->load('node.' . $bundle . '.field_galeria')
        ->setSetting('alt_field_required', TRUE)
        ->setSetting('title_field', TRUE)
        ->save();
    }
    foreach (CarruselPortada::ESTILOS as $nombre => $ancho) {
      $estilo = ImageStyle::create(['name' => $nombre, 'label' => $nombre]);
      $estilo->addImageEffect([
        'id' => 'image_scale',
        'weight' => 1,
        'data' => ['width' => $ancho, 'height' => NULL, 'upscale' => FALSE],
      ]);
      $estilo->save();
    }
    $this->container->get('theme_installer')->install(['unillanos']);
    $this->config('system.theme')->set('default', 'unillanos')->save();
    $this->container->get('theme.manager')->setActiveTheme(
      $this->container->get('theme.initialization')->initTheme('unillanos')
    );
  }

  /**
   * Crea una imagen real y su archivo.
   *
   * @param string $nombre
   *   Nombre del archivo.
   * @param int $estado
   *   Estado del archivo: 1 permanente, 0 temporal.
   * @param string $directorio
   *   Carpeta del archivo.
   *
   * @return \Drupal\file\Entity\File
   *   El archivo guardado.
   */
  protected function imagen(string $nombre, int $estado = 1, string $directorio = 'public://galeria/programa'): File {
    $this->container->get('file_system')->prepareDirectory($directorio, 3);
    $im = imagecreatetruecolor(1600, 600);
    imagejpeg($im, $directorio . '/' . $nombre);
    $archivo = File::create([
      'uri' => $directorio . '/' . $nombre,
      'filename' => $nombre,
      'status' => $estado,
    ]);
    $archivo->save();
    return $archivo;
  }

  /**
   * Crea un programa con las imágenes dadas.
   *
   * @param string $titulo
   *   Título del programa.
   * @param array<int, array{0: string, 1: string}> $imagenes
   *   Cada una: nombre de archivo y texto alternativo.
   * @param string $leyenda
   *   Leyenda de la primera imagen.
   */
  protected function programa(string $titulo, array $imagenes, string $leyenda = ''): Node {
    $valores = [];
    foreach ($imagenes as $i => $datos) {
      $archivo = $this->imagen($datos[0]);
      $valores[] = [
        'target_id' => $archivo->id(),
        'alt' => $datos[1],
        'title' => $i === 0 ? $leyenda : '',
      ];
    }
    $nodo = Node::create([
      'type' => 'programa',
      'title' => $titulo,
      'status' => 1,
      'field_galeria' => $valores,
    ]);
    $nodo->save();
    return $nodo;
  }

  /**
   * Las diapositivas del servicio de la galería de un nodo.
   *
   * @param \Drupal\node\Entity\Node $nodo
   *   El programa.
   *
   * @return array<int, array<string, mixed>>
   *   Las diapositivas.
   */
  protected function galeria(Node $nodo): array {
    return $this->container->get(GaleriaImagenes::class)->delNodo($nodo, new CacheableMetadata());
  }

  /**
   * Sin imágenes no hay galería (y el campo vacío no falla).
   */
  public function testSinImagenesNoHayGaleria(): void {
    $nodo = Node::create(['type' => 'programa', 'title' => 'Sin imágenes', 'status' => 1]);
    $nodo->save();
    $this->assertSame([], $this->galeria($nodo));
    // Un nodo de otro tipo, sin el campo, tampoco falla.
    $otro = Node::create(['type' => 'norma', 'title' => 'Norma', 'status' => 1]);
    $otro->save();
    $this->assertSame([], $this->galeria($otro));
  }

  /**
   * Devuelve las imágenes del programa, en el orden del campo, con su alt.
   */
  public function testDevuelveSusImagenesEnOrden(): void {
    $nodo = $this->programa('Con galería', [
      ['uno.jpg', 'Primera imagen'],
      ['dos.jpg', 'Segunda imagen'],
      ['tres.jpg', 'Tercera imagen'],
    ], 'Leyenda de la primera');
    $items = $this->galeria($nodo);
    $this->assertCount(3, $items);
    $this->assertSame(['Primera imagen', 'Segunda imagen', 'Tercera imagen'], array_column(array_column($items, 'image'), 'alt'));
    // La leyenda es opcional y va como titular; las demás quedan sin titular.
    $this->assertSame(['Leyenda de la primera', '', ''], array_column($items, 'title'));
    foreach ($items as $item) {
      $this->assertSame('', $item['text']);
      $this->assertSame('', $item['url']);
      $this->assertFalse($item['external']);
      $this->assertSame('image/webp', $item['image']['type']);
      $this->assertSame(1600, $item['image']['width']);
      // Los tres estilos de imagen del carrusel.
      $this->assertSame(3, preg_match_all('~ \d+w(?:,|$)~', $item['image']['srcset']));
      $this->assertStringContainsString('unillanos_carrusel_1280', $item['image']['srcset']);
    }
    // El orden es el del campo: se reordena y cambia.
    $valores = array_reverse($nodo->get('field_galeria')->getValue());
    $nodo->set('field_galeria', $valores)->save();
    $this->assertSame(['Tercera imagen', 'Segunda imagen', 'Primera imagen'], array_column(array_column($this->galeria($nodo), 'image'), 'alt'));
  }

  /**
   * Nunca usa las diapositivas de la portada ni las imágenes de otro programa.
   */
  public function testEsIndependienteDeLaPortadaMasOtrosProgramas(): void {
    // Dos diapositivas publicadas en la portada.
    foreach (['a', 'b'] as $clave) {
      $archivo = $this->imagen('portada-' . $clave . '.jpg', 1, 'public://carrusel');
      Node::create([
        'type' => 'diapositiva',
        'title' => 'Diapositiva ' . $clave,
        'status' => 1,
        'field_imagen' => ['target_id' => $archivo->id(), 'alt' => 'Portada ' . $clave],
      ])->save();
    }
    $uno = $this->programa('Programa uno', [['uno-a.jpg', 'Imagen del uno']]);
    $dos = $this->programa('Programa dos', [['dos-a.jpg', 'Dos A'], ['dos-b.jpg', 'Dos B']]);
    $tres = Node::create(['type' => 'programa', 'title' => 'Programa tres', 'status' => 1]);
    $tres->save();
    $this->assertSame(['Imagen del uno'], array_column(array_column($this->galeria($uno), 'image'), 'alt'));
    $this->assertSame(['Dos A', 'Dos B'], array_column(array_column($this->galeria($dos), 'image'), 'alt'));
    // Un programa sin galería no hereda nada: ni la portada ni los demás.
    $this->assertSame([], $this->galeria($tres));
    // Y la portada no ve las imágenes de las galerías.
    $portada = $this->container->get(CarruselPortada::class)->datos(new CacheableMetadata());
    $this->assertSame(['Portada a', 'Portada b'], array_column(array_column($portada['diapositivas'], 'imagen'), 'alt'));
    foreach ($portada['diapositivas'] as $diapositiva) {
      $this->assertStringContainsString('carrusel/portada-', $diapositiva['imagen']['src']);
      $this->assertStringNotContainsString('galeria/programa', $diapositiva['imagen']['src']);
    }
  }

  /**
   * Una imagen cuyo archivo ya no existe no deja un hueco en el carrusel.
   */
  public function testOmiteLasImagenesQueYaNoExisten(): void {
    $nodo = $this->programa('Mixta', [
      ['visible.jpg', 'Visible'],
      ['borrada.jpg', 'Borrada'],
    ]);
    $this->assertCount(2, $this->galeria($nodo));
    // Se borra el archivo de la segunda: el nodo sigue apuntando a él.
    $archivos = $nodo->get('field_galeria')->referencedEntities();
    $archivos[1]->delete();
    $nodo = Node::load($nodo->id());
    $this->assertSame(['Visible'], array_column(array_column($this->galeria($nodo), 'image'), 'alt'));
  }

  /**
   * La página institucional lee la galería de SU proceso institucional.
   *
   * No usa las diapositivas de la portada ni la galería de un programa, ni la
   * de los procesos de un programa o de condiciones; sin imágenes, vacía.
   */
  public function testInstitucionalLeeLaGaleriaDeSuProceso(): void {
    $sede = Term::create(['vid' => 'sede', 'name' => 'Sede Villavicencio']);
    $sede->save();
    $valores = function (array $nombres): array {
      $salida = [];
      foreach ($nombres as $nombre => $alt) {
        $salida[] = ['target_id' => $this->imagen($nombre)->id(), 'alt' => $alt, 'title' => ''];
      }
      return $salida;
    };
    $institucional = Node::create([
      'type' => 'proceso',
      'title' => 'Autoevaluación institucional',
      'status' => 1,
      'field_sede' => $sede->id(),
      'field_tipo_proceso' => 'acreditacion',
      'field_galeria' => $valores(['inst-1.jpg' => 'Campus', 'inst-2.jpg' => 'Edificio']),
    ]);
    $institucional->save();
    // Un programa con su galería y un proceso de ese programa con otra: no se
    // mezclan con la institucional.
    $programa = $this->programa('Programa', [['prog.jpg', 'Imagen del programa']]);
    Node::create([
      'type' => 'proceso',
      'title' => 'Proceso del programa',
      'status' => 1,
      'field_programa' => $programa->id(),
      'field_tipo_proceso' => 'acreditacion',
      'field_galeria' => $valores(['del-proceso.jpg' => 'Del proceso de programa']),
    ])->save();
    $datos = $this->container->get(InstitucionalProcesos::class)->datos(new CacheableMetadata());
    $this->assertSame(['Campus', 'Edificio'], array_column(array_column($datos['principal']['galeria'], 'image'), 'alt'));
    // Sin imágenes en el proceso institucional, la galería queda vacía.
    $institucional->set('field_galeria', [])->save();
    $datos = $this->container->get(InstitucionalProcesos::class)->datos(new CacheableMetadata());
    $this->assertSame([], $datos['principal']['galeria']);
  }

  /**
   * El componente con la galería: contenida, primera sin diferir, sin franja.
   */
  public function testComponenteConLaGaleria(): void {
    $nodo = $this->programa('Con galería', [
      ['uno.jpg', 'Primera imagen'],
      ['dos.jpg', 'Segunda imagen'],
      ['tres.jpg', 'Tercera imagen'],
    ], 'Con leyenda');
    $html = $this->renderizar($this->galeria($nodo));
    $this->assertStringContainsString('ua-carousel--contenida', $html);
    $this->assertSame(3, substr_count($html, 'data-ua-carousel-slide>'));
    // Solo la primera lleva leyenda: una sola franja.
    $this->assertSame(1, substr_count($html, 'class="ua-carousel__franja"'));
    $this->assertStringContainsString('Con leyenda', $html);
    // Cada imagen con su alt; solo la primera se carga sin diferir.
    foreach (['Primera imagen', 'Segunda imagen', 'Tercera imagen'] as $alt) {
      $this->assertStringContainsString('alt="' . $alt . '"', $html);
    }
    $this->assertSame(2, substr_count($html, 'loading="lazy"'));
    // Con varias hay flechas, puntos y pausa; los puntos sin leyenda se
    // nombran por su número.
    $this->assertStringContainsString('data-ua-carousel-controles', $html);
    $this->assertStringContainsString('data-ua-carousel-pausa', $html);
    $this->assertSame(3, substr_count($html, 'data-ua-carousel-punto'));
    $this->assertStringContainsString('Diapositiva 2 de 3"', $html);
    // Sin titulares en la mayoría no hay lista de enlaces para sin JavaScript.
    $this->assertSame(1, preg_match('~<nav class="ua-carousel__lista".*?</nav>~s', $html, $lista));
    $this->assertSame(1, substr_count($lista[0], '<li>'));
  }

  /**
   * Con una sola imagen: imagen fija, sin flechas, puntos, pausa ni lista.
   */
  public function testUnaSolaImagenSinControles(): void {
    $nodo = $this->programa('Una', [['sola.jpg', 'Única imagen']]);
    $html = $this->renderizar($this->galeria($nodo));
    $this->assertSame(1, substr_count($html, 'data-ua-carousel-slide>'));
    $this->assertStringContainsString('alt="Única imagen"', $html);
    $ausentes = [
      'data-ua-carousel-controles',
      'data-ua-carousel-pausa',
      'data-ua-carousel-punto',
      'ua-carousel__lista',
      'ua-carousel__franja',
    ];
    foreach ($ausentes as $ausente) {
      $this->assertStringNotContainsString($ausente, $html, $ausente);
    }
    // Sin imágenes el componente no imprime nada.
    $this->assertSame('', trim($this->renderizar([])));
  }

  /**
   * Renderiza el componente carousel con las diapositivas dadas.
   *
   * @param array<int, array<string, mixed>> $items
   *   Diapositivas de GaleriaImagenes::delNodo().
   *
   * @return string
   *   El HTML.
   */
  protected function renderizar(array $items): string {
    $construccion = [
      '#type' => 'component',
      '#component' => 'unillanos:carousel',
      '#props' => ['id' => 'galeria', 'label' => 'Imágenes del programa', 'contenida' => TRUE, 'items' => $items],
    ];
    return (string) $this->container->get('renderer')->renderInIsolation($construccion);
  }

}
