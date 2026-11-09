<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Component\Datetime\TimeInterface;
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
   * La configuración por defecto trae el pie de Acreditación completo.
   */
  public function testPorDefecto(): void {
    $datos = $this->datos();
    $this->assertSame(['linea1' => 'Sistema de', 'linea2' => 'Autoevaluación'], $datos['nombre']);
    $this->assertSame('', $datos['web_principal']);
    $this->assertSame([], $datos['franja']);
    $this->assertNull($datos['accesibilidad']);
    $pie = $datos['pie'];
    $this->assertSame('Ubícanos', $pie['ubicanos']['titulo']);
    $this->assertSame(
      ['Campus Barcelona', 'Campus San Antonio', 'Campus Boquemonte'],
      array_column($pie['ubicanos']['sedes'], 'title'),
    );
    $this->assertSame('Km. 12 Vía Puerto López', $pie['ubicanos']['sedes'][0]['text']);
    $this->assertSame('Horario de atención: Lunes a Viernes', $pie['ubicanos']['horario']['title']);
    $this->assertSame('7:30am a 11:30m y 2:00pm a 5:30pm', $pie['ubicanos']['horario']['text']);
    $this->assertSame(
      ['Notificaciones judiciales', 'Ventanilla única virtual', 'Correo electrónico', 'PQRS', 'Chat en línea'],
      array_column($pie['contacto']['items'], 'title'),
    );
    $this->assertSame('mailto:quejasyreclamos@unillanos.edu', $pie['contacto']['items'][3]['url']);
    $this->assertSame('https://www.unillanos.edu.co/livezilla/chat.php?a=30c6e', $pie['contacto']['items'][4]['url']);
    $this->assertSame(
      ['PBX', 'Línea anticorrupción', 'Línea nacional', 'WhatsApp'],
      array_column($pie['lineas']['items'], 'title'),
    );
    $this->assertSame('tel:+576086611623', $pie['lineas']['items'][0]['url']);
    $this->assertSame('https://wa.me/573222923194', $pie['lineas']['items'][3]['url']);
    $this->assertSame('Redes', $pie['redes_titulo']);
    $this->assertCount(4, $pie['politicas']['enlaces']);
    $this->assertSame(['unillanos', 'mineducacion', 'colombia', 'govco'], array_column($pie['logos'], 'clave'));
    $this->assertSame(
      [
        'https://www.unillanos.edu.co/',
        'https://www.mineducacion.gov.co/',
        'https://www.colombia.co/',
        'https://www.gov.co/',
      ],
      array_column($pie['logos'], 'url'),
    );
    $this->assertSame('Nit: 892.000.757-3', $pie['derechos']['nit']);
    $this->assertSame('Reservados todos los derechos', $pie['derechos']['texto']);
  }

  /**
   * El año de la línea de derechos sale del sistema, no de la configuración.
   */
  public function testAnioDeLosDerechosEsDinamico(): void {
    $ahora = mktime(12, 0, 0, 6, 15, 2031);
    $reloj = $this->createMock(TimeInterface::class);
    $reloj->method('getRequestTime')->willReturn($ahora);
    $servicio = new DatosMarco($this->container->get('config.factory'), $reloj);
    $cache = new CacheableMetadata();
    $derechos = $servicio->datos($cache)['pie']['derechos'];
    $this->assertSame('1974 - 2031', $derechos['anios']);
    // La página caduca el 1 de enero siguiente, cuando cambia el año.
    $this->assertSame(mktime(0, 0, 0, 1, 1, 2032) - $ahora, $cache->getCacheMaxAge());
    // El año de la configuración no es el final: nunca está escrito a mano.
    $this->assertStringNotContainsString('2026', (string) json_encode($this->config('unillanos_autoeval.micrositio')->get('pie.derechos')));
    // Sin año de inicio, solo el actual; con inicio en el año actual, también.
    $this->fijar('pie.derechos.desde', 0);
    $this->assertSame('2031', $servicio->datos(new CacheableMetadata())['pie']['derechos']['anios']);
    $this->fijar('pie.derechos.desde', 2031);
    $this->assertSame('2031', $servicio->datos(new CacheableMetadata())['pie']['derechos']['anios']);
    // Con el reloj real, el año final es el actual.
    $this->fijar('pie.derechos.desde', 1974);
    $this->assertSame('1974 - ' . date('Y'), $this->datos()['pie']['derechos']['anios']);
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
   * Los textos del pie se normalizan y los vacíos o inseguros se descartan.
   */
  public function testTextosDelPieSeLimpian(): void {
    $pie = $this->config('unillanos_autoeval.micrositio')->get('pie');
    $pie['ubicanos']['sedes'] = [
      ['titulo' => "  Sede   uno \n dos ", 'texto' => '  Calle   1 '],
      ['titulo' => '', 'texto' => 'sin título'],
      ['titulo' => '   '],
      ['otra' => 'clave'],
      ['titulo' => 'Sede tres'],
    ];
    $pie['contacto']['items'] = [
      ['titulo' => 'Solo valor', 'texto' => 'valor', 'url' => '', 'icono' => 'mail'],
      ['titulo' => 'Solo enlace', 'texto' => '', 'url' => 'https://example.org/c', 'icono' => 'inventado'],
      ['titulo' => 'Enlace peligroso', 'texto' => 'queda como texto', 'url' => 'javascript:alert(1)'],
      ['titulo' => 'Peligroso sin texto', 'texto' => '', 'url' => 'javascript:alert(1)'],
      ['titulo' => '', 'texto' => 'sin título', 'url' => '/x'],
    ];
    $this->fijarSinEsquema(['pie' => $pie]);
    $datos = $this->datos()['pie'];
    $this->assertSame(
      [['title' => 'Sede uno dos', 'text' => 'Calle 1'], ['title' => 'Sede tres', 'text' => '']],
      $datos['ubicanos']['sedes'],
    );
    $this->assertSame(
      [
        ['title' => 'Solo valor', 'text' => 'valor', 'url' => '', 'icono' => 'mail'],
        ['title' => 'Solo enlace', 'text' => '', 'url' => 'https://example.org/c', 'icono' => ''],
        ['title' => 'Enlace peligroso', 'text' => 'queda como texto', 'url' => '', 'icono' => ''],
      ],
      $datos['contacto']['items'],
    );
  }

  /**
   * Un texto muy largo se corta.
   */
  public function testTextoLargoSeCorta(): void {
    $this->fijar('pie.derechos.texto', str_repeat('a', 1000));
    $this->assertSame(300, mb_strlen($this->datos()['pie']['derechos']['texto']));
  }

  /**
   * Una lista mal formada no rompe nada y no pinta nada.
   */
  public function testListasMalFormadas(): void {
    $pie = $this->config('unillanos_autoeval.micrositio')->get('pie');
    $pie['ubicanos']['sedes'] = 'no es una lista';
    $pie['contacto']['items'] = [['titulo' => 'Sin valor'], 'texto suelto', NULL];
    $pie['politicas']['enlaces'] = [['titulo' => 'Red'], 'texto suelto', NULL];
    $pie['logos'] = [
      ['clave' => 'inventado', 'titulo' => 'Logo', 'url' => 'https://example.org'],
      ['clave' => 'govco', 'titulo' => 'Sin url', 'url' => ''],
      ['clave' => 'colombia', 'titulo' => '', 'url' => 'https://example.org'],
      ['clave' => 'unillanos', 'titulo' => 'Peligroso', 'url' => 'javascript:alert(1)'],
      'suelto',
    ];
    $this->fijarSinEsquema([
      'pie' => $pie,
      'franja_superior' => [7, [], ['titulo' => 5, 'url' => 7]],
    ]);
    $datos = $this->datos();
    $this->assertSame([], $datos['pie']['ubicanos']['sedes']);
    $this->assertSame([], $datos['pie']['contacto']['items']);
    $this->assertSame([], $datos['pie']['politicas']['enlaces']);
    $this->assertSame([], $datos['pie']['logos']);
    $this->assertSame([], $datos['franja']);
  }

  /**
   * Los enlaces externos se marcan; los internos y los de correo no.
   */
  public function testEnlacesExternos(): void {
    $this->fijar('pie.politicas.enlaces', [
      ['titulo' => 'Web', 'url' => 'https://example.org'],
      ['titulo' => 'Interno', 'url' => '/programas'],
      ['titulo' => 'Correo', 'url' => 'mailto:a@example.org'],
    ]);
    $this->assertSame([TRUE, FALSE, FALSE], array_column($this->datos()['pie']['politicas']['enlaces'], 'externo'));
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
    $this->assertNull($datos['govco']);
    $this->assertSame([], $datos['redes']);
    $this->assertSame([], $datos['pie']['ubicanos']['sedes']);
    $this->assertNull($datos['pie']['ubicanos']['horario']);
    $this->assertSame([], $datos['pie']['contacto']['items']);
    $this->assertSame([], $datos['pie']['lineas']['items']);
    $this->assertSame([], $datos['pie']['logos']);
    $this->assertSame([], $datos['pie']['derechos']);
  }

}
