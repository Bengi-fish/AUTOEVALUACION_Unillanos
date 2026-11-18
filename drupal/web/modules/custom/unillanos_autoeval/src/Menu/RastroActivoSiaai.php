<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Menu;

use Drupal\Core\Menu\MenuActiveTrail;
use Drupal\node\NodeInterface;

/**
 * Rastro activo del menú principal dentro del SIAAI.
 *
 * El SIAAI es un solo ítem del menú principal («SIAAI»); sus secciones
 * (programas, documentos, «Institucional» y la página de cada programa) viven
 * en la barra secundaria. Por eso, en cualquiera de ellas el ítem «SIAAI» del
 * menú principal queda en el rastro activo. La página de un programa no está
 * en el menú: sin este ajuste Drupal no marcaría ningún ítem.
 */
class RastroActivoSiaai extends MenuActiveTrail {

  /**
   * Identificador del enlace «SIAAI» del menú principal.
   */
  const ID_ENLACE_SIAAI = 'unillanos_autoeval.menu.siaai';

  /**
   * Menú al que se aplica el ajuste.
   */
  const MENU = 'main';

  /**
   * Rutas que pertenecen al SIAAI (llevan la barra secundaria).
   *
   * Los programas (nodo `programa`) también son del SIAAI; se detectan aparte.
   *
   * @var string[]
   */
  const RUTAS = [
    'unillanos_autoeval.portada',
    'unillanos_autoeval.programas',
    'unillanos_autoeval.documentos',
    'unillanos_autoeval.institucional',
    'unillanos_autoeval.institucional_procesos',
    'unillanos_autoeval.institucional_dependencias',
    'unillanos_autoeval.institucional_directivos',
    'unillanos_autoeval.institucional_normatividad',
    'unillanos_autoeval.institucional_cifras',
  ];

  /**
   * {@inheritdoc}
   */
  protected function doGetActiveTrailIds($menu_name) {
    $rastro = parent::doGetActiveTrailIds($menu_name);
    if ($menu_name !== self::MENU || !$this->esPaginaDelSiaai()) {
      return $rastro;
    }
    if (!$this->menuLinkManager->hasDefinition(self::ID_ENLACE_SIAAI)) {
      return $rastro;
    }
    // El enlace y sus padres (si los tuviera) van delante del rastro.
    $padres = $this->menuLinkManager->getParentIds(self::ID_ENLACE_SIAAI)
      ?: [self::ID_ENLACE_SIAAI => self::ID_ENLACE_SIAAI];
    return $padres + $rastro;
  }

  /**
   * Indica si la página actual es del SIAAI.
   *
   * @return bool
   *   TRUE en las rutas del SIAAI y en la página canónica de un programa.
   */
  protected function esPaginaDelSiaai(): bool {
    $ruta = (string) $this->routeMatch->getRouteName();
    if (in_array($ruta, self::RUTAS, TRUE)) {
      return TRUE;
    }
    if ($ruta !== 'entity.node.canonical') {
      return FALSE;
    }
    $nodo = $this->routeMatch->getParameter('node');
    return $nodo instanceof NodeInterface && $nodo->bundle() === 'programa';
  }

}
