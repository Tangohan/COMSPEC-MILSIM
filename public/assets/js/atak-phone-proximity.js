/**
 * Alerte vibrante : l’ATAK allié (indicatif de session) entre dans le rayon
 * d’un téléphone suivi. Rayon réglable, mêmes libellés qu’en jeu.
 */
window.ATAKPhoneProximity = (function () {
  'use strict';

  var STORAGE_KEY = 'atak_phone_proximity_m';
  var PRESETS = [0, 50, 100, 200, 500, 1000, 2000];
  var DEFAULT_M = 200;
  var EXIT_FACTOR = 1.15;
  var SELECT_IDS = ['atak-phone-proximity', 'atak-phone-proximity-account'];
  var ORIGIN_EPS = 0.5;

  var radiusM = DEFAULT_M;
  var inside = {};
  var lastToastAt = 0;
  var syncingUi = false;

  function normalize(value) {
    var n = parseInt(value, 10);
    if (isNaN(n) || n <= 0) {
      if (n === 0) return 0;
      return DEFAULT_M;
    }
    if (PRESETS.indexOf(n) >= 0) return n;
    var best = DEFAULT_M;
    var bestDelta = 1e9;
    PRESETS.forEach(function (p) {
      var d = Math.abs(p - n);
      if (d < bestDelta) {
        best = p;
        bestDelta = d;
      }
    });
    return best;
  }

  function label(meters) {
    var m = normalize(meters);
    if (m === 0) return 'Désactivée';
    if (m === 50) return '50 mètres';
    if (m === 100) return '100 mètres';
    if (m === 200) return '200 mètres';
    if (m === 500) return '500 mètres';
    if (m === 1000) return '1 kilomètre';
    if (m === 2000) return '2 kilomètres';
    return m + ' mètres';
  }

  function formatDistance(meters) {
    var d = Number(meters);
    if (!isFinite(d) || d < 0) d = 0;
    if (d >= 1000) {
      var km = Math.round(d / 100) / 10;
      var txt = km >= 10 ? String(Math.round(km)) : String(km).replace('.', ',');
      return txt + ' km';
    }
    return String(Math.round(d)) + ' m';
  }

  function evaluate(wasInside, distance, radius) {
    var r = normalize(radius);
    if (r <= 0) return { inside: false, alert: false };
    if (distance <= r) return { inside: true, alert: !wasInside };
    if (wasInside && distance <= r * EXIT_FACTOR) return { inside: true, alert: false };
    return { inside: false, alert: false };
  }

  function load() {
    try {
      radiusM = normalize(localStorage.getItem(STORAGE_KEY));
    } catch (e) {
      radiusM = DEFAULT_M;
    }
    return radiusM;
  }

  function save(meters) {
    var next = normalize(meters);
    var changed = next !== radiusM;
    radiusM = next;
    try {
      localStorage.setItem(STORAGE_KEY, String(next));
    } catch (e) {}
    if (changed) inside = {};
    syncUi();
    return radiusM;
  }

  function syncUi() {
    syncingUi = true;
    SELECT_IDS.forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.value = String(radiusM);
    });
    syncingUi = false;
  }

  function ownCallsignKey() {
    var u = window.ATAK_USER || {};
    var raw = String(u.callsign || u.armaCallsign || '').trim();
    if (!raw && window.ATAK_PHONE_SESSION) {
      raw = String(window.ATAK_PHONE_SESSION.label || '').trim();
    }
    return raw.toUpperCase();
  }

  function parseExtra(u) {
    try {
      if (typeof u.extra === 'string') return JSON.parse(u.extra || '{}');
      if (u.extra && typeof u.extra === 'object') return u.extra;
    } catch (e) {}
    return {};
  }

  function parseCoords(u) {
    var x = u && u.pos_x != null && u.pos_x !== '' ? parseFloat(u.pos_x) : NaN;
    var y = u && u.pos_y != null && u.pos_y !== '' ? parseFloat(u.pos_y) : NaN;
    if (isNaN(x) || isNaN(y)) {
      var parts = String((u && u.grid_ref) || '').trim().split(/\s+/);
      if (parts.length >= 2) {
        x = parseFloat(parts[0]);
        y = parseFloat(parts[1]);
      }
    }
    return { x: x, y: y };
  }

  function hasValidPos(u) {
    var c = parseCoords(u);
    if (isNaN(c.x) || isNaN(c.y)) return false;
    if (Math.abs(c.x) < ORIGIN_EPS && Math.abs(c.y) < ORIGIN_EPS) return false;
    return true;
  }

  function isPhone(u) {
    var ex = parseExtra(u);
    var P = window.ATAKUnitPopup;
    if (P && typeof P.isPhoneGeoloc === 'function') return !!P.isPhoneGeoloc(ex);
    return !!(ex.phone_geoloc);
  }

  function phoneName(u) {
    var ex = parseExtra(u);
    var P = window.ATAKUnitPopup;
    if (P && typeof P.phoneDisplayName === 'function') return P.phoneDisplayName(u, ex);
    return String((u && (u.call_sign || u.callsign)) || 'Téléphone suivi');
  }

  function unitKey(u) {
    if (u && u.id != null && String(u.id) !== '') return 'id:' + String(u.id);
    return 'cs:' + String((u && (u.call_sign || u.callsign)) || '').toUpperCase();
  }

  function isOwnUnit(u, ownKey) {
    if (!ownKey) return false;
    var cs = String((u && (u.call_sign || u.callsign)) || '').toUpperCase().trim();
    return cs !== '' && cs === ownKey;
  }

  function liveOk(u) {
    var st = 'offline';
    if (window.ATAKUnits && typeof window.ATAKUnits.resolveLiveStatus === 'function') {
      st = window.ATAKUnits.resolveLiveStatus(u);
    } else {
      st = String((u && u.status) || '').toLowerCase();
    }
    return st === 'linked' || st === 'delayed';
  }

  function fireAlert(name, dist) {
    var msg = 'Téléphone proche — ' + name + ' (' + formatDistance(dist) + ')';
    var now = Date.now();
    if (now - lastToastAt < 1200) return;
    lastToastAt = now;
    if (typeof window.ATAKShowNotification === 'function') {
      window.ATAKShowNotification(msg, { silent: true, priority: true });
    }
    if (window.ATAKSounds && typeof window.ATAKSounds.tryVibrate === 'function') {
      window.ATAKSounds.tryVibrate({ priority: true });
    }
  }

  function scan() {
    if (radiusM <= 0) {
      inside = {};
      return;
    }
    if (!window.ATAKUnits || typeof window.ATAKUnits.getUnits !== 'function') return;
    var list = window.ATAKUnits.getUnits() || [];
    var ownKey = ownCallsignKey();
    if (!ownKey) return;

    var selfUnit = null;
    var phones = [];
    list.forEach(function (u) {
      if (!u || !hasValidPos(u) || !liveOk(u)) return;
      if (isOwnUnit(u, ownKey)) {
        selfUnit = u;
        return;
      }
      if (isPhone(u)) phones.push(u);
    });
    if (!selfUnit) return;

    var selfC = parseCoords(selfUnit);
    var seen = {};
    phones.forEach(function (phone) {
      var key = unitKey(phone);
      seen[key] = true;
      var pc = parseCoords(phone);
      var dx = pc.x - selfC.x;
      var dy = pc.y - selfC.y;
      var dist = Math.sqrt(dx * dx + dy * dy);
      var ev = evaluate(!!inside[key], dist, radiusM);
      if (ev.inside) inside[key] = true;
      else delete inside[key];
      if (ev.alert) fireAlert(phoneName(phone), dist);
    });
    Object.keys(inside).forEach(function (k) {
      if (!seen[k]) delete inside[k];
    });
  }

  function onSelectChange(ev) {
    if (syncingUi) return;
    save(ev.target.value);
    scan();
  }

  function bindUi() {
    SELECT_IDS.forEach(function (id) {
      var el = document.getElementById(id);
      if (!el || el._atakProxBound) return;
      el._atakProxBound = true;
      el.addEventListener('change', onSelectChange);
    });
    syncUi();
  }

  function init() {
    load();
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', bindUi);
    } else {
      bindUi();
    }
    window.addEventListener('atak:units-updated', scan);
  }

  init();

  return {
    PRESETS: PRESETS,
    normalize: normalize,
    label: label,
    formatDistance: formatDistance,
    evaluate: evaluate,
    getRadius: function () { return radiusM; },
    setRadius: save,
    scan: scan
  };
})();
