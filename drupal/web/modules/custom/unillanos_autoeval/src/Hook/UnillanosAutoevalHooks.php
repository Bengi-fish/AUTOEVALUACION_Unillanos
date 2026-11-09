<?php

namespace Drupal\unillanos_autoeval\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\media\MediaForm;
use Drupal\node\Form\NodeForm;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\unillanos_autoeval\Service\CalculadorGrado;
use Drupal\unillanos_autoeval\Service\EnlacesEdicion;
use Drupal\unillanos_autoeval\Service\EntidadesDependientes;
use Drupal\unillanos_autoeval\Service\ModeloProceso;
use Drupal\unillanos_autoeval\Service\ProgramasYProcesos;
use Drupal\unillanos_autoeval\Service\SugerenciaDestino;

/**
 * Hooks del módulo de autoevaluación.
 */
class UnillanosAutoevalHooks {

  use StringTranslationTrait;

  /**
   * Permiso que habilita editar «Publicado» en el contenido de autoevaluación.
   */
  const PERMISO_PUBLICACION = 'change publication status on acreditacion content';

  /**
   * Tipos de contenido y de medio a los que aplica el permiso de publicación.
   *
   * @var array<string, string[]>
   */
  const TIPOS_PUBLICACION = [
    'node' => [
      'programa',
      'proceso',
      'valoracion',
      'hallazgo',
      'plan_mejoramiento',
      'meta',
      'seguimiento',
      'lineamiento',
    ],
    'media' => ['documento'],
  ];

  /**
   * Constructor.
   */
  public function __construct(
    protected CalculadorGrado $calculadorGrado,
    protected SugerenciaDestino $sugerenciaDestino,
    protected ModeloProceso $modeloProceso,
    protected RequestStack $requestStack,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $usuarioActual,
    protected EntidadesDependientes $dependientes,
    protected TimeInterface $tiempo,
    #[Autowire(service: 'logger.channel.unillanos_autoeval')]
    protected LoggerInterface $registro,
  ) {}

  /**
   * Relaciones que se precargan en los formularios de creación.
   *
   * Por tipo de entidad y de contenido: campo => [parámetro de la URL, tipo de
   * contenido al que debe apuntar]. Lo usa el panel de Acreditación para abrir
   * «crear valoración» con el proceso ya elegido, sin cambiar el modelo.
   *
   * @var array<string, array<string, array<string, array{0: string, 1: string}>>>
   */
  const PRECARGA = [
    'node' => [
      'proceso' => ['field_programa' => ['programa', 'programa']],
      'valoracion' => ['field_proceso' => ['proceso', 'proceso']],
      'hallazgo' => ['field_proceso' => ['proceso', 'proceso']],
      'plan_mejoramiento' => ['field_proceso' => ['proceso', 'proceso']],
      'meta' => ['field_plan' => ['plan', 'plan_mejoramiento']],
    ],
    'media' => [
      'documento' => ['field_proceso' => ['proceso', 'proceso']],
    ],
  ];

  /**
   * Relaciones con un término que se precargan en los formularios de creación.
   *
   * Por tipo de contenido: campo => [parámetro de la URL, vocabulario al que
   * debe pertenecer el término]. Lo usa el modo edición para abrir «crear
   * valoración» con el factor ya elegido.
   *
   * @var array<string, array<string, array<string, array{0: string, 1: string}>>>
   */
  const PRECARGA_TERMINOS = [
    'node' => [
      'valoracion' => ['field_elemento' => ['elemento', 'elemento_modelo']],
    ],
  ];

