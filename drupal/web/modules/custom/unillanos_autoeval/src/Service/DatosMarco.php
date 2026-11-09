<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Component\Datetime\TimeInterface;
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
   * Logos de la fila del pie que admite el tema (clave del archivo).
   */
  const LOGOS = ['unillanos', 'mineducacion', 'colombia', 'govco'];

  /**
   * Íconos que puede llevar un dato de contacto (nombres de unillanos:icon).
   */
  const ICONOS = ['mail', 'phone', 'message', 'clock', 'building', 'map-pin'];

  /**
   * Constructor.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
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
      'pie' => $this->pie($pie, $cache),
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
   * Arma los grupos del pie: ubícanos, contacto, líneas, políticas y logos.
   *
   * @param array<string, mixed> $pie
   *   Valor de `pie` en la configuración.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe la vigencia del año de la línea de derechos.
   *
   * @return array<string, mixed>
   *   Claves: `ubicanos` (`titulo`, `sedes`, `horario`), `contacto` y
   *   `lineas` (`titulo`, `items` con `title`, `text`, `url`, `icono`),
   *   `redes_titulo`, `politicas` (`titulo`, `enlaces`), `logos` (`clave`,
   *   `title`, `url`) y `derechos` (`institucion`, `url`, `anios`, `texto`,
   *   `nit`). Lo vacío se devuelve vacío.
   */
  protected function pie(array $pie, CacheableMetadata $cache): array {
    $ubicanos = (array) ($pie['ubicanos'] ?? []);
    $horario = $this->tituloTexto((array) ($ubicanos['horario'] ?? []));
    return [
      'ubicanos' => [
        'titulo' => $this->texto($ubicanos['titulo'] ?? NULL),
        'sedes' => $this->titulosConTexto($ubicanos['sedes'] ?? NULL),
        'horario' => $horario,
      ],
      'contacto' => $this->grupoDeDatos((array) ($pie['contacto'] ?? [])),
      'lineas' => $this->grupoDeDatos((array) ($pie['lineas'] ?? [])),
      'redes_titulo' => $this->texto($pie['redes_titulo'] ?? NULL),
      'politicas' => [
        'titulo' => $this->texto(((array) ($pie['politicas'] ?? []))['titulo'] ?? NULL),
        'enlaces' => $this->enlaces(((array) ($pie['politicas'] ?? []))['enlaces'] ?? NULL),
      ],
      'logos' => $this->logos($pie['logos'] ?? NULL),
      'derechos' => $this->derechos((array) ($pie['derechos'] ?? []), $cache),
    ];
  }

  /**
   * Convierte un par título / texto; NULL si falta el título.
   *
   * @param array<string, mixed> $datos
   *   Arreglo con `titulo` y `texto`.
   *
   * @return array{title: string, text: string}|null
   *   El par, o NULL.
   */
  protected function tituloTexto(array $datos): ?array {
    $titulo = $this->texto($datos['titulo'] ?? NULL);
    if ($titulo === '') {
      return NULL;
    }
    return ['title' => $titulo, 'text' => $this->texto($datos['texto'] ?? NULL)];
  }

  /**
   * Convierte una lista de pares título / texto descartando los vacíos.
   *
   * @param mixed $lista
   *   Valor de la configuración.
   *
   * @return array<int, array{title: string, text: string}>
   *   Los pares con título.
   */
  protected function titulosConTexto(mixed $lista): array {
    $pares = [];
    foreach (is_array($lista) ? $lista : [] as $elemento) {
      $par = $this->tituloTexto(is_array($elemento) ? $elemento : []);
      if ($par !== NULL) {
        $pares[] = $par;
      }
    }
    return $pares;
  }

  /**
   * Arma un grupo de datos de contacto (título e ítems).
   *
   * Un ítem lleva título; el valor y la URL son opcionales (la URL se
   * descarta si no es segura, y el ítem queda como texto). Sin título o sin
   * valor ni URL, se omite.
   *
   * @param array<string, mixed> $grupo
   *   Valor de la configuración del grupo.
   *
   * @return array{titulo: string, items: array<int, array{title: string, text: string, url: string, icono: string}>}
   *   El grupo.
   */
  protected function grupoDeDatos(array $grupo): array {
    $items = [];
    foreach (is_array($grupo['items'] ?? NULL) ? $grupo['items'] : [] as $dato) {
      $dato = is_array($dato) ? $dato : [];
      $titulo = $this->texto($dato['titulo'] ?? NULL);
      $texto = $this->texto($dato['texto'] ?? NULL);
      $url = $this->urlSegura($dato['url'] ?? NULL);
      if ($titulo === '' || ($texto === '' && $url === '')) {
        continue;
      }
      $icono = is_string($dato['icono'] ?? NULL) ? trim($dato['icono']) : '';
      $items[] = [
        'title' => $titulo,
        'text' => $texto,
        'url' => $url,
        'icono' => in_array($icono, self::ICONOS, TRUE) ? $icono : '',
      ];
    }
    return ['titulo' => $this->texto($grupo['titulo'] ?? NULL), 'items' => $items];
  }

  /**
   * Arma la fila de logos del pie: solo los conocidos con URL segura.
   *
   * @param mixed $lista
   *   Valor de la configuración: lista con `clave`, `titulo` y `url`.
   *
   * @return array<int, array{clave: string, title: string, url: string}>
   *   Los logos válidos, en el orden de la configuración.
   */
  protected function logos(mixed $lista): array {
    $logos = [];
    foreach (is_array($lista) ? $lista : [] as $logo) {
      $logo = is_array($logo) ? $logo : [];
      $clave = is_string($logo['clave'] ?? NULL) ? trim($logo['clave']) : '';
      $titulo = $this->texto($logo['titulo'] ?? NULL);
      $url = $this->urlSegura($logo['url'] ?? NULL);
      if (in_array($clave, self::LOGOS, TRUE) && $titulo !== '' && $url !== '') {
        $logos[] = ['clave' => $clave, 'title' => $titulo, 'url' => $url];
      }
    }
    return $logos;
  }

  /**
   * Arma la línea de derechos: «© Institución 1974 - <año actual> …».
   *
   * El año actual sale del reloj del sistema, no de la configuración. La
   * página se vuelve a generar al cambiar de año (vigencia de la caché).
   *
   * @param array<string, mixed> $datos
   *   Valor de `pie.derechos`.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe la vigencia hasta el 1 de enero siguiente.
   *
   * @return array<string, mixed>
   *   `institucion`, `url`, `anios` («1974 - 2026»; solo el año actual si no
   *   hay año de inicio), `texto` y `nit`; vacío si no hay institución.
   */
  protected function derechos(array $datos, CacheableMetadata $cache): array {
    $institucion = $this->texto($datos['institucion'] ?? NULL);
    if ($institucion === '') {
      return [];
    }
    $ahora = $this->time->getRequestTime();
    $actual = (int) date('Y', $ahora);
    $cache->mergeCacheMaxAge(max(1, (int) mktime(0, 0, 0, 1, 1, $actual + 1) - $ahora));
    $desde = (int) ($datos['desde'] ?? 0);
    return [
      'institucion' => $institucion,
      'url' => $this->urlSegura($datos['url'] ?? NULL),
      'anios' => ($desde > 0 && $desde < $actual) ? $desde . ' - ' . $actual : (string) $actual,
      'texto' => $this->texto($datos['texto'] ?? NULL),
      'nit' => $this->texto($datos['nit'] ?? NULL),
    ];
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
