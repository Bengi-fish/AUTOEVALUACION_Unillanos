/**
 * @file
 * Botones de la galería (portado de plantilla/app.js).
 *
 * Desplaza la pista un 80 % de su ancho por clic. Con prefers-reduced-motion
 * el desplazamiento es inmediato. La pista tiene tabindex="0": con el foco
 * en ella, las flechas del teclado la recorren de forma nativa.
 *
 * Añadido respecto al prototipo: en los extremos el botón correspondiente
 * pasa a aria-disabled (no a disabled, para no perder el foco) y los botones
 * apuntan a la pista con aria-controls.
 */

((Drupal, once) => {
  'use strict';

  let contador = 0;

  /**
   * Engancha los botones de cada galería.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Engancha cada [data-ua-gallery] una sola vez.
   */
  Drupal.behaviors.unillanosGallery = {
    attach(context) {
      once('ua-gallery', '[data-ua-gallery]', context).forEach((galeria) => {
        const pista = galeria.querySelector('.ua-gallery__track');
        if (!pista) {
          return;
        }
        const botones = Array.from(galeria.querySelectorAll('[data-dir]'));
        const reducido = window.matchMedia('(prefers-reduced-motion: reduce)');

        if (!pista.id) {
          contador += 1;
          pista.id = ['ua-gallery-track', contador].join('-');
        }

        // Marca como no disponible el botón que ya no tiene hacia dónde ir.
        const actualizar = () => {
          const maximo = pista.scrollWidth - pista.clientWidth;
          botones.forEach((boton) => {
            const direccion = parseInt(boton.getAttribute('data-dir'), 10);
            const extremo = direccion < 0 ? pista.scrollLeft <= 1 : pista.scrollLeft >= maximo - 1;
            if (extremo) {
              boton.setAttribute('aria-disabled', 'true');
            }
            else {
              boton.removeAttribute('aria-disabled');
            }
          });
        };

        botones.forEach((boton) => {
          boton.setAttribute('aria-controls', pista.id);
          boton.addEventListener('click', () => {
            if (boton.getAttribute('aria-disabled') === 'true') {
              return;
            }
            const direccion = parseInt(boton.getAttribute('data-dir'), 10);
            pista.scrollBy({
              left: direccion * pista.clientWidth * 0.8,
              behavior: reducido.matches ? 'auto' : 'smooth',
            });
          });
        });

        pista.addEventListener('scroll', actualizar);
        window.addEventListener('resize', actualizar);
        actualizar();
      });
    },
  };
})(Drupal, once);