  /**
   * Implements hook_theme().
   *
   * Plantilla del panel de Acreditación (vive en el módulo: se pinta con el
   * tema de administración, no con el tema público).
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'unillanos_acreditacion_panel' => [
        'variables' => ['datos' => []],
        'template' => 'unillanos-acreditacion-panel',
      ],
    ];
  }

  /**
   * Implements hook_entity_prepare_form().
   *
   * En un formulario de creación, si la URL trae el identificador del programa,
   * el proceso o el plan (`?proceso=49`), precarga esa relación. Solo actúa
   * sobre entidades nuevas de los tipos de PRECARGA, con un número entero que
   * sea un contenido del tipo esperado que la persona pueda ver; nunca
   * sobrescribe un valor que ya exista ni cambia el modelo de datos.
   */
  #[Hook('entity_prepare_form')]
  public function entityPrepareForm(EntityInterface $entity, string $operation, FormStateInterface $form_state): void {
    $operaciones = ['default', 'add', 'acreditacion'];
    if (!$entity instanceof FieldableEntityInterface || !$entity->isNew() || !in_array($operation, $operaciones, TRUE)) {
      return;
    }
    $reglas = self::PRECARGA[$entity->getEntityTypeId()][$entity->bundle()] ?? [];
    $reglas_terminos = self::PRECARGA_TERMINOS[$entity->getEntityTypeId()][$entity->bundle()] ?? [];
    $peticion = $this->requestStack->getCurrentRequest();
    if ((!$reglas && !$reglas_terminos) || !$peticion) {
      return;
    }
    // all(): un parámetro en forma de arreglo (?proceso[]=1) no debe lanzar una
    // excepción de petición mala; simplemente no se usa.
    $consulta = $peticion->query->all();
    foreach ($reglas as $campo => [$parametro, $tipo]) {
      $valor = $consulta[$parametro] ?? NULL;
      if (!is_string($valor) || !ctype_digit($valor) || !$entity->hasField($campo) || !$entity->get($campo)->isEmpty()) {
        continue;
      }
      $destino = $this->entityTypeManager->getStorage('node')->load((int) $valor);
      if ($destino instanceof NodeInterface && $destino->bundle() === $tipo && $destino->access('view')) {
        $entity->set($campo, $destino->id());
      }
    }
    // Términos (el factor de una valoración): del vocabulario esperado y que
    // la persona pueda ver.
    foreach ($reglas_terminos as $campo => [$parametro, $vocabulario]) {
      $valor = $consulta[$parametro] ?? NULL;
      if (!is_string($valor) || !ctype_digit($valor) || !$entity->hasField($campo) || !$entity->get($campo)->isEmpty()) {
        continue;
      }
      $termino = $this->entityTypeManager->getStorage('taxonomy_term')->load((int) $valor);
      if ($termino && $termino->bundle() === $vocabulario && $termino->access('view')) {
        $entity->set($campo, $termino->id());
      }
    }
  }

  /**
   * Implements hook_form_alter().
   *
   * Solo toca los formularios simples de Acreditación (modo «acreditacion» de
   * la ficha del programa y del documento): añade ayudas en lenguaje sencillo
   * a cada campo y, al guardar, vuelve al panel. El formulario completo de
   * Drupal no cambia. Los textos son traducibles (t()).
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if ($form_id === 'user_login_form') {
      $this->alPortadaTrasEntrar($form);
      return;
    }
    $this->enPanelLateral($form);
    $ayudas = match ($form_id) {
      'node_programa_acreditacion_form' => $this->ayudasDeLaFicha(),
      'media_documento_acreditacion_form' => $this->ayudasDelDocumento(),
      default => NULL,
    };
    if ($ayudas === NULL) {
      return;
    }
    foreach ($ayudas as $campo => $texto) {
      if (isset($form[$campo])) {
        $this->ponerAyuda($form[$campo], $texto);
      }
    }
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#submit'][] = [self::class, 'volverAlPanel'];
    }
  }

  /**
   * Implements hook_entity_access().
   *
   * Impide borrar un contenido de autoevaluación (o un documento) al que otro
   * contenido todavía apunta: un proceso con valoraciones, hallazgos, planes o
   * documentos; un programa con procesos; un plan con metas; una meta con
   * seguimientos; un hallazgo con metas; un documento enlazado desde una
   * valoración. Aplica a quien NO tiene el acceso de administración del
   * contenido (el rol Acreditación y cualquier otro rol limitado); quien tiene
   * «bypass node access» (nodos) o «administer media» (medios) conserva el
   * acceso de núcleo y no se bloquea. El resultado varía por permisos y se
   * invalida cuando cambia la lista de los tipos que pueden depender.
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation !== 'delete' || !$this->dependientes->enAlcance($entity)) {
      return AccessResult::neutral();
    }
    $permiso = $entity->getEntityTypeId() === 'media' ? 'administer media' : 'bypass node access';
    $resultado = $account->hasPermission($permiso) || !$this->dependientes->tieneDependientes($entity)
      ? AccessResult::neutral()
      : AccessResult::forbidden('Otro contenido todavía apunta a este: elimina primero lo que depende de él.');
    return $resultado
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($entity)
      ->addCacheTags($this->dependientes->etiquetasDeCache($entity));
  }

  /**
   * Implements hook_entity_delete().
   *
   * Anota en el registro del sitio (canal `unillanos_autoeval`) quién eliminó
   * qué: usuario, fecha, tipo, identificador y título.
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if (!$this->dependientes->enAlcance($entity)) {
      return;
    }
    $this->registro->notice('Se eliminó @tipo «@titulo» (id @id) por @usuario (uid @uid) el @fecha.', [
      '@tipo' => $entity->getEntityTypeId() . ':' . $entity->bundle(),
      '@titulo' => (string) $entity->label(),
      '@id' => $entity->id(),
      '@usuario' => $this->usuarioActual->getAccountName(),
      '@uid' => $this->usuarioActual->id(),
      '@fecha' => date('Y-m-d H:i:s', $this->tiempo->getRequestTime()),
    ]);
  }

  /**
   * Hace que un formulario abierto en el panel lateral se guarde sin recargar.
   *
   * Los lápices del modo edición abren la pantalla de edición en el panel
   * lateral (off-canvas). Para que guardar no saque a la persona del panel, el
   * botón principal se envía por AJAX: con errores, se vuelve a mostrar el
   * formulario con sus mensajes dentro del panel; sin errores, Drupal
   * redirige a la página de origen (`?destination=`), que se recarga con el
   * cambio. Solo actúa para quien tiene el permiso del modo edición y solo en
   * una petición del panel lateral; fuera de él, ningún formulario cambia.
   *
   * @param array<mixed> $form
   *   El formulario.
   */
  protected function enPanelLateral(array &$form): void {
    $solicitud = $this->requestStack->getCurrentRequest();
    if (!$solicitud || !$this->usuarioActual->hasPermission(EnlacesEdicion::PERMISO)) {
      return;
    }
    // Al enviar el formulario, Drupal repite `_wrapper_format` en la URL (el
    // del panel y el de AJAX) y PHP se queda con el último: se mira la URL
    // completa de la petición.
    if (!str_contains($solicitud->getRequestUri(), '_wrapper_format=drupal_dialog.off_canvas')) {
      return;
    }
    if (!isset($form['actions']['submit']) || ($form['actions']['submit']['#type'] ?? '') !== 'submit') {
      return;
    }
    $form['#prefix'] = '<div id="ua-panel-formulario">' . ($form['#prefix'] ?? '');
    $form['#suffix'] = ($form['#suffix'] ?? '') . '</div>';
    $form['actions']['submit']['#ajax'] = [
      'callback' => self::class . '::devolverFormularioAlPanel',
      'wrapper' => 'ua-panel-formulario',
      'progress' => ['type' => 'throbber'],
    ];
  }

