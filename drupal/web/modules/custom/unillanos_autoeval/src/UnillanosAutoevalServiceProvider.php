<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\unillanos_autoeval\Menu\RastroActivoProgramas;

/**
 * Cambia la clase del rastro activo del menú para marcar «Programas».
 */
class UnillanosAutoevalServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->hasDefinition('menu.active_trail')) {
      // Mismos argumentos y etiquetas: solo cambia la clase. El servicio deja
      // de ser perezoso (`lazy`): un servicio perezoso necesita una clase proxy
      // generada para la subclase y su construcción es barata.
      $container->getDefinition('menu.active_trail')
        ->setClass(RastroActivoProgramas::class)
        ->setLazy(FALSE);
    }
  }

}
