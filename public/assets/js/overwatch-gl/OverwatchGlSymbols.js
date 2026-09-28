/* Symboles APP-6 / MIL-STD-2525 pour Overwatch Relief 3D (milsymbol + NatoSidcIcons). */
window.OverwatchGlSymbols = (function () {
  'use strict';

  var cache = {};
  var cacheOrder = [];
  var CACHE_MAX = 240;

  function nato() {
    return window.NatoSidcIcons || null;
  }

  function extrasOf(unit) {
    var ex = unit && unit.extras;
    if (typeof ex === 'string') {
      try { ex = JSON.parse(ex); } catch (e) { ex = {}; }
    }
    return ex && typeof ex === 'object' ? ex : {};
  }

  function affiliationOf(unit) {
    var N = nato();
    var ex = extrasOf(unit);
    var raw = ex.affiliation || ex.affil || unit.affiliation || unit.side || unit.faction || unit.iff || 'friend';
    if (N && N.normalizeAffiliation) return N.normalizeAffiliation(raw);
    var s = String(raw || '').toLowerCase();
    if (/hostile|opfor|east|enemy/.test(s)) return 'hostile';
    if (/neutral|civ|guer/.test(s)) return 'neutral';
    if (/unknown|suspect/.test(s)) return /suspect/.test(s) ? 'suspect' : 'unknown';
    return 'friend';
  }

  function healthOf(unit) {
    var ex = extrasOf(unit);
    return String(ex.health || unit.health || '').toLowerCase();
  }

  function isCritical(unit) {
    var h = healthOf(unit);
    return /unconscious|cardiac|dead|kia|critical/.test(h);
  }

  function isWounded(unit) {
    var h = healthOf(unit);
    return /wound|injur/.test(h);
  }

  function isMedicalAlert(unit) {
    return isCritical(unit) || isWounded(unit);
  }

  function headingOf(unit) {
    var ex = extrasOf(unit);
    var arma = unit.arma && typeof unit.arma === 'object' ? unit.arma : {};
    var motion = unit.motion && typeof unit.motion === 'object' ? unit.motion : {};
    var candidates = [
      ex.movement_heading, unit.movement_heading, arma.movement_heading_deg,
      ex.heading_object, unit.heading_object, arma.heading_deg,
      ex.heading, unit.heading, motion.heading
    ];
    for (var i = 0; i < candidates.length; i++) {
      var n = Number(candidates[i]);
      if (isFinite(n)) return ((n % 360) + 360) % 360;
    }
    return null;
  }

  function speedMsOf(unit) {
    var ex = extrasOf(unit);
    var arma = unit.arma && typeof unit.arma === 'object' ? unit.arma : {};
    var motion = unit.motion && typeof unit.motion === 'object' ? unit.motion : {};
    var candidates = [ex.speed_ms, unit.speed_ms, arma.speed_ms, motion.speed_ms, unit.speed];
    for (var i = 0; i < candidates.length; i++) {
      var n = Number(candidates[i]);
      if (isFinite(n) && n >= 0) return n;
    }
    return 0;
  }

  function ageSecOf(unit) {
    var ex = extrasOf(unit);
    var stamp = ex.last_seen_at || unit.updated_at || unit.last_seen_at || unit.last_seen || '';
    if (!stamp) return 0;
    var t = Date.parse(stamp);
    if (!isFinite(t)) {
      var n = Number(stamp);
      if (isFinite(n)) t = n < 1e12 ? n * 1000 : n;
    }
    if (!isFinite(t)) return 0;
    return Math.max(0, (Date.now() - t) / 1000);
  }

  function freshnessOf(unit) {
    var age = ageSecOf(unit);
    if (age <= 20) return 1;
    if (age <= 60) return 0.88;
    if (age <= 180) return 0.7;
    if (age <= 420) return 0.5;
    return 0.32;
  }

  function statusLetter(unit) {
    /* P = présent, A = anticipé / estimé (contact périmé). */
    return ageSecOf(unit) > 180 ? 'A' : 'P';
  }

  function symbolOpts(unit, callsign) {
    var N = nato();
    var ex = extrasOf(unit);
    var fields = N && N.symbolFieldsFromUnit
      ? N.symbolFieldsFromUnit(unit, ex)
      : {
        affiliation: affiliationOf(unit),
        role: unit.role || ex.role || '',
        sidc: ex.sidc || unit.sidc || '',
        platform: ex.platform || unit.vehicle_class || '',
        vehicle: ex.vehicle || ex.vehicle_type || '',
        in_vehicle: ex.in_vehicle,
        aircraftType: ex.aircraft_type || unit.aircraft_type || ''
      };
    var roleKey = N && N.guessRole
      ? N.guessRole(fields.role, fields.aircraftType, fields)
      : 'infantry';
    var heading = headingOf(unit);
    var sidc = fields.sidc || '';
    if (!sidc && N && N.resolveSidc) {
      sidc = N.resolveSidc({
        affiliation: fields.affiliation,
        role: fields.role,
        roleKey: roleKey,
        platform: fields.platform,
        vehicle: fields.vehicle,
        in_vehicle: fields.in_vehicle,
        aircraftType: fields.aircraftType,
        functionid: fields.functionid
      });
    }
    if (sidc && sidc.length >= 4) {
      sidc = sidc.slice(0, 3) + statusLetter(unit) + sidc.slice(4);
    }
    return {
      sidc: sidc,
      affiliation: fields.affiliation || affiliationOf(unit),
      role: fields.role,
      roleKey: roleKey,
      platform: fields.platform,
      vehicle: fields.vehicle,
      in_vehicle: fields.in_vehicle,
      aircraftType: fields.aircraftType,
      heading: heading,
      health: healthOf(unit),
      callsign: callsign || '',
      size: 34
    };
  }

  function cacheKey(opts) {
    return [
      opts.sidc || '',
      opts.affiliation || '',
      opts.roleKey || '',
      opts.heading == null ? '' : Math.round(Number(opts.heading) / 15) * 15,
      opts.health || '',
      opts.size || 34
    ].join('|');
  }

  function remember(key, value) {
    if (cache[key]) return value;
    cache[key] = value;
    cacheOrder.push(key);
    while (cacheOrder.length > CACHE_MAX) {
      var old = cacheOrder.shift();
      delete cache[old];
    }
    return value;
  }

  function canvasToUrl(canvas) {
    try {
      return canvas.toDataURL('image/png');
    } catch (e) {
      return '';
    }
  }

  function milsymbolIcon(opts) {
    if (!window.ms || typeof window.ms.Symbol !== 'function') return null;
    try {
      var sidc = opts.sidc;
      if (!sidc && nato() && nato().resolveSidc) sidc = nato().resolveSidc(opts);
      if (!sidc) return null;
      var size = opts.size || 34;
      var symOpts = {
        size: size,
        uniqueDesignation: '',
        infoFields: false,
        outlineWidth: 4,
        outlineColor: 'rgba(0,0,0,0.9)',
        fill: true,
        frame: true
      };
      if (opts.heading != null && isFinite(Number(opts.heading))) {
        symOpts.direction = Number(opts.heading);
      }
      var sym = new window.ms.Symbol(sidc, symOpts);
      if (sym.isValid && sym.isValid() === false) return null;
      /* 2–3× la taille affichée pour netteté / anti-scintillement. */
      var dpr = Math.max(2, Math.min(3, Math.round((window.devicePixelRatio || 1) * 1.5) || 2));
      var canvas = null;
      if (typeof sym.asCanvas === 'function') {
        canvas = sym.asCanvas(dpr);
      }
      if (!canvas && typeof sym.asSVG === 'function') {
        var svg = sym.asSVG();
        if (!svg) return null;
        var url = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
        var sz = sym.getSize ? sym.getSize() : { width: size, height: size };
        var anchor = sym.getAnchor ? sym.getAnchor() : { x: size / 2, y: size / 2 };
        return {
          url: url,
          width: Math.ceil(sz.width || size),
          height: Math.ceil(sz.height || size),
          anchorX: Math.round(anchor.x != null ? anchor.x : size / 2),
          anchorY: Math.round(anchor.y != null ? anchor.y : size / 2),
          sidc: sidc
        };
      }
      if (!canvas) return null;
      var urlC = canvasToUrl(canvas);
      if (!urlC) return null;
      var anc = sym.getAnchor ? sym.getAnchor() : { x: canvas.width / (2 * dpr), y: canvas.height / (2 * dpr) };
      return {
        url: urlC,
        width: Math.ceil(canvas.width / dpr),
        height: Math.ceil(canvas.height / dpr),
        anchorX: Math.round(anc.x != null ? anc.x : size / 2),
        anchorY: Math.round(anc.y != null ? anc.y : size / 2),
        sidc: sidc
      };
    } catch (e2) {
      return null;
    }
  }

  function fallbackIcon(opts) {
    var N = nato();
    var aff = (N && N.normalizeAffiliation ? N.normalizeAffiliation(opts.affiliation) : 'friend');
    var size = opts.size || 34;
    var markup = N && N.svgMarkup
      ? N.svgMarkup({
        affiliation: aff,
        role: opts.role,
        roleKey: opts.roleKey,
        size: size,
        heading: opts.heading,
        showLabel: false,
        health: opts.health
      })
      : '';
    var svgMatch = markup && markup.match(/<svg[\s\S]*<\/svg>/i);
    var svg = svgMatch ? svgMatch[0] : (
      '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size + '" viewBox="0 0 32 32">'
      + '<rect x="4" y="8" width="24" height="16" fill="#80e0ff" stroke="#1e3a5f" stroke-width="1.5"/>'
      + '</svg>'
    );
    return {
      url: 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg),
      width: size,
      height: size,
      anchorX: size / 2,
      anchorY: size / 2,
      sidc: opts.sidc || ''
    };
  }

  function iconForOpts(opts) {
    opts = opts || {};
    var key = cacheKey(opts);
    if (cache[key]) return cache[key];
    var icon = milsymbolIcon(opts) || fallbackIcon(opts);
    return remember(key, icon);
  }

  function iconForUnit(unit, callsign) {
    return iconForOpts(symbolOpts(unit, callsign));
  }

  function altitudeAslOf(unit) {
    var ex = extrasOf(unit);
    var arma = unit.arma && typeof unit.arma === 'object' ? unit.arma : {};
    var candidates = [ex.asl_z, ex.pos_z, ex.altitude, unit.pos_z, unit.altitude, arma.altitude_m];
    for (var i = 0; i < candidates.length; i++) {
      var n = Number(candidates[i]);
      if (isFinite(n) && Math.abs(n) > 0.5) return n;
    }
    return null;
  }

  function isAircraft(unit) {
    var opts = symbolOpts(unit, '');
    if (/aviation|uav/.test(String(opts.roleKey || ''))) return true;
    var ex = extrasOf(unit);
    var plat = String(ex.platform || ex.vehicle_class || unit.vehicle_class || unit.aircraft_type || '').toLowerCase();
    return /heli|air|plane|uav|drone|rotary|fixed/.test(plat);
  }

  function velocityPath(lng, lat, z, headingDeg, speedMs) {
    if (headingDeg == null || !isFinite(headingDeg) || !(speedMs > 0.4)) return null;
    var lenM = Math.min(90, Math.max(8, speedMs * 4.5));
    var rad = headingDeg * Math.PI / 180;
    var dLng = (Math.sin(rad) * lenM) / 111319.49;
    var dLat = (Math.cos(rad) * lenM) / 111319.49;
    return [[lng, lat, z], [lng + dLng, lat + dLat, z]];
  }

  function sectorFan(lng, lat, z, headingDeg, rangeM, halfAngleDeg, steps) {
    if (headingDeg == null || !isFinite(headingDeg) || !(rangeM > 5)) return null;
    steps = steps || 10;
    halfAngleDeg = halfAngleDeg == null ? 28 : halfAngleDeg;
    var ring = [[lng, lat, z]];
    var i;
    for (i = 0; i <= steps; i++) {
      var a = (headingDeg - halfAngleDeg + (2 * halfAngleDeg * i / steps)) * Math.PI / 180;
      var dLng = (Math.sin(a) * rangeM) / 111319.49;
      var dLat = (Math.cos(a) * rangeM) / 111319.49;
      ring.push([lng + dLng, lat + dLat, z]);
    }
    ring.push([lng, lat, z]);
    return ring;
  }

  function clusterIcon(count) {
    var n = Math.max(2, Number(count) || 2);
    var size = 36;
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size + '" viewBox="0 0 36 36">'
      + '<rect x="3" y="8" width="30" height="20" rx="2" fill="#1a2420" stroke="#d4af37" stroke-width="2"/>'
      + '<text x="18" y="22" text-anchor="middle" font-family="IBM Plex Mono, monospace" font-size="13" font-weight="700" fill="#f4f7f2">'
      + n + '</text></svg>';
    return {
      url: 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg),
      width: size,
      height: size,
      anchorX: size / 2,
      anchorY: size / 2
    };
  }

  return {
    affiliationOf: affiliationOf,
    headingOf: headingOf,
    speedMsOf: speedMsOf,
    ageSecOf: ageSecOf,
    freshnessOf: freshnessOf,
    isMedicalAlert: isMedicalAlert,
    isCritical: isCritical,
    symbolOpts: symbolOpts,
    iconForUnit: iconForUnit,
    iconForOpts: iconForOpts,
    velocityPath: velocityPath,
    sectorFan: sectorFan,
    altitudeAslOf: altitudeAslOf,
    isAircraft: isAircraft,
    clusterIcon: clusterIcon,
    clearCache: function () { cache = {}; cacheOrder = []; }
  };
})();
