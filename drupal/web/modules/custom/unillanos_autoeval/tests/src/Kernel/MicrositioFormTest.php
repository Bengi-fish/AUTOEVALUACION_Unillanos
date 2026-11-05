<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\unillanos_autoeval\Form\MicrositioForm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el formulario de administración de la cabecera, la franja y el pie.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class MicrositioFormTest extends KernelTestBase {

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
   * Envía el formulario con unos valores y devuelve su estado.
   *
   * @param array<string, mixed> $valores
   *   Valores que reemplazan a los que el formulario ofrece por defecto.
   */
  protected function enviar(array $valores): FormStateInterface {
    $constructor = $this->container->get('form_builder');
    $estado = new FormState();
    $estado->setValues($valores);
    $constructor->submitForm(MicrositioForm::class, $estado);
    return $estado;
  }

  /**
   * Valores que reproducen la configuración por defecto.
   *
   * @return array<string, mixed>
   *   Valores de los campos del formulario.
   */
  protected function base(): array {
    return [
      'nombre_linea1' => 'Sistema de',
      'nombre_linea2' => 'Autoevaluación',
      'url_web_principal' => '',
      'franja_superior' => '',
      'accesibilidad_titulo' => 'Accesibilidad',
      'accesibilidad_url' => '',
      'pie_titulo' => 'Pie',
      'pie_descripcion' => 'Descripción',
      'pie_institucion' => 'Universidad',
      'pie_nota_institucion' => 'Nota',
      'pie_entidades' => '',
      'pie_sedes' => '',
      'pie_contacto' => '',
      'pie_redes' => '',
      'pie_enlaces_legales' => '',
      'pie_legal' => 'Aviso',
    ];
  }

  /**
   * Lo que se guarda lo lee DatosMarco tal como se escribió.
   */
  public function testGuardaLuegoElMarcoLoLee(): void {
    $estado = $this->enviar([
      'nombre_linea1' => '  Sistema de  ',
      'url_web_principal' => 'https://www.unillanos.edu.co',
      'franja_superior' => "Admisiones | https://www.unillanos.edu.co/admisiones\n\n  Correo | mailto:info@unillanos.edu.co  ",
      'pie_sedes' => "Villavicencio\n\n  Granada  ",
      'pie_redes' => 'Instagram | https://instagram.com/unillanos',
    ] + $this->base());
    $this->assertSame([], $estado->getErrors());

    $config = $this->config('unillanos_autoeval.micrositio');
    $this->assertSame('Sistema de', $config->get('nombre_linea1'));
    $this->assertSame([
      ['titulo' => 'Admisiones', 'url' => 'https://www.unillanos.edu.co/admisiones'],
      ['titulo' => 'Correo', 'url' => 'mailto:info@unillanos.edu.co'],
    ], $config->get('franja_superior'));
    $this->assertSame([['texto' => 'Villavicencio'], ['texto' => 'Granada']], $config->get('pie.sedes'));

    $datos = $this->container->get('unillanos_autoeval.datos_marco')->datos(new CacheableMetadata());
    $this->assertSame('https://www.unillanos.edu.co', $datos['web_principal']);
    $this->assertSame(['Admisiones', 'Correo'], array_column($datos['franja'], 'title'));
    $this->assertSame(['Villavicencio', 'Granada'], $datos['pie']['sedes']);
    $this->assertSame('Instagram', $datos['pie']['redes'][0]['title']);
  }

  /**
   * Una URL no segura o una línea mal formada impiden guardar.
   */
  #[DataProvider('urlsInvalidas')]
  public function testRechazaLoInvalido(string $campo, string $valor): void {
    $antes = $this->config('unillanos_autoeval.micrositio')->get();
    $estado = $this->enviar([$campo => $valor] + $this->base());
    $this->assertArrayHasKey($campo, $estado->getErrors(), $valor);
    $this->assertSame($antes, $this->config('unillanos_autoeval.micrositio')->get());
  }

  /**
   * Casos inválidos.
   *
   * @return array<string, array{0: string, 1: string}>
   *   Campo y valor.
   */
  public static function urlsInvalidas(): array {
    return [
      'javascript en la web' => ['url_web_principal', 'javascript:alert(1)'],
      'ruta sin barra' => ['url_web_principal', 'unillanos.edu.co'],
      'doble barra' => ['accesibilidad_url', '//otro.sitio'],
      'franja sin url' => ['franja_superior', 'Solo título'],
      'franja con javascript' => ['franja_superior', 'Malo | javascript:alert(1)'],
      'entidad sin título' => ['pie_entidades', ' | https://ejemplo.co'],
      'legal con espacio en la url' => ['pie_enlaces_legales', 'Aviso | https://ejemplo.co/a b'],
    ];
  }

  /**
   * El formulario ofrece lo guardado en líneas y exige permiso.
   */
  public function testMuestraLoGuardadoConPermiso(): void {
    $this->config('unillanos_autoeval.micrositio')
      ->set('franja_superior', [['titulo' => 'Uno', 'url' => '/uno']])
      ->save();
    $form = $this->container->get('form_builder')->getForm(MicrositioForm::class);
    $this->assertSame('Uno | /uno', $form['cabecera']['franja_superior']['#default_value']);
    $ruta = $this->container->get('router.route_provider')->getRouteByName('unillanos_autoeval.micrositio');
    $this->assertSame('/admin/config/unillanos/micrositio', $ruta->getPath());
    $this->assertSame('administer site configuration', $ruta->getRequirement('_permission'));
  }

}
