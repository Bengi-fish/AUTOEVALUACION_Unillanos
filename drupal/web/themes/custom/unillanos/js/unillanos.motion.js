/**
 * @file
 * Movimiento compartido (portado de plantilla/app.js): entrada de paneles,
 * cascada de piezas y conteo de cifras. Todo respeta prefers-reduced-motion.
 *
 * Expone Drupal.unillanos.motion con reducido(), flourish() y enter().
 */

/* phpcs:disable Generic.PHP.UpperCaseConstant.Found */
/* phpcs:disable Squiz.WhiteSpace.OperatorSpacing */

((Drupal) => {
  'use strict';

  const SUBIDA = '.ua-panel-intro, .ua-section__head, .ua-concept > *, .ua-cond, .ua-kpi, .ua-factor-card, .ua-adenda, .ua-doc, .ua-ficha, .ua-stat, .ua-tool, .ua-plan, .ua-planlink, .ua-strength, .ua-planall-filter, .ua-cta-row, .ua-tabs';

  /**
   * Indica si la persona pidió menos movimiento.
   *
   * @return {boolean}
   *   TRUE si prefers-reduced-motion es reduce.
   */
  const reducido = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /**
   * Cuenta desde 0 hasta el valor que ya tiene el elemento.
   *
   * @param {HTMLElement} elemento
   *   Elemento con un número entero como texto.
   */
  const contar = (elemento) => {
    const hasta = parseInt(elemento.textContent, 10);
    if (Number.isNaN(hasta)) {
      return;
    }
    let inicio = null;
    const paso = (instante) => {
      if (inicio === null) {
        inicio = instante;
      }
      const avance = Math.min(1, (instante - inicio) / 800);
      elemento.textContent = Math.round(hasta * (1 - Math.pow(1 - avance, 3)));
      if (avance < 1) {
        window.requestAnimationFrame(paso);
      }
    };
    elemento.textContent = '0';
    window.requestAnimationFrame(paso);
  };

  /**
   * Hace subir las piezas de un contenedor con un pequeño retraso en cascada.
   *
   * @param {HTMLElement} contenedor
   *   Contenedor cuyos descendientes se animan.
   * @param {string} [selector]
   *   Selector de las piezas; por defecto, las de SUBIDA.
   */
  const flourish = (contenedor, selector) => {
    if (reducido()) {
      return;
    }
    const piezas = Array.from(contenedor.querySelectorAll(selector || SUBIDA));
    piezas.forEach((pieza) => pieza.classList.remove('ua-rise'));
    // Fuerza el recálculo para reiniciar la animación.
    contenedor.getBoundingClientRect();
    piezas.forEach((pieza, indice) => {
      pieza.style.setProperty('--i', Math.min(indice, 12));
      pieza.classList.add('ua-rise');
    });
    contenedor.querySelectorAll('[data-count]').forEach(contar);
  };

  /**
   * Anima la entrada de un panel según el sentido del cambio.
   *
   * @param {HTMLElement} panel
   *   Panel que acaba de mostrarse.
   * @param {number} direccion
   *   Mayor que 0 si viene de una posición anterior; menor, de una posterior;
   *   0 para un fundido.
   */
  const enter = (panel, direccion) => {
    if (reducido() || !panel) {
      return;
    }
    panel.classList.remove('is-in-next', 'is-in-prev', 'is-in-fade');
    panel.getBoundingClientRect();
    if (direccion > 0) {
      panel.classList.add('is-in-next');
    }
    else if (direccion < 0) {
      panel.classList.add('is-in-prev');
    }
    else {
      panel.classList.add('is-in-fade');
    }
    flourish(panel);
  };

  Drupal.unillanos = Drupal.unillanos || {};
  Drupal.unillanos.motion = { reducido, flourish, enter };
})(Drupal);
