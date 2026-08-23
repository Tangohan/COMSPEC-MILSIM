/**
 * Symboles OTAN / APP-6 pour Leaflet.
 * Préfère milsymbol (window.ms) quand disponible ; sinon SVG maison (fallback).
 */
window.NatoSidcIcons = (function () {
  var COLORS = {
    friend: { fill: '#80e0ff', stroke: '#1e3a5f', frame: '#4db8e8' },
    friendly: { fill: '#80e0ff', stroke: '#1e3a5f', frame: '#4db8e8' },
    hostile: { fill: '#ff8080', stroke: '#5f1e1e', frame: '#e84d4d' },
    enemy: { fill: '#ff8080', stroke: '#5f1e1e', frame: '#e84d4d' },
    neutral: { fill: '#90ee90', stroke: '#1e5f1e', frame: '#4de84d' },
    unknown: { fill: '#ffe066', stroke: '#5f5a1e', frame: '#e8d44d' },
    suspect: { fill: '#ffb347', stroke: '#5f3a1e', frame: '#e89a4d' },
  };

  function normAff(aff) {
    var a = String(aff || 'friend').toLowerCase().trim();
    if (a === 'hostile' || a === 'enemy' || a === 'east') return 'hostile';
    if (a === 'neutral' || a === 'guer' || a === 'civ') return 'neutral';
    if (a === 'unknown' || a === 'suspect') return a === 'suspect' ? 'suspect' : 'unknown';
    if (a === 'friendly') return 'friend';
    return 'friend';
  }

  function guessRole(role, aircraftType) {
    var r = String(role || '').toLowerCase();
    var ac = String(aircraftType || '').toLowerCase();
    if (ac === 'helicopter' || /heli|ah-|uh-|mh-/.test(r + ac)) return 'aviation_rotary';
    if (ac === 'uav' || /uav|drone|mq-/.test(r + ac)) return 'uav';
    if (ac === 'plane' || /air|avion|f-|a-10|cas/.test(r + ac)) return 'aviation_fixed';
    if (/armor|char|tank|blindé|blinde/.test(r)) return 'armor';
    if (/arty|artiller|mortier|appui.?feu|fire.?support/.test(r)) return 'artillery';
    if (/log|supply|ravitail/.test(r)) return 'logistics';
    if (/cmd|hq|command|état.?major|etat.?major/.test(r)) return 'hq';
    if (/medic|opsan|santé|sante|medical/.test(r)) return 'medical';
    if (/recon|scout|isr/.test(r)) return 'recon';
    return 'infantry';
  }

  function roleGlyph(roleKey) {
    switch (roleKey) {
      case 'armor':
        return '<ellipse cx="16" cy="16" rx="7" ry="4.5" fill="none" stroke="currentColor" stroke-width="1.6"/>'
          + '<circle cx="11" cy="16" r="1.4" fill="currentColor"/><circle cx="21" cy="16" r="1.4" fill="currentColor"/>';
      case 'artillery':
        return '<circle cx="16" cy="16" r="3.2" fill="currentColor"/>'
          + '<line x1="16" y1="8" x2="16" y2="12" stroke="currentColor" stroke-width="1.6"/>'
          + '<line x1="16" y1="20" x2="16" y2="24" stroke="currentColor" stroke-width="1.6"/>'
          + '<line x1="8" y1="16" x2="12" y2="16" stroke="currentColor" stroke-width="1.6"/>'
          + '<line x1="20" y1="16" x2="24" y2="16" stroke="currentColor" stroke-width="1.6"/>';
      case 'aviation_fixed':
        return '<path d="M6 16 L26 16 M16 10 L16 22 M10 14 L16 10 L22 14" fill="none" stroke="currentColor" stroke-width="1.7"/>';
      case 'aviation_rotary':
        return '<text x="16" y="20" text-anchor="middle" font-size="14" font-weight="800" font-family="Arial,sans-serif" fill="currentColor">H</text>';
      case 'uav':
        return '<path d="M10 18 L16 10 L22 18 Z" fill="none" stroke="currentColor" stroke-width="1.6"/>'
          + '<circle cx="16" cy="20" r="1.5" fill="currentColor"/>';
      case 'hq':
        return '<rect x="12" y="11" width="8" height="10" fill="none" stroke="currentColor" stroke-width="1.6"/>'
          + '<line x1="12" y1="15" x2="20" y2="15" stroke="currentColor" stroke-width="1.4"/>';
      case 'medical':
        return '<line x1="16" y1="10" x2="16" y2="22" stroke="currentColor" stroke-width="2"/>'
          + '<line x1="10" y1="16" x2="22" y2="16" stroke="currentColor" stroke-width="2"/>';
      case 'logistics':
        return '<rect x="10" y="12" width="12" height="8" fill="none" stroke="currentColor" stroke-width="1.6"/>'
          + '<line x1="10" y1="16" x2="22" y2="16" stroke="currentColor" stroke-width="1.3"/>';
      case 'recon':
        return '<circle cx="16" cy="16" r="5" fill="none" stroke="currentColor" stroke-width="1.6"/>'
          + '<circle cx="16" cy="16" r="1.8" fill="currentColor"/>';
      case 'infantry':
      default:
        return '<line x1="10" y1="10" x2="22" y2="22" stroke="currentColor" stroke-width="1.8"/>'
          + '<line x1="22" y1="10" x2="10" y2="22" stroke="currentColor" stroke-width="1.8"/>';
    }
  }

  function framePath(aff) {
    if (aff === 'hostile') return 'M16 3 L29 16 L16 29 L3 16 Z';
    if (aff === 'neutral') return 'M6 6 H26 V26 H6 Z';
    if (aff === 'unknown' || aff === 'suspect') {
      return 'M16 4 C20 4 26 10 26 16 C26 22 20 28 16 28 C12 28 6 22 6 16 C6 10 12 4 16 4 Z';
    }
    return 'M5 8 H27 V24 H5 Z';
  }

  function hasMilsymbol() {
    return !!(window.ms && typeof window.ms.Symbol === 'function');
  }

  function resolveSidc(opts) {
    opts = opts || {};
    if (opts.sidc) return String(opts.sidc).trim();
    var aff = normAff(opts.affiliation);
    var cat = window.MilstdCatalog;
    if (cat && opts.functionid) {
      return cat.buildSidc(aff, {
        scheme: opts.scheme || 'S',
        dim: opts.battledimension || opts.dim || 'G',
        functionid: opts.functionid,
      });
    }
    var roleKey = opts.roleKey || guessRole(opts.role, opts.aircraftType);
    if (cat && cat.sidcForRole) return cat.sidcForRole(roleKey, aff);
    var letter = { friend: 'F', hostile: 'H', neutral: 'N', unknown: 'U', suspect: 'S' }[aff] || 'F';
    var fidMap = {
      infantry: 'UCI---', armor: 'UCA---', artillery: 'UCF---', recon: 'UCR---',
      hq: 'UH----', medical: 'USM---', logistics: 'US----',
      aviation_fixed: 'MF----', aviation_rotary: 'MH----', uav: 'MFQ---',
    };
    var dim = /aviation|uav/.test(roleKey) ? 'A' : 'G';
    var fid = fidMap[roleKey] || 'UCI---';
    return ('S' + letter + dim + 'P' + fid + '-----').slice(0, 15);
  }

  function milsymbolSvg(opts) {
    if (!hasMilsymbol()) return null;
    try {
      var sidc = resolveSidc(opts);
      var size = opts.size || (window.ATAKMarkerSizes ? window.ATAKMarkerSizes.px('tactical') : 19);
      // Jamais graver l’indicatif dans milsymbol : ça gonfle la boîte SVG.
      var designation = '';
      if (opts.uniqueDesignation != null && opts.uniqueDesignation !== '') {
        designation = opts.uniqueDesignation;
      }
      var symOpts = {
        size: size,
        uniqueDesignation: designation,
      };
      var heading = parseFloat(opts.heading);
      if (!isNaN(heading)) symOpts.direction = heading;
      var sym = new window.ms.Symbol(sidc, symOpts);
      if (sym.isValid && sym.isValid() === false) return null;
      var svg = sym.asSVG();
      if (!svg) return null;
      var anchor = sym.getAnchor ? sym.getAnchor() : { x: size / 2, y: size / 2 };
      var sz = sym.getSize ? sym.getSize() : { width: size, height: size };
      return { svg: svg, anchor: anchor, size: sz, sidc: sidc };
    } catch (e) {
      return null;
    }
  }

  function fallbackSvgInner(opts) {
    var aff = normAff(opts.affiliation);
    var roleKey = opts.roleKey || guessRole(opts.role, opts.aircraftType);
    var c = COLORS[aff] || COLORS.friend;
    var size = opts.size || (window.ATAKMarkerSizes ? window.ATAKMarkerSizes.px('tactical') : 19);
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size + '" viewBox="0 0 32 32" class="nato-sidc-svg" aria-hidden="true">'
      + '<path d="' + framePath(aff) + '" fill="' + c.fill + '" stroke="' + c.stroke + '" stroke-width="1.5"/>'
      + '<g color="' + c.stroke + '" opacity="0.95">' + roleGlyph(roleKey) + '</g>'
      + '</svg>';
  }

  function healthClassFromOpts(opts) {
    var health = String(opts.health || '').toLowerCase();
    var healthClass = opts.className || '';
    if (!healthClass) {
      if (health === 'wounded' || health === 'injured') healthClass = 'nato-sidc--wounded';
      if (health === 'unconscious' || health === 'cardiac_arrest' || health === 'cardiac-arrest' || health === 'dead' || health === 'kia') {
        healthClass = 'nato-sidc--critical';
      }
    }
    return healthClass;
  }

  function escapeLabel(s) {
    return String(s == null ? '' : s).replace(/</g, '&lt;').replace(/&/g, '&amp;');
  }

  function svgMarkup(opts) {
    opts = opts || {};
    var aff = normAff(opts.affiliation);
    var label = String(opts.callSign || opts.label || '').slice(0, 12);
    var heading = parseFloat(opts.heading);
    if (isNaN(heading)) heading = 0;
    var size = opts.size || (window.ATAKMarkerSizes ? window.ATAKMarkerSizes.px('tactical') : 19);
    var showLabel = opts.showLabel === true;
    var healthClass = healthClassFromOpts(opts);

    var mil = milsymbolSvg(opts);
    var svg;
    var rotate = '';
    if (mil) {
      svg = mil.svg;
      // milsymbol direction already applied when heading set; avoid double rotate
      if (opts.heading == null || opts.heading === '' || isNaN(parseFloat(opts.heading))) {
        rotate = 'transform:rotate(' + heading + 'deg);transform-origin:center center;';
      }
    } else {
      svg = fallbackSvgInner(opts);
      rotate = 'transform:rotate(' + heading + 'deg);transform-origin:center center;';
    }

    var wrap = '<div class="nato-sidc-wrap nato-sidc--' + aff + (healthClass ? ' ' + healthClass : '') + '" style="display:flex;flex-direction:column;align-items:center;' + rotate + '">'
      + svg
      + (opts.emitting ? '<span class="atak-unit-emit-badge">Émet</span>' : '')
      + (opts.listening && !opts.emitting ? '<span class="atak-unit-listen-badge">Réseau</span>' : '')
      + '</div>';
    if (showLabel && label) {
      wrap = '<div class="nato-sidc-stack' + (healthClass ? ' ' + healthClass : '') + '" style="display:flex;flex-direction:column;align-items:center;gap:1px;">'
        + wrap
        + '<span class="nato-sidc-label">' + escapeLabel(label) + '</span></div>';
    }
    return wrap;
  }

  function leafletDivIcon(L, opts) {
    if (!L || !L.divIcon) return null;
    opts = opts || {};
    var S = window.ATAKMarkerSizes;
    var size = S ? S.clampPref(opts.size || S.px('tactical')) : (opts.size || 19);
    opts.size = size;
    opts.showLabel = false;
    var mil = milsymbolSvg(opts);
    var iconW = size;
    var iconH = size;
    var anchorX = size / 2;
    var anchorY = size / 2;
    if (mil && mil.size) {
      iconW = Math.min(S ? S.px('important') : 22, Math.ceil(mil.size.width) || size);
      iconH = Math.min(S ? S.px('important') : 22, Math.ceil(mil.size.height) || size);
      if (mil.anchor) {
        anchorX = mil.anchor.x != null ? Math.min(iconW, mil.anchor.x) : iconW / 2;
        anchorY = mil.anchor.y != null ? Math.min(iconH, mil.anchor.y) : iconH / 2;
      }
    }
    var html = svgMarkup(opts);
    if (S && S.wrapGlyph) html = S.wrapGlyph(html);
    return L.divIcon({
      className: 'nato-sidc-icon atak-compact-marker',
      html: html,
      iconSize: [iconW, iconH],
      iconAnchor: [anchorX, anchorY],
      popupAnchor: [0, -anchorY],
      tooltipAnchor: [0, -anchorY]
    });
  }

  function listBadgeHtml(opts) {
    opts = opts || {};
    opts.size = opts.size || 22;
    opts.showLabel = false;
    return '<span class="nato-sidc-badge" style="display:inline-flex;vertical-align:middle;margin-right:6px;">'
      + svgMarkup(opts)
      + '</span>';
  }

  function previewHtml(opts) {
    opts = opts || {};
    opts.showLabel = false;
    opts.size = opts.size || 40;
    return svgMarkup(opts);
  }

  return {
    colors: COLORS,
    normalizeAffiliation: normAff,
    guessRole: guessRole,
    resolveSidc: resolveSidc,
    hasMilsymbol: hasMilsymbol,
    milsymbolSvg: milsymbolSvg,
    svgMarkup: svgMarkup,
    leafletDivIcon: leafletDivIcon,
    listBadgeHtml: listBadgeHtml,
    previewHtml: previewHtml,
  };
})();
