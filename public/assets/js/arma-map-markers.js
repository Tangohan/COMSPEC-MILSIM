/**
 * Rendu marqueurs Arma (CfgMarkers vanilla) — mil_* / hd_*, NATO b_/o_/n_/c_*, flags, Contact_*.
 * Aligné patterns cTabIRL (classname → icône + dir) sans copier leur code.
 * Partagé ATAK / TACMAP.
 */
window.ArmaMapMarkers = (function () {
  'use strict';

  /** Formes hand-drawn / military (suffixe après mil_ / hd_). */
  var HANDDRAWN = {
    dot: true, objective: true, flag: true, ambush: true, destroy: true,
    start: true, end: true, pickup: true, join: true, warning: true,
    unknown: true, arrow: true, arrow2: true, triangle: true, box: true,
    circle: true, marker: true
  };

  /** Rôles CfgMarkers NATO → clé NatoSidcIcons / MilstdCatalog. */
  var NATO_ROLE = {
    inf: 'infantry',
    mech_inf: 'armor',
    motor_inf: 'logistics',
    armor: 'armor',
    art: 'artillery',
    mortar: 'artillery',
    antiair: 'artillery',
    air: 'aviation_rotary',
    plane: 'aviation_fixed',
    uav: 'uav',
    recon: 'recon',
    hq: 'hq',
    med: 'medical',
    support: 'logistics',
    maint: 'logistics',
    service: 'logistics',
    installation: 'hq',
    naval: 'logistics',
    ordnance: 'artillery',
    unknown: 'infantry',
    car: 'logistics',
    ship: 'logistics'
  };

  var AFF_FROM_PREFIX = {
    b: 'friend',
    o: 'hostile',
    n: 'neutral',
    c: 'neutral',
    u: 'unknown'
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

  function readDir(data) {
    if (!data) return 0;
    var raw = data.dir != null ? data.dir : data.heading;
    var n = parseFloat(raw);
    if (isNaN(n)) return 0;
    // Normalise dans [0, 360)
    n = n % 360;
    if (n < 0) n += 360;
    return n;
  }

  /**
   * Décode un classname CfgMarkers → { kind, typeKey, affiliation, roleKey }.
   * kind: 'handdrawn' | 'nato' | 'flag' | 'contact' | 'unknown'
   */
  function decodeType(data) {
    var raw = normalizeTypeKey(data && (data.type || data.icon || data.name));
    if (!raw) return { kind: 'handdrawn', typeKey: 'dot', affiliation: '', roleKey: '' };

    // NATO / APP-6 affiliations : b_inf, o_armor, n_recon, c_car, u_*
    var nato = raw.match(/^([boncu])_(.+)$/);
    if (nato) {
      var roleRaw = nato[2];
      var roleKey = NATO_ROLE[roleRaw] || NATO_ROLE[roleRaw.replace(/_ca$/, '')] || 'infantry';
      return {
        kind: 'nato',
        typeKey: raw,
        affiliation: AFF_FROM_PREFIX[nato[1]] || 'friend',
        roleKey: roleKey
      };
    }

    // Drapeaux faction
    if (raw.indexOf('flag_') === 0 || raw === 'flag') {
      return { kind: 'flag', typeKey: 'flag', affiliation: '', roleKey: '' };
    }

    // Contact DLC arrows / shapes
    if (raw.indexOf('contact_') === 0) {
      var rest = raw.slice(8);
      if (rest.indexOf('arrow') === 0) {
        return { kind: 'handdrawn', typeKey: 'arrow', affiliation: '', roleKey: '' };
      }
      if (rest.indexOf('circle') === 0 || rest.indexOf('pencilcircle') === 0) {
        return { kind: 'handdrawn', typeKey: 'circle', affiliation: '', roleKey: '' };
      }
      if (rest.indexOf('dot') === 0 || rest.indexOf('pencildot') === 0) {
        return { kind: 'handdrawn', typeKey: 'dot', affiliation: '', roleKey: '' };
      }
      return { kind: 'handdrawn', typeKey: 'marker', affiliation: '', roleKey: '' };
    }

    // mil_* / hd_*
    if (raw.indexOf('hd_') === 0) {
      var hd = raw.slice(3);
      return { kind: 'handdrawn', typeKey: HANDDRAWN[hd] ? hd : 'dot', affiliation: '', roleKey: '' };
    }
    if (raw.indexOf('mil_') === 0) {
      var mil = raw.slice(4);
      return { kind: 'handdrawn', typeKey: HANDDRAWN[mil] ? mil : 'dot', affiliation: '', roleKey: '' };
    }

    if (HANDDRAWN[raw]) {
      return { kind: 'handdrawn', typeKey: raw, affiliation: '', roleKey: '' };
    }

    // respawn_*, Empty, waypoint, loc_*, selector_* → point neutre
    if (/^(empty|emptyicon|respawn_|waypoint|loc_|selector_|kia)/.test(raw)) {
      return { kind: 'handdrawn', typeKey: 'dot', affiliation: '', roleKey: '' };
    }

    for (var known in HANDDRAWN) {
      if (HANDDRAWN.hasOwnProperty(known) && raw.indexOf(known) >= 0) {
        return { kind: 'handdrawn', typeKey: known, affiliation: '', roleKey: '' };
      }
    }

    return { kind: 'unknown', typeKey: 'dot', affiliation: '', roleKey: '' };
  }

  function baseTypeKey(data) {
    return decodeType(data).typeKey;
  }

  function isArmaStyleMarker(data) {
    if (!data || typeof data !== 'object') return false;
    if (data.symbolMode === 'tactical' || data.sidc || data.icon === 'milsymbol') return false;
    if (data.type === 'manual' && (data.color || data.icon || data.size)) return false;
    var t = String(data.type || '');
    if (/^(mil_|hd_|b_|o_|n_|c_|u_|flag_|contact_)/i.test(t)) return true;
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
    if (k === 'start' || k === 'end' || k === 'arrow' || k === 'arrow2') {
      return '<span style="display:inline-block;width:0;height:0;border-top:6px solid transparent;border-bottom:6px solid transparent;border-left:12px solid ' + c + ';"></span>';
    }
    return '<span style="display:inline-block;width:11px;height:11px;border-radius:50%;background:' + c + ';border:2px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.25);"></span>';
  }

  function labelOf(data) {
    if (!data) return '';
    return String(data.text || data.label || data.name || '').trim();
  }

  function needsRotation(typeKey) {
    return typeKey === 'arrow' || typeKey === 'arrow2' || typeKey === 'start' || typeKey === 'end';
  }

  function natoOptsFromData(data, decoded) {
    var label = labelOf(data);
    var dir = readDir(data);
    return {
      affiliation: decoded.affiliation || 'friend',
      roleKey: decoded.roleKey || 'infantry',
      callSign: label ? String(label).substring(0, 12) : '',
      label: label,
      showLabel: !!label,
      size: 28,
      heading: dir || undefined
    };
  }

  /**
   * @returns {{ html: string, color: string, typeKey: string, label: string, iconSize: number[], iconAnchor: number[], kind: string }}
   */
  function buildIconSpec(data) {
    var decoded = decodeType(data || {});
    var color = armaColorHex((data && (data.colorHex || data.color)) || '');
    // Couleur d’affiliation OTAN si ColorDefault / vide
    if (decoded.kind === 'nato' && (!data.color || /^ColorDefault$/i.test(String(data.color)))) {
      if (decoded.affiliation === 'friend') color = '#4e9de0';
      else if (decoded.affiliation === 'hostile') color = '#d9534f';
      else if (decoded.affiliation === 'neutral') color = '#4ec94e';
      else if (decoded.affiliation === 'unknown') color = '#e7cc5b';
    }
    var label = labelOf(data);
    var dir = readDir(data);
    var typeKey = decoded.typeKey;

    // Préférer milsymbol / SVG OTAN pour b_*/o_*/n_*/c_*
    if (decoded.kind === 'nato' && window.NatoSidcIcons && typeof window.NatoSidcIcons.svgMarkup === 'function') {
      var natoHtml = window.NatoSidcIcons.svgMarkup(natoOptsFromData(data, decoded));
      return {
        html: natoHtml,
        color: color,
        typeKey: typeKey,
        label: label,
        kind: 'nato',
        iconSize: [80, label ? 44 : 32],
        iconAnchor: [40, label ? 22 : 16]
      };
    }

    var rotateStyle = '';
    if (dir && needsRotation(typeKey)) {
      rotateStyle = 'transform:rotate(' + dir + 'deg);transform-origin:center center;';
    }

    var shape = shapeHtml(typeKey, color);
    var html = '<div style="display:flex;flex-direction:column;align-items:center;gap:1px;pointer-events:none;">' +
      '<span style="display:inline-flex;align-items:center;justify-content:center;' + rotateStyle + '">' + shape + '</span>' +
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
      kind: decoded.kind,
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

  /** Badge liste (panneau historique). */
  function listBadgeHtml(data) {
    var decoded = decodeType(data || {});
    if (decoded.kind === 'nato' && window.NatoSidcIcons && typeof window.NatoSidcIcons.listBadgeHtml === 'function') {
      return window.NatoSidcIcons.listBadgeHtml(natoOptsFromData(data, decoded));
    }
    var spec = buildIconSpec(data);
    return '<span class="arma-marker-list-badge" style="display:inline-flex;vertical-align:middle;margin-right:6px;transform:scale(.85);">' +
      spec.html +
      '</span>';
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
    decodeType: decodeType,
    isArmaStyleMarker: isArmaStyleMarker,
    buildIconSpec: buildIconSpec,
    leafletDivIcon: leafletDivIcon,
    listBadgeHtml: listBadgeHtml,
    parsePos: parsePos,
    labelOf: labelOf,
    readDir: readDir
  };
})();
