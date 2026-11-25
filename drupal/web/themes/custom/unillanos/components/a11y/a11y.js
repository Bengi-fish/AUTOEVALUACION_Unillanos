/**
 * @file
 * Opciones de accesibilidad: botón flotante y panel desplegable.
 *
 * Cada preferencia es un atributo data-ua-a11y-* del elemento html; el CSS
 * del componente (y el de motion) reaccionan a ellos. Se guardan en
 * localStorage (si el navegador lo permite) y se aplican al cargar cada
 * página. El panel se abre y se cierra con una transición (atributo
 * data-abierto del widget; sin JavaScript queda con hidden). Escape, el botón
 * «Cerrar» y un clic fuera lo cierran, y Escape y «Cerrar» devuelven el foco al
 * botón. No usa innerHTML.
 */

/* phpcs:disable Generic.PHP.UpperCaseConstant.Found */
/* phpcs:disable Squiz.WhiteSpace.OperatorSpacing */

((Drupal, once) => {
  'use strict';

  const CLAVE = 'ua-a11y';
  const PASOS = [100, 115, 130, 150];
  const OPCIONES = ['contraste', 'subrayado', 'espaciado', 'movimiento'];
  const raiz = document.documentElement;

  const leer = () => {
    try {
      const guardado = JSON.parse(window.localStorage.getItem(CLAVE) || '{}');
      return guardado && typeof guardado === 'object' ? guardado : {};
    }
    catch (e) {
      return {};
    }
  };

  const guardar = (estado) => {
    try {
      window.localStorage.setItem(CLAVE, JSON.stringify(estado));
    }
    catch (e) {
      // Sin almacenamiento: la preferencia vale solo en esta página.
    }
  };

  const aplicar = (estado) => {
    const paso = Math.min(Math.max(parseInt(estado.texto, 10) || 0, 0), PASOS.length - 1);
    if (paso > 0) {
      raiz.setAttribute('data-ua-a11y-texto', String(paso));
    }
    else {
      raiz.removeAttribute('data-ua-a11y-texto');
    }
    OPCIONES.forEach((nombre) => {
      raiz.toggleAttribute('data-ua-a11y-' + nombre, estado[nombre] === true);
    });
    return paso;
  };

  // Se aplican en cuanto carga el script, antes de montar el panel.
  aplicar(leer());

  Drupal.behaviors.unillanosA11y = {
    attach(context) {
      once('ua-a11y', '[data-ua-a11y]', context).forEach((widget) => {
        const enlace = widget.querySelector('[data-ua-a11y-enlace]');
        const boton = widget.querySelector('[data-ua-a11y-boton]');
        const panel = widget.querySelector('[data-ua-a11y-panel]');
        const valor = widget.querySelector('[data-ua-a11y-valor]');
        if (!boton || !panel) {
          return;
        }
        let estado = leer();

        const pintar = () => {
          const paso = aplicar(estado);
          valor.textContent = PASOS[paso] + ' %';
          widget.querySelector('[data-ua-a11y-texto="menos"]').disabled = paso === 0;
          widget.querySelector('[data-ua-a11y-texto="mas"]').disabled = paso === PASOS.length - 1;
          widget.querySelectorAll('[data-ua-a11y-opcion]').forEach((opcion) => {
            opcion.setAttribute('aria-pressed', estado[opcion.dataset.uaA11yOpcion] === true ? 'true' : 'false');
          });
        };

        const estaAbierto = () => widget.hasAttribute('data-abierto');

        const abrir = (abierto) => {
          widget.toggleAttribute('data-abierto', abierto);
          boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        };

        // Con JavaScript el enlace se sustituye por el botón.
        if (enlace) {
          enlace.hidden = true;
        }
        boton.hidden = false;
        // La visibilidad del panel la lleva data-abierto (CSS), no hidden, para
        // poder animar la apertura y el cierre.
        panel.hidden = false;
        pintar();

        boton.addEventListener('click', () => abrir(!estaAbierto()));

        widget.addEventListener('click', (evento) => {
          const objetivo = evento.target.closest('button');
          if (!objetivo || !panel.contains(objetivo)) {
            return;
          }
          if (objetivo.hasAttribute('data-ua-a11y-cerrar')) {
            abrir(false);
            boton.focus();
            return;
          }
          if (objetivo.dataset.uaA11yTexto) {
            const paso = (parseInt(estado.texto, 10) || 0) + (objetivo.dataset.uaA11yTexto === 'mas' ? 1 : -1);
            estado.texto = Math.min(Math.max(paso, 0), PASOS.length - 1);
          }
          else if (objetivo.dataset.uaA11yOpcion) {
            estado[objetivo.dataset.uaA11yOpcion] = estado[objetivo.dataset.uaA11yOpcion] !== true;
          }
          else if (objetivo.hasAttribute('data-ua-a11y-restablecer')) {
            estado = {};
          }
          else {
            return;
          }
          guardar(estado);
          pintar();
        });

        widget.addEventListener('keydown', (evento) => {
          if (evento.key === 'Escape' && estaAbierto()) {
            abrir(false);
            boton.focus();
          }
        });

        // Un clic fuera, o sacar el foco del widget, cierra el panel.
        document.addEventListener('click', (evento) => {
          if (estaAbierto() && !widget.contains(evento.target)) {
            abrir(false);
          }
        });
        widget.addEventListener('focusout', (evento) => {
          if (estaAbierto() && evento.relatedTarget && !widget.contains(evento.relatedTarget)) {
            abrir(false);
          }
        });
      });
    },
  };
})(Drupal, once);
