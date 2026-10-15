<?php

namespace Drupal\unillanos_migrate\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Site\Settings;

/**
 * Hooks del módulo de migraciones.
 */
class UnillanosMigrateHooks {

  /**
   * Marca de las migraciones que se replican por cada programa.
   */
  const MARCA_POR_PROGRAMA = 'unillanos_por_programa';

  /**
   * Marca de las migraciones por programa cuyo CSV puede no existir.
   *
   * Si el programa no tiene ese CSV (p. ej. `documentos.csv`), o solo trae la
   * cabecera, la migración de ese programa no se crea, en vez de fallar al
   * importar el grupo.
   */
  const MARCA_CSV_OPCIONAL = 'unillanos_csv_opcional';

  /**
   * Implements hook_migration_plugins_alter().
   *
   * Resuelve las rutas y replica por programa las migraciones marcadas:
   * - `%datos%`: carpeta `datos/` del repositorio (o el valor de
   *   `$settings['unillanos_datos']`).
   * - `%raiz%`: raíz del repositorio (o `$settings['unillanos_raiz']`); las
   *   rutas de archivos de los CSV son relativas a ella.
   * - `%carpeta%`: nombre de la carpeta del programa en `datos/importacion/`.
   * - `%programa%`: lo mismo, con guiones bajos (válido en ids de migración).
   * Para agregar un programa basta crear su carpeta y limpiar la caché.
   *
   * @param array<string, array<mixed>> $definitions
   *   Definiciones de plugins de migración.
   */
  #[Hook('migration_plugins_alter')]
  public function migrationPluginsAlter(array &$definitions): void {
    $datos = rtrim((string) Settings::get('unillanos_datos', dirname(DRUPAL_ROOT, 2) . '/datos'), '/');
    $raiz = rtrim((string) Settings::get('unillanos_raiz', dirname(DRUPAL_ROOT, 2)), '/');
    $carpetas = $this->carpetasDeProgramas($datos);
    foreach ($definitions as $id => $definition) {
      if (($definition['migration_group'] ?? NULL) !== 'unillanos') {
        continue;
      }
      if (empty($definition[self::MARCA_POR_PROGRAMA])) {
        $definitions[$id] = $this->reemplazar($definition, ['%datos%' => $datos, '%raiz%' => $raiz]);
        continue;
      }
      unset($definitions[$id]);
      unset($definition[self::MARCA_POR_PROGRAMA]);
      $opcional = !empty($definition[self::MARCA_CSV_OPCIONAL]);
      unset($definition[self::MARCA_CSV_OPCIONAL]);
      foreach ($carpetas as $carpeta) {
        $programa = str_replace('-', '_', $carpeta);
        $copia = $this->reemplazar($definition, [
          '%datos%' => $datos,
          '%raiz%' => $raiz,
          '%carpeta%' => $carpeta,
          '%programa%' => $programa,
        ]);
        if ($opcional && !$this->csvConDatos((string) ($copia['source']['path'] ?? ''))) {
          continue;
        }
        $copia['id'] = $id . ':' . $programa;
        $copia['label'] = $definition['label'] . ' (' . $carpeta . ')';
        $definitions[$copia['id']] = $copia;
      }
    }
  }

  /**
   * Indica si un CSV existe y trae la cabecera y al menos una fila de datos.
   *
   * @param string $ruta
   *   Ruta del CSV.
   *
   * @return bool
   *   FALSE si no existe, está vacío o solo tiene la cabecera.
   */
  protected function csvConDatos(string $ruta): bool {
    if (!is_file($ruta) || !is_readable($ruta)) {
      return FALSE;
    }
    $lineas = 0;
    $archivo = new \SplFileObject($ruta);
    foreach ($archivo as $linea) {
      if (trim((string) $linea) !== '') {
        $lineas++;
      }
      if ($lineas >= 2) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Lista las carpetas de `datos/importacion/` (una por programa).
   *
   * @return string[]
   *   Nombres de carpeta.
   */
  protected function carpetasDeProgramas(string $datos): array {
    $carpetas = [];
    foreach (glob($datos . '/importacion/*', GLOB_ONLYDIR) ?: [] as $ruta) {
      $carpetas[] = basename($ruta);
    }
    sort($carpetas);
    return $carpetas;
  }

  /**
   * Reemplaza marcas de texto en toda la definición.
   *
   * @param array<mixed> $definicion
   *   Definición o fragmento.
   * @param array<string, string> $marcas
   *   Marca => valor.
   *
   * @return array<mixed>
   *   Definición con las marcas reemplazadas.
   */
  protected function reemplazar(array $definicion, array $marcas): array {
    foreach ($definicion as $clave => $valor) {
      if (is_array($valor)) {
        $definicion[$clave] = $this->reemplazar($valor, $marcas);
      }
      elseif (is_string($valor)) {
        $definicion[$clave] = strtr($valor, $marcas);
      }
    }
    return $definicion;
  }

}
