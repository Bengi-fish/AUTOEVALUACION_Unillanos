<?php

namespace Drupal\unillanos_migrate\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Convierte una fecha escrita en español ("19 de Junio de 2024") a Y-m-d.
 *
 * Devuelve NULL si el valor está vacío.
 */
#[MigrateProcess('unillanos_fecha_es')]
class FechaEspanol extends ProcessPluginBase {

  /**
   * Meses en español.
   */
  const MESES = [
    'enero' => 1,
    'febrero' => 2,
    'marzo' => 3,
    'abril' => 4,
    'mayo' => 5,
    'junio' => 6,
    'julio' => 7,
    'agosto' => 8,
    'septiembre' => 9,
    'setiembre' => 9,
    'octubre' => 10,
    'noviembre' => 11,
    'diciembre' => 12,
  ];

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $texto = mb_strtolower(trim((string) $value));
    if ($texto === '') {
      return NULL;
    }
    if (!preg_match('/^(\d{1,2})\s+de\s+(\p{L}+)\s+de\s+(\d{4})$/u', $texto, $partes)
      || !isset(self::MESES[$partes[2]])
      || !checkdate(self::MESES[$partes[2]], (int) $partes[1], (int) $partes[3])) {
      throw new MigrateException(sprintf('Fecha no reconocida: "%s".', $value));
    }
    return sprintf('%04d-%02d-%02d', $partes[3], self::MESES[$partes[2]], $partes[1]);
  }

}
