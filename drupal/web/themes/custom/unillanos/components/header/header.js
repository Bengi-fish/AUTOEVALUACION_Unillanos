/**
 * @file
 * Botón del menú móvil del encabezado (portado de plantilla/app.js).
 *
 * El botón tiene un nombre fijo («Menú») y solo cambian aria-expanded y el
 * ícono. Con el menú abierto, Escape lo cierra y devuelve el foco al botón.
 */

((Drupal, once) => {
  'use strict';

  /**
   * Abre y cierra el menú principal en pantallas pequeñas.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Engancha el botón [data-ua-menu-toggle] una sola vez.
   */
  Drupal.behaviors.unillanosMenu = {
    attach(context) {
      once('ua-menu-toggle', '[data-ua-menu-toggle]', context).forEach((boton) => {
        const estaAbierto = () => boton.getAttribute('aria-expanded') === 'true';

        const mostrar = (abierto) => {
          const menu = document.getElementById(boton.getAttribute('aria-controls'));
          boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
          // toggleAttribute y no la propiedad hidden: los <svg> no la tienen.
          boton.querySelectorAll('[data-ua-menu-open]').forEach((el) => {
            el.toggleAttribute('hidden', abierto);
          });
          boton.querySelectorAll('[data-ua-menu-close]').forEach((el) => {
            el.toggleAttribute('hidden', !abierto);
          });
          if (menu) {
            menu.classList.toggle('is-open', abierto);
          }
        };

        boton.addEventListener('click', () => mostrar(!estaAbierto()));

        document.addEventListener('keydown', (evento) => {
          if (evento.key === 'Escape' && estaAbierto()) {
            // Está abierto: alternar lo cierra.
            mostrar(!estaAbierto());
            boton.focus();
          }
        });
      });
    },
  };
})(Drupal, once);
