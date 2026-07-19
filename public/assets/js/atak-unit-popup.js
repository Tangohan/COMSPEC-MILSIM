/**
 * Fiche popup unité / aérien — carte tactique Athena (ATAK, Overwatch, TACMAP).
 * N’affiche que les champs présents dans le payload (pas de métriques inventées).
 */
window.ATAKUnitPopup = (function () {
  'use strict';

  var POPUP_OPTS = {
    className: 'atak-map-popup',
    maxWidth: 300,
    minWidth: 210,
    closeButton: true,
    autoPan: true,
  };

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function parseExtra(u) {
    if (!u) return {};
    try {
      if (typeof u.extra === 'string') return JSON.parse(u.extra || '{}') || {};
      if (u.extra && typeof u.extra === 'object') return u.extra;
    } catch (e) {}
    return {};
  }

  function statusLabelFr(status) {
    var s = String(status || '').toLowerCase().trim();
    if (s === 'linked') return 'En liaison';
    if (s === 'delayed') return 'Signal différé';
    if (s === 'offline') return 'Hors ligne';
    if (s === 'in-flight' || s === 'in_flight') return 'En vol';
    if (s === 'suspect') return 'À vérifier';
    if (!s) return '';
    return String(status);
  }

  function affiliationLabelFr(a) {
    var x = String(a || '').toLowerCase().trim();
    if (x === 'friend' || x === 'friendly' || x === 'west') return 'Allié';
    if (x === 'hostile' || x === 'enemy' || x === 'east') return 'Hostile';
    if (x === 'neutral' || x === 'guer' || x === 'civ') return 'Neutre';
    if (x === 'unknown') return 'Inconnu';
    if (x === 'suspect') return 'Suspect';
    return a ? String(a) : '';
  }

  function healthLabelFr(h) {
    var x = String(h || '').toLowerCase().trim();
    if (x === 'ok' || x === 'stable' || x === 'healthy') return 'Opérationnel';
    if (x === 'wounded' || x === 'injured') return 'Blessé';
    if (x === 'unconscious') return 'Inconscient';
    if (x === 'dead' || x === 'kia') return 'Hors combat';
    return h ? String(h) : '';
  }

  function healthTone(h) {
    var x = String(h || '').toLowerCase().trim();
    if (x === 'wounded' || x === 'injured') return 'warn';
    if (x === 'unconscious' || x === 'dead' || x === 'kia') return 'danger';
    if (x === 'ok' || x === 'stable' || x === 'healthy') return 'ok';
    return '';
  }

  function statusTone(status) {
    var s = String(status || '').toLowerCase().trim();
    if (s === 'linked' || s === 'in-flight' || s === 'in_flight') return 'ok';
    if (s === 'delayed' || s === 'suspect') return 'warn';
    if (s === 'offline') return 'danger';
    return '';
  }

  function formatTimeAgo(iso) {
    if (!iso) return '';
    var t = new Date(iso).getTime();
    if (isNaN(t)) return '';
    var sec = Math.max(0, Math.floor((Date.now() - t) / 1000));
    if (sec < 15) return 'À l’instant';
    if (sec < 60) return 'Il y a ' + sec + ' s';
    if (sec < 3600) return 'Il y a ' + Math.floor(sec / 60) + ' min';
    if (sec < 86400) return 'Il y a ' + Math.floor(sec / 3600) + ' h';
    try {
      return new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      return String(iso);
    }
  }

  function formatHeading(h) {
    if (h == null || h === '') return '';
    var n = parseFloat(h);
    if (isNaN(n)) return '';
    return Math.round(n) + '°';
  }

  function formatGrid(u, extra) {
    var g = (u && u.grid_ref) || (extra && extra.grid_ref) || '';
    g = String(g).trim();
    if (g) return g;
    var x = u && u.pos_x != null ? parseFloat(u.pos_x) : NaN;
    var y = u && u.pos_y != null ? parseFloat(u.pos_y) : NaN;
    if (!isNaN(x) && !isNaN(y)) return Math.round(x) + ' ' + Math.round(y);
    return '';
  }

  function row(label, value, tone) {
    if (value == null || value === '') return '';
    var toneClass = tone ? ' atak-unit-popup__val--' + tone : '';
    return (
      '<div class="atak-unit-popup__row">' +
      '<span class="atak-unit-popup__k">' + escapeHtml(label) + '</span>' +
      '<span class="atak-unit-popup__v' + toneClass + '">' + escapeHtml(value) + '</span>' +
      '</div>'
    );
  }

  function badgeHtml(aff, role) {
    if (!window.NatoSidcIcons || !window.NatoSidcIcons.listBadgeHtml) return '';
    return window.NatoSidcIcons.listBadgeHtml({
      affiliation: aff || 'friend',
      role: role || '',
      size: 22,
    });
  }

  /**
   * @param {object} u unit row from /api/units
   * @returns {string} HTML
   */
  function buildUnitHtml(u) {
    u = u || {};
    var extra = parseExtra(u);
    var callSign = u.call_sign || u.callsign || '—';
    var role = u.role || extra.role || '';
    var aff = extra.affiliation || extra.affil || u.affiliation || '';
    var affLabel = affiliationLabelFr(aff);
    var statusRaw = u.status || '';
    var status = statusLabelFr(statusRaw);
    var healthRaw = extra.health != null ? extra.health : u.health;
    var health = healthLabelFr(healthRaw);
    var heading = formatHeading(u.heading != null ? u.heading : extra.heading);
    var grid = formatGrid(u, extra);
    var updated = formatTimeAgo(u.updated_at || extra.updated_at || u.last_update);
    var parent = extra.parent || extra.parent_callsign || extra.group || extra.groupe || '';
    var radio = extra.radio_freq != null && extra.radio_freq !== ''
      ? String(extra.radio_freq)
      : (extra.radio != null && extra.radio !== '' ? String(extra.radio) : '');
    var fuel = extra.fuel !== undefined && extra.fuel !== null && extra.fuel !== ''
      ? String(extra.fuel) + (String(extra.fuel).indexOf('%') >= 0 ? '' : ' %')
      : '';
    var ammo = extra.ammo != null && extra.ammo !== '' && String(extra.ammo).toLowerCase() !== 'n/a'
      ? String(extra.ammo)
      : '';
    var notes = extra.notes || extra.note || '';
    var side = extra.side || u.side || '';

    var tone = statusTone(statusRaw) || healthTone(healthRaw);
    var headClass = 'atak-unit-popup__head';
    if (tone) headClass += ' atak-unit-popup__head--' + tone;

    var rows =
      row('Liaison', status, statusTone(statusRaw)) +
      row('État', health, healthTone(healthRaw)) +
      row('Affiliation', affLabel) +
      (side && !affLabel ? row('Camp', String(side)) : '') +
      row('Groupe', parent) +
      row('Coordonnées', grid) +
      row('Cap', heading) +
      row('Radio', radio) +
      row('Carburant', fuel) +
      row('Munitions', ammo) +
      row('Dernière MAJ', updated);

    return (
      '<div class="atak-unit-popup">' +
      '<div class="' + headClass + '">' +
      badgeHtml(aff || 'friend', role) +
      '<div class="atak-unit-popup__title-wrap">' +
      '<div class="atak-unit-popup__callsign">' + escapeHtml(callSign) + '</div>' +
      (role ? '<div class="atak-unit-popup__subtitle">' + escapeHtml(role) + '</div>' : '') +
      '</div></div>' +
      (rows ? '<div class="atak-unit-popup__body">' + rows + '</div>' : '') +
      (notes
        ? '<div class="atak-unit-popup__notes">' + escapeHtml(String(notes)) + '</div>'
        : '') +
      '</div>'
    );
  }

  /**
   * @param {object} a air asset
   * @returns {string} HTML
   */
  function buildAirHtml(a) {
    a = a || {};
    var callsign = a.callsign || a.call_sign || '—';
    var model = a.model || a.aircraft_type || '';
    var statusRaw = a.status || '';
    var status = statusLabelFr(statusRaw) || String(statusRaw || '');
    var side = a.side || '';
    var aff = 'friend';
    var sideU = String(side).toUpperCase();
    if (sideU === 'EAST') aff = 'hostile';
    else if (sideU === 'GUER' || sideU === 'CIV' || String(statusRaw).toUpperCase() === 'SUSPECT') aff = 'unknown';
    var freq = a.freq != null && a.freq !== '' ? String(a.freq) : '';
    var laser = a.laser != null && a.laser !== '' ? String(a.laser) : '';
    var grid = formatGrid(a, {});
    var updated = formatTimeAgo(a.updated_at || a.last_update);

    var rows =
      row('Statut', status, statusTone(statusRaw)) +
      row('Affiliation', affiliationLabelFr(aff)) +
      row('Fréquence', freq) +
      row('Code laser', laser) +
      row('Coordonnées', grid) +
      row('Dernière MAJ', updated);

    return (
      '<div class="atak-unit-popup atak-unit-popup--air">' +
      '<div class="atak-unit-popup__head">' +
      badgeHtml(aff, model) +
      '<div class="atak-unit-popup__title-wrap">' +
      '<div class="atak-unit-popup__callsign">' + escapeHtml(callsign) + '</div>' +
      (model ? '<div class="atak-unit-popup__subtitle">' + escapeHtml(model) + '</div>' : '') +
      '</div></div>' +
      (rows ? '<div class="atak-unit-popup__body">' + rows + '</div>' : '') +
      '</div>'
    );
  }

  function popupOptions(extra) {
    var o = {};
    for (var k in POPUP_OPTS) {
      if (Object.prototype.hasOwnProperty.call(POPUP_OPTS, k)) o[k] = POPUP_OPTS[k];
    }
    if (extra && typeof extra === 'object') {
      for (var j in extra) {
        if (Object.prototype.hasOwnProperty.call(extra, j)) o[j] = extra[j];
      }
    }
    return o;
  }

  function bindUnit(marker, u) {
    if (!marker || !marker.bindPopup) return marker;
    var html = buildUnitHtml(u);
    if (marker.getPopup && marker.getPopup()) {
      marker.setPopupContent(html);
    } else {
      marker.bindPopup(html, popupOptions());
    }
    return marker;
  }

  function bindAir(marker, a) {
    if (!marker || !marker.bindPopup) return marker;
    var html = buildAirHtml(a);
    if (marker.getPopup && marker.getPopup()) {
      marker.setPopupContent(html);
    } else {
      marker.bindPopup(html, popupOptions());
    }
    return marker;
  }

  return {
    popupOptions: popupOptions,
    buildUnitHtml: buildUnitHtml,
    buildAirHtml: buildAirHtml,
    bindUnit: bindUnit,
    bindAir: bindAir,
    statusLabelFr: statusLabelFr,
    affiliationLabelFr: affiliationLabelFr,
    healthLabelFr: healthLabelFr,
    formatTimeAgo: formatTimeAgo,
  };
})();
