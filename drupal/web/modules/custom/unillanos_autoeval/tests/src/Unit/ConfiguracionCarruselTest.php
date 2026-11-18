<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Prueba la configuración del carrusel (Fase 9, etapa 5).
 *
 * Lee `config/sync` y la copia del módulo: tipo `diapositiva`, sus campos y
 * límites, los estilos de imagen WebP y el rol Acreditación.
 */
#[Group('unillanos_autoeval')]
class ConfiguracionCarruselTest extends UnitTestCase {

  /**
   * Lee un archivo de configuración.
   *
   * @param string $nombre
   *   Nombre de la configuración.
   * @param bool $modulo
   *   TRUE para leer la copia del módulo (config/optional).
   *
   * @return array<string, mixed>
   *   Contenido.
   */
  protected function configuracion(string $nombre, bool $modulo = FALSE): array {
    $raiz = dirname(__DIR__, 3);
    $ruta = $modulo ? $raiz . '/config/optional/' . $nombre . '.yml' : $raiz . '/../../../../config/sync/' . $nombre . '.yml';
    $this->assertFileExists($ruta, $nombre);
    return Yaml::decode((string) file_get_contents($ruta));
  }

  /**
   * El tipo crea revisión y sus campos tienen los límites pedidos.
   */
  public function testTipoCampos(): void {
    $this->assertTrue($this->configuracion('node.type.diapositiva')['new_revision']);
    $imagen = $this->configuracion('field.field.node.diapositiva.field_imagen');
    $this->assertTrue($imagen['required']);
    $ajustes = $imagen['settings'];
    $this->assertTrue($ajustes['alt_field_required']);
    $this->assertSame('png jpg jpeg webp', $ajustes['file_extensions']);
    $this->assertSame('5 MB', $ajustes['max_filesize']);
    $this->assertSame('1600x1', $ajustes['min_resolution']);
    $this->assertFalse($this->configuracion('field.field.node.diapositiva.field_texto_corto')['required']);
    $this->assertSame(160, $this->configuracion('field.storage.node.field_texto_corto')['settings']['max_length']);
    $enlace = $this->configuracion('field.field.node.diapositiva.field_enlace');
    $this->assertFalse($enlace['required']);
    // Interno o externo (17) y sin título del enlace.
    $this->assertSame(17, $enlace['settings']['link_type']);
    $this->assertSame(0, $enlace['settings']['title']);
    $this->assertFalse($this->configuracion('field.field.node.diapositiva.field_orden')['required']);
  }

  /**
   * Tres estilos de imagen en WebP, con el ancho que dice su nombre.
   */
  public function testEstilosDeImagen(): void {
    foreach ([800, 1280, 1920] as $ancho) {
      $nombre = 'image.style.unillanos_carrusel_' . $ancho;
      $estilo = $this->configuracion($nombre);
      $efectos = array_column($estilo['effects'], 'data', 'id');
      $this->assertSame($ancho, $efectos['image_scale']['width'], $nombre);
      $this->assertFalse($efectos['image_scale']['upscale'], $nombre);
      $this->assertSame('webp', $efectos['image_convert']['extension'], $nombre);
      $sin_uuid = $estilo;
      unset($sin_uuid['uuid']);
      $this->assertEquals($sin_uuid, $this->configuracion($nombre, TRUE), $nombre);
    }
  }

  /**
   * Acreditación crea, edita y borra lo propio, con la pantalla simple.
   */
  public function testPermisosPantallaSimple(): void {
    $rol = $this->configuracion('user.role.acreditacion');
    foreach (['create diapositiva content', 'edit any diapositiva content', 'delete own diapositiva content'] as $permiso) {
      $this->assertContains($permiso, $rol['permissions']);
    }
    $this->assertNotContains('delete any diapositiva content', $rol['permissions']);
    $sin_uuid = $rol;
    unset($sin_uuid['uuid']);
    $this->assertEquals($sin_uuid, $this->configuracion('user.role.acreditacion', TRUE), 'el módulo y el sitio declaran el mismo rol');
    $pantalla = $this->configuracion('core.entity_form_display.node.diapositiva.acreditacion');
    $this->assertSame('acreditacion', $pantalla['mode']);
    foreach (['title', 'field_imagen', 'field_texto_corto', 'field_enlace', 'field_orden', 'status'] as $campo) {
      $this->assertArrayHasKey($campo, $pantalla['content'], $campo);
    }
    foreach (['uid', 'created', 'path', 'promote', 'sticky'] as $campo) {
      $this->assertArrayNotHasKey($campo, $pantalla['content'], $campo);
    }
    unset($pantalla['uuid']);
    $this->assertEquals($pantalla, $this->configuracion('core.entity_form_display.node.diapositiva.acreditacion', TRUE));
  }

}
