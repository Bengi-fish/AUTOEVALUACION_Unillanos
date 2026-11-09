/**
 * @file
 * Modo edición: interruptor de la barra superior.
 *
 * Activa o desactiva los lápices y los «+» (atributo data-ua-edicion en la
 * etiqueta html). El estado se recuerda en el navegador; si el almacenamiento
 * no está disponible, empieza apagado.
 */
((Drupal, once) => {
  const CLAVE = 'ua-edicion';

  const leer = () => {
    try {
      return window.localStorage.getItem(CLAVE) === '1';
    }
    catch (e) {
      return false;
    }
  };

  const guardar = (activo) => {
    try {
      window.localStorage.setItem(CLAVE, activo ? '1' : '0');
    }
    catch (e) {
      // Sin almacenamiento el modo queda apagado al recargar.
    }
  };

  const aplicar = (boton, activo) => {
    document.documentElement.setAttribute('data-ua-edicion', activo ? 'on' : 'off');
    boton.setAttribute('aria-pressed', activo ? 'true' : 'false');
  };

  // Panel lateral: tras guardar con errores, el foco pasa al mensaje para que
  // se anuncie y quede a la vista (el formulario se vuelve a pintar entero).
  Drupal.behaviors.uaPanelErrores = {
    attach(context) {
      once('ua-panel-error', '#drupal-off-canvas [role="alert"]', context).forEach((mensaje) => {
        mensaje.setAttribute('tabindex', '-1');
        // Drupal devuelve el foco al botón que envió el formulario justo
        // después de pintar la respuesta: el mensaje lo toma un instante después.
        window.setTimeout(() => mensaje.focus(), 100);
      });
    },
  };

  Drupal.behaviors.uaEdicion = {
    attach(context) {
      once('ua-edicion', '[data-ua-edicion-toggle]', context).forEach((boton) => {
        aplicar(boton, leer());
        boton.addEventListener('click', () => {
          const activo = boton.getAttribute('aria-pressed') !== 'true';
          aplicar(boton, activo);
          guardar(activo);
        });
      });
    },
  };
})(Drupal, once);
