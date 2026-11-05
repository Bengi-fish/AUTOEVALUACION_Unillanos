/**
 * @file
 * Filtro de documentos por grupo (página /documentos).
 *
 * Cada botón [data-key] corresponde a una sección con ese id. «Todos» (clave
 * vacía) muestra todas. Cambia solo el atributo hidden de las secciones y el
 * aria-pressed de los botones, y anuncia en la región role="status" cuántos
 * documentos se ven (suma de los conteos que recibió el panel). No usa
 * innerHTML. Si falta alguna sección, ese botón no filtra nada.
 */

/* phpcs:disable Generic.PHP.UpperCaseConstant.Found */
/* phpcs:disable Squiz.WhiteSpace.OperatorSpacing */

((Drupal, once) => {
  'use strict';

  const iniciar = (panel) => {
    const botones = Array.from(panel.querySelectorAll('button[data-key]'));
    const aviso = panel.querySelector('[data-ua-doc-filter-live]');
    if (botones.length < 2 || !aviso) {
      return;
    }
    const seccion = (clave) => (clave ? document.getElementById(clave) : null);
    const conteo = (boton) => {
      const cifra = boton.querySelector('.ua-docfilter__count');
      return cifra ? parseInt(cifra.textContent, 10) || 0 : 0;
    };

    const aplicar = (elegido, anunciar) => {
      botones.forEach((boton) => {
        boton.setAttribute('aria-pressed', boton === elegido ? 'true' : 'false');
        const destino = seccion(boton.getAttribute('data-key'));
        if (destino) {
          destino.hidden = !!elegido.getAttribute('data-key') && boton !== elegido;
        }
      });
      if (anunciar) {
        const n = conteo(elegido);
        aviso.textContent = Drupal.formatPlural(n, 'Se muestra 1 documento.', 'Se muestran @count documentos.');
      }
    };

    botones.forEach((boton) => {
      boton.addEventListener('click', () => {
        aplicar(boton, true);
        Drupal.unillanos.motion.flourish(document.body, '.ua-section:not([hidden]) .ua-doc');
      });
    });
    panel.hidden = false;
    aplicar(botones[0], false);
  };

  /**
   * Engancha el filtro de documentos.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Engancha cada [data-ua-doc-filter] una sola vez.
   */
  Drupal.behaviors.unillanosDocFilter = {
    attach(context) {
      once('ua-doc-filter', '[data-ua-doc-filter]', context).forEach(iniciar);
    },
  };
})(Drupal, once);
