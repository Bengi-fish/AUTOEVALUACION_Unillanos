<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\unillanos_autoeval\Traits\EtiquetasYOrdenTrait;
use Drupal\unillanos_autoeval\Traits\FormatoTextoTrait;

/**
 * Arma lo que muestra la página de un programa.
 *
 * Arma la cabecera y la ficha (datosPrograma). Decide la bajada de la
 * cabecera según las secciones que existen y, cuando el programa no tiene
 * nada de autoevaluación (ni factores, ni plan, ni documentos), entrega los
 * demás programas de su facultad y los enlaces al listado y a los documentos.
 * Solo usa datos guardados y no calcula nada.
 */
class PaginaPrograma {

  use StringTranslationTrait;
  use EtiquetasYOrdenTrait;
  use FormatoTextoTrait;

  /**
   * Constructor.
   */
  public function __construct(
    protected ListadoProgramas $listado,
    protected UrlGeneratorInterface $generadorUrl,
    protected AccessManagerInterface $accesos,
    protected AccountInterface $usuario,
  ) {}

  /**
   * Arma la bajada y, si corresponde, los relacionados y los enlaces.
   *
   * @param \Drupal\node\NodeInterface $programa
   *   Nodo `programa`.
   * @param array<string, mixed> $factores
   *   Resultado de factoresDelPrograma() (se mira `pestanas`).
   * @param array<string, mixed> $plan
   *   El plan del programa (se mira `filas`).
   * @param array<string, mixed> $documentos
   *   Los documentos del programa (se mira `items`).
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: lead (texto o vacío), otros (titulo, facultad y programas; vacío si
   *   no hay) y enlaces (lista de label y url; vacía si el programa tiene
   *   contenido de autoevaluación).
   */
  public function armar(NodeInterface $programa, array $factores, array $plan, array $documentos, CacheableMetadata $cache): array {
    $con_factores = !empty($factores['pestanas']);
    $con_plan = !empty($plan['filas']);
    $con_documentos = !empty($documentos['items']);
    $resultado = [
      'lead' => $this->lead($programa, $con_factores, $con_plan, $con_documentos),
      'otros' => [],
      'enlaces' => [],
    ];
    if (!$con_factores && !$con_plan && !$con_documentos) {
      $resultado['otros'] = $this->otros($programa, $cache);
      $resultado['enlaces'] = $this->enlaces($cache);
    }
    return $resultado;
  }

  /**
   * Escribe la bajada de la cabecera con las secciones que existen.
   *
   * Con factores se conserva la frase de siempre. Sin ellos se nombran solo
   * las secciones que hay (registro calificado, plan de mejoramiento y
   * documentos); si no hay ninguna, no hay bajada.
   *
   * @param \Drupal\node\NodeInterface $programa
   *   Nodo `programa`.
   * @param bool $con_factores
   *   El programa tiene factores de autoevaluación.
   * @param bool $con_plan
   *   El programa tiene plan de mejoramiento.
   * @param bool $con_documentos
   *   El programa tiene documentos.
   *
   * @return string
   *   La bajada, o una cadena vacía.
   */
  public function lead(NodeInterface $programa, bool $con_factores, bool $con_plan, bool $con_documentos): string {
    if ($con_factores) {
      return (string) $this->t('Registro calificado, autoevaluación y plan de mejoramiento del programa, en un solo lugar.');
    }
    $secciones = [];
    if (!$programa->get('field_resolucion_registro')->isEmpty() || !$programa->get('field_registro_hasta')->isEmpty()) {
      $secciones[] = (string) $this->t('registro calificado');
    }
    if ($con_plan) {
      $secciones[] = (string) $this->t('plan de mejoramiento');
    }
    if ($con_documentos) {
      $secciones[] = (string) $this->t('documentos');
    }
    if (!$secciones) {
      return '';
    }
    $ultima = array_pop($secciones);
    $lista = $secciones ? implode(', ', $secciones) . ' ' . $this->t('y') . ' ' . $ultima : $ultima;
    return (string) $this->t('@secciones del programa, en un solo lugar.', [
      '@secciones' => mb_strtoupper(mb_substr($lista, 0, 1)) . mb_substr($lista, 1),
    ]);
  }

