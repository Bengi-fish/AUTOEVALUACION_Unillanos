<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\unillanos_autoeval\Traits\EtiquetasYOrdenTrait;
use Drupal\unillanos_autoeval\Traits\FormatoTextoTrait;

/**
 * Arma el plan de mejoramiento (FO-GCL-20) de un programa y su seguimiento.
 *
 * Sirve para cualquier programa y modelo. Con factores, usa el plan del
 * proceso de los factores mostrados y enlaza cada meta con su factor. Solo
 * lee valores guardados: no calcula avances ni destinos.
 */
class PlanMejoramientoPrograma {

  use StringTranslationTrait;
  use EtiquetasYOrdenTrait;
  use FormatoTextoTrait;

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ProgramasYProcesos $procesos,
  ) {}

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
  public function delPrograma(NodeInterface $programa, array $factores, CacheableMetadata $cache): array {
    $cache->addCacheTags(ProgramasYProcesos::ETIQUETAS_AUTOEVALUACION);
    $procesos = $this->procesos->procesosDelPrograma($programa);
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

}
