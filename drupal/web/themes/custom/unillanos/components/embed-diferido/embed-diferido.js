/**
 * @file
 * Carga bajo demanda de un contenido externo (/institucional/cifras).
 *
 * El botón nace oculto: sin JavaScript solo queda el enlace «Abrir». Al
 * pulsarlo se crea el iframe con título, carga diferida y sin enviar el sitio
 * de origen, solo si la dirección es https. No usa innerHTML.
 */

/* phpcs:disable Generic.PHP.UpperCaseConstant.Found */
/* phpcs:disable Squiz.WhiteSpace.OperatorSpacing */

((Drupal, once) => {
  'use strict';

  const iniciar = (bloque) => {
    const boton = bloque.querySelector('[data-ua-embed-cargar]');
    const marco = bloque.querySelector('[data-ua-embed-marco]');
    const aviso = bloque.querySelector('[data-ua-embed-live]');
    if (!boton || !marco) {
      return;
    }
    boton.hidden = false;
    boton.addEventListener('click', () => {
      const url = boton.getAttribute('data-url') || '';
      if (!url.startsWith('https://') || url.length <= 8) {
        return;
      }
      const alto = parseInt(boton.getAttribute('data-height'), 10) || 800;
      const visor = document.createElement('iframe');
      visor.setAttribute('src', url);
      visor.setAttribute('title', boton.getAttribute('data-title') || '');
      visor.setAttribute('loading', 'lazy');
      visor.setAttribute('referrerpolicy', 'no-referrer');
      visor.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox');
      visor.setAttribute('height', String(alto));
      marco.appendChild(visor);
      boton.hidden = true;
      if (aviso) {
        aviso.textContent = Drupal.t('El contenido externo se está cargando.');
      }
      visor.focus();
    });
  };

  /**
   * Engancha los bloques de contenido externo.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Engancha cada [data-ua-embed] una sola vez.
   */
  Drupal.behaviors.unillanosEmbedDiferido = {
    attach(context) {
      once('ua-embed', '[data-ua-embed]', context).forEach(iniciar);
    },
  };
})(Drupal, once);