  /**
   * Devuelve los demás programas de la facultad con su título.
   *
   * @param \Drupal\node\NodeInterface $programa
   *   El programa actual: no se incluye.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de lo que se lee.
   *
   * @return array<string, mixed>
   *   Con titulo (concuerda con la cantidad), facultad y programas (con la
   *   forma de ListadoProgramas::otrosDeLaFacultad()). Vacío si no hay
   *   ninguno o el programa no tiene facultad.
   */
  public function otros(NodeInterface $programa, CacheableMetadata $cache): array {
    $otros = $this->listado->otrosDeLaFacultad($programa, $cache);
    if (!$otros) {
      return [];
    }
    $facultad = $programa->get('field_facultad')->entity;
    if ($facultad) {
      $cache->addCacheableDependency($facultad);
    }
    return [
      'titulo' => (string) $this->formatPlural(
        count($otros),
        'Otro programa de la facultad',
        'Otros programas de la facultad',
      ),
      'facultad' => trim((string) $facultad?->label()),
      'programas' => $otros,
    ];
  }

  /**
   * Devuelve los enlaces al listado de programas y a los documentos.
   *
   * Cada uno solo se incluye si el visitante puede entrar a la ruta.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché del acceso y de las URL.
   *
   * @return array<int, array{label: string, url: string}>
   *   Etiqueta y URL de cada enlace.
   */
  public function enlaces(CacheableMetadata $cache): array {
    $enlaces = [];
    $rutas = [
      'unillanos_autoeval.programas' => (string) $this->t('Ver todos los programas'),
      'unillanos_autoeval.documentos' => (string) $this->t('Ver documentos'),
    ];
    foreach ($rutas as $ruta => $etiqueta) {
      $acceso = $this->accesos->checkNamedRoute($ruta, [], $this->usuario, TRUE);
      $cache->addCacheableDependency($acceso);
      if (!$acceso->isAllowed()) {
        continue;
      }
      $url = $this->generadorUrl->generateFromRoute($ruta, [], [], TRUE);
      $cache->addCacheableDependency($url);
      $enlaces[] = ['label' => $etiqueta, 'url' => $url->getGeneratedUrl()];
    }
    return $enlaces;
  }

  /**
   * Arma los datos de la cabecera y la ficha de un programa.
   *
   * Solo incluye lo que tiene valor: un dato vacío no se devuelve, para que
   * la plantilla no deje huecos. La facultad va en `eyebrow` (como en el
   * prototipo) y no se repite en la ficha.
   *
   * @param \Drupal\node\NodeInterface $programa
   *   Nodo `programa`.
   *
   * @return array<string, mixed>
   *   Con: nombre, nombre_inicio (primera palabra), nombre_acento (resto),
   *   eyebrow (facultad y sede), etiquetas (nivel y modalidad) y ficha (lista
   *   de pares term/value).
   */
  public function datosPrograma(NodeInterface $programa): array {
    $nombre = trim((string) $programa->label());
    $palabras = preg_split('/\s+/', $nombre, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    // Como el prototipo: la primera palabra en negro y el resto en acento.
    // En nombres largos (más de cuatro palabras) solo las dos últimas.
    $corte = count($palabras) > 4 ? count($palabras) - 2 : min(1, count($palabras));
    $inicio = implode(' ', array_slice($palabras, 0, $corte));
    $acento = implode(' ', array_slice($palabras, $corte));
    $facultad = (string) $programa->get('field_facultad')->entity?->label();
    $sede = (string) $programa->get('field_sede')->entity?->label();
    $nivel = $this->etiquetaLista($programa->get('field_nivel'));
    $modalidad = $this->etiquetaLista($programa->get('field_modalidad'));
    $datos = [
      'Nivel' => $nivel,
      'Modalidad' => $modalidad,
      'Sede' => $sede,
      'Dirección del programa' => (string) $programa->get('field_director')->value,
      'Código SNIES' => (string) $programa->get('field_codigo_snies')->value,
      'Resolución de registro' => (string) $programa->get('field_resolucion_registro')->value,
      'Registro vigente hasta' => $this->fechaMesAnio($programa->get('field_registro_hasta')->value),
      'Resolución de acreditación' => (string) $programa->get('field_resolucion_acreditacion')->value,
      'Acreditación vigente hasta' => $this->fechaMesAnio($programa->get('field_acreditacion_hasta')->value),
    ];
    $ficha = [];
    foreach ($datos as $termino => $valor) {
      if (trim($valor) !== '') {
        $ficha[] = ['term' => $termino, 'value' => trim($valor)];
      }
    }
    return [
      'nombre' => $nombre,
      'nombre_inicio' => $inicio,
      'nombre_acento' => $acento,
      'eyebrow' => implode(' · ', array_filter([$facultad, $sede])),
      'etiquetas' => array_values(array_filter([$nivel, $modalidad])),
      'ficha' => $ficha,
    ];
  }

}
