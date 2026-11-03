/**
 * Atelier de documents (COMSPEC IDENTITY, Lot 6) : aperçu en direct du papier fictif, bande MRZ recalculée par le site
 * (SseMrzService, GET …/atelier/mrz), choix des faces affichées et export de l'aperçu en image PNG (html2canvas,
 * chargé seulement au premier export). Le filigrane SIMULATION fait partie de chaque face exportée ; l'export le
 * redessine en plus directement sur l'image (diagonale sur toute la surface) avec le bandeau « DOCUMENT DE JEU – SANS
 * VALEUR » : ni le masquer dans la page ni retoucher le DOM ne permet d'obtenir une image sans eux.
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
    driving_license: 'PERMIS DE CONDUIRE · DRIVING LICENCE'
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
    ['id_card', 'passport', 'driving_license'].forEach(function (t) { preview.classList.toggle('idw-type--' + t, t === type); });
    preview.setAttribute('data-doc-type', type);
    setText('type_line', TYPE_LINES[type] || '');
    document.querySelectorAll('[data-face-show][data-kind]').forEach(function (b) {
      b.hidden = (b.getAttribute('data-kind') === 'passport') !== (type === 'passport');
    });
  }

  function applyLayout() {
    var layout = val('layout') || 'modern';
    ['modern', 'classic', 'compact'].forEach(function (l) { preview.classList.toggle('idw-layout--' + l, l === layout); });
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
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data || seq !== mrzSeq || !Array.isArray(data.lines)) return;
          setText('mrz', data.lines.join('\n'));
          var state = document.getElementById('idw-mrz-state');
          if (state) state.textContent = (data.valid ? 'Bande de lecture valide' : 'Bande de lecture incomplète') + (data.format ? ' · ' + data.format : '');
        })
        .catch(function () {});
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
    refreshAll();
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
  // Marques incrustées dans l'image exportée (non retirables) : filigrane diagonal sur toute la surface et bandeau.
  var BAND = 'DOCUMENT DE JEU – SANS VALEUR · GAME DOCUMENT – NO VALUE';
  function stampGameMarks(canvas) {
    var ctx = canvas.getContext('2d');
    if (!ctx) return;
    var w = canvas.width, h = canvas.height, diag = Math.sqrt(w * w + h * h);
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
  if (exportBtn) {
    exportBtn.addEventListener('click', function () {
      exportBtn.disabled = true;
      loadLib(exportBtn.getAttribute('data-lib')).then(function (h2c) {
        var faces = Array.prototype.filter.call(preview.querySelectorAll('.idw-face'), function (f) { return f.offsetParent !== null; });
        var base = exportBtn.getAttribute('data-name') || 'papier-simulation';
        return faces.reduce(function (p, face) {
          return p.then(function () {
            return h2c(face, { backgroundColor: null, scale: 2, useCORS: true, logging: false }).then(function (canvas) {
              stampGameMarks(canvas);
              var a = document.createElement('a');
              a.download = base + '-' + (face.getAttribute('data-face') || 'face') + '.png';
              a.href = canvas.toDataURL('image/png');
              document.body.appendChild(a);
              a.click();
              a.remove();
            });
          });
        }, Promise.resolve());
      }).catch(function () {
        window.alert('Export impossible dans ce navigateur.');
      }).then(function () { exportBtn.disabled = false; });
    });
  }

  refreshAll();
})();
