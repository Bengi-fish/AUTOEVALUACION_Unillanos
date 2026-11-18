<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\unillanos_autoeval\Service\InstitucionalCifras;
use Drupal\unillanos_autoeval\Service\InstitucionalDependencias;
use Drupal\unillanos_autoeval\Service\InstitucionalDirectivos;
use Drupal\unillanos_autoeval\Service\InstitucionalNormas;
use Drupal\unillanos_autoeval\Service\InstitucionalPortada;
use Drupal\unillanos_autoeval\Service\InstitucionalProcesos;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Páginas de «Institucional»: portada y sus cinco módulos.
 *
 * Entrega los datos de los servicios; el tema los pinta con las plantillas
 * `unillanos_institucional_*`.
 */
final class InstitucionalController extends ControllerBase {

  /**
   * Constructor.
   */
  public function __construct(
    protected InstitucionalPortada $portada,
    protected InstitucionalProcesos $procesos,
    protected InstitucionalDependencias $dependencias,
    protected InstitucionalDirectivos $directivos,
    protected InstitucionalNormas $normas,
    protected InstitucionalCifras $cifras,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(InstitucionalPortada::class),
      $container->get(InstitucionalProcesos::class),
      $container->get(InstitucionalDependencias::class),
      $container->get(InstitucionalDirectivos::class),
      $container->get(InstitucionalNormas::class),
      $container->get(InstitucionalCifras::class),
    );
  }

  /**
   * Portada de «Institucional» (/institucional).
   *
   * @return array<string, mixed>
   *   Arreglo de render.
   */
  public function portada(): array {
    return $this->construir('portada', fn(CacheableMetadata $c): array => $this->portada->datos($c));
  }

  /**
   * Procesos institucionales (/institucional/procesos).
   *
   * @return array<string, mixed>
   *   Arreglo de render.
   */
  public function procesos(): array {
    return $this->construir('procesos', fn(CacheableMetadata $c): array => $this->procesos->datos($c));
  }

  /**
   * Dependencias (/institucional/dependencias).
   *
   * @return array<string, mixed>
   *   Arreglo de render.
   */
  public function dependencias(): array {
    return $this->construir('dependencias', fn(CacheableMetadata $c): array => $this->dependencias->datos($c));
  }

  /**
   * Directivos (/institucional/directivos).
   *
   * @return array<string, mixed>
   *   Arreglo de render.
   */
  public function directivos(): array {
    return $this->construir('directivos', fn(CacheableMetadata $c): array => $this->directivos->datos($c));
  }

  /**
   * Normatividad (/institucional/normatividad).
   *
   * @return array<string, mixed>
   *   Arreglo de render.
   */
  public function normatividad(): array {
    return $this->construir('normatividad', fn(CacheableMetadata $c): array => $this->normas->datos($c));
  }

  /**
   * Cifras (/institucional/cifras).
   *
   * @return array<string, mixed>
   *   Arreglo de render.
   */
  public function cifras(): array {
    return $this->construir('cifras', fn(CacheableMetadata $c): array => $this->cifras->datos($c));
  }

  /**
   * Arma el arreglo de render de una página.
   *
   * @param string $pagina
   *   Sufijo de la plantilla (`unillanos_institucional_{pagina}`).
   * @param callable $datos
   *   Recibe los metadatos de caché y devuelve los datos de la página.
   *
   * @return array<string, mixed>
   *   Arreglo de render con la caché aplicada.
   */
  protected function construir(string $pagina, callable $datos): array {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user.permissions']);
    $construccion = [
      '#theme' => 'unillanos_institucional_' . $pagina,
      '#datos' => $datos($cache),
    ];
    $cache->applyTo($construccion);
    return $construccion;
  }

}
