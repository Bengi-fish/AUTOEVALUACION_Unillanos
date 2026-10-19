<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\node\NodeInterface;
use Drupal\unillanos_autoeval\Traits\EtiquetasYOrdenTrait;

/**
 * Arma el listado de programas y decide cuáles tienen autoevaluación.
 *
 * Es la única fuente de la regla «con autoevaluación»: el listado de
 * /programas y la portada la usan por medio de ::procesosPorPrograma(). Solo
 * lee valores guardados; no calcula nada.
 */
class ListadoProgramas {

  use EtiquetasYOrdenTrait;

  /**
   * Programas que devuelve «otros de la facultad» como máximo.
   */
  const MAX_OTROS_DE_LA_FACULTAD = 4;

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Agrupa los procesos publicados por programa visible.
   *
   * Regla de «con autoevaluación»: el programa está publicado, el visitante
   * puede verlo y tiene al menos un proceso publicado y visible. No se exige
   * que el proceso tenga valoraciones ni hallazgos.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de lo que se lee.
   *
   * @return array<int, array{programa: \Drupal\node\NodeInterface, procesos: \Drupal\node\NodeInterface[]}>
   *   Por ID de programa: el programa y sus procesos, el más reciente primero.
   *   El orden de los programas es el de su proceso más reciente.
   */
  public function procesosPorPrograma(CacheableMetadata $cache): array {
    $cache->addCacheTags(['node_list:programa', 'node_list:proceso']);
    $cache->addCacheContexts(['user.permissions']);
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'proceso')
      ->condition('status', 1)
      ->exists('field_programa')
      ->sort('nid', 'DESC')
      ->execute();
    $procesos = $almacen->loadMultiple($ids);
    $ids_programa = array_filter(array_map(
      fn($proceso) => $proceso->get('field_programa')->target_id,
      $procesos
    ));
    $programas = $almacen->loadMultiple($ids_programa);

