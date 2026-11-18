<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Redirecciones permanentes entre rutas propias del sitio.
 *
 * La ruta destino sale de los valores por defecto de la ruta (`_ruta`, y
 * opcionalmente `_ancla`), nunca de la petición: no es una redirección
 * abierta.
 */
final class RedireccionController extends ControllerBase {

  /**
   * Redirige (301) a la ruta indicada en `_ruta`.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   La ruta actual.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   La respuesta de redirección.
   */
  public function redirigir(RouteMatchInterface $route_match): RedirectResponse {
    $ruta = (string) $route_match->getRouteObject()?->getDefault('_ruta');
    $ancla = (string) $route_match->getRouteObject()?->getDefault('_ancla');
    $url = Url::fromRoute($ruta, [], $ancla !== '' ? ['fragment' => $ancla] : []);
    return new RedirectResponse($url->toString(), 301);
  }

}
