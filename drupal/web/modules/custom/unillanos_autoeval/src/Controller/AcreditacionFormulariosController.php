<?php

declare(strict_types=1);

namespace Drupal\unillanos_autoeval\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Formularios simples de Acreditación: la ficha del programa y los documentos.
 *
 * Usan el modo de formulario «acreditacion» (solo los campos que hacen falta,
 * en orden claro y con ayudas en lenguaje sencillo). El control de acceso es
 * el de editar o crear, que exigen las rutas (`_entity_access` y
 * `_entity_create_access`); aquí solo se comprueba que el contenido sea del
 * tipo correcto. El formulario completo de Drupal no cambia.
 */
final class AcreditacionFormulariosController extends ControllerBase {

  /**
   * Formulario simple de la ficha de un programa.
   *
   * @param \Drupal\node\NodeInterface $node
   *   El programa.
   *
   * @return array<string, mixed>
   *   Render array con el formulario.
   */
  public function ficha(NodeInterface $node): array {
    if ($node->bundle() !== 'programa') {
      throw new NotFoundHttpException();
    }
    return [
      '#title' => $this->t('Ficha: @programa', ['@programa' => $node->label()]),
      'formulario' => $this->entityFormBuilder()->getForm($node, 'acreditacion'),
    ];
  }

  /**
   * Formulario simple para editar un documento.
   *
   * @param \Drupal\media\MediaInterface $media
   *   El documento.
   *
   * @return array<string, mixed>
   *   Render array con el formulario.
   */
  public function documento(MediaInterface $media): array {
    if ($media->bundle() !== 'documento') {
      throw new NotFoundHttpException();
    }
    return [
      '#title' => $this->t('Documento: @titulo', ['@titulo' => $media->label()]),
      'formulario' => $this->entityFormBuilder()->getForm($media, 'acreditacion'),
    ];
  }

  /**
   * Formulario simple para subir un documento nuevo.
   *
   * Con `?proceso=ID` el proceso ya viene elegido (ver
   * UnillanosAutoevalHooks::entityPrepareForm()).
   *
   * @return array<string, mixed>
   *   Render array con el formulario.
   */
  public function documentoNuevo(): array {
    $documento = $this->entityTypeManager()->getStorage('media')->create(['bundle' => 'documento']);
    return [
      '#title' => $this->t('Subir un documento'),
      'formulario' => $this->entityFormBuilder()->getForm($documento, 'acreditacion'),
    ];
  }

}
