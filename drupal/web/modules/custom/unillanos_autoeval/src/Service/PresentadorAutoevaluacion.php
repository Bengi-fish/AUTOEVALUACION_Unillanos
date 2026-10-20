<?php

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\unillanos_autoeval\Traits\EtiquetasYOrdenTrait;
use Drupal\unillanos_autoeval\Traits\FormatoTextoTrait;

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
  use EtiquetasYOrdenTrait;
  use FormatoTextoTrait;

  /**
   * Programas que muestra la portada como máximo.
   */
  const MAX_PROGRAMAS_PORTADA = 12;

  /**
   * Ruta del listado de programas.
   */
  const RUTA_PROGRAMAS = '/programas';

  /**
   * Ruta de los documentos.
   */
  const RUTA_DOCUMENTOS = '/documentos';

  /**
   * Orden de las categorías de documento (alias de DocumentosPublicados).
   */
  const CATEGORIAS_DOCUMENTO = DocumentosPublicados::CATEGORIAS_DOCUMENTO;

  /**
   * Orden de las categorías dentro de los documentos de un programa.
   */
  const CATEGORIAS_PROGRAMA = DocumentosPublicados::CATEGORIAS_PROGRAMA;

  /**
   * Programas con documentos desde los que se ofrece una lista de salto.
   */
  const MIN_PROGRAMAS_SALTO = DocumentosPublicados::MIN_PROGRAMAS_SALTO;

  /**
   * Etiquetas de caché de la autoevaluación.
   *
   * Es un alias de la constante de ProgramasYProcesos.
   */
  const ETIQUETAS_AUTOEVALUACION = ProgramasYProcesos::ETIQUETAS_AUTOEVALUACION;

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PathValidatorInterface $pathValidator,
    protected ListadoProgramas $listado,
    protected DocumentosPublicados $documentos,
    protected FactoresPrograma $factores,
    protected ProgramasYProcesos $procesos,
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
    return $this->procesos->programaPorClave($clave);
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
    return $this->procesos->procesosDelPrograma($programa);
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
    return $this->procesos->procesoDelPrograma($programa);
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
    return $this->factores->delPrograma($programa, $cache);
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
            $factores['pestanas'][$indice]['plan_note'] = (string) $this->formatPlural($cuenta, 'formulada para este factor', 'formuladas para este factor');
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
  public function factoresDelModelo(int $modelo): array {
    return $this->factores->delModelo($modelo);
  }

  /**
   * Indica si una ruta existe (para enlazar solo páginas que existen).
   *
   * @param string $ruta
   *   Ruta interna, por ejemplo «/documentos».
   *
   * @return bool
   *   TRUE si la ruta existe y el visitante puede entrar.
   */
  protected function rutaExiste(string $ruta): bool {
    return (bool) $this->pathValidator->isValid($ruta);
  }

  /**
   * Arma los datos de la portada a partir de los procesos publicados.
   *
   * Escala de uno a muchos programas: cada programa aparece una vez, con su
   * proceso más reciente; las cifras son totales de lo publicado (con un solo
   * programa, las de ese proceso) y las que valen cero se omiten; los chips de
   * factores llevan la valoración guardada solo si hay un único programa (con
   * varios sería un promedio, es decir, un cálculo). Solo se leen valores
   * guardados.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: eyebrow, cifras (value y label), unico (TRUE con un solo
   *   programa), programas (title, text, url y cta; hasta el tope),
   *   total_programas, ver_todos (URL o vacío), factores (n, name y, con un
   *   solo programa, score, grade y decimals), hay_grado y documentos (URL o
   *   vacío). Lo que no existe queda vacío.
   */
  public function datosPortada(CacheableMetadata $cache): array {
    $cache->addCacheTags(array_merge(self::ETIQUETAS_AUTOEVALUACION, ['node_list:programa', 'routes']));
    $cache->addCacheContexts(['user.permissions']);
    // Qué programas aparecen lo decide ListadoProgramas (la misma regla que el
    // listado de /programas): programa publicado y visible con al menos un
    // proceso publicado y visible. Los datos solo eligen cuál proceso se usa.
    $agrupados = $this->listado->procesosPorPrograma($cache);
    $ids_proceso_visibles = [];
    foreach ($agrupados as $grupo) {
      foreach ($grupo['procesos'] as $proceso) {
        $ids_proceso_visibles[] = (int) $proceso->id();
      }
    }
    // Un proceso por programa visible: el más reciente que tenga datos (si
    // ninguno los tiene, el más reciente). Así cifras, chips y tarjeta hablan
    // del mismo proceso que la página del programa.
    $con_datos = $this->procesosConDatos($ids_proceso_visibles);
    $elegidos = [];
    foreach ($agrupados as $id_programa => $grupo) {
      $elegido = reset($grupo['procesos']);
      foreach ($grupo['procesos'] as $proceso) {
        if (isset($con_datos[(int) $proceso->id()])) {
          $elegido = $proceso;
          break;
        }
      }
      $elegidos[$id_programa] = ['proceso' => $elegido, 'programa' => $grupo['programa']];
    }

    $total = count($elegidos);
    $tarjetas = [];
    foreach (array_slice($elegidos, 0, self::MAX_PROGRAMAS_PORTADA) as $elegido) {
      $tarjetas[] = $this->tarjetaDePrograma($elegido['programa'], $elegido['proceso'], $cache);
    }

    $datos = [
      'eyebrow' => '',
      'cifras' => [],
      'unico' => $total === 1,
      'programas' => $tarjetas,
      'total_programas' => $total,
      'ver_todos' => $total >= 1 && $this->rutaExiste(self::RUTA_PROGRAMAS) ? self::RUTA_PROGRAMAS : '',
      'factores' => [],
      'hay_grado' => FALSE,
      'documentos' => $this->rutaExiste(self::RUTA_DOCUMENTOS) ? self::RUTA_DOCUMENTOS : '',
    ];
    if (!$elegidos) {
      return $datos;
    }
    $primero = reset($elegidos);
    $periodo = trim((string) $primero['proceso']->get('field_periodo_evaluado')->value);
    $datos['eyebrow'] = $total === 1
      ? $this->plano($periodo !== '' ? $this->t('Proceso de autoevaluación @periodo', ['@periodo' => $periodo]) : $this->t('Proceso de autoevaluación'))
      : (string) $this->t('Programas con autoevaluación publicada');
    $ids_proceso = array_values(array_map(fn($elegido) => (int) $elegido['proceso']->id(), $elegidos));
    $datos['cifras'] = $this->cifrasDePortada($ids_proceso, $total);

    // Chips: factores del modelo del proceso más reciente que tenga factores.
    foreach ($elegidos as $elegido) {
      $modelo = $elegido['proceso']->get('field_lineamiento')->target_id;
      $factores = $modelo ? $this->factoresDelModelo((int) $modelo) : [];
      if (!$factores) {
        continue;
      }
      // Con un único programa, la valoración guardada de cada factor.
      $valores = [];
      if ($total === 1) {
        foreach ($this->factoresDelPrograma($elegido['programa'], new CacheableMetadata())['tarjetas'] ?? [] as $tarjeta) {
          $valores[(int) $tarjeta['number']] = $tarjeta;
        }
      }
      $posicion = 0;
      foreach ($factores as $factor) {
        $posicion++;
        $numero = $factor->get('field_numero')->isEmpty() ? $posicion : (int) $factor->get('field_numero')->value;
        $chip = ['n' => $numero, 'name' => (string) $factor->label()];
        if (isset($valores[$numero]['score'])) {
          $chip['score'] = $valores[$numero]['score'];
          $chip['decimals'] = 2;
          if (!empty($valores[$numero]['grade'])) {
            $chip['grade'] = $valores[$numero]['grade'];
            $datos['hay_grado'] = TRUE;
          }
        }
        $datos['factores'][] = $chip;
      }
      break;
    }
    return $datos;
  }

  /**
   * Arma la tarjeta (o fila destacada) de un programa de la portada.
   *
   * @param \Drupal\node\NodeInterface $programa
   *   Nodo `programa` visible.
   * @param \Drupal\node\NodeInterface $proceso
   *   Su proceso publicado más reciente.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché (nodos, términos y URL).
   *
   * @return array<string, mixed>
   *   Con title, text (facultad y sede, periodo y resultado guardado, en una
   *   frase), lines (las mismas partes por separado), url y cta.
   */
  protected function tarjetaDePrograma(NodeInterface $programa, NodeInterface $proceso, CacheableMetadata $cache): array {
    $cache->addCacheableDependency($programa);
    $cache->addCacheableDependency($proceso);
    $facultad = $programa->get('field_facultad')->entity;
    $sede = $programa->get('field_sede')->entity;
    foreach ([$facultad, $sede] as $termino) {
      if ($termino) {
        $cache->addCacheableDependency($termino);
      }
    }
    $periodo = trim((string) $proceso->get('field_periodo_evaluado')->value);
    $resultado = trim((string) $proceso->get('field_resultado')->value);
    $partes = array_filter([
      implode(' · ', array_filter([(string) $facultad?->label(), (string) $sede?->label()])),
      $periodo !== '' ? $this->plano($this->t('Periodo evaluado: @periodo', ['@periodo' => $periodo])) : '',
      $resultado !== '' ? $this->plano($this->t('Resultado: @resultado', ['@resultado' => $resultado])) : '',
    ], fn($parte) => $parte !== '');
    $url = $programa->toUrl()->toString(TRUE);
    $cache->addCacheableDependency($url);
    return [
      'title' => (string) $programa->label(),
      'text' => implode(' · ', $partes),
      'lines' => array_values($partes),
      'url' => $url->getGeneratedUrl(),
      'cta' => (string) $this->t('Ver el programa'),
    ];
  }

  /**
   * Indica cuáles procesos tienen valoraciones o hallazgos publicados.
   *
   * @param int[] $ids_proceso
   *   IDs de procesos.
   *
   * @return array<int, true>
   *   Los IDs de proceso con datos, como clave.
   */
  protected function procesosConDatos(array $ids_proceso): array {
    $con_datos = [];
    if (!$ids_proceso) {
      return $con_datos;
    }
    $almacen = $this->entityTypeManager->getStorage('node');
    foreach (['valoracion', 'hallazgo'] as $tipo) {
      $filas = $almacen->getAggregateQuery()
        ->accessCheck(TRUE)
        ->condition('type', $tipo)
        ->condition('status', 1)
        ->condition('field_proceso', $ids_proceso, 'IN')
        ->aggregate('nid', 'COUNT')
        ->groupBy('field_proceso')
        ->execute();
      // Una consulta agregada devuelve filas con el alias de cada columna.
      /** @var array<int, array<string, mixed>> $filas */
      foreach ($filas as $fila) {
        if (isset($fila['field_proceso_target_id'])) {
          $con_datos[(int) $fila['field_proceso_target_id']] = TRUE;
        }
      }
    }
    return $con_datos;
  }

  /**
   * Cuenta lo publicado de los procesos mostrados y arma las cifras.
   *
   * Con un solo programa la primera cifra son los factores evaluados; con dos
   * o más, los programas con autoevaluación. Las otras tres son totales. Una
   * cifra en cero se omite.
   *
   * @param int[] $ids_proceso
   *   IDs de los procesos mostrados.
   * @param int $programas
   *   Número de programas mostrados.
   *
   * @return array<int, array<string, string>>
   *   Pares value y label.
   */
  protected function cifrasDePortada(array $ids_proceso, int $programas): array {
    if (!$ids_proceso) {
      return [];
    }
    $almacen = $this->entityTypeManager->getStorage('node');
    $contar = function (string $tipo, array $condiciones = []) use ($almacen, $ids_proceso): int {
      $consulta = $almacen->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $tipo)
        ->condition('status', 1);
      if ($tipo === 'meta') {
        // Solo metas de planes publicados (como la página del programa).
        $consulta->condition('field_plan.entity:node.field_proceso', $ids_proceso, 'IN');
        $consulta->condition('field_plan.entity:node.status', 1);
      }
      else {
        $consulta->condition('field_proceso', $ids_proceso, 'IN');
      }
      foreach ($condiciones as $campo => $valor) {
        $consulta->condition($campo, $valor);
      }
      return (int) $consulta->count()->execute();
    };
    $es_factor = ['field_elemento.entity:taxonomy_term.field_tipo_elemento' => 'factor'];
    // El rótulo concuerda con la cifra: «1 fortaleza identificada», «2
    // fortalezas identificadas».
    $fortalezas = $contar('hallazgo', ['field_tipo_hallazgo' => 'fortaleza']);
    $aspectos = $contar('hallazgo', ['field_tipo_hallazgo' => 'aspecto_por_mejorar']);
    $acciones = $contar('meta');
    $rotulo = fn(int $cantidad, string $singular, string $plural): string
      => (string) $this->formatPlural($cantidad, $singular, $plural);
    if ($programas === 1) {
      $factores = $contar('valoracion', $es_factor);
      $primera = [$rotulo($factores, 'Factor evaluado', 'Factores evaluados'), $factores];
    }
    else {
      $primera = [
        $rotulo($programas, 'Programa con autoevaluación', 'Programas con autoevaluación'),
        $programas,
      ];
    }
    $cifras = [
      $primera,
      [$rotulo($fortalezas, 'Fortaleza identificada', 'Fortalezas identificadas'), $fortalezas],
      [$rotulo($aspectos, 'Aspecto por mejorar', 'Aspectos por mejorar'), $aspectos],
      [$rotulo($acciones, 'Acción de mejora formulada', 'Acciones de mejora formuladas'), $acciones],
    ];
    $resultado = [];
    foreach ($cifras as [$etiqueta, $valor]) {
      if ($valor > 0) {
        $resultado[] = ['value' => number_format($valor, 0, ',', '.'), 'label' => $etiqueta];
      }
    }
    return $resultado;
  }

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
    return $this->documentos->tamano($bytes);
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
  public function documentosPublicados(CacheableMetadata $cache): array {
    return $this->documentos->publicados($cache);
  }

  /**
   * Arma los documentos de un programa y sus enlaces destacados.
   *
   * @param \Drupal\node\NodeInterface $programa
   *   Nodo `programa`.
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
  public function documentosDelPrograma(NodeInterface $programa, CacheableMetadata $cache): array {
    // Los procesos del programa, el más reciente primero: el servicio de
    // documentos no depende del presentador.
    return $this->documentos->delPrograma($programa, array_keys($this->procesosDelPrograma($programa)), $cache);
  }

}
