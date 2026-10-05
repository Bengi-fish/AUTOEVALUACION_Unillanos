<?php

namespace Drupal\unillanos_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Database\Connection;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Plugin\migrate\id_map\Sql;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Devuelve las revisiones de paragraphs hijos ya migrados de un padre.
 *
 * El origen de la migración de los hijos debe tener como primer identificador
 * el id del padre (p. ej. `[meta_id, id]` o `[indicador_id, anio]`). El valor
 * de entrada es el id del padre. Resultado: lista de
 * `target_id` / `target_revision_id`, ordenada por el segundo identificador.
 *
 * Configuración:
 * - migracion: id de la migración de los hijos.
 *
 * Ejemplo:
 * @code
 * field_programacion:
 *   plugin: unillanos_hijos_migrados
 *   source: id
 *   migracion: 'programacion_anual:%programa%'
 * @endcode
 */
#[MigrateProcess('unillanos_hijos_migrados')]
final class HijosMigrados extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructor.
   *
   * @param array<string, mixed> $configuration
   *   Configuración del plugin.
   * @param string $plugin_id
   *   Id del plugin.
   * @param mixed $plugin_definition
   *   Definición del plugin.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migrationPluginManager
   *   Gestor de plugins de migración.
   * @param \Drupal\Core\Database\Connection $database
   *   Conexión a la base de datos.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected MigrationPluginManagerInterface $migrationPluginManager,
    protected Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.migration'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function multiple(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $migracion = $this->migrationPluginManager->createInstance($this->configuration['migracion']);
    if (!$migracion instanceof MigrationInterface) {
      throw new MigrateException(sprintf('No existe la migración %s.', $this->configuration['migracion']));
    }
    $mapa = $migracion->getIdMap();
    if (!$mapa instanceof Sql) {
      throw new MigrateException('El mapa de identificadores no es SQL.');
    }
    if ($value === NULL || $value === '' || !$this->database->schema()->tableExists($mapa->mapTableName())) {
      return [];
    }
    $filas = $this->database->select($mapa->mapTableName(), 'm')
      ->fields('m', ['destid1', 'destid2'])
      ->condition('sourceid1', (string) $value)
      ->isNotNull('destid1')
      ->orderBy('sourceid2')
      ->execute()
      ->fetchAll();
    $hijos = [];
    foreach ($filas as $fila) {
      $hijos[] = ['target_id' => $fila->destid1, 'target_revision_id' => $fila->destid2];
    }
    return $hijos;
  }

}
