/**
 * AthenaMedia — runtime léger pour CDN (icônes, emoji, drapeaux, gif, animations, lottie).
 * Dépendances optionnelles chargées via views/partials/cdn_media_libs.php
 */
(function (global) {
  'use strict';

  var packsMeta = document.querySelector('meta[name="athena-cdn-packs"]');
  var packs = [];
  try {
    packs = packsMeta ? JSON.parse(packsMeta.getAttribute('content') || '[]') : [];
  } catch (e) {
    packs = [];
  }

  function hasPack(name) {
    return packs.indexOf(name) !== -1;
  }

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function tenorKey() {
    var m = qs('meta[name="athena-tenor-key"]');
    return m ? (m.getAttribute('content') || '').trim() : '';
  }

  /** Parse Twemoji dans un nœud ou le document. */
  function parseEmoji(root) {
    if (!global.twemoji || typeof global.twemoji.parse !== 'function') return;
    var target = root || document.body;
    global.twemoji.parse(target, {
      folder: 'svg',
      ext: '.svg',
      base: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/',
    });
  }

  /** Initialise Lucide sur les [data-lucide]. */
  function createIcons(root) {
    if (!global.lucide || typeof global.lucide.createIcons !== 'function') return;
    try {
      global.lucide.createIcons({
        attrs: { 'aria-hidden': 'true' },
        nameAttr: 'data-lucide',
        root: root || document,
      });
    } catch (err) {
      // API Lucide UMD : createIcons() sans options root sur certaines versions
      global.lucide.createIcons();
    }
  }

  /** AOS (scroll reveal). */
  function initAos() {
    if (!global.AOS || typeof global.AOS.init !== 'function') return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return;
    }
    global.AOS.init({
      duration: 520,
      easing: 'ease-out-cubic',
      once: true,
      offset: 48,
    });
  }

  /**
   * Drapeau : code ISO 3166-1 alpha-2 (ex. fr, us, de).
   * mode: 'css' (flag-icons) | 'img' (flagcdn)
   */
  function flag(code, opts) {
    opts = opts || {};
    var iso = String(code || '').trim().toLowerCase();
    if (!/^[a-z]{2}$/.test(iso)) return null;
    var mode = opts.mode || (hasPack('flags') ? 'css' : 'img');
    var title = opts.title || iso.toUpperCase();

    if (mode === 'img') {
      var size = opts.size || '24x18';
      var img = document.createElement('img');
      img.className = 'athena-flag athena-flag--img' + (opts.className ? ' ' + opts.className : '');
      img.src = 'https://flagcdn.com/' + size + '/' + iso + '.png';
      img.width = parseInt(String(size).split('x')[0], 10) || 24;
      img.height = parseInt(String(size).split('x')[1], 10) || 18;
      img.alt = opts.alt != null ? opts.alt : '';
      img.title = title;
      img.loading = 'lazy';
      img.decoding = 'async';
      return img;
    }

    var span = document.createElement('span');
    span.className = 'fi fi-' + iso + ' athena-flag' + (opts.className ? ' ' + opts.className : '');
    span.title = title;
    span.setAttribute('aria-hidden', opts.decorative === false ? 'false' : 'true');
    if (opts.label) {
      span.setAttribute('role', 'img');
      span.setAttribute('aria-label', opts.label);
      span.removeAttribute('aria-hidden');
    }
    return span;
  }

  /**
   * Recherche GIF Tenor (nécessite meta athena-tenor-key).
   * Retourne Promise<Array<{id,url,preview,title}>>
   */
  function searchGifs(query, limit) {
    var key = tenorKey();
    var q = String(query || '').trim();
    limit = Math.min(Math.max(parseInt(limit, 10) || 12, 1), 24);

    if (!key) {
      return Promise.reject(new Error('Clé Tenor absente'));
    }
    if (!q) {
      return Promise.resolve([]);
    }

    var url =
      'https://tenor.googleapis.com/v2/search?q=' +
      encodeURIComponent(q) +
      '&key=' +
      encodeURIComponent(key) +
      '&limit=' +
      limit +
      '&media_filter=gif,tinygif&contentfilter=medium';

    return fetch(url)
      .then(function (r) {
        if (!r.ok) throw new Error('Recherche GIF indisponible');
        return r.json();
      })
      .then(function (data) {
        var results = (data && data.results) || [];
        return results.map(function (item) {
          var media = item.media_formats || {};
          var gif = media.gif || media.mediumgif || media.tinygif || {};
          var preview = media.tinygif || media.nanogif || gif;
          return {
            id: item.id,
            title: item.content_description || item.title || '',
            url: gif.url || '',
            preview: preview.url || gif.url || '',
          };
        }).filter(function (g) { return !!g.url; });
      });
  }

  /** Remplit un conteneur .athena-gif-grid avec des résultats Tenor. */
  function renderGifGrid(container, gifs, onSelect) {
    if (!container) return;
    container.innerHTML = '';
    (gifs || []).forEach(function (g) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.title = g.title || 'GIF';
      var img = document.createElement('img');
      img.src = g.preview || g.url;
      img.alt = g.title || '';
      img.loading = 'lazy';
      btn.appendChild(img);
      btn.addEventListener('click', function () {
        if (typeof onSelect === 'function') onSelect(g);
      });
      container.appendChild(btn);
    });
  }

  /** Monte une animation Lottie dans [data-athena-lottie]. */
  function mountLottie(el) {
    if (!el || !global.lottie || typeof global.lottie.loadAnimation !== 'function') return null;
    var path = el.getAttribute('data-athena-lottie');
    if (!path) return null;
    el.innerHTML = '';
    return global.lottie.loadAnimation({
      container: el,
      renderer: 'svg',
      loop: el.getAttribute('data-loop') !== 'false',
      autoplay: el.getAttribute('data-autoplay') !== 'false',
      path: path,
    });
  }

  function mountAllLottie(root) {
    qsa('[data-athena-lottie]', root || document).forEach(mountLottie);
  }

  /**
   * Sélecteur emoji (Emoji Button) attaché à un bouton déclencheur.
   * callback(emojiNativeString)
   */
  function attachEmojiPicker(trigger, callback) {
    if (!global.EmojiButton || !trigger) return null;
    var picker = new global.EmojiButton({
      position: 'bottom-start',
      zIndex: 2400,
      showSearch: true,
      showPreview: false,
      showVariants: true,
      emojiSize: '1.4em',
    });
    picker.on('emoji', function (selection) {
      var emoji = selection && (selection.emoji || selection);
      if (typeof callback === 'function' && emoji) callback(String(emoji));
    });
    trigger.addEventListener('click', function (ev) {
      ev.preventDefault();
      picker.togglePicker(trigger);
    });
    return picker;
  }

  function boot() {
    if (hasPack('icons') || hasPack('hero')) {
      createIcons();
    }
    if (hasPack('emoji')) {
      parseEmoji(document.body);
    }
    if (hasPack('animation')) {
      initAos();
    }
    if (hasPack('lottie')) {
      mountAllLottie();
    }

    // Auto-parse emoji dans les zones marquées après mutations légères
    qsa('[data-athena-emoji]').forEach(function (el) {
      parseEmoji(el);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      // Laisser les scripts defer CDN s’enregistrer
      setTimeout(boot, 0);
    });
  } else {
    setTimeout(boot, 0);
  }

  // Re-scan après chargement complet (Lucide / Twemoji parfois tardifs)
  global.addEventListener('load', function () {
    if (hasPack('icons') || hasPack('hero')) createIcons();
    if (hasPack('emoji')) parseEmoji(document.body);
    if (hasPack('animation')) initAos();
  });

  var AthenaMedia = {
    packs: packs,
    hasPack: hasPack,
    parseEmoji: parseEmoji,
    createIcons: createIcons,
    initAos: initAos,
    flag: flag,
    gif: {
      search: searchGifs,
      renderGrid: renderGifGrid,
      hasKey: function () { return !!tenorKey(); },
    },
    lottie: {
      mount: mountLottie,
      mountAll: mountAllLottie,
    },
    emojiPicker: {
      attach: attachEmojiPicker,
    },
    refresh: boot,
  };

  global.AthenaMedia = AthenaMedia;
})(typeof window !== 'undefined' ? window : this);
