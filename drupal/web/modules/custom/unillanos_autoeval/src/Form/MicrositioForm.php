<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\unillanos_autoeval\Service\DatosMarco;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulario de administración de la cabecera, la franja y el pie.
 *
 * Edita `unillanos_autoeval.micrositio`. Las listas se escriben una por línea
 * con partes separadas por «|» («Título | URL», «Título | Valor | URL | Ícono»,
 * «Logo | Nombre | URL»). Las URL se
 * validan con la misma regla que aplica la cabecera al pintar (DatosMarco).
 */
final class MicrositioForm extends ConfigFormBase {

  /**
   * Constructor.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected DatosMarco $datosMarco,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('unillanos_autoeval.datos_marco'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'unillanos_autoeval_micrositio';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [DatosMarco::CONFIG];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   *   Formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   *
   * @return array<string, mixed>
   *   Formulario con los campos.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(DatosMarco::CONFIG);
    $pie = (array) ($config->get('pie') ?? []);
    $accesibilidad = (array) ($config->get('accesibilidad') ?? []);
    $ayuda_enlaces = $this->t('Un enlace por línea, con el formato «Título | URL». La URL puede empezar por «/», «#», «https://», «mailto:» o «tel:». Lo vacío no se muestra.');

    $form['cabecera'] = [
      '#type' => 'details',
      '#title' => $this->t('Cabecera y franja superior'),
      '#open' => TRUE,
    ];
    $form['cabecera']['nombre_linea1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nombre del sistema, primera línea'),
      '#default_value' => $config->get('nombre_linea1'),
      '#maxlength' => 100,
    ];
    $form['cabecera']['nombre_linea2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nombre del sistema, segunda línea'),
      '#default_value' => $config->get('nombre_linea2'),
      '#maxlength' => 100,
    ];
    $form['cabecera']['url_web_principal'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL de la web principal de la Universidad'),
      '#default_value' => $config->get('url_web_principal'),
      '#maxlength' => 255,
    ];
    $form['cabecera']['url_govco'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL del logo GOV.CO'),
      '#default_value' => $config->get('url_govco'),
      '#maxlength' => 255,
    ];
    $redes = (array) ($config->get('redes_sociales') ?? []);
    foreach ([
      'facebook' => $this->t('URL de Facebook'),
      'x' => $this->t('URL de X'),
      'instagram' => $this->t('URL de Instagram'),
      'youtube' => $this->t('URL de YouTube'),
    ] as $red => $titulo) {
      $form['cabecera']['red_' . $red] = [
        '#type' => 'textfield',
        '#title' => $titulo,
        '#description' => $this->t('Sin URL, el ícono no se muestra (cabecera y pie).'),
        '#default_value' => $redes[$red] ?? '',
        '#maxlength' => 255,
      ];
    }
    $form['cabecera']['franja_superior'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enlaces de la franja superior'),
      '#description' => $ayuda_enlaces,
      '#default_value' => $this->enlacesComoLineas($config->get('franja_superior')),
      '#rows' => 4,
    ];
    $form['cabecera']['accesibilidad_titulo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enlace de accesibilidad: título'),
      '#default_value' => $accesibilidad['titulo'] ?? '',
      '#maxlength' => 100,
    ];
    $form['cabecera']['accesibilidad_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enlace de accesibilidad: URL'),
      '#default_value' => $accesibilidad['url'] ?? '',
      '#maxlength' => 255,
    ];

    $ayuda_pares = $this->t('Una por línea, con el formato «Título | Texto». Lo vacío no se muestra.');
    $ayuda_datos = $this->t('Uno por línea, con el formato «Título | Valor | URL | Ícono». El valor, la URL y el ícono (mail, phone, message, clock, building o map-pin) son opcionales; la URL puede empezar por «mailto:», «tel:» o «https://».');

    $ubicanos = (array) ($pie['ubicanos'] ?? []);
    $horario = (array) ($ubicanos['horario'] ?? []);
    $form['pie_ubicanos'] = [
      '#type' => 'details',
      '#title' => $this->t('Pie: Ubícanos'),
      '#open' => TRUE,
    ];
    $form['pie_ubicanos']['pie_ubicanos_titulo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título del grupo'),
      '#default_value' => $ubicanos['titulo'] ?? '',
      '#maxlength' => 100,
    ];
    $form['pie_ubicanos']['pie_ubicanos_sedes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Sedes con su dirección'),
      '#description' => $ayuda_pares,
      '#default_value' => $this->paresComoLineas($ubicanos['sedes'] ?? NULL),
      '#rows' => 3,
    ];
    $form['pie_ubicanos']['pie_horario_titulo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Horario de atención: título'),
      '#default_value' => $horario['titulo'] ?? '',
      '#maxlength' => 150,
    ];
    $form['pie_ubicanos']['pie_horario_texto'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Horario de atención: horas'),
      '#default_value' => $horario['texto'] ?? '',
      '#maxlength' => 150,
    ];

    foreach ([
      'contacto' => $this->t('Pie: Contacto'),
      'lineas' => $this->t('Pie: Líneas de atención'),
    ] as $grupo => $titulo_grupo) {
      $datos = (array) ($pie[$grupo] ?? []);
      $form['pie_' . $grupo] = [
        '#type' => 'details',
        '#title' => $titulo_grupo,
        '#open' => TRUE,
      ];
      $form['pie_' . $grupo]['pie_' . $grupo . '_titulo'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Título del grupo'),
        '#default_value' => $datos['titulo'] ?? '',
        '#maxlength' => 100,
      ];
      $form['pie_' . $grupo]['pie_' . $grupo . '_items'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Datos'),
        '#description' => $ayuda_datos,
        '#default_value' => $this->datosComoLineas($datos['items'] ?? NULL),
        '#rows' => 5,
      ];
    }

    $politicas = (array) ($pie['politicas'] ?? []);
    $form['pie_otros'] = [
      '#type' => 'details',
      '#title' => $this->t('Pie: redes, políticas, logos y derechos'),
      '#open' => TRUE,
    ];
    $form['pie_otros']['pie_redes_titulo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título del grupo de redes (los íconos se editan arriba)'),
      '#default_value' => $pie['redes_titulo'] ?? '',
      '#maxlength' => 100,
    ];
    $form['pie_otros']['pie_politicas_titulo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Políticas: título del grupo'),
      '#default_value' => $politicas['titulo'] ?? '',
      '#maxlength' => 150,
    ];
    $form['pie_otros']['pie_politicas_enlaces'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Políticas: enlaces'),
      '#description' => $ayuda_enlaces,
      '#default_value' => $this->enlacesComoLineas($politicas['enlaces'] ?? NULL),
      '#rows' => 4,
    ];
    $form['pie_otros']['pie_logos'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Fila de logos'),
      '#description' => $this->t('Uno por línea, con el formato «Logo | Nombre accesible | URL». Logos disponibles: unillanos, mineducacion, colombia y govco. El orden es el de las líneas.'),
      '#default_value' => $this->logosComoLineas($pie['logos'] ?? NULL),
      '#rows' => 4,
    ];
    $derechos = (array) ($pie['derechos'] ?? []);
    $form['pie_otros']['pie_derechos_institucion'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Derechos: institución'),
      '#default_value' => $derechos['institucion'] ?? '',
      '#maxlength' => 150,
    ];
    $form['pie_otros']['pie_derechos_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Derechos: URL de la institución'),
      '#default_value' => $derechos['url'] ?? '',
      '#maxlength' => 255,
    ];
    $form['pie_otros']['pie_derechos_desde'] = [
      '#type' => 'number',
      '#title' => $this->t('Derechos: año de inicio'),
      '#description' => $this->t('El año final es el año actual y se calcula solo.'),
      '#default_value' => $derechos['desde'] ?? '',
      '#min' => 1800,
      '#max' => 2100,
    ];
    $form['pie_otros']['pie_derechos_texto'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Derechos: texto'),
      '#default_value' => $derechos['texto'] ?? '',
      '#maxlength' => 150,
    ];
    $form['pie_otros']['pie_derechos_nit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Derechos: NIT'),
      '#default_value' => $derechos['nit'] ?? '',
      '#maxlength' => 100,
    ];
    $portal = (array) ($config->get('portal') ?? []);
    $form['portal'] = [
      '#type' => 'details',
      '#title' => $this->t('Portal de inicio: texto, accesos y sitios de interés'),
      '#open' => TRUE,
    ];
    $form['portal']['portal_titulo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título de la portada'),
      '#default_value' => $portal['titulo'] ?? '',
      '#maxlength' => 120,
    ];
    $form['portal']['portal_texto'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Texto de bienvenida'),
      '#description' => $this->t('Separe los párrafos con una línea en blanco.'),
      '#default_value' => $portal['texto'] ?? '',
      '#rows' => 6,
    ];
    $form['portal']['portal_accesos'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Accesos'),
      '#description' => $this->t('Uno por línea, con el formato «Título | Descripción corta | URL». La URL es una ruta del sitio (empieza por «/») o una dirección https://. El primero es el acceso principal (el del SIAAI).'),
      '#default_value' => $this->cifrasComoLineas($portal['accesos'] ?? NULL),
      '#rows' => 6,
    ];
    $form['portal']['portal_sitios'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Sitios de interés'),
      '#description' => $this->t('Uno por línea, con el formato «Nombre | URL». La URL debe empezar por https://.'),
      '#default_value' => $this->enlacesComoLineas($portal['sitios'] ?? NULL),
      '#rows' => 6,
    ];
    $institucional = (array) ($config->get('institucional') ?? []);
    $cifras = (array) ($institucional['cifras'] ?? []);
    $form['institucional'] = [
      '#type' => 'details',
      '#title' => $this->t('Institucional: introducción y cifras'),
      '#open' => TRUE,
    ];
    $form['institucional']['institucional_introduccion'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Frase de introducción de la página «Institucional»'),
      '#default_value' => $institucional['introduccion'] ?? '',
      '#rows' => 2,
      '#maxlength' => 300,
    ];
    $form['institucional']['cifras_enlaces'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cifras: enlaces'),
      '#description' => $this->t('Uno por línea, con el formato «Título | Descripción corta | URL». La URL debe empezar por https://.'),
      '#default_value' => $this->cifrasComoLineas($cifras['enlaces'] ?? NULL),
      '#rows' => 5,
    ];
    $form['institucional']['cifras_tablero_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cifras: URL del tablero institucional'),
      '#description' => $this->t('Tablero externo (por ejemplo, de Tableau Public). Debe empezar por https://. Sin URL, no se muestra.'),
      '#default_value' => $cifras['tablero_url'] ?? '',
      '#maxlength' => 255,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   *   Formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $campos_url = [
      'url_web_principal' => $this->t('URL de la web principal de la Universidad'),
      'accesibilidad_url' => $this->t('Enlace de accesibilidad: URL'),
      'url_govco' => $this->t('URL del logo GOV.CO'),
      'pie_derechos_url' => $this->t('Derechos: URL de la institución'),
      'red_facebook' => $this->t('URL de Facebook'),
      'red_x' => $this->t('URL de X'),
      'red_instagram' => $this->t('URL de Instagram'),
      'red_youtube' => $this->t('URL de YouTube'),
    ];
    foreach ($campos_url as $campo => $titulo) {
      $valor = trim((string) $form_state->getValue($campo));
      if ($valor !== '' && $this->datosMarco->urlSegura($valor) === '') {
        $form_state->setErrorByName($campo, $this->t('@campo: la URL no es válida. Use una ruta que empiece por «/», «#», «https://», «mailto:» o «tel:».', ['@campo' => $titulo]));
      }
    }
    $this->lineasComoCifras((string) $form_state->getValue('portal_accesos'), 'portal_accesos', $form_state, TRUE);
    $this->lineasComoSitios((string) $form_state->getValue('portal_sitios'), 'portal_sitios', $form_state);
    $this->lineasComoCifras((string) $form_state->getValue('cifras_enlaces'), 'cifras_enlaces', $form_state);
    $tablero = trim((string) $form_state->getValue('cifras_tablero_url'));
    if ($tablero !== '' && !preg_match('~^https://[^\s/]+~i', $tablero)) {
      $form_state->setErrorByName('cifras_tablero_url', $this->t('Cifras: la URL del tablero debe empezar por https://.'));
    }
    foreach (['franja_superior', 'pie_politicas_enlaces'] as $campo) {
      $this->lineasComoEnlaces((string) $form_state->getValue($campo), $campo, $form_state);
    }
    foreach (['pie_contacto_items', 'pie_lineas_items'] as $campo) {
      $this->lineasComoDatos((string) $form_state->getValue($campo), $campo, $form_state);
    }
    $this->lineasComoPares((string) $form_state->getValue('pie_ubicanos_sedes'), 'pie_ubicanos_sedes', $form_state);
    $this->lineasComoLogos((string) $form_state->getValue('pie_logos'), 'pie_logos', $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   *   Formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $enlaces = fn(string $campo): array => $this->lineasComoEnlaces((string) $form_state->getValue($campo));
    $this->config(DatosMarco::CONFIG)
      ->set('nombre_linea1', trim((string) $form_state->getValue('nombre_linea1')))
      ->set('nombre_linea2', trim((string) $form_state->getValue('nombre_linea2')))
      ->set('url_web_principal', trim((string) $form_state->getValue('url_web_principal')))
      ->set('url_govco', trim((string) $form_state->getValue('url_govco')))
      ->set('redes_sociales', array_combine(DatosMarco::REDES, array_map(
        fn(string $red): string => trim((string) $form_state->getValue('red_' . $red)),
        DatosMarco::REDES,
      )))
      ->set('portal', [
        'titulo' => trim((string) $form_state->getValue('portal_titulo')),
        'texto' => trim((string) $form_state->getValue('portal_texto')),
        'accesos' => $this->lineasComoCifras((string) $form_state->getValue('portal_accesos'), NULL, NULL, TRUE),
        'sitios' => $this->lineasComoSitios((string) $form_state->getValue('portal_sitios')),
      ])
      ->set('institucional', [
        'introduccion' => trim((string) $form_state->getValue('institucional_introduccion')),
        'cifras' => [
          'enlaces' => $this->lineasComoCifras((string) $form_state->getValue('cifras_enlaces')),
          'tablero_url' => trim((string) $form_state->getValue('cifras_tablero_url')),
        ],
      ])
      ->set('franja_superior', $enlaces('franja_superior'))
      ->set('accesibilidad', [
        'titulo' => trim((string) $form_state->getValue('accesibilidad_titulo')),
        'url' => trim((string) $form_state->getValue('accesibilidad_url')),
      ])
      ->set('pie', [
        'ubicanos' => [
          'titulo' => trim((string) $form_state->getValue('pie_ubicanos_titulo')),
          'sedes' => $this->lineasComoPares((string) $form_state->getValue('pie_ubicanos_sedes')),
          'horario' => [
            'titulo' => trim((string) $form_state->getValue('pie_horario_titulo')),
            'texto' => trim((string) $form_state->getValue('pie_horario_texto')),
          ],
        ],
        'contacto' => [
          'titulo' => trim((string) $form_state->getValue('pie_contacto_titulo')),
          'items' => $this->lineasComoDatos((string) $form_state->getValue('pie_contacto_items')),
        ],
        'lineas' => [
          'titulo' => trim((string) $form_state->getValue('pie_lineas_titulo')),
          'items' => $this->lineasComoDatos((string) $form_state->getValue('pie_lineas_items')),
        ],
        'redes_titulo' => trim((string) $form_state->getValue('pie_redes_titulo')),
        'politicas' => [
          'titulo' => trim((string) $form_state->getValue('pie_politicas_titulo')),
          'enlaces' => $enlaces('pie_politicas_enlaces'),
        ],
        'logos' => $this->lineasComoLogos((string) $form_state->getValue('pie_logos')),
        'derechos' => [
          'institucion' => trim((string) $form_state->getValue('pie_derechos_institucion')),
          'url' => trim((string) $form_state->getValue('pie_derechos_url')),
          'desde' => (int) $form_state->getValue('pie_derechos_desde'),
          'texto' => trim((string) $form_state->getValue('pie_derechos_texto')),
          'nit' => trim((string) $form_state->getValue('pie_derechos_nit')),
        ],
      ])
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Convierte los enlaces de cifras guardados en líneas.
   *
   * Formato de cada línea: «Título | Descripción corta | URL».
   *
   * @param mixed $lista
   *   Valor de la configuración.
   *
   * @return string
   *   Una línea por enlace.
   */
  protected function cifrasComoLineas(mixed $lista): string {
    $lineas = [];
    foreach (is_array($lista) ? $lista : [] as $dato) {
      if (is_array($dato)) {
        $lineas[] = implode(' | ', [
          trim((string) ($dato['titulo'] ?? '')),
          trim((string) ($dato['texto'] ?? '')),
          trim((string) ($dato['url'] ?? '')),
        ]);
      }
    }
    return implode("\n", $lineas);
  }

