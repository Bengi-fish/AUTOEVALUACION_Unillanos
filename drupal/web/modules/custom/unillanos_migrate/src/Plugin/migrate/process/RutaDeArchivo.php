<?php

namespace Drupal\unillanos_migrate\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Valida la ruta de un archivo del CSV y devuelve su ruta absoluta.
 *
 * El valor es una ruta relativa a la raíz del repositorio (opción `raiz`).
 * Se rechaza la fila con un mensaje claro si la ruta: está vacía o es
 * absoluta, sale de la raíz (por `..` o por un enlace simbólico), no está
 * dentro de una de las carpetas permitidas (opción `bases`, por defecto `docs`
 * y `datos`), tiene un segmento oculto (que empieza por «.»), no existe o no
 * es un archivo, o tiene una extensión no permitida. Las extensiones
 * permitidas salen de la opción `extensiones` o, si falta, del campo de
 * archivo del tipo de medio `documento`.
 *
 * Ejemplo:
 * @code
 * ruta:
 *   plugin: unillanos_ruta_archivo
 *   source: archivo
 *   raiz: '%raiz%'
 * @endcode
 */
#[MigrateProcess('unillanos_ruta_archivo')]
final class RutaDeArchivo extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructor.
   *
   * @param array<string, mixed> $configuration
   *   Configuración del plugin (raiz, bases y extensiones).
   * @param string $plugin_id
   *   ID del plugin.
   * @param mixed $plugin_definition
   *   Definición del plugin.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Gestor de tipos de entidad.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $relativa = trim((string) $value);
    if ($relativa === '') {
      throw new MigrateException('La ruta del archivo está vacía.');
    }
    if (str_contains($relativa, "\0")) {
      throw new MigrateException('La ruta del archivo tiene caracteres no válidos.');
    }
    // Una ruta absoluta (Unix o con unidad de disco) no es relativa a la
    // raíz.
    if (str_starts_with($relativa, '/') || str_starts_with($relativa, '\\') || preg_match('/^[A-Za-z]:/', $relativa)) {
      throw new MigrateException(sprintf('La ruta «%s» es absoluta: debe ser relativa a la raíz del repositorio.', $relativa));
    }
    $raiz = realpath((string) ($this->configuration['raiz'] ?? ''));
    if ($raiz === FALSE) {
      throw new MigrateException('La raíz del repositorio (opción «raiz») no existe.');
    }
    $absoluta = realpath($raiz . '/' . $relativa);
    if ($absoluta === FALSE || !is_file($absoluta)) {
      throw new MigrateException(sprintf('El archivo «%s» no existe.', $relativa));
    }
    // realpath() resuelve «..» y enlaces simbólicos: se comprueba el destino
    // real.
    if (!str_starts_with($absoluta, $raiz . DIRECTORY_SEPARATOR)) {
      throw new MigrateException(sprintf('La ruta «%s» sale de la raíz del repositorio.', $relativa));
    }
    // Solo carpetas explícitas del repositorio (no .git, .ddev, drupal/…).
    $dentro = FALSE;
    foreach ($this->basesPermitidas() as $base) {
      if (str_starts_with($absoluta, $raiz . DIRECTORY_SEPARATOR . $base . DIRECTORY_SEPARATOR)) {
        $dentro = TRUE;
        break;
      }
    }
    if (!$dentro) {
      throw new MigrateException(sprintf('La ruta «%s» no está en una carpeta permitida (%s).', $relativa, implode(', ', $this->basesPermitidas())));
    }
    foreach (explode(DIRECTORY_SEPARATOR, substr($absoluta, strlen($raiz) + 1)) as $segmento) {
      if (str_starts_with($segmento, '.')) {
        throw new MigrateException(sprintf('La ruta «%s» pasa por un elemento oculto («%s»).', $relativa, $segmento));
      }
    }
    $extension = strtolower(pathinfo($absoluta, PATHINFO_EXTENSION));
    if (!in_array($extension, $this->extensionesPermitidas(), TRUE)) {
      throw new MigrateException(sprintf('La extensión «%s» de «%s» no está permitida.', $extension, $relativa));
    }
    return $absoluta;
  }

  /**
   * Devuelve las carpetas del repositorio de las que se puede copiar.
   *
   * @return string[]
   *   Carpetas relativas a la raíz, sin barras (por defecto `docs` y `datos`).
   */
  protected function basesPermitidas(): array {
    $bases = (array) ($this->configuration['bases'] ?? ['docs', 'datos']);
    return array_values(array_filter(array_map(fn($base) => trim((string) $base, '/\\'), $bases)));
  }

  /**
   * Devuelve las extensiones permitidas, en minúsculas.
   *
   * @return string[]
   *   Las de la opción `extensiones` o, si falta, las del campo de archivo del
   *   tipo de medio `documento`.
   */
  protected function extensionesPermitidas(): array {
    if (!empty($this->configuration['extensiones'])) {
      return array_map('strtolower', (array) $this->configuration['extensiones']);
    }
    $campo = $this->entityTypeManager->getStorage('field_config')->load('media.documento.field_media_file');
    $lista = $campo ? (string) $campo->getSetting('file_extensions') : '';
    return array_values(array_filter(preg_split('/[\s,]+/', strtolower($lista)) ?: []));
  }

}
