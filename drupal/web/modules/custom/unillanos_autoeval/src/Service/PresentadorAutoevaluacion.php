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
   *   Con: proceso (título), proceso_id, resultado (texto guardado), tarjetas
   *   (una por factor con valoración, para `factor-card`), pestanas (una por
   *   factor, y una final «Sin factor asignado» si hace falta, para
   *   `factor-tabs`), claves (clave de pestaña de cada factor) y
   *   factor_de_hallazgo (factor de cada hallazgo). Vacío si el programa no
   *   tiene un proceso con factores.
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
   * Arma el plan de mejoramiento de un programa y lo enlaza con sus factores.
   *
   * Sirve para cualquier programa y modelo. Con factores, usa el plan del
   * mismo proceso que ellos; sin factores, el plan más reciente que tenga
   * metas (sin filtro por factor). Cada meta es una fila: la situación sale de
   * los hallazgos enlazados, la acción de la meta, y el avance es el del
   * periodo más reciente tal como lo reportó el programa (sin porcentaje
   * global y sin usar el de un periodo anterior). Una meta con hallazgos de
   * varios factores aparece en todos; sin ninguno, en «Sin factor asignado».
   * Lo vacío se omite. Los factores recibidos vuelven con `plan_count`,
   * `plan_note` y `plan_url` para el enlace «Ver en el plan de mejoramiento».
   *
   * @param \Drupal\node\NodeInterface $programa
   *   Nodo `programa`.
   * @param array<string, mixed> $factores
   *   Resultado de ::factoresDelPrograma() (puede estar vacío).
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: factores (los recibidos, con el enlace al plan) y plan (lead,
   *   opciones del filtro, filas, aviso). `plan` queda vacío si no hay plan
   *   con metas.
   */
  public function planDelPrograma(NodeInterface $programa, array $factores, CacheableMetadata $cache): array {
    $cache->addCacheTags(self::ETIQUETAS_AUTOEVALUACION);
    $procesos = $this->procesosDelPrograma($programa);
    if (!empty($factores['proceso_id'])) {
      $procesos = array_intersect_key($procesos, [$factores['proceso_id'] => TRUE]);
    }
    foreach ($procesos as $proceso) {
      $plan = $this->planDeProceso($proceso, $factores, $cache);
      if ($plan) {
        foreach ($factores['pestanas'] ?? [] as $indice => $pestana) {
          $cuenta = $plan['cuenta'][$pestana['key']] ?? 0;
          if ($cuenta > 0) {
            $factores['pestanas'][$indice]['plan_count'] = $cuenta;
            $factores['pestanas'][$indice]['plan_note'] = (string) $this->t('formuladas para este factor');
            $factores['pestanas'][$indice]['plan_url'] = '#plan';
          }
        }
        unset($plan['cuenta']);
        return ['factores' => $factores, 'plan' => $plan];
      }
    }
    return ['factores' => $factores, 'plan' => []];
  }

  /**
   * Arma las filas del plan de mejoramiento de un proceso.
   *
   * @param \Drupal\node\NodeInterface $proceso
   *   Nodo `proceso`.
   * @param array<string, mixed> $factores
   *   Resultado de ::factoresDelPrograma().
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché.
   *
   * @return array<string, mixed>
   *   Ver ::planDelPrograma(), más `cuenta` (metas por clave de factor).
   *   Vacío si el proceso no tiene un plan publicado con metas.
   */
  protected function planDeProceso(NodeInterface $proceso, array $factores, CacheableMetadata $cache): array {
    $almacen = $this->entityTypeManager->getStorage('node');
    $terminos = $this->entityTypeManager->getStorage('taxonomy_term');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'plan_mejoramiento')
      ->condition('status', 1)
      ->condition('field_proceso', $proceso->id())
      ->sort('nid', 'DESC')
      ->execute();
    foreach ($almacen->loadMultiple($ids) as $plan) {
      $ids_meta = $almacen->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'meta')
        ->condition('status', 1)
        ->condition('field_plan', $plan->id())
        ->sort('field_numero')
        ->sort('nid')
        ->execute();
      $metas = $almacen->loadMultiple($ids_meta);
      if (!$metas) {
        continue;
      }
      $cache->addCacheableDependency($plan);

      // Hallazgos, responsables y seguimientos de todas las metas, de una vez.
      $ids_hallazgo = [];
      $ids_responsable = [];
      foreach ($metas as $meta) {
        $cache->addCacheableDependency($meta);
        $ids_hallazgo = array_merge($ids_hallazgo, array_column($meta->get('field_hallazgos')->getValue(), 'target_id'));
        $ids_responsable = array_merge($ids_responsable, array_column($meta->get('field_responsables')->getValue(), 'target_id'));
      }
      $hallazgos = $ids_hallazgo ? $almacen->loadMultiple(array_unique($ids_hallazgo)) : [];
      $responsables = $ids_responsable ? $terminos->loadMultiple(array_unique($ids_responsable)) : [];
      $seguimientos = [];
      $ids_seguimiento = $almacen->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'seguimiento')
        ->condition('status', 1)
        ->condition('field_meta', array_keys($metas), 'IN')
        ->execute();
      foreach ($almacen->loadMultiple($ids_seguimiento) as $seguimiento) {
        $seguimientos[(int) $seguimiento->get('field_meta')->target_id][] = $seguimiento;
      }
      $sin_verificar = FALSE;

      $etiquetas = [];
      foreach ($factores['pestanas'] ?? [] as $pestana) {
        $etiquetas[$pestana['key']] = $pestana['number'] !== '' ? 'F' . $pestana['number'] : '';
      }
      $filas = [];
      $cuenta = [];
      foreach ($metas as $meta) {
        // Factores de la meta, por sus hallazgos publicados.
        $textos_situacion = [];
        $claves = [];
        foreach ($meta->get('field_hallazgos')->getValue() as $valor) {
          $hallazgo = $hallazgos[$valor['target_id']] ?? NULL;
          // Solo hallazgos publicados del mismo proceso que el plan.
          if (!$hallazgo || !$hallazgo->isPublished()
            || (int) $hallazgo->get('field_proceso')->target_id !== (int) $proceso->id()) {
            continue;
          }
          $texto = $this->textoDeCampo($hallazgo->get('body')->first());
          if ($texto !== '') {
            $textos_situacion[] = $texto;
          }
          $id_factor = $factores['factor_de_hallazgo'][(int) $hallazgo->id()] ?? 0;
          if ($id_factor && isset($factores['claves'][$id_factor])) {
            $claves[$factores['claves'][$id_factor]] = TRUE;
          }
        }
        $claves = array_keys($claves);
        if (!$claves && $factores) {
          $claves = ['sin-factor'];
        }
        foreach ($claves as $clave) {
          $cuenta[$clave] = ($cuenta[$clave] ?? 0) + 1;
        }

        $nombres_responsables = [];
        foreach ($meta->get('field_responsables')->getValue() as $valor) {
          if (isset($responsables[$valor['target_id']])) {
            $nombres_responsables[] = (string) $responsables[$valor['target_id']]->label();
          }
        }

        $lista = $seguimientos[(int) $meta->id()] ?? [];
        usort($lista, fn($a, $b) => strnatcmp((string) $b->get('field_periodo')->value, (string) $a->get('field_periodo')->value));
        $periodos = [];
        foreach ($lista as $seguimiento) {
          $periodos[] = $this->lineaDeSeguimiento($seguimiento);
          if ((string) $seguimiento->get('field_estado')->value !== 'verificado') {
            $sin_verificar = TRUE;
          }
        }
        // Siempre el periodo más reciente: si no trae porcentaje no se usa el
        // de uno anterior.
        $ultimo = $lista ? $this->resumenDeSeguimiento(reset($lista), TRUE) : '';

        $codigo = trim((string) $meta->get('field_codigo')->value);
        if ($codigo === '' && !$meta->get('field_numero')->isEmpty()) {
          $codigo = (string) $meta->get('field_numero')->value;
        }
        $filas[] = [
          'keys' => $claves,
          'tag' => implode(', ', array_filter(array_map(fn($clave) => $etiquetas[$clave] ?? '', $claves))),
          'cells' => [
            count($textos_situacion) > 1
              ? ['type' => 'list', 'items' => $textos_situacion]
              : ['type' => 'text', 'value' => $textos_situacion[0] ?? ''],
            [
              'type' => 'text',
              'value' => $this->textoDeCampo($meta->get('body')->first()),
              'eyebrow' => $codigo !== '' ? $this->plano($this->t('Meta @codigo', ['@codigo' => $codigo])) : '',
              'details' => [
                'summary' => (string) $this->t('Ver actividades, indicadores y avance'),
                // Contexto para lectores de pantalla: hay un resumen por meta.
                'context' => $codigo !== '' ? $this->plano($this->t('Meta @codigo', ['@codigo' => $codigo])) : '',
                'groups' => [
                  [
                    'label' => (string) $this->t('Actividades'),
                    'text' => $this->textoDeCampo($meta->get('field_actividades')->first()),
                  ],
                  [
                    'label' => (string) $this->t('Recursos'),
                    'text' => $this->textoDeCampo($meta->get('field_recursos')->first()),
                  ],
                  [
                    'label' => (string) $this->t('Proyecto institucional'),
                    'text' => (string) $meta->get('field_proyecto')->entity?->label(),
                  ],
                  [
                    'label' => (string) $this->t('Tipo de meta'),
                    'text' => $this->etiquetaLista($meta->get('field_tipo_meta')),
                  ],
                  [
                    'label' => (string) $this->t('Indicadores'),
                    'items' => $this->indicadoresDeMeta($meta),
                  ],
                  [
                    'label' => (string) $this->t('Avance reportado por periodo'),
                    'items' => $periodos,
                  ],
                ],
              ],
            ],
            $nombres_responsables
              ? ['type' => 'list', 'items' => $nombres_responsables]
              : ['type' => 'text', 'value' => ''],
            ['type' => 'text', 'value' => $ultimo],
          ],
        ];
      }

      // Opciones del filtro de factor, en el orden de las pestañas.
      $opciones = [];
      foreach ($factores['pestanas'] ?? [] as $pestana) {
        if (!empty($cuenta[$pestana['key']])) {
          $opciones[] = [
            'value' => $pestana['key'],
            'label' => $pestana['key'] === 'sin-factor'
              ? $pestana['name']
              : trim(($etiquetas[$pestana['key']] !== '' ? $etiquetas[$pestana['key']] . ' · ' : '') . $pestana['name']),
          ];
        }
      }
      if (!empty($cuenta['sin-factor']) && !in_array('sin-factor', array_column($opciones, 'value'), TRUE)) {
        $opciones[] = ['value' => 'sin-factor', 'label' => (string) $this->t('Sin factor asignado')];
      }

      $partes = [];
      $codigo_formato = trim((string) $plan->get('field_codigo_formato')->value);
      if ($codigo_formato !== '') {
        $partes[] = $this->plano($this->t('Formato @codigo', ['@codigo' => $codigo_formato]));
      }
      $version = trim((string) $plan->get('field_version_formato')->value);
      if ($version !== '') {
        $partes[] = $this->plano($this->t('versión @version', ['@version' => $version]));
      }
      $periodo = trim((string) $plan->get('field_periodo')->value);
      if ($periodo !== '') {
        $partes[] = $this->plano($this->t('periodo @periodo', ['@periodo' => $periodo]));
      }
      $suscrito = $this->fechaMesAnio($plan->get('field_fecha_suscripcion')->value);
      if ($suscrito !== '') {
        $partes[] = $this->plano($this->t('suscrito en @fecha', ['@fecha' => $suscrito]));
      }
      return [
        'lead' => implode(' · ', $partes),
        'opciones' => $opciones,
        'filas' => $filas,
        'aviso' => $sin_verificar,
        'cuenta' => $cuenta,
      ];
    }
    return [];
  }

  /**
   * Resume el avance de un seguimiento: «2025-2 · 80 % · Reportado».
   *
   * @param \Drupal\node\NodeInterface $seguimiento
   *   Nodo `seguimiento`.
   * @param bool $estado_aparte
   *   TRUE para poner el estado en otra línea.
   *
   * @return string
   *   Periodo, porcentaje (solo si lo tiene) y estado guardado, separados por
   *   «·»; las partes vacías se omiten.
   */
  protected function resumenDeSeguimiento(NodeInterface $seguimiento, bool $estado_aparte = FALSE): string {
    $porcentaje = '';
    $avance = $seguimiento->get('field_avance')->value;
    if ($avance !== NULL && is_numeric($avance) && (float) $avance >= 0 && (float) $avance <= 1) {
      // El avance se guarda de 0 a 1; se pasa a porcentaje solo al mostrarlo.
      // Un valor fuera de ese rango no se muestra como porcentaje.
      $porcentaje = $this->numero((float) $avance * 100, 0) . ' %';
    }
    $estado = $this->etiquetaLista($seguimiento->get('field_estado'));
    $periodo = implode(' · ', array_filter([
      trim((string) $seguimiento->get('field_periodo')->value),
      $porcentaje,
    ], fn($parte) => $parte !== ''));
    // En la celda de la tabla el estado va en otra línea (columna angosta); en
    // el detalle, todo en una línea.
    return implode($estado_aparte ? "\n" : ' · ', array_filter([$periodo, $estado], fn($parte) => $parte !== ''));
  }

  /**
   * Escribe un seguimiento completo para el detalle de la meta.
   *
   * @param \Drupal\node\NodeInterface $seguimiento
   *   Nodo `seguimiento`.
   *
   * @return string
   *   El resumen más lo que reportó el programa (actividades) y sus medios de
   *   verificación, si existen.
   */
  protected function lineaDeSeguimiento(NodeInterface $seguimiento): string {
    $linea = $this->resumenDeSeguimiento($seguimiento);
    $actividades = $this->textoDeCampo($seguimiento->get('field_actividades')->first());
    if ($actividades !== '') {
      $linea .= ' — ' . $actividades;
    }
    $medios = $this->textoDeCampo($seguimiento->get('field_medios_verificacion')->first());
    if ($medios !== '') {
      $linea .= ' ' . $this->plano($this->t('(Medios de verificación: @medios)', ['@medios' => $medios]));
    }
    return $linea;
  }

  /**
   * Escribe los indicadores de una meta con su programación anual.
   *
   * @param \Drupal\node\NodeInterface $meta
   *   Nodo `meta`.
   *
   * @return string[]
   *   Un texto por indicador: nombre, línea base y programación por año (solo
   *   los años con valor). Los números van como están guardados, sin
   *   interpretar unidades ni convertir a porcentaje.
   */
  protected function indicadoresDeMeta(NodeInterface $meta): array {
    $textos = [];
    foreach ($meta->get('field_indicadores')->referencedEntities() as $indicador) {
      $partes = [trim((string) $indicador->get('field_nombre')->value)];
      $unidad = trim((string) $indicador->get('field_unidad')->value);
      if ($unidad !== '') {
        $partes[] = $this->plano($this->t('Unidad: @unidad', ['@unidad' => $unidad]));
      }
      $base = trim((string) $indicador->get('field_linea_base')->value);
      if ($base !== '') {
        $partes[] = $this->plano($this->t('Línea base: @base', ['@base' => $base]));
      }
      $filas_anio = $indicador->get('field_programacion')->referencedEntities();
      usort($filas_anio, fn($x, $y) => (int) $x->get('field_anio')->value <=> (int) $y->get('field_anio')->value);
      $anios = [];
      foreach ($filas_anio as $anio) {
        $programado = $this->decimalLimpio($anio->get('field_programado')->value);
        $logrado = $this->decimalLimpio($anio->get('field_logrado')->value);
        if ($programado === '' && $logrado === '') {
          continue;
        }
        $anios[] = $this->plano($programado !== ''
          ? ($logrado !== ''
            ? $this->t('@anio: @programado (logrado @logrado)', [
              '@anio' => $anio->get('field_anio')->value,
              '@programado' => $programado,
              '@logrado' => $logrado,
            ])
            : $this->t('@anio: @programado', ['@anio' => $anio->get('field_anio')->value, '@programado' => $programado]))
          : $this->t('@anio: logrado @logrado', ['@anio' => $anio->get('field_anio')->value, '@logrado' => $logrado]));
      }
      if ($anios) {
        $partes[] = $this->plano($this->t('Programación anual · @anios', ['@anios' => implode(' · ', $anios)]));
      }
      $texto = implode(' · ', array_filter($partes, fn($parte) => (string) $parte !== ''));
      if ($texto !== '') {
        $textos[] = $texto;
      }
    }
    return $textos;
  }

  /**
   * Da formato a un decimal guardado sin ceros finales y con coma decimal.
   *
   * @param string|null $valor
   *   Valor guardado (p. ej. «165.0000» o «0.6000»).
   *
   * @return string
   *   «165» o «0,6»; vacío si no es numérico.
   */
  protected function decimalLimpio(?string $valor): string {
    if ($valor === NULL || $valor === '' || !is_numeric($valor)) {
      return '';
    }
    $limpio = rtrim(rtrim(number_format((float) $valor, 4, '.', ''), '0'), '.');
    return str_replace('.', ',', $limpio);
  }

  /**
   * Devuelve una cadena traducida como texto plano, sin escapar.
   *
   * Los marcadores `@` de t() escapan el HTML, y la plantilla vuelve a
   * escapar al imprimir (un «&» se vería «&amp;amp;»). Aquí se decodifican
   * para que el escape ocurra una sola vez, en la plantilla.
   *
   * @param \Stringable|string $texto
   *   El resultado de $this->t().
   *
   * @return string
   *   El texto plano.
   */
  protected function plano(\Stringable|string $texto): string {
    return Html::decodeEntities((string) $texto);
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
   * Un campo `plain_text` (o de texto corto, sin formato) ya es texto: solo
   * se recortan los espacios (así un «puntaje < 4 y > 3» no pierde lo que
   * queda entre los signos). Con otro
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
    // Un campo de texto corto no tiene formato: es texto plano.
    $formato = isset($item->getProperties()['format']) ? (string) $item->get('format')->getValue() : 'plain_text';
    if ($formato === 'plain_text') {
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
