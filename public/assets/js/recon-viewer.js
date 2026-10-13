/**
 * COMSPEC — Lecteur d'images de reconnaissance (photos ATAK, drone, casque, remontées).
 *
 * Deux usages :
 *   ReconViewer.open(items, index, opts)        calque plein écran au-dessus de la page (carte ATAK, Tacmap, Overwatch…)
 *   ReconViewer.mount(el, items, index, opts)   page dédiée /recon/images/{id}
 *
 * items : lignes de GET /api/recon/images (ou { src, title, … }). Le lecteur ne fait que montrer ce que l'API a déjà
 * filtré : un cliché « restricted » (confidentiel sans habilitation) n'a pas d'URL et reste masqué.
 *
 * Zoom molette / pincement / double clic, déplacement à la souris ou au doigt, rotation, plein écran, téléchargement,
 * fiche du cliché, mini-carte (si Leaflet + config de carte), album précédent/suivant, clavier :
 * ← → photo, Échap fermer, + − zoom, 0 ajuster, R rotation, F plein écran, I fiche, D télécharger.
 */
(function (global) {
  'use strict';

  var CSS_ID = 'recon-viewer-css';
  var current = null;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function scriptSrc() {
    var el = document.currentScript;
    if (el && el.src) return el.src;
    var list = document.getElementsByTagName('script');
    for (var i = list.length - 1; i >= 0; i--) {
      if (/\/recon-viewer\.js(\?|$)/.test(list[i].src || '')) return list[i].src;
    }
    return '';
  }

  var SELF_SRC = scriptSrc();

  function ensureCss() {
    if (document.getElementById(CSS_ID) || document.querySelector('link[href*="recon-viewer.css"]')) return;
    if (!SELF_SRC) return;
    var link = document.createElement('link');
    link.id = CSS_ID;
    link.rel = 'stylesheet';
    link.href = SELF_SRC.replace(/\/js\/recon-viewer\.js/, '/css/recon-viewer.css');
    document.head.appendChild(link);
  }

  function siteRoot() {
    if (global.RECON_VIEWER_ROOT) return String(global.RECON_VIEWER_ROOT).replace(/\/$/, '');
    // …/assets/js/recon-viewer.js → racine publique du site
    var m = /^(.*?)\/assets\/js\/recon-viewer\.js/.exec(SELF_SRC);
    return m ? m[1] : '';
  }

  // --- Thème : fond de scène toujours sombre, fiche claire ou sombre comme le site ---
  function detectTheme() {
    var root = document.documentElement;
    var bo = root.getAttribute('data-bo-theme') || root.getAttribute('data-theme');
    if (bo === 'light' || bo === 'dark') return bo;
    if (root.classList.contains('dark')) return 'dark';
    try {
      var pref = localStorage.getItem('athena.bo.theme');
      if (pref === 'light' || pref === 'dark') return pref;
    } catch (e) {}
    return global.matchMedia && global.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
  }

  // --- Données ---
  function num(v) {
    if (v == null || v === '') return null;
    var n = parseFloat(v);
    return isFinite(n) ? n : null;
  }

  function formatWhen(raw) {
    var s = String(raw || '').trim();
    var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/.exec(s);
    if (!m) return s;
    return m[3] + '/' + m[2] + '/' + m[1] + ' · ' + m[4] + ':' + m[5] + (m[6] ? ':' + m[6] : '');
  }

  function sourceLabel(p) {
    var src = String(p.photo_source || '').toLowerCase();
    if (src === 'drone') return 'Drone';
    if (src === 'phone' || src === 'telephone' || src === 'photo') return 'Téléphone';
    if (src === 'helmet' || src === 'casque') return 'Caméra casque';
    if (p.device_label) return String(p.device_label);
    var d = String(p.device_type || '').toUpperCase();
    if (d === 'HELMET' || d === 'HCAM') return 'Caméra casque';
    if (d === 'DRONE') return 'Caméra drone';
    if (d === 'UAV' || d === 'VEHICLE') return 'Caméra aérienne';
    if (d === 'CTAB' || d === 'TABLET') return 'Photo tablette';
    return d ? 'Photo terrain' : '';
  }

  function missionLabel(p, opts) {
    if (p.mission_label) return String(p.mission_label);
    var id = String(p.mission_id || '').trim();
    if (!id) return '';
    var m = /^mission_\d+_map_(\d+)$/.exec(id);
    if (m) return opts.mapTitle ? String(opts.mapTitle) : 'Carte n° ' + m[1];
    return id;
  }

  function isFileRoute(u) {
    return /\/api\/recon\/images\/\d+\/file(\?|$)/.test(u);
  }

  function withParam(u, key) {
    return u + (u.indexOf('?') >= 0 ? '&' : '?') + key + '=1';
  }

  function normalize(p, opts) {
    p = p || {};
    var resolve = typeof opts.resolveUrl === 'function' ? opts.resolveUrl : function (x) { return x; };
    var src = '';
    if (!p.restricted) src = resolve(p.src || p.url || p.path || p.snapshot_url || '', p) || '';
    var id = p.id != null ? String(p.id) : '';
    var isRecon = p._recon === true || isFileRoute(src) || opts.recon === true;
    var x = num(p.pos_x), y = num(p.pos_y);
    var hasPos = x != null && y != null && !(Math.abs(x) < 0.5 && Math.abs(y) < 0.5);
    var tags = [];
    if (Number(p.is_confidential || 0)) tags.push({ label: 'CONFIDENTIEL', cls: 'conf' });
    if (Number(p.is_sse || 0) || p.sse_case_ref) {
      tags.push({ label: 'SSE' + (p.sse_case_ref ? ' ' + p.sse_case_ref : ''), href: p.sse_case_url || '', title: p.sse_case_title || '' });
    } else if (p.sse_case_id || p.sse_transferred_at) {
      tags.push({ label: 'Archivée dans SSE' });
    }
    if (p.drone_name) tags.push({ label: String(p.drone_name) });
    if (Number(p.is_blurred || 0)) tags.push({ label: 'Floutée' });
    var title = String(p.title || '').trim();
    var author = String(p.author_callsign || p.author || p.unit_name || '').trim();
    var dl = '';
    if (src) dl = isFileRoute(src) ? withParam(src, 'download') : src;
    var numericId = /^\d+$/.test(id);
    return {
      raw: p,
      id: id,
      src: src,
      downloadUrl: dl,
      rawUrl: src && isFileRoute(src) ? withParam(src, 'raw') : src,
      fileName: 'recon-' + (id || 'photo') + (/\.png(\?|$)/i.test(src) ? '.png' : '.jpg'),
      pageUrl: isRecon && numericId ? siteRoot() + '/recon/images/' + id : '',
      title: title,
      heading: title || String(p.caption || '').trim() || (author ? 'Cliché de ' + author : 'Cliché'),
      author: author,
      when: formatWhen(p.captured_at || p.capturedAt || p.created_at || ''),
      grid: String(p.grid_ref || p.grid || '').trim(),
      x: hasPos ? x : null,
      y: hasPos ? y : null,
      z: num(p.pos_z),
      dir: num(p.heading),
      alt: num(p.altitude),
      source: sourceLabel(p),
      mission: missionLabel(p, opts),
      caption: title ? String(p.caption || '').trim() : '',
      description: String(p.description || '').trim(),
      comment: String(p.operator_comment || '').trim(),
      tags: tags,
      blurred: !!Number(p.is_blurred || 0) || p._blurred === true,
      restricted: !!p.restricted,
    };
  }

  // --- Erreurs lisibles : on relit le statut HTTP de l'URL qui n'a pas chargé ---
  function diagnose(src) {
    if (!global.fetch) return Promise.resolve({ status: 0, message: '' });
    var ctrl = global.AbortController ? new AbortController() : null;
    return fetch(src, { credentials: 'include', cache: 'no-store', signal: ctrl ? ctrl.signal : undefined })
      .then(function (r) {
        var type = r.headers.get('Content-Type') || '';
        if (r.ok || type.indexOf('json') < 0) {
          if (ctrl) ctrl.abort();
          return { status: r.status, type: type, message: '' };
        }
        return r.json().then(function (j) {
          return { status: r.status, type: type, message: (j && (j.message || j.error)) || '' };
        }, function () { return { status: r.status, type: type, message: '' }; });
      }, function () { return { status: 0, message: '' }; });
  }

  function errorText(d) {
    var s = d.status;
    if (s === 404) return 'Cliché introuvable : supprimé, fichier absent du serveur, ou réservé aux habilitations « confidentiel » et plus.';
    if (s === 401 || s === 403) return 'Accès refusé : reconnectez-vous à Athena (communauté non identifiée).';
    if (s === 503) return 'Service indisponible (503)' + (d.message ? ' : ' + d.message : ' : carte en maintenance ou serveur surchargé. Réessayez dans un instant.');
    if (s >= 500) return 'Erreur du serveur (' + s + ') en lisant le fichier. Le détail est dans le journal du site (préfixe [atak/recon-images]).';
    if (s === 0) return 'Réseau injoignable : vérifiez la connexion puis réessayez.';
    if (s >= 200 && s < 300) return 'Fichier reçu mais illisible (format non pris en charge ou image corrompue).';
    return 'Image indisponible (erreur ' + s + ').';
  }

  // --- Lecteur ---
  var ICONS = {
    prev: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>',
    next: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>',
    zoomIn: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5M11 8v6M8 11h6"/></svg>',
    zoomOut: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5M8 11h6"/></svg>',
    rotate: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/></svg>',
    full: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>',
    download: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>',
    info: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 11v5M12 8h.01"/></svg>',
    open: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/></svg>',
    close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',
  };

  function Viewer(host, items, index, opts) {
    this.opts = opts || {};
    this.page = !!this.opts.page;
    this.items = (items || []).map(function (p) { return normalize(p, this.opts); }, this);
    this.index = Math.max(0, Math.min(this.items.length - 1, index || 0));
    this.host = host;
    this.s = { scale: 1, fit: 1, tx: 0, ty: 0, rot: 0, w: 0, h: 0 };
    this.pointers = {};
    this.revealed = {};
    this.miniMap = null;
    this.opener = document.activeElement;
    this.build();
    this.show(this.index);
  }

  Viewer.prototype.btn = function (act, icon, label, key) {
    return '<button type="button" class="rv__btn" data-rv="' + act + '" aria-label="' + esc(label) + '" title="' + esc(label + (key ? ' (' + key + ')' : '')) + '">' + ICONS[icon] + '</button>';
  };

  Viewer.prototype.build = function () {
    var o = this.opts;
    var root = document.createElement('div');
    root.className = 'rv' + (this.page ? ' rv--page' : ' rv--overlay');
    root.setAttribute('data-rv-theme', o.theme || detectTheme());
    if (!this.page) {
      root.setAttribute('role', 'dialog');
      root.setAttribute('aria-modal', 'true');
    }
    root.setAttribute('aria-labelledby', 'rv-title');
    var infoOpen = o.info !== false && !(global.innerWidth < 760);
    if (infoOpen) root.classList.add('rv--info');
    root.innerHTML =
      '<header class="rv__bar">' +
        '<div class="rv__headline"><span class="rv__count" data-rv-count></span><h1 class="rv__title" id="rv-title" data-rv-title translate="no"></h1></div>' +
        '<div class="rv__tools" role="toolbar" aria-label="Outils du lecteur">' +
          this.btn('zoomOut', 'zoomOut', 'Dézoomer', '−') +
          '<button type="button" class="rv__btn rv__zoom" data-rv="fit" title="Ajuster à l’écran / taille réelle (0)" aria-label="Ajuster à l’écran"><span data-rv-zoom>100 %</span></button>' +
          this.btn('zoomIn', 'zoomIn', 'Zoomer', '+') +
          this.btn('rotate', 'rotate', 'Pivoter de 90°', 'R') +
          this.btn('full', 'full', 'Plein écran', 'F') +
          '<a class="rv__btn" data-rv="download" href="#" download aria-label="Télécharger l’original" title="Télécharger l’original (D)">' + ICONS.download + '</a>' +
          (this.page ? '' : '<a class="rv__btn" data-rv="page" href="#" target="_blank" rel="noopener" aria-label="Ouvrir dans un onglet" title="Ouvrir dans un onglet (lien à partager)">' + ICONS.open + '</a>') +
          this.btn('info', 'info', 'Fiche du cliché', 'I') +
          (this.page && !o.backUrl ? '' : this.btn('close', 'close', this.page ? 'Retour' : 'Fermer', 'Échap')) +
        '</div>' +
      '</header>' +
      '<div class="rv__main">' +
        '<div class="rv__stage" data-rv-stage tabindex="0" aria-roledescription="visionneuse" aria-label="Image. Molette, pincement ou double clic pour zoomer, glisser pour déplacer.">' +
          '<img class="rv__img" data-rv-img alt="" draggable="false" decoding="async">' +
          '<div class="rv__state" data-rv-state role="status" aria-live="polite"></div>' +
          '<button type="button" class="rv__nav rv__nav--prev" data-rv="prev" aria-label="Photo précédente" title="Photo précédente (←)">' + ICONS.prev + '</button>' +
          '<button type="button" class="rv__nav rv__nav--next" data-rv="next" aria-label="Photo suivante" title="Photo suivante (→)">' + ICONS.next + '</button>' +
        '</div>' +
        '<aside class="rv__info" data-rv-info aria-label="Fiche du cliché"></aside>' +
      '</div>' +
      '<nav class="rv__strip" data-rv-strip aria-label="Album"></nav>' +
      '<p class="rv__sr" data-rv-live aria-live="polite"></p>';
    this.root = root;
    this.stage = root.querySelector('[data-rv-stage]');
    this.img = root.querySelector('[data-rv-img]');
    this.state = root.querySelector('[data-rv-state]');
    this.info = root.querySelector('[data-rv-info]');
    this.strip = root.querySelector('[data-rv-strip]');
    this.host.appendChild(root);
    if (!this.page) document.documentElement.classList.add('rv-lock');
    this.renderStrip();
    this.bind();
  };

  Viewer.prototype.q = function (sel) { return this.root.querySelector(sel); };

  Viewer.prototype.renderStrip = function () {
    if (this.items.length < 2) {
      this.strip.hidden = true;
      this.root.classList.add('rv--single');
      return;
    }
    this.strip.innerHTML = this.items.map(function (it, i) {
      return '<button type="button" class="rv__thumb' + (it.restricted || !it.src ? ' rv__thumb--empty' : '') + '" data-rv-go="' + i + '" aria-label="' + esc('Photo ' + (i + 1) + ' : ' + it.heading) + '">' +
        (it.src && !it.restricted ? '<img src="' + esc(it.src) + '" alt="" loading="lazy" decoding="async"' + (it.blurred ? ' class="is-blurred"' : '') + '>' : '<span aria-hidden="true">🔒</span>') +
        '</button>';
    }).join('');
  };

  Viewer.prototype.bind = function () {
    var self = this;
    this.root.addEventListener('click', function (ev) {
      var go = ev.target.closest('[data-rv-go]');
      if (go) { self.show(Number(go.getAttribute('data-rv-go'))); return; }
      var el = ev.target.closest('[data-rv]');
      if (!el) {
        if (!self.page && ev.target === self.stage && self.s.scale <= self.s.fit * 1.01 && !self.moved) self.close();
        return;
      }
      var act = el.getAttribute('data-rv');
      if (act === 'download' || act === 'page') {
        if (el.getAttribute('aria-disabled') === 'true') ev.preventDefault();
        return;
      }
      ev.preventDefault();
      self.action(act, el);
    });
    this.keyHandler = function (ev) { self.onKey(ev); };
    (this.page ? document : this.root).addEventListener('keydown', this.keyHandler);
    this.resizeHandler = function () { self.refit(false); };
    global.addEventListener('resize', this.resizeHandler);
    this.fsHandler = function () {
      self.root.classList.toggle('rv--fs', document.fullscreenElement === self.root);
      setTimeout(function () { self.refit(false); if (self.miniMap) self.miniMap.invalidateSize(); }, 60);
    };
    document.addEventListener('fullscreenchange', this.fsHandler);

    // Molette : zoom autour du curseur.
    this.stage.addEventListener('wheel', function (ev) {
      if (!self.loaded) return;
      ev.preventDefault();
      var dy = ev.deltaY * (ev.deltaMode === 1 ? 33 : ev.deltaMode === 2 ? 400 : 1);
      self.zoomAt(self.s.scale * Math.exp(-dy * 0.0016), self.local(ev));
    }, { passive: false });

    this.stage.addEventListener('dblclick', function (ev) {
      if (ev.target.closest('[data-rv]')) return;
      ev.preventDefault();
      self.toggleZoom(self.local(ev));
    });

    // Souris, stylet, doigts : déplacement, pincement, double tape, glisser pour changer de photo.
    this.stage.addEventListener('pointerdown', function (ev) {
      if (ev.target.closest('[data-rv]') || ev.button > 0) return;
      self.stage.setPointerCapture(ev.pointerId);
      self.pointers[ev.pointerId] = { x: ev.clientX, y: ev.clientY, type: ev.pointerType };
      self.moved = false;
      self.gesture = self.snapshot();
      self.root.classList.add('rv--dragging');
    });
    this.stage.addEventListener('pointermove', function (ev) {
      var p = self.pointers[ev.pointerId];
      if (!p || !self.gesture) return;
      p.x = ev.clientX; p.y = ev.clientY;
      var ids = Object.keys(self.pointers);
      var g = self.gesture;
      if (ids.length >= 2) {
        var a = self.pointers[ids[0]], b = self.pointers[ids[1]];
        var d = Math.hypot(a.x - b.x, a.y - b.y);
        var mid = self.local({ clientX: (a.x + b.x) / 2, clientY: (a.y + b.y) / 2 });
        if (!g.pinch) { g.pinch = { d: d, mid: mid, scale: self.s.scale, tx: self.s.tx, ty: self.s.ty }; }
        var k = self.clampScale(g.pinch.scale * d / (g.pinch.d || 1)) / g.pinch.scale;
        self.s.scale = g.pinch.scale * k;
        self.s.tx = mid.x - k * (g.pinch.mid.x - g.pinch.tx);
        self.s.ty = mid.y - k * (g.pinch.mid.y - g.pinch.ty);
        self.moved = true;
        self.apply();
        return;
      }
      var dx = ev.clientX - g.x, dy = ev.clientY - g.y;
      if (Math.abs(dx) + Math.abs(dy) > 4) self.moved = true;
      if (self.s.scale > self.s.fit * 1.01) {
        self.s.tx = g.tx + dx;
        self.s.ty = g.ty + dy;
        self.apply(true);
      } else if (self.items.length > 1) {
        self.img.style.setProperty('--rv-swipe', dx + 'px');
      }
    });
    var end = function (ev) {
      var p = self.pointers[ev.pointerId];
      if (!p) return;
      delete self.pointers[ev.pointerId];
      if (Object.keys(self.pointers).length) { self.gesture = self.snapshot(); return; }
      var g = self.gesture;
      self.gesture = null;
      self.root.classList.remove('rv--dragging');
      self.img.style.removeProperty('--rv-swipe');
      if (!g) return;
      var dx = ev.clientX - g.x;
      if (!g.pinch && self.s.scale <= self.s.fit * 1.01 && Math.abs(dx) > 70 && self.items.length > 1) {
        self.step(dx < 0 ? 1 : -1);
        return;
      }
      self.clampPan();
      self.apply();
      // Double tape au doigt (dblclick n'arrive pas toujours sur mobile).
      if (p.type === 'touch' && !self.moved && !g.pinch) {
        var now = Date.now();
        if (self.lastTap && now - self.lastTap.t < 320 && Math.hypot(ev.clientX - self.lastTap.x, ev.clientY - self.lastTap.y) < 30) {
          self.lastTap = null;
          self.toggleZoom(self.local(ev));
        } else {
          self.lastTap = { t: now, x: ev.clientX, y: ev.clientY };
        }
      }
    };
    this.stage.addEventListener('pointerup', end);
    this.stage.addEventListener('pointercancel', end);

    // Vignette d'album introuvable : repère au lieu de l'icône d'image cassée.
    this.strip.addEventListener('error', function (ev) {
      var b = ev.target && ev.target.closest ? ev.target.closest('.rv__thumb') : null;
      if (b) b.classList.add('rv__thumb--broken');
    }, true);
    this.img.addEventListener('load', function () { self.onLoad(); });
    this.img.addEventListener('error', function () { self.onError(); });
  };

  Viewer.prototype.snapshot = function () {
    var ids = Object.keys(this.pointers);
    var p = this.pointers[ids[0]] || { x: 0, y: 0 };
    return { x: p.x, y: p.y, tx: this.s.tx, ty: this.s.ty };
  };

  /** Point de l'événement, relatif au centre de la scène. */
  Viewer.prototype.local = function (ev) {
    var r = this.stage.getBoundingClientRect();
    return { x: ev.clientX - r.left - r.width / 2, y: ev.clientY - r.top - r.height / 2 };
  };

  Viewer.prototype.clampScale = function (s) {
    var min = Math.min(this.s.fit, 1) * 0.5;
    var max = Math.max(this.s.fit * 8, 6);
    return Math.max(min, Math.min(max, s));
  };

  Viewer.prototype.zoomAt = function (scale, pt) {
    if (!this.loaded) return;
    var next = this.clampScale(scale);
    var k = next / this.s.scale;
    pt = pt || { x: 0, y: 0 };
    this.s.tx = pt.x - k * (pt.x - this.s.tx);
    this.s.ty = pt.y - k * (pt.y - this.s.ty);
    this.s.scale = next;
    this.clampPan();
    this.apply();
  };

  Viewer.prototype.toggleZoom = function (pt) {
    if (this.s.scale > this.s.fit * 1.05) this.refit(true);
    else this.zoomAt(Math.max(this.s.fit * 2.5, 1), pt);
  };

  Viewer.prototype.dims = function () {
    var quarter = (this.s.rot / 90) % 2 !== 0;
    return quarter ? { w: this.s.h, h: this.s.w } : { w: this.s.w, h: this.s.h };
  };

  Viewer.prototype.clampPan = function () {
    var r = this.stage.getBoundingClientRect();
    var d = this.dims();
    var mx = Math.max(0, (d.w * this.s.scale - r.width) / 2);
    var my = Math.max(0, (d.h * this.s.scale - r.height) / 2);
    this.s.tx = Math.max(-mx, Math.min(mx, this.s.tx));
    this.s.ty = Math.max(-my, Math.min(my, this.s.ty));
  };

  Viewer.prototype.refit = function (reset) {
    if (!this.loaded) return;
    var r = this.stage.getBoundingClientRect();
    var d = this.dims();
    var pad = global.innerWidth < 640 ? 0 : 24;
    var fit = Math.min((r.width - pad) / d.w, (r.height - pad) / d.h);
    if (!isFinite(fit) || fit <= 0) fit = 1;
    var wasFit = this.s.scale <= this.s.fit * 1.01;
    this.s.fit = fit;
    if (reset || wasFit) {
      this.s.scale = fit;
      this.s.tx = 0;
      this.s.ty = 0;
    }
    this.clampPan();
    this.apply();
  };

  Viewer.prototype.apply = function (instant) {
    var s = this.s;
    this.img.style.width = s.w + 'px';
    this.img.style.height = s.h + 'px';
    this.img.style.transform = 'translate(-50%, -50%) translate(calc(' + s.tx.toFixed(1) + 'px + var(--rv-swipe, 0px)), ' + s.ty.toFixed(1) + 'px) rotate(' + s.rot + 'deg) scale(' + s.scale.toFixed(4) + ')';
    this.root.classList.toggle('rv--zoomed', s.scale > s.fit * 1.01);
    this.root.classList.toggle('rv--instant', !!instant);
    var z = this.q('[data-rv-zoom]');
    if (z) z.textContent = Math.round(s.scale * 100) + ' %';
  };

  Viewer.prototype.action = function (act) {
    var c = { x: 0, y: 0 };
    if (act === 'prev') this.step(-1);
    else if (act === 'next') this.step(1);
    else if (act === 'zoomIn') this.zoomAt(this.s.scale * 1.4, c);
    else if (act === 'zoomOut') this.zoomAt(this.s.scale / 1.4, c);
    else if (act === 'fit') {
      if (Math.abs(this.s.scale - this.s.fit) < 0.01) this.zoomAt(1, c); else this.refit(true);
    } else if (act === 'rotate') {
      this.s.rot = (this.s.rot + 90) % 360;
      this.refit(true);
    } else if (act === 'full') this.toggleFull();
    else if (act === 'info') {
      this.root.classList.toggle('rv--info');
      var self = this;
      setTimeout(function () { self.refit(false); if (self.miniMap) self.miniMap.invalidateSize(); }, 220);
    } else if (act === 'close') this.close();
    else if (act === 'reveal') {
      this.revealed[this.index] = true;
      this.img.classList.remove('rv__img--blur');
      this.setState('');
    } else if (act === 'retry') this.show(this.index, true);
    else if (act === 'locate') {
      var it = this.items[this.index];
      if (it && typeof this.opts.onLocate === 'function') {
        this.opts.onLocate(it.x, it.y, it.raw);
        if (!this.page) this.close();
      }
    }
  };

  Viewer.prototype.toggleFull = function () {
    var el = this.root;
    if (document.fullscreenElement) {
      if (document.exitFullscreen) document.exitFullscreen();
    } else if (el.requestFullscreen) {
      el.requestFullscreen().catch(function () {});
    } else if (el.webkitRequestFullscreen) {
      el.webkitRequestFullscreen();
    }
  };

  Viewer.prototype.onKey = function (ev) {
    if (ev.defaultPrevented || ev.ctrlKey || ev.metaKey || ev.altKey) return;
    var t = ev.target;
    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
    var k = ev.key;
    var map = {
      ArrowLeft: 'prev', ArrowRight: 'next', '+': 'zoomIn', '=': 'zoomIn', '-': 'zoomOut', '_': 'zoomOut',
      '0': 'fit', r: 'rotate', R: 'rotate', f: 'full', F: 'full', i: 'info', I: 'info',
    };
    if (k === 'Escape') {
      if (document.fullscreenElement) return;
      if (!this.page || this.opts.backUrl) { ev.preventDefault(); this.close(); }
      return;
    }
    if (k === 'd' || k === 'D') {
      var a = this.q('[data-rv="download"]');
      if (a && a.getAttribute('aria-disabled') !== 'true') { ev.preventDefault(); a.click(); }
      return;
    }
    if (k === 'Tab' && !this.page) { this.trapFocus(ev); return; }
    if (map[k]) { ev.preventDefault(); this.action(map[k]); }
  };

  Viewer.prototype.trapFocus = function (ev) {
    var list = Array.prototype.filter.call(
      this.root.querySelectorAll('button, a[href], [tabindex="0"]'),
      function (el) { return el.offsetParent !== null && !el.disabled; }
    );
    if (!list.length) return;
    var first = list[0], last = list[list.length - 1];
    if (ev.shiftKey && document.activeElement === first) { ev.preventDefault(); last.focus(); }
    else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
  };

  Viewer.prototype.step = function (dir) {
    var n = this.items.length;
    if (n < 2) return;
    var i = this.index + dir;
    if (i < 0 || i >= n) {
      if (this.opts.loop === false) return;
      i = (i + n) % n;
    }
    this.show(i);
  };

  Viewer.prototype.setState = function (html, kind) {
    this.state.innerHTML = html;
    this.state.className = 'rv__state' + (kind ? ' rv__state--' + kind : '');
  };

  Viewer.prototype.show = function (i, force) {
    var it = this.items[i];
    if (!it) return;
    var same = i === this.index && !force && this.img.getAttribute('src') === it.src;
    this.index = i;
    this.s.rot = 0;
    this.loaded = false;
    this.root.classList.remove('rv--zoomed');
    this.q('[data-rv-zoom]').textContent = '—';
    this.q('[data-rv-title]').textContent = it.heading;
    var count = this.q('[data-rv-count]');
    count.textContent = this.items.length > 1 ? (i + 1) + ' / ' + this.items.length : '';
    this.q('[data-rv-live]').textContent = 'Photo ' + (i + 1) + ' sur ' + this.items.length + ' : ' + it.heading;
    this.root.classList.toggle('rv--single', this.items.length < 2);

    var dl = this.q('[data-rv="download"]');
    dl.setAttribute('href', it.downloadUrl || '#');
    dl.setAttribute('download', it.fileName);
    dl.setAttribute('aria-disabled', it.downloadUrl ? 'false' : 'true');
    var pg = this.q('[data-rv="page"]');
    if (pg) {
      pg.setAttribute('href', it.pageUrl || '#');
      pg.hidden = !it.pageUrl;
    }
    var strip = this.strip.querySelectorAll('[data-rv-go]');
    for (var k = 0; k < strip.length; k++) {
      var on = k === i;
      strip[k].classList.toggle('is-active', on);
      if (on) {
        strip[k].setAttribute('aria-current', 'true');
        if (strip[k].scrollIntoView) strip[k].scrollIntoView({ block: 'nearest', inline: 'center' });
      } else {
        strip[k].removeAttribute('aria-current');
      }
    }
    this.renderInfo(it);

    if (this.page && it.pageUrl && global.history && history.replaceState && location.pathname !== it.pageUrl.replace(/^https?:\/\/[^/]+/, '')) {
      try { history.replaceState({ rv: it.id }, '', it.pageUrl); } catch (e) {}
      document.title = it.heading + ' · Athena';
    }

    this.img.classList.toggle('rv__img--blur', it.blurred && !this.revealed[i]);
    this.img.alt = it.title || it.caption || it.description || ('Cliché de ' + (it.author || 'reconnaissance'));
    if (it.restricted || !it.src) {
      this.img.removeAttribute('src');
      this.img.hidden = true;
      this.setState('<p><strong>' + (it.restricted ? 'Cliché confidentiel' : 'Image indisponible') + '</strong></p><p>' +
        (it.restricted ? 'Réservé aux habilitations « confidentiel » et plus. La fiche reste visible.' : 'Aucun fichier n’est associé à ce cliché.') + '</p>', 'error');
      this.preload(i);
      return;
    }
    this.img.hidden = false;
    this.setState('<span class="rv__spinner" aria-hidden="true"></span><span class="rv__sr">Chargement de l’image…</span>', 'loading');
    if (same && this.img.complete && this.img.naturalWidth) {
      this.onLoad();
    } else {
      this.img.removeAttribute('src');
      this.img.src = it.src;
    }
    this.preload(i);
  };

  Viewer.prototype.preload = function (i) {
    var n = this.items.length;
    [i + 1, i - 1].forEach(function (j) {
      var it = this.items[(j + n) % n];
      if (it && it.src && !it.restricted && !it._pre) {
        it._pre = new Image();
        it._pre.src = it.src;
      }
    }, this);
  };

  Viewer.prototype.onLoad = function () {
    if (!this.img.naturalWidth) return;
    this.loaded = true;
    this.s.w = this.img.naturalWidth;
    this.s.h = this.img.naturalHeight;
    this.s.scale = 0;
    this.refit(true);
    var it = this.items[this.index];
    if (it && it.blurred && !this.revealed[this.index]) {
      this.setState('<p>Cliché flouté par un opérateur.</p><button type="button" class="rv__pill" data-rv="reveal">Afficher quand même</button>', 'blur');
    } else {
      this.setState('');
    }
    var dims = this.info.querySelector('[data-rv-dims]');
    if (dims) dims.textContent = this.s.w + ' × ' + this.s.h + ' px';
  };

  Viewer.prototype.onError = function () {
    var it = this.items[this.index];
    if (!it || !it.src || this.img.getAttribute('src') !== it.src) return;
    this.img.hidden = true;
    var self = this;
    var idx = this.index;
    this.setState('<span class="rv__spinner" aria-hidden="true"></span><span class="rv__sr">Analyse de l’erreur…</span>', 'loading');
    diagnose(it.src).then(function (d) {
      if (self.index !== idx) return;
      self.setState('<p><strong>Image indisponible</strong></p><p>' + esc(errorText(d)) + '</p>' +
        '<p class="rv__state-actions"><button type="button" class="rv__pill" data-rv="retry">Réessayer</button>' +
        (it.downloadUrl ? ' <a class="rv__pill" href="' + esc(it.src) + '" target="_blank" rel="noopener">Ouvrir le fichier</a>' : '') + '</p>', 'error');
    });
  };

  Viewer.prototype.renderInfo = function (it) {
    var rows = [];
    function row(label, value, mono) {
      if (value == null || value === '') return;
      rows.push('<div class="rv__row"><dt>' + esc(label) + '</dt><dd' + (mono ? ' class="rv__mono"' : '') + ' translate="no">' + esc(value) + '</dd></div>');
    }
    row('Auteur', it.author);
    row('Date et heure', it.when, true);
    row('Source', it.source);
    row('Mission', it.mission);
    row('Grille', it.grid, true);
    if (it.x != null) {
      row('Coordonnées', 'X\u00a0' + Math.round(it.x) + ' · Y\u00a0' + Math.round(it.y) + (it.z != null ? ' · Z\u00a0' + Math.round(it.z) + '\u00a0m' : ''), true);
    }
    if (it.dir != null) row('Cap', Math.round(it.dir) + '°', true);
    if (it.alt != null && Math.abs(it.alt) > 0.5) row('Altitude', Math.round(it.alt) + ' m', true);

    var tags = it.tags.map(function (t) {
      var cls = 'rv__tag' + (t.cls ? ' rv__tag--' + t.cls : '');
      return t.href
        ? '<a class="' + cls + '" href="' + esc(t.href) + '" target="_blank" rel="noopener"' + (t.title ? ' title="' + esc(t.title) + '"' : '') + '>' + esc(t.label) + '</a>'
        : '<span class="' + cls + '">' + esc(t.label) + '</span>';
    }).join('');

    var texts = '';
    if (it.title && it.caption) texts += '<p class="rv__text" translate="no">' + esc(it.caption) + '</p>';
    if (it.description) texts += '<p class="rv__text" translate="no">' + esc(it.description) + '</p>';
    if (it.comment) texts += '<div class="rv__comment"><span>Commentaire de l’opérateur</span><p translate="no">' + esc(it.comment) + '</p></div>';

    var canMap = it.x != null && this.opts.map && global.L && typeof global.MGRS_CRS === 'function';
    var locate = '';
    if (it.x != null) {
      if (typeof this.opts.onLocate === 'function') {
        locate = '<button type="button" class="rv__pill" data-rv="locate">Localiser sur la carte</button>';
      } else if (this.opts.mapUrl) {
        locate = '<a class="rv__pill" href="' + esc(this.opts.mapUrl + (this.opts.mapUrl.indexOf('?') >= 0 ? '&' : '?') + 'focus=' + Math.round(it.x) + ',' + Math.round(it.y)) + '">Voir sur la carte</a>';
      }
    }

    this.info.innerHTML =
      '<h2 class="rv__info-title">Fiche du cliché</h2>' +
      (tags ? '<p class="rv__tags">' + tags + '</p>' : '') +
      (texts ? '<div class="rv__texts">' + texts + '</div>' : '') +
      '<dl class="rv__facts">' + rows.join('') + '<div class="rv__row"><dt>Taille</dt><dd class="rv__mono" data-rv-dims>' + (this.loaded ? this.s.w + ' × ' + this.s.h + ' px' : '—') + '</dd></div></dl>' +
      (canMap ? '<div class="rv__map" data-rv-map role="img" aria-label="Position du cliché sur la carte"></div>' : '') +
      (it.x == null ? '<p class="rv__muted">Position non transmise par l’appareil.</p>' : '') +
      (locate ? '<p class="rv__actions">' + locate + '</p>' : '') +
      '<p class="rv__keys" aria-hidden="true">' +
        '<span><kbd>←</kbd><kbd>→</kbd> <span>photo</span></span> <span><kbd>+</kbd><kbd>−</kbd> <span>zoom</span></span> ' +
        '<span><kbd>0</kbd> <span>ajuster</span></span> <span><kbd>R</kbd> <span>pivoter</span></span> ' +
        '<span><kbd>F</kbd> <span>plein écran</span></span>' + (this.page ? '' : ' <span><kbd>Échap</kbd> <span>fermer</span></span>') + '</p>';
    if (canMap) this.drawMap(it);
  };

  Viewer.prototype.drawMap = function (it) {
    var L = global.L;
    var cfg = this.opts.map;
    var el = this.info.querySelector('[data-rv-map]');
    if (!el) return;
    if (this.miniMap) { try { this.miniMap.remove(); } catch (e) {} this.miniMap = null; }
    try {
      var crs = cfg.crs || {};
      var maxNative = cfg.maxZoom != null ? Number(cfg.maxZoom) : 6;
      var world = Number(cfg.worldSize) || 30720;
      var map = L.map(el, {
        crs: global.MGRS_CRS(crs.factorx != null ? crs.factorx : 0.006839, crs.factory != null ? crs.factory : 0.006836, crs.tileWidth != null ? crs.tileWidth : 212),
        minZoom: cfg.minZoom != null ? cfg.minZoom : 0,
        maxZoom: maxNative + 2,
        zoomControl: true,
        attributionControl: false,
        scrollWheelZoom: 'center',
      });
      L.tileLayer(cfg.tilePattern, {
        tileSize: cfg.tileSize || 212,
        maxNativeZoom: maxNative,
        maxZoom: maxNative + 2,
        noWrap: true,
        bounds: L.latLngBounds(L.latLng(0, 0), L.latLng(world, world)),
        errorTileUrl: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
      }).addTo(map);
      var ll = L.latLng(it.y + (Number(cfg.offsetY) || 0), it.x + (Number(cfg.offsetX) || 0));
      L.circleMarker(ll, { radius: 7, color: '#f59e0b', weight: 3, fillColor: '#f59e0b', fillOpacity: 0.35 }).addTo(map);
      if (it.dir != null) {
        var rad = it.dir * Math.PI / 180;
        var len = 250;
        L.polyline([ll, L.latLng(ll.lat + Math.cos(rad) * len, ll.lng + Math.sin(rad) * len)], { color: '#f59e0b', weight: 2, dashArray: '4 4' }).addTo(map);
      }
      map.setView(ll, Math.max(0, maxNative - 1));
      this.miniMap = map;
    } catch (e) {
      el.remove();
    }
  };

  Viewer.prototype.close = function () {
    if (this.closed) return;
    if (this.page) {
      if (this.opts.backUrl) location.href = this.opts.backUrl;
      return;
    }
    this.closed = true;
    if (document.fullscreenElement === this.root && document.exitFullscreen) document.exitFullscreen();
    this.root.removeEventListener('keydown', this.keyHandler);
    global.removeEventListener('resize', this.resizeHandler);
    document.removeEventListener('fullscreenchange', this.fsHandler);
    if (this.miniMap) { try { this.miniMap.remove(); } catch (e) {} }
    this.root.remove();
    document.documentElement.classList.remove('rv-lock');
    if (current === this) current = null;
    if (this.opener && this.opener.focus) { try { this.opener.focus(); } catch (e) {} }
    if (typeof this.opts.onClose === 'function') this.opts.onClose();
  };

  function open(items, index, opts) {
    ensureCss();
    if (current) current.close();
    if (!Array.isArray(items)) items = [items];
    if (!items.length) return null;
    current = new Viewer(document.body, items, index || 0, opts || {});
    var stage = current.stage;
    setTimeout(function () { try { stage.focus({ preventScroll: true }); } catch (e) { stage.focus(); } }, 0);
    return current;
  }

  function mount(el, items, index, opts) {
    ensureCss();
    opts = opts || {};
    opts.page = true;
    return new Viewer(el, items || [], index || 0, opts);
  }

  /** Ouvre le lecteur sur une seule URL (pièce jointe, aperçu) sans fiche détaillée. */
  function openUrl(src, meta, opts) {
    var item = meta || {};
    item.src = src;
    return open([item], 0, opts || {});
  }

  /**
   * Liens marqués dans une vue PHP : <a href="image" data-recon-viewer="groupe" data-title="…" data-author="…"
   * data-when="…" data-caption="…" data-blurred="1">. Le lien reste le repli sans JavaScript.
   */
  document.addEventListener('click', function (ev) {
    if (ev.defaultPrevented || ev.button > 0 || ev.ctrlKey || ev.metaKey || ev.shiftKey) return;
    var a = ev.target && ev.target.closest ? ev.target.closest('a[data-recon-viewer]') : null;
    if (!a) return;
    var group = a.getAttribute('data-recon-viewer');
    var links = group ? document.querySelectorAll('a[data-recon-viewer="' + group.replace(/["\\]/g, '') + '"]') : [a];
    var items = [];
    var index = 0;
    Array.prototype.forEach.call(links, function (el) {
      if (el === a) index = items.length;
      items.push({
        src: el.getAttribute('href'),
        title: el.getAttribute('data-title') || '',
        author: el.getAttribute('data-author') || '',
        captured_at: el.getAttribute('data-when') || '',
        caption: el.getAttribute('data-caption') || '',
        device_label: el.getAttribute('data-source') || '',
        is_blurred: el.getAttribute('data-blurred') === '1' ? 1 : 0,
      });
    });
    ev.preventDefault();
    open(items, index, {});
  });

  global.ReconViewer = { open: open, mount: mount, openUrl: openUrl, normalize: normalize, errorText: errorText };
})(window);