  /**
   * Convierte líneas «Título | Descripción corta | URL» en enlaces de cifras.
   *
   * @param string $texto
   *   Contenido del campo.
   * @param string|null $campo
   *   Campo donde marcar los errores (si se indica con $form_state).
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   Estado del formulario donde marcar los errores.
   * @param bool $internas
   *   TRUE para aceptar también rutas del sitio (que empiezan por «/»).
   *
   * @return array<int, array{titulo: string, texto: string, url: string}>
   *   Los enlaces válidos.
   */
  protected function lineasComoCifras(string $texto, ?string $campo = NULL, ?FormStateInterface $form_state = NULL, bool $internas = FALSE): array {
    $enlaces = [];
    foreach (preg_split('/\R/u', $texto) ?: [] as $numero => $linea) {
      $linea = trim($linea);
      if ($linea === '') {
        continue;
      }
      [$titulo, $descripcion, $url] = array_pad(array_map('trim', explode('|', $linea, 3)), 3, '');
      $patron = $internas ? '~^(?:/(?![/\\\\\s])|https://[^\s/]+)~i' : '~^https://[^\s/]+~i';
      if ($titulo === '' || !preg_match($patron, $url)) {
        if ($campo !== NULL && $form_state !== NULL) {
          $form_state->setErrorByName($campo, $internas
            ? $this->t('Línea @numero: use «Título | Descripción corta | URL» con título y una URL que empiece por «/» o por https://.', ['@numero' => $numero + 1])
            : $this->t('Línea @numero: use «Título | Descripción corta | URL» con título y una URL que empiece por https://.', ['@numero' => $numero + 1]));
        }
        continue;
      }
      $enlaces[] = ['titulo' => $titulo, 'texto' => $descripcion, 'url' => $url];
    }
    return $enlaces;
  }

