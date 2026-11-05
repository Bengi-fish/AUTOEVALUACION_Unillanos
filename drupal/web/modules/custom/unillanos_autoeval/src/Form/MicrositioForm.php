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
 * Edita `unillanos_autoeval.micrositio`. Las listas de enlaces se escriben una
 * por línea («Título | URL») y las de textos, un texto por línea. Las URL se
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
    $ayuda_textos = $this->t('Un texto por línea. Lo vacío no se muestra.');

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

    $form['pie'] = [
      '#type' => 'details',
      '#title' => $this->t('Pie de página'),
      '#open' => TRUE,
    ];
    foreach ([
      'titulo' => $this->t('Nombre del sistema'),
      'institucion' => $this->t('Institución'),
      'nota_institucion' => $this->t('Nota de la institución'),
    ] as $clave => $titulo) {
      $form['pie']['pie_' . $clave] = [
        '#type' => 'textfield',
        '#title' => $titulo,
        '#default_value' => $pie[$clave] ?? '',
        '#maxlength' => 300,
      ];
    }
    $form['pie']['pie_descripcion'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Descripción'),
      '#default_value' => $pie['descripcion'] ?? '',
      '#rows' => 2,
    ];
    foreach ([
      'entidades' => $this->t('Entidades'),
      'redes' => $this->t('Redes sociales'),
      'enlaces_legales' => $this->t('Enlaces legales'),
    ] as $clave => $titulo) {
      $form['pie']['pie_' . $clave] = [
        '#type' => 'textarea',
        '#title' => $titulo,
        '#description' => $ayuda_enlaces,
        '#default_value' => $this->enlacesComoLineas($pie[$clave] ?? NULL),
        '#rows' => 3,
      ];
    }
    foreach ([
      'sedes' => $this->t('Sedes'),
      'contacto' => $this->t('Contacto'),
    ] as $clave => $titulo) {
      $form['pie']['pie_' . $clave] = [
        '#type' => 'textarea',
        '#title' => $titulo,
        '#description' => $ayuda_textos,
        '#default_value' => $this->textosComoLineas($pie[$clave] ?? NULL),
        '#rows' => 3,
      ];
    }
    $form['pie']['pie_legal'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Aviso legal'),
      '#default_value' => $pie['legal'] ?? '',
      '#rows' => 2,
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
    ];
    foreach ($campos_url as $campo => $titulo) {
      $valor = trim((string) $form_state->getValue($campo));
      if ($valor !== '' && $this->datosMarco->urlSegura($valor) === '') {
        $form_state->setErrorByName($campo, $this->t('@campo: la URL no es válida. Use una ruta que empiece por «/», «#», «https://», «mailto:» o «tel:».', ['@campo' => $titulo]));
      }
    }
    foreach (['franja_superior', 'pie_entidades', 'pie_redes', 'pie_enlaces_legales'] as $campo) {
      $this->lineasComoEnlaces((string) $form_state->getValue($campo), $campo, $form_state);
    }
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
    $textos = fn(string $campo): array => $this->lineasComoTextos((string) $form_state->getValue($campo));
    $this->config(DatosMarco::CONFIG)
      ->set('nombre_linea1', trim((string) $form_state->getValue('nombre_linea1')))
      ->set('nombre_linea2', trim((string) $form_state->getValue('nombre_linea2')))
      ->set('url_web_principal', trim((string) $form_state->getValue('url_web_principal')))
      ->set('franja_superior', $enlaces('franja_superior'))
      ->set('accesibilidad', [
        'titulo' => trim((string) $form_state->getValue('accesibilidad_titulo')),
        'url' => trim((string) $form_state->getValue('accesibilidad_url')),
      ])
      ->set('pie', [
        'titulo' => trim((string) $form_state->getValue('pie_titulo')),
        'descripcion' => trim((string) $form_state->getValue('pie_descripcion')),
        'institucion' => trim((string) $form_state->getValue('pie_institucion')),
        'nota_institucion' => trim((string) $form_state->getValue('pie_nota_institucion')),
        'entidades' => $enlaces('pie_entidades'),
        'sedes' => $textos('pie_sedes'),
        'contacto' => $textos('pie_contacto'),
        'redes' => $enlaces('pie_redes'),
        'enlaces_legales' => $enlaces('pie_enlaces_legales'),
        'legal' => trim((string) $form_state->getValue('pie_legal')),
      ])
      ->save();
    parent::submitForm($form, $form_state);
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
   * Convierte una lista de textos guardada en líneas.
   *
   * @param mixed $lista
   *   Valor de la configuración.
   *
   * @return string
   *   Un texto por línea.
   */
  protected function textosComoLineas(mixed $lista): string {
    $lineas = [];
    foreach (is_array($lista) ? $lista : [] as $elemento) {
      if (is_array($elemento) && isset($elemento['texto'])) {
        $lineas[] = (string) $elemento['texto'];
      }
    }
    return implode("\n", $lineas);
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
