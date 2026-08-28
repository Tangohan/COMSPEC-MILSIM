/**
 * MapControls — contrôles carte flottants (nord, zoom, 2D/3D, recentrage).
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
      '<div class="tac-map-controls__stack">'
      + btn('nord', 'N', 'Nord', h.onNorth)
      + btn('zoom-in', '+', 'Zoom avant', h.onZoomIn)
      + btn('zoom-out', '−', 'Zoom arrière', h.onZoomOut)
      + btn('toggle-23d', '3D', 'Basculer 2D / 3D', h.onToggle23d, 'tac-ctrl-23d')
      + btn('recenter', '⌖', 'Recentrer', h.onRecenter)
      + btn('follow', '◎', 'Suivi unité', h.onFollow, 'tac-ctrl-follow')
      + '</div>';

    const self = this;
    this.container.querySelectorAll('[data-action]').forEach(function (el) {
      el.addEventListener('click', function () {
        const action = el.getAttribute('data-action');
        if (action === 'nord' && h.onNorth) h.onNorth();
        if (action === 'zoom-in' && h.onZoomIn) h.onZoomIn();
        if (action === 'zoom-out' && h.onZoomOut) h.onZoomOut();
        if (action === 'toggle-23d' && h.onToggle23d) {
          self.mode = self.mode === '2d' ? '3d' : '2d';
          el.textContent = self.mode === '2d' ? '3D' : '2D';
          el.classList.toggle('is-active', self.mode === '3d');
          h.onToggle23d(self.mode);
        }
        if (action === 'recenter' && h.onRecenter) h.onRecenter();
        if (action === 'follow' && h.onFollow) {
          el.classList.toggle('is-active');
          h.onFollow(el.classList.contains('is-active'));
        }
      });
    });
  }

  setMode(mode) {
    this.mode = mode;
    const btn23d = this.container && this.container.querySelector('[data-action="toggle-23d"]');
    if (btn23d) {
      btn23d.textContent = mode === '2d' ? '3D' : '2D';
      btn23d.classList.toggle('is-active', mode === '3d');
    }
  }
}

function btn(action, label, title, handler, extraClass) {
  return '<button type="button" class="tac-map-controls__btn' + (extraClass ? ' ' + extraClass : '') + '"'
    + ' data-action="' + action + '" title="' + title + '">' + label + '</button>';
}

if (typeof window !== 'undefined') {
  window.MapControls = MapControls;
}
