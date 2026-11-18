/**
 * @file
 * Filtros y búsqueda de la matriz legal (/institucional/normatividad).
 *
 * Filtra las filas [data-ua-norm-fila] por origen, tipo y texto (todos los
 * filtros a la vez), actualiza el conteo visible y lo anuncia en la región
 * role="status". Cambia solo el atributo hidden de las filas. No usa
 * innerHTML.
 */

/* phpcs:disable Generic.PHP.UpperCaseConstant.Found */
/* phpcs:disable Squiz.WhiteSpace.OperatorSpacing */

((Drupal, once) => {
  'use strict';

  const iniciar = (tabla) => {
    const filtros = tabla.querySelector('[data-ua-norm-filtros]');
    const origen = tabla.querySelector('[data-ua-norm-origen]');
    const tipo = tabla.querySelector('[data-ua-norm-tipo]');
    const texto = tabla.querySelector('[data-ua-norm-texto]');
    const conteo = tabla.querySelector('[data-ua-norm-conteo]');
    const aviso = tabla.querySelector('[data-ua-norm-live]');
    const filas = Array.from(tabla.querySelectorAll('[data-ua-norm-fila]'));
    if (!filtros || !origen || !tipo || !texto) {
      return;
    }

    const aplicar = (anunciar) => {
      const consulta = texto.value.trim().toLowerCase();
      let visibles = 0;
      filas.forEach((fila) => {
        const coincide = (origen.value === '' || fila.getAttribute('data-origen') === origen.value)
          && (tipo.value === '' || fila.getAttribute('data-tipo') === tipo.value)
          && (consulta === '' || fila.getAttribute('data-texto').includes(consulta));
        fila.hidden = !coincide;
        if (coincide) {
          visibles += 1;
        }
      });
      const mensaje = Drupal.formatPlural(visibles, 'Se muestra 1 norma.', 'Se muestran @count normas.');
      if (conteo) {
        conteo.textContent = mensaje;
      }
      if (anunciar && aviso) {
        aviso.textContent = mensaje;
      }
    };

    [origen, tipo].forEach((control) => control.addEventListener('change', () => aplicar(true)));
    texto.addEventListener('input', () => aplicar(true));
    filtros.hidden = false;
    aplicar(false);
  };

  /**
   * Engancha la tabla de normas.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Engancha cada [data-ua-norm] una sola vez.
   */
  Drupal.behaviors.unillanosNormas = {
    attach(context) {
      once('ua-norm', '[data-ua-norm]', context).forEach(iniciar);
    },
  };
})(Drupal, once);