  /**
   * Convierte una lista de enlaces guardada en líneas «Título | URL».
   *
   * @param mixed $lista
   *   Valor de la configuración.
   *
   * @return string
   *   Una línea por enlace.
   */
  protected function enlacesComoLineas(mixed $lista): string {
    $lineas = [];
    foreach (is_array($lista) ? $lista : [] as $enlace) {
      if (is_array($enlace)) {
        $lineas[] = trim((string) ($enlace['titulo'] ?? '')) . ' | ' . trim((string) ($enlace['url'] ?? ''));
      }
    }
    return implode("\n", $lineas);
  }

  /**
   * Convierte pares título / texto guardados en líneas «Título | Texto».
   *
   * @param mixed $lista
   *   Valor de la configuración.
   *
   * @return string
   *   Una línea por par.
   */
  protected function paresComoLineas(mixed $lista): string {
    $lineas = [];
    foreach (is_array($lista) ? $lista : [] as $par) {
      if (is_array($par)) {
        $lineas[] = trim((string) ($par['titulo'] ?? '')) . ' | ' . trim((string) ($par['texto'] ?? ''));
      }
    }
    return implode("\n", $lineas);
  }

  /**
   * Convierte los datos de contacto guardados en líneas.
   *
   * Formato de cada línea: «Título | Valor | URL | Ícono».
   *
   * @param mixed $lista
   *   Valor de la configuración.
   *
   * @return string
   *   Una línea por dato.
   */
  protected function datosComoLineas(mixed $lista): string {
    $lineas = [];
    foreach (is_array($lista) ? $lista : [] as $dato) {
      if (is_array($dato)) {
        $lineas[] = implode(' | ', [
          trim((string) ($dato['titulo'] ?? '')),
          trim((string) ($dato['texto'] ?? '')),
          trim((string) ($dato['url'] ?? '')),
          trim((string) ($dato['icono'] ?? '')),
        ]);
      }
    }
    return implode("\n", $lineas);
  }

