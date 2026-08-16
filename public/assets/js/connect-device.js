/* Shell téléphone /connect — OSD + resize carte iframe */
(function () {
  'use strict';

  function pad(n) {
    return n < 10 ? '0' + n : String(n);
  }

  function tickClock() {
    var el = document.getElementById('connect-device-clock');
    if (!el) return;
    var d = new Date();
    el.textContent = pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ' Z';
  }

  function notifyMapResize() {
    var frame = document.getElementById('connect-device-frame');
    if (!frame || !frame.contentWindow) return;
    try {
      frame.contentWindow.dispatchEvent(new Event('resize'));
      frame.contentWindow.postMessage({ type: 'connect-device-resize' }, window.location.origin);
    } catch (e) {
      // cross-origin impossible ici (same-origin)
    }
  }

  function applyCompactClass() {
    var compact = window.matchMedia('(max-width: 560px)').matches;
    document.body.classList.toggle('is-compact', compact);
  }

  tickClock();
  setInterval(tickClock, 15000);
  applyCompactClass();

  window.addEventListener('resize', function () {
    applyCompactClass();
    notifyMapResize();
  });
  window.addEventListener('orientationchange', function () {
    setTimeout(function () {
      applyCompactClass();
      notifyMapResize();
    }, 250);
  });

  var frame = document.getElementById('connect-device-frame');
  if (frame) {
    frame.addEventListener('load', function () {
      setTimeout(notifyMapResize, 200);
      setTimeout(notifyMapResize, 800);
    });
  }
})();
