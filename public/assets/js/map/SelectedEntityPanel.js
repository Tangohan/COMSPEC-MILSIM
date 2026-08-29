/**
 * SelectedEntityPanel — panneau contextuel bas (unité sélectionnée ou journal).
 */
export class SelectedEntityPanel {
  /**
   * @param {HTMLElement} container
   */
  constructor(container) {
    this.container = container;
    this.entity = null;
    this._bindEvents();
    this.render(null);
  }

  _bindEvents() {
    const self = this;
    window.addEventListener('atak:entity-selected', function (ev) {
      self.render(ev.detail || null);
    });
  }

  /**
   * @param {object|null} entity
   */
  render(entity) {
    this.entity = entity;
    if (!this.container) return;

    if (!entity) {
      this.container.innerHTML =
        '<div class="tac-ctx tac-ctx--idle">'
        + '<div class="tac-ctx__section">'
        + '<span class="tac-ctx__label">Réseau</span>'
        + '<span class="tac-ctx__value tac-ctx__value--ok">Opérationnel</span>'
        + '</div>'
        + '<div class="tac-ctx__section">'
        + '<span class="tac-ctx__label">Journal</span>'
        + '<span class="tac-ctx__value tac-ctx__muted">Sélectionnez une unité sur la carte</span>'
        + '</div>'
        + '</div>';
      return;
    }

    const cs = entity.callsign || entity.id || '—';
    const role = entity.role || entity.team || '—';
    const status = entity.status || entity.linkStatus || 'ONLINE';
    const alt = entity.altitude != null ? Math.round(entity.altitude) + ' m' : '—';
    const spd = entity.speed != null ? Math.round(entity.speed) + ' km/h' : '—';
    const hdg = entity.heading != null ? String(Math.round(entity.heading)).padStart(3, '0') + '°' : '—';
    const pos = entity.grid || entity.posLabel || '—';

    this.container.innerHTML =
      '<div class="tac-ctx tac-ctx--entity">'
      + '<div class="tac-ctx__head">'
      + '<span class="tac-ctx__callsign mono">' + esc(cs) + '</span>'
      + '<span class="tac-ctx__role">' + esc(role) + '</span>'
      + '<span class="tac-ctx__status tac-ctx__status--' + esc(status.toLowerCase()) + '">' + esc(status) + '</span>'
      + '</div>'
      + '<div class="tac-ctx__grid">'
      + cell('Position', pos)
      + cell('Altitude', alt)
      + cell('Vitesse', spd)
      + cell('Cap', hdg)
      + '</div>'
      + '<div class="tac-ctx__actions">'
      + '<button type="button" class="tac-ctx__action" data-action="focus">Centrer</button>'
      + '<button type="button" class="tac-ctx__action" data-action="follow">Suivre</button>'
      + '</div>'
      + '</div>';

    const self = this;
    this.container.querySelectorAll('[data-action]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const a = btn.getAttribute('data-action');
        if (a === 'focus') {
          window.dispatchEvent(new CustomEvent('atak:entity-focus', { detail: entity }));
        }
        if (a === 'follow') {
          window.dispatchEvent(new CustomEvent('atak:entity-follow', { detail: entity }));
        }
      });
    });
  }
}

function cell(label, value) {
  return '<div class="tac-ctx__cell">'
    + '<span class="tac-ctx__label">' + esc(label) + '</span>'
    + '<span class="tac-ctx__value mono">' + esc(value) + '</span>'
    + '</div>';
}

function esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

if (typeof window !== 'undefined') {
  window.SelectedEntityPanel = SelectedEntityPanel;
}
