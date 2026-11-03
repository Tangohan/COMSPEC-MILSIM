/**
 * Images des papiers fictifs (COMSPEC IDENTITY) : recadrage de la photo du titulaire dans le navigateur (format 35 × 45
 * ou carré, zoom et déplacement), puis remplacement du fichier envoyé par l'image recadrée (JPEG) ; bouton « Copier le
 * prompt » de l'aide photomaton (variantes cochées ajoutées). Le site recadre et nettoie l'image de toute façon
 * (SseIdImageService) : sans ce script, l'envoi marche quand même.
 */
(function () {
  var OUT = { '35x45': [700, 900], '1x1': [900, 900] };

  document.querySelectorAll('input[type="file"][data-crop]').forEach(function (input) {
    var field = input.closest('.idi-photo-field') || input.parentElement;
    var panel = field ? field.querySelector('[data-crop-panel]') : null;
    if (!panel || !window.DataTransfer || !window.FileReader) return;
    var canvas = panel.querySelector('[data-crop-canvas]');
    var zoomEl = panel.querySelector('[data-crop-zoom]');
    var ratioEl = panel.querySelector('[data-crop-ratio]');
    var applyBtn = panel.querySelector('[data-crop-apply]');
    var done = panel.querySelector('[data-crop-done]');
    var ctx = canvas.getContext('2d');
    var img = null;
    var state = { x: 0, y: 0, zoom: 1 };
    var skip = false;

    function frame() {
      var r = (ratioEl && ratioEl.value === '1x1') ? 1 : 35 / 45;
      var h = 360;
      return { w: Math.round(h * r), h: h };
    }
    function baseScale(f) {
      return Math.max(f.w / img.width, f.h / img.height);
    }
    function clamp(f) {
      var s = baseScale(f) * state.zoom;
      var maxX = Math.max(0, (img.width * s - f.w) / 2);
      var maxY = Math.max(0, (img.height * s - f.h) / 2);
      state.x = Math.min(maxX, Math.max(-maxX, state.x));
      state.y = Math.min(maxY, Math.max(-maxY, state.y));
    }
    function draw(target, tw, th) {
      var f = frame();
      clamp(f);
      var s = baseScale(f) * state.zoom * (tw / f.w);
      var dw = img.width * s;
      var dh = img.height * s;
      var c = target.getContext('2d');
      c.fillStyle = '#d9d6cf';
      c.fillRect(0, 0, tw, th);
      c.drawImage(img, (tw - dw) / 2 + state.x * (tw / f.w), (th - dh) / 2 + state.y * (tw / f.w), dw, dh);
    }
    function render() {
      if (!img) return;
      var f = frame();
      canvas.width = f.w;
      canvas.height = f.h;
      draw(canvas, f.w, f.h);
      ctx.strokeStyle = 'rgba(255,255,255,0.7)';
      ctx.setLineDash([4, 4]);
      ctx.beginPath();
      ctx.ellipse(f.w / 2, f.h * 0.42, f.w * 0.3, f.h * 0.3, 0, 0, Math.PI * 2);
      ctx.stroke();
      ctx.setLineDash([]);
    }

    input.addEventListener('change', function () {
      if (skip || !input.files || !input.files[0]) return;
      var reader = new FileReader();
      reader.onload = function () {
        var im = new Image();
        im.onload = function () {
          img = im;
          state = { x: 0, y: 0, zoom: 1 };
          if (zoomEl) zoomEl.value = '1';
          if (done) done.hidden = true;
          panel.hidden = false;
          render();
        };
        im.src = String(reader.result);
      };
      reader.readAsDataURL(input.files[0]);
    });
    if (zoomEl) zoomEl.addEventListener('input', function () { state.zoom = parseFloat(zoomEl.value) || 1; render(); });
    if (ratioEl) ratioEl.addEventListener('change', render);

    var drag = null;
    canvas.addEventListener('pointerdown', function (e) {
      drag = { x: e.clientX, y: e.clientY, sx: state.x, sy: state.y };
      canvas.setPointerCapture(e.pointerId);
    });
    canvas.addEventListener('pointermove', function (e) {
      if (!drag || !img) return;
      var k = canvas.width / canvas.getBoundingClientRect().width;
      state.x = drag.sx + (e.clientX - drag.x) * k;
      state.y = drag.sy + (e.clientY - drag.y) * k;
      render();
    });
    canvas.addEventListener('pointerup', function () { drag = null; });
    canvas.addEventListener('pointercancel', function () { drag = null; });

    if (applyBtn) {
      applyBtn.addEventListener('click', function () {
        if (!img) return;
        var size = OUT[(ratioEl && ratioEl.value) || '35x45'] || OUT['35x45'];
        var out = document.createElement('canvas');
        out.width = size[0];
        out.height = size[1];
        draw(out, size[0], size[1]);
        out.toBlob(function (blob) {
          if (!blob) return;
          var dt = new DataTransfer();
          dt.items.add(new File([blob], 'photo.jpg', { type: 'image/jpeg' }));
          input.files = dt.files;
          skip = true;
          input.dispatchEvent(new Event('change', { bubbles: true }));
          skip = false;
          if (done) done.hidden = false;
        }, 'image/jpeg', 0.92);
      });
    }
  });

  // Aide photomaton : copie du prompt (français ou anglais) avec les variantes cochées.
  document.querySelectorAll('.idi-guide').forEach(function (guide) {
    guide.querySelectorAll('[data-copy-prompt]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var lang = btn.getAttribute('data-copy-prompt');
        var area = guide.querySelector('[data-prompt-lang="' + lang + '"] [data-prompt]');
        if (!area) return;
        var text = String(area.value || '');
        guide.querySelectorAll('input[data-variant-' + lang + ']').forEach(function (cb) {
          if (cb.checked) text += ' ' + cb.getAttribute('data-variant-' + lang);
        });
        var ok = guide.querySelector('[data-copy-ok]');
        var fail = guide.querySelector('[data-copy-fail]');
        function show(success) {
          if (ok) ok.hidden = !success;
          if (fail) fail.hidden = success;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(function () { show(true); }, function () { area.select(); show(false); });
        } else {
          area.select();
          try { show(document.execCommand('copy')); } catch (err) { show(false); }
        }
      });
    });
  });
})();
