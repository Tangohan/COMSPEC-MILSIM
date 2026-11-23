(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-ident-copy]');
    if (!btn) { return; }
    var value = btn.getAttribute('data-ident-copy') || '';
    var done = function () {
      var old = btn.getAttribute('data-label') || btn.textContent;
      btn.setAttribute('data-label', old);
      btn.textContent = '\u2713';
      setTimeout(function () { btn.textContent = old; }, 1400);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(value).then(done, function () {});
    }
  });
})();
