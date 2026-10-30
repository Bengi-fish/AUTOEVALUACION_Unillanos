<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\unillanos_autoeval\Traits\EtiquetasYOrdenTrait;
use Drupal\unillanos_autoeval\Traits\FormatoTextoTrait;

/**
 * Arma los documentos publicados: la página /documentos y los de un programa.
 *
 * Solo lee valores guardados. Un documento ligado a un proceso se asigna al
 * programa de ese proceso; si el proceso o su programa no están publicados (o
 * no se pueden ver), el documento no se muestra.
 */
class DocumentosPublicados {

  use StringTranslationTrait;
  use EtiquetasYOrdenTrait;
  use FormatoTextoTrait;

  /**
   * Orden de las categorías de documento (la clave guardada).
   */
  const CATEGORIAS_DOCUMENTO = ['normatividad', 'documento_base', 'informe', 'acto_administrativo', 'soporte'];

  /**
   * Orden de las categorías dentro de los documentos de un programa.
   */
  const CATEGORIAS_PROGRAMA = ['informe', 'documento_base', 'acto_administrativo', 'soporte', 'normatividad'];

  /**
   * Programas con documentos desde los que se ofrece una lista de salto.
   */
  const MIN_PROGRAMAS_SALTO = 6;

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Escribe un tamaño en bytes: «197 KB», «5,2 MB».
   *
   * @param int $bytes
   *   Tamaño del archivo.
   *
   * @return string
   *   El tamaño con coma decimal; vacío si no hay tamaño.
   */
  public function tamano(int $bytes): string {
    if ($bytes <= 0) {
      return '';
    }
    if ($bytes < 1024) {
      return $bytes . ' B';
    }
    // Hasta 1.023 KB; por encima (incluido lo que redondearía a 1.024 KB), MB.
    if (round($bytes / 1024) < 1024) {
      return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    }
    return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
  }

