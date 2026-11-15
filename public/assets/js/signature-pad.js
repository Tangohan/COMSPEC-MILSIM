/**
 * Pad de signature réutilisable (sans dépendance).
 *
 * Markup : views/partials/signature_pad.php. Chaque élément [data-sigpad] devient un pad : dessin à la souris, au doigt
 * ou au stylet (Pointer Events, pression du stylet si elle existe), traits lissés, annulation du dernier trait,
 * effacement, import d'un fichier image, signature « tapée » avec une police manuscrite (alternative au clavier).
 *
 * Sortie : le champ caché [data-sigpad-out] reçoit un PNG en data URL (fond transparent ou blanc, rogné sur l'encre),
 * vide tant que le pad est vide. Le champ fichier natif du formulaire (s'il existe) continue d'être envoyé ; le serveur
 * donne la priorité au dessin.
 *
 * Aucun texte n'est écrit ici : les libellés viennent de la vue (balises [data-sigpad-t] cachées, traduites avec la
 * page). Événement émis sur l'élément : « signaturepad:change » (detail.empty). Accès : ComspecSignaturePad.get(el).
 */
(function () {
  'use strict';

  var FONT = '"Segoe Script","Brush Script MT","Lucida Handwriting","Apple Chancery","Snell Roundhand","Bradley Hand",cursive';
  var BASE_WIDTH = 2.4; // épaisseur de base du trait, en pixels pour un pad de 600 px
  var instances = [];

  function luminance(color) {
    var c = document.createElement('canvas').getContext('2d');
    c.fillStyle = '#000';
    c.fillStyle = color;
    var m = /^#([0-9a-f]{6})$/i.exec(c.fillStyle);
    if (!m) { return 0; }
    var n = parseInt(m[1], 16);
    return (0.2126 * ((n >> 16) & 255) + 0.7152 * ((n >> 8) & 255) + 0.0722 * (n & 255)) / 255;
  }

  function SignaturePad(root) {
    this.root = root;
    this.canvas = root.querySelector('[data-sigpad-canvas]');
    this.out = root.querySelector('[data-sigpad-out]');
    this.fileInput = root.querySelector('[data-sigpad-file]');
    this.status = root.querySelector('[data-sigpad-status]');
    this.hint = root.querySelector('[data-sigpad-hint]');
    this.typeInput = root.querySelector('[data-sigpad-name]');
    this.btnUndo = root.querySelector('[data-sigpad-undo]');
    this.btnClear = root.querySelector('[data-sigpad-clear]');
    this.btnType = root.querySelector('[data-sigpad-type]');
    this.ink = root.getAttribute('data-ink') || '#0b1220';
    this.opaque = root.getAttribute('data-background') === 'white';
    this.required = root.getAttribute('data-required') === '1';
    this.maxSide = parseInt(root.getAttribute('data-max-side') || '900', 10);
    this.trim = root.getAttribute('data-trim') !== '0';
    this.cssH = parseInt(root.getAttribute('data-height') || '160', 10);
    this.cssW = 0;
    this.layers = [];     // { type: 'stroke', pts: [{x, y, w}] } | { type: 'text', text } | { type: 'image', img }
    this.active = null;   // trait en cours
    this.pointerId = null;
    this.lastSync = null;
    this.timer = 0;
    this.texts = {};
    root.querySelectorAll('[data-sigpad-t]').forEach(function (el) {
      this.texts[el.getAttribute('data-sigpad-t')] = el.textContent.trim();
    }, this);
    if (!this.canvas || !this.canvas.getContext) { return; }
    this.ctx = this.canvas.getContext('2d');
    this.dark = luminance(this.ink) > 0.7;
    root.classList.toggle('sigpad--ink-light', this.dark);
    this.bind();
    this.resize();
    this.refresh(false);
    instances.push(this);
  }

  SignaturePad.prototype.t = function (key) { return this.texts[key] || ''; };

  SignaturePad.prototype.isEmpty = function () {
    return !this.layers.length;
  };

  SignaturePad.prototype.hasFile = function () {
    return !!(this.fileInput && this.fileInput.files && this.fileInput.files.length);
  };

  /* ---------- géométrie ---------- */

  SignaturePad.prototype.resize = function () {
    var rect = this.canvas.getBoundingClientRect();
    var w = Math.floor(rect.width);
    if (w < 40) { return; } // masqué (onglet, fenêtre) : repris par l'observateur quand il devient visible
    var dpr = Math.min(window.devicePixelRatio || 1, 3);
    var changed = w !== this.cssW;
    if (changed && this.cssW > 0) {
      var f = w / this.cssW;
      this.layers.forEach(function (l) {
        if (l.type === 'stroke') {
          l.pts.forEach(function (p) { p.x *= f; p.y *= f; p.w *= f; });
        }
      });
    }
    this.cssW = w;
    this.canvas.width = Math.round(w * dpr);
    this.canvas.height = Math.round(this.cssH * dpr);
    this.canvas.style.height = this.cssH + 'px';
    this.dpr = dpr;
    this.redraw();
  };

  SignaturePad.prototype.position = function (e) {
    var r = this.canvas.getBoundingClientRect();
    var sx = this.cssW / Math.max(1, r.width);
    var sy = this.cssH / Math.max(1, r.height);
    return { x: (e.clientX - r.left) * sx, y: (e.clientY - r.top) * sy, t: e.timeStamp || Date.now(), p: e.pressure };
  };

  /* ---------- dessin ---------- */

  SignaturePad.prototype.scaleFactor = function () { return this.cssW / 600; };

  SignaturePad.prototype.paintStroke = function (ctx, pts, from) {
    var n = pts.length;
    if (n === 0) { return; }
    ctx.fillStyle = this.ink;
    ctx.strokeStyle = this.ink;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    if (n === 1) {
      ctx.beginPath();
      ctx.arc(pts[0].x, pts[0].y, Math.max(0.8, pts[0].w * 0.6), 0, Math.PI * 2);
      ctx.fill();
      return;
    }
    // Courbe quadratique entre les milieux des points : trait lisse, épaisseur propre à chaque morceau.
    for (var i = Math.max(1, from); i < n; i++) {
      var a = pts[i - 1];
      var b = pts[i];
      var prev = i >= 2 ? { x: (pts[i - 2].x + a.x) / 2, y: (pts[i - 2].y + a.y) / 2 } : a;
      var mid = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
      ctx.beginPath();
      ctx.lineWidth = Math.max(0.6, (a.w + b.w) / 2);
      ctx.moveTo(prev.x, prev.y);
      ctx.quadraticCurveTo(a.x, a.y, mid.x, mid.y);
      if (i === n - 1) { ctx.lineTo(b.x, b.y); }
      ctx.stroke();
    }
  };

  SignaturePad.prototype.textFit = function (ctx, text, w, h) {
    var size = Math.min(h * 0.62, 120);
    ctx.font = 'italic ' + size + 'px ' + FONT;
    var m = ctx.measureText(text).width;
    var max = w * 0.9;
    if (m > max) { size = Math.max(10, size * max / m); ctx.font = 'italic ' + size + 'px ' + FONT; m = ctx.measureText(text).width; }
    return { size: size, width: m };
  };

  /** Dessine toutes les couches sur ctx, dans un repère de w × h pixels CSS. */
  SignaturePad.prototype.paintLayers = function (ctx, w, h) {
    var self = this;
    this.layers.forEach(function (l) {
      if (l.type === 'image') {
        var r = Math.min(w * 0.96 / l.img.width, h * 0.92 / l.img.height, 1.6);
        var iw = l.img.width * r;
        var ih = l.img.height * r;
        ctx.drawImage(l.img, (w - iw) / 2, (h - ih) / 2, iw, ih);
      } else if (l.type === 'text') {
        self.textFit(ctx, l.text, w, h);
        ctx.fillStyle = self.ink;
        ctx.textBaseline = 'alphabetic';
        ctx.textAlign = 'center';
        ctx.fillText(l.text, w / 2, h * 0.62);
      } else {
        self.paintStroke(ctx, l.pts, 0);
      }
    });
  };

  SignaturePad.prototype.redraw = function () {
    if (!this.cssW) { return; }
    this.lastSync = null;
    var ctx = this.ctx;
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    ctx.setTransform(this.dpr, 0, 0, this.dpr, 0, 0);
    this.paintLayers(ctx, this.cssW, this.cssH);
  };

  /* ---------- événements du pointeur ---------- */

  SignaturePad.prototype.bind = function () {
    var self = this;
    var c = this.canvas;
    c.addEventListener('pointerdown', function (e) {
      if (self.pointerId !== null) { return; }
      if (e.pointerType === 'mouse' && e.button !== 0) { return; }
      if (Math.floor(c.getBoundingClientRect().width) !== self.cssW) { self.resize(); } // pad devenu visible, ou page redimensionnée
      if (!self.cssW) { return; }
      self.pointerId = e.pointerId;
      try { c.setPointerCapture(e.pointerId); } catch (err) { /* pointeur non actif (événement simulé) : sans conséquence */ }
      var p = self.position(e);
      var k = self.scaleFactor();
      var pen = e.pointerType === 'pen' && e.pressure > 0;
      p.w = BASE_WIDTH * k * (pen ? 0.5 + e.pressure : 1.1);
      self.active = { type: 'stroke', pts: [p], pen: pen, v: 0 };
      self.layers.push(self.active);
      self.redraw();
      e.preventDefault();
    });
    c.addEventListener('pointermove', function (e) {
      if (self.active === null || e.pointerId !== self.pointerId) { return; }
      var list = typeof e.getCoalescedEvents === 'function' ? e.getCoalescedEvents() : [];
      if (!list.length) { list = [e]; }
      list.forEach(function (ev) { self.addPoint(ev); });
      e.preventDefault();
    });
    var end = function (e) {
      if (self.active === null || e.pointerId !== self.pointerId) { return; }
      self.pointerId = null;
      var stroke = self.active;
      self.active = null;
      if (e.type !== 'pointercancel' && stroke.pts.length === 1) {
        stroke.pts[0].w *= 1.6; // simple appui : point visible
      }
      self.redraw();
      self.refresh(true);
    };
    c.addEventListener('pointerup', end);
    c.addEventListener('pointercancel', end);
    c.addEventListener('lostpointercapture', end);
    c.addEventListener('contextmenu', function (e) { e.preventDefault(); });

    if (this.btnUndo) { this.btnUndo.addEventListener('click', function () { self.undo(); }); }
    if (this.btnClear) { this.btnClear.addEventListener('click', function () { self.clear(); }); }
    if (this.btnType && this.typeInput) {
      var applyName = function () { self.setText(self.typeInput.value); };
      this.btnType.addEventListener('click', applyName);
      this.typeInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); applyName(); }
      });
    }
    if (this.fileInput) {
      this.fileInput.addEventListener('change', function () { self.importFile(); });
    }

    if (typeof ResizeObserver === 'function') {
      var ro = new ResizeObserver(function () { self.resize(); });
      ro.observe(c);
    } else {
      window.addEventListener('resize', function () { self.resize(); });
    }

    var form = this.root.closest('form');
    if (form) {
      form.addEventListener('submit', function (e) {
        self.sync();
        if (self.required && self.isEmpty() && !self.hasFile()) {
          e.preventDefault();
          self.root.classList.add('sigpad--invalid');
          self.canvas.setAttribute('aria-invalid', 'true');
          self.setStatus(self.t('required'));
          if (typeof self.root.scrollIntoView === 'function') { self.root.scrollIntoView({ block: 'center' }); }
        }
      }, true);
      form.addEventListener('formdata', function () { self.sync(); });
      form.addEventListener('reset', function () { self.clear(); });
    }
  };

  SignaturePad.prototype.addPoint = function (e) {
    var s = this.active;
    var p = this.position(e);
    var last = s.pts[s.pts.length - 1];
    var dx = p.x - last.x;
    var dy = p.y - last.y;
    var dist = Math.sqrt(dx * dx + dy * dy);
    if (dist < 0.8) { return; }
    var k = this.scaleFactor();
    var target;
    if (s.pen && e.pressure > 0) {
      target = BASE_WIDTH * k * (0.5 + e.pressure * 1.1);
    } else {
      var dt = Math.max(1, p.t - last.t);
      var v = dist / dt; // pixels par milliseconde : plus c'est rapide, plus le trait s'affine
      target = BASE_WIDTH * k * Math.min(1.35, Math.max(0.6, 1.6 - v * 0.55));
    }
    p.w = last.w * 0.7 + target * 0.3;
    s.pts.push(p);
    this.ctx.setTransform(this.dpr, 0, 0, this.dpr, 0, 0);
    this.paintStroke(this.ctx, s.pts, s.pts.length - 1);
    if (this.hint) { this.hint.hidden = true; }
  };

  /* ---------- actions ---------- */

  SignaturePad.prototype.undo = function () {
    if (this.active !== null) { return; }
    this.layers.pop();
    if (this.isEmpty() && this.fileInput) { this.fileInput.value = ''; }
    this.redraw();
    this.refresh(true);
  };

  SignaturePad.prototype.clear = function () {
    this.layers = [];
    this.active = null;
    this.pointerId = null;
    if (this.fileInput) { this.fileInput.value = ''; }
    if (this.typeInput) { this.typeInput.value = ''; }
    this.redraw();
    this.refresh(true);
  };

  SignaturePad.prototype.setText = function (text) {
    text = String(text || '').replace(/\s+/g, ' ').trim().slice(0, 60);
    this.layers = this.layers.filter(function (l) { return l.type !== 'text'; });
    if (text !== '') { this.layers.push({ type: 'text', text: text }); }
    this.redraw();
    this.refresh(true);
  };

  SignaturePad.prototype.importFile = function () {
    var self = this;
    var f = this.fileInput && this.fileInput.files ? this.fileInput.files[0] : null;
    this.layers = this.layers.filter(function (l) { return l.type !== 'image'; });
    if (!f) { this.redraw(); this.refresh(true); return; }
    var url = URL.createObjectURL(f);
    var img = new Image();
    img.onload = function () {
      URL.revokeObjectURL(url);
      self.layers.unshift({ type: 'image', img: img });
      self.redraw();
      self.refresh(true);
    };
    img.onerror = function () {
      URL.revokeObjectURL(url);
      // Format que le navigateur ne sait pas lire : le fichier part tel quel, le site le contrôlera.
      self.refresh(true);
      self.setStatus(self.t('file_only'));
    };
    img.src = url;
  };

  /* ---------- sortie ---------- */

  SignaturePad.prototype.setStatus = function (text) {
    if (this.status) { this.status.textContent = text; }
  };

  SignaturePad.prototype.refresh = function (notify) {
    var empty = this.isEmpty();
    this.root.setAttribute('data-empty', empty ? '1' : '0');
    if (empty && this.out) { this.out.value = ''; }
    if (this.btnUndo) { this.btnUndo.disabled = empty; }
    if (this.btnClear) { this.btnClear.disabled = empty && !this.hasFile(); }
    if (this.hint) { this.hint.hidden = !empty; }
    this.setStatus(empty ? (this.hasFile() ? this.t('file_only') : this.t('empty')) : this.t('ready'));
    if (!empty) {
      this.root.classList.remove('sigpad--invalid');
      this.canvas.removeAttribute('aria-invalid');
    }
    this.lastSync = null;
    if (notify) {
      var self = this;
      window.clearTimeout(this.timer);
      this.timer = window.setTimeout(function () {
        self.sync();
        self.root.dispatchEvent(new CustomEvent('signaturepad:change', { bubbles: true, detail: { empty: self.isEmpty(), pad: self } }));
      }, 120);
    }
  };

  /** Met le data URL dans le champ caché (vide si le pad est vide). */
  SignaturePad.prototype.sync = function () {
    if (this.out) { this.out.value = this.toDataURL(); }
  };

  /** PNG (data URL) du dessin, rogné sur l'encre ; chaîne vide si le pad est vide. */
  SignaturePad.prototype.toDataURL = function () {
    if (this.isEmpty() || !this.cssW) { return ''; }
    if (this.lastSync !== null) { return this.lastSync; }
    var w = this.cssW;
    var h = this.cssH;
    var box = { x0: 0, y0: 0, x1: w, y1: h };
    if (this.trim) {
      var b = this.inkBounds(w, h);
      if (b) { box = b; }
    }
    var bw = Math.max(1, box.x1 - box.x0);
    var bh = Math.max(1, box.y1 - box.y0);
    var scale = Math.min(2, this.maxSide / Math.max(bw, bh));
    var out = document.createElement('canvas');
    out.width = Math.max(1, Math.round(bw * scale));
    out.height = Math.max(1, Math.round(bh * scale));
    var ctx = out.getContext('2d');
    if (this.opaque) {
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, out.width, out.height);
    }
    ctx.setTransform(scale, 0, 0, scale, -box.x0 * scale, -box.y0 * scale);
    this.paintLayers(ctx, w, h);
    this.lastSync = out.toDataURL('image/png');
    return this.lastSync;
  };

  /** Cadre de l'encre (traits, texte, image) avec une marge ; null si on ne sait pas le calculer. */
  SignaturePad.prototype.inkBounds = function (w, h) {
    var x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    var self = this;
    this.layers.forEach(function (l) {
      if (l.type === 'stroke') {
        l.pts.forEach(function (p) {
          var r = Math.max(1, p.w) * 0.8;
          x0 = Math.min(x0, p.x - r); x1 = Math.max(x1, p.x + r);
          y0 = Math.min(y0, p.y - r); y1 = Math.max(y1, p.y + r);
        });
      } else if (l.type === 'text') {
        var fit = self.textFit(self.ctx, l.text, w, h);
        x0 = Math.min(x0, (w - fit.width) / 2); x1 = Math.max(x1, (w + fit.width) / 2);
        y0 = Math.min(y0, h * 0.62 - fit.size * 0.85); y1 = Math.max(y1, h * 0.62 + fit.size * 0.35);
      } else {
        var r2 = Math.min(w * 0.96 / l.img.width, h * 0.92 / l.img.height, 1.6);
        var iw = l.img.width * r2;
        var ih = l.img.height * r2;
        x0 = Math.min(x0, (w - iw) / 2); x1 = Math.max(x1, (w + iw) / 2);
        y0 = Math.min(y0, (h - ih) / 2); y1 = Math.max(y1, (h + ih) / 2);
      }
    });
    if (!isFinite(x0) || !isFinite(y0)) { return null; }
    var pad = 6;
    return { x0: Math.floor(x0 - pad), y0: Math.floor(y0 - pad), x1: Math.ceil(x1 + pad), y1: Math.ceil(y1 + pad) };
  };

  /* ---------- initialisation ---------- */

  function init(scope) {
    var found = [];
    (scope || document).querySelectorAll('[data-sigpad]').forEach(function (el) {
      if (el.__sigpad) { return; }
      el.__sigpad = new SignaturePad(el);
      found.push(el.__sigpad);
    });
    return found;
  }

  window.ComspecSignaturePad = {
    init: init,
    get: function (el) {
      if (typeof el === 'string') { el = document.getElementById(el); }
      var root = el && el.closest ? el.closest('[data-sigpad]') : null;
      return root ? (root.__sigpad || null) : null;
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(document); });
  } else {
    init(document);
  }
})();
