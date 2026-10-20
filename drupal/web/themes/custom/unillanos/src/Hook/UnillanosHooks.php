<?php

namespace Drupal\unillanos\Hook;

use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\unillanos_autoeval\Service\EnlacesDelPie;
use Drupal\unillanos_autoeval\Service\PaginaPrograma;
use Drupal\unillanos_autoeval\Service\PresentadorAutoevaluacion;
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
    protected PresentadorAutoevaluacion $presentador,
    protected PaginaPrograma $paginaPrograma,
    protected EnlacesDelPie $enlacesDelPie,
    protected MenuActiveTrailInterface $rastroActivo,
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
    // `url.path`: la página actual también se marca por la URL del enlace.
    $build['#cache']['contexts'][] = 'user.roles:authenticated';
    $build['#cache']['contexts'][] = 'url.path';
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
   * Implements hook_theme().
   *
   * Registra la plantilla de la portada, cuyos datos entrega el controlador
   * de `unillanos_autoeval`.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'unillanos_documentos' => [
        'variables' => ['datos' => []],
        'template' => 'unillanos-documentos',
        'path' => $this->pathResolver->getPath('theme', 'unillanos') . '/templates/content',
      ],
      'unillanos_programas' => [
        'variables' => ['datos' => []],
        'template' => 'unillanos-programas',
        'path' => $this->pathResolver->getPath('theme', 'unillanos') . '/templates/content',
      ],
      'unillanos_portada' => [
        'variables' => ['datos' => []],
        'template' => 'unillanos-portada',
        'path' => $this->pathResolver->getPath('theme', 'unillanos') . '/templates/content',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_menu().
   *
   * Entrega a la plantilla el enlace activo del menú (el de la página exacta);
   * los demás enlaces del rastro (por ejemplo «Programas» en la página de un
   * programa) se marcan como parte del recorrido, no como la página actual.
   */
  #[Hook('preprocess_menu')]
  public function preprocessMenu(array &$variables): void {
    $enlace = $this->rastroActivo->getActiveLink($variables['menu_name'] ?? NULL);
    $variables['enlace_activo'] = $enlace ? $enlace->getPluginId() : '';
  }

  /**
   * Implements hook_preprocess_page().
   *
   * Los programas y la portada pintan su propia cabecera (con su h1): en la
   * ruta canónica del programa y en la página de inicio la página base no
   * repite el bloque de migas ni el del título, y deja el contenido sin
   * contenedor. En una previsualización o una revisión se pinta el nodo
   * normal, con los bloques de la página.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $nodo = $this->routeMatch->getParameter('node');
    $programa = $this->routeMatch->getRouteName() === 'entity.node.canonical'
      && $nodo instanceof NodeInterface
      && $nodo->bundle() === 'programa';
    // La portada, la de documentos y la de programas también traen su propio
    // h1.
    $variables['pagina_propia'] = $programa || !empty($variables['is_front'])
      || in_array($this->routeMatch->getRouteName(), ['unillanos_autoeval.documentos', 'unillanos_autoeval.programas'], TRUE);
    // Enlaces del pie desde el menú `footer` (sin enlaces, no hay columna). El
    // arreglo `pie_cache` no imprime nada: lleva a la página las etiquetas y
    // los contextos de caché del menú, del acceso y de las URL.
    $cache = new CacheableMetadata();
    $variables['pie_enlaces'] = $this->enlacesDelPie->enlaces($cache);
    $variables['pie_cache'] = [];
    $cache->applyTo($variables['pie_cache']);
  }

  /**
   * Implements hook_preprocess_node().
   *
   * Entrega a la plantilla del programa los datos ya armados por el
   * presentador.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $nodo = $variables['node'];
    // Solo en la ruta canónica: allí la página omite sus propias migas y su
    // título. En una previsualización o una revisión se pinta el nodo normal.
    $ruta_nodo = $this->routeMatch->getParameter('node');
    $canonica = $this->routeMatch->getRouteName() === 'entity.node.canonical'
      && $ruta_nodo instanceof NodeInterface
      && $ruta_nodo->id() === $nodo->id();
    if ($canonica && $nodo->bundle() === 'programa' && $variables['view_mode'] === 'full') {
      $variables['programa'] = $this->presentador->datosPrograma($nodo);
      // Los metadatos de caché de lo que lee el presentador (nodos, términos y
      // permisos) los cubre node_view_alter() para toda la página del nodo.
      $cache = new CacheableMetadata();
      $factores = $this->presentador->factoresDelPrograma($nodo, $cache);
      $resultado = $this->presentador->planDelPrograma($nodo, $factores, $cache);
      $variables['factores'] = $resultado['factores'];
      $variables['plan'] = $resultado['plan'];
      $variables['documentos'] = $this->presentador->documentosDelPrograma($nodo, $cache);
      // La bajada y, si el programa no tiene nada de autoevaluación, los demás
      // programas de la facultad y los enlaces al listado y a los documentos.
      $pagina = $this->paginaPrograma->armar($nodo, $variables['factores'], $variables['plan'], $variables['documentos'], $cache);
      $variables['lead'] = $pagina['lead'];
      $variables['otros'] = $pagina['otros'];
      $variables['enlaces'] = $pagina['enlaces'];
    }
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
