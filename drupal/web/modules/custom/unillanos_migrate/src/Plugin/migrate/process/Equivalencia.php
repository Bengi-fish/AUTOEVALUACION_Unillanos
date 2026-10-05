<?php

namespace Drupal\unillanos_migrate\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Traduce un texto de origen con una tabla de equivalencias en CSV.
 *
 * Configuración:
 * - archivo: ruta del CSV de equivalencias.
 * - desde: columna con el texto de origen.
 * - hasta: columna con el valor de destino.
 * - normalizar: (opcional) compara sin distinguir mayúsculas ni espacios.
 *   Si es FALSE (por defecto) la comparación es por texto completo, exacto.
 * - si_falta: (opcional) `error` (por defecto) falla la fila; `omitir`
 *   devuelve NULL.
 *
 * Si el valor de destino de la equivalencia está vacío, devuelve NULL (p. ej.
 * "Todos los factores": meta transversal sin elemento). Si el origen es una
 * lista (p. ej. tras `explode`), Migrate aplica el plugin a cada texto.
 *
 * Ejemplo:
 * @code
 * plugin: unillanos_equivalencia
 * archivo: '%datos%/importacion/%carpeta%/equivalencias_responsables.csv'
 * desde: variante
 * hasta: id
 * normalizar: true
 * @endcode
 */
#[MigrateProcess('unillanos_equivalencia')]
class Equivalencia extends ProcessPluginBase {

  /**
   * Tablas ya leídas, por archivo y modo.
   *
   * @var array<string, array<string, string>>
   */
  protected static array $tablas = [];

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($value === NULL || trim((string) $value) === '') {
      return NULL;
    }
    return $this->traducir((string) $value);
  }

  /**
   * Traduce un texto.
   *
   * @throws \Drupal\migrate\MigrateException
   *   Si no hay equivalencia y `si_falta` es `error`.
   */
  protected function traducir(string $texto): ?string {
    $tabla = $this->tabla();
    $clave = $this->normalizar($texto);
    if (!array_key_exists($clave, $tabla)) {
      if (($this->configuration['si_falta'] ?? 'error') === 'omitir') {
        return NULL;
      }
      throw new MigrateException(sprintf('Sin equivalencia para "%s" en %s.', $texto, $this->configuration['archivo']));
    }
    return $tabla[$clave] === '' ? NULL : $tabla[$clave];
  }

  /**
   * Prepara el texto para comparar.
   */
  protected function normalizar(string $texto): string {
    $texto = trim($texto);
    if (!empty($this->configuration['normalizar'])) {
      $texto = mb_strtolower((string) preg_replace('/\s+/u', ' ', $texto));
    }
    return $texto;
  }

  /**
   * Lee la tabla de equivalencias (una vez por archivo).
   *
   * @return array<string, string>
   *   Texto de origen (normalizado) => valor de destino.
   *
   * @throws \Drupal\migrate\MigrateException
   *   Si el archivo no se puede leer o le faltan columnas.
   */
  protected function tabla(): array {
    $archivo = $this->configuration['archivo'];
    $llave = $archivo . '|' . (int) !empty($this->configuration['normalizar']);
    if (isset(self::$tablas[$llave])) {
      return self::$tablas[$llave];
    }
    $manejador = @fopen($archivo, 'r');
    if (!$manejador) {
      throw new MigrateException(sprintf('No se puede leer %s.', $archivo));
    }
    $encabezado = fgetcsv($manejador, NULL, ',', '"', '');
    if ($encabezado) {
      $encabezado[0] = ltrim((string) $encabezado[0], "\xEF\xBB\xBF");
    }
    $desde = array_search($this->configuration['desde'], $encabezado ?: [], TRUE);
    $hasta = array_search($this->configuration['hasta'], $encabezado ?: [], TRUE);
    if ($desde === FALSE || $hasta === FALSE) {
      fclose($manejador);
      throw new MigrateException(sprintf('Faltan las columnas %s o %s en %s.', $this->configuration['desde'], $this->configuration['hasta'], $archivo));
    }
    $tabla = [];
    while (($fila = fgetcsv($manejador, NULL, ',', '"', '')) !== FALSE) {
      if (isset($fila[$desde])) {
        $tabla[$this->normalizar($fila[$desde])] = trim($fila[$hasta] ?? '');
      }
    }
    fclose($manejador);
    self::$tablas[$llave] = $tabla;
    return $tabla;
  }

}