  /**
   * Convierte los logos guardados en líneas «Logo | Nombre | URL».
   *
   * @param mixed $lista
   *   Valor de la configuración.
   *
   * @return string
   *   Una línea por logo.
   */
  protected function logosComoLineas(mixed $lista): string {
    $lineas = [];
    foreach (is_array($lista) ? $lista : [] as $logo) {
      if (is_array($logo)) {
        $lineas[] = implode(' | ', [
          trim((string) ($logo['clave'] ?? '')),
          trim((string) ($logo['titulo'] ?? '')),
          trim((string) ($logo['url'] ?? '')),
        ]);
      }
    }
    return implode("\n", $lineas);
  }

  /**
   * Convierte líneas «Título | Texto» en pares.
   *
   * @param string $texto
   *   Contenido del campo.
   * @param string|null $campo
   *   Campo donde marcar los errores (si se indica con $form_state).
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   Estado del formulario donde marcar los errores.
   *
   * @return array<int, array{titulo: string, texto: string}>
   *   Los pares válidos (el título es obligatorio).
   */
  protected function lineasComoPares(string $texto, ?string $campo = NULL, ?FormStateInterface $form_state = NULL): array {
    $pares = [];
    foreach (preg_split('/\R/u', $texto) ?: [] as $numero => $linea) {
      $linea = trim($linea);
      if ($linea === '') {
        continue;
      }
      $partes = array_map('trim', explode('|', $linea, 2));
      if ($partes[0] === '') {
        if ($campo !== NULL && $form_state !== NULL) {
          $form_state->setErrorByName($campo, $this->t('Línea @numero: use el formato «Título | Texto» y no deje el título vacío.', ['@numero' => $numero + 1]));
        }
        continue;
      }
      $pares[] = ['titulo' => $partes[0], 'texto' => $partes[1] ?? ''];
    }
    return $pares;
  }

