<?php

namespace Drupal\unillanos_guia\Service;

use Drupal\Core\Extension\ExtensionPathResolver;

/**
 * Datos de ejemplo de la guía de estilos.
 *
 * Lee data/datos-ejemplo.json, copia de plantilla/datos-ejemplo.js. Esos
 * datos son inventados y solo se usan en /guia-estilos, nunca en páginas
 * reales.
 */
class DatosEjemplo {

  /**
   * Datos ya leídos, para no abrir el archivo dos veces por petición.
   *
   * @var array<string, mixed>|null
   */
  protected ?array $datos = NULL;

  /**
   * Constructor.
   */
  public function __construct(
    protected string $appRoot,
    protected ExtensionPathResolver $pathResolver,
  ) {}

  /**
   * Devuelve todos los datos de ejemplo.
   *
   * @return array<string, mixed>
   *   Institucional, sedes, programa y programas, como en el prototipo.
   *
   * @throws \RuntimeException
   *   Si el archivo no existe o no es un JSON válido.
   */
  public function obtener(): array {
    if ($this->datos === NULL) {
      $ruta = $this->appRoot . '/' . $this->pathResolver->getPath('module', 'unillanos_guia') . '/data/datos-ejemplo.json';
      $contenido = is_readable($ruta) ? file_get_contents($ruta) : FALSE;
      $datos = $contenido === FALSE ? NULL : json_decode($contenido, TRUE);
      if (!is_array($datos)) {
        throw new \RuntimeException('No se pudieron leer los datos de ejemplo de la guía de estilos.');
      }
      $this->datos = $datos;
    }
    return $this->datos;
  }

}
