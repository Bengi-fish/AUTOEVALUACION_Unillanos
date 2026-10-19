/**
 * @file
 * Buscador de programas (portado de plantilla/app.js, initProgramas).
 *
 * Filtra las tarjetas sin recargar la página: por texto (sin tildes ni
 * mayúsculas, sobre data-search) y por facultad, nivel y sede (data-faculty,
 * data-level, data-campus). Los que no coinciden llevan hidden. Filtrar nunca
 * mueve el foco; solo «Quitar filtros» lo devuelve al campo de búsqueda porque
 * el propio enlace desaparece al limpiar. El contador (región role="status")
 * cambia de texto solo cuando la persona usa un control, y al escribir se
 * aplaza para no anunciar cada tecla. Sin JavaScript, el formulario GET sigue
 * funcionando (el servidor marca los ocultos).
 * No usa innerHTML: solo cambia atributos y textContent.
 */

/* phpcs:disable Generic.PHP.UpperCaseConstant.Found */
/* phpcs:disable Squiz.WhiteSpace.OperatorSpacing */

((Drupal, once) => {
  'use strict';

  // Espera, en milisegundos, antes de actualizar el contador al escribir.
  const ESPERA = 300;

  const normalizar = (texto) => String(texto).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

  const iniciar = (raiz) => {
    const campo = raiz.querySelector('[data-ua-prog-q]');
    const formulario = raiz.querySelector('form');
    const selectores = Array.from(raiz.querySelectorAll('[data-ua-prog-filter]'));
    const enviar = raiz.querySelector('[data-ua-prog-submit]');
    const limpiar = raiz.querySelector('[data-ua-prog-clear]');
    const cuenta = raiz.querySelector('[data-ua-prog-count]');
    const lista = raiz.querySelector('[data-ua-prog-list]');
    const vacio = raiz.querySelector('[data-ua-prog-empty]');
    const items = lista ? Array.from(lista.querySelectorAll('[data-ua-prog-item]')) : [];
    if (!campo || !formulario || !cuenta || !lista || !vacio || !items.length) {
      return;
    }
    const total = parseInt(cuenta.getAttribute('data-total'), 10) || items.length;
    let temporizador = null;

    // Con JavaScript el filtro es instantáneo: el botón de enviar sobra.
    if (enviar) {
      enviar.hidden = true;
    }

    const hayFiltro = () => campo.value.trim() !== '' || selectores.some((seleccion) => seleccion.value);

    // Escribe el contador con la concordancia (sin innerHTML).
    const escribirCuenta = (visibles) => {
      cuenta.textContent = hayFiltro()
        ? Drupal.formatPlural(visibles, 'Se muestra @count programa de @total.', 'Se muestran @count programas de @total.', { '@total': String(total) })
        : Drupal.formatPlural(visibles, 'Se muestra @count programa.', 'Se muestran @count programas.');
    };

    // Aplica los filtros. Con actualizarCuenta se reescribe el contador y con
    // animar se anima la lista (no al escribir).
    const filtrar = (actualizarCuenta, animar) => {
      const termino = normalizar(campo.value.trim());
      let visibles = 0;
      items.forEach((item) => {
        const pasaTexto = termino === '' || (item.getAttribute('data-search') || '').includes(termino);
        const pasaFiltros = selectores.every((seleccion) => {
          return !seleccion.value || item.getAttribute('data-' + seleccion.getAttribute('data-ua-prog-filter')) === seleccion.value;
        });
        item.hidden = !(pasaTexto && pasaFiltros);
        if (!item.hidden) {
          visibles += 1;
        }
      });
      vacio.hidden = visibles > 0;
      if (limpiar) {
        limpiar.hidden = !hayFiltro();
      }
      if (actualizarCuenta) {
        escribirCuenta(visibles);
      }
      if (animar) {
        Drupal.unillanos.motion.flourish(lista, '.ua-prog');
      }
    };

    // Al escribir, la lista cambia al instante y el contador espera.
    campo.addEventListener('input', () => {
      filtrar(false, false);
      window.clearTimeout(temporizador);
      temporizador = window.setTimeout(() => filtrar(true, false), ESPERA);
    });
    selectores.forEach((seleccion) => {
      seleccion.addEventListener('change', () => filtrar(true, true));
    });
    // Enter en el campo envía el formulario: se filtra en el sitio.
    formulario.addEventListener('submit', (evento) => {
      evento.preventDefault();
      window.clearTimeout(temporizador);
      filtrar(true, false);
    });
    if (limpiar) {
      limpiar.addEventListener('click', (evento) => {
        evento.preventDefault();
        window.clearTimeout(temporizador);
        campo.value = '';
        selectores.forEach((seleccion) => {
          seleccion.value = '';
        });
        filtrar(true, true);
        campo.focus();
      });
    }
    // Respeta lo que dejó el servidor (valores del formulario) sin anunciar.
    filtrar(false, false);
  };

  Drupal.behaviors.unillanosProgramSearch = {
    attach(context) {
      once('ua-programas', '[data-ua-programas]', context).forEach(iniciar);
    },
  };
})(Drupal, once);
