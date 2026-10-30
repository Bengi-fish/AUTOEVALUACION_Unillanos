<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;

/**
 * Lee los enlaces del menú del pie (`footer`) que el visitante puede ver.
 */
class EnlacesDelPie {

  /**
   * Menú del que se leen los enlaces.
   */
  const MENU = 'footer';

  /**
   * Constructor.
   */
  public function __construct(
    protected MenuLinkTreeInterface $arbol,
  ) {}

  /**
   * Devuelve los enlaces habilitados y accesibles del pie, ordenados.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché del menú, del acceso y de las URL.
   * @param string $menu
   *   Nombre del menú (por defecto el del pie).
   *
   * @return array<int, array{title: string, url: string}>
   *   Título y URL de cada enlace; vacío si no hay ninguno.
   */
  public function enlaces(CacheableMetadata $cache, string $menu = self::MENU): array {
    $cache->addCacheTags(['config:system.menu.' . $menu]);
    $cache->addCacheContexts(['user.permissions']);
    $parametros = new MenuTreeParameters();
    $parametros->setMaxDepth(1)->onlyEnabledLinks();
    $arbol = $this->arbol->load($menu, $parametros);
    $arbol = $this->arbol->transform($arbol, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);
    $enlaces = [];
    foreach ($arbol as $elemento) {
      $acceso = $elemento->access ?? NULL;
      if ($acceso instanceof CacheableDependencyInterface) {
        $cache->addCacheableDependency($acceso);
      }
      // El árbol deja los enlaces sin acceso (con el título «Inaccessible»):
      // quien los pinta debe omitirlos.
      if ($acceso && !$acceso->isAllowed()) {
        continue;
      }
      $url = $elemento->link->getUrlObject()->toString(TRUE);
      $cache->addCacheableDependency($url);
      $enlaces[] = ['title' => (string) $elemento->link->getTitle(), 'url' => $url->getGeneratedUrl()];
    }
    return $enlaces;
  }

}
