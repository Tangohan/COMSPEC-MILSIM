(function () {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-dbot-copy]') : null;
    if (!btn) return;
    var field = document.getElementById(btn.getAttribute('data-dbot-copy'));
    if (!field) return;
    var done = function () {
      var old = btn.textContent;
      btn.textContent = btn.getAttribute('data-copied') || old;
      setTimeout(function () { btn.textContent = old; }, 1800);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(field.value).then(done, function () { field.select(); });
    } else {
      field.select();
      try { document.execCommand('copy'); done(); } catch (err) {}
    }
  });
})();
