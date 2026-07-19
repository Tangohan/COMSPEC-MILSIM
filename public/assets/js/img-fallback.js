/**
 * Remplace les images cassées / absentes par un placeholder soigné.
 *
 * Opt-in : <img data-img-fallback="avatar|portrait|media|hero|logo|badge|cover" …>
 * Options :
 *   data-img-initials="AB"   — initiales (avatar / portrait)
 *   data-img-label="…"       — libellé accessible au repli
 *
 * Évite les boucles onerror : le handler est retiré dès le premier repli.
 */
(function (global) {
  'use strict';

  var ATTR = 'data-img-fallback';
  var APPLIED = 'data-img-fallback-applied';
  var SELECTOR = 'img[' + ATTR + ']';

  var ALT_BY_KIND = {
    avatar: 'Photo de compte indisponible',
    portrait: 'Portrait opérateur indisponible',
    media: 'Image indisponible',
    hero: 'Visuel de présentation indisponible',
    logo: 'Emblème indisponible',
    badge: 'Insigne indisponible',
    cover: 'Couverture indisponible',
  };

  function escXml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&apos;');
  }

  function svgDataUri(svg) {
    return 'data:image/svg+xml,' + encodeURIComponent(svg);
  }

  function initialsPlaceholder(initials, w, h) {
    var label = String(initials || '?').trim().slice(0, 3).toUpperCase() || '?';
    var fs = label.length > 2 ? Math.round(Math.min(w, h) * 0.32) : Math.round(Math.min(w, h) * 0.42);
    return svgDataUri(
      '<svg xmlns="http://www.w3.org/2000/svg" width="' +
        w +
        '" height="' +
        h +
        '" viewBox="0 0 ' +
        w +
        ' ' +
        h +
        '" role="img">' +
        '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">' +
        '<stop offset="0%" stop-color="#0f172a"/>' +
        '<stop offset="55%" stop-color="#134e4a"/>' +
        '<stop offset="100%" stop-color="#064e3b"/>' +
        '</linearGradient></defs>' +
        '<rect width="100%" height="100%" fill="url(#g)"/>' +
        '<text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" ' +
        'fill="#ecfdf5" font-family="Inter,system-ui,sans-serif" font-weight="800" font-size="' +
        fs +
        '">' +
        escXml(label) +
        '</text></svg>'
    );
  }

  function mediaPlaceholder(w, h, mark) {
    var icon = mark
      ? '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="#6ee7b7" ' +
        'font-family="Inter,system-ui,sans-serif" font-weight="900" font-size="' +
        Math.round(Math.min(w, h) * 0.22) +
        '" letter-spacing="0.08em">' +
        escXml(mark) +
        '</text>'
      : '<g fill="none" stroke="#34d399" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" opacity="0.85">' +
        '<rect x="' +
        Math.round(w * 0.28) +
        '" y="' +
        Math.round(h * 0.3) +
        '" width="' +
        Math.round(w * 0.44) +
        '" height="' +
        Math.round(h * 0.36) +
        '" rx="6"/>' +
        '<circle cx="' +
        Math.round(w * 0.4) +
        '" cy="' +
        Math.round(h * 0.42) +
        '" r="' +
        Math.round(Math.min(w, h) * 0.04) +
        '"/>' +
        '<path d="M' +
        Math.round(w * 0.32) +
        ' ' +
        Math.round(h * 0.58) +
        ' L' +
        Math.round(w * 0.44) +
        ' ' +
        Math.round(h * 0.46) +
        ' L' +
        Math.round(w * 0.55) +
        ' ' +
        Math.round(h * 0.54) +
        ' L' +
        Math.round(w * 0.68) +
        ' ' +
        Math.round(h * 0.42) +
        '"/></g>';

    return svgDataUri(
      '<svg xmlns="http://www.w3.org/2000/svg" width="' +
        w +
        '" height="' +
        h +
        '" viewBox="0 0 ' +
        w +
        ' ' +
        h +
        '" role="img">' +
        '<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">' +
        '<stop offset="0%" stop-color="#020617"/>' +
        '<stop offset="50%" stop-color="#0f172a"/>' +
        '<stop offset="100%" stop-color="#022c22"/>' +
        '</linearGradient>' +
        '<radialGradient id="glow" cx="30%" cy="25%" r="60%">' +
        '<stop offset="0%" stop-color="#10b981" stop-opacity="0.28"/>' +
        '<stop offset="100%" stop-color="#10b981" stop-opacity="0"/>' +
        '</radialGradient></defs>' +
        '<rect width="100%" height="100%" fill="url(#bg)"/>' +
        '<rect width="100%" height="100%" fill="url(#glow)"/>' +
        icon +
        '</svg>'
    );
  }

  function placeholderFor(img) {
    var kind = (img.getAttribute(ATTR) || 'media').toLowerCase();
    var initials = (img.getAttribute('data-img-initials') || '').trim();
    var w = Math.max(32, parseInt(img.getAttribute('width'), 10) || 0);
    var h = Math.max(32, parseInt(img.getAttribute('height'), 10) || 0);
    if (!w || !h) {
      var rect = img.getBoundingClientRect();
      w = Math.max(32, Math.round(rect.width) || 160);
      h = Math.max(32, Math.round(rect.height) || 160);
    }
    if (kind === 'avatar' || kind === 'badge') {
      return initialsPlaceholder(initials || '?', w, h);
    }
    if (kind === 'portrait') {
      if (!initials) {
        return mediaPlaceholder(w, h, 'A');
      }
      return initialsPlaceholder(initials, w, Math.max(h, Math.round(w * 1.35)));
    }
    if (kind === 'logo') {
      return mediaPlaceholder(Math.max(w, 96), Math.max(h, 96), 'A');
    }
    if (kind === 'hero' || kind === 'cover') {
      return mediaPlaceholder(Math.max(w, 640), Math.max(h, 240), '');
    }
    return mediaPlaceholder(Math.max(w, 320), Math.max(h, 200), '');
  }

  function fallbackAlt(img) {
    var custom = (img.getAttribute('data-img-label') || '').trim();
    if (custom) return custom;
    var kind = (img.getAttribute(ATTR) || 'media').toLowerCase();
    return ALT_BY_KIND[kind] || ALT_BY_KIND.media;
  }

  function needsFallback(img) {
    if (img.getAttribute(APPLIED) === '1') return false;
    var src = (img.getAttribute('src') || '').trim();
    return src === '' || src === '#' || src.toLowerCase().indexOf('javascript:') === 0;
  }

  function applyFallback(img) {
    if (!img || img.getAttribute(APPLIED) === '1') return;
    img.setAttribute(APPLIED, '1');
    img.onerror = null;
    img.removeAttribute('onerror');
    try {
      img.src = placeholderFor(img);
    } catch (e) {
      /* ignore */
    }
    var currentAlt = (img.getAttribute('alt') || '').trim();
    if (currentAlt === '') {
      img.setAttribute('alt', fallbackAlt(img));
    }
    img.classList.add('img-fallback-applied');
    img.setAttribute('data-img-fallback-kind', (img.getAttribute(ATTR) || 'media').toLowerCase());
  }

  function onError(ev) {
    var img = ev && ev.target;
    if (!img || img.tagName !== 'IMG' || !img.hasAttribute(ATTR)) return;
    applyFallback(img);
  }

  function bind(img) {
    if (!img || img.tagName !== 'IMG' || !img.hasAttribute(ATTR)) return;
    if (img.getAttribute(APPLIED) === '1') return;
    if (needsFallback(img)) {
      applyFallback(img);
      return;
    }
    // Image déjà en échec avant l’attache du listener (eager / cache 404).
    var src = (img.getAttribute('src') || '').trim();
    if (
      img.complete &&
      src !== '' &&
      src.indexOf('data:') !== 0 &&
      ((typeof img.naturalWidth === 'number' && img.naturalWidth === 0) ||
        (typeof img.naturalHeight === 'number' && img.naturalHeight === 0))
    ) {
      applyFallback(img);
    }
  }

  function scan(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var list = scope.querySelectorAll(SELECTOR);
    for (var i = 0; i < list.length; i++) {
      bind(list[i]);
    }
  }

  function observe() {
    if (!global.MutationObserver || !document.documentElement) return;
    var mo = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var m = mutations[i];
        if (m.type === 'attributes' && m.target && m.target.tagName === 'IMG') {
          if (m.attributeName === 'src' || m.attributeName === ATTR) {
            bind(m.target);
          }
          continue;
        }
        var nodes = m.addedNodes;
        for (var j = 0; j < nodes.length; j++) {
          var n = nodes[j];
          if (!n || n.nodeType !== 1) continue;
          if (n.tagName === 'IMG') bind(n);
          else if (n.querySelectorAll) scan(n);
        }
      }
    });
    mo.observe(document.documentElement, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['src', ATTR],
    });
  }

  function boot() {
    scan(document);
    observe();
  }

  // Capture : intercepte les 404 même si le scan arrive après l’échec.
  if (global.document && global.document.addEventListener) {
    document.addEventListener('error', onError, true);
  }

  var api = {
    scan: scan,
    bind: bind,
    apply: applyFallback,
    placeholder: placeholderFor,
  };
  global.AthenaImgFallback = api;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(typeof window !== 'undefined' ? window : this);