    $por_programa = [];
    foreach ($procesos as $proceso) {
      $id_programa = (int) $proceso->get('field_programa')->target_id;
      $programa = $programas[$id_programa] ?? NULL;
      if (!$programa instanceof NodeInterface || !$programa->isPublished()) {
        continue;
      }
      $por_programa[$id_programa][] = $proceso;
    }
    $agrupados = [];
    foreach ($por_programa as $id_programa => $lista) {
      $programa = $programas[$id_programa];
      $acceso = $programa->access('view', NULL, TRUE);
      $cache->addCacheableDependency($acceso);
      if ($acceso->isAllowed()) {
        $agrupados[$id_programa] = ['programa' => $programa, 'procesos' => $lista];
      }
    }
    return $agrupados;
  }

  /**
   * Arma el listado de programas con sus filtros.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: programas (ver ::tarjetas()), total, con_autoevaluacion (cuántos
   *   tienen autoevaluación) y filtros (facultad y nivel si hay valores; sede
   *   solo con dos o más valores distintos), cada uno como lista de pares
   *   valor y etiqueta.
   */
  public function listado(CacheableMetadata $cache): array {
    $con_proceso = $this->procesosPorPrograma($cache);
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'programa')
      ->condition('status', 1)
      ->execute();
    $programas = $ids ? $almacen->loadMultiple($ids) : [];
    $tarjetas = $this->tarjetas($programas, $con_proceso, $cache);
    $filtros = $this->filtros($tarjetas, $programas, $cache);
    return [
      'programas' => $tarjetas,
      'total' => count($tarjetas),
      'con_autoevaluacion' => count(array_filter(array_column($tarjetas, 'con_autoevaluacion'))),
      'filtros' => $filtros,
    ];
  }

  /**
   * Devuelve otros programas de la misma facultad.
   *
   * @param \Drupal\node\NodeInterface $programa
   *   El programa actual: no se incluye.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de lo que se lee.
   * @param int $limite
   *   Máximo de programas.
   *
   * @return array<int, array<string, mixed>>
   *   Programas con la forma de ::tarjetas(), con autoevaluación primero y
   *   luego alfabético. Vacío si el programa no tiene facultad.
   */
  public function otrosDeLaFacultad(NodeInterface $programa, CacheableMetadata $cache, int $limite = self::MAX_OTROS_DE_LA_FACULTAD): array {
    $cache->addCacheTags(['node_list:programa', 'taxonomy_term_list:facultad']);
    $cache->addCacheableDependency($programa);
    $facultad = $programa->get('field_facultad')->target_id;
    if (!$facultad || $limite < 1) {
      return [];
    }
    $con_proceso = $this->procesosPorPrograma($cache);
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'programa')
      ->condition('status', 1)
      ->condition('field_facultad', $facultad)
      ->condition('nid', $programa->id(), '<>')
      ->execute();
    $otros = $ids ? $almacen->loadMultiple($ids) : [];
    return array_slice($this->tarjetas($otros, $con_proceso, $cache), 0, $limite);
  }

  /**
   * Arma y ordena los programas: con autoevaluación primero, luego alfabético.
   *
   * @param \Drupal\node\NodeInterface[] $programas
   *   Programas publicados y visibles.
   * @param array<int, mixed> $con_proceso
   *   Resultado de ::procesosPorPrograma(); solo importan las claves.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché (programas, términos y URL).
   *
   * @return array<int, array<string, mixed>>
   *   Por programa, solo lo que existe: id, nombre, url, facultad, facultad_id,
   *   nivel, nivel_clave, sede, sede_id, con_autoevaluacion (solo si es TRUE)
   *   y buscar (nombre, facultad, nivel y sede sin tildes ni mayúsculas).
   */
  protected function tarjetas(array $programas, array $con_proceso, CacheableMetadata $cache): array {
    $cache->addCacheTags(['node_list:programa', 'taxonomy_term_list:facultad', 'taxonomy_term_list:sede']);
    $cache->addCacheContexts(['user.permissions']);
    $tarjetas = [];
    foreach ($programas as $programa) {
      $cache->addCacheableDependency($programa);
      $facultad = $programa->get('field_facultad')->entity;
      $sede = $programa->get('field_sede')->entity;
      $nivel = $this->etiquetaLista($programa->get('field_nivel'));
      $url = $programa->toUrl()->toString(TRUE);
      $cache->addCacheableDependency($url);
      $partes = [
        'id' => (int) $programa->id(),
        'nombre' => trim((string) $programa->label()),
        'url' => $url->getGeneratedUrl(),
        'facultad' => trim((string) $facultad?->label()),
        'facultad_id' => $facultad ? (string) $facultad->id() : '',
        'nivel' => $nivel,
        'nivel_clave' => (string) $programa->get('field_nivel')->value,
        'sede' => trim((string) $sede?->label()),
        'sede_id' => $sede ? (string) $sede->id() : '',
        'con_autoevaluacion' => isset($con_proceso[(int) $programa->id()]),
      ];
      $tarjeta = array_filter($partes, fn($valor) => $valor !== '' && $valor !== FALSE);
      $tarjeta['buscar'] = $this->normalizar(implode(' ', array_filter([
        $partes['nombre'],
        $partes['facultad'],
        $partes['nivel'],
        $partes['sede'],
      ])));
      $tarjetas[] = $tarjeta;
    }
    usort($tarjetas, fn(array $a, array $b): int => (int) !empty($b['con_autoevaluacion']) <=> (int) !empty($a['con_autoevaluacion'])
      ?: $this->compararTextos($a['nombre'], $b['nombre']));
    return $tarjetas;
  }

  /**
   * Arma los filtros con los valores que existen en los programas.
   *
   * @param array<int, array<string, mixed>> $tarjetas
   *   Resultado de ::tarjetas().
   * @param \Drupal\node\NodeInterface[] $programas
   *   Los programas de los que salen las tarjetas.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de las etiquetas de nivel.
   *
   * @return array<string, array<int, array{valor: string, etiqueta: string}>>
   *   Facultad y nivel con al menos un valor; sede con dos o más. Las
   *   facultades y las sedes van alfabéticas; los niveles, en el orden de la
   *   lista de valores permitidos del campo.
   */
  protected function filtros(array $tarjetas, array $programas, CacheableMetadata $cache): array {
    $facultades = [];
    $sedes = [];
    $niveles = [];
    foreach ($tarjetas as $tarjeta) {
      if (isset($tarjeta['facultad_id'], $tarjeta['facultad'])) {
        $facultades[$tarjeta['facultad_id']] = $tarjeta['facultad'];
      }
      if (isset($tarjeta['sede_id'], $tarjeta['sede'])) {
        $sedes[$tarjeta['sede_id']] = $tarjeta['sede'];
      }
      if (isset($tarjeta['nivel_clave'], $tarjeta['nivel'])) {
        $niveles[$tarjeta['nivel_clave']] = $tarjeta['nivel'];
      }
    }
    $filtros = [];
    if ($facultades) {
      $filtros['facultad'] = $this->opciones($facultades);
    }
    if ($niveles) {
      // Orden de los valores permitidos del campo (pregrado, posgrado…).
      $primero = reset($programas);
      $definicion = $primero ? $primero->get('field_nivel')->getFieldDefinition()->getFieldStorageDefinition() : NULL;
      if ($definicion instanceof FieldStorageDefinitionInterface) {
        $cache->addCacheTags($definicion->getCacheTags());
        $orden = array_keys($definicion->getSetting('allowed_values') ?? []);
        uksort($niveles, fn($a, $b): int => (array_search($a, $orden, TRUE) <=> array_search($b, $orden, TRUE)));
      }
      $filtros['nivel'] = $this->opciones($niveles, FALSE);
    }
    if (count($sedes) >= 2) {
      $filtros['sede'] = $this->opciones($sedes);
    }
    return $filtros;
  }

  /**
   * Convierte un mapa valor → etiqueta en una lista de opciones.
   *
   * @param array<int|string, string> $mapa
   *   Valor y etiqueta.
   * @param bool $alfabetico
   *   TRUE para ordenar por etiqueta; FALSE conserva el orden recibido.
   *
   * @return array<int, array{valor: string, etiqueta: string}>
   *   Las opciones.
   */
  protected function opciones(array $mapa, bool $alfabetico = TRUE): array {
    if ($alfabetico) {
      uasort($mapa, fn(string $a, string $b): int => $this->compararTextos($a, $b));
    }
    $opciones = [];
    foreach ($mapa as $valor => $etiqueta) {
      $opciones[] = ['valor' => (string) $valor, 'etiqueta' => $etiqueta];
    }
    return $opciones;
  }

  /**
   * Escribe un texto sin tildes y en minúsculas, para buscar.
   *
   * @param string $texto
   *   El texto.
   *
   * @return string
   *   El texto normalizado («Ingeniería» → «ingenieria»).
   */
  protected function normalizar(string $texto): string {
    $sin_marcas = class_exists('\Transliterator')
      ? \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC')?->transliterate($texto)
      : NULL;
    return mb_strtolower($sin_marcas ?: $texto);
  }

}
