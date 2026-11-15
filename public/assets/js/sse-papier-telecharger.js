/**
 * Registre des papiers : onglets des faces de l'aperçu (sans JS, toutes les faces se suivent) et impression à l'échelle réelle.
 * Le menu « Télécharger » est un <details> natif ; seul « Imprimer » passe ici.
 */
(function () {
  var preview = document.querySelector('[data-pdl-preview]');
  var menu = document.querySelector('[data-pdl-menu]');

  if (preview) {
    var tabs = Array.prototype.slice.call(preview.querySelectorAll('[data-pdl-tab]'));
    var faces = Array.prototype.slice.call(preview.querySelectorAll('[data-pdl-face]'));
    var bar = preview.querySelector('.pdl-tabs');
    var select = function (key, focus) {
      tabs.forEach(function (t) {
        var on = t.getAttribute('data-pdl-tab') === key;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        if (on && focus) t.focus();
      });
      faces.forEach(function (f) { f.hidden = f.getAttribute('data-pdl-face') !== key; });
    };
    if (tabs.length > 1 && bar) {
      preview.classList.add('pdl-js');
      bar.hidden = false;
      select(tabs[0].getAttribute('data-pdl-tab'), false);
      tabs.forEach(function (t, i) {
        t.addEventListener('click', function () { select(t.getAttribute('data-pdl-tab'), false); });
        t.addEventListener('keydown', function (e) {
          var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : -1;
          if (n < 0) return;
          e.preventDefault();
          n = (n + tabs.length) % tabs.length;
          select(tabs[n].getAttribute('data-pdl-tab'), true);
        });
      });
    }
  }

  document.querySelectorAll('[data-pdl-print]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (menu) menu.removeAttribute('open');
      var imgs = preview ? Array.prototype.slice.call(preview.querySelectorAll('img')) : [];
      var hidden = preview ? Array.prototype.slice.call(preview.querySelectorAll('[data-pdl-face][hidden]')) : [];
      hidden.forEach(function (f) { f.hidden = false; });
      document.body.classList.add('pdl-printing');
      var done = function () {
        document.body.classList.remove('pdl-printing');
        hidden.forEach(function (f) { f.hidden = true; });
        window.removeEventListener('afterprint', done);
      };
      window.addEventListener('afterprint', done);
      Promise.all(imgs.map(function (i) { return i.decode ? i.decode().catch(function () {}) : Promise.resolve(); })).then(function () {
        window.print();
      });
    });
  });

  // Un clic ailleurs ferme le menu.
  document.addEventListener('click', function (e) {
    if (menu && menu.open && !menu.contains(e.target)) menu.removeAttribute('open');
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && menu && menu.open) {
      menu.removeAttribute('open');
      var s = menu.querySelector('summary');
      if (s) s.focus();
    }
  });
})();
