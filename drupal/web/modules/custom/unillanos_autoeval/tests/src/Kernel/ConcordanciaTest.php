<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Comprueba que el plural del español en Drupal concuerda con 0, 1 y varios.
 *
 * Las cifras y notas del sitio usan formatPlural(): en español, 1 va en
 * singular y 0 o más de 1 en plural.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class ConcordanciaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'language', 'locale'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'language', 'locale']);
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locales_location']);
    ConfigurableLanguage::createFromLangcode('es')->save();
  }

  /**
   * Con 0, 1 y 2 o más, el texto concuerda con el número.
   *
   * @param int $cantidad
   *   Cantidad.
   * @param string $esperado
   *   Texto esperado en español.
   */
  #[DataProvider('proveedorDeCantidades')]
  public function testPluralEnEspanol(int $cantidad, string $esperado): void {
    $traduccion = $this->container->get('string_translation');
    $texto = $traduccion->formatPlural(
      $cantidad,
      '@count acción de mejora',
      '@count acciones de mejora',
      [],
      ['langcode' => 'es'],
    );
    $this->assertSame($esperado, (string) $texto);
  }

  /**
   * Proveedor: cantidad y texto esperado.
   *
   * @return array<string, array{int, string}>
   *   Casos de 0, 1, 2 y muchos.
   */
  public static function proveedorDeCantidades(): array {
    return [
      'cero' => [0, '0 acciones de mejora'],
      'uno' => [1, '1 acción de mejora'],
      'dos' => [2, '2 acciones de mejora'],
      'muchos' => [40, '40 acciones de mejora'],
    ];
  }

}
