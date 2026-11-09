(function () {
  'use strict';
  var btn = document.querySelector('[data-pa-copy-report]');
  var area = document.getElementById('pa-diag-report');
  if (!btn || !area) { return; }
  var label = btn.textContent;
  function done() {
    btn.textContent = btn.getAttribute('data-done') || 'Copié';
    setTimeout(function () { btn.textContent = label; }, 2000);
  }
  btn.addEventListener('click', function () {
    var text = area.value;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, function () { area.select(); });
      return;
    }
    area.select();
    try { if (document.execCommand('copy')) { done(); } } catch (e) { /* sélection manuelle */ }
  });
})();
