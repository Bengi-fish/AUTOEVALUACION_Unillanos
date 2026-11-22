/**
 * @file
 * Carrusel de la portada.
 *
 * Muestra una diapositiva a la vez: las demás quedan apiladas, invisibles y con
 * inert. El cambio es animado (fundido y deslizamiento, ver carousel.css); la
 * dirección sale de la flecha pulsada o del avance automático. Avanza sola cada
 * data-interval milisegundos (por defecto 5500) solo si la persona no pidió
 * menos movimiento (prefers-reduced-motion); se detiene al pasar el ratón
 * por encima o al recibir foco, y el botón Pausar/Reproducir (primero en el orden de
 * tabulación) la detiene o la reanuda. Flechas y puntos son botones con nombre.
 * Cuando no avanza sola, la región anuncia cada cambio (aria-live polite). No
 * usa innerHTML.
 */

/* phpcs:disable Generic.PHP.UpperCaseConstant.Found */
/* phpcs:disable Squiz.WhiteSpace.OperatorSpacing */

((Drupal, once) => {
  'use strict';

  const iniciar = (carrusel) => {
    const diapositivas = Array.from(carrusel.querySelectorAll('[data-ua-carousel-slide]'));
    const contenedor = carrusel.querySelector('[data-ua-carousel-slides]');
    if (diapositivas.length < 2 || !contenedor) {
      return;
    }
    const barra = carrusel.querySelector('[data-ua-carousel-barra]');
    const controles = carrusel.querySelector('[data-ua-carousel-controles]');
    const pausa = carrusel.querySelector('[data-ua-carousel-pausa]');
    const textoPausa = carrusel.querySelector('[data-ua-carousel-pausa-texto]');
    const puntos = Array.from(carrusel.querySelectorAll('[data-ua-carousel-punto]'));
    const intervalo = parseInt(carrusel.getAttribute('data-interval'), 10) || 5500;
    const menosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)');

    let actual = 0;
    let temporizador = null;
    // La persona la detuvo con el botón (o pidió menos movimiento).
    let detenidaPorPersona = menosMovimiento.matches;
    // Detenida de forma pasajera: ratón encima o foco dentro.
    let detenidaPasajera = false;

    // Desplazamiento (en %) de la diapositiva que entra o sale.
    const DESPLAZAMIENTO = 4;

    const mostrar = (indice, moverFoco, direccion = 1) => {
      const anterior = actual;
      actual = (indice + diapositivas.length) % diapositivas.length;
      if (actual !== anterior && !menosMovimiento.matches) {
        // La entrante arranca del lado de la dirección, sin transición.
        const entrante = diapositivas[actual];
        entrante.style.transition = 'none';
        entrante.style.setProperty('--ua-desplazamiento', `${direccion * DESPLAZAMIENTO}%`);
        void entrante.offsetWidth;
        entrante.style.transition = '';
        diapositivas[anterior].style.setProperty('--ua-desplazamiento', `${-direccion * DESPLAZAMIENTO}%`);
      }
      diapositivas.forEach((diapositiva, i) => {
        const activa = i === actual;
        diapositiva.toggleAttribute('data-ua-activa', activa);
        diapositiva.setAttribute('aria-hidden', activa ? 'false' : 'true');
        diapositiva.toggleAttribute('inert', !activa);
      });
      puntos.forEach((punto, i) => {
        punto.setAttribute('aria-current', i === actual ? 'true' : 'false');
      });
      if (moverFoco) {
        contenedor.focus({preventScroll: true});
      }
    };

    const correr = () => !detenidaPorPersona && !detenidaPasajera;

    const actualizarAnuncio = () => {
      // Sin avance automático, los cambios se anuncian.
      contenedor.setAttribute('aria-live', correr() ? 'off' : 'polite');
    };

    const programar = () => {
      if (temporizador) {
        window.clearInterval(temporizador);
        temporizador = null;
      }
      if (correr()) {
        temporizador = window.setInterval(() => mostrar(actual + 1, false, 1), intervalo);
      }
      actualizarAnuncio();
    };

    const rotularPausa = () => {
      if (!pausa || !textoPausa) {
        return;
      }
      pausa.setAttribute('aria-pressed', detenidaPorPersona ? 'true' : 'false');
      textoPausa.textContent = detenidaPorPersona
        ? pausa.getAttribute('data-reproducir')
        : pausa.getAttribute('data-pausar');
    };

    // Controles.
    carrusel.querySelector('[data-ua-carousel-ant]').addEventListener('click', () => {
      mostrar(actual - 1, false, -1);
      programar();
    });
    carrusel.querySelector('[data-ua-carousel-sig]').addEventListener('click', () => {
      mostrar(actual + 1, false, 1);
      programar();
    });
    puntos.forEach((punto, i) => {
      punto.addEventListener('click', () => {
        mostrar(i, false, i < actual ? -1 : 1);
        programar();
      });
    });
    if (pausa) {
      pausa.addEventListener('click', () => {
        detenidaPorPersona = !detenidaPorPersona;
        rotularPausa();
        programar();
      });
    }

    // Se detiene al pasar el ratón o al recibir foco.
    carrusel.addEventListener('mouseenter', () => {
      detenidaPasajera = true;
      programar();
    });
    carrusel.addEventListener('mouseleave', () => {
      detenidaPasajera = carrusel.contains(document.activeElement);
      programar();
    });
    carrusel.addEventListener('focusin', () => {
      detenidaPasajera = true;
      programar();
    });
    carrusel.addEventListener('focusout', (evento) => {
      if (!evento.relatedTarget || !carrusel.contains(evento.relatedTarget)) {
        detenidaPasajera = false;
        programar();
      }
    });

    // Si cambia la preferencia de movimiento, se respeta.
    menosMovimiento.addEventListener('change', () => {
      detenidaPorPersona = menosMovimiento.matches;
      rotularPausa();
      programar();
    });

    contenedor.setAttribute('tabindex', '-1');
    carrusel.setAttribute('data-ua-carousel-listo', '');
    if (barra) {
      barra.hidden = false;
    }
    if (controles) {
      controles.hidden = false;
    }
    mostrar(0, false);
    rotularPausa();
    programar();
  };

  /**
   * Engancha el carrusel de la portada.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Engancha cada [data-ua-carousel] una sola vez.
   */
  Drupal.behaviors.unillanosCarrusel = {
    attach(context) {
      once('ua-carousel', '[data-ua-carousel]', context).forEach(iniciar);
    },
  };
})(Drupal, once);
