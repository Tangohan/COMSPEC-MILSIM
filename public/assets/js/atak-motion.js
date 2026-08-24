/* Cinématique BFT — libellés métier, formatage, lecture du payload. */
window.ATAKMotion = (function () {
  'use strict';

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function num(v) {
    if (v == null || v === '') return null;
    var n = Number(v);
    return isFinite(n) ? n : null;
  }

  function motionOf(u) {
    return (u && u.motion && typeof u.motion === 'object') ? u.motion : {};
  }

  function assignmentOf(u) {
    return (u && (u.navigation || u.assignment) && typeof (u.navigation || u.assignment) === 'object')
      ? (u.navigation || u.assignment)
      : null;
  }

  function isAir(u) {
    if (!u) return false;
    if (u.aircraft_type || u.model || (u.air && typeof u.air === 'object' && (u.air.altitude != null || u.air.ground_speed != null))) {
      var cat = String((u.motion && u.motion.category) || '').toUpperCase();
      if (cat === 'HELICOPTER' || cat === 'FIXED_WING' || cat === 'UAV') return true;
      if (u.aircraft_type || u.callsign && !u.call_sign) return true;
    }
    var c = String((u.motion && u.motion.category) || '').toUpperCase();
    return c === 'HELICOPTER' || c === 'FIXED_WING' || c === 'UAV';
  }

  function statusLabel(s) {
    var x = String(s || '').toUpperCase();
    if (x === 'STATIC') return 'Immobile';
    if (x === 'MOVING') return 'En déplacement';
    if (x === 'MANEUVERING') return 'Manœuvre';
    if (x === 'FAST') return 'Rapide';
    if (x === 'UNKNOWN') return 'Indéterminé';
    return '';
  }

  function trendLabel(s) {
    var x = String(s || '').toUpperCase();
    if (x === 'STABLE') return 'Stable';
    if (x === 'ACCEL') return 'Accélère';
    if (x === 'DECEL') return 'Ralentit';
    if (x === 'TURNING') return 'Vire';
    if (x === 'CLIMBING') return 'Montée';
    if (x === 'DESCENDING') return 'Descente';
    return '';
  }

  function courseLabel(s) {
    var x = String(s || '').toUpperCase();
    if (x === 'ON_COURSE') return 'En route';
    if (x === 'DIVERGING') return 'S’éloigne';
    if (x === 'CROSSING') return 'Traverse';
    if (x === 'STATIC') return 'À l’arrêt';
    if (x === 'ARRIVED') return 'Arrivé';
    return '';
  }

  function confidenceLabel(c) {
    var n = num(c);
    if (n == null) return '';
    if (n >= 0.75) return 'Déplacement confirmé';
    if (n >= 0.45) return 'Déplacement probable';
    return 'Historique insuffisant';
  }

  function formatHeading(h) {
    var n = num(h);
    if (n == null) return '';
    var d = Math.round(((n % 360) + 360) % 360);
    return (d < 10 ? '00' : d < 100 ? '0' : '') + d + '°';
  }

  function formatSpeed(u) {
    var m = motionOf(u);
    var ms = num(m.eta_speed != null ? m.eta_speed : (u && u.speed));
    if (ms == null) return '';
    if (isAir(u)) {
      var kt = (u.air && num(u.air.ground_speed_kt) != null) ? num(u.air.ground_speed_kt) : ms * 1.943844;
      return Math.round(kt) + ' kt';
    }
    var kmh = ms * 3.6;
    if (kmh < 10) return kmh.toFixed(1).replace('.', ',') + ' km/h';
    return Math.round(kmh) + ' km/h';
  }

  function formatDistance(m) {
    var n = num(m);
    if (n == null) return '';
    if (isFinite(n) && n >= 1852) {
      var nm = n / 1852;
      if (nm >= 10) return Math.round(nm) + ' NM';
      return nm.toFixed(1).replace('.', ',') + ' NM';
    }
    if (n >= 1000) return (n / 1000).toFixed(2).replace('.', ',') + ' km';
    return Math.round(n) + ' m';
  }

  function formatEta(sec, arrived) {
    if (arrived) return 'Arrivé';
    var n = num(sec);
    if (n == null) return '';
    n = Math.max(0, Math.round(n));
    var mm = Math.floor(n / 60);
    var ss = n % 60;
    return (mm < 10 ? '0' : '') + mm + ':' + (ss < 10 ? '0' : '') + ss;
  }

  function formatAlt(u) {
    var air = u && u.air;
    if (air && air.altitude_ft != null) return String(Math.round(Number(air.altitude_ft))).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ft';
    var m = num(air && air.altitude);
    if (m == null && u) m = num(u.alt);
    if (m == null) return '';
    return String(Math.round(m * 3.28084)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ft';
  }

  function formatVs(u) {
    var air = u && u.air;
    if (!air) return '';
    if (air.vertical_speed_fpm != null) {
      var f = Math.round(Number(air.vertical_speed_fpm));
      return (f > 0 ? '+' : '') + f + ' ft/min';
    }
    var vs = num(air.vertical_speed);
    if (vs == null) return '';
    var fpm = Math.round(vs * 196.85);
    return (fpm > 0 ? '+' : '') + fpm + ' ft/min';
  }

  function isMoving(u) {
    var m = motionOf(u);
    var st = String(m.status || '').toUpperCase();
    if (st === 'STATIC' || st === 'UNKNOWN' || st === '') return false;
    var spd = num(m.speed_current != null ? m.speed_current : (u && u.speed));
    if (spd != null && spd * 3.6 < 0.5) return false;
    return num(u && u.movement_heading) != null || num(m.eta_speed) != null;
  }

  function destLabel(asg) {
    if (!asg) return '';
    return String(asg.destination_label || '').trim();
  }

  function parseExtra(u) {
    if (!u) return {};
    try {
      if (typeof u.extra === 'string') return JSON.parse(u.extra || '{}') || {};
      if (u.extra && typeof u.extra === 'object') return u.extra;
    } catch (e) {}
    return {};
  }

  function isPhone(u) {
    var ex = parseExtra(u);
    if (ex.phone_geoloc === true || ex.phone_geoloc === 1 || ex.phone_geoloc === '1' || ex.phone_geoloc === 'true') {
      return true;
    }
    return String(ex.source || '').toLowerCase() === 'phone';
  }

  function trackKind(u) {
    if (isPhone(u)) return 'phone';
    if (isAir(u)) return 'air';
    var cat = String((motionOf(u).category) || '').toUpperCase();
    if (cat === 'GROUND_VEHICLE') return 'vehicle';
    var ex = parseExtra(u);
    var inVeh = ex.in_vehicle === true || ex.in_vehicle === 1 || ex.in_vehicle === '1' || ex.in_vehicle === 'true';
    if (inVeh) return 'vehicle';
    return 'infantry';
  }

  function trackColor(kind, lost) {
    if (lost) return '#f87171';
    if (kind === 'phone') return '#22d3ee';
    if (kind === 'vehicle') return '#f59e0b';
    if (kind === 'air') return '#60a5fa';
    return '#4ade80';
  }

  function trackKindLabel(kind) {
    if (kind === 'phone') return 'Téléphone';
    if (kind === 'vehicle') return 'Véhicule';
    if (kind === 'air') return 'Aérien';
    return 'À pied';
  }

  function gapSecFor(kind) {
    if (kind === 'phone') return 20;
    if (kind === 'vehicle') return 10;
    if (kind === 'air') return 8;
    return 15;
  }

  function plausibleSpeedMs(kind, lastSpeed) {
    var typical = kind === 'vehicle' ? 16 : (kind === 'air' ? 45 : (kind === 'phone' ? 1.6 : 1.4));
    var cap = kind === 'vehicle' ? 33 : (kind === 'air' ? 70 : (kind === 'phone' ? 3.5 : 3));
    var v = typical;
    var s = num(lastSpeed);
    if (s != null && s > 0.3) v = Math.max(s * 1.12, typical * 0.55);
    return Math.min(cap, v);
  }

  function reachRadiusM(kind, elapsedSec, lastSpeed) {
    if (!(elapsedSec > 6)) return 0;
    var cap = kind === 'vehicle' ? 3500 : (kind === 'air' ? 7000 : (kind === 'phone' ? 700 : 600));
    return Math.min(cap, plausibleSpeedMs(kind, lastSpeed) * elapsedSec);
  }

  return {
    esc: esc,
    num: num,
    motionOf: motionOf,
    assignmentOf: assignmentOf,
    isAir: isAir,
    isMoving: isMoving,
    isPhone: isPhone,
    trackKind: trackKind,
    trackColor: trackColor,
    trackKindLabel: trackKindLabel,
    gapSecFor: gapSecFor,
    reachRadiusM: reachRadiusM,
    statusLabel: statusLabel,
    trendLabel: trendLabel,
    courseLabel: courseLabel,
    confidenceLabel: confidenceLabel,
    formatHeading: formatHeading,
    formatSpeed: formatSpeed,
    formatDistance: formatDistance,
    formatEta: formatEta,
    formatAlt: formatAlt,
    formatVs: formatVs,
    destLabel: destLabel
  };
})();
