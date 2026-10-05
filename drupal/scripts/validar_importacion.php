<?php

/**
 * @file
 * Valida las entidades importadas sin modificarlas ni guardarlas.
 *
 * Uso (desde la raíz del repositorio):
 *   ddev drush php:script drupal/scripts/validar_importacion.php.
 *
 * Las migraciones no validan al guardar; aquí se comprueban las restricciones
 * de unillanos_autoeval (valoración única, proceso con sede o programa, clave
 * única, estado por tipo, modelo del proceso) y las de los campos. Lista cada
 * violación con tipo, id, título, campo y mensaje.
 *
 * Es un ERROR que el proceso declare un modelo no permitido, o que el elemento
 * de una valoración o de un hallazgo (o los de los hallazgos de una meta) no
 * pertenezca al modelo de su proceso (restricción UnillanosModeloDelProceso).
 *
 * Además comprueba que cada programa tenga modalidad y sede (no son
 * obligatorias en el campo porque el catálogo aún no las trae). Es un ERROR
 * si el programa tiene datos importados (algún proceso) y un AVISO si no.
 */

$entidades = \Drupal::entityTypeManager();

// Qué se valida: tipo de entidad => bundles.
$objetivos = [
  'node' => ['proceso', 'programa', 'valoracion', 'hallazgo', 'plan_mejoramiento', 'meta', 'seguimiento'],
  'taxonomy_term' => ['elemento_modelo'],
];

$clave_bundle = [
  'node' => 'type',
  'taxonomy_term' => 'vid',
];

$revisadas = [];
$violaciones = [];
$avisos = [];

// Programas con datos importados: los que tienen algún proceso.
$con_datos = [];
foreach ($entidades->getStorage('node')->loadByProperties(['type' => 'proceso']) as $proceso) {
  $con_datos[$proceso->get('field_programa')->target_id] = TRUE;
}
// Datos que debe tener todo programa, aunque el campo no sea obligatorio.
$completitud = [
  'field_modalidad' => 'modalidad',
  'field_sede' => 'sede',
];

foreach ($objetivos as $tipo_entidad => $bundles) {
  $almacen = $entidades->getStorage($tipo_entidad);
  foreach ($bundles as $bundle) {
    $ids = $almacen->getQuery()
      ->accessCheck(FALSE)
      ->condition($clave_bundle[$tipo_entidad], $bundle)
      ->sort($almacen->getEntityType()->getKey('id'))
      ->execute();
    $revisadas[$bundle] = count($ids);
    // Se carga por tandas para no agotar la memoria.
    foreach (array_chunk($ids, 50) as $tanda) {
      foreach ($almacen->loadMultiple($tanda) as $entidad) {
        foreach ($entidad->validate() as $violacion) {
          $violaciones[] = [
            $bundle,
            $entidad->id(),
            mb_substr((string) $entidad->label(), 0, 50),
            $violacion->getPropertyPath() ?: '-',
            strip_tags((string) $violacion->getMessage()),
          ];
        }
        if ($bundle === 'programa') {
          foreach ($completitud as $campo => $nombre) {
            if ($entidad->get($campo)->isEmpty()) {
              $fila = [
                $bundle,
                $entidad->id(),
                mb_substr((string) $entidad->label(), 0, 50),
                $campo,
                "Falta la $nombre del programa.",
              ];
              if (isset($con_datos[$entidad->id()])) {
                $violaciones[] = $fila;
              }
              else {
                $avisos[] = $fila;
              }
            }
          }
        }
      }
      $almacen->resetCache($tanda);
    }
  }
}

printf("Entidades revisadas:\n");
foreach ($revisadas as $bundle => $cantidad) {
  printf("  %-18s %4d\n", $bundle, $cantidad);
}

$imprimir = function (string $titulo, array $filas): void {
  printf("\n%s (%d):\n", $titulo, count($filas));
  if (!$filas) {
    print "  ninguno\n";
    return;
  }
  printf("%-16s %-6s %-50s %-24s %s\n", 'tipo', 'id', 'título', 'campo', 'mensaje');
  foreach ($filas as [$bundle, $id, $titulo_entidad, $campo, $mensaje]) {
    printf("%-16s %-6s %-50s %-24s %s\n", $bundle, $id, $titulo_entidad, $campo, $mensaje);
  }
};

$imprimir('ERRORES', $violaciones);
$imprimir('AVISOS', $avisos);
printf("\nResumen: %d error(es), %d aviso(s).\n", count($violaciones), count($avisos));
