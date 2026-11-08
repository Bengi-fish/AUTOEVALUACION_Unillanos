<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\unillanos_autoeval\Service\DatosMarco;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el servicio DatosMarco: lo vacío se omite y las URL se filtran.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class DatosMarcoTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'unillanos_autoeval'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['unillanos_autoeval']);
  }

  /**
   * El servicio.
   */
  protected function servicio(): DatosMarco {
    return $this->container->get('unillanos_autoeval.datos_marco');
  }

  /**
   * Los datos del marco.
   *
   * @return array<string, mixed>
   *   Lo que devuelve DatosMarco::datos().
   */
  protected function datos(): array {
    return $this->servicio()->datos(new CacheableMetadata());
  }

  /**
   * Fija un valor de la configuración del micrositio.
   */
  protected function fijar(string $clave, mixed $valor): void {
    $this->config('unillanos_autoeval.micrositio')->set($clave, $valor)->save();
  }

  /**
   * Guarda la configuración tal cual, sin pasar por el esquema.
   *
   * Alguien puede editar el YAML a mano: el servicio no debe fallar con una
   * lista mal formada.
   *
   * @param array<string, mixed> $cambios
   *   Valores que reemplazan a los de la configuración por defecto.
   */
  protected function fijarSinEsquema(array $cambios): void {
    $almacen = $this->container->get('config.storage');
    $datos = array_merge($almacen->read('unillanos_autoeval.micrositio'), $cambios);
    $almacen->write('unillanos_autoeval.micrositio', $datos);
    $this->container->get('config.factory')->reset('unillanos_autoeval.micrositio');
  }

  /**
   * Con la configuración por defecto solo hay lo que ya existía.
   */
  public function testPorDefecto(): void {
    $datos = $this->datos();
    $this->assertSame(['linea1' => 'Sistema de', 'linea2' => 'Autoevaluación'], $datos['nombre']);
    $this->assertSame('', $datos['web_principal']);
    $this->assertSame([], $datos['franja']);
    $this->assertNull($datos['accesibilidad']);
    foreach (['entidades', 'sedes', 'contacto', 'redes', 'enlaces_legales'] as $lista) {
      $this->assertSame([], $datos['pie'][$lista], $lista);
    }
    $this->assertSame('Universidad de los Llanos', $datos['pie']['institucion']);
    $this->assertStringContainsString('inspección y vigilancia', $datos['pie']['legal']);
    $this->assertNotSame('', $datos['pie']['descripcion']);
  }

  /**
   * GOV.CO y las redes de la configuración por defecto, en su orden.
   */
  public function testGovcoMasRedesPorDefecto(): void {
    $datos = $this->datos();
    $this->assertSame('https://www.gov.co/', $datos['govco']['url']);
    $this->assertSame(['facebook', 'x', 'instagram', 'youtube'], array_column($datos['redes'], 'red'));
    $this->assertSame([
      'Facebook de la Universidad de los Llanos',
      'X de la Universidad de los Llanos',
      'Instagram de la Universidad de los Llanos',
      'YouTube de la Universidad de los Llanos',
    ], array_column($datos['redes'], 'title'));
  }

  /**
   * Una red sin URL o con una URL peligrosa no se entrega; sin GOV.CO, NULL.
   */
  public function testRedesSinUrlNiInsegurasSeOmiten(): void {
    $this->fijar('redes_sociales', [
      'facebook' => '',
      'x' => 'javascript:alert(1)',
      'instagram' => 'https://www.instagram.com/unillanos_/',
      'youtube' => '//otro.sitio',
    ]);
    $this->fijar('url_govco', '');
    $datos = $this->datos();
    $this->assertSame(['instagram'], array_column($datos['redes'], 'red'));
    $this->assertNull($datos['govco']);
    $this->fijar('redes_sociales', ['facebook' => '', 'x' => '', 'instagram' => '', 'youtube' => '']);
    $this->assertSame([], $this->datos()['redes']);
    $this->fijar('url_govco', 'javascript:alert(1)');
    $this->assertNull($this->datos()['govco']);
  }

  /**
   * Destinos seguros y peligrosos.
   *
   * @return array<string, array{string, bool}>
   *   URL y si se acepta.
   */
  public static function urls(): array {
    return [
      'ruta' => ['/programas', TRUE],
      'ruta con consulta' => ['/programas?q=a', TRUE],
      'ancla' => ['#contenido', TRUE],
      'consulta' => ['?q=1', TRUE],
      'https' => ['https://example.org/a', TRUE],
      'http' => ['http://example.org', TRUE],
      'mayúsculas' => ['HTTPS://EXAMPLE.ORG', TRUE],
      'correo' => ['mailto:a@example.org', TRUE],
      'teléfono' => ['tel:+576000000', TRUE],
      'con espacios en los extremos' => ['  /programas  ', TRUE],
      'javascript' => ['javascript:alert(1)', FALSE],
      'javascript con mayúsculas' => ['JaVaScRiPt:alert(1)', FALSE],
      'data' => ['data:text/html,hola', FALSE],
      'vbscript' => ['vbscript:x', FALSE],
      'protocolo relativo' => ['//example.org/x', FALSE],
      'barra invertida' => ['/\\example.org', FALSE],
      'espacio dentro' => ['/a b', FALSE],
      'salto de línea' => ["/a\nb", FALSE],
      'carácter de control' => ["/a\x01b", FALSE],
      'sin esquema' => ['example.org', FALSE],
      'vacía' => ['', FALSE],
      'solo espacios' => ['   ', FALSE],
    ];
  }

  /**
   * El filtro de URL acepta solo destinos seguros.
   */
  #[DataProvider('urls')]
  public function testUrlSegura(string $url, bool $aceptada): void {
    $esperada = $aceptada ? trim($url) : '';
    $this->assertSame($esperada, $this->servicio()->urlSegura($url));
  }

  /**
   * Lo que no es texto no es una URL.
   */
  public function testUrlSeguraConValoresQueNoSonTexto(): void {
    foreach ([NULL, 5, ['/a'], TRUE] as $valor) {
      $this->assertSame('', $this->servicio()->urlSegura($valor));
    }
  }

  /**
   * Un enlace sin título o con URL peligrosa se descarta; el resto se queda.
   */
  public function testSeDescartanEnlacesSinTituloMasLosInseguros(): void {
    $this->fijar('franja_superior', [
      ['titulo' => 'Uno', 'url' => 'https://example.org/1'],
      ['titulo' => '', 'url' => 'https://example.org/sin-titulo'],
      ['titulo' => '   ', 'url' => 'https://example.org/espacios'],
      ['titulo' => 'Peligroso', 'url' => 'javascript:alert(1)'],
      ['titulo' => 'Sin URL', 'url' => ''],
      ['titulo' => 'Interno', 'url' => '/programas'],
    ]);
    $this->assertSame([
      ['title' => 'Uno', 'url' => 'https://example.org/1', 'externo' => TRUE],
      ['title' => 'Interno', 'url' => '/programas', 'externo' => FALSE],
    ], $this->datos()['franja']);
  }

  /**
   * El enlace de accesibilidad pide título y URL segura.
   */
  public function testAccesibilidad(): void {
    $this->fijar('accesibilidad.url', '/documentos');
    $this->assertSame(['title' => 'Accesibilidad', 'url' => '/documentos', 'externo' => FALSE], $this->datos()['accesibilidad']);
    $this->fijar('accesibilidad.url', 'javascript:void(0)');
    $this->assertNull($this->datos()['accesibilidad']);
    $this->fijar('accesibilidad.url', '/documentos');
    $this->fijar('accesibilidad.titulo', '');
    $this->assertNull($this->datos()['accesibilidad']);
  }

  /**
   * Los textos del pie se normalizan y los vacíos se descartan.
   */
  public function testTextosDelPie(): void {
    $this->fijar('pie.contacto', [['texto' => 'Teléfono']]);
    $pie = $this->config('unillanos_autoeval.micrositio')->get('pie');
    $pie['sedes'] = [
      ['texto' => "  Sede   uno \n dos "],
      ['texto' => ''],
      ['texto' => '   '],
      ['otra' => 'clave'],
      ['texto' => 'Sede tres'],
    ];
    $this->fijarSinEsquema(['pie' => $pie]);
    $this->assertSame(['Sede uno dos', 'Sede tres'], $this->datos()['pie']['sedes']);
    $this->assertSame(['Teléfono'], $this->datos()['pie']['contacto']);
  }

  /**
   * Un texto muy largo se corta.
   */
  public function testTextoLargoSeCorta(): void {
    $this->fijar('pie.legal', str_repeat('a', 1000));
    $this->assertSame(300, mb_strlen($this->datos()['pie']['legal']));
  }

  /**
   * Una lista mal formada no rompe nada y no pinta nada.
   */
  public function testListasMalFormadas(): void {
    $pie = $this->config('unillanos_autoeval.micrositio')->get('pie');
    $pie['entidades'] = 'no es una lista';
    $pie['redes'] = [['titulo' => 'Red'], 'texto suelto', NULL];
    $this->fijarSinEsquema([
      'pie' => $pie,
      'franja_superior' => [7, [], ['titulo' => 5, 'url' => 7]],
    ]);
    $datos = $this->datos();
    $this->assertSame([], $datos['pie']['entidades']);
    $this->assertSame([], $datos['pie']['redes']);
    $this->assertSame([], $datos['franja']);
  }

  /**
   * Los enlaces externos se marcan; los internos y los de correo no.
   */
  public function testEnlacesExternos(): void {
    $this->fijar('pie.redes', [
      ['titulo' => 'Web', 'url' => 'https://example.org'],
      ['titulo' => 'Interno', 'url' => '/programas'],
      ['titulo' => 'Correo', 'url' => 'mailto:a@example.org'],
    ]);
    $this->assertSame([TRUE, FALSE, FALSE], array_column($this->datos()['pie']['redes'], 'externo'));
  }

  /**
   * La URL de la web principal pasa por el mismo filtro.
   */
  public function testWebPrincipal(): void {
    $this->fijar('url_web_principal', 'https://example.org');
    $this->assertSame('https://example.org', $this->datos()['web_principal']);
    $this->fijar('url_web_principal', 'javascript:alert(1)');
    $this->assertSame('', $this->datos()['web_principal']);
  }

  /**
   * La caché depende de la configuración del micrositio.
   */
  public function testCacheDeLaConfiguracion(): void {
    $cache = new CacheableMetadata();
    $this->servicio()->datos($cache);
    $this->assertContains('config:unillanos_autoeval.micrositio', $cache->getCacheTags());
  }

  /**
   * Sin la configuración instalada el servicio no falla y no devuelve nada.
   */
  public function testSinConfiguracion(): void {
    $this->config('unillanos_autoeval.micrositio')->delete();
    $datos = $this->datos();
    $this->assertSame('', $datos['nombre']['linea1']);
    $this->assertSame([], $datos['franja']);
    $this->assertNull($datos['accesibilidad']);
    $this->assertSame([], $datos['pie']['entidades']);
    $this->assertSame('', $datos['pie']['legal']);
  }

}
