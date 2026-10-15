/**
 * @file
 * Paginación de la tabla del plan (portado de plantilla/app.js).
 *
 * Las filas que un filtro excluye llevan data-ua-out; la paginación solo
 * cuenta las demás. Con el evento unillanos:plan-refresh se recalcula desde la
 * primera página. Los botones no se desactivan (se pierde el foco): en los
 * extremos pasan a aria-disabled y no hacen nada. El recuento se anuncia (región
 * role="status") solo al cambiar de página.
 */

/* phpcs:disable Generic.PHP.UpperCaseConstant.Found */
/* phpcs:disable Squiz.WhiteSpace.OperatorSpacing */

((Drupal, once) => {
  'use strict';

  const iniciar = (contenedor) => {
    const tamano = parseInt(contenedor.getAttribute('data-page-size'), 10) || 6;
    const filas = Array.from(contenedor.querySelectorAll('tbody tr'));
    const navegacion = contenedor.querySelector('.ua-plan__nav');
    const cuenta = contenedor.querySelector('.ua-plan__count');
    const aviso = contenedor.querySelector('[data-ua-plan-page-live]');
    const anterior = contenedor.querySelector('[data-dir="-1"]');
    const siguiente = contenedor.querySelector('[data-dir="1"]');
    let pagina = 0;

    const visibles = () => filas.filter((fila) => !fila.hasAttribute('data-ua-out'));
    const marcar = (boton, sinDestino) => {
      if (sinDestino) {
        boton.setAttribute('aria-disabled', 'true');
      }
      else {
        boton.removeAttribute('aria-disabled');
      }
    };
    const negrita = (texto) => {
      const elemento = document.createElement('strong');
      elemento.textContent = texto;
      return elemento;
    };

    const pintar = () => {
      const lista = visibles();
      const paginas = Math.max(1, Math.ceil(lista.length / tamano));
      pagina = Math.min(pagina, paginas - 1);
      filas.forEach((fila) => {
        fila.hidden = true;
      });
      lista.forEach((fila, indice) => {
        fila.hidden = Math.floor(indice / tamano) !== pagina;
      });
      if (lista.length) {
        const desde = pagina * tamano + 1;
        const hasta = Math.min(lista.length, desde + tamano - 1);
        cuenta.replaceChildren(
          document.createTextNode(Drupal.t('Acciones') + ' '),
          negrita(desde + '–' + hasta),
          document.createTextNode(' ' + Drupal.t('de') + ' '),
          negrita(String(lista.length))
        );
      }
      else {
        cuenta.textContent = Drupal.t('Sin acciones');
      }
      marcar(anterior, pagina === 0);
      marcar(siguiente, pagina >= paginas - 1);
    };

    // Solo un cambio de página se anuncia; un cambio de filtro lo anuncia el
    // propio filtro, y así no se oyen dos avisos casi iguales.
    const anunciar = () => {
      aviso.textContent = cuenta.textContent;
    };
    anterior.addEventListener('click', () => {
      if (anterior.getAttribute('aria-disabled') !== 'true') {
        pagina -= 1;
        pintar();
        anunciar();
      }
    });
    siguiente.addEventListener('click', () => {
      if (siguiente.getAttribute('aria-disabled') !== 'true') {
        pagina += 1;
        pintar();
        anunciar();
      }
    });
    contenedor.addEventListener('unillanos:plan-refresh', () => {
      pagina = 0;
      pintar();
    });

    navegacion.hidden = false;
    pintar();
  };

  /**
   * Engancha la paginación de cada tabla del plan.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Engancha cada [data-ua-plan] una sola vez.
   */
  Drupal.behaviors.unillanosPlanTable = {
    attach(context) {
      once('ua-plan', '[data-ua-plan]', context).forEach(iniciar);
    },
  };
})(Drupal, once);
