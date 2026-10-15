/**
 * @file
 * Pestañas de factores (portado de plantilla/app.js).
 *
 * Patrón de pestañas accesible: roles tablist/tab/tabpanel, aria-selected,
 * tabindex itinerante (solo la pestaña activa entra en el orden de Tab),
 * flechas según la orientación (arriba/abajo si la lista es vertical,
 * izquierda/derecha si es horizontal), Inicio y Fin, activación inmediata y
 * foco gestionado. Las combinaciones con Alt, Ctrl, Cmd o Mayús no se tocan
 * (son atajos del navegador). Los paneles ya llegan renderizados: aquí solo se
 * alterna cuál se muestra y se anima su entrada (prefers-reduced-motion).
 *
 * Los paneles se buscan dentro de su propio componente, así que varios
 * conjuntos de pestañas pueden convivir en una página. Las tarjetas y adendas
 * con data-ua-goto="clave" llevan a la pestaña con esa clave y le dan el foco;
 * si además llevan data-ua-goto-tabs="id", solo se busca en ese conjunto.
 */

/* phpcs:disable Generic.PHP.UpperCaseConstant.Found */
/* phpcs:disable Squiz.WhiteSpace.OperatorSpacing */

((Drupal, once) => {
  'use strict';

  // Guarda el aviso de cambio de tamaño de cada conjunto para poder retirarlo.
  const avisos = new WeakMap();

  const iniciar = (tabs) => {
    const lista = tabs.querySelector('[role="tablist"]');
    const pestanas = lista ? Array.from(lista.querySelectorAll('[role="tab"]')) : [];
    if (!pestanas.length) {
      return;
    }
    const panelDe = (pestana) => {
      const id = pestana.getAttribute('aria-controls');
      return Array.from(tabs.querySelectorAll('[role="tabpanel"]')).find((panel) => panel.id === id);
    };
    let actual = pestanas.find((pestana) => pestana.getAttribute('aria-selected') === 'true') || pestanas[0];

    // Desplaza solo la lista (no la página) para dejar visible la pestaña.
    const mostrarEnLista = (pestana) => {
      if (lista.scrollWidth <= lista.clientWidth) {
        return;
      }
      lista.scrollTo({
        left: pestana.offsetLeft - (lista.clientWidth - pestana.offsetWidth) / 2,
        behavior: Drupal.unillanos.motion.reducido() ? 'auto' : 'smooth',
      });
    };

    const seleccionar = (pestana, opciones) => {
      const previa = actual;
      actual = pestana;
      pestanas.forEach((otra) => {
        const activa = otra === pestana;
        otra.setAttribute('aria-selected', activa ? 'true' : 'false');
        otra.tabIndex = activa ? 0 : -1;
        const panel = panelDe(otra);
        if (panel) {
          panel.hidden = !activa;
        }
      });
      if (opciones.foco) {
        pestana.focus({ preventScroll: true });
      }
      if (opciones.foco || opciones.animar) {
        mostrarEnLista(pestana);
      }
      if (opciones.animar && previa !== pestana) {
        Drupal.unillanos.motion.enter(panelDe(pestana), pestanas.indexOf(pestana) - pestanas.indexOf(previa));
      }
    };

    // Al achicarse la ventana la lista pasa a ser horizontal.
    const estrecha = window.matchMedia('(max-width: 1080px)');
    const orientar = () => lista.setAttribute('aria-orientation', estrecha.matches ? 'horizontal' : 'vertical');
    estrecha.addEventListener('change', orientar);
    avisos.set(tabs, { consulta: estrecha, funcion: orientar });
    orientar();

    pestanas.forEach((pestana, indice) => {
      pestana.addEventListener('click', () => seleccionar(pestana, { animar: true }));
      pestana.addEventListener('keydown', (evento) => {
        if (evento.altKey || evento.ctrlKey || evento.metaKey || evento.shiftKey) {
          return;
        }
        const vertical = lista.getAttribute('aria-orientation') === 'vertical';
        const siguiente = vertical ? 'ArrowDown' : 'ArrowRight';
        const anterior = vertical ? 'ArrowUp' : 'ArrowLeft';
        let destino = null;
        if (evento.key === siguiente) {
          destino = (indice + 1) % pestanas.length;
        }
        else if (evento.key === anterior) {
          destino = (indice - 1 + pestanas.length) % pestanas.length;
        }
        else if (evento.key === 'Home') {
          destino = 0;
        }
        else if (evento.key === 'End') {
          destino = pestanas.length - 1;
        }
        if (destino === null) {
          return;
        }
        evento.preventDefault();
        seleccionar(pestanas[destino], { foco: true, animar: true });
      });
    });

    // Selección desde fuera (tarjetas y adendas con data-ua-goto).
    tabs.addEventListener('unillanos:tabs-select', (evento) => {
      const clave = evento.detail && evento.detail.key;
      const pestana = pestanas.find((otra) => otra.getAttribute('data-key') === clave);
      if (pestana) {
        seleccionar(pestana, { foco: true, animar: true });
      }
    });

    seleccionar(actual, {});
  };

  /**
   * Engancha las pestañas de factores y los enlaces data-ua-goto.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Engancha cada [data-ua-tabs] una sola vez.
   * @prop {Drupal~behaviorDetach} detach
   *   Retira el aviso de cambio de tamaño de los conjuntos que se eliminan.
   */
  Drupal.behaviors.unillanosTabs = {
    attach(context) {
      once('ua-tabs', '[data-ua-tabs]', context).forEach(iniciar);

      // Un solo oyente para todos los enlaces data-ua-goto de la página.
      once('ua-tabs-goto', 'body', context).forEach(() => {
        document.addEventListener('click', (evento) => {
          const enlace = evento.target.closest('[data-ua-goto]');
          if (!enlace || evento.defaultPrevented || evento.button !== 0
            || evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey) {
            return;
          }
          const clave = enlace.getAttribute('data-ua-goto');
          const destino = enlace.getAttribute('data-ua-goto-tabs');
          const tabs = Array.from(document.querySelectorAll('[data-ua-tabs]')).find(
            (candidato) => (!destino || candidato.id === destino)
              && candidato.querySelector('[role="tab"][data-key="' + window.CSS.escape(clave) + '"]')
          );
          if (!tabs) {
            return;
          }
          evento.preventDefault();
          tabs.dispatchEvent(new window.CustomEvent('unillanos:tabs-select', { detail: { key: clave } }));
          tabs.scrollIntoView({
            behavior: Drupal.unillanos.motion.reducido() ? 'auto' : 'smooth',
            block: 'start',
          });
        });
      });
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once.remove('ua-tabs', '[data-ua-tabs]', context).forEach((tabs) => {
        const aviso = avisos.get(tabs);
        if (aviso) {
          aviso.consulta.removeEventListener('change', aviso.funcion);
          avisos.delete(tabs);
        }
      });
    },
  };
})(Drupal, once);
