/**
 * @file
 * Filtro del plan de mejoramiento (portado de plantilla/app.js).
 *
 * Filtra las filas de la tabla por factor y por plazo sin recargar la página,
 * actualiza las cuatro cifras, muestra el aviso de «sin resultados» y anuncia
 * a los lectores de pantalla cuántas acciones quedan (región role="status").
 * No usa innerHTML: solo cambia atributos y textContent.
 *
 * También escucha unillanos:plan-filter-key (lo envía el enlace al plan de un
 * factor) para filtrar por ese factor y dejar el foco en el filtro.
 */

/* phpcs:disable Generic.PHP.UpperCaseConstant.Found */
/* phpcs:disable Squiz.WhiteSpace.OperatorSpacing */

((Drupal, once) => {
  'use strict';

  const iniciar = (raiz) => {
    const selFactor = raiz.querySelector('[data-f="key"]');
    const selPlazo = raiz.querySelector('[data-f="plazo"]');
    const limpiar = raiz.querySelector('[data-f-clear]');
    const aviso = raiz.querySelector('[data-ua-plan-live]');
    const vacio = raiz.querySelector('[data-ua-plan-empty]');
    const salida = raiz.querySelector('[data-out]');
    const tabla = raiz.querySelector('[data-ua-plan]');
    const filas = tabla ? Array.from(tabla.querySelectorAll('tbody tr')) : [];
    const cifras = {};
    raiz.querySelectorAll('[data-kpi]').forEach((cifra) => {
      cifras[cifra.getAttribute('data-kpi')] = cifra;
    });
    if (!selFactor || !selPlazo || !tabla || !limpiar || !aviso || !vacio || !salida
      || !cifras.total || !cifras.corto || !cifras.mediano || !cifras.largo) {
      return;
    }

    // Aplica los filtros. Con anunciar, avisa del resultado a los lectores.
    const filtrar = (anunciar, animar) => {
      const conteo = { corto: 0, mediano: 0, largo: 0 };
      let visibles = 0;
      filas.forEach((fila) => {
        const pasaFactor = !selFactor.value || fila.getAttribute('data-key') === selFactor.value;
        const pasaPlazo = !selPlazo.value || fila.getAttribute('data-plazo') === selPlazo.value;
        if (pasaFactor && pasaPlazo) {
          fila.removeAttribute('data-ua-out');
          visibles += 1;
          const plazo = fila.getAttribute('data-plazo');
          if (plazo in conteo) {
            conteo[plazo] += 1;
          }
        }
        else {
          fila.setAttribute('data-ua-out', '');
        }
      });
      limpiar.hidden = !(selFactor.value || selPlazo.value);
      vacio.hidden = visibles > 0;
      tabla.hidden = visibles === 0;
      cifras.total.textContent = String(visibles);
      cifras.corto.textContent = String(conteo.corto);
      cifras.mediano.textContent = String(conteo.mediano);
      cifras.largo.textContent = String(conteo.largo);
      tabla.dispatchEvent(new window.CustomEvent('unillanos:plan-refresh'));
      if (animar) {
        Drupal.unillanos.motion.flourish(salida, '.ua-kpi, .ua-plan, .ua-panel__block-title');
      }
      if (anunciar) {
        aviso.textContent = visibles
          ? Drupal.formatPlural(visibles, 'Se muestra 1 acción de mejora de @total.', 'Se muestran @count acciones de mejora de @total.', { '@total': String(filas.length) })
          : Drupal.t('No hay acciones con esos filtros.');
      }
    };

    [selFactor, selPlazo].forEach((seleccion) => {
      seleccion.addEventListener('change', () => filtrar(true, true));
    });
    limpiar.addEventListener('click', () => {
      selFactor.value = '';
      selPlazo.value = '';
      filtrar(true, true);
      selFactor.focus();
    });
    raiz.addEventListener('unillanos:plan-filter-key', (evento) => {
      const clave = evento.detail && evento.detail.key;
      if (!Array.from(selFactor.options).some((opcion) => opcion.value === clave)) {
        return;
      }
      selFactor.value = clave;
      selPlazo.value = '';
      filtrar(true, true);
      selFactor.focus({ preventScroll: true });
    });

    filtrar(false, false);
  };

  /**
   * Engancha el filtro de cada plan de mejoramiento.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Engancha cada [data-ua-plan-all] una sola vez.
   */
  Drupal.behaviors.unillanosPlanFilter = {
    attach(context) {
      once('ua-plan-all', '[data-ua-plan-all]', context).forEach(iniciar);
    },
  };
})(Drupal, once);