  /**
   * Cierra el panel y recarga la página, o devuelve el formulario con errores.
   *
   * Sin errores, el panel se cierra y se redirige a la página de origen
   * (`?destination=`, solo rutas internas; si no hay, a la portada): como es
   * la misma página, se recarga y muestra el cambio y el mensaje de Drupal.
   * Con errores, se vuelve a pintar el formulario con sus mensajes dentro del
   * panel.
   *
   * @param array<mixed> $form
   *   Formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   *
   * @return array<mixed>|\Drupal\Core\Ajax\AjaxResponse
   *   Las órdenes de AJAX, o el formulario con los mensajes al principio.
   */
  public function devolverFormularioAlPanel(array &$form, FormStateInterface $form_state): array|AjaxResponse {
    if ($form_state->hasAnyErrors()) {
      $form['mensajes_del_panel'] = ['#type' => 'status_messages', '#weight' => -1000];
      return $form;
    }
    $destino = $this->requestStack->getCurrentRequest()?->query->get('destination');
    $url = Url::fromRoute('<front>');
    if (is_string($destino) && str_starts_with($destino, '/') && !str_starts_with($destino, '//') && !UrlHelper::isExternal($destino)) {
      try {
        $url = Url::fromUserInput($destino);
      }
      catch (\InvalidArgumentException) {
        // Un destino que Drupal no puede construir: se vuelve a la portada.
      }
    }
    $respuesta = new AjaxResponse();
    $respuesta->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    $respuesta->addCommand(new RedirectCommand($url->toString()));
    return $respuesta;
  }

  /**
   * En /acceso-acreditacion, tras iniciar sesión se va a la portada.
   *
   * Drupal lleva a la página de la cuenta; solo en la ruta propia se añade un
   * manejador que cambia el destino a la portada. Un `destination` explícito
   * en la URL sigue mandando, y /user/login no cambia.
   *
   * @param array<mixed> $form
   *   El formulario de inicio de sesión.
   */
  protected function alPortadaTrasEntrar(array &$form): void {
    $solicitud = $this->requestStack->getCurrentRequest();
    if ($solicitud?->attributes->get('_route') === 'unillanos_autoeval.acceso' && !$solicitud->query->has('destination')) {
      $form['#submit'][] = [self::class, 'alPortada'];
    }
  }

