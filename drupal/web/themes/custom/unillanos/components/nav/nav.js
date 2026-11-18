/**
 * @file
 * Submenú desplegable del menú principal (disclosure accesible).
 *
 * Cada [data-ua-nav-sub] tiene un enlace a la sección y, al lado, un botón
 * [data-ua-nav-toggle] con aria-expanded y aria-controls. El botón abre y
 * cierra el submenú; en escritorio el submenú también se abre al pasar el
 * ratón (con una pequeña demora al cerrar). Escape lo cierra y devuelve el foco
 * al botón; un clic fuera, o sacar el foco del elemento, también lo cierra. En
 * celular el menú móvil usa el mismo botón como acordeón. No usa innerHTML.
 */

/* phpcs:disable Generic.PHP.UpperCaseConstant.Found */
/* phpcs:disable Squiz.WhiteSpace.OperatorSpacing */

((Drupal, once) => {
  'use strict';

  const DEMORA_AL_CERRAR = 250;
  const ESCRITORIO = '(min-width: 861px) and (hover: hover)';

  const iniciar = (item) => {
    const boton = item.querySelector('[data-ua-nav-toggle]');
    const panel = item.querySelector('[data-ua-nav-panel]');
    if (!boton || !panel) {
      return;
    }
    let temporizador = null;

    const abierto = () => boton.getAttribute('aria-expanded') === 'true';
    const mostrar = (abrir) => {
      boton.setAttribute('aria-expanded', abrir ? 'true' : 'false');
      item.classList.toggle('is-open', abrir);
    };
    const cancelar = () => {
      if (temporizador) {
        window.clearTimeout(temporizador);
        temporizador = null;
      }
    };

    boton.hidden = false;
    boton.addEventListener('click', () => {
      cancelar();
      mostrar(!abierto());
    });

    // Escritorio con ratón: se abre al pasar y se cierra con una demora.
    item.addEventListener('mouseenter', () => {
      if (window.matchMedia(ESCRITORIO).matches) {
        cancelar();
        mostrar(true);
      }
    });
    item.addEventListener('mouseleave', () => {
      if (window.matchMedia(ESCRITORIO).matches && abierto()) {
        cancelar();
        temporizador = window.setTimeout(() => mostrar(false), DEMORA_AL_CERRAR);
      }
    });

    // Escape cierra solo el submenú (no el menú móvil) y devuelve el foco.
    item.addEventListener('keydown', (evento) => {
      if (evento.key === 'Escape' && abierto()) {
        evento.stopPropagation();
        mostrar(false);
        boton.focus();
      }
    });

    // Si el foco sale del elemento, el submenú se cierra.
    item.addEventListener('focusout', (evento) => {
      if (abierto() && evento.relatedTarget && !item.contains(evento.relatedTarget)) {
        mostrar(false);
      }
    });

    // Un clic fuera lo cierra.
    document.addEventListener('click', (evento) => {
      if (abierto() && !item.contains(evento.target)) {
        mostrar(false);
      }
    });
  };

  /**
   * Engancha los submenús del menú principal.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Engancha cada [data-ua-nav-sub] una sola vez.
   */
  Drupal.behaviors.unillanosNavSubmenu = {
    attach(context) {
      once('ua-nav-sub', '[data-ua-nav-sub]', context).forEach(iniciar);
    },
  };
})(Drupal, once);
