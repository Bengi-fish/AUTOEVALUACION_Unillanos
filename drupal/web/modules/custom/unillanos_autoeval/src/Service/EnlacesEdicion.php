<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Service;

use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Arma los enlaces del modo edición (lápices y «+») del sitio público.
 *
 * Solo entrega un enlace si la persona tiene el permiso «access acreditacion
 * panel» y acceso real a la pantalla de destino (la comprobación de acceso de
 * la ruta, que a su vez revisa el acceso a la entidad). Cada enlace vuelve a la
 * página actual al guardar (`?destination=`). Quien llama debe añadir el
 * contexto de caché `user.permissions` a lo que pinta. No cambia el modelo de
 * datos: usa solo pantallas que ya existen.
 */
class EnlacesEdicion {

  use StringTranslationTrait;

  /**
   * Permiso existente que habilita el modo edición.
   */
  const PERMISO = 'access acreditacion panel';

  /**
   * Constructor.
   */
  public function __construct(
    protected AccountInterface $usuario,
    protected RedirectDestinationInterface $destino,
  ) {}

  /**
   * Indica si la persona actual puede usar el modo edición.
   *
   * @return bool
   *   TRUE con el permiso «access acreditacion panel».
   */
  public function activo(): bool {
    return $this->usuario->hasPermission(self::PERMISO);
  }

  /**
   * Arma un enlace de edición si hay acceso a su destino.
   *
   * @param string $ruta
   *   Nombre de la ruta de destino.
   * @param array<string, mixed> $parametros
   *   Parámetros de la ruta.
   * @param string $titulo
   *   Nombre accesible del enlace.
   * @param string $tipo
   *   Variante: `lapiz` (editar) o `mas` (añadir).
   * @param array<string, string> $consulta
   *   Parámetros de la URL, por ejemplo la relación que se precarga.
   *
   * @return array{url: string, title: string, tipo: string}|null
   *   El enlace, o NULL si la persona no puede usar el modo edición o no tiene
   *   acceso a la pantalla.
   */
  public function enlace(string $ruta, array $parametros, string $titulo, string $tipo = 'lapiz', array $consulta = []): ?array {
    if (!$this->activo()) {
      return NULL;
    }
    $consulta['destination'] = $this->destino->get();
    $url = Url::fromRoute($ruta, $parametros, ['query' => $consulta]);
    if (!$url->access($this->usuario)) {
      return NULL;
    }
    return ['url' => $url->toString(), 'title' => $titulo, 'tipo' => $tipo];
  }

  /**
   * Lápiz de la ficha simple de un programa.
   *
   * @param int $id
   *   Identificador del programa.
   * @param string $nombre
   *   Nombre del programa.
   *
   * @return array{url: string, title: string, tipo: string}|null
   *   El enlace o NULL.
   */
  public function ficha(int $id, string $nombre): ?array {
    return $this->enlace('unillanos_autoeval.acreditacion_ficha', ['node' => $id], (string) $this->t('Editar la ficha de @programa', ['@programa' => $nombre]));
  }

  /**
   * Botón «Cambiar foto» (o «Añadir foto» si el programa no tiene).
   *
   * @param int $id
   *   Identificador del programa.
   * @param bool $con_foto
   *   TRUE si el programa ya tiene imagen de cabecera.
   *
   * @return array{url: string, title: string, tipo: string}|null
   *   El enlace o NULL.
   */
  public function foto(int $id, bool $con_foto): ?array {
    $titulo = $con_foto ? (string) $this->t('Cambiar foto') : (string) $this->t('Añadir foto');
    return $this->enlace('unillanos_autoeval.acreditacion_foto', ['node' => $id], $titulo, $con_foto ? 'lapiz' : 'mas');
  }

  /**
   * «+ Añadir programa».
   *
   * @return array{url: string, title: string, tipo: string}|null
   *   El enlace o NULL.
   */
  public function programaNuevo(): ?array {
    return $this->enlace('node.add', ['node_type' => 'programa'], (string) $this->t('Añadir programa'), 'mas');
  }

  /**
   * Lápiz de un contenido (valoración, plan o meta) en su formulario.
   *
   * @param int $id
   *   Identificador del contenido.
   * @param string $titulo
   *   Nombre accesible del enlace.
   *
   * @return array{url: string, title: string, tipo: string}|null
   *   El enlace o NULL.
   */
  public function contenido(int $id, string $titulo): ?array {
    return $this->enlace('entity.node.edit_form', ['node' => $id], $titulo);
  }

  /**
   * «+» para crear la valoración de un factor.
   *
   * Precarga el proceso y el factor (los campos `field_proceso` y
   * `field_elemento` ya existen).
   *
   * @param int $proceso
   *   Identificador del proceso.
   * @param int $factor
   *   Identificador del factor (término del modelo).
   * @param string $titulo
   *   Nombre accesible del enlace.
   *
   * @return array{url: string, title: string, tipo: string}|null
   *   El enlace o NULL.
   */
  public function valoracionNueva(int $proceso, int $factor, string $titulo): ?array {
    return $this->enlace('node.add', ['node_type' => 'valoracion'], $titulo, 'mas', [
      'proceso' => (string) $proceso,
      'elemento' => (string) $factor,
    ]);
  }