  /**
   * Convierte líneas «Título | Valor | URL | Ícono» en datos de contacto.
   *
   * @param string $texto
   *   Contenido del campo.
   * @param string|null $campo
   *   Campo donde marcar los errores (si se indica con $form_state).
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   Estado del formulario donde marcar los errores.
   *
   * @return array<int, array{titulo: string, texto: string, url: string, icono: string}>
   *   Los datos válidos.
   */
  protected function lineasComoDatos(string $texto, ?string $campo = NULL, ?FormStateInterface $form_state = NULL): array {
    $datos = [];
    foreach (preg_split('/\R/u', $texto) ?: [] as $numero => $linea) {
      $linea = trim($linea);
      if ($linea === '') {
        continue;
      }
      $partes = array_pad(array_map('trim', explode('|', $linea, 4)), 4, '');
      [$titulo, $valor, $url, $icono] = $partes;
      $mal = $titulo === '' || ($valor === '' && $url === '')
        || ($url !== '' && $this->datosMarco->urlSegura($url) === '')
        || ($icono !== '' && !in_array($icono, DatosMarco::ICONOS, TRUE));
      if ($mal) {
        if ($campo !== NULL && $form_state !== NULL) {
          $form_state->setErrorByName($campo, $this->t('Línea @numero: use «Título | Valor | URL | Ícono» con título, un valor o una URL válida y, si hay ícono, uno de la lista.', ['@numero' => $numero + 1]));
        }
        continue;
      }
      $datos[] = ['titulo' => $titulo, 'texto' => $valor, 'url' => $url, 'icono' => $icono];
    }
    return $datos;
  }

