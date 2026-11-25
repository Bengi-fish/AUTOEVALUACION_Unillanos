<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\NodeInterface;

/**
 * Datos de /institucional/dependencias.
 *
 * Una entrada por dependencia, en su orden; dentro, los formatos agrupados por
 * nivel del programa (Pregrados, Especializaciones…) y por programa. Solo se
 * leen valores guardados y lo vacío se omite.
 */
class InstitucionalDependencias extends InstitucionalBase {

  /**
   * Orden de los niveles (clave guardada en el programa).
   */
  const NIVELES = ['pregrado', 'especializacion', 'maestria', 'doctorado'];

  /**
   * Rótulo de un nivel.
   *
   * @param string $nivel
   *   Clave del nivel; vacío para los programas sin nivel conocido.
   *
   * @return string
   *   El rótulo en plural («Pregrados»).
   */
  protected function rotulo(string $nivel): string {
    return match ($nivel) {
      'pregrado' => (string) $this->t('Pregrados'),
      'especializacion' => (string) $this->t('Especializaciones'),
      'maestria' => (string) $this->t('Maestrías'),
      'doctorado' => (string) $this->t('Doctorados'),
      default => (string) $this->t('Otros'),
    };
  }

  /**
   * Arma los datos de la página.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: dependencias (id, titulo, descripcion, total y grupos con titulo e
   *   items), total, edicion y vacio.
   */
  public function datos(CacheableMetadata $cache): array {
    $cache->addCacheTags(['node_list:dependencia', 'node_list:programa']);
    $cache->addCacheContexts(['user.permissions']);
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'dependencia')
      ->condition('status', 1)
      ->sort('field_orden')
      ->sort('nid')
      ->execute();
    $medios = $this->mediosQueApuntan('field_dependencia', [], $cache);
    $por_dependencia = [];
    foreach ($medios as $medio) {
      $por_dependencia[(int) $medio->get('field_dependencia')->target_id][] = $medio;
    }
    $dependencias = [];
    $total = 0;
    foreach ($almacen->loadMultiple($ids) as $dependencia) {
      if (!$dependencia->access('view')) {
        continue;
      }
      $cache->addCacheableDependency($dependencia);
      $grupos = $this->grupos($por_dependencia[(int) $dependencia->id()] ?? [], $cache);
      $cuenta = array_sum(array_map(fn(array $g): int => count($g['items']), $grupos));
      $edicion = array_filter([
        'documento' => $this->edicion->documentoPara('dependencia', (int) $dependencia->id(), (string) $this->t('Añadir un formato a @dependencia', ['@dependencia' => $dependencia->label()])),
        'editar' => $this->edicion->contenido((int) $dependencia->id(), (string) $this->t('Editar @dependencia', ['@dependencia' => $dependencia->label()])),
      ]);
      if (!$cuenta && !$edicion) {
        continue;
      }
      $dependencias[] = [
        'id' => 'dependencia-' . $dependencia->id(),
        'titulo' => (string) $dependencia->label(),
        'descripcion' => trim((string) $dependencia->get('field_descripcion')->value),
        'total' => $cuenta,
        'grupos' => $grupos,
        'edicion' => $edicion,
      ];
      $total += $cuenta;
    }
    return [
      'dependencias' => $dependencias,
      'total' => $total,
      'edicion' => array_filter(['nueva' => $this->edicion->dependenciaNueva()]),
      'vacio' => !$dependencias,
    ];
  }

  /**
   * Agrupa los formatos de una dependencia por nivel del programa.
   *
   * @param \Drupal\media\MediaInterface[] $medios
   *   Documentos de la dependencia.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché.
   *
   * @return array<int, array{titulo: string, items: array<int, array<string, mixed>>}>
   *   Un grupo por nivel (en el orden de NIVELES y «Otros» al final).
   */
  protected function grupos(array $medios, CacheableMetadata $cache): array {
    $por_nivel = [];
    $almacen = $this->entityTypeManager->getStorage('node');
    foreach ($medios as $medio) {
      $nivel = '';
      $id_programa = $medio->hasField('field_programa') ? $medio->get('field_programa')->target_id : NULL;
      $programa = $id_programa ? $almacen->load($id_programa) : NULL;
      if ($programa instanceof NodeInterface) {
        $cache->addCacheableDependency($programa);
        $nivel = (string) $programa->get('field_nivel')->value;
      }
      $por_nivel[in_array($nivel, self::NIVELES, TRUE) ? $nivel : ''][] = $medio;
    }
    $grupos = [];
    foreach ([...self::NIVELES, ''] as $clave) {
      $items = $this->elementos($por_nivel[$clave] ?? [], $cache);
      if ($items) {
        $grupos[] = ['titulo' => $this->rotulo($clave), 'items' => $items];
      }
    }
    return $grupos;
  }

}
