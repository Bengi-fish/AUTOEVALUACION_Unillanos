<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\unillanos_autoeval\Service\ListadoProgramas;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Listado y buscador de programas (/programas).
 *
 * Entrega los datos; el tema los pinta con la plantilla del gancho
 * `unillanos_programas`. El formulario es un GET real: el servidor lee los
 * parámetros q, facultad, nivel y sede, los valida y marca como ocultos los
 * programas que no coinciden, sin recortar la lista.
 */
final class ProgramasController extends ControllerBase {

  /**
   * Segundos que se guarda una respuesta con texto de búsqueda.
   *
   * El texto libre puede generar muchas variantes de la misma página: se deja
   * que caduquen solas. Sin texto, o solo con filtros válidos, las
   * combinaciones están acotadas y la respuesta se guarda sin caducidad.
   */
  const MAX_AGE_CON_TEXTO = 3600;

  /**
   * Parámetros de la URL que cambian la página.
   */
  const PARAMETROS = ['q', 'facultad', 'nivel', 'sede'];

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
   * Construye la página.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   La petición: de ella solo se leen los cuatro parámetros de búsqueda.
   *
   * @return array
   *   Arreglo de render.
   */
  public function programas(Request $request): array {
    $cache = new CacheableMetadata();
    $crudo = array_intersect_key($request->query->all(), array_flip(self::PARAMETROS));
    $datos = $this->listado->listadoFiltrado($crudo, $cache);
    // Un contexto por parámetro: otros parámetros de la URL (utm_*, etc.) no
    // crean variantes de la página.
    foreach (self::PARAMETROS as $parametro) {
      $cache->addCacheContexts(['url.query_args:' . $parametro]);
    }
    if ($datos['consulta']['invalida']) {
      // Parámetros que no sirven (valor fuera de la lista, arreglos): se
      // ignoran y la respuesta no se guarda, para que el ruido de la URL no
      // deje entradas en la caché.
      $cache->setCacheMaxAge(0);
    }
    elseif ($datos['consulta']['con_texto']) {
      $cache->setCacheMaxAge(self::MAX_AGE_CON_TEXTO);
    }
    else {
      $cache->setCacheMaxAge(Cache::PERMANENT);
    }
    $construccion = [
      '#theme' => 'unillanos_programas',
      '#datos' => $datos,
    ];
    $cache->applyTo($construccion);
    return $construccion;
  }

}