  /**
   * Convierte líneas «Logo | Nombre | URL» en la fila de logos.
   *
   * @param string $texto
   *   Contenido del campo.
   * @param string|null $campo
   *   Campo donde marcar los errores (si se indica con $form_state).
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   Estado del formulario donde marcar los errores.
   *
   * @return array<int, array{clave: string, titulo: string, url: string}>
   *   Los logos válidos.
   */
  protected function lineasComoLogos(string $texto, ?string $campo = NULL, ?FormStateInterface $form_state = NULL): array {
    $logos = [];
    foreach (preg_split('/\R/u', $texto) ?: [] as $numero => $linea) {
      $linea = trim($linea);
      if ($linea === '') {
        continue;
      }
      $partes = array_pad(array_map('trim', explode('|', $linea, 3)), 3, '');
      [$clave, $titulo, $url] = $partes;
      if (!in_array($clave, DatosMarco::LOGOS, TRUE) || $titulo === '' || $this->datosMarco->urlSegura($url) === '') {
        if ($campo !== NULL && $form_state !== NULL) {
          $form_state->setErrorByName($campo, $this->t('Línea @numero: use «Logo | Nombre accesible | URL» con un logo de la lista y una URL válida.', ['@numero' => $numero + 1]));
        }
        continue;
      }
      $logos[] = ['clave' => $clave, 'titulo' => $titulo, 'url' => $url];
    }
    return $logos;
  }

