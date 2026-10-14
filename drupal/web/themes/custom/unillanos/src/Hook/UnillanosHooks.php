<?php

namespace Drupal\unillanos\Hook;

use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
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
      'unillanos_portada' => [
        'variables' => ['procesos' => []],
        'template' => 'unillanos-portada',
        'path' => $this->pathResolver->getPath('theme', 'unillanos') . '/templates/content',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_page().
   *
   * Los programas pintan su propia cabecera (con migas y título): en la ruta
   * canónica la página base no repite el bloque de migas ni el del título, y
   * deja el contenido sin contenedor. En una previsualización o una revisión
   * se pinta el nodo normal, con los bloques de la página.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $nodo = $this->routeMatch->getParameter('node');
    $variables['pagina_propia'] = $this->routeMatch->getRouteName() === 'entity.node.canonical'
      && $nodo instanceof NodeInterface
      && $nodo->bundle() === 'programa';
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
      $variables['factores'] = $this->presentador->factoresDelPrograma($nodo, new CacheableMetadata());
    }
  }

  /**
   * Implements hook_node_view_alter().
   *
   * La cabecera nombra la facultad y la sede, y la sección de autoevaluación
   * lee procesos, valoraciones, hallazgos y términos del modelo: si cambian,
   * el programa debe volver a pintarse.
   */
  #[Hook('node_view_alter')]
  public function nodeViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!$entity instanceof FieldableEntityInterface || $entity->bundle() !== 'programa') {
      return;
    }
    $build['#cache']['tags'] = Cache::mergeTags(
      $build['#cache']['tags'] ?? [],
      PresentadorAutoevaluacion::ETIQUETAS_AUTOEVALUACION,
    );
    // Las consultas del presentador respetan el acceso del visitante.
    $build['#cache']['contexts'][] = 'user.permissions';
    foreach (['field_facultad', 'field_sede'] as $campo) {
      $termino = $entity->get($campo)->entity;
      if ($termino) {
        $build['#cache']['tags'] = Cache::mergeTags($build['#cache']['tags'] ?? [], $termino->getCacheTags());
      }
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
