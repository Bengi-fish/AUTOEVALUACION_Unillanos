<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\unillanos_autoeval\Traits\FormatoTextoTrait;

/**
 * Arma los datos de la portada a partir de los procesos publicados.
 *
 * Escala de uno a muchos programas. Solo lee valores guardados: no calcula
 * grados, destinos ni avances.
 */
class DatosPortada {

  use StringTranslationTrait;
  use FormatoTextoTrait;

  /**
   * Programas que muestra la portada como máximo.
   */
  const MAX_PROGRAMAS_PORTADA = 12;

  /**
   * Ruta del listado de programas.
   */
  const RUTA_PROGRAMAS = 'unillanos_autoeval.programas';

  /**
   * Ruta de los documentos.
   */
  const RUTA_DOCUMENTOS = 'unillanos_autoeval.documentos';

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ListadoProgramas $listado,
    protected FactoresPrograma $factores,
    protected UrlGeneratorInterface $generadorUrl,
    protected AccessManagerInterface $accesos,
    protected AccountInterface $usuario,
  ) {}

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
  public function armar(CacheableMetadata $cache): array {
    $cache->addCacheTags(array_merge(ProgramasYProcesos::ETIQUETAS_AUTOEVALUACION, ['node_list:programa', 'routes']));
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
      'ver_todos' => $total >= 1 ? $this->enlaceDeRuta(self::RUTA_PROGRAMAS) : '',
      'factores' => [],
      'hay_grado' => FALSE,
      'documentos' => $this->enlaceDeRuta(self::RUTA_DOCUMENTOS),
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
      $factores = $modelo ? $this->factores->delModelo((int) $modelo) : [];
      if (!$factores) {
        continue;
      }
      // Con un único programa, la valoración guardada de cada factor.
      $valores = [];
      if ($total === 1) {
        foreach ($this->factores->delPrograma($elegido['programa'], new CacheableMetadata())['tarjetas'] ?? [] as $tarjeta) {
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
   * Devuelve la URL de una ruta del sitio si existe y el visitante puede verla.
   *
   * @param string $ruta
   *   Nombre de la ruta.
   *
   * @return string
   *   La URL (por ejemplo `/programas`), o vacío si no se puede entrar.
   */
  protected function enlaceDeRuta(string $ruta): string {
    $acceso = $this->accesos->checkNamedRoute($ruta, [], $this->usuario, TRUE);
    if (!$acceso->isAllowed()) {
      return '';
    }
    return $this->generadorUrl->generateFromRoute($ruta);
  }

}
