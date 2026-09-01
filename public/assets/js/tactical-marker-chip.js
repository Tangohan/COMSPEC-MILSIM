/**
 * Pastilles carte style marqueur 3D Arma/ACE (barre colorée, type en capitales, méta T+ / distance).
 * Partagé ATAK, TACMAP, Overwatch, espace opérationnel.
 */
window.TacticalMarkerChip = (function () {
  'use strict';

  var TYPES = {
    SPOTREP: { color: '#e4b429', title: 'SPOTREP', labelFr: 'Observation' },
    IMINI: { color: '#e23b3b', title: 'IMINI', labelFr: 'Renseignement immédiat' },
    INTREP: { color: '#e4b429', title: 'INTREP', labelFr: 'Renseignement' },
    SITREP: { color: '#4ea8d8', title: 'SITREP', labelFr: 'Situation' },
    SALUTE: { color: '#e9974a', title: 'SALUTE', labelFr: 'Compte rendu SALUTE' },
    CONTACT: { color: '#e23b3b', title: 'CONTACT', labelFr: 'Prise de contact' },
    TIC: { color: '#e23b3b', title: 'TIC', labelFr: 'Contact' },
    BDA: { color: '#d97706', title: 'BDA', labelFr: 'Bilan des dégâts' },
    FRAGO: { color: '#cfd6de', title: 'FRAGO', labelFr: 'Ordre fragmentaire' },
    EAGLE_DOWN: { color: '#f472b6', title: 'EAGLE DOWN', labelFr: 'Opérateur à terre' },
    MEDEVAC: { color: '#f472b6', title: 'MEDEVAC', labelFr: 'Évacuation sanitaire' },
    PATROLREP: { color: '#9ca36a', title: 'PATROLREP', labelFr: 'Compte rendu de patrouille' },
    OTHER: { color: '#9aa3ad', title: 'RAPPORT', labelFr: 'Rapport' }
  };

  var KIND_RE = /\b(SPOTREP|IMINI|INTREP|SITREP|SALUTE|CONTACT|PATROLREP|MEDEVAC|EAGLE[_\s-]?DOWN|FRAGO|BDA|TIC)\b/i;

  var ALERT_KIND = {
    tic: 'CONTACT',
    salute: 'SALUTE',
    frago: 'FRAGO',
    bda: 'BDA',
    eagle_down: 'EAGLE_DOWN',
    spotrep: 'SPOTREP',
    sitrep: 'SITREP',
    imini: 'IMINI',
    contact: 'CONTACT',
    medevac: 'MEDEVAC'
  };

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function normalizeKind(raw) {
    var k = String(raw || '').toUpperCase().trim().replace(/[\s-]+/g, '_');
    if (k === 'EAGLEDOWN') k = 'EAGLE_DOWN';
    if (TYPES[k]) return k;
    return '';
  }

  function specFor(kind) {
    var k = normalizeKind(kind);
    return TYPES[k] || TYPES.OTHER;
  }

  function detectKind(data) {
    if (!data || typeof data !== 'object') return '';
    var direct = normalizeKind(data.report_type || data.reportType || data.kind || '');
    if (direct && TYPES[direct] && direct !== 'OTHER') return direct;
    if (direct === 'OTHER' && String(data.report_type || data.reportType || '').toUpperCase() === 'OTHER') {
      return 'OTHER';
    }
    var hay = [data.text, data.label, data.name, data.message, data.title, data.summary].join(' ');
    var m = hay.match(KIND_RE);
    if (m) return normalizeKind(m[1]);
    var alertKind = ALERT_KIND[String(data.kind || data.alert_kind || '').toLowerCase()];
    if (alertKind) return alertKind;
    return '';
  }

  function shouldUseChip(data) {
    return !!detectKind(data);
  }

  function resolveColor(data, kind) {
    var spec = specFor(kind);
    var raw = data && (data.color || data.markerColor);
    if (raw && window.ArmaMapMarkers && typeof window.ArmaMapMarkers.armaColorHex === 'function') {
      var hex = window.ArmaMapMarkers.armaColorHex(raw);
      if (hex && hex !== '#ef4444') return hex;
      var asText = String(raw).toLowerCase();
      if (asText.charAt(0) === '#' || asText.indexOf('color') >= 0 || asText.indexOf('red') >= 0
        || asText.indexOf('yellow') >= 0 || asText.indexOf('blue') >= 0) {
        return hex;
      }
    }
    if (raw && String(raw).charAt(0) === '#') return String(raw);
    return spec.color;
  }

  function parseTime(value) {
    if (!value) return NaN;
    if (typeof value === 'number' && isFinite(value)) {
      return value < 1e12 ? value * 1000 : value;
    }
    var t = Date.parse(String(value));
    if (isNaN(t)) {
      var iso = String(value).trim().replace(' ', 'T');
      t = Date.parse(iso);
    }
    return isNaN(t) ? NaN : t;
  }

  function formatElapsed(fromMs, nowMs) {
    var start = Number(fromMs);
    if (!isFinite(start)) return '';
    var now = nowMs != null ? Number(nowMs) : Date.now();
    var sec = Math.max(0, Math.floor((now - start) / 1000));
    var h = Math.floor(sec / 3600);
    var m = Math.floor((sec % 3600) / 60);
    if (h > 0) return 'T+' + h + 'h ' + m + 'm';
    if (m > 0) return 'T+' + m + 'm';
    return 'T+' + sec + 's';
  }

  function formatDistanceM(meters) {
    var n = Number(meters);
    if (!isFinite(n) || n < 0) return '';
    if (n >= 1000) {
      var km = n / 1000;
      return (km >= 10 ? Math.round(km) : km.toFixed(1).replace(/\.0$/, '')) + ' km';
    }
    return Math.round(n) + ' m';
  }

  function pickTimestamp(data) {
    if (!data) return NaN;
    return parseTime(
      data.report_timestamp || data.event_timestamp || data.first_seen_at
      || data.created_at || data.createdAt || data.timestamp || data.dtg || data.time
    );
  }

  function subtitleFrom(data, opts) {
    opts = opts || {};
    if (opts.subtitle) return String(opts.subtitle);
    if (data && data.subtitle) return String(data.subtitle);
    if (opts.distanceM != null) {
      var dist = formatDistanceM(opts.distanceM);
      if (dist) return dist;
    }
    if (data && data.distance_m != null) {
      var d2 = formatDistanceM(data.distance_m);
      if (d2) return d2;
    }
    var elapsed = formatElapsed(pickTimestamp(data), opts.now);
    if (elapsed) return elapsed;
    return '';
  }

  function html(opts) {
    opts = opts || {};
    var kind = normalizeKind(opts.kind) || detectKind(opts) || 'OTHER';
    var spec = specFor(kind);
    var color = opts.color || spec.color;
    var title = String(opts.title || spec.title || kind).toUpperCase();
    var meta = opts.subtitle ? String(opts.subtitle) : '';
    var billboard = opts.billboard !== false;
    return '<span class="tactical-marker-chip' + (billboard ? ' atak-marker-billboard' : '') +
      '" style="--chip-color:' + escapeHtml(color) + '">' +
      '<span class="tactical-marker-chip__bar" aria-hidden="true"></span>' +
      '<span class="tactical-marker-chip__row">' +
      '<span class="tactical-marker-chip__diamond" aria-hidden="true"></span>' +
      '<span class="tactical-marker-chip__title">' + escapeHtml(title) + '</span>' +
      '</span>' +
      (meta ? '<span class="tactical-marker-chip__meta">' + escapeHtml(meta) + '</span>' : '') +
      '</span>';
  }

  function keyOf(opts) {
    opts = opts || {};
    return [opts.kind || '', opts.title || '', opts.subtitle || '', opts.color || ''].join('|');
  }

  function leafletDivIcon(L, opts) {
    if (!L || !L.divIcon) return null;
    var markup = html(opts);
    return L.divIcon({
      className: 'tactical-marker-chip-icon atak-compact-marker',
      html: markup,
      iconSize: [1, 1],
      iconAnchor: [0, 0],
      popupAnchor: [0, -36]
    });
  }

  function fromMarkerData(data, extra) {
    extra = extra || {};
    var kind = extra.kind || detectKind(data) || 'OTHER';
    var spec = specFor(kind);
    return {
      kind: kind,
      title: extra.title || spec.title,
      color: extra.color || resolveColor(data, kind),
      subtitle: subtitleFrom(data, extra),
      billboard: extra.billboard
    };
  }

  function fromReport(report, extra) {
    extra = extra || {};
    var kind = normalizeKind(report && (report.report_type || report.reportType)) || detectKind(report) || 'OTHER';
    var spec = specFor(kind);
    return {
      kind: kind,
      title: extra.title || spec.title,
      color: extra.color || spec.color,
      subtitle: subtitleFrom(report, extra),
      billboard: extra.billboard
    };
  }

  function fromAlert(alert, extra) {
    extra = extra || {};
    var mapped = ALERT_KIND[String((alert && alert.kind) || '').toLowerCase()] || detectKind(alert) || 'OTHER';
    var spec = specFor(mapped);
    return {
      kind: mapped,
      title: extra.title || spec.title,
      color: extra.color || spec.color,
      subtitle: subtitleFrom(alert, extra),
      billboard: extra.billboard
    };
  }

  function fromIntel(report, extra) {
    extra = extra || {};
    var kind = normalizeKind(report && (report.report_type || report.reportType)) || 'SITREP';
    var spec = specFor(kind);
    return {
      kind: kind,
      title: extra.title || spec.title,
      color: extra.color || spec.color,
      subtitle: subtitleFrom(report, extra),
      billboard: extra.billboard
    };
  }

  return {
    TYPES: TYPES,
    detectKind: detectKind,
    shouldUseChip: shouldUseChip,
    specFor: specFor,
    formatElapsed: formatElapsed,
    formatDistanceM: formatDistanceM,
    html: html,
    keyOf: keyOf,
    leafletDivIcon: leafletDivIcon,
    fromMarkerData: fromMarkerData,
    fromReport: fromReport,
    fromAlert: fromAlert,
    fromIntel: fromIntel,
    pickTimestamp: pickTimestamp
  };
})();
