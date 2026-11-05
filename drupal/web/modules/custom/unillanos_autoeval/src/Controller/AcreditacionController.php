<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\unillanos_autoeval\Service\ListadoProgramas;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Panel de Acreditación (/admin/acreditacion).
 *
 * Lista los programas publicados con sus indicadores y, por cada uno, los
 * accesos a las pantallas de edición y de creación que ya existen en Drupal,
 * con el programa, el proceso o el plan ya elegidos cuando la relación lo
 * permite (ver UnillanosAutoevalHooks::PRECARGA). No guarda nada ni calcula
 * nada propio: usa ListadoProgramas y enlaza solo lo que se puede abrir.
 * La respuesta se guarda por usuario (nunca es pública).
 */
final class AcreditacionController extends ControllerBase {

  /**
   * Constructor.
   */
  public function __construct(
    protected ListadoProgramas $listado,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('unillanos_autoeval.listado_programas'));
  }

  /**
   * Arma el panel.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   La petición; solo se lee `q` (búsqueda por nombre).
   *
   * @return array<string, mixed>
   *   Render array del panel.
   */
  public function panel(Request $request): array {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user', 'url.query_args:q']);
    $cache->addCacheTags([
      'node_list:programa',
      'node_list:proceso',
      'node_list:plan_mejoramiento',
      'taxonomy_term_list:facultad',
      'taxonomy_term_list:sede',
    ]);

    // all(): `?q[]=x` no debe terminar en una petición mala; el servicio
    // descarta lo que no sea texto.
    $consulta = $request->query->all();
    $filtrado = $this->listado->listadoFiltrado(array_key_exists('q', $consulta) ? ['q' => $consulta['q']] : [], $cache);
    $procesos = $this->listado->procesosPorPrograma($cache);
    $planes = $this->planesPorProceso($procesos, $cache);

    $filas = [];
    foreach ($filtrado['programas'] as $programa) {
      if (!empty($programa['oculto'])) {
        continue;
      }
      $filas[] = $this->fila($programa, $procesos[$programa['id']]['procesos'] ?? [], $planes, $cache);
    }

    $construccion = [
      '#theme' => 'unillanos_acreditacion_panel',
      '#datos' => [
        'q' => $filtrado['valores']['q'] ?? '',
        'total' => count($filas),
        'total_general' => $filtrado['total'],
        'generales' => $this->accesosGenerales($cache),
        'programas' => $filas,
      ],
      '#attached' => ['library' => ['unillanos_autoeval/acreditacion']],
    ];
    $cache->applyTo($construccion);
    return $construccion;
  }

  /**
   * Arma los datos de un programa: indicadores y grupos de accesos.
   *
   * @param array<string, mixed> $programa
   *   Tarjeta de ListadoProgramas: id, nombre, facultad, nivel, sede...
   * @param \Drupal\node\NodeInterface[] $procesos
   *   Procesos publicados del programa, el más reciente primero.
   * @param array<int, int> $planes
   *   Plan de mejoramiento por ID de proceso.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de las URL.
   *
   * @return array<string, mixed>
   *   Fila del panel.
   */
  protected function fila(array $programa, array $procesos, array $planes, CacheableMetadata $cache): array {
    $id = (int) $programa['id'];
    $proceso = $procesos ? reset($procesos) : NULL;
    $proceso_id = $proceso ? (int) $proceso->id() : 0;
    $plan_id = $proceso_id ? ($planes[$proceso_id] ?? 0) : 0;
    $con_proceso = !empty($procesos);

    $indicadores = [];
    if ($con_proceso) {
      $indicadores[] = $this->t('Con proceso');
    }
    if ($plan_id) {
      $indicadores[] = $this->t('Con plan');
    }

    $grupos = [];
    $grupos[] = [
      'titulo' => $this->t('Ficha del programa'),
      'enlaces' => array_filter([
        $this->enlace($this->t('Editar la ficha'), 'entity.node.edit_form', ['node' => $id], [], $cache),
      ]),
    ];
    $enlaces_proceso = [];
    foreach ($procesos as $p) {
      $enlaces_proceso[] = $this->enlace($this->t('Editar el proceso «@titulo»', ['@titulo' => $p->label()]), 'entity.node.edit_form', ['node' => $p->id()], [], $cache);
    }
    $enlaces_proceso[] = $this->enlace($this->t('Crear un proceso'), 'node.add', ['node_type' => 'proceso'], ['programa' => $id], $cache);
    $grupos[] = ['titulo' => $this->t('Procesos'), 'enlaces' => array_filter($enlaces_proceso)];

    // Lo que depende del proceso solo se crea con el proceso ya elegido; sin
    // proceso queda el enlace a la lista de contenido del tipo.
    $grupos[] = $this->grupoDeTipo($this->t('Valoraciones'), $this->t('Crear una valoración'), 'valoracion', $proceso_id ? ['proceso' => $proceso_id] : NULL, $cache);
    $grupos[] = $this->grupoDeTipo($this->t('Hallazgos'), $this->t('Crear un hallazgo'), 'hallazgo', $proceso_id ? ['proceso' => $proceso_id] : NULL, $cache);

    $enlaces_plan = [];
    if ($plan_id) {
      $enlaces_plan[] = $this->enlace($this->t('Editar el plan de mejoramiento'), 'entity.node.edit_form', ['node' => $plan_id], [], $cache);
    }
    elseif ($proceso_id) {
      $enlaces_plan[] = $this->enlace($this->t('Crear el plan de mejoramiento'), 'node.add', ['node_type' => 'plan_mejoramiento'], ['proceso' => $proceso_id], $cache);
    }
    $enlaces_plan[] = $this->enlace($this->t('Ver la lista de planes'), 'system.admin_content', [], ['type' => 'plan_mejoramiento'], $cache);
    $grupos[] = ['titulo' => $this->t('Plan de mejoramiento'), 'enlaces' => array_filter($enlaces_plan)];

    $grupos[] = $this->grupoDeTipo($this->t('Metas'), $this->t('Crear una meta'), 'meta', $plan_id ? ['plan' => $plan_id] : NULL, $cache);
    // Un seguimiento pertenece a una meta, no al programa: no se precarga nada.
    $grupos[] = $this->grupoDeTipo($this->t('Seguimientos'), $this->t('Crear un seguimiento'), 'seguimiento', [], $cache);

    $enlaces_documentos = [];
    if ($proceso_id) {
      $enlaces_documentos[] = $this->enlace($this->t('Subir un documento'), 'entity.media.add_form', ['media_type' => 'documento'], ['proceso' => $proceso_id], $cache);
    }
    $enlaces_documentos[] = $this->enlace($this->t('Ver la lista de documentos'), 'entity.media.collection', [], ['type' => 'documento'], $cache);
    $grupos[] = ['titulo' => $this->t('Documentos'), 'enlaces' => array_filter($enlaces_documentos)];

    return [
      'id' => $id,
      'nombre' => $programa['nombre'],
      'facultad' => $programa['facultad'] ?? '',
      'nivel' => $programa['nivel'] ?? '',
      'sede' => $programa['sede'] ?? '',
      'indicadores' => $indicadores,
      'grupos' => array_values(array_filter($grupos, fn(array $g): bool => !empty($g['enlaces']))),
    ];
  }

  /**
   * Arma el grupo de accesos de un tipo de contenido.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $titulo
   *   Título del grupo.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $crear
   *   Texto del enlace de creación.
   * @param string $tipo
   *   Tipo de contenido.
   * @param array<string, int>|null $consulta
   *   Parámetros de precarga; NULL si no se puede crear con la relación ya
   *   elegida (entonces solo queda la lista del tipo); [] si el tipo no
   *   admite precarga y se crea sin relación.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de las URL.
   *
   * @return array<string, mixed>
   *   Grupo con titulo y enlaces.
   */
  protected function grupoDeTipo($titulo, $crear, string $tipo, ?array $consulta, CacheableMetadata $cache): array {
    $enlaces = [];
    if ($consulta !== NULL) {
      $enlaces[] = $this->enlace($crear, 'node.add', ['node_type' => $tipo], $consulta, $cache);
    }
    $enlaces[] = $this->enlace($this->t('Ver la lista'), 'system.admin_content', [], ['type' => $tipo], $cache);
    return ['titulo' => $titulo, 'enlaces' => array_filter($enlaces)];
  }

  /**
   * Accesos generales de la parte superior del panel.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de las URL.
   *
   * @return array<int, array{texto: mixed, url: string}>
   *   Enlaces que la persona puede abrir.
   */
  protected function accesosGenerales(CacheableMetadata $cache): array {
    return array_values(array_filter([
      $this->enlace($this->t('Todo el contenido'), 'system.admin_content', [], [], $cache),
      $this->enlace($this->t('Contenido sin publicar'), 'system.admin_content', [], ['status' => 2], $cache),
      $this->enlace($this->t('Documentos'), 'entity.media.collection', [], ['type' => 'documento'], $cache),
      $this->enlace($this->t('Cabecera y pie del micrositio'), 'unillanos_autoeval.micrositio', [], [], $cache),
    ]));
  }

  /**
   * Busca el plan de mejoramiento de cada proceso.
   *
   * @param array<int, array{programa: \Drupal\node\NodeInterface, procesos: \Drupal\node\NodeInterface[]}> $procesos
   *   Resultado de ListadoProgramas::procesosPorPrograma().
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de la consulta.
   *
   * @return array<int, int>
   *   ID del plan por ID de proceso.
   */
  protected function planesPorProceso(array $procesos, CacheableMetadata $cache): array {
    $ids_proceso = [];
    foreach ($procesos as $grupo) {
      foreach ($grupo['procesos'] as $proceso) {
        $ids_proceso[] = (int) $proceso->id();
      }
    }
    if (!$ids_proceso) {
      return [];
    }
    $almacen = $this->entityTypeManager()->getStorage('node');
    $ids = $almacen->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'plan_mejoramiento')
      ->condition('field_proceso', $ids_proceso, 'IN')
      ->sort('nid')
      ->execute();
    $planes = [];
    foreach ($almacen->loadMultiple($ids) as $plan) {
      $cache->addCacheableDependency($plan);
      $proceso = (int) $plan->get('field_proceso')->target_id;
      $planes += [$proceso => (int) $plan->id()];
    }
    return $planes;
  }

  /**
   * Arma un enlace solo si la persona puede abrir su destino.
   *
   * @param mixed $texto
   *   Texto del enlace (traducible).
   * @param string $ruta
   *   Nombre de la ruta.
   * @param array<string, mixed> $parametros
   *   Parámetros de la ruta.
   * @param array<string, mixed> $consulta
   *   Parámetros de la URL.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos del acceso y de la URL.
   *
   * @return array{texto: mixed, url: string}|null
   *   El enlace, o NULL si no hay acceso.
   */
  protected function enlace(mixed $texto, string $ruta, array $parametros, array $consulta, CacheableMetadata $cache): ?array {
    $url = Url::fromRoute($ruta, $parametros, ['query' => $consulta]);
    $acceso = $url->access(NULL, TRUE);
    $cache->addCacheableDependency($acceso);
    if (!$acceso->isAllowed()) {
      return NULL;
    }
    $generada = $url->toString(TRUE);
    $cache->addCacheableDependency($generada);
    return ['texto' => $texto, 'url' => $generada->getGeneratedUrl()];
  }

}
