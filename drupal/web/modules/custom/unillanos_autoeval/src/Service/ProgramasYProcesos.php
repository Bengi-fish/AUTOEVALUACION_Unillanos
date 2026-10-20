<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Busca programas y procesos: la base de las páginas de autoevaluación.
 *
 * Consultas puras sobre nodos publicados que no dependen de ningún otro
 * servicio del sitio. Las usan los servicios que arman los factores, el plan,
 * los documentos y la portada.
 */
class ProgramasYProcesos {

  /**
   * Etiquetas de caché de lo que lee la sección de autoevaluación.
   */
  const ETIQUETAS_AUTOEVALUACION = [
    'media_list',
    'node_list:proceso',
    'node_list:valoracion',
    'node_list:hallazgo',
    'node_list:plan_mejoramiento',
    'node_list:meta',
    'node_list:seguimiento',
    'paragraph_list',
    'taxonomy_term_list:elemento_modelo',
    'taxonomy_term_list:grado_cumplimiento',
    'taxonomy_term_list:responsable',
    'taxonomy_term_list:proyecto_institucional',
  ];

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Busca un programa publicado por su clave (`field_clave`).
   *
   * @param string $clave
   *   Clave del programa, por ejemplo `ingenieria-electronica`.
   *
   * @return \Drupal\node\NodeInterface|null
   *   El programa, o NULL si no existe.
   */
  public function programaPorClave(string $clave): ?NodeInterface {
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'programa')
      ->condition('status', 1)
      ->condition('field_clave', $clave)
      ->range(0, 1)
      ->execute();
    $programa = $ids ? $almacen->load(reset($ids)) : NULL;
    return $programa instanceof NodeInterface ? $programa : NULL;
  }

  /**
   * Devuelve los procesos publicados de un programa, el más reciente primero.
   *
   * @param \Drupal\node\NodeInterface $programa
   *   Nodo `programa`.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Los procesos, con el ID como clave.
   */
  public function procesosDelPrograma(NodeInterface $programa): array {
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'proceso')
      ->condition('status', 1)
      ->condition('field_programa', $programa->id())
      ->sort('nid', 'DESC')
      ->execute();
    return $ids ? $almacen->loadMultiple($ids) : [];
  }

  /**
   * Devuelve el proceso publicado más reciente de un programa.
   *
   * @param \Drupal\node\NodeInterface $programa
   *   Nodo `programa`.
   *
   * @return \Drupal\node\NodeInterface|null
   *   El proceso, o NULL si el programa no tiene ninguno publicado.
   */
  public function procesoDelPrograma(NodeInterface $programa): ?NodeInterface {
    $procesos = $this->procesosDelPrograma($programa);
    return $procesos ? reset($procesos) : NULL;
  }

}
