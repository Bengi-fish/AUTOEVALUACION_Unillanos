<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Entrega a la cabecera, la franja superior y el pie lo que se muestra.
 *
 * Lee `unillanos_autoeval.micrositio` y devuelve solo lo que tiene valor: un
 * enlace sin título, o con una URL que no es segura, se descarta; una lista
 * vacía no se devuelve. Quien pinta (las plantillas) no decide nada.
 */
class DatosMarco {

  use StringTranslationTrait;

  /**
   * Nombre del objeto de configuración.
   */
  const CONFIG = 'unillanos_autoeval.micrositio';

  /**
   * Destinos admitidos: ruta de la raíz, ancla, consulta, http(s), mailto, tel.
   *
   * Es el mismo criterio que aplican en Twig los componentes del tema (una
   * sola barra, sin «\» ni espacios detrás).
   */
  const URL_SEGURA = '~^(?:/(?![/\\\\\s])|#|\?|https?://|mailto:|tel:)~i';

  /**
   * Redes sociales de la cabecera y del pie, en el orden en que se muestran.
   *
   * La clave es la de `redes_sociales` en la configuración y el nombre del
   * ícono autoalojado (images/redes/<clave>.svg).
   */
  const REDES = ['facebook', 'x', 'instagram', 'youtube'];

  /**
   * Constructor.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Arma los datos del marco de la página.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe las etiquetas de caché de la configuración.
   *
   * @return array<string, mixed>
   *   Claves: `nombre` (dos líneas), `web_principal`, `govco` (enlace del
   *   logo GOV.CO o NULL), `redes` (solo las que tienen URL segura: `red`,
   *   `title`, `url`), `franja` (enlaces),
   *   `accesibilidad` (enlace o NULL) y `pie` (`titulo`, `descripcion`,
   *   `institucion`, `nota_institucion`, `entidades`, `sedes`, `contacto`,
   *   `redes`, `enlaces_legales` y `legal`; las listas vacías, como arreglos
   *   vacíos).
   */
  public function datos(CacheableMetadata $cache): array {
    $config = $this->configFactory->get(self::CONFIG);
    $cache->addCacheableDependency($config);
    $pie = (array) ($config->get('pie') ?? []);
    return [
      'nombre' => [
        'linea1' => $this->texto($config->get('nombre_linea1')),
        'linea2' => $this->texto($config->get('nombre_linea2')),
      ],
      'web_principal' => $this->urlSegura($config->get('url_web_principal')),
      'govco' => $this->govco($config->get('url_govco')),
      'redes' => $this->redes($config->get('redes_sociales')),
      'franja' => $this->enlaces($config->get('franja_superior')),
      'accesibilidad' => $this->enlace((array) ($config->get('accesibilidad') ?? [])),
      'pie' => [
        'titulo' => $this->texto($pie['titulo'] ?? NULL),
        'descripcion' => $this->texto($pie['descripcion'] ?? NULL),
        'institucion' => $this->texto($pie['institucion'] ?? NULL),
        'nota_institucion' => $this->texto($pie['nota_institucion'] ?? NULL),
        'entidades' => $this->enlaces($pie['entidades'] ?? NULL),
        'sedes' => $this->textos($pie['sedes'] ?? NULL),
        'contacto' => $this->textos($pie['contacto'] ?? NULL),
        'redes' => $this->enlaces($pie['redes'] ?? NULL),
        'enlaces_legales' => $this->enlaces($pie['enlaces_legales'] ?? NULL),
        'legal' => $this->texto($pie['legal'] ?? NULL),
      ],
    ];
  }

  /**
   * Devuelve la URL si es segura, o una cadena vacía.
   *
   * @param mixed $url
   *   Valor de la configuración.
   *
   * @return string
   *   La URL sin espacios en los extremos, o ''.
   */
  public function urlSegura(mixed $url): string {
    if (!is_string($url)) {
      return '';
    }
    $url = trim($url);
    if ($url === '' || preg_match('/[\x00-\x20\x7F]/', $url) || !preg_match(self::URL_SEGURA, $url)) {
      return '';
    }
    return $url;
  }