  /**
   * Lee los documentos publicados que el visitante puede ver.
   *
   * Un documento ligado a un proceso se asigna al programa de ese proceso; si
   * el proceso o su programa no están publicados (o no se pueden ver), el
   * documento no se muestra. Sin proceso, o con un proceso sin programa
   * (institucional), es un documento general.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   * @param int[]|null $ids_proceso
   *   Si se indica, solo los documentos ligados a esos procesos.
   *
   * @return array<int, array<string, mixed>>
   *   Cada documento con: item (title, cat, desc, meta, kind y url),
   *   categoria (clave), fecha, programa (nodo o NULL) y orden.
   */
  protected function visibles(CacheableMetadata $cache, ?array $ids_proceso = NULL): array {
    $cache->addCacheTags(['media_list', 'node_list:proceso', 'node_list:programa', 'node_list:lineamiento']);
    $cache->addCacheContexts(['user.permissions']);
    $medios_almacen = $this->entityTypeManager->getStorage('media');
    $consulta = $medios_almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('bundle', 'documento')
      ->condition('status', 1);
    if ($ids_proceso !== NULL) {
      if (!$ids_proceso) {
        return [];
      }
      $consulta->condition('field_proceso', $ids_proceso, 'IN');
    }
    $ids = $consulta->execute();
    $medios = $medios_almacen->loadMultiple($ids);

    // Procesos y programas de una vez.
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids_proceso = array_filter(array_map(fn($medio) => $medio->get('field_proceso')->target_id, $medios));
    $procesos = $ids_proceso ? $almacen->loadMultiple(array_unique($ids_proceso)) : [];
    $ids_programa = array_filter(array_map(fn($proceso) => $proceso->get('field_programa')->target_id, $procesos));
    $programas = $ids_programa ? $almacen->loadMultiple(array_unique($ids_programa)) : [];

    $documentos = [];
    foreach ($medios as $medio) {
      // La consulta no filtra los medios por permiso: se comprueba cada uno.
      $acceso_medio = $medio->access('view', NULL, TRUE);
      $cache->addCacheableDependency($acceso_medio);
      if (!$acceso_medio->isAllowed()) {
        continue;
      }
      $archivo = $medio->get('field_media_file')->entity;
      if (!$archivo instanceof FileInterface) {
        continue;
      }
      $proceso = $procesos[(int) $medio->get('field_proceso')->target_id] ?? NULL;
      $programa = NULL;
      if ($medio->get('field_proceso')->target_id) {
        // Ligado a un proceso: debe poder verse, y su programa también.
        if (!$proceso instanceof NodeInterface || !$proceso->isPublished() || !$proceso->access('view')) {
          continue;
        }
        $id_programa = $proceso->get('field_programa')->target_id;
        if ($id_programa) {
          $candidato = $programas[(int) $id_programa] ?? NULL;
          if (!$candidato instanceof NodeInterface || !$candidato->isPublished()) {
            continue;
          }
          $acceso = $candidato->access('view', NULL, TRUE);
          $cache->addCacheableDependency($acceso);
          if (!$acceso->isAllowed()) {
            continue;
          }
          $programa = $candidato;
          $cache->addCacheableDependency($programa);
        }
      }
      $cache->addCacheableDependency($medio);
      $cache->addCacheableDependency($archivo);
      $lineamiento = $medio->get('field_lineamiento')->entity;
      // El título del lineamiento solo se usa si está publicado.
      $lineamiento = $lineamiento instanceof NodeInterface && $lineamiento->isPublished() ? $lineamiento : NULL;
      $descripcion = $lineamiento ? (string) $lineamiento->label() : ($proceso ? (string) $proceso->label() : '');
      $nombre_archivo = (string) $archivo->getFilename();
      // Sin extensión no se sabe el tipo: rótulo genérico (nunca «PDF»).
      $tipo = strtoupper(pathinfo($nombre_archivo, PATHINFO_EXTENSION)) ?: 'DOC';
      $fecha = $this->fechaMesAnio($medio->get('field_fecha_publicacion')->value);
      $categoria = (string) $medio->get('field_categoria')->value;
      $orden = array_search($categoria, self::CATEGORIAS_DOCUMENTO, TRUE);
      $orden_programa = array_search($categoria, self::CATEGORIAS_PROGRAMA, TRUE);
      $documentos[] = [
        'item' => [
          'title' => $this->sinCortesEnPeriodo(trim((string) $medio->label())),
          'cat' => $this->etiquetaLista($medio->get('field_categoria')),
          'desc' => $descripcion,
          'meta' => implode(' · ', array_filter([$tipo, $this->tamano((int) $archivo->getSize()), $fecha])),
          'kind' => $tipo,
          'url' => $archivo->createFileUrl(),
        ],
        'categoria' => $categoria,
        'fecha' => (string) $medio->get('field_fecha_publicacion')->value,
        'programa' => $programa,
        'orden' => $orden === FALSE ? count(self::CATEGORIAS_DOCUMENTO) : $orden,
        'orden_programa' => $orden_programa === FALSE ? count(self::CATEGORIAS_PROGRAMA) : $orden_programa,
      ];
    }
    // Por categoría; dentro de ella, la fecha más reciente y el nombre.
    usort($documentos, function (array $a, array $b): int {
      return [$a['orden'], $b['fecha']] <=> [$b['orden'], $a['fecha']]
        ?: $this->compararTextos($a['item']['title'], $b['item']['title']);
    });
    return $documentos;
  }

