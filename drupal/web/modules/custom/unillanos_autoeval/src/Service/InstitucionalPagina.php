<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Datos de /institucional: la Universidad con la interfaz de un programa.
 *
 * Reúne lo que ya arman los servicios de «Institucional»: el proceso
 * institucional (factores, plan y documentos, como la página de un programa) y
 * sus secciones (condiciones por sede, dependencias, directivos, normatividad
 * y cifras). Solo lee valores guardados; lo vacío se omite, y cada sección que
 * no tiene contenido ni forma de añadirlo no aparece ni en la página ni en su
 * navegación interna.
 */
class InstitucionalPagina {

  use StringTranslationTrait;

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
   * Arma los datos de la página.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Recibe los metadatos de caché de todo lo que se lee.
   *
   * @return array<string, mixed>
   *   Con: intro, proceso (título del proceso institucional), etiquetas
   *   (periodo y estado del proceso), ficha (pares
   *   term/value), procesos (principal y condiciones), dependencias,
   *   directivos, normas, cifras, secciones (title y url de cada sección con
   *   contenido), edicion y vacio (TRUE si no hay nada que mostrar).
   */
  public function datos(CacheableMetadata $cache): array {
    $portada = $this->portada->datos($cache);
    $procesos = $this->procesos->datos($cache);
    $dependencias = $this->dependencias->datos($cache);
    $directivos = $this->directivos->datos($cache);
    $normas = $this->normas->datos($cache);
    $cifras = $this->cifras->datos($cache);
    $principal = $procesos['principal'] ?? NULL;
    $hay = [
      'autoevaluacion' => !empty($principal['factores']['pestanas']),
      'plan' => !empty($principal['plan']['filas']),
      'documentos' => !empty($principal['documentos']),
      'condiciones' => !empty($procesos['condiciones']),
      'dependencias' => empty($dependencias['vacio']) || !empty($dependencias['edicion']['nueva']),
      'directivos' => empty($directivos['vacio']) || $this->hayEdicion($directivos),
      'normatividad' => empty($normas['vacio']) || !empty($normas['edicion']['nueva']),
      'cifras' => !empty($cifras['enlaces']) || !empty($cifras['tablero']) || !empty($cifras['edicion']),
    ];
    $titulos = [
      'autoevaluacion' => $this->t('Factores y valoración'),
      'plan' => $this->t('Plan de mejoramiento'),
      'documentos' => $this->t('Documentos'),
      'condiciones' => $this->t('Condiciones por sede'),
      'dependencias' => $this->t('Dependencias'),
      'directivos' => $this->t('Directivos'),
      'normatividad' => $this->t('Normatividad'),
      'cifras' => $this->t('Cifras'),
    ];
    $secciones = [];
    foreach ($titulos as $clave => $titulo) {
      if ($hay[$clave]) {
        $secciones[] = ['title' => (string) $titulo, 'url' => '#' . $this->ancla($clave)];
      }
    }
    return [
      'intro' => $portada['intro'],
      'proceso' => $principal ? trim((string) $principal['titulo']) : '',
      'etiquetas' => $principal ? array_values(array_filter([$principal['periodo'], $principal['estado']])) : [],
      'ficha' => $principal ? $this->ficha($principal) : [],
      'procesos' => $procesos,
      'dependencias' => $dependencias,
      'directivos' => $directivos,
      'normas' => $normas,
      'cifras' => $cifras,
      'secciones' => $secciones,
      'hay' => $hay,
      'edicion' => $portada['edicion'],
      'vacio' => !in_array(TRUE, $hay, TRUE),
    ];
  }

  /**
   * El ancla de una sección (la misma de la página y de la navegación).
   *
   * @param string $clave
   *   Clave de la sección.
   *
   * @return string
   *   Identificador HTML.
   */
  public function ancla(string $clave): string {
    return match ($clave) {
      'autoevaluacion' => 'autoevaluacion',
      'condiciones' => 'procesos',
      default => $clave,
    };
  }

  /**
   * Pares término / valor de la ficha del proceso institucional.
   *
   * @param array<string, mixed> $principal
   *   El proceso principal (InstitucionalProcesos).
   *
   * @return array<int, array{term: string, value: string}>
   *   Solo los datos que existen.
   */
  protected function ficha(array $principal): array {
    $datos = [
      (string) $this->t('Proceso') => (string) ($principal['titulo'] ?? ''),
      (string) $this->t('Periodo evaluado') => (string) ($principal['periodo'] ?? ''),
      (string) $this->t('Estado') => (string) ($principal['estado'] ?? ''),
      (string) $this->t('Sede') => (string) ($principal['sede'] ?? ''),
      (string) $this->t('Resultado') => (string) ($principal['resultado'] ?? ''),
    ];
    $ficha = [];
    foreach ($datos as $termino => $valor) {
      if (trim($valor) !== '') {
        $ficha[] = ['term' => $termino, 'value' => trim($valor)];
      }
    }
    return $ficha;
  }

  /**
   * Indica si los directivos ofrecen algún acceso de edición.
   *
   * @param array<string, mixed> $directivos
   *   Datos de InstitucionalDirectivos.
   *
   * @return bool
   *   TRUE si alguna tarjeta tiene edición.
   */
  protected function hayEdicion(array $directivos): bool {
    foreach ((array) ($directivos['tarjetas'] ?? []) as $tarjeta) {
      if (!empty($tarjeta['edicion'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
