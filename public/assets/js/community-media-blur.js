/**
 * Éditeur de zones de flou (pourcentages) + détection visage navigateur si disponible.
 */
(function () {
  var img = document.getElementById('bo-media-preview-img');
  var canvas = document.getElementById('bo-media-blur-canvas');
  var stage = document.getElementById('bo-media-blur-stage');
  var input = document.getElementById('bo-media-regions');
  var blurMode = document.getElementById('bo-media-blur-mode');
  var clearBtn = document.getElementById('bo-media-clear-blur');
  var detectBtn = document.getElementById('bo-media-detect-faces');
  var hint = document.getElementById('bo-media-face-hint');
  if (!img || !canvas || !input || !stage) return;

  var regions = [];
  try {
    regions = JSON.parse(input.value || '[]') || [];
  } catch (e) {
    regions = [];
  }
  if (!Array.isArray(regions)) regions = [];

  var drawing = false;
  var startX = 0;
  var startY = 0;

  function syncInput() {
    input.value = JSON.stringify(regions);
  }

  function pctFromEvent(ev) {
    var rect = canvas.getBoundingClientRect();
    var x = ((ev.clientX - rect.left) / rect.width) * 100;
    var y = ((ev.clientY - rect.top) / rect.height) * 100;
    return {
      x: Math.max(0, Math.min(100, x)),
      y: Math.max(0, Math.min(100, y)),
    };
  }

  function resizeCanvas() {
    var w = img.clientWidth;
    var h = img.clientHeight;
    if (!w || !h) return;
    canvas.width = w;
    canvas.height = h;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    draw();
  }

  function draw(temp) {
    var ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    var list = regions.slice();
    if (temp) list.push(temp);
    list.forEach(function (r) {
      var x = (r.x / 100) * canvas.width;
      var y = (r.y / 100) * canvas.height;
      var w = (r.w / 100) * canvas.width;
      var h = (r.h / 100) * canvas.height;
      ctx.fillStyle = 'rgba(16, 185, 129, 0.28)';
      ctx.strokeStyle = 'rgba(16, 185, 129, 0.95)';
      ctx.lineWidth = 2;
      ctx.fillRect(x, y, w, h);
      ctx.strokeRect(x, y, w, h);
    });
  }

  function hitIndex(p) {
    for (var i = regions.length - 1; i >= 0; i--) {
      var r = regions[i];
      if (p.x >= r.x && p.x <= r.x + r.w && p.y >= r.y && p.y <= r.y + r.h) {
        return i;
      }
    }
    return -1;
  }

  canvas.addEventListener('mousedown', function (ev) {
    drawing = true;
    var p = pctFromEvent(ev);
    startX = p.x;
    startY = p.y;
  });
  canvas.addEventListener('mousemove', function (ev) {
    if (!drawing) return;
    var p = pctFromEvent(ev);
    draw({
      x: Math.min(startX, p.x),
      y: Math.min(startY, p.y),
      w: Math.abs(p.x - startX),
      h: Math.abs(p.y - startY),
    });
  });
  function endDraw(ev) {
    if (!drawing) return;
    drawing = false;
    var p = pctFromEvent(ev);
    var r = {
      x: Math.min(startX, p.x),
      y: Math.min(startY, p.y),
      w: Math.abs(p.x - startX),
      h: Math.abs(p.y - startY),
    };
    if (r.w >= 2 && r.h >= 2) {
      regions.push(r);
      if (blurMode && blurMode.value === 'none') {
        blurMode.value = 'manual';
      }
      syncInput();
    }
    draw();
  }
  canvas.addEventListener('mouseup', endDraw);
  canvas.addEventListener('mouseleave', function (ev) {
    if (drawing) endDraw(ev);
  });
  canvas.addEventListener('dblclick', function (ev) {
    var idx = hitIndex(pctFromEvent(ev));
    if (idx >= 0) {
      regions.splice(idx, 1);
      syncInput();
      draw();
    }
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      regions = [];
      syncInput();
      draw();
    });
  }

  if (detectBtn) {
    detectBtn.addEventListener('click', async function () {
      if (!('FaceDetector' in window)) {
        if (hint) {
          hint.textContent = 'Votre navigateur ne propose pas la détection de visage. Dessinez les zones manuellement, ou une zone indicative sera proposée à l’enregistrement.';
        }
        regions = [{ x: 35, y: 12, w: 30, h: 28 }];
        if (blurMode) blurMode.value = 'auto_face';
        syncInput();
        draw();
        return;
      }
      try {
        var detector = new window.FaceDetector({ fastMode: true, maxDetectedFaces: 8 });
        var faces = await detector.detect(img);
        if (!faces || !faces.length) {
          if (hint) hint.textContent = 'Aucun visage détecté. Ajoutez des zones manuellement.';
          return;
        }
        var nw = img.naturalWidth || img.clientWidth;
        var nh = img.naturalHeight || img.clientHeight;
        regions = faces.map(function (f) {
          var b = f.boundingBox;
          return {
            x: (b.x / nw) * 100,
            y: (b.y / nh) * 100,
            w: (b.width / nw) * 100,
            h: (b.height / nh) * 100,
          };
        });
        if (blurMode) blurMode.value = 'auto_face';
        syncInput();
        draw();
        if (hint) hint.textContent = faces.length + ' visage(s) détecté(s). Ajustez les zones si besoin.';
      } catch (err) {
        if (hint) hint.textContent = 'Détection indisponible pour le moment. Utilisez le floutage manuel.';
      }
    });
  }

  if (img.complete) resizeCanvas();
  else img.addEventListener('load', resizeCanvas);
  window.addEventListener('resize', resizeCanvas);
})();