  /**
   * «+» para añadir un documento con el proceso precargado.
   *
   * @param int $proceso
   *   Identificador del proceso (0 si no hay).
   *
   * @return array{url: string, title: string, tipo: string}|null
   *   El enlace o NULL.
   */
  public function documentoNuevo(int $proceso): ?array {
    return $this->enlace('unillanos_autoeval.acreditacion_documento_nuevo', [], (string) $this->t('Añadir un documento'), 'mas', $proceso > 0 ? ['proceso' => (string) $proceso] : []);
  }

  /**
   * Añade los enlaces de edición a los datos de la página de un programa.
   *
   * Solo agrega (nunca quita ni cambia) claves: `edicion` en cada pestaña de
   * factor y en cada fila del plan, y devuelve aparte los enlaces de la ficha,
   * del plan y de «añadir documento». Sin el permiso, o sin acceso a ninguna
   * pantalla, devuelve los datos tal cual y listas vacías.
   *
   * @param int $programa
   *   Identificador del programa.
   * @param string $nombre
   *   Nombre del programa.
   * @param array<string, mixed> $factores
   *   Datos de los factores (ver FactoresPrograma).
   * @param array<string, mixed> $plan
   *   Datos del plan (ver PlanMejoramientoPrograma).
   * @param bool $con_foto
   *   TRUE si el programa ya tiene imagen de cabecera.
   *
   * @return array{factores: array<string, mixed>, plan: array<string, mixed>, edicion: array<string, mixed>}
   *   Los datos con `edicion` y, en `edicion`: ficha, plan y documento.
   */
  public function paraPrograma(int $programa, string $nombre, array $factores, array $plan, bool $con_foto = TRUE): array {
    $vacio = ['factores' => $factores, 'plan' => $plan, 'edicion' => []];
    if (!$this->activo()) {
      return $vacio;
    }
    $proceso = (int) ($factores['proceso_id'] ?? 0);
    foreach ($factores['pestanas'] ?? [] as $indice => $pestana) {
      $factor = (int) ($pestana['factor_id'] ?? 0);
      if (!$factor) {
        continue;
      }
      $nombre_factor = trim(($pestana['label'] ?? '') . ': ' . $pestana['name'], ': ');
      $enlace = !empty($pestana['valoracion_id'])
        ? $this->contenido((int) $pestana['valoracion_id'], (string) $this->t('Editar la valoración del @factor', ['@factor' => $nombre_factor]))
        : ($proceso ? $this->valoracionNueva($proceso, $factor, (string) $this->t('Añadir la valoración del @factor', ['@factor' => $nombre_factor])) : NULL);
      if ($enlace) {
        $factores['pestanas'][$indice]['edicion'] = [$enlace];
      }
    }
    foreach ($plan['filas'] ?? [] as $indice => $fila) {
      if (empty($fila['meta_id'])) {
        continue;
      }
      $meta = trim((string) ($fila['cells'][1]['eyebrow'] ?? '')) ?: (string) $this->t('la meta');
      $enlace = $this->contenido((int) $fila['meta_id'], (string) $this->t('Editar @meta del plan de mejoramiento', ['@meta' => $meta]));
      if ($enlace) {
        $plan['filas'][$indice]['edicion'] = [$enlace];
      }
    }
    $edicion = [
      'ficha' => $this->ficha($programa, $nombre),
      'foto' => $this->foto($programa, $con_foto),
      'plan' => !empty($plan['plan_id']) ? $this->contenido((int) $plan['plan_id'], (string) $this->t('Editar el plan de mejoramiento')) : NULL,
      'documento' => $this->documentoNuevo($proceso),
    ];
    return ['factores' => $factores, 'plan' => $plan, 'edicion' => array_filter($edicion)];
  }

  /**
   * Lápiz del formulario del micrositio (cabecera, franja y pie).
   *
   * Se muestra solo con el permiso propio de esa pantalla.
   *
   * @return array{url: string, title: string, tipo: string}|null
   *   El enlace o NULL.
   */
  public function micrositio(): ?array {
    return $this->enlace('unillanos_autoeval.micrositio', [], (string) $this->t('Editar la cabecera y el pie'));
  }

  /**
   * Botón «Panel avanzado» de la barra del modo edición.
   *
   * Lleva al panel de Acreditación (`/admin/acreditacion`); es una página
   * completa de Drupal, no el panel lateral, y no lleva `destination`.
   *
   * @return array{url: string, title: string}|null
   *   El enlace, o NULL si la persona no tiene acceso al panel.
   */
  public function panelAvanzado(): ?array {
    if (!$this->activo()) {
      return NULL;
    }
    $url = Url::fromRoute('unillanos_autoeval.acreditacion');
    if (!$url->access($this->usuario)) {
      return NULL;
    }
    return ['url' => $url->toString(), 'title' => (string) $this->t('Panel avanzado')];
  }

  /**
   * Dirección de «Cerrar sesión» (la pantalla de confirmación de Drupal).
   *
   * @return string|null
   *   La URL, o NULL si la persona no puede usar el modo edición.
   */
  public function cerrarSesion(): ?string {
    return $this->activo() ? Url::fromRoute('user.logout.confirm')->toString() : NULL;
  }

}
