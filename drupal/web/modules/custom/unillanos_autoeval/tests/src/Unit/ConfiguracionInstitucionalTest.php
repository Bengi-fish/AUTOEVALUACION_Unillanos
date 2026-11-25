<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Prueba la configuración de lo institucional (Fase 9, etapa 2).
 *
 * Lee los archivos de `config/sync` y del módulo: tipos `dependencia` y
 * `norma`, sus campos, los campos nuevos del documento, el permiso del rol
 * Acreditación y los enlaces de Cifras.
 */
#[Group('unillanos_autoeval')]
class ConfiguracionInstitucionalTest extends UnitTestCase {

  /**
   * Lee un archivo de configuración.
   *
   * @param string $nombre
   *   Nombre de la configuración.
   * @param bool $modulo
   *   TRUE para leer la copia del módulo (config/optional o install).
   *
   * @return array<string, mixed>
   *   Contenido.
   */
  protected function configuracion(string $nombre, bool $modulo = FALSE): array {
    $raiz = dirname(__DIR__, 3);
    $ruta = $modulo
      ? $raiz . '/config/' . (str_starts_with($nombre, 'unillanos_autoeval.') ? 'install' : 'optional') . '/' . $nombre . '.yml'
      : $raiz . '/../../../../config/sync/' . $nombre . '.yml';
    $this->assertFileExists($ruta, $nombre);
    return Yaml::decode((string) file_get_contents($ruta));
  }

  /**
   * Los tipos de contenido nuevos crean revisión al guardar.
   */
  public function testTiposConRevision(): void {
    foreach (['dependencia' => 'Dependencia', 'norma' => 'Norma'] as $tipo => $nombre) {
      $config = $this->configuracion('node.type.' . $tipo);
      $this->assertSame($nombre, $config['name']);
      $this->assertTrue($config['new_revision'], $tipo);
    }
  }

  /**
   * Los campos de la norma: solo origen, tipo y epígrafe son obligatorios.
   */
  public function testCamposDeLaNorma(): void {
    $esperados = [
      'field_origen_norma' => TRUE,
      'field_tipo_norma' => TRUE,
      'field_numero_fecha' => FALSE,
      'field_entidad' => FALSE,
      'field_epigrafe' => TRUE,
      'field_capitulos' => FALSE,
      'field_aplicacion' => FALSE,
      'field_documento' => FALSE,
    ];
    foreach ($esperados as $campo => $obligatorio) {
      $config = $this->configuracion('field.field.node.norma.' . $campo);
      $this->assertSame($obligatorio, $config['required'], $campo);
    }
    $origen = $this->configuracion('field.storage.node.field_origen_norma');
    $this->assertSame(['externo', 'interno'], array_column($origen['settings']['allowed_values'], 'value'));
  }

  /**
   * La dependencia lleva descripción corta y orden, ambos opcionales.
   */
  public function testCamposDeLaDependencia(): void {
    foreach (['field_descripcion', 'field_orden'] as $campo) {
      $config = $this->configuracion('field.field.node.dependencia.' . $campo);
      $this->assertFalse($config['required'], $campo);
    }
  }

  /**
   * El documento puede apuntar a una dependencia, un programa y una facultad.
   */
  public function testCamposNuevosDelDocumento(): void {
    $destinos = ['field_dependencia' => 'dependencia', 'field_programa' => 'programa', 'field_facultad' => 'facultad'];
    foreach ($destinos as $campo => $destino) {
      $config = $this->configuracion('field.field.media.documento.' . $campo);
      $this->assertFalse($config['required'], $campo);
      $this->assertSame([$destino => $destino], $config['settings']['handler_settings']['target_bundles'], $campo);
    }
    $categoria = $this->configuracion('field.storage.media.field_categoria');
    $claves = array_column($categoria['settings']['allowed_values'], 'value');
    $this->assertContains('directivos', $claves);
    $this->assertContains('dependencia', $claves);
    // El archivo ya no es obligatorio (archivo o enlace).
    $this->assertFalse($this->configuracion('field.field.media.documento.field_media_file')['required']);
  }

  /**
   * El proceso institucional puede quedar sin modelo.
   */
  public function testElModeloDelProcesoYaNoEsObligatorio(): void {
    $this->assertFalse($this->configuracion('field.field.node.proceso.field_lineamiento')['required']);
  }

  /**
   * Acreditación crea, edita y borra lo propio de dependencia y norma.
   */
  public function testPermisosDelRol(): void {
    $rol = $this->configuracion('user.role.acreditacion');
    foreach (['dependencia', 'norma'] as $tipo) {
      foreach (["create $tipo content", "edit any $tipo content", "delete own $tipo content"] as $permiso) {
        $this->assertContains($permiso, $rol['permissions']);
      }
      $this->assertNotContains("delete any $tipo content", $rol['permissions']);
      $this->assertNotContains("edit own $tipo content", $rol['permissions']);
    }
    foreach ($rol['permissions'] as $permiso) {
      $this->assertStringNotContainsString('administer', $permiso);
    }
    $sin_uuid = $rol;
    unset($sin_uuid['uuid']);
    $this->assertEquals($sin_uuid, $this->configuracion('user.role.acreditacion', TRUE), 'el módulo y el sitio declaran el mismo rol');
  }

  /**
   * Los modos de formulario simples de lo nuevo existen y son iguales.
   */
  public function testPantallasSimples(): void {
    foreach (['node.dependencia', 'node.norma', 'media.documento'] as $tipo) {
      $nombre = 'core.entity_form_display.' . $tipo . '.acreditacion';
      $sitio = $this->configuracion($nombre);
      $this->assertSame('acreditacion', $sitio['mode'], $nombre);
      $this->assertArrayHasKey('status', $sitio['content'], $nombre);
      foreach (['uid', 'created', 'path', 'langcode', 'promote', 'sticky'] as $campo) {
        $this->assertArrayNotHasKey($campo, $sitio['content'], "$nombre: $campo");
      }
      unset($sitio['uuid']);
      $this->assertEquals($sitio, $this->configuracion($nombre, TRUE), $nombre);
    }
  }

  /**
   * Los enlaces de Cifras instalados son https y el tablero también.
   */
  public function testEnlacesDeCifras(): void {
    $instalada = $this->configuracion('unillanos_autoeval.micrositio', TRUE);
    $sitio = $this->configuracion('unillanos_autoeval.micrositio');
    foreach ([$instalada, $sitio] as $config) {
      $cifras = $config['institucional']['cifras'];
      $this->assertNotEmpty($config['institucional']['introduccion']);
      $this->assertCount(4, $cifras['enlaces']);
      foreach ($cifras['enlaces'] as $enlace) {
        $this->assertNotSame('', $enlace['titulo']);
        $this->assertNotSame('', $enlace['texto']);
        $this->assertStringStartsWith('https://', $enlace['url']);
      }
      $this->assertStringStartsWith('https://public.tableau.com/', $cifras['tablero_url']);
    }
  }

}
