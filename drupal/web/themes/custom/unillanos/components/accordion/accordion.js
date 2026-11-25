/**
 * @file
 * Acordeón accesible (/institucional).
 *
 * Cada [data-ua-acc-btn] abre o cierra su panel con aria-expanded y el
 * atributo hidden; varias entradas pueden estar abiertas. Sin JavaScript todo
 * queda abierto: al cargar, se cierran todas (salvo la que pida el # de la
 * URL). El buscador filtra las entradas por el texto de data-ua-acc-text, abre
 * las que coinciden y anuncia cuántas se ven. No usa innerHTML.
 */

/* phpcs:disable Generic.PHP.UpperCaseConstant.Found */
/* phpcs:disable Squiz.WhiteSpace.OperatorSpacing */

((Drupal, once) => {
  'use strict';

  const alternar = (boton, abrir) => {
    const panel = document.getElementById(boton.getAttribute('aria-controls'));
    boton.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    if (panel) {
      panel.hidden = !abrir;
    }
  };

  const iniciar = (acordeon) => {
    const entradas = Array.from(acordeon.querySelectorAll('[data-ua-acc-item]'));
    const botones = Array.from(acordeon.querySelectorAll('[data-ua-acc-btn]'));
    botones.forEach((boton) => {
      alternar(boton, false);
      boton.addEventListener('click', () => {
        alternar(boton, boton.getAttribute('aria-expanded') !== 'true');
      });
    });

    // Una entrada pedida en la URL (#dependencia-12) llega abierta.
    const clave = window.location.hash.slice(1);
    const pedida = clave ? entradas.find((entrada) => entrada.id === clave) : null;
    if (pedida) {
      alternar(pedida.querySelector('[data-ua-acc-btn]'), true);
    }

    const herramientas = acordeon.querySelector('[data-ua-acc-tools]');
    const campo = acordeon.querySelector('[data-ua-acc-search]');
    const aviso = acordeon.querySelector('[data-ua-acc-live]');
    if (!herramientas || !campo) {
      return;
    }
    herramientas.hidden = false;
    campo.addEventListener('input', () => {
      const consulta = campo.value.trim().toLowerCase();
      let visibles = 0;
      entradas.forEach((entrada) => {
        const coincide = consulta === '' || entrada.getAttribute('data-ua-acc-text').includes(consulta);
        entrada.hidden = !coincide;
        if (coincide) {
          visibles += 1;
        }
        const boton = entrada.querySelector('[data-ua-acc-btn]');
        if (coincide && consulta !== '' && boton) {
          alternar(boton, true);
        }
      });
      if (aviso) {
        aviso.textContent = Drupal.formatPlural(visibles, 'Se muestra 1 entrada.', 'Se muestran @count entradas.');
      }
    });
  };

  /**
   * Engancha el acordeón.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Engancha cada [data-ua-acc] una sola vez.
   */
  Drupal.behaviors.unillanosAcordeon = {
    attach(context) {
      once('ua-acc', '[data-ua-acc]', context).forEach(iniciar);
    },
  };
})(Drupal, once);
