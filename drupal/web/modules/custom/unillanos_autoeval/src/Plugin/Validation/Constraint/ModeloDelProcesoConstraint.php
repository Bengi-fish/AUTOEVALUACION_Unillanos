<?php

namespace Drupal\unillanos_autoeval\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Los elementos de un proceso pertenecen al modelo de ese proceso.
 */
#[Constraint(
  id: 'UnillanosModeloDelProceso',
  label: new TranslatableMarkup('Elementos del modelo del proceso', [], ['context' => 'Validation']),
)]
class ModeloDelProcesoConstraint extends SymfonyConstraint {

  /**
   * Mensaje cuando el proceso declara un modelo que no es permitido.
   */
  public string $mensajeModelo = 'El modelo del proceso debe ser uno de los siguientes: %modelos.';

  /**
   * Mensaje cuando se cambia el modelo de un proceso que ya tiene datos.
   */
  public string $mensajeCambio = 'No se puede cambiar el modelo a %modelo: el proceso ya tiene elementos de otro modelo (%tipo %id).';

  /**
   * Mensaje cuando el elemento es de otro modelo.
   */
  public string $mensajeElemento = 'El elemento «%elemento» pertenece al modelo %modelo_elemento, pero el proceso «%proceso» usa el modelo %modelo_proceso. Elija un elemento del modelo del proceso.';

  /**
   * Mensaje cuando un hallazgo de una meta es de otro modelo.
   */
  public string $mensajeHallazgo = 'El hallazgo «%hallazgo» atiende el elemento «%elemento» del modelo %modelo_elemento, pero el proceso del plan usa el modelo %modelo_proceso.';

}
