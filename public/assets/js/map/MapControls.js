/**
 * MapControls — contrôles carte flottants (nord, zoom, 2D/3D, recentrage).
 * Affiche libellés visibles pour zoom / mode / actions.
 */
export class MapControls {
  /**
   * @param {HTMLElement} container — .tac-map-controls host
   * @param {object} handlers
   */
  constructor(container, handlers) {
    this.container = container;
    this.handlers = handlers || {};
    this.mode = '2d';
    if (container) this._render();
  }

  _render() {
    const h = this.handlers;
    this.container.innerHTML =
      '<div class="tac-map-controls__stack" role="toolbar" aria-label="Contrôles de la carte">'
      + group('Vue', [
          btn('nord', 'N', 'Nord', 'Remettre le nord en haut'),
          btn('toggle-2d', '2D', '2D', 'Vue carte à plat', 'tac-ctrl-mode tac-ctrl-2d is-active'),
          btn('toggle-3d', '3D', '3D', 'Vue relief', 'tac-ctrl-mode tac-ctrl-3d'),
        ])
      + group('Zoom', [
          btn('zoom-in', '+', 'Zoom +', 'Zoom avant'),
          btn('zoom-out', '−', 'Zoom −', 'Zoom arrière'),
        ])
      + group('Cible', [
          btn('recenter', '⌖', 'Centre', 'Recentrer la carte'),
          btn('follow', '◎', 'Suivi', 'Suivre ma position', 'tac-ctrl-follow'),
          btn('map-settings', '⚙', 'Carte', 'Réglages carte (relief, ombre, 3D, données)', 'tac-ctrl-settings'),
        ])
      + '</div>';

    const self = this;
    this.container.querySelectorAll('[data-action]').forEach(function (el) {
      el.addEventListener('click', function () {
        const action = el.getAttribute('data-action');
        if (action === 'nord' && h.onNorth) h.onNorth();
        if (action === 'zoom-in' && h.onZoomIn) h.onZoomIn();
        if (action === 'zoom-out' && h.onZoomOut) h.onZoomOut();
        if (action === 'toggle-2d') {
          self.setMode('2d');
          if (h.onToggle23d) h.onToggle23d('2d');
        }
        if (action === 'toggle-3d') {
          self.setMode('3d');
          if (h.onToggle23d) h.onToggle23d('3d');
        }
        if (action === 'recenter' && h.onRecenter) h.onRecenter();
        if (action === 'follow' && h.onFollow) {
          el.classList.toggle('is-active');
          h.onFollow(el.classList.contains('is-active'));
        }
        if (action === 'map-settings') {
          if (h.onMapSettings) h.onMapSettings();
          else if (window.ATAKMapTools && typeof window.ATAKMapTools.openMapSettings === 'function') {
            window.ATAKMapTools.openMapSettings();
          } else if (window.ATAKC2Workspace && typeof window.ATAKC2Workspace.setSettingsOpen === 'function') {
            window.ATAKC2Workspace.setSettingsOpen(true);
            var target = document.getElementById('atak-settings-map');
            if (target && target.scrollIntoView) {
              try { target.scrollIntoView({ block: 'nearest' }); } catch (e) {}
            }
          }
        }
      });
    });
  }

  setMode(mode) {
    this.mode = mode === '3d' ? '3d' : '2d';
    if (!this.container) return;
    const b2 = this.container.querySelector('[data-action="toggle-2d"]');
    const b3 = this.container.querySelector('[data-action="toggle-3d"]');
    if (b2) b2.classList.toggle('is-active', this.mode === '2d');
    if (b3) b3.classList.toggle('is-active', this.mode === '3d');
  }
}

function group(label, buttonsHtml) {
  return '<div class="tac-map-controls__group" role="group" aria-label="' + label + '">'
    + '<span class="tac-map-controls__group-label">' + label + '</span>'
    + buttonsHtml.join('')
    + '</div>';
}

function btn(action, glyph, label, title, extraClass) {
  return '<button type="button" class="tac-map-controls__btn' + (extraClass ? ' ' + extraClass : '') + '"'
    + ' data-action="' + action + '" title="' + title + '" aria-label="' + title + '">'
    + '<span class="tac-map-controls__glyph" aria-hidden="true">' + glyph + '</span>'
    + '<span class="tac-map-controls__label">' + label + '</span>'
    + '</button>';
}

if (typeof window !== 'undefined') {
  window.MapControls = MapControls;
}