  /**
   * Convierte líneas «Título | URL» en enlaces.
   *
   * @param string $texto
   *   Contenido del campo.
   * @param string|null $campo
   *   Si se indica, los errores se marcan en ese campo; si no, las líneas
   *   inválidas se descartan.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   Estado del formulario donde marcar los errores.
   *
   * @return array<int, array{titulo: string, url: string}>
   *   Los enlaces válidos.
   */
  protected function lineasComoEnlaces(string $texto, ?string $campo = NULL, ?FormStateInterface $form_state = NULL): array {
    $enlaces = [];
    foreach (preg_split('/\R/u', $texto) ?: [] as $numero => $linea) {
      $linea = trim($linea);
      if ($linea === '') {
        continue;
      }
      $partes = array_map('trim', explode('|', $linea, 2));
      $titulo = $partes[0];
      $url = $partes[1] ?? '';
      if ($titulo === '' || $url === '' || $this->datosMarco->urlSegura($url) === '') {
        if ($campo !== NULL && $form_state !== NULL) {
          $form_state->setErrorByName($campo, $this->t('Línea @numero: use el formato «Título | URL» con una URL válida.', ['@numero' => $numero + 1]));
        }
        continue;
      }
      $enlaces[] = ['titulo' => $titulo, 'url' => $url];
    }
    return $enlaces;
  }

  /**
   * Convierte líneas «Nombre | URL» en sitios de interés (solo https).
   *
   * @param string $texto
   *   Contenido del campo.
   * @param string|null $campo
   *   Campo donde marcar los errores (si se indica con $form_state).
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   Estado del formulario donde marcar los errores.
   *
   * @return array<int, array{titulo: string, texto: string, url: string}>
   *   Los sitios válidos.
   */
  protected function lineasComoSitios(string $texto, ?string $campo = NULL, ?FormStateInterface $form_state = NULL): array {
    $sitios = [];
    foreach (preg_split('/\R/u', $texto) ?: [] as $numero => $linea) {
      $linea = trim($linea);
      if ($linea === '') {
        continue;
      }
      [$titulo, $url] = array_pad(array_map('trim', explode('|', $linea, 2)), 2, '');
      if ($titulo === '' || !preg_match('~^https://[^\s/]+~i', $url)) {
        if ($campo !== NULL && $form_state !== NULL) {
          $form_state->setErrorByName($campo, $this->t('Línea @numero: use «Nombre | URL» con una URL que empiece por https://.', ['@numero' => $numero + 1]));
        }
        continue;
      }
      $sitios[] = ['titulo' => $titulo, 'texto' => '', 'url' => $url];
    }
    return $sitios;
  }

  /**
   * Convierte líneas de texto en la lista de textos de la configuración.
   *
   * @param string $texto
   *   Contenido del campo.
   *
   * @return array<int, array{texto: string}>
   *   Los textos no vacíos.
   */
  protected function lineasComoTextos(string $texto): array {
    $textos = [];
    foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
      $linea = trim($linea);
      if ($linea !== '') {
        $textos[] = ['texto' => $linea];
      }
    }
    return $textos;
  }

}
