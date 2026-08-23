/* Shell téléphone /connect — OSD + iframe collée au trou écran */
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
    } catch (e) {}
  }

  tickClock();
  setInterval(tickClock, 15000);

  var screen = document.querySelector('.connect-device-screen');
  var frame = document.getElementById('connect-device-frame');
  var lastKey = '';

  function syncFrameToScreen() {
    if (!screen || !frame) return;
    var w = Math.round(screen.clientWidth);
    var h = Math.round(screen.clientHeight);
    if (w < 2 || h < 2) return;
    var key = w + 'x' + h;
    if (key === lastKey) return;
    lastKey = key;
    frame.style.width = '100%';
    frame.style.height = '100%';
    notifyMapResize();
  }

  if (frame) {
    frame.addEventListener('load', function () {
      lastKey = '';
      syncFrameToScreen();
      setTimeout(notifyMapResize, 200);
      setTimeout(notifyMapResize, 800);
    });
  }

  if (screen && typeof ResizeObserver !== 'undefined') {
    var observer = new ResizeObserver(function () {
      syncFrameToScreen();
    });
    observer.observe(screen);
  } else {
    window.addEventListener('resize', syncFrameToScreen);
  }

  window.addEventListener('orientationchange', function () {
    lastKey = '';
    setTimeout(syncFrameToScreen, 250);
  });
})();
