<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Menu;

use Drupal\Core\Menu\MenuActiveTrail;
use Drupal\node\NodeInterface;

/**
 * Rastro activo del menú que incluye «Programas» en la página de un programa.
 *
 * La página de un programa (`entity.node.canonical`) no pertenece a ningún
 * enlace del menú, así que el núcleo no marca nada. Este servicio sustituye al
 * de siempre (`menu.active_trail`, ver UnillanosAutoevalServiceProvider) y
 * añade al rastro del menú principal el enlace «Programas». Como el contexto
 * de caché `route.menu_active_trails` sale de este mismo rastro, el menú queda
 * correctamente guardado sin contextos adicionales.
 */
class RastroActivoProgramas extends MenuActiveTrail {

  /**
   * Identificador del enlace «Programas» del menú principal.
   */
  const ID_ENLACE_PROGRAMAS = 'unillanos_autoeval.menu.programas';

  /**
   * Menú en el que se añade el enlace.
   */
  const MENU = 'main';

  /**
   * {@inheritdoc}
   */
  protected function doGetActiveTrailIds($menu_name) {
    $rastro = parent::doGetActiveTrailIds($menu_name);
    if ($menu_name !== self::MENU || !$this->esPaginaDeUnPrograma()) {
      return $rastro;
    }
    if (!$this->existeElEnlace()) {
      return $rastro;
    }
    // El enlace y sus padres (si los tuviera) van delante del rastro.
    $padres = $this->menuLinkManager->getParentIds(self::ID_ENLACE_PROGRAMAS)
      ?: [self::ID_ENLACE_PROGRAMAS => self::ID_ENLACE_PROGRAMAS];
    return $padres + $rastro;
  }

  /**
   * Indica si la ruta actual es la página canónica de un programa.
   *
   * @return bool
   *   TRUE si es `entity.node.canonical` de un nodo `programa`.
   */
  protected function esPaginaDeUnPrograma(): bool {
    if ($this->routeMatch->getRouteName() !== 'entity.node.canonical') {
      return FALSE;
    }
    $nodo = $this->routeMatch->getParameter('node');
    return $nodo instanceof NodeInterface && $nodo->bundle() === 'programa';
  }

  /**
   * Indica si el enlace «Programas» existe en el menú.
   *
   * @return bool
   *   TRUE si la definición del enlace está disponible.
   */
  protected function existeElEnlace(): bool {
    return $this->menuLinkManager->hasDefinition(self::ID_ENLACE_PROGRAMAS);
  }

}
