/**
 * Rendu marqueurs Arma (type mil_*/hd_* + Color*) — aligné Athena-Web-2 RelayMarkerLayer.
 * Partagé ATAK / TACMAP.
 */
window.ArmaMapMarkers = (function () {
  'use strict';

  var HANDDRAWN = {
    dot: true, objective: true, flag: true, ambush: true, destroy: true,
    start: true, end: true, pickup: true, join: true, warning: true,
    unknown: true, arrow: true, triangle: true, box: true, circle: true, marker: true
  };

  function armaColorHex(colorName) {
    var key = String(colorName || '').toLowerCase();
    if (key.indexOf('blufor') >= 0 || key.indexOf('west') >= 0) return '#4e9de0';
    if (key.indexOf('opfor') >= 0 || key.indexOf('east') >= 0) return '#d9534f';
    if (key.indexOf('independent') >= 0 || key.indexOf('guer') >= 0 || key.indexOf('resistance') >= 0) return '#4ec94e';
    if (key.indexOf('civilian') >= 0 || key.indexOf('civ') >= 0) return '#cfcfcf';
    if (key.indexOf('unknown') >= 0) return '#b9b9b9';
    if (key.indexOf('red') >= 0) return '#d9534f';
    if (key.indexOf('blue') >= 0) return '#4e9de0';
    if (key.indexOf('green') >= 0) return '#4ec94e';
    if (key.indexOf('yellow') >= 0) return '#e7cc5b';
    if (key.indexOf('orange') >= 0) return '#e9974a';
    if (key.indexOf('pink') >= 0) return '#d783b7';
    if (key.indexOf('brown') >= 0 || key.indexOf('khaki') >= 0) return '#a16207';
    if (key.indexOf('white') >= 0) return '#f2f2f2';
    if (key.indexOf('black') >= 0) return '#222222';
    if (key.charAt(0) === '#') return key;
    return '#ef4444';
  }

  function normalizeTypeKey(value) {
    return String(value || '')
      .trim()
      .toLowerCase()
      .replace(/\s+/g, '_')
      .replace(/-/g, '_');
  }

  function baseTypeKey(data) {
    var candidates = [
      normalizeTypeKey(data && data.type),
      normalizeTypeKey(data && data.icon),
      normalizeTypeKey(data && data.name)
    ];
    for (var i = 0; i < candidates.length; i++) {
      var c = candidates[i];
      if (!c) continue;
      if (HANDDRAWN[c]) return c;
      if (c.indexOf('hd_') === 0) {
        var hd = c.slice(3);
        if (HANDDRAWN[hd]) return hd;
      }
      if (c.indexOf('mil_') === 0) {
        var mil = c.slice(4);
        if (HANDDRAWN[mil]) return mil;
      }
      for (var known in HANDDRAWN) {
        if (HANDDRAWN.hasOwnProperty(known) && c.indexOf(known) >= 0) return known;
      }
    }
    return 'dot';
  }

  function isArmaStyleMarker(data) {
    if (!data || typeof data !== 'object') return false;
    if (data.symbolMode === 'tactical' || data.sidc || data.icon === 'milsymbol') return false;
    if (data.type === 'manual' && (data.color || data.icon || data.size)) return false;
    var t = String(data.type || '');
    if (/^(mil_|hd_)/i.test(t)) return true;
    if (data.color && /^Color/i.test(String(data.color))) return true;
    if (data.shape && /ICON|ELLIPSE|RECTANGLE|POLYLINE/i.test(String(data.shape))) return true;
    return false;
  }

  function shapeHtml(typeKey, color) {
    var c = color || '#ef4444';
    var k = typeKey || 'dot';
    if (k === 'warning' || k === 'unknown') {
      return '<span style="display:inline-block;width:0;height:0;border-left:7px solid transparent;border-right:7px solid transparent;border-bottom:13px solid ' + c + ';filter:drop-shadow(0 0 1px #000);"></span>';
    }
    if (k === 'triangle' || k === 'objective' || k === 'destroy' || k === 'ambush') {
      return '<span style="display:inline-block;width:0;height:0;border-left:7px solid transparent;border-right:7px solid transparent;border-bottom:13px solid ' + c + ';"></span>';
    }
    if (k === 'box' || k === 'marker' || k === 'flag') {
      return '<span style="display:inline-block;width:11px;height:11px;background:' + c + ';border:1px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.3);"></span>';
    }
    if (k === 'circle' || k === 'join' || k === 'pickup') {
      return '<span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:transparent;border:2px solid ' + c + ';box-shadow:0 0 0 1px rgba(0,0,0,.25);"></span>';
    }
    if (k === 'start' || k === 'end' || k === 'arrow') {
      return '<span style="display:inline-block;width:0;height:0;border-top:6px solid transparent;border-bottom:6px solid transparent;border-left:12px solid ' + c + ';"></span>';
    }
    return '<span style="display:inline-block;width:11px;height:11px;border-radius:50%;background:' + c + ';border:2px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.25);"></span>';
  }

  function labelOf(data) {
    if (!data) return '';
    return String(data.text || data.label || data.name || '').trim();
  }

  /**
   * @returns {{ html: string, color: string, typeKey: string, label: string, iconSize: number[], iconAnchor: number[] }}
   */
  function buildIconSpec(data) {
    var typeKey = baseTypeKey(data || {});
    var color = armaColorHex((data && (data.colorHex || data.color)) || '');
    var label = labelOf(data);
    var html = '<div style="display:flex;flex-direction:column;align-items:center;gap:1px;pointer-events:none;">' +
      shapeHtml(typeKey, color) +
      (label
        ? '<span style="font:700 9px/1.1 ui-sans-serif,system-ui,sans-serif;color:' + color +
          ';text-shadow:0 0 2px #000,0 1px 2px #000;white-space:nowrap;max-width:80px;overflow:hidden;text-overflow:ellipsis;">' +
          String(label).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;').slice(0, 16) +
          '</span>'
        : '') +
      '</div>';
    return {
      html: html,
      color: color,
      typeKey: typeKey,
      label: label,
      iconSize: [80, label ? 30 : 16],
      iconAnchor: [40, label ? 10 : 8]
    };
  }

  function leafletDivIcon(L, data) {
    if (!L || !L.divIcon) return null;
    var spec = buildIconSpec(data);
    return L.divIcon({
      className: 'arma-map-marker-icon',
      html: spec.html,
      iconSize: spec.iconSize,
      iconAnchor: spec.iconAnchor
    });
  }

  function parsePos(data) {
    if (!data || !data.pos) return null;
    var pos = data.pos;
    var x;
    var y;
    if (Array.isArray(pos[0])) {
      x = pos[0][0];
      y = pos[0][1];
    } else {
      x = pos[0];
      y = pos[1];
    }
    if (x == null || y == null || isNaN(Number(x)) || isNaN(Number(y))) return null;
    return { x: Number(x), y: Number(y) };
  }

  return {
    armaColorHex: armaColorHex,
    baseTypeKey: baseTypeKey,
    isArmaStyleMarker: isArmaStyleMarker,
    buildIconSpec: buildIconSpec,
    leafletDivIcon: leafletDivIcon,
    parsePos: parsePos,
    labelOf: labelOf
  };
})();
