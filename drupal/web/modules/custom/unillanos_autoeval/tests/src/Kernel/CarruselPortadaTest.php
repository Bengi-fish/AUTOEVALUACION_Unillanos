<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Drupal\unillanos_autoeval\Service\CarruselPortada;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el carrusel de la portada (Fase 9, etapa 5).
 *
 * Hasta cinco diapositivas publicadas y por orden; con una sola, imagen fija;
 * sin ninguna, no aparece. Marcado accesible, validaciones y lápices solo con
 * permiso.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class CarruselPortadaTest extends InstitucionalBaseKernel {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('image_style');
    $this->crearCampo('node', 'diapositiva', 'field_texto_corto', 'string', ['max_length' => 160]);
    $this->crearCampo('node', 'diapositiva', 'field_enlace', 'link');
    $this->crearCampo('node', 'diapositiva', 'field_orden', 'integer');
    $this->crearCampo('node', 'diapositiva', 'field_imagen', 'image');
    $campo = $this->container->get('entity_type.manager')->getStorage('field_config')->load('node.diapositiva.field_imagen');
    $campo->setRequired(TRUE)
      ->setSetting('alt_field_required', TRUE)
      ->setSetting('min_resolution', '1600x1')
      ->setSetting('file_extensions', 'png jpg jpeg webp')
      ->save();
    $enlace = $this->container->get('entity_type.manager')->getStorage('field_config')->load('node.diapositiva.field_enlace');
    $enlace->setSetting('link_type', 17)->setSetting('title', 0)->save();
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
   * @param int $ancho
   *   Ancho en píxeles.
   * @param string $nombre
   *   Nombre del archivo.
   *
   * @return \Drupal\file\Entity\File
   *   El archivo guardado.
   */
  protected function imagen(int $ancho = 1920, string $nombre = 'prueba.jpg'): File {
    $directorio = 'public://carrusel';
    $this->container->get('file_system')->prepareDirectory($directorio, 3);
    $im = imagecreatetruecolor($ancho, (int) round($ancho * 0.375));
    imagejpeg($im, $directorio . '/' . $nombre);
    $archivo = File::create(['uri' => $directorio . '/' . $nombre, 'filename' => $nombre, 'status' => 1]);
    $archivo->save();
    return $archivo;
  }

  /**
   * Crea una diapositiva.
   *
   * @param string $titular
   *   Titular.
   * @param array<string, mixed> $extra
   *   Otros valores.
   */
  protected function diapositiva(string $titular, array $extra = []): Node {
    static $n = 0;
    $archivo = $this->imagen(1920, 'img-' . ++$n . '.jpg');
    $nodo = Node::create($extra + [
      'type' => 'diapositiva',
      'title' => $titular,
      'status' => 1,
      'field_imagen' => ['target_id' => $archivo->id(), 'alt' => 'Imagen de prueba'],
    ]);
    $nodo->save();
    return $nodo;
  }

  /**
   * Datos del servicio.
   *
   * @return array<string, mixed>
   *   Diapositivas y edición.
   */
  protected function datos(): array {
    return $this->container->get(CarruselPortada::class)->datos(new CacheableMetadata());
  }

  /**
   * Renderiza el componente con las diapositivas del servicio.
   *
   * @param array<string, mixed> $datos
   *   Resultado de ::datos().
   *
   * @return string
   *   El HTML.
   */
  protected function html(array $datos): string {
    $items = [];
    foreach ($datos['diapositivas'] as $d) {
      $items[] = [
        'title' => $d['title'],
        'text' => $d['text'],
        'url' => $d['url'],
        'external' => $d['externo'],
        'image' => $d['imagen'],
        'edit' => $d['edicion'] ?? [],
      ];
    }
    $construccion = [
      '#type' => 'component',
      '#component' => 'unillanos:carousel',
      '#props' => ['id' => 'carrusel-portada', 'label' => 'Noticias destacadas', 'items' => $items],
    ];
    return (string) $this->container->get('renderer')->renderInIsolation($construccion);
  }

  /**
   * Sin diapositivas el carrusel no aparece.
   */
  public function testSinDiapositivas(): void {
    $datos = $this->datos();
    $this->assertSame([], $datos['diapositivas']);
    $this->assertSame('', trim($this->html($datos)));
  }

  /**
   * Con una sola es imagen fija: sin controles ni lista.
   */
  public function testUnaSolaEsImagenFija(): void {
    $this->diapositiva('Única');
    $html = $this->html($this->datos());
    $this->assertStringContainsString('role="region"', $html);
    $this->assertSame(1, substr_count($html, 'data-ua-carousel-slide>'));
    $this->assertStringNotContainsString('data-ua-carousel-controles', $html);
    $this->assertStringNotContainsString('data-ua-carousel-pausa', $html);
    $this->assertStringNotContainsString('ua-carousel__lista', $html);
    $this->assertStringContainsString('fetchpriority="high"', $html);
  }

  /**
   * Hasta cinco, por orden; las sin orden al final; la despublicada no sale.
   */
  public function testCincoPorOrdenSoloPublicadas(): void {
    $this->diapositiva('Sexta sin orden');
    $this->diapositiva('Tercera', ['field_orden' => 3]);
    $this->diapositiva('Primera', ['field_orden' => 1]);
    $this->diapositiva('Despublicada', ['field_orden' => 2, 'status' => 0]);
    $this->diapositiva('Segunda', ['field_orden' => 2]);
    $this->diapositiva('Cuarta', ['field_orden' => 4]);
    $this->diapositiva('Quinta', ['field_orden' => 5]);
    $this->diapositiva('Séptima', ['field_orden' => 6]);
    $datos = $this->datos();
    $this->assertSame(
      ['Primera', 'Segunda', 'Tercera', 'Cuarta', 'Quinta'],
      array_column($datos['diapositivas'], 'title'),
    );
  }

  /**
   * Una diapositiva sin imagen visible no se muestra (sin huecos).
   */
  public function testSinImagenNoSeMuestra(): void {
    Node::create(['type' => 'diapositiva', 'title' => 'Sin imagen', 'status' => 1])->save();
    $this->diapositiva('Con imagen');
    $this->assertSame(['Con imagen'], array_column($this->datos()['diapositivas'], 'title'));
  }

  /**
   * Marcado accesible con varias diapositivas.
   */
  public function testMarcadoAccesible(): void {
    $this->diapositiva('Uno', [
      'field_orden' => 1,
      'field_texto_corto' => 'Resumen uno',
      'field_enlace' => ['uri' => 'internal:/programas'],
    ]);
    $this->diapositiva('Dos', ['field_orden' => 2, 'field_enlace' => ['uri' => 'https://example.org/x']]);
    $this->diapositiva('Tres', ['field_orden' => 3]);
    $html = $this->html($this->datos());
    $this->assertStringContainsString('aria-roledescription="carrusel"', $html);
    $this->assertSame(3, substr_count($html, 'aria-roledescription="diapositiva"'));
    $this->assertStringContainsString('aria-label="Diapositiva 2 de 3"', $html);
    // El botón de pausa es el primero que se puede tabular y nace oculto.
    $this->assertLessThan(strpos($html, 'data-ua-carousel-slide>'), strpos($html, 'data-ua-carousel-pausa'));
    $this->assertStringContainsString('data-ua-carousel-barra hidden', $html);
    $this->assertStringContainsString('data-ua-carousel-controles hidden', $html);
    $this->assertStringContainsString('aria-label="Diapositiva 3 de 3: Tres"', $html);
    $this->assertSame(3, substr_count($html, 'data-ua-carousel-punto'));
    $this->assertStringContainsString('aria-live="off"', $html);
    // Primera imagen sin diferir; las demás, diferidas.
    $this->assertSame(1, substr_count($html, 'fetchpriority="high"'));
    $this->assertSame(2, substr_count($html, 'loading="lazy"'));
    // Los tamaños salen de los estilos de imagen (WebP).
    $this->assertStringContainsString('800w', $html);
    $this->assertStringContainsString('1920w', $html);
    // Enlaces: interno directo, externo en pestaña nueva con aviso.
    $this->assertStringContainsString('href="/programas"', $html);
    $this->assertStringContainsString('href="https://example.org/x" target="_blank" rel="noopener noreferrer"', $html);
    // Sin JavaScript: la lista de titulares como enlaces.
    $this->assertStringContainsString('ua-carousel__lista', $html);
    // El texto no va dentro de la imagen: solo en la franja.
    $this->assertStringContainsString('ua-carousel__franja', $html);
    $this->assertStringContainsString('Resumen uno', $html);
  }

  /**
   * Un enlace que no es del sitio ni https no llega al HTML.
   */
  public function testEnlaceInseguroNoSeImprime(): void {
    $this->diapositiva('Uno', ['field_orden' => 1, 'field_enlace' => ['uri' => 'http://example.org/inseguro']]);
    $this->diapositiva('Dos', ['field_orden' => 2, 'field_enlace' => ['uri' => 'javascript:alert(1)']]);
    $datos = $this->datos();
    $this->assertSame(['', ''], array_column($datos['diapositivas'], 'url'));
    $html = $this->html($datos);
    $this->assertStringNotContainsString('javascript:', $html);
    $this->assertStringNotContainsString('http://example.org', $html);
  }

  /**
   * El titular, el escape y la ausencia de HTML de usuario.
   */
  public function testTextosEscapados(): void {
    $this->diapositiva('<b>Titular</b>', ['field_orden' => 1, 'field_texto_corto' => '<i>Texto</i>']);
    $this->diapositiva('Otra', ['field_orden' => 2]);
    $html = $this->html($this->datos());
    $this->assertStringNotContainsString('<b>Titular</b>', $html);
    $this->assertStringContainsString('&lt;b&gt;Titular&lt;/b&gt;', $html);
    $this->assertStringNotContainsString('<i>Texto</i>', $html);
  }

  /**
   * Mensajes de la validación de una diapositiva.
   *
   * @param \Drupal\node\Entity\Node $nodo
   *   La diapositiva sin guardar.
   *
   * @return array<string, string>
   *   Mensaje por ruta del campo.
   */
  protected function infracciones(Node $nodo): array {
    $mensajes = [];
    foreach ($nodo->validate() as $infraccion) {
      $mensajes[$infraccion->getPropertyPath()] = strip_tags((string) $infraccion->getMessage());
    }
    return $mensajes;
  }

  /**
   * Titular de más de 90 caracteres, texto de más de 160 y enlace inseguro.
   */
  public function testValidaciones(): void {
    $archivo = $this->imagen();
    $base = [
      'type' => 'diapositiva',
      'status' => 1,
      'field_imagen' => ['target_id' => $archivo->id(), 'alt' => 'Imagen de prueba'],
    ];
    $this->assertSame([], $this->infracciones(Node::create($base + ['title' => str_repeat('a', 90)])));
    $largo = $this->infracciones(Node::create($base + ['title' => str_repeat('a', 91)]));
    $this->assertSame(['title'], array_keys($largo));
    $this->assertStringContainsString('hasta 90 caracteres', $largo['title']);
    $texto = $this->infracciones(Node::create($base + ['title' => 'T', 'field_texto_corto' => str_repeat('b', 161)]));
    $this->assertArrayHasKey('field_texto_corto.0.value', $texto);
    foreach (['http://example.org', 'javascript:alert(1)', 'internal:programas'] as $uri) {
      $enlace = $this->infracciones(Node::create($base + ['title' => 'T', 'field_enlace' => ['uri' => $uri]]));
      $this->assertArrayHasKey('field_enlace', $enlace, $uri);
    }
    foreach (['internal:/programas', 'https://example.org/a'] as $uri) {
      $this->assertSame([], $this->infracciones(Node::create($base + ['title' => 'T', 'field_enlace' => ['uri' => $uri]])), $uri);
    }
  }

  /**
   * La imagen es obligatoria, con texto alternativo y de ancho mínimo 1600 px.
   */
  public function testImagenObligatoriaAltAnchoMinimo(): void {
    $sin_imagen = $this->infracciones(Node::create(['type' => 'diapositiva', 'title' => 'T', 'status' => 1]));
    $this->assertNotSame([], array_filter(array_keys($sin_imagen), fn(string $r): bool => str_starts_with($r, 'field_imagen')), implode('|', array_keys($sin_imagen)));
    // El ancho mínimo (1600 px) lo comprueba el validador de archivo al subir
    // la imagen en el formulario; aquí se comprueba que el campo lo declara.
    $ajustes = $this->container->get('entity_type.manager')->getStorage('field_config')
      ->load('node.diapositiva.field_imagen')->getSettings();
    $this->assertSame('1600x1', $ajustes['min_resolution']);
    // El texto alternativo obligatorio lo exige el formulario de la imagen.
    $this->assertTrue($ajustes['alt_field_required']);
    $this->assertSame('png jpg jpeg webp', $ajustes['file_extensions']);
  }

  /**
   * Los lápices y el «+» solo para quien puede editar.
   */
  public function testEdicionSoloConPermiso(): void {
    $this->diapositiva('Uno', ['field_orden' => 1]);
    $this->diapositiva('Dos', ['field_orden' => 2]);
    $anonimo = $this->datos();
    $this->assertSame([], $anonimo['edicion']);
    $this->assertStringNotContainsString('data-ua-edit', $this->html($anonimo));

    $this->setUpCurrentUser([], [
      'access content',
      'view media',
      'access acreditacion panel',
      'create diapositiva content',
      'edit any diapositiva content',
    ]);
    $datos = $this->datos();
    $this->assertSame('Añadir una diapositiva', (string) $datos['edicion']['nueva']['title']);
    $html = $this->html($datos);
    $this->assertSame(2, substr_count($html, 'data-ua-edit'));
    $this->assertStringContainsString('Editar la diapositiva «Uno»', $html);
  }

}
