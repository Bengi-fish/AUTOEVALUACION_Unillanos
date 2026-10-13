<?php

namespace Drupal\unillanos\Hook;

use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for unillanos.
 */
class UnillanosHooks {

  /**
   * Constructor.
   */
  public function __construct(
    protected ExtensionPathResolver $pathResolver,
    protected TitleResolverInterface $titleResolver,
    protected RouteMatchInterface $routeMatch,
    protected RequestStack $requestStack,
    protected RendererInterface $renderer,
  ) {}

  /**
   * Implements hook_preprocess_html().
   *
   * Añade la clase raíz `ua` que acota los estilos del prototipo, el color
   * del navegador y el favicon del tema.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $variables['attributes']['class'][] = 'ua';
    $ruta = '/' . $this->pathResolver->getPath('theme', 'unillanos');
    $variables['page']['#attached']['html_head'][] = [
      [
        '#tag' => 'meta',
        '#attributes' => ['name' => 'theme-color', 'content' => '#e3061d'],
      ],
      'unillanos_theme_color',
    ];
    $variables['page']['#attached']['html_head_link'][] = [
      [
        'rel' => 'icon',
        'type' => 'image/png',
        'href' => $ruta . '/images/favicon.png',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_breadcrumb().
   *
   * Drupal no incluye la página actual en las migas y el componente
   * unillanos:breadcrumb trata el último elemento como la página actual, así
   * que se añade el título de la ruta como último elemento sin enlace.
   */
  #[Hook('preprocess_breadcrumb')]
  public function preprocessBreadcrumb(array &$variables): void {
    $request = $this->requestStack->getCurrentRequest();
    $ruta = $this->routeMatch->getRouteObject();
    if (empty($variables['breadcrumb']) || !$request || !$ruta) {
      return;
    }
    $titulo = $this->titleResolver->getTitle($request, $ruta);
    // Un título que es un render array se renderiza sin aislar para que sus
    // etiquetas de caché suban al bloque; uno de texto se deja tal cual.
    $texto = is_array($titulo)
      ? Html::decodeEntities(strip_tags((string) $this->renderer->render($titulo)))
      : (string) $titulo;
    if (trim($texto) !== '') {
      $variables['breadcrumb'][] = ['text' => trim($texto), 'url' => ''];
    }
  }

  /**
   * Implements hook_block_view_BASE_BLOCK_ID_alter() para las migas.
   *
   * La última miga es el título de la ruta: el bloque varía por ruta y por
   * cada entidad de sus parámetros (p. ej. el nodo), para que editar el
   * título invalide la miga.
   */
  #[Hook('block_view_system_breadcrumb_block_alter')]
  public function breadcrumbBlockAlter(array &$build, BlockPluginInterface $block): void {
    $build['#cache']['contexts'][] = 'route';
    foreach ($this->routeMatch->getParameters() as $parametro) {
      if ($parametro instanceof CacheableDependencyInterface) {
        $build['#cache']['tags'] = Cache::mergeTags($build['#cache']['tags'] ?? [], $parametro->getCacheTags());
      }
    }
  }

  /**
   * Implements hook_block_view_BASE_BLOCK_ID_alter() para el menú.
   *
   * El elemento «Ingresar» / «Mi cuenta» depende de si hay sesión iniciada,
   * y ese dato no es un contexto de caché por sí mismo.
   */
  #[Hook('block_view_system_menu_block_alter')]
  public function menuBlockAlter(array &$build, BlockPluginInterface $block): void {
    $build['#cache']['contexts'][] = 'user.roles:authenticated';
  }

  /**
   * Implements hook_page_attachments().
   *
   * El botón de cuenta del encabezado cambia con la sesión.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $attachments['#cache']['contexts'][] = 'user.roles:authenticated';
  }

  /**
   * Implements hook_preprocess_image_widget().
   */
  #[Hook('preprocess_image_widget')]
  public function preprocessImageWidget(array &$variables): void {
    $data = &$variables['data'];
    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo revisit in https://drupal.org/node/953034
    // @todo revisit in https://drupal.org/node/3114318
    if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
      unset($data['preview']);
    }
  }

}