  /**
   * Cambia el destino del formulario de inicio de sesión a la portada.
   *
   * @param array<mixed> $form
   *   Formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  public static function alPortada(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('<front>');
  }

  /**
   * Después de guardar un formulario simple, vuelve al panel si se puede abrir.
   *
   * @param array<mixed> $form
   *   Formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  public static function volverAlPanel(array &$form, FormStateInterface $form_state): void {
    $url = Url::fromRoute('unillanos_autoeval.acreditacion');
    if ($url->access()) {
      $form_state->setRedirectUrl($url);
    }
  }

  /**
   * Pone el texto de ayuda en el elemento de entrada de un campo.
   *
   * @param array<mixed> $campo
   *   El campo del formulario (el contenedor con su `widget`).
   * @param mixed $texto
   *   El texto de ayuda, ya traducible.
   */
  protected function ponerAyuda(array &$campo, mixed $texto): void {
    $rutas = [
      ['widget'],
      ['widget', 'value'],
      ['widget', 0, 'value'],
      ['widget', 0, 'target_id'],
      ['widget', 0],
    ];
    foreach ($rutas as $ruta) {
      $existe = FALSE;
      $elemento = &NestedArray::getValue($campo, $ruta, $existe);
      if ($existe && is_array($elemento) && isset($elemento['#type'])) {
        $elemento['#description'] = $texto;
        return;
      }
    }
    $campo['widget']['#description'] = $texto;
  }

  /**
   * Ayudas de la ficha del programa (lenguaje sencillo).
   *
   * @return array<string, mixed>
   *   Texto de ayuda por campo.
   */
  protected function ayudasDeLaFicha(): array {
    return [
      'title' => $this->t('Nombre completo del programa, tal como debe verse en el sitio.'),
      'field_facultad' => $this->t('Elija la facultad a la que pertenece el programa.'),
      'field_nivel' => $this->t('Elija si es de pregrado, especialización, maestría o doctorado.'),
      'field_modalidad' => $this->t('Elija cómo se ofrece el programa, por ejemplo presencial. Si no lo sabe, déjelo en blanco.'),
      'field_sede' => $this->t('Elija la sede donde se ofrece el programa. Si no lo sabe, déjelo en blanco.'),
      'field_duracion' => $this->t('Número de semestres que dura el programa. Si no lo sabe, déjelo en blanco.'),
      'field_director' => $this->t('Nombre de la persona que dirige el programa.'),
      'field_codigo_snies' => $this->t('Número con el que el Ministerio de Educación identifica el programa (código SNIES).'),
      'field_resolucion_registro' => $this->t('Número y fecha de la resolución que dio el registro calificado.'),
      'field_registro_hasta' => $this->t('Fecha hasta la que vale el registro calificado.'),
      'field_resolucion_acreditacion' => $this->t('Número y fecha de la resolución que dio la acreditación de alta calidad.'),
      'field_acreditacion_hasta' => $this->t('Fecha hasta la que vale la acreditación.'),
      'field_imagen' => $this->t('Foto que se ve arriba en la página del programa. Es opcional.'),
      'status' => $this->t('Si quita la marca, la página del programa deja de verse en el sitio público.'),
    ];
  }

  /**
   * Ayudas del documento (lenguaje sencillo).
   *
   * @return array<string, mixed>
   *   Texto de ayuda por campo.
   */
  protected function ayudasDelDocumento(): array {
    return [
      'name' => $this->t('Nombre del documento, tal como debe verse en la lista.'),
      'field_media_file' => $this->t('Suba aquí el archivo del documento.'),
      'field_categoria' => $this->t('Elija qué tipo de documento es, por ejemplo un informe o una norma.'),
      'field_fecha_publicacion' => $this->t('Fecha en que se publicó el documento.'),
      'field_version' => $this->t('Versión del documento, si la tiene.'),
      'field_proceso' => $this->t('Proceso de autoevaluación al que pertenece. Escriba parte del nombre y elíjalo de la lista. Déjelo en blanco si es un documento general.'),
      'field_lineamiento' => $this->t('Solo para normas: el modelo de evaluación al que corresponde. Si no aplica, déjelo en blanco.'),
      'status' => $this->t('Si quita la marca, el documento deja de verse en el sitio público.'),
    ];
  }

