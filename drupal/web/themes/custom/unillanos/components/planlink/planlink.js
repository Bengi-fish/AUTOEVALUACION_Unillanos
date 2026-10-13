/**
 * @file
 * Enlaces al plan de mejoramiento (portado de plantilla/app.js).
 *
 * Un clic en un enlace con data-ua-plan-link="clave" filtra el plan de la
 * página por esa clave (evento unillanos:plan-filter-key sobre el filtro) y
 * lleva allí al usuario. Si la página no tiene filtro, el enlace funciona
 * como un enlace normal; también si el destino no es un ancla de la página o
 * si se pulsa con Ctrl, Cmd, Mayús o Alt (abrir en otra pestaña).
 */

/* phpcs:disable Generic.PHP.UpperCaseConstant.Found */
/* phpcs:disable Squiz.WhiteSpace.OperatorSpacing */

((Drupal, once) => {
  'use strict';

  /**
   * Engancha los enlaces data-ua-plan-link.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Registra un único oyente de clic para toda la página.
   */
  Drupal.behaviors.unillanosPlanLink = {
    attach(context) {
      once('ua-planlink', 'body', context).forEach(() => {
        document.addEventListener('click', (evento) => {
          const enlace = evento.target.closest('[data-ua-plan-link]');
          const filtro = document.querySelector('[data-ua-plan-all]');
          if (!enlace || !filtro || evento.defaultPrevented || evento.button !== 0
            || evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey) {
            return;
          }
          // Un destino que no es un ancla de esta página se sigue con normalidad.
          if ((enlace.getAttribute('href') || '').charAt(0) !== '#') {
            return;
          }
          evento.preventDefault();
          filtro.dispatchEvent(new window.CustomEvent('unillanos:plan-filter-key', {
            detail: { key: enlace.getAttribute('data-ua-plan-link') },
          }));
          filtro.scrollIntoView({
            behavior: Drupal.unillanos.motion.reducido() ? 'auto' : 'smooth',
            block: 'start',
          });
        });
      });
    },
  };
})(Drupal, once);
