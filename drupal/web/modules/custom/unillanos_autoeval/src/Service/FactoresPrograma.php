<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\unillanos_autoeval\Traits\EtiquetasYOrdenTrait;

/**
 * Arma las tarjetas y las pestañas de factores del proceso de un programa.
 *
 * Sirve para cualquier programa y modelo: los factores salen del modelo
 * (lineamiento) del proceso. Solo lee valores guardados: no calcula grados.
 */
class FactoresPrograma {

  use StringTranslationTrait;
  use EtiquetasYOrdenTrait;

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ProgramasYProcesos $procesos,
  ) {}

  /**
   * Arma las tarjetas y las pestañas de factores del proceso de un programa.
   *
   * Sirve para cualquier programa y modelo: los factores salen del modelo
   * (lineamiento) que el proceso declara, se ordenan por número y solo se
   * incluyen los que tienen una valoración o algún hallazgo. Un hallazgo cuyo
   * elemento es una característica o un aspecto (modelo 2025) se agrupa en su
   * factor subiendo por el padre; el que no tiene elemento, o cuyo elemento no
   * llega a un factor, va al grupo «Sin factor asignado». Solo se leen valores
   * guardados: la etiqueta de grado es la del término `field_grado` y no se
   * calcula nada. Lo vacío no se devuelve.
   *
   * @param \Drupal\node\NodeInterface $programa
   *   Nodo `programa`.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: proceso (título), proceso_id, resultado (texto guardado), tarjetas
   *   (una por factor con valoración, para `factor-card`), pestanas (una por
   *   factor, y una final «Sin factor asignado» si hace falta, para
   *   `factor-tabs`), claves (clave de pestaña de cada factor) y
   *   factor_de_hallazgo (factor de cada hallazgo). Vacío si el programa no
   *   tiene un proceso con factores.
   */
  public function delPrograma(NodeInterface $programa, CacheableMetadata $cache): array {
    $cache->addCacheTags(ProgramasYProcesos::ETIQUETAS_AUTOEVALUACION);
    // El proceso más reciente puede no tener factores (p. ej. un registro
    // calificado) o no tener datos todavía: se usa el primero que sí los tenga.
    foreach ($this->procesos->procesosDelPrograma($programa) as $proceso) {
      $datos = $this->factoresDeProceso($proceso, $cache);
      if ($datos) {
        return $datos;
      }
    }
    return [];
  }

  /**
   * Lee los factores de un modelo (lineamiento), ordenados por número.
   *
   * Un factor sin número va después de los numerados.
   *
   * @param int $modelo
   *   ID del lineamiento (modelo).
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Los factores, con el ID como clave.
   */
  public function delModelo(int $modelo): array {
    $terminos = $this->entityTypeManager->getStorage('taxonomy_term');
    $ids = $terminos->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', 'elemento_modelo')
      ->condition('field_lineamiento', $modelo)
      ->condition('field_tipo_elemento', 'factor')
      ->execute();
    $factores = $terminos->loadMultiple($ids);
    $orden = fn($factor) => [
      $factor->get('field_numero')->isEmpty() ? PHP_INT_MAX : (int) $factor->get('field_numero')->value,
      (int) $factor->id(),
    ];
    uasort($factores, fn($a, $b) => $orden($a) <=> $orden($b));
    return $factores;
  }

  /**
   * Arma las tarjetas y las pestañas de un proceso.
   *
   * @param \Drupal\node\NodeInterface $proceso
   *   Nodo `proceso`.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Ver ::factoresDelPrograma(); vacío si el proceso no tiene modelo,
   *   factores o datos para mostrar.
   */
  protected function factoresDeProceso(NodeInterface $proceso, CacheableMetadata $cache): array {
    $cache->addCacheableDependency($proceso);
    $modelo = $proceso->get('field_lineamiento')->target_id;
    $almacen = $this->entityTypeManager->getStorage('node');

    // Factores del modelo del proceso, por número.
    $factores = $modelo ? $this->delModelo((int) $modelo) : [];
    if (!$factores) {
      return [];
    }

    // Valoraciones del proceso, solo las de un factor (una por factor).
    $valoraciones = [];
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'valoracion')
      ->condition('status', 1)
      ->condition('field_proceso', $proceso->id())
      ->sort('nid')
      ->execute();
    foreach ($almacen->loadMultiple($ids) as $valoracion) {
      $id_factor = (int) $valoracion->get('field_elemento')->target_id;
      if (isset($factores[$id_factor]) && !isset($valoraciones[$id_factor])) {
        $valoraciones[$id_factor] = $valoracion;
      }
    }

    // Hallazgos del proceso, agrupados por factor.
    $fortalezas = [];
    $aspectos = [];
    $sin_factor = ['fortaleza' => [], 'aspecto_por_mejorar' => []];
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'hallazgo')
      ->condition('status', 1)
      ->condition('field_proceso', $proceso->id())
      ->sort('nid')
      ->execute();
    $hallazgos = $almacen->loadMultiple($ids);
    $padres = NULL;
    $elementos = [];
    $factor_de_hallazgo = [];
    foreach ($hallazgos as $hallazgo) {
      $id_elemento = (int) $hallazgo->get('field_elemento')->target_id;
      if ($id_elemento && !isset($elementos[$id_elemento])) {
        // El árbol del vocabulario se lee una sola vez, no por nivel.
        $padres ??= $this->mapaDePadres();
        $elementos[$id_elemento] = $this->factorDelElemento($id_elemento, $factores, $padres);
      }
    }
    foreach ($hallazgos as $hallazgo) {
      $factor_de_hallazgo[(int) $hallazgo->id()] = $elementos[(int) $hallazgo->get('field_elemento')->target_id] ?? 0;
      $tipo = (string) $hallazgo->get('field_tipo_hallazgo')->value;
      $texto = $this->textoDeCampo($hallazgo->get('body')->first());
      if ($texto === '' || !in_array($tipo, ['fortaleza', 'aspecto_por_mejorar'], TRUE)) {
        continue;
      }
      $id_factor = $elementos[(int) $hallazgo->get('field_elemento')->target_id] ?? 0;
      if ($id_factor) {
        if ($tipo === 'fortaleza') {
          $fortalezas[$id_factor][] = $texto;
        }
        else {
          $aspectos[$id_factor][] = $texto;
        }
      }
      else {
        $sin_factor[$tipo][] = $texto;
      }
    }

    $tarjetas = [];
    $pestanas = [];
    $claves = [];
    $clave_de_factor = [];
    foreach ($factores as $id => $factor) {
      $valoracion = $valoraciones[$id] ?? NULL;
      if (!$valoracion && empty($fortalezas[$id]) && empty($aspectos[$id])) {
        continue;
      }
      $cache->addCacheableDependency($factor);
      $numero = (string) $factor->get('field_numero')->value;
      $dos_cifras = $numero !== '' ? sprintf('%02d', (int) $numero) : '';
      // La clave debe ser única en la página (ids de pestaña y panel): un
      // número repetido o ausente nunca pisa la clave de otro factor.
      $clave = 'f' . ($dos_cifras !== '' ? $dos_cifras : $id);
      if (isset($claves[$clave])) {
        $clave .= '-' . $id;
      }
      $claves[$clave] = TRUE;
      $clave_de_factor[$id] = $clave;
      $datos = [
        'key' => $clave,
        'number' => $dos_cifras,
        'name' => (string) $factor->label(),
        'label' => $dos_cifras !== '' ? (string) $this->t('Factor @numero', ['@numero' => $dos_cifras]) : '',
        'decimals' => 2,
      ];
      if ($valoracion) {
        $cache->addCacheableDependency($valoracion);
        $valor = $valoracion->get('field_valoracion')->value;
        if (is_numeric($valor)) {
          $datos['score'] = (float) $valor;
        }
        $grado = $valoracion->get('field_grado')->entity;
        if ($grado) {
          $cache->addCacheableDependency($grado);
          $datos['grade'] = (string) $grado->label();
        }
      }
      if (isset($datos['score'])) {
        $tarjetas[] = array_intersect_key($datos, array_flip(['number', 'name', 'score', 'grade', 'decimals'])) + [
          'goto' => $clave,
          'url' => '#factores',
        ];
      }
      $pestanas[] = $datos + [
        'strengths' => $fortalezas[$id] ?? [],
        'improvements' => $aspectos[$id] ?? [],
      ];
    }
    if ($sin_factor['fortaleza'] || $sin_factor['aspecto_por_mejorar']) {
      $pestanas[] = [
        'key' => 'sin-factor',
        'number' => '',
        'name' => (string) $this->t('Sin factor asignado'),
        'label' => '',
        'strengths' => $sin_factor['fortaleza'],
        'improvements' => $sin_factor['aspecto_por_mejorar'],
      ];
    }
    if (!$pestanas) {
      return [];
    }
    return [
      'proceso' => (string) $proceso->label(),
      'proceso_id' => (int) $proceso->id(),
      'resultado' => (string) $proceso->get('field_resultado')->value,
      'tarjetas' => $tarjetas,
      'pestanas' => $pestanas,
      // Para enlazar el plan con los factores: clave de cada factor mostrado
      // y factor (0 si ninguno) de cada hallazgo publicado del proceso.
      'claves' => $clave_de_factor,
      'factor_de_hallazgo' => $factor_de_hallazgo,
    ];
  }

  /**
   * Devuelve el mapa de padres del vocabulario de elementos del modelo.
   *
   * @return array<int, int[]>
   *   Los IDs de los padres de cada término, con el ID del término como
   *   clave. Una sola lectura del vocabulario en vez de una por término.
   */
  protected function mapaDePadres(): array {
    $mapa = [];
    $arbol = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('elemento_modelo', 0, NULL, FALSE);
    foreach ($arbol as $termino) {
      $mapa[(int) $termino->tid] = array_map('intval', array_filter((array) $termino->parents));
    }
    return $mapa;
  }

  /**
   * Devuelve el ID del factor al que pertenece un elemento del modelo.
   *
   * @param int $id_elemento
   *   ID del término `elemento_modelo`.
   * @param \Drupal\taxonomy\TermInterface[] $factores
   *   Factores del modelo del proceso, con el ID como clave.
   * @param array<int, int[]> $padres
   *   Mapa de padres (ver ::mapaDePadres()).
   *
   * @return int
   *   El ID del factor (el propio elemento, o su ancestro más cercano que sea
   *   un factor del modelo), o 0 si no llega a ninguno.
   */
  protected function factorDelElemento(int $id_elemento, array $factores, array $padres): int {
    $visitados = [];
    $pendientes = [$id_elemento];
    while ($pendientes) {
      $id = array_shift($pendientes);
      if (isset($factores[$id])) {
        return $id;
      }
      if (isset($visitados[$id])) {
        continue;
      }
      $visitados[$id] = TRUE;
      foreach ($padres[$id] ?? [] as $padre) {
        $pendientes[] = $padre;
      }
    }
    return 0;
  }

}
