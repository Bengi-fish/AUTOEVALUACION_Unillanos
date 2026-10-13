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
   * Arma los datos de la cabecera y la ficha de un programa.
   *
   * Solo incluye lo que tiene valor: un dato vacío no se devuelve, para que
   * la plantilla no deje huecos. La facultad va en `eyebrow` (como en el
   * prototipo) y no se repite en la ficha.
   *
   * @param \Drupal\node\NodeInterface $programa
   *   Nodo `programa`.
   *
   * @return array<string, mixed>
   *   Con: nombre, nombre_inicio (primera palabra), nombre_acento (resto),
   *   eyebrow (facultad y sede), etiquetas (nivel y modalidad) y ficha (lista
   *   de pares term/value).
   */
  public function datosPrograma(NodeInterface $programa): array {
    $nombre = trim((string) $programa->label());
    $palabras = preg_split('/\s+/', $nombre, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    // Como el prototipo: la primera palabra en negro y el resto en acento.
    // En nombres largos (más de cuatro palabras) solo las dos últimas.
    $corte = count($palabras) > 4 ? count($palabras) - 2 : min(1, count($palabras));
    $inicio = implode(' ', array_slice($palabras, 0, $corte));
    $acento = implode(' ', array_slice($palabras, $corte));
    $facultad = (string) $programa->get('field_facultad')->entity?->label();
    $sede = (string) $programa->get('field_sede')->entity?->label();
    $nivel = $this->etiquetaLista($programa->get('field_nivel'));
    $modalidad = $this->etiquetaLista($programa->get('field_modalidad'));
    $datos = [
      'Nivel' => $nivel,
      'Modalidad' => $modalidad,
      'Sede' => $sede,
      'Dirección del programa' => (string) $programa->get('field_director')->value,
      'Código SNIES' => (string) $programa->get('field_codigo_snies')->value,
      'Resolución de registro' => (string) $programa->get('field_resolucion_registro')->value,
      'Registro vigente hasta' => $this->fechaMesAnio($programa->get('field_registro_hasta')->value),
      'Resolución de acreditación' => (string) $programa->get('field_resolucion_acreditacion')->value,
      'Acreditación vigente hasta' => $this->fechaMesAnio($programa->get('field_acreditacion_hasta')->value),
    ];
    $ficha = [];
    foreach ($datos as $termino => $valor) {
      if (trim($valor) !== '') {
        $ficha[] = ['term' => $termino, 'value' => trim($valor)];
      }
    }
    return [
      'nombre' => $nombre,
      'nombre_inicio' => $inicio,
      'nombre_acento' => $acento,
      'eyebrow' => implode(' · ', array_filter([$facultad, $sede])),
      'etiquetas' => array_values(array_filter([$nivel, $modalidad])),
      'ficha' => $ficha,
    ];
  }

  /**
   * Escribe una fecha (AAAA-MM-DD) como «diciembre de 2029».
   *
   * @param string|null $fecha
   *   Fecha guardada.
   *
   * @return string
   *   El mes y el año, o una cadena vacía si no hay fecha válida.
   */
  public function fechaMesAnio(?string $fecha): string {
    if (!$fecha || !preg_match('/^(\d{4})-(\d{2})-\d{2}/', $fecha, $partes)) {
      return '';
    }
    $meses = [
      1 => 'enero',
      2 => 'febrero',
      3 => 'marzo',
      4 => 'abril',
      5 => 'mayo',
      6 => 'junio',
      7 => 'julio',
      8 => 'agosto',
      9 => 'septiembre',
      10 => 'octubre',
      11 => 'noviembre',
      12 => 'diciembre',
    ];
    $mes = $meses[(int) $partes[2]] ?? '';
    return $mes === '' ? '' : $mes . ' de ' . $partes[1];
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
