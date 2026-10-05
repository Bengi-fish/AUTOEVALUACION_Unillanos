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
   * Implements hook_migration_plugins_alter().
   *
   * Resuelve las rutas y replica por programa las migraciones marcadas:
   * - `%datos%`: carpeta `datos/` del repositorio (o el valor de
   *   `$settings['unillanos_datos']`).
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
    $carpetas = $this->carpetasDeProgramas($datos);
    foreach ($definitions as $id => $definition) {
      if (($definition['migration_group'] ?? NULL) !== 'unillanos') {
        continue;
      }
      if (empty($definition[self::MARCA_POR_PROGRAMA])) {
        $definitions[$id] = $this->reemplazar($definition, ['%datos%' => $datos]);
        continue;
      }
      unset($definitions[$id]);
      unset($definition[self::MARCA_POR_PROGRAMA]);
      foreach ($carpetas as $carpeta) {
        $programa = str_replace('-', '_', $carpeta);
        $copia = $this->reemplazar($definition, [
          '%datos%' => $datos,
          '%carpeta%' => $carpeta,
          '%programa%' => $programa,
        ]);
        $copia['id'] = $id . ':' . $programa;
        $copia['label'] = $definition['label'] . ' (' . $carpeta . ')';
        $definitions[$copia['id']] = $copia;
      }
    }
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
