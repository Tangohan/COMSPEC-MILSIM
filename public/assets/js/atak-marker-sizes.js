/**
 * Tailles centralisées des marqueurs Tacmap Athena / ATAK.
 * Ne concerne que le rendu visuel : pas les coordonnées, offsets ni IDs.
 */
window.ATAKMarkerSizes = (function () {
  'use strict';

  var PX = {
    micro: 10,
    small: 14,
    normal: 17,
    tactical: 19,
    important: 22,
    large: 28
  };

  var PREF_MIN = PX.micro;
  var PREF_MAX = PX.large;

  function clampPref(n) {
    n = Number(n);
    if (!isFinite(n)) return PX.normal;
    return Math.max(PREF_MIN, Math.min(PREF_MAX, Math.round(n)));
  }

  function px(kind) {
    if (typeof kind === 'number') return clampPref(kind);
    return PX[kind] || PX.normal;
  }

  function square(kind, pad) {
    var s = px(kind) + (pad || 0);
    return {
      size: [s, s],
      anchor: [s / 2, s / 2],
      popup: [0, Math.round(-s / 2)],
      tooltip: [0, Math.round(-s / 2)]
    };
  }

  function pin(kind) {
    var s = px(kind);
    return {
      size: [s, s],
      anchor: [s / 2, s],
      popup: [0, -s],
      tooltip: [0, -s]
    };
  }

  function wrapGlyph(html, extraClass) {
    return '<span class="atak-marker-glyph atak-marker-billboard' + (extraClass ? ' ' + extraClass : '') + '">' + html + '</span>';
  }

  function escapeTip(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function hoverTipHtml(title, lines) {
    var html = '<div class="atak-map-tip">';
    if (title) html += '<div class="atak-map-tip__title">' + escapeTip(title) + '</div>';
    (lines || []).forEach(function (line) {
      if (line == null || line === '') return;
      html += '<div class="atak-map-tip__line">' + escapeTip(line) + '</div>';
    });
    return html + '</div>';
  }

  function bindHoverTip(marker, html) {
    if (!marker || !marker.bindTooltip || !html) return marker;
    if (marker.getTooltip && marker.getTooltip()) {
      marker.setTooltipContent(html);
    } else {
      marker.bindTooltip(html, {
        direction: 'top',
        offset: [0, -6],
        opacity: 0.96,
        className: 'atak-map-tip-pane',
        sticky: true
      });
    }
    return marker;
  }

  function bindSelectVisual(marker) {
    if (!marker || marker._atakSelectBound) return marker;
    marker._atakSelectBound = true;
    marker.on('popupopen', function () {
      if (marker._icon) marker._icon.classList.add('atak-marker-selected');
    });
    marker.on('popupclose', function () {
      if (marker._icon) marker._icon.classList.remove('atak-marker-selected');
    });
    return marker;
  }

  function divIcon(L, html, kind, opts) {
    if (!L || !L.divIcon) return null;
    opts = opts || {};
    var spec = opts.pin ? pin(kind) : square(kind, opts.pad || 0);
    return L.divIcon({
      className: (opts.className || 'atak-compact-marker') + ' leaflet-div-icon',
      html: wrapGlyph(html),
      iconSize: spec.size,
      iconAnchor: spec.anchor,
      popupAnchor: spec.popup,
      tooltipAnchor: spec.tooltip
    });
  }

  function zoomBand(z) {
    z = Number(z);
    if (!isFinite(z)) z = 0;
    if (z <= 1) return 'theatre';
    if (z <= 3) return 'ops';
    if (z <= 5) return 'tac';
    return 'close';
  }

  function applyMapZoom(map) {
    if (!map || typeof map.getContainer !== 'function') return;
    var el = map.getContainer();
    var b = zoomBand(map.getZoom());
    el.classList.remove('atak-zoom-theatre', 'atak-zoom-ops', 'atak-zoom-tac', 'atak-zoom-close');
    el.classList.add('atak-zoom-' + b);
  }

  function bindZoom(map) {
    if (!map || map._atakZoomBound) return;
    map._atakZoomBound = true;
    map.on('zoomend zoom', function () {
      applyMapZoom(map);
    });
    applyMapZoom(map);
  }

  return {
    PX: PX,
    PREF_MIN: PREF_MIN,
    PREF_MAX: PREF_MAX,
    px: px,
    clampPref: clampPref,
    square: square,
    pin: pin,
    wrapGlyph: wrapGlyph,
    hoverTipHtml: hoverTipHtml,
    bindHoverTip: bindHoverTip,
    bindSelectVisual: bindSelectVisual,
    divIcon: divIcon,
    zoomBand: zoomBand,
    applyMapZoom: applyMapZoom,
    bindZoom: bindZoom
  };
})();
