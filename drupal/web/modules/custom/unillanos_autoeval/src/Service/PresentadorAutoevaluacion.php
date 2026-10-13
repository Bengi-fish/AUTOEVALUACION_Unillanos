<?php

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;

/**
 * Arma, desde las entidades, los datos que muestran las páginas públicas.
 *
 * Devuelve arreglos de texto ya listos para los componentes del tema: solo
 * lee valores guardados, no calcula grados, destinos ni avances (esas reglas
 * viven en sus propios servicios y siguen pendientes de Acreditación). Lo que
 * falta se devuelve vacío para que la plantilla lo omita o lo marque como
 * pendiente. Nunca usa el título de un nodo cuando este es un identificador
 * (`valoracion`, `seguimiento`): las etiquetas salen de los campos.
 */
class PresentadorAutoevaluacion {

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Da formato numérico con coma decimal (4,27).
   *
   * @param float|int|string|null $valor
   *   Valor guardado.
   * @param int $decimales
   *   Cantidad de decimales.
   *
   * @return string
   *   El número formateado, o una cadena vacía si no es numérico.
   */
  public function numero(float|int|string|NULL $valor, int $decimales = 2): string {
    if ($valor === NULL || !is_numeric($valor)) {
      return '';
    }
    return number_format((float) $valor, $decimales, ',', '');
  }

  /**
   * Devuelve la etiqueta de un campo de lista (texto) con su valor guardado.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $campo
   *   Campo de tipo lista de texto.
   *
   * @return string
   *   La etiqueta, o una cadena vacía si el campo está vacío.
   */
  public function etiquetaLista(FieldItemListInterface $campo): string {
    $valor = $campo->value;
    if ($valor === NULL || $valor === '') {
      return '';
    }
    $permitidos = $campo->getFieldDefinition()->getFieldStorageDefinition()->getSetting('allowed_values') ?? [];
    return (string) ($permitidos[$valor] ?? $valor);
  }

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
   * Devuelve el proceso publicado más reciente de un programa.
   *
   * @param \Drupal\node\NodeInterface $programa
   *   Nodo `programa`.
   *
   * @return \Drupal\node\NodeInterface|null
   *   El proceso, o NULL si el programa no tiene ninguno publicado.
   */
  public function procesoDelPrograma(NodeInterface $programa): ?NodeInterface {
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'proceso')
      ->condition('status', 1)
      ->condition('field_programa', $programa->id())
      ->sort('nid', 'DESC')
      ->range(0, 1)
      ->execute();
    $proceso = $ids ? $almacen->load(reset($ids)) : NULL;
    return $proceso instanceof NodeInterface ? $proceso : NULL;
  }

  /**
   * Resume los procesos de programa publicados para la portada.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee: cada proceso, cada
   *   programa, el acceso a este y su URL (que depende del alias).
   *
   * @return array<int, array<string, string>>
   *   Un elemento por proceso con: programa, url_programa, proceso, periodo,
   *   estado y resultado (texto guardado, tal cual). Los datos que faltan
   *   quedan como cadena vacía.
   */
  public function procesosPublicados(CacheableMetadata $cache): array {
    $almacen = $this->entityTypeManager->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'proceso')
      ->condition('status', 1)
      ->exists('field_programa')
      ->sort('nid')
      ->execute();
    $procesos = $almacen->loadMultiple($ids);
    $ids_programa = [];
    foreach ($procesos as $proceso) {
      $ids_programa[] = $proceso->get('field_programa')->target_id;
    }
    $programas = $almacen->loadMultiple(array_filter($ids_programa));
    $resumen = [];
    foreach ($procesos as $proceso) {
      $cache->addCacheableDependency($proceso);
      $programa = $programas[$proceso->get('field_programa')->target_id] ?? NULL;
      $visible = FALSE;
      if ($programa instanceof NodeInterface) {
        $cache->addCacheableDependency($programa);
        $acceso = $programa->access('view', NULL, TRUE);
        $cache->addCacheableDependency($acceso);
        $visible = $acceso->isAllowed() && $programa->isPublished();
      }
      $url = '';
      if ($visible) {
        $generada = $programa->toUrl()->toString(TRUE);
        $cache->addCacheableDependency($generada);
        $url = $generada->getGeneratedUrl();
      }
      $resumen[] = [
        'programa' => $visible ? (string) $programa->label() : '',
        'url_programa' => $url,
        'proceso' => (string) $proceso->label(),
        'periodo' => (string) $proceso->get('field_periodo_evaluado')->value,
        'estado' => $this->etiquetaLista($proceso->get('field_estado')),
        'resultado' => (string) $proceso->get('field_resultado')->value,
      ];
    }
    return $resumen;
  }

}
