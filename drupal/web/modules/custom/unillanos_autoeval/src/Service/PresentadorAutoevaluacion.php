<?php

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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

  use StringTranslationTrait;

  /**
   * Etiquetas de caché de lo que lee la sección de autoevaluación.
   */
  const ETIQUETAS_AUTOEVALUACION = [
    'node_list:proceso',
    'node_list:valoracion',
    'node_list:hallazgo',
    'taxonomy_term_list:elemento_modelo',
    'taxonomy_term_list:grado_cumplimiento',
  ];

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
   *   Con: proceso (título), resultado (texto guardado), tarjetas (una por
   *   factor con valoración, para `factor-card`) y pestanas (una por factor, y
   *   una final «Sin factor asignado» si hace falta, para `factor-tabs`).
   *   Vacío si el programa no tiene un proceso con factores.
   */
  public function factoresDelPrograma(NodeInterface $programa, CacheableMetadata $cache): array {
    $cache->addCacheTags(self::ETIQUETAS_AUTOEVALUACION);
    // El proceso más reciente puede no tener factores (p. ej. un registro
    // calificado) o no tener datos todavía: se usa el primero que sí los tenga.
    foreach ($this->procesosDelPrograma($programa) as $proceso) {
      $datos = $this->factoresDeProceso($proceso, $cache);
      if ($datos) {
        return $datos;
      }
    }
    return [];
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
    $terminos = $this->entityTypeManager->getStorage('taxonomy_term');

    // Factores del modelo del proceso, por número.
    $factores = [];
    if ($modelo) {
      $ids = $terminos->getQuery()
        ->accessCheck(TRUE)
        ->condition('vid', 'elemento_modelo')
        ->condition('field_lineamiento', $modelo)
        ->condition('field_tipo_elemento', 'factor')
        ->execute();
      $factores = $terminos->loadMultiple($ids);
      // Por número; un factor sin número va después de los numerados.
      $orden = fn($factor) => [
        $factor->get('field_numero')->isEmpty() ? PHP_INT_MAX : (int) $factor->get('field_numero')->value,
        (int) $factor->id(),
      ];
      uasort($factores, fn($a, $b) => $orden($a) <=> $orden($b));
    }
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
    foreach ($hallazgos as $hallazgo) {
      $id_elemento = (int) $hallazgo->get('field_elemento')->target_id;
      if ($id_elemento && !isset($elementos[$id_elemento])) {
        // El árbol del vocabulario se lee una sola vez, no por nivel.
        $padres ??= $this->mapaDePadres();
        $elementos[$id_elemento] = $this->factorDelElemento($id_elemento, $factores, $padres);
      }
    }
    foreach ($hallazgos as $hallazgo) {
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
      'resultado' => (string) $proceso->get('field_resultado')->value,
      'tarjetas' => $tarjetas,
      'pestanas' => $pestanas,
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

  /**
   * Devuelve el texto de un campo de texto largo como texto plano.
   *
   * Un campo `plain_text` ya es texto: solo se recortan los espacios (así un
   * «puntaje < 4 y > 3» no pierde lo que queda entre los signos). Con otro
   * formato se quitan las etiquetas HTML y se decodifican las entidades. En
   * ambos casos la plantilla imprime escapado.
   *
   * @param \Drupal\Core\Field\FieldItemInterface|null $item
   *   Primer valor del campo, o NULL si está vacío.
   *
   * @return string
   *   El texto, o una cadena vacía.
   */
  protected function textoDeCampo(?FieldItemInterface $item): string {
    if (!$item) {
      return '';
    }
    $texto = (string) $item->get('value')->getValue();
    if ((string) $item->get('format')->getValue() === 'plain_text') {
      return trim($texto);
    }
    return trim(Html::decodeEntities(strip_tags($texto)));
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
