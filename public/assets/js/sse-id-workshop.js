/**
 * Atelier de documents (COMSPEC IDENTITY, Lot 6) : aperçu en direct du papier fictif, bande MRZ recalculée par le site
 * (SseMrzService, GET …/atelier/mrz), choix des faces affichées et export de l'aperçu en image PNG (html2canvas,
 * chargé seulement au premier export). Le marquage « document de jeu » (discret par défaut, voir SseIdMarking) est
 * dessiné directement sur l'image exportée, au-dessus de la photo, de la signature et de l'image de fond importées,
 * et la mention est écrite dans les métadonnées PNG (tEXt) : ni le masquer dans la page ni
 * retoucher le DOM ne retire la marque d'un pays réel stylisé.
 */
(function () {
  var form = document.getElementById('idw-form');
  var preview = document.getElementById('idw-preview');
  if (!form || !preview) return;

  var countries = {};
  try {
    countries = JSON.parse((document.getElementById('idw-countries') || {}).textContent || '{}') || {};
  } catch (err) {
    countries = {};
  }
  var TYPE_LINES = {
    id_card: 'CARTE D’IDENTITÉ · IDENTITY CARD',
    passport: 'PASSEPORT · PASSPORT',
    driving_license: 'PERMIS DE CONDUIRE · DRIVING LICENCE',
    military_id: 'CARTE MILITAIRE · MILITARY ID',
    residence_permit: 'TITRE DE SÉJOUR · RESIDENCE PERMIT',
    vehicle_registration: 'CERTIFICAT D’IMMATRICULATION · VEHICLE REGISTRATION'
  };

  function field(name) {
    return form.querySelector('[data-bind="' + name + '"]');
  }
  function val(name) {
    var el = field(name);
    return el ? String(el.value || '') : '';
  }
  function setText(key, text) {
    preview.querySelectorAll('[data-f="' + key + '"]').forEach(function (el) { el.textContent = text; });
  }

  function applyCountry() {
    var code = val('country');
    var c = countries[code];
    if (!c) return;
    setText('country_name', String(c.name || code).toUpperCase());
    setText('country_code', code);
    var colors = Array.isArray(c.colors) ? c.colors : [];
    preview.style.setProperty('--idw-c1', colors[0] || '#1f4e79');
    preview.style.setProperty('--idw-c2', colors[1] || '#c9a227');
    preview.querySelectorAll('[data-f-flag]').forEach(function (img) {
      if (c.flag) { img.src = c.flag; img.hidden = false; } else { img.hidden = true; }
    });
    preview.querySelectorAll('[data-f-flag-bands]').forEach(function (el) { el.hidden = !!c.flag; });
    ['europe', 'americas', 'other'].forEach(function (z) { preview.classList.toggle('idw-zone--' + z, c.zone === z); });
    var fmt = document.getElementById('idw-format');
    if (fmt) fmt.textContent = c.format || '';
    var auth = field('authority');
    if (auth && !auth.value && c.authority) auth.placeholder = c.authority;
  }

  function applyType() {
    var type = val('doc_type') || preview.getAttribute('data-doc-type') || 'id_card';
    ['id_card', 'passport', 'driving_license', 'military_id'].forEach(function (t) { preview.classList.toggle('idw-type--' + t, t === type); });
    preview.setAttribute('data-doc-type', type);
    setText('type_line', TYPE_LINES[type] || '');
    document.querySelectorAll('[data-face-show][data-kind]').forEach(function (b) {
      b.hidden = (b.getAttribute('data-kind') === 'passport') !== (type === 'passport');
    });
  }

  function applyLayout() {
    var layout = val('layout') || 'modern';
    ['modern', 'classic', 'compact', 'custom'].forEach(function (l) { preview.classList.toggle('idw-layout--' + l, l === layout); });
  }

  function applyFields() {
    setText('last_name', val('last_name').toUpperCase());
    setText('first_name', val('first_name'));
    setText('sex', val('sex'));
    setText('nationality', val('nationality').toUpperCase());
    setText('birth_date', val('birth_date'));
    setText('birth_place', val('birth_place'));
    setText('number', val('number').toUpperCase().replace(/[^A-Z0-9]/g, ''));
    setText('issued_on', val('issued_on'));
    setText('expires_on', val('expires_on'));
    var auth = field('authority');
    var authority = val('authority') || (auth ? auth.placeholder : '');
    setText('authority', authority);
    form.querySelectorAll('[data-bind-print]').forEach(function (input) {
      var key = input.getAttribute('data-bind-print');
      var v = String(input.value || '').trim();
      preview.querySelectorAll('[data-p="' + key + '"]').forEach(function (el) { el.textContent = v; });
      preview.querySelectorAll('[data-row-p="' + key + '"]').forEach(function (el) { el.hidden = v === ''; });
    });
    var sig = (form.querySelector('[data-bind-print="signature"]') || {}).value || (val('first_name') + ' ' + val('last_name'));
    preview.querySelectorAll('[data-p-signature]').forEach(function (el) { el.textContent = sig; });
    var stamp = (form.querySelector('[data-bind-print="stamp"]') || {}).value || authority;
    preview.querySelectorAll('[data-p-stamp]').forEach(function (el) { el.textContent = stamp; });
  }

  var mrzTimer = null;
  var mrzSeq = 0;
  function refreshMrz() {
    clearTimeout(mrzTimer);
    mrzTimer = setTimeout(function () {
      var url = form.getAttribute('data-mrz-url');
      if (!url || !window.fetch) return;
      var q = new URLSearchParams({
        doc_type: val('doc_type'), country: val('country'), number: val('number'), last: val('last_name'),
        first: val('first_name'), dob: val('birth_date'), sex: val('sex'), nat: val('nationality'), expires: val('expires_on')
      });
      var seq = ++mrzSeq;
      fetch(url + '?' + q.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { if (!r.ok) { if (window.SseNotify) { SseNotify.failure(r); } return null; } return r.json(); })
        .then(function (data) {
          if (!data || seq !== mrzSeq || !Array.isArray(data.lines)) return;
          setText('mrz', data.lines.join('\n'));
          var state = document.getElementById('idw-mrz-state');
          if (state) state.textContent = (data.valid ? 'Bande de lecture valide' : 'Bande de lecture incomplète') + (data.format ? ' · ' + data.format : '');
        })
        .catch(function (err) { if (window.SseNotify) { SseNotify.failure(err); } });
    }, 250);
  }

  function refreshAll() {
    applyCountry();
    applyType();
    applyLayout();
    applyFields();
    refreshMrz();
  }

  form.addEventListener('input', function () { applyFields(); refreshMrz(); });
  form.addEventListener('change', function (e) {
    var t = e.target;
    if (t && t.hasAttribute && t.hasAttribute('data-bind-photo') && t.files && t.files[0]) {
      var reader = new FileReader();
      reader.onload = function () {
        preview.querySelectorAll('[data-f-photo]').forEach(function (img) { img.src = String(reader.result); img.hidden = false; });
        preview.querySelectorAll('[data-f-silhouette]').forEach(function (s) { s.hidden = true; });
      };
      reader.readAsDataURL(t.files[0]);
      return;
    }
    // Signature et image de fond importées : aperçu immédiat (le filigrane reste par-dessus).
    var imgKind = t && t.getAttribute ? t.getAttribute('data-bind-img') : null;
    if (imgKind && t.files && t.files[0]) {
      var r2 = new FileReader();
      r2.onload = function () {
        var src = String(r2.result);
        if (imgKind === 'signature') {
          preview.querySelectorAll('[data-f-signature]').forEach(function (img) { img.src = src; img.hidden = false; });
          preview.querySelectorAll('[data-p-signature]').forEach(function (b) { b.hidden = true; });
        } else if (imgKind === 'background') {
          preview.querySelectorAll('[data-f-bg]').forEach(function (bg) { bg.style.backgroundImage = 'url("' + src + '")'; bg.hidden = false; });
        }
      };
      r2.readAsDataURL(t.files[0]);
      return;
    }
    refreshAll();
  });

  // Signature dessinée au pad (signature-pad.js) : aperçu immédiat ; pad vidé, on revient à la signature enregistrée.
  var sigImgs = Array.prototype.slice.call(preview.querySelectorAll('[data-f-signature]'));
  var sigInitial = sigImgs.map(function (img) { return { src: img.getAttribute('src') || '', hidden: img.hidden }; });
  form.addEventListener('signaturepad:change', function (e) {
    var pad = e.detail && e.detail.pad;
    var url = pad && !pad.isEmpty() ? pad.toDataURL() : '';
    sigImgs.forEach(function (img, i) {
      img.src = url !== '' ? url : sigInitial[i].src;
      img.hidden = url !== '' ? false : sigInitial[i].hidden;
    });
    preview.querySelectorAll('[data-p-signature]').forEach(function (b) {
      b.hidden = url !== '' || sigInitial.some(function (s) { return !s.hidden; });
    });
  });

  // Faces affichées
  document.querySelectorAll('[data-face-show]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var face = btn.getAttribute('data-face-show');
      document.querySelectorAll('[data-face-show]').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
      preview.setAttribute('data-show', face ? '1' : '0');
      preview.querySelectorAll('.idw-face').forEach(function (f) { f.classList.toggle('is-shown', f.getAttribute('data-face') === face); });
    });
  });

  // Export PNG : chaque face visible devient une image.
  var exportBtn = document.getElementById('idw-export');
  function loadLib(src) {
    return new Promise(function (resolve, reject) {
      if (window.html2canvas) { resolve(window.html2canvas); return; }
      var s = document.createElement('script');
      s.src = src;
      s.onload = function () { resolve(window.html2canvas); };
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }
  // Marquage « document de jeu » de l'image exportée (réglage de communauté lu dans data-marking, jamais retirable
  // par l'utilisateur) :
  //  - discreet (défaut) : micro-texte faible contraste en bas de chaque face ;
  //  - standard : ancien rendu, filigrane diagonal et bandeau pleine largeur ;
  //  - none : aucune marque visible (le serveur ne l'envoie jamais pour un pays réel stylisé).
  // Dans tous les cas la mention est aussi écrite dans les métadonnées du PNG (blocs tEXt).
  var BAND = 'DOCUMENT DE JEU – SANS VALEUR · GAME DOCUMENT – NO VALUE';
  var MICRO = 'Document de jeu – sans valeur · Game document – no value';
  var META = 'Document de jeu - sans valeur / Game document - no value';
  function markingMode() {
    var m = preview.getAttribute('data-marking');
    // Pays réel stylisé (zone de style définie) : jamais sans marque visible, même si le DOM est retouché.
    var sel = document.getElementById('idw-country');
    var c = sel ? countries[sel.value] : null;
    var real = (c && c.zone) || /idw-zone--/.test(preview.className);
    if (m === 'none' && real) return 'discreet';
    return m === 'standard' || m === 'none' ? m : 'discreet';
  }
  function stampDiscreet(ctx, w, h) {
    var size = Math.max(9, Math.round(h * 0.026));
    ctx.save();
    ctx.font = '600 ' + size + 'px Arial';
    ctx.textAlign = 'right';
    ctx.textBaseline = 'alphabetic';
    var x = w - Math.round(size * 0.9), y = h - Math.round(size * 0.8);
    ctx.lineWidth = Math.max(2, size * 0.28);
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.55)';
    ctx.strokeText(MICRO, x, y);
    ctx.fillStyle = 'rgba(40, 40, 48, 0.5)';
    ctx.fillText(MICRO, x, y);
    ctx.restore();
  }
  function stampStandard(ctx, w, h) {
    var diag = Math.sqrt(w * w + h * h);
    var size = Math.max(14, Math.round(h / 9));
    ctx.save();
    ctx.translate(w / 2, h / 2);
    ctx.rotate(-24 * Math.PI / 180);
    ctx.font = '900 ' + size + 'px Arial';
    ctx.fillStyle = 'rgba(179, 38, 30, 0.16)';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    for (var y = -diag / 2; y <= diag / 2; y += size * 1.9) {
      ctx.fillText('SIMULATION   SIMULATION   SIMULATION   SIMULATION', 0, y);
    }
    ctx.restore();
    var bh = Math.max(16, Math.round(h * 0.075));
    ctx.fillStyle = '#a8191e';
    ctx.fillRect(0, h - bh, w, bh);
    ctx.fillStyle = '#ffffff';
    ctx.font = '800 ' + Math.round(bh * 0.55) + 'px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(BAND, w / 2, h - bh / 2);
  }
  function stampGameMarks(canvas) {
    var ctx = canvas.getContext('2d');
    if (!ctx) return;
    var mode = markingMode();
    if (mode === 'standard') stampStandard(ctx, canvas.width, canvas.height);
    else if (mode === 'discreet') stampDiscreet(ctx, canvas.width, canvas.height);
  }
  var crcTable = null;
  function crc32(bytes) {
    if (!crcTable) {
      crcTable = [];
      for (var n = 0; n < 256; n++) {
        var c = n;
        for (var k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
        crcTable[n] = c >>> 0;
      }
    }
    var crc = 0xFFFFFFFF;
    for (var i = 0; i < bytes.length; i++) crc = crcTable[(crc ^ bytes[i]) & 0xFF] ^ (crc >>> 8);
    return (crc ^ 0xFFFFFFFF) >>> 0;
  }
  // Insère des blocs tEXt (mention de jeu) juste après l'IHDR d'un PNG ; renvoie un Blob.
  function pngWithMention(dataUrl) {
    var bin = atob(dataUrl.split(',')[1]);
    var src = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) src[i] = bin.charCodeAt(i);
    var parts = [];
    [['Description', META], ['Comment', META], ['Source', 'COMSPEC Athena - fictional ID workshop']].forEach(function (kv) {
      var text = kv[0] + '\0' + kv[1];
      var type = 'tEXt' + text;
      var buf = new Uint8Array(4 + type.length + 4);
      var dv = new DataView(buf.buffer);
      dv.setUint32(0, text.length);
      for (var j = 0; j < type.length; j++) buf[4 + j] = type.charCodeAt(j) & 0xFF;
      dv.setUint32(4 + type.length, crc32(buf.subarray(4, 4 + type.length)));
      parts.push(buf);
    });
    return new Blob([src.subarray(0, 33)].concat(parts, [src.subarray(33)]), { type: 'image/png' });
  }
  if (exportBtn) {
    exportBtn.addEventListener('click', function () {
      exportBtn.disabled = true;
      loadLib(exportBtn.getAttribute('data-lib')).then(function (h2c) {
        var faces = Array.prototype.filter.call(preview.querySelectorAll('.idw-face'), function (f) { return f.offsetParent !== null; });
        var base = exportBtn.getAttribute('data-name') || 'papier';
        return faces.reduce(function (p, face) {
          return p.then(function () {
            return h2c(face, { backgroundColor: null, scale: 2, useCORS: true, logging: false }).then(function (canvas) {
              stampGameMarks(canvas);
              var a = document.createElement('a');
              a.download = base + '-' + (face.getAttribute('data-face') || 'face') + '.png';
              var blob = pngWithMention(canvas.toDataURL('image/png'));
              a.href = URL.createObjectURL(blob);
              document.body.appendChild(a);
              a.click();
              a.remove();
              setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
            });
          });
        }, Promise.resolve());
      }).catch(function () {
        if (window.SseNotify) { SseNotify.error('Export impossible dans ce navigateur.'); }
      }).then(function () { exportBtn.disabled = false; });
    });
  }

  // ---- Zoom de l'aperçu, impression PDF -----------------------------------------------------------------------
  var zoom = 1;
  function setZoom(z) {
    zoom = Math.max(0.6, Math.min(1.6, z));
    preview.style.zoom = String(zoom);
    var r = document.getElementById('idw-zoom-reset');
    if (r) r.textContent = Math.round(zoom * 100) + ' %';
  }
  [['idw-zoom-out', -0.1], ['idw-zoom-in', 0.1]].forEach(function (z) {
    var b = document.getElementById(z[0]);
    if (b) b.addEventListener('click', function () { setZoom(zoom + z[1]); });
  });
  var zr = document.getElementById('idw-zoom-reset');
  if (zr) zr.addEventListener('click', function () { setZoom(1); });
  // PDF : boîte d'impression du navigateur (« enregistrer en PDF ») ; la feuille de style d'impression ne garde que
  // l'aperçu et y ajoute la mention de jeu selon data-marking.
  var printBtn = document.getElementById('idw-print');
  if (printBtn) printBtn.addEventListener('click', function () { window.print(); });

  // ---- Validation en ligne (n'empêche jamais l'enregistrement : le serveur tranche) -----------------------------
  function flag(el, msg) {
    if (!el) return;
    var id = el.id + '-err';
    var n = document.getElementById(id);
    if (!n) {
      n = document.createElement('p');
      n.id = id;
      n.className = 'idw-err';
      n.setAttribute('role', 'alert');
      n.hidden = true;
      el.parentNode.appendChild(n);
    }
    n.textContent = msg;
    n.hidden = msg === '';
    if (msg === '') { el.removeAttribute('aria-invalid'); el.removeAttribute('aria-describedby'); }
    else { el.setAttribute('aria-invalid', 'true'); el.setAttribute('aria-describedby', id); }
  }
  function formatToRegex(fmt) {
    if (!fmt) return null;
    var out = '';
    for (var i = 0; i < fmt.length; i++) {
      var ch = fmt.charAt(i);
      out += ch === '#' ? '[0-9]' : ch === '@' ? '[A-Za-z]' : ch.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    return new RegExp('^' + out + '$');
  }
  function validate() {
    var c = countries[val('country')] || {};
    var numEl = field('number');
    var re = formatToRegex(String(c.format || ''));
    flag(numEl, numEl && re && val('number') !== '' && !re.test(val('number')) ? 'Le numéro ne suit pas le format du pays.' : '');
    var iss = val('issued_on'), exp = val('expires_on'), dob = val('birth_date');
    flag(field('expires_on'), iss && exp && exp <= iss ? 'La date d’expiration doit suivre la date de délivrance.' : '');
    flag(field('birth_date'), dob && iss && dob >= iss ? 'La naissance doit précéder la délivrance.' : '');
  }
  form.addEventListener('input', validate);
  form.addEventListener('change', validate);

  // ---- Brouillon local automatique, annuler / rétablir -----------------------------------------------------------
  var undoBtn = document.getElementById('idw-undo');
  var redoBtn = document.getElementById('idw-redo');
  var draftEl = document.getElementById('idw-draft');
  var storeKey = 'idw-draft:' + location.pathname;
  function snapshot() {
    var o = {};
    form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (el) {
      if (el.type === 'file' || el.type === 'hidden' || el.disabled || el.name === '_csrf_token') return;
      o[el.name] = el.type === 'checkbox' ? el.checked : el.value;
    });
    return o;
  }
  function restoreSnapshot(o) {
    form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (el) {
      if (!(el.name in o) || el.type === 'file') return;
      if (el.type === 'checkbox') el.checked = !!o[el.name]; else el.value = o[el.name];
    });
    refreshAll();
    validate();
  }
  if (undoBtn && redoBtn) {
    var history = [JSON.stringify(snapshot())], pos = 0, saveTimer = null, applying = false;
    var syncButtons = function () { undoBtn.disabled = pos <= 0; redoBtn.disabled = pos >= history.length - 1; };
    try {
      var saved = window.localStorage.getItem(storeKey);
      if (saved && saved !== history[0] && window.confirm('Un brouillon local non enregistré existe pour ce papier. Le reprendre ?')) {
        restoreSnapshot(JSON.parse(saved));
        history = [saved]; pos = 0;
        if (draftEl) draftEl.textContent = 'Brouillon local repris';
      }
    } catch (err) { /* stockage indisponible */ }
    form.addEventListener('input', function () {
      if (applying) return;
      clearTimeout(saveTimer);
      saveTimer = setTimeout(function () {
        var snap = JSON.stringify(snapshot());
        if (snap === history[pos]) return;
        history = history.slice(0, pos + 1); history.push(snap); if (history.length > 40) history.shift();
        pos = history.length - 1;
        syncButtons();
        try { window.localStorage.setItem(storeKey, snap); if (draftEl) draftEl.textContent = 'Brouillon local enregistré (pas encore sur le site)'; }
        catch (err) { if (draftEl) draftEl.textContent = 'Brouillon local indisponible'; }
      }, 600);
    });
    var go = function (d) {
      var n = pos + d;
      if (n < 0 || n >= history.length) return;
      pos = n; applying = true; restoreSnapshot(JSON.parse(history[pos])); applying = false; syncButtons();
    };
    undoBtn.addEventListener('click', function () { go(-1); });
    redoBtn.addEventListener('click', function () { go(1); });
    form.addEventListener('submit', function () { try { window.localStorage.removeItem(storeKey); } catch (err) { /* ignore */ } });
  }

  refreshAll();
  validate();
})();