  /**
   * Arma la página de documentos: generales por categoría y por programa.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: generales (una lista por categoría con categoria e items), programas
   *   (por programa: id, nombre, url e items), total y salto (TRUE si hay
   *   muchos programas con documentos y conviene una lista de salto).
   */
  public function publicados(CacheableMetadata $cache): array {
    $generales = [];
    $por_programa = [];
    $total = 0;
    foreach ($this->visibles($cache) as $documento) {
      $total++;
      if ($documento['programa'] instanceof NodeInterface) {
        $id = (int) $documento['programa']->id();
        $por_programa[$id]['programa'] = $documento['programa'];
        $por_programa[$id]['documentos'][] = $documento;
        continue;
      }
      $generales[$documento['categoria']]['items'][] = $documento['item'];
    }
    $listas_generales = [];
    foreach (self::CATEGORIAS_DOCUMENTO as $categoria) {
      if (!empty($generales[$categoria]['items'])) {
        $listas_generales[] = ['categoria' => $categoria, 'items' => $generales[$categoria]['items']];
      }
    }
    // Categorías no previstas, al final.
    foreach ($generales as $categoria => $datos) {
      if (!in_array($categoria, self::CATEGORIAS_DOCUMENTO, TRUE)) {
        $listas_generales[] = ['categoria' => $categoria, 'items' => $datos['items']];
      }
    }
    $programas = [];
    foreach ($por_programa as $id => $datos) {
      $url = $datos['programa']->toUrl()->toString(TRUE);
      $items = array_column($this->ordenarDeProgramas($datos['documentos']), 'item');
      $cache->addCacheableDependency($url);
      $programas[] = [
        'id' => $id,
        'nombre' => (string) $datos['programa']->label(),
        'url' => $url->getGeneratedUrl(),
        'items' => $items,
      ];
    }
    usort($programas, fn($a, $b) => $this->compararTextos($a['nombre'], $b['nombre']));
    return [
      'generales' => $listas_generales,
      'programas' => $programas,
      'total' => $total,
      'salto' => count($programas) >= self::MIN_PROGRAMAS_SALTO,
    ];
  }

  /**
   * Arma los documentos de un programa y sus enlaces destacados.
   *
   * @param \Drupal\node\NodeInterface $programa
   *   Nodo `programa`.
   * @param int[] $ids_proceso
   *   IDs de los procesos del programa, el más reciente primero (los calcula
   *   quien llama: no depende de otros servicios de la página).
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: items (todos los documentos del programa), informe (el documento de
   *   categoría informe, si hay exactamente uno) y plan (el de categoría
   *   documento base, si hay exactamente uno); cada uno con title, url, kind y
   *   meta. Vacío si el programa no tiene documentos. Las páginas rotulan estos
   *   enlaces con el título del propio documento: la categoría no garantiza
   *   que sea «el informe» o «el plan» (no hay categoría propia para el plan).
   */
  public function delPrograma(NodeInterface $programa, array $ids_proceso, CacheableMetadata $cache): array {
    $items = [];
    $informes = [];
    $planes = [];
    // Solo los documentos de los procesos de este programa.
    $propios = array_filter(
      $this->visibles($cache, $ids_proceso),
      fn(array $d) => $d['programa'] instanceof NodeInterface && (int) $d['programa']->id() === (int) $programa->id()
    );
    foreach ($this->ordenarDeProgramas($propios) as $documento) {
      $items[] = $documento['item'];
      if ($documento['categoria'] === 'informe') {
        $informes[] = $documento['item'];
      }
      elseif ($documento['categoria'] === 'documento_base') {
        $planes[] = $documento['item'];
      }
    }
    if (!$items) {
      return [];
    }
    return [
      'items' => $items,
      'informe' => count($informes) === 1 ? $informes[0] : NULL,
      'plan' => count($planes) === 1 ? $planes[0] : NULL,
    ];
  }

  /**
   * Ordena los documentos de un programa: informe, documento base y demás.
   *
   * Dentro de cada categoría, la fecha más reciente primero y luego el nombre.
   *
   * @param array<int, array<string, mixed>> $documentos
   *   Documentos de ::visibles().
   *
   * @return array<int, array<string, mixed>>
   *   Los mismos documentos, ordenados.
   */
  protected function ordenarDeProgramas(array $documentos): array {
    $documentos = array_values($documentos);
    usort($documentos, function (array $a, array $b): int {
      return [$a['orden_programa'], $b['fecha']] <=> [$b['orden_programa'], $a['fecha']]
        ?: $this->compararTextos($a['item']['title'], $b['item']['title']);
    });
    return $documentos;
  }

}