  /**
   * Implements hook_node_presave().
   */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    match ($node->bundle()) {
      'valoracion' => $this->calcularGrado($node),
      'hallazgo' => $this->sugerirDestino($node),
      default => NULL,
    };
  }

  /**
   * Implements hook_entity_field_access().
   *
   * Permite editar el campo «Publicado» (status) del contenido de
   * autoevaluación a quien tenga el permiso propio del módulo. El núcleo solo
   * lo concede con «administer nodes» o «administer node published status»; así
   * el rol de Acreditación puede despublicar sin ningún permiso administrativo.
   * Solo suma: nunca devuelve «prohibido» ni «neutral con razón», de modo que
   * quien ya tiene acceso por otra vía lo conserva, y no toca otros campos,
   * tipos de contenido ni tipos de medio.
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($operation !== 'edit' || $field_definition->getName() !== 'status' || !$items) {
      return AccessResult::neutral();
    }
    $entidad = $items->getEntity();
    $tipos = self::TIPOS_PUBLICACION[$entidad->getEntityTypeId()] ?? [];
    if (!in_array($entidad->bundle(), $tipos, TRUE)) {
      return AccessResult::neutral();
    }
    return AccessResult::allowedIfHasPermission($account, self::PERMISO_PUBLICACION);
  }

  /**
   * Implements hook_node_view_alter().
   *
   * Lo invoca el núcleo solo para los módulos (no para el tema): por eso vive
   * aquí. La página del programa nombra la facultad y la sede, lee procesos,
   * valoraciones, hallazgos y términos del modelo y lista los demás programas
   * de su facultad: si cualquiera cambia, debe volver a pintarse.
   */
  #[Hook('node_view_alter')]
  public function nodeViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!$entity instanceof FieldableEntityInterface || $entity->bundle() !== 'programa') {
      return;
    }
    $build['#cache']['tags'] = Cache::mergeTags(
      $build['#cache']['tags'] ?? [],
      ProgramasYProcesos::ETIQUETAS_AUTOEVALUACION,
    );
    // Los demás programas de la facultad dependen de la lista de programas, de
    // los términos de facultad y sede y de las etiquetas de nivel.
    $build['#cache']['tags'] = Cache::mergeTags($build['#cache']['tags'], [
      'node_list:programa',
      'taxonomy_term_list:facultad',
      'taxonomy_term_list:sede',
      'config:field.storage.node.field_nivel',
    ]);
    // Las consultas de los servicios respetan el acceso del visitante.
    $build['#cache']['contexts'][] = 'user.permissions';
    // Los enlaces del modo edición llevan la URL actual como destino.
    if ($this->usuarioActual->hasPermission(EnlacesEdicion::PERMISO)) {
      $build['#cache']['contexts'][] = 'url.path';
      $build['#cache']['contexts'][] = 'url.query_args';
    }
    foreach (['field_facultad', 'field_sede'] as $campo) {
      $termino = $entity->get($campo)->entity;
      if ($termino) {
        $build['#cache']['tags'] = Cache::mergeTags($build['#cache']['tags'], $termino->getCacheTags());
      }
    }
    // La foto de la cabecera (si la hay) y sus estilos de imagen.
    if ($entity->hasField('field_imagen') && !$entity->get('field_imagen')->isEmpty()) {
      $archivo = $entity->get('field_imagen')->entity;
      if ($archivo) {
        $build['#cache']['tags'] = Cache::mergeTags($build['#cache']['tags'], $archivo->getCacheTags());
      }
      $build['#cache']['tags'] = Cache::mergeTags($build['#cache']['tags'], ['config:image_style_list']);
    }
  }

  /**
   * Implements hook_entity_type_alter().
   *
   * Agrega las restricciones de validación. Cada validador comprueba el
   * tipo de contenido o vocabulario y no hace nada si no le corresponde.
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    foreach (['node', 'taxonomy_term'] as $tipo) {
      if (isset($entity_types[$tipo])) {
        $entity_types[$tipo]->addConstraint('UnillanosClaveUnica');
      }
    }
    // Modo de formulario «acreditacion» (ficha y documentos simples): la
    // operación necesita una clase de formulario; es la misma del formulario
    // normal, solo cambia la pantalla de campos (core.entity_form_display.*).
    if (isset($entity_types['node'])) {
      $entity_types['node']->setFormClass('acreditacion', NodeForm::class);
    }
    if (isset($entity_types['media'])) {
      $entity_types['media']->setFormClass('acreditacion', MediaForm::class);
    }
    if (isset($entity_types['node'])) {
      $entity_types['node']->addConstraint('UnillanosValoracionUnica');
      $entity_types['node']->addConstraint('UnillanosProcesoSedeOPrograma');
      $entity_types['node']->addConstraint('UnillanosEstadoPorTipo');
      $entity_types['node']->addConstraint('UnillanosModeloDelProceso');
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() para node_form.
   *
   * - Proceso: el selector de modelo ofrece solo los modelos permitidos.
   * - Valoración y hallazgo: el elemento se busca solo entre los elementos
   *   del modelo del proceso elegido. Al elegir otro proceso, el campo se
   *   refresca por AJAX. La restricción UnillanosModeloDelProceso sigue
   *   siendo la garantía al guardar.
   */
  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(array &$form, FormStateInterface $form_state): void {
    $formulario = $form_state->getFormObject();
    if (!$formulario instanceof EntityFormInterface) {
      return;
    }
    $nodo = $formulario->getEntity();
    if (!$nodo instanceof NodeInterface) {
      return;
    }
    match ($nodo->bundle()) {
      'proceso' => $this->limitarModelos($form),
      'valoracion', 'hallazgo' => $this->limitarElementos($form, $form_state, $nodo),
      default => NULL,
    };
  }

  /**
   * Deja en el selector de modelo solo los lineamientos permitidos.
   */
  protected function limitarModelos(array &$form): void {
    if (!isset($form['field_lineamiento']['widget']['#options'])) {
      return;
    }
    $permitidos = $this->modeloProceso->modelosPermitidos();
    $form['field_lineamiento']['widget']['#options'] = array_filter(
      $form['field_lineamiento']['widget']['#options'],
      fn($clave): bool => $clave === '_none' || isset($permitidos[(int) $clave]),
      ARRAY_FILTER_USE_KEY,
    );
  }

  /**
   * Filtra el autocompletado del elemento por el modelo del proceso.
   */
  protected function limitarElementos(array &$form, FormStateInterface $form_state, NodeInterface $nodo): void {
    $elemento = &$form['field_elemento']['widget'][0]['target_id'];
    $proceso = &$form['field_proceso']['widget'][0]['target_id'];
    if (!isset($elemento, $proceso)) {
      return;
    }
    // Lo que el usuario acaba de escribir manda sobre lo guardado.
    $entrada = $form_state->getUserInput()['field_proceso'][0]['target_id'] ?? NULL;
    $proceso_id = $entrada !== NULL
      ? EntityAutocomplete::extractEntityIdFromAutocompleteInput($entrada)
      : $nodo->get('field_proceso')->target_id;

    $elemento['#selection_handler'] = 'unillanos_elemento_modelo';
    $elemento['#selection_settings']['lineamiento'] = $this->modeloProceso->lineamientoDelProceso($proceso_id);
    $elemento['#description'] = $this->t('Elija primero el proceso: solo se ofrecen los elementos de su modelo.');
    $form['field_elemento']['#prefix'] = '<div id="unillanos-elemento-wrapper">';
    $form['field_elemento']['#suffix'] = '</div>';
    $proceso['#ajax'] = [
      'callback' => [static::class, 'refrescarElemento'],
      'event' => 'autocompleteclose change',
      'wrapper' => 'unillanos-elemento-wrapper',
      'disable-refocus' => TRUE,
    ];
  }

  /**
   * Callback AJAX: devuelve el campo del elemento ya filtrado.
   */
  public static function refrescarElemento(array $form): array {
    return $form['field_elemento'];
  }

  /**
   * Calcula `field_grado` desde `field_valoracion`; no se digita.
   */
  protected function calcularGrado(NodeInterface $valoracion): void {
    $grado = $this->calculadorGrado->calcular($valoracion->get('field_valoracion')->value);
    $valoracion->set('field_grado', $grado ? $grado->id() : NULL);
  }

  /**
   * Sugiere `field_destino` al crear el hallazgo, si no se eligió uno.
   */
  protected function sugerirDestino(NodeInterface $hallazgo): void {
    if (!$hallazgo->isNew() || !$hallazgo->get('field_destino')->isEmpty()) {
      return;
    }
    $destino = $this->sugerenciaDestino->sugerir(
      $hallazgo->get('field_proceso')->target_id,
      $hallazgo->get('field_elemento')->target_id,
    );
    if ($destino) {
      $hallazgo->set('field_destino', $destino);
    }
  }

}
