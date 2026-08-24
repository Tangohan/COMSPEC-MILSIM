/**
 * Rendu marqueurs Arma (CfgMarkers vanilla + formes) — mil_* / hd_*, NATO b_/o_/n_/c_*,
 * loc_* (POI), flags, Contact_*, ELLIPSE / RECTANGLE / POLYLINE + brushes.
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
    mech_inf: 'mechanized',
    motor_inf: 'motorized',
    armor: 'armor',
    art: 'artillery',
    mortar: 'mortar',
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
    naval: 'naval',
    ordnance: 'artillery',
    unknown: 'infantry',
    car: 'motorized',
    ship: 'naval'
  };

  var AFF_FROM_PREFIX = {
    b: 'friend',
    o: 'hostile',
    n: 'neutral',
    c: 'neutral',
    u: 'unknown'
  };

  /** POI loc_* → glyphe + libellé FR. */
  var LOC_POI = {
    hospital: { glyph: 'cross', label: 'Poste médical' },
    fuelstation: { glyph: 'fuel', label: 'Station-service' },
    fuel: { glyph: 'fuel', label: 'Carburant' },
    church: { glyph: 'church', label: 'Église' },
    transmitter: { glyph: 'tower', label: 'Antenne / tour' },
    tower: { glyph: 'tower', label: 'Tour' },
    lighthouse: { glyph: 'tower', label: 'Phare' },
    power: { glyph: 'power', label: 'Électricité' },
    stack: { glyph: 'tower', label: 'Cheminée' },
    bunker: { glyph: 'bunker', label: 'Bunker' },
    quay: { glyph: 'port', label: 'Quai' },
    busstop: { glyph: 'dot', label: 'Arrêt' },
    tourism: { glyph: 'flag', label: 'Tourisme' },
    viewpoint: { glyph: 'eye', label: 'Point de vue' },
    rockarea: { glyph: 'mountain', label: 'Rocher' },
    fortification: { glyph: 'bunker', label: 'Fortification' },
    crossroad: { glyph: 'dot', label: 'Carrefour' },
    crossroads: { glyph: 'dot', label: 'Carrefour' }
  };

  function armaColorHex(colorName) {
    var key = String(colorName || '').toLowerCase();
    if (key.charAt(0) === '#') return key;
    if (/^[0-9a-f]{6}$/i.test(key)) return '#' + key;
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
    if (key.indexOf('pink') >= 0 || key.indexOf('purple') >= 0 || key.indexOf('fuchsia') >= 0) return '#a78bfa';
    if (key.indexOf('brown') >= 0 || key.indexOf('khaki') >= 0) return '#a16207';
    if (key.indexOf('white') >= 0) return '#f2f2f2';
    if (key.indexOf('black') >= 0) return '#222222';
    if (key.indexOf('grey') >= 0 || key.indexOf('gray') >= 0) return '#b9b9b9';
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
    n = n % 360;
    if (n < 0) n += 360;
    return n;
  }

  function readSize(data) {
    var s = data && data.size;
    if (Array.isArray(s) && s.length >= 2) {
      return [Math.max(1, Number(s[0]) || 1), Math.max(1, Number(s[1]) || 1)];
    }
    if (typeof s === 'number' && !isNaN(s)) return [Math.max(1, s), Math.max(1, s)];
    return [1, 1];
  }

  function readShape(data) {
    return String((data && data.shape) || 'ICON').toUpperCase();
  }

  function readBrush(data) {
    return String((data && data.brush) || 'Solid');
  }

  function readAlpha(data) {
    var a = data && data.alpha != null ? Number(data.alpha) : 1;
    if (isNaN(a)) return 1;
    return Math.max(0, Math.min(1, a));
  }

  /**
   * Décode un classname CfgMarkers → { kind, typeKey, affiliation, roleKey, locKey }.
   * kind: 'handdrawn' | 'nato' | 'flag' | 'contact' | 'loc' | 'unknown'
   */
  function decodeType(data) {
    var raw = normalizeTypeKey(data && (data.type || data.icon || data.name));
    if (!raw) return { kind: 'handdrawn', typeKey: 'dot', affiliation: '', roleKey: '', locKey: '' };

    // Catalogue enrichi (vanilla + MarkersPlus + Metis)
    if (window.ArmaMarkerCatalog && typeof window.ArmaMarkerCatalog.get === 'function') {
      var cat = window.ArmaMarkerCatalog.get(raw);
      if (cat) {
        if (cat.kind === 'nato' || cat.kind === 'metis') {
          var roleKeyOut = cat.roleKey || 'infantry';
          if (cat.role && NATO_ROLE[cat.role]) roleKeyOut = NATO_ROLE[cat.role];
          return {
            kind: 'nato',
            typeKey: cat.typeKey || raw,
            affiliation: cat.affiliation || 'friend',
            roleKey: roleKeyOut,
            locKey: '',
            catalogLabel: cat.label || ''
          };
        }
        if (cat.kind === 'mplus' || cat.kind === 'handdrawn') {
          var g = cat.glyph || 'marker';
          return {
            kind: (g === 'cross' || g === 'fuel' || g === 'church' || g === 'tower') ? 'loc' : 'handdrawn',
            typeKey: g,
            affiliation: '',
            roleKey: '',
            locKey: g,
            catalogLabel: cat.label || ''
          };
        }
      }
    }

    var nato = raw.match(/^([boncu])_(.+)$/);
    if (nato) {
      var roleRaw = nato[2].replace(/_ca$/, '');
      var roleKey = NATO_ROLE[roleRaw] || 'infantry';
      return {
        kind: 'nato',
        typeKey: raw,
        affiliation: AFF_FROM_PREFIX[nato[1]] || 'friend',
        roleKey: roleKey,
        locKey: ''
      };
    }

    if (raw.indexOf('flag_') === 0 || raw === 'flag') {
      return { kind: 'flag', typeKey: 'flag', affiliation: '', roleKey: '', locKey: '' };
    }

    if (raw.indexOf('contact_') === 0) {
      var rest = raw.slice(8);
      if (rest.indexOf('arrow') === 0) {
        return { kind: 'handdrawn', typeKey: 'arrow', affiliation: '', roleKey: '', locKey: '' };
      }
      if (rest.indexOf('circle') === 0 || rest.indexOf('pencilcircle') === 0) {
        return { kind: 'handdrawn', typeKey: 'circle', affiliation: '', roleKey: '', locKey: '' };
      }
      if (rest.indexOf('dot') === 0 || rest.indexOf('pencildot') === 0) {
        return { kind: 'handdrawn', typeKey: 'dot', affiliation: '', roleKey: '', locKey: '' };
      }
      return { kind: 'handdrawn', typeKey: 'marker', affiliation: '', roleKey: '', locKey: '' };
    }

    if (raw.indexOf('loc_') === 0) {
      var loc = raw.slice(4);
      var poi = LOC_POI[loc] || LOC_POI[loc.replace(/_ca$/, '')];
      return {
        kind: 'loc',
        typeKey: poi ? poi.glyph : 'dot',
        affiliation: '',
        roleKey: '',
        locKey: loc
      };
    }

    if (raw.indexOf('hd_') === 0) {
      var hd = raw.slice(3);
      return { kind: 'handdrawn', typeKey: HANDDRAWN[hd] ? hd : 'dot', affiliation: '', roleKey: '', locKey: '' };
    }
    if (raw.indexOf('mil_') === 0) {
      var mil = raw.slice(4);
      return { kind: 'handdrawn', typeKey: HANDDRAWN[mil] ? mil : 'dot', affiliation: '', roleKey: '', locKey: '' };
    }

    if (HANDDRAWN[raw]) {
      return { kind: 'handdrawn', typeKey: raw, affiliation: '', roleKey: '', locKey: '' };
    }

    if (/^(empty|emptyicon|respawn_|waypoint|selector_|kia)/.test(raw)) {
      return { kind: 'handdrawn', typeKey: 'dot', affiliation: '', roleKey: '', locKey: '' };
    }

    for (var known in HANDDRAWN) {
      if (HANDDRAWN.hasOwnProperty(known) && raw.indexOf(known) >= 0) {
        return { kind: 'handdrawn', typeKey: known, affiliation: '', roleKey: '', locKey: '' };
      }
    }

    return { kind: 'unknown', typeKey: 'dot', affiliation: '', roleKey: '', locKey: '' };
  }

  function baseTypeKey(data) {
    return decodeType(data).typeKey;
  }

  function isArmaStyleMarker(data) {
    if (!data || typeof data !== 'object') return false;
    if (data.symbolMode === 'tactical' || data.sidc || data.icon === 'milsymbol') return false;
    if (data.type === 'manual' && (data.color || data.icon || data.size)) return false;
    var t = String(data.type || '');
    if (/^(mil_|hd_|b_|o_|n_|c_|u_|flag_|contact_|loc_|mplus_|mts_)/i.test(t)) return true;
    if (data.color && /^Color/i.test(String(data.color))) return true;
    if (data.shape && /ICON|ELLIPSE|RECTANGLE|POLYLINE/i.test(String(data.shape))) return true;
    if (data.source === 'arma' || data.source === 'ctab_user' || data.source === 'ctab_route' || data.source === 'ctab_jump') return true;
    if (/^ace_/i.test(String(data.source || ''))) return true;
    return false;
  }

  function isAreaShape(data) {
    var sh = readShape(data);
    return sh === 'ELLIPSE' || sh === 'RECTANGLE' || sh === 'POLYLINE';
  }

  function typeLabelFr(data) {
    if (!data) return 'Repère';
    var src = String(data.source || '').toLowerCase();
    if (src === 'ace_poi') return data.symbolName ? String(data.symbolName) : 'Point d’intérêt';
    if (src.indexOf('medevac') >= 0) return 'Point d’évacuation sanitaire';
    if (src.indexOf('qrf') >= 0) return 'Point de renfort';
    if (src.indexOf('vehicle_service') >= 0) return 'Point de service véhicule';
    if (data.symbolName) return String(data.symbolName);
    var decoded = decodeType(data);
    if (decoded.catalogLabel) return decoded.catalogLabel;
    if (window.ArmaMarkerCatalog && typeof window.ArmaMarkerCatalog.labelFr === 'function') {
      var fromCat = window.ArmaMarkerCatalog.labelFr(data.type || data.icon || '', '');
      if (fromCat && fromCat !== 'Repère' && fromCat !== 'Repere') return fromCat;
    }
    var sh = readShape(data);
    if (sh === 'RECTANGLE') return 'Zone rectangulaire';
    if (sh === 'ELLIPSE') return 'Zone circulaire';
    if (sh === 'POLYLINE') return 'Tracé';
    if (decoded.kind === 'loc') {
      var poi = LOC_POI[decoded.locKey];
      if (poi) return poi.label;
      return 'Point d’intérêt';
    }
    if (decoded.kind === 'nato' || decoded.kind === 'metis') {
      var aff = decoded.affiliation === 'hostile' ? 'adverse'
        : decoded.affiliation === 'friend' ? 'ami'
        : decoded.affiliation === 'neutral' ? 'neutre'
        : 'inconnu';
      var role = {
        infantry: 'infanterie', armor: 'blindé', artillery: 'artillerie',
        aviation_rotary: 'hélicoptère', aviation_fixed: 'avion', uav: 'drone',
        recon: 'reconnaissance', hq: 'PC', medical: 'santé', logistics: 'soutien'
      }[decoded.roleKey] || 'unité';
      return 'Symbole ' + aff + ' — ' + role;
    }
    var tk = decoded.typeKey;
    var map = {
      warning: 'Alerte', unknown: 'Inconnu', triangle: 'Triangle', box: 'Carré',
      circle: 'Cercle', objective: 'Objectif', destroy: 'Destruction', ambush: 'Embuscade',
      start: 'Départ', end: 'Arrivée', join: 'Ralliement', pickup: 'Récupération',
      arrow: 'Flèche', flag: 'Drapeau', dot: 'Repère', marker: 'Repère',
      fuel: 'Carburant', church: 'Église', tower: 'Tour', cross: 'Poste médical',
      power: 'Électricité', bunker: 'Bunker', mountain: 'Relief', eye: 'Observation', port: 'Port'
    };
    return map[tk] || 'Repère';
  }

  function locGlyphHtml(glyph, color) {
    var c = color || '#ef4444';
    var box = 'display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:3px;background:rgba(0,0,0,.45);border:1px solid ' + c + ';';
    if (glyph === 'cross') {
      return '<span style="' + box + '"><span style="position:relative;width:10px;height:10px;">' +
        '<span style="position:absolute;left:4px;top:0;width:2px;height:10px;background:' + c + ';"></span>' +
        '<span style="position:absolute;left:0;top:4px;width:10px;height:2px;background:' + c + ';"></span></span></span>';
    }
    if (glyph === 'fuel') {
      return '<span style="' + box + '"><span style="width:7px;height:10px;border:2px solid ' + c + ';border-radius:1px;position:relative;">' +
        '<span style="position:absolute;right:-4px;top:2px;width:3px;height:5px;border:1.5px solid ' + c + ';border-left:0;border-radius:0 2px 2px 0;"></span></span></span>';
    }
    if (glyph === 'church') {
      return '<span style="' + box + '"><span style="position:relative;width:8px;height:11px;">' +
        '<span style="position:absolute;left:3px;top:0;width:2px;height:11px;background:' + c + ';"></span>' +
        '<span style="position:absolute;left:0;top:3px;width:8px;height:2px;background:' + c + ';"></span></span></span>';
    }
    if (glyph === 'tower') {
      return '<span style="' + box + '"><span style="width:0;height:0;border-left:5px solid transparent;border-right:5px solid transparent;border-bottom:12px solid ' + c + ';"></span></span>';
    }
    if (glyph === 'power') {
      return '<span style="' + box + '"><span style="width:0;height:0;border-left:5px solid transparent;border-right:5px solid transparent;border-top:7px solid ' + c +
        ';margin-top:2px;position:relative;"><span style="position:absolute;left:-3px;top:-10px;width:0;height:0;border-left:3px solid transparent;border-right:3px solid transparent;border-bottom:5px solid ' + c + ';"></span></span></span>';
    }
    if (glyph === 'bunker') {
      return '<span style="' + box + '"><span style="width:12px;height:8px;border:2px solid ' + c + ';border-bottom-width:3px;"></span></span>';
    }
    if (glyph === 'mountain') {
      return '<span style="' + box + '"><span style="width:0;height:0;border-left:7px solid transparent;border-right:7px solid transparent;border-bottom:12px solid ' + c + ';"></span></span>';
    }
    if (glyph === 'eye') {
      return '<span style="' + box + '"><span style="width:12px;height:7px;border:2px solid ' + c + ';border-radius:50%;position:relative;">' +
        '<span style="position:absolute;left:3px;top:0;width:4px;height:4px;border-radius:50%;background:' + c + ';"></span></span></span>';
    }
    if (glyph === 'port') {
      return '<span style="' + box + '"><span style="width:10px;height:2px;background:' + c + ';position:relative;">' +
        '<span style="position:absolute;left:4px;top:-6px;width:2px;height:6px;background:' + c + ';"></span>' +
        '<span style="position:absolute;left:1px;top:2px;width:8px;height:4px;border:2px solid ' + c + ';border-top:0;border-radius:0 0 4px 4px;"></span></span></span>';
    }
    if (glyph === 'flag') {
      return '<span style="' + box + '"><span style="width:2px;height:12px;background:' + c + ';position:relative;">' +
        '<span style="position:absolute;left:2px;top:0;width:8px;height:6px;background:' + c + ';"></span></span></span>';
    }
    return '<span style="display:inline-block;width:11px;height:11px;border-radius:50%;background:' + c + ';border:2px solid #fff;"></span>';
  }

  function shapeHtml(typeKey, color) {
    var c = color || '#ef4444';
    var k = typeKey || 'dot';
    if (k === 'fuel' || k === 'church' || k === 'tower' || k === 'cross' || k === 'power'
      || k === 'bunker' || k === 'mountain' || k === 'eye' || k === 'port') {
      return locGlyphHtml(k, c);
    }
    // Diamant hostile (Marker Dropper / APP-6) pour alerte / destruction / objectif rouge
    if (k === 'warning' || k === 'destroy' || k === 'objective' || k === 'ambush' || k === 'triangle') {
      var isAlert = (k === 'warning');
      return '<span style="display:inline-block;width:12px;height:12px;background:' + c +
        ';transform:rotate(45deg);border:1px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.35);' +
        (isAlert ? 'outline:1px solid rgba(255,255,255,.35);outline-offset:-3px;' : '') +
        '"></span>';
    }
    if (k === 'unknown') {
      // Trèfle / quatrefeuille
      return '<span style="display:inline-block;width:14px;height:14px;background:' + c +
        ';border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:1px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.3);"></span>';
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

  function prefixBadgeHtml(data, color) {
    var raw = labelOf(data);
    // Préfixe Marker Dropper : "T", "T1", "A-3", etc.
    var m = raw.match(/^([A-Za-z]{1,3})[\s\-]?(\d{0,4})$/);
    if (!m) return '';
    var badge = m[1] + (m[2] || '');
    if (badge.length > 4) badge = badge.slice(0, 4);
    return '<span style="display:inline-flex;align-items:center;justify-content:center;min-width:14px;height:14px;padding:0 3px;' +
      'border-radius:2px;background:rgba(0,0,0,.65);border:1px solid ' + (color || '#fff') +
      ';font:800 9px/1 ui-sans-serif,system-ui,sans-serif;color:#fff;margin-left:2px;">' +
      String(badge).replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</span>';
  }

  function labelOf(data) {
    if (!data) return '';
    return String(data.text || data.label || data.name || '').trim();
  }

  /** Libellé carte : jamais un indicatif « nu » (évite de confondre un repère avec un effectif). */
  function displayLabelOf(data) {
    var raw = labelOf(data);
    if (!raw) return 'Repère';
    var typeFr = typeLabelFr(data);
    // Texte = indicatif joueur / callsign court → préfixer pour distinguer du BFT OTAN.
    if (/^[A-Za-z]{1,3}-?\d{1,4}$/.test(raw) || /^[A-Z]{1,4}\d{0,3}$/.test(raw)) {
      return 'Repère · ' + raw;
    }
    if (typeFr && typeFr !== raw && typeFr !== 'Repère') {
      return raw;
    }
    return raw;
  }

  /**
   * Masquer un repère carte qui double un contact BFT déjà affiché (même indicatif proche,
   * ou point anonyme collé sur une unité en liaison).
   * @param {object} data
   * @param {Array<object>} units
   * @param {{ maxDist?: number }} [opts]
   */
  function isLiveUnitDuplicate(data, units, opts) {
    if (!data || isAreaShape(data)) return false;
    var list = Array.isArray(units) ? units : [];
    if (!list.length) return false;
    var maxDist = (opts && opts.maxDist != null) ? opts.maxDist : 120;
    var pos = parsePos(data);
    if (!pos) return false;
    var mx = pos.x;
    var my = pos.y;
    var label = labelOf(data).toUpperCase();
    var decoded = decodeType(data);
    var isPlainDot = (decoded.kind === 'handdrawn' || decoded.kind === 'unknown')
      && (decoded.typeKey === 'dot' || decoded.typeKey === 'circle' || !decoded.typeKey);

    for (var i = 0; i < list.length; i++) {
      var u = list[i];
      var st = String((u && u.status) || '').toLowerCase();
      if (st !== 'linked' && st !== 'delayed') continue;
      var ux = u.pos_x != null ? parseFloat(u.pos_x) : NaN;
      var uy = u.pos_y != null ? parseFloat(u.pos_y) : NaN;
      if (isNaN(ux) || isNaN(uy)) {
        var gridRef = String(u.grid_ref || '').trim().split(/\s+/);
        ux = parseFloat(gridRef[0]);
        uy = parseFloat(gridRef[1]);
      }
      if (isNaN(ux) || isNaN(uy)) continue;
      var dx = mx - ux;
      var dy = my - uy;
      var near = (dx * dx + dy * dy) <= (maxDist * maxDist);
      var cs = String(u.call_sign || '').toUpperCase().trim();
      if (label && cs && label === cs) {
        // Même indicatif qu’un contact en liaison : le symbole OTAN fait foi.
        return true;
      }
      if (near && isPlainDot && !label) return true;
    }
    return false;
  }

  function needsRotation(typeKey) {
    return typeKey === 'arrow' || typeKey === 'arrow2' || typeKey === 'start' || typeKey === 'end';
  }

  function natoOptsFromData(data, decoded) {
    var label = labelOf(data);
    var dir = readDir(data);
    var groupSize = Number(data.groupSize) || 0;
    return {
      affiliation: decoded.affiliation || 'friend',
      roleKey: decoded.roleKey || 'infantry',
      callSign: label ? String(label).substring(0, 12) : '',
      label: label,
      showLabel: !!label,
      size: groupSize >= 3 ? 34 : (groupSize >= 1 ? 30 : 28),
      heading: dir || undefined
    };
  }

  function resolveColor(data, decoded) {
    var color = armaColorHex((data && (data.colorHex || data.color)) || '');
    if (decoded && decoded.kind === 'nato' && (!data.color || /^ColorDefault$/i.test(String(data.color)))) {
      if (decoded.affiliation === 'friend') color = '#4e9de0';
      else if (decoded.affiliation === 'hostile') color = '#d9534f';
      else if (decoded.affiliation === 'neutral') color = '#4ec94e';
      else if (decoded.affiliation === 'unknown') color = '#e7cc5b';
    }
    return color;
  }

  /** Base CDN icônes PAA→PNG (injectée côté page, sinon défaut relatif). */
  function markerIconsCdnBase() {
    var fromWin = (typeof window !== 'undefined' && window.ATAK_MARKER_ICONS_CDN)
      ? String(window.ATAK_MARKER_ICONS_CDN).replace(/\/$/, '')
      : '';
    return fromWin || '';
  }

  var ADDON_PREFIX_REMAP = [
    ['nln_ctab_core/', 'ctab/'],
    ['nln_ctab/', 'ctab/'],
    ['ctab_core/', 'ctab/'],
    ['ctab_rev/', 'ctab/'],
    ['ctab_enhanced/', 'ctab/'],
    ['iceman_atak/', 'ctab/'],
    ['iceman/', 'ctab/'],
    ['markers_plus/', 'markersplus/'],
    ['plp_markersplus/', 'markersplus/']
  ];

  function rewriteAddonPrefix(rel) {
    var raw = String(rel || '');
    for (var i = 0; i < ADDON_PREFIX_REMAP.length; i++) {
      if (raw.indexOf(ADDON_PREFIX_REMAP[i][0]) === 0) {
        return ADDON_PREFIX_REMAP[i][1] + raw.slice(ADDON_PREFIX_REMAP[i][0].length);
      }
    }
    return raw;
  }

  function relToCdnUrl(rel) {
    var cleaned = rewriteAddonPrefix(String(rel || '').replace(/\\/g, '/').replace(/^\/+/, '').toLowerCase());
    var parts = cleaned.split('/').filter(function (p) {
      return p && p !== '.' && p !== '..';
    }).map(encodeURIComponent);
    if (!parts.length) return '';
    var base = markerIconsCdnBase();
    if (!base) return '';
    return base + '/' + parts.join('/');
  }

  var libMapsCache = null;
  function libraryMaps() {
    if (libMapsCache) return libMapsCache;
    libMapsCache = { byKey: {}, byBase: {} };
    var items = (window.ArmaMarkerLibraryIndex && window.ArmaMarkerLibraryIndex.ITEMS) || [];
    for (var i = 0; i < items.length; i++) {
      var it = items[i];
      if (!it || !it.png) continue;
      var png = String(it.png).replace(/\\/g, '/').toLowerCase();
      var k = String(it.key || '').toLowerCase();
      if (k && !libMapsCache.byKey[k]) libMapsCache.byKey[k] = png;
      if (k.indexOf('ctab_') === 0) {
        var alias = k.slice(5);
        // o_inf_rifle / b_armor_aa : pas les classnames vanilla b_inf / o_inf
        if (alias && alias.indexOf('_') !== alias.lastIndexOf('_') && !libMapsCache.byKey[alias]) {
          libMapsCache.byKey[alias] = png;
        }
      }
      var base = png.split('/').pop().replace(/\.png$/i, '').replace(/_ca$/i, '');
      if (base && !libMapsCache.byBase[base]) libMapsCache.byBase[base] = png;
    }
    return libMapsCache;
  }

  function packHintFromTexture(tex) {
    var t = String(tex || '').toLowerCase();
    if (t.indexOf('ctab') >= 0 || t.indexOf('iceman') >= 0) return 'ctab/';
    if (t.indexOf('markersplus') >= 0 || t.indexOf('markers_plus') >= 0) return 'markersplus/';
    if (t.indexOf('/mts') >= 0 || t.indexOf('\\mts') >= 0 || t.indexOf('mts_') >= 0) return 'z/mts/';
    if (t.indexOf('a3/') === 0 || t.indexOf('/a3/') >= 0) return 'a3/';
    return '';
  }

  function libraryPngFor(data) {
    var maps = libraryMaps();
    var tex = String((data && (data.texture || data.iconPath)) || '').replace(/\\/g, '/').toLowerCase();
    if (tex && tex.charAt(0) !== '#') {
      var base = tex.split('/').pop().replace(/\.paa$/i, '').replace(/\.png$/i, '').replace(/_ca$/i, '');
      var hint = packHintFromTexture(tex);
      if (base) {
        if (hint) {
          var items = (window.ArmaMarkerLibraryIndex && window.ArmaMarkerLibraryIndex.ITEMS) || [];
          for (var i = 0; i < items.length; i++) {
            var png = items[i] && items[i].png ? String(items[i].png).toLowerCase() : '';
            if (!png || png.indexOf(hint) !== 0) continue;
            var bn = png.split('/').pop().replace(/\.png$/i, '').replace(/_ca$/i, '');
            if (bn === base) return png;
          }
        }
        if (maps.byBase[base]) return maps.byBase[base];
      }
    }
    var type = String((data && (data.type || data.icon)) || '').toLowerCase().replace(/[\s-]/g, '_');
    if (type && maps.byKey[type]) return maps.byKey[type];
    if (window.ArmaMarkerCatalog && typeof window.ArmaMarkerCatalog.get === 'function') {
      var cat = window.ArmaMarkerCatalog.get(type);
      if (cat && cat.pngUrl) return String(cat.pngUrl);
    }
    return '';
  }

  function vanillaRelFromType(type) {
    var key = String(type || '').toLowerCase().replace(/[\s-]/g, '_');
    if (!key) return '';
    if (key.indexOf('mplus_') === 0 && key.length > 6) return 'markersplus/data/img/' + key.slice(6) + '.png';
    if (key.indexOf('mil_') === 0 && key.length > 4) return 'a3/ui_f/data/map/markers/military/' + key.slice(4) + '_ca.png';
    if (key.indexOf('hd_') === 0 && key.length > 3) return 'a3/ui_f/data/map/markers/handdrawn/' + key.slice(3) + '_ca.png';
    if (/^[boncu]_[a-z0-9_]+$/.test(key)) return 'a3/ui_f/data/map/markers/nato/' + key + '.png';
    return '';
  }

  function rebaseStoredPngUrl(url) {
    var m = String(url || '').match(/\/assets\/markers\/arma\/(.+)$/i);
    if (!m) return '';
    return relToCdnUrl(decodeURIComponent(m[1]));
  }

  /**
   * \A3\ui_f\...\warning_CA.paa → {CDN}/a3/ui_f/.../warning_ca.png
   */
  function armaTextureToPngUrl(texturePath) {
    var raw = String(texturePath || '').replace(/\\/g, '/').replace(/^\/+/, '');
    if (!raw || raw.charAt(0) === '#') return '';
    raw = raw.replace(/^[a-z]:\//i, '');
    if (/\.paa$/i.test(raw)) raw = raw.replace(/\.paa$/i, '.png');
    else if (!/\.(png|jpe?g|webp|svg)$/i.test(raw)) raw += '.png';
    return relToCdnUrl(raw);
  }

  function resolvePngUrl(data) {
    if (!data || typeof data !== 'object') return '';
    var tex = data.texture || data.iconPath || '';
    if (tex && String(tex).charAt(0) !== '#') {
      var fromTex = armaTextureToPngUrl(tex);
      if (fromTex) return fromTex;
    }
    var libRel = libraryPngFor(data);
    if (libRel) {
      if (/^https?:\/\//i.test(libRel) || libRel.indexOf('/') === 0) return libRel;
      var fromLib = relToCdnUrl(libRel);
      if (fromLib) return fromLib;
    }
    var typeRel = vanillaRelFromType(data.type || data.icon || '');
    if (typeRel) {
      var fromType = relToCdnUrl(typeRel);
      if (fromType) return fromType;
    }
    var direct = data.pngUrl || data.png_url || '';
    if (direct) {
      var rebased = rebaseStoredPngUrl(String(direct));
      if (rebased) return rebased;
      return String(direct);
    }
    return '';
  }

  function pngNeedsColorMask(pngUrl) {
    return /\/(military|handdrawn)\//i.test(String(pngUrl || ''));
  }

  function escapeAttr(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function labelSpanHtml(label, labelColor) {
    return '<span style="font:700 8px/1.1 ui-sans-serif,system-ui,sans-serif;color:' + labelColor +
      ';text-shadow:0 0 2px #000,0 1px 2px #000;white-space:nowrap;max-width:88px;overflow:hidden;text-overflow:ellipsis;">' +
      String(label).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;').slice(0, 18) +
      '</span>';
  }

  /**
   * @returns {{ html: string, color: string, typeKey: string, label: string, iconSize: number[], iconAnchor: number[], kind: string, pngUrl?: string, iconUrl?: string }}
   */
  function buildIconSpec(data) {
    var decoded = decodeType(data || {});
    var color = resolveColor(data, decoded);
    var rawLabel = labelOf(data);
    var label = displayLabelOf(data);
    var dir = readDir(data);
    var typeKey = decoded.typeKey;
    var mutedLabel = !rawLabel || label.indexOf('Repère') === 0;
    var labelColor = mutedLabel ? '#94a3b8' : color;
    var pngUrl = resolvePngUrl(data);

    var S = window.ATAKMarkerSizes;
    var glyphPx = S ? S.px('normal') : 17;
    var specBox = S ? S.square('normal') : { size: [glyphPx, glyphPx], anchor: [glyphPx / 2, glyphPx / 2], popup: [0, -glyphPx / 2] };
    var glyphHtml = '';
    var kind = decoded.kind;
    var iconSize = specBox.size;
    var iconAnchor = specBox.anchor;
    var isNatoSvg = false;

    if (decoded.kind === 'nato' && window.NatoSidcIcons && typeof window.NatoSidcIcons.svgMarkup === 'function') {
      var natoOpts = natoOptsFromData(data, decoded);
      natoOpts.showLabel = false;
      natoOpts.size = S ? S.px('tactical') : 19;
      natoOpts.callSign = '';
      natoOpts.label = '';
      glyphHtml = window.NatoSidcIcons.svgMarkup(natoOpts);
      kind = 'nato';
      isNatoSvg = true;
      var natoBox = S ? S.square('tactical') : { size: [19, 19], anchor: [9.5, 9.5] };
      iconSize = natoBox.size;
      iconAnchor = natoBox.anchor;
    } else {
      var rotateStyle = '';
      if (dir && needsRotation(typeKey)) {
        rotateStyle = 'transform:rotate(' + dir + 'deg);transform-origin:center center;';
      }
      var shape = shapeHtml(typeKey, color);
      // Diamant rouge pour repères hostiles simples (Marker Dropper)
      if ((decoded.kind === 'handdrawn' || decoded.kind === 'unknown') && /red|opfor|east/i.test(String((data && data.color) || ''))) {
        if (typeKey === 'dot' || typeKey === 'marker') {
          shape = shapeHtml('destroy', color);
        }
      }
      var badge = prefixBadgeHtml(data, color);
      glyphHtml = '<span style="display:inline-flex;align-items:center;justify-content:center;gap:2px;' + rotateStyle + '">' + shape + badge + '</span>';
    }

    // Texture / PNG : icône jeu prioritaire, repli SVG/glyph si le fichier est absent.
    if (pngUrl) {
      var rotImg = (dir && needsRotation(typeKey) && !isNatoSvg) ? 'transform:rotate(' + dir + 'deg);' : '';
      var onErr = "this.style.display='none';var f=this.nextElementSibling;if(f){f.style.display='flex';}";
      var onErrMask = "var w=this.parentNode;if(w){w.style.display='none';var f=w.nextElementSibling;if(f){f.style.display='flex';}}";
      var wrap = S && S.wrapGlyph ? S.wrapGlyph : function (h) { return h; };
      var iconInner;
      if (pngNeedsColorMask(pngUrl)) {
        iconInner = '<span class="arma-marker-paa-mask-wrap" style="position:relative;width:' + glyphPx + 'px;height:' + glyphPx + 'px;' + rotImg + '">' +
          '<span class="arma-marker-paa-mask" style="width:' + glyphPx + 'px;height:' + glyphPx + 'px;background:' +
          escapeAttr(color) + ';-webkit-mask-image:url(\'' + escapeAttr(pngUrl) + '\');mask-image:url(\'' +
          escapeAttr(pngUrl) + '\');"></span>' +
          '<img src="' + escapeAttr(pngUrl) + '" alt="" width="1" height="1" style="position:absolute;opacity:0;width:0;height:0;" onerror="' + onErrMask + '" />' +
          '</span>';
      } else {
        iconInner = '<img class="arma-marker-paa-png" src="' + escapeAttr(pngUrl) + '" alt="" width="' + glyphPx + '" height="' + glyphPx + '" ' +
          'style="width:' + glyphPx + 'px;height:' + glyphPx + 'px;object-fit:contain;' + rotImg + 'display:block;" onerror="' + onErr + '" />';
      }
      var htmlPng = wrap(
        iconInner +
        '<span class="arma-marker-paa-fallback" style="display:none;">' + glyphHtml + '</span>'
      );
      return {
        html: htmlPng,
        color: color,
        typeKey: typeKey,
        label: label,
        kind: kind,
        pngUrl: pngUrl,
        iconUrl: pngUrl,
        iconSize: iconSize,
        iconAnchor: iconAnchor
      };
    }

    var wrapGlyph = S && S.wrapGlyph ? S.wrapGlyph : function (h) { return h; };
    return {
      html: wrapGlyph(glyphHtml),
      color: color,
      typeKey: typeKey,
      label: label,
      kind: isNatoSvg ? 'nato' : decoded.kind,
      iconSize: iconSize,
      iconAnchor: iconAnchor
    };
  }

  function leafletDivIcon(L, data) {
    if (!L) return null;
    var spec = buildIconSpec(data);
    var S = window.ATAKMarkerSizes;
    var box = S ? S.square('normal') : { size: spec.iconSize, anchor: spec.iconAnchor, popup: [0, -8] };
    if (!L.divIcon) return null;
    return L.divIcon({
      className: 'arma-map-marker-icon atak-compact-marker' + (spec.iconUrl ? ' arma-map-marker-png' : ''),
      html: spec.html,
      iconSize: spec.iconSize || box.size,
      iconAnchor: spec.iconAnchor || box.anchor,
      popupAnchor: box.popup || [0, -8],
      tooltipAnchor: box.tooltip || [0, -8]
    });
  }

  function brushStyle(brush, color, alpha) {
    var b = String(brush || 'Solid').toLowerCase();
    var a = alpha != null ? alpha : 1;
    var fillOpacity = 0.35 * a;
    var weight = 2;
    var dashArray = null;
    var fill = true;
    if (b.indexOf('border') >= 0) {
      fillOpacity = 0;
      weight = 2;
    } else if (b.indexOf('solidfull') >= 0 || b === 'solid') {
      fillOpacity = (b.indexOf('solidfull') >= 0 ? 0.85 : 0.4) * a;
    } else if (b.indexOf('cross') >= 0 || b.indexOf('diag') >= 0 || b.indexOf('grid') >= 0
      || b.indexOf('horizontal') >= 0 || b.indexOf('vertical') >= 0 || b.indexOf('fdiagonal') >= 0
      || b.indexOf('bdiagonal') >= 0) {
      fillOpacity = 0.28 * a;
      dashArray = '4 3';
      weight = 2;
    } else if (b.indexOf('hollow') >= 0) {
      fillOpacity = 0;
    }
    return {
      color: color,
      fillColor: color,
      fillOpacity: fill ? fillOpacity : 0,
      opacity: a,
      weight: weight,
      dashArray: dashArray
    };
  }

  function rotatePoints(center, corners, dirDeg) {
    var angle = (Number(dirDeg) || 0) * Math.PI / 180;
    var cos = Math.cos(angle);
    var sin = Math.sin(angle);
    return corners.map(function (p) {
      var dx = p[0] - center[0];
      var dy = p[1] - center[1];
      return [center[0] + cos * dx - sin * dy, center[1] + sin * dx + cos * dy];
    });
  }

  /**
   * Construit une couche Leaflet pour ELLIPSE / RECTANGLE / POLYLINE (CRS.Simple / MGRS).
   * latlng = L.LatLng déjà offseté (centre).
   */
  function leafletShapeLayer(L, data, latlng) {
    if (!L || !data || !latlng) return null;
    var shape = readShape(data);
    var color = resolveColor(data, decodeType(data));
    var alpha = readAlpha(data);
    var size = readSize(data);
    var dir = readDir(data);
    var style = brushStyle(readBrush(data), color, alpha);
    var label = labelOf(data);

    if (shape === 'POLYLINE') {
      var poly = data.polyline || data.points || [];
      var latlngs = [];
      if (Array.isArray(poly) && poly.length >= 4 && typeof poly[0] === 'number') {
        for (var i = 0; i + 1 < poly.length; i += 2) {
          latlngs.push(L.latLng(poly[i + 1], poly[i]));
        }
      } else if (Array.isArray(poly)) {
        poly.forEach(function (p) {
          if (Array.isArray(p) && p.length >= 2) latlngs.push(L.latLng(p[1], p[0]));
        });
      }
      if (latlngs.length < 2) return null;
      var line = L.polyline(latlngs, {
        color: color,
        weight: style.weight,
        opacity: alpha,
        dashArray: style.dashArray || undefined
      });
      if (label) line.bindTooltip(label, { sticky: true, direction: 'top', opacity: 0.9 });
      return line;
    }

    if (shape === 'RECTANGLE') {
      var cy = latlng.lat;
      var cx = latlng.lng;
      var hx = size[0];
      var hy = size[1];
      var corners = [
        [cy - hy, cx - hx],
        [cy - hy, cx + hx],
        [cy + hy, cx + hx],
        [cy + hy, cx - hx]
      ];
      var layer;
      if (dir) {
        layer = L.polygon(rotatePoints([cy, cx], corners, dir), style);
      } else {
        layer = L.rectangle([[cy - hy, cx - hx], [cy + hy, cx + hx]], style);
      }
      if (label) layer.bindTooltip(label, { sticky: true, direction: 'center', opacity: 0.9 });
      return layer;
    }

    if (shape === 'ELLIPSE') {
      // Sur CRS.Simple / MGRS, le rayon Leaflet = unités carte (mètres Arma).
      var rx = size[0];
      var ry = size[1];
      var ellipse;
      if (Math.abs(rx - ry) < 0.5 && !dir) {
        ellipse = L.circle(latlng, Object.assign({}, style, { radius: rx }));
      } else if (L.ellipse) {
        ellipse = L.ellipse(latlng, [rx, ry], Object.assign({}, style, { tilt: dir || 0 }));
      } else {
        // Approximation polygonale d’ellipse (avec rotation)
        var pts = [];
        var steps = 32;
        for (var s = 0; s < steps; s++) {
          var t = (s / steps) * Math.PI * 2;
          pts.push([latlng.lat + ry * Math.sin(t), latlng.lng + rx * Math.cos(t)]);
        }
        if (dir) pts = rotatePoints([latlng.lat, latlng.lng], pts, dir);
        ellipse = L.polygon(pts, style);
      }
      if (label) ellipse.bindTooltip(label, { sticky: true, direction: 'center', opacity: 0.9 });
      return ellipse;
    }

    return null;
  }

  /** Badge liste (panneau historique). */
  function listBadgeHtml(data) {
    var decoded = decodeType(data || {});
    if (isAreaShape(data)) {
      var c = resolveColor(data, decoded);
      var sh = readShape(data);
      var tip = sh === 'RECTANGLE' ? '▭' : (sh === 'POLYLINE' ? '╱' : '○');
      return '<span class="arma-marker-list-badge" style="display:inline-flex;width:18px;height:14px;align-items:center;justify-content:center;margin-right:6px;color:' + c + ';font-weight:700;">' + tip + '</span>';
    }
    if (resolvePngUrl(data)) {
      var specPng = buildIconSpec(data);
      return '<span class="arma-marker-list-badge" style="display:inline-flex;vertical-align:middle;margin-right:6px;transform:scale(.85);">' +
        specPng.html +
        '</span>';
    }
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
    isAreaShape: isAreaShape,
    buildIconSpec: buildIconSpec,
    leafletDivIcon: leafletDivIcon,
    leafletShapeLayer: leafletShapeLayer,
    listBadgeHtml: listBadgeHtml,
    parsePos: parsePos,
    labelOf: labelOf,
    displayLabelOf: displayLabelOf,
    isLiveUnitDuplicate: isLiveUnitDuplicate,
    typeLabelFr: typeLabelFr,
    readDir: readDir,
    readShape: readShape,
    readBrush: readBrush,
    readSize: readSize,
    resolvePngUrl: resolvePngUrl,
    armaTextureToPngUrl: armaTextureToPngUrl
  };
})();