  /**
   * Arma el enlace del logo GOV.CO.
   *
   * @param mixed $url
   *   Valor de la configuración.
   *
   * @return array{title: string, url: string}|null
   *   El enlace, o NULL si no hay URL segura.
   */
  protected function govco(mixed $url): ?array {
    $url = $this->urlSegura($url);
    if ($url === '') {
      return NULL;
    }
    return ['title' => (string) $this->t('GOV.CO, portal único del Estado colombiano'), 'url' => $url];
  }

  /**
   * Arma los íconos de redes que tienen una URL segura.
   *
   * @param mixed $redes
   *   Valor de `redes_sociales`: URL por red.
   *
   * @return array<int, array{red: string, title: string, url: string}>
   *   Las redes con URL, en el orden de ::REDES; sin URL no se devuelven.
   */
  protected function redes(mixed $redes): array {
    $redes = is_array($redes) ? $redes : [];
    $nombres = [
      'facebook' => $this->t('Facebook de la Universidad de los Llanos'),
      'x' => $this->t('X de la Universidad de los Llanos'),
      'instagram' => $this->t('Instagram de la Universidad de los Llanos'),
      'youtube' => $this->t('YouTube de la Universidad de los Llanos'),
    ];
    $lista = [];
    foreach (self::REDES as $red) {
      $url = $this->urlSegura($redes[$red] ?? NULL);
      if ($url !== '') {
        $lista[] = ['red' => $red, 'title' => (string) $nombres[$red], 'url' => $url];
      }
    }
    return $lista;
  }

  /**
   * Normaliza un texto: sin espacios sobrantes y con tope de largo.
   *
   * @param mixed $valor
   *   Valor de la configuración.
   *
   * @return string
   *   El texto en una línea, o ''.
   */
  protected function texto(mixed $valor): string {
    if (!is_string($valor)) {
      return '';
    }
    $texto = trim((string) preg_replace('/\s+/u', ' ', $valor));
    return mb_substr($texto, 0, 300);
  }

  /**
   * Convierte una lista de textos descartando los vacíos.
   *
   * @param mixed $lista
   *   Valor de la configuración: lista de arreglos con `texto`.
   *
   * @return array<int, string>
   *   Los textos no vacíos.
   */
  protected function textos(mixed $lista): array {
    $textos = [];
    foreach (is_array($lista) ? $lista : [] as $elemento) {
      $texto = $this->texto(is_array($elemento) ? ($elemento['texto'] ?? NULL) : NULL);
      if ($texto !== '') {
        $textos[] = $texto;
      }
    }
    return $textos;
  }

  /**
   * Convierte una lista de enlaces descartando los incompletos o inseguros.
   *
   * @param mixed $lista
   *   Valor de la configuración: lista de arreglos con `titulo` y `url`.
   *
   * @return array<int, array{title: string, url: string, externo: bool}>
   *   Los enlaces válidos, en el orden de la configuración.
   */
  protected function enlaces(mixed $lista): array {
    $enlaces = [];
    foreach (is_array($lista) ? $lista : [] as $elemento) {
      $enlace = $this->enlace(is_array($elemento) ? $elemento : []);
      if ($enlace !== NULL) {
        $enlaces[] = $enlace;
      }
    }
    return $enlaces;
  }

  /**
   * Convierte un enlace; NULL si falta el título o la URL no es segura.
   *
   * @param array<string, mixed> $datos
   *   Arreglo con `titulo` y `url`.
   *
   * @return array{title: string, url: string, externo: bool}|null
   *   El enlace, o NULL.
   */
  protected function enlace(array $datos): ?array {
    $titulo = $this->texto($datos['titulo'] ?? NULL);
    $url = $this->urlSegura($datos['url'] ?? NULL);
    if ($titulo === '' || $url === '') {
      return NULL;
    }
    return [
      'title' => $titulo,
      'url' => $url,
      'externo' => (bool) preg_match('~^https?://~i', $url),
    ];
  }

}
