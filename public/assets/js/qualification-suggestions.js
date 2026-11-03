/*
 * Qualifications et brevets suggérés : info-barre (masquable pour la session) et fenêtre de la liste complète.
 * Les actions rapides sont les formulaires classiques de la liste : ils sont envoyés sans quitter la fenêtre, puis
 * la liste et les compteurs sont remplacés par ceux de la fiche renvoyée. Sans JavaScript, le lien ouvre la liste
 * par son ancre et les formulaires reviennent à la fiche.
 */
(function () {
  'use strict';
  if (window.__qsgReady) return;
  window.__qsgReady = true;

  function init() {
    var bar = document.querySelector('[data-qsg-bar]');
    var dialog = document.querySelector('[data-qsg-dialog]');
    if (!bar || !dialog || typeof dialog.showModal !== 'function') return;
    var hideKey = 'qsg.hide.' + bar.getAttribute('data-qsg-member') + '.' + bar.getAttribute('data-qsg-sig');
    var filter = 'all';
    var opener = null;

    try { if (sessionStorage.getItem(hideKey) === '1') bar.hidden = true; } catch (e) {}
    var hideBtn = bar.querySelector('[data-qsg-hide]');
    if (hideBtn) {
      hideBtn.hidden = false;
      hideBtn.addEventListener('click', function () {
        bar.hidden = true;
        try { sessionStorage.setItem(hideKey, '1'); } catch (e) {}
      });
    }

    function body() { return dialog.querySelector('[data-qsg-body]'); }

    function applyFilter() {
      var b = body();
      var shown = 0;
      b.querySelectorAll('[data-qsg-item]').forEach(function (li) {
        var on = filter === 'all' || li.getAttribute('data-qsg-group') === filter;
        li.hidden = !on;
        if (on) shown++;
      });
      b.querySelectorAll('[data-qsg-filter]').forEach(function (btn) {
        var on = btn.getAttribute('data-qsg-filter') === filter;
        btn.classList.toggle('is-on', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      var none = b.querySelector('[data-qsg-none]');
      if (none) none.hidden = shown > 0 || b.querySelectorAll('[data-qsg-item]').length === 0;
      var f = b.querySelector('[data-qsg-filters]');
      if (f) f.hidden = false;
    }

    function open(ev) {
      if (ev) ev.preventDefault();
      opener = document.activeElement;
      if (!dialog.open) dialog.showModal();
      applyFilter();
    }

    document.addEventListener('click', function (ev) {
      var t = ev.target.closest ? ev.target.closest('[data-qsg-open], [data-qsg-close], [data-qsg-filter]') : null;
      if (!t) return;
      if (t.hasAttribute('data-qsg-open')) open(ev);
      else if (t.hasAttribute('data-qsg-close')) { ev.preventDefault(); dialog.close(); }
      else { filter = t.getAttribute('data-qsg-filter'); applyFilter(); }
    });
    dialog.addEventListener('click', function (ev) { if (ev.target === dialog) dialog.close(); });
    dialog.addEventListener('close', function () {
      if (opener && typeof opener.focus === 'function' && document.contains(opener)) opener.focus();
    });

    function messages(doc) {
      var out = [];
      doc.querySelectorAll('[data-flash-toast], .ath-main__flashes .ds-alert').forEach(function (el) {
        var ps = el.querySelectorAll('p');
        var m = (ps.length ? ps[ps.length - 1].textContent : el.textContent).trim();
        if (!m) return;
        var c = el.className;
        out.push({ v: /bg-red|--error/.test(c) ? 'error' : /bg-amber|--warning/.test(c) ? 'warning' : 'success', m: m });
      });
      return out;
    }

    function say(v, m) {
      if (typeof window.athToast === 'function') { window.athToast(v, m); return; }
      var s = body().querySelector('.qsg-live');
      if (!s) { s = document.createElement('p'); s.className = 'qsg-live'; s.setAttribute('role', 'status'); body().prepend(s); }
      s.textContent = m;
    }

    function refresh(doc) {
      var nb = doc.querySelector('[data-qsg-body]');
      if (!nb) return false;
      body().innerHTML = nb.innerHTML;
      var nbar = doc.querySelector('[data-qsg-bar]');
      document.querySelectorAll('[data-qsg-count]').forEach(function (el) {
        if (body().contains(el)) return;
        var k = el.getAttribute('data-qsg-count');
        var n = nbar ? (doc.querySelector('[data-qsg-count="' + k + '"]') || null) : null;
        el.textContent = n ? n.textContent : '0';
      });
      if (!nbar || nbar.hidden) bar.hidden = true;
      applyFilter();
      return true;
    }

    function reload() {
      return fetch(window.location.pathname + window.location.search, { credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (html) { refresh(new DOMParser().parseFromString(html, 'text/html')); });
    }

    dialog.addEventListener('submit', function (ev) {
      var form = ev.target;
      if (!form.matches || !form.matches('form[data-qsg-action]') || !window.fetch) return;
      ev.preventDefault();
      var btn = form.querySelector('[type="submit"]');
      if (btn) btn.disabled = true;
      var li = form.closest('[data-qsg-item]');
      if (li) li.setAttribute('aria-busy', 'true');
      fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json, text/html;q=0.9' } })
        .then(function (r) {
          var type = r.headers.get('Content-Type') || '';
          if (type.indexOf('json') !== -1) {
            return r.json().then(function (data) {
              say(data && data.ok ? 'success' : 'error', (data && data.message) || 'Action non effectuée : réessayez.');
              return reload();
            });
          }
          return r.text().then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            messages(doc).forEach(function (t) { say(t.v, t.m); });
            if (!refresh(doc)) return reload();
          });
        })
        .catch(function () {
          say('error', 'Action non effectuée : réessayez.');
          if (btn) btn.disabled = false;
          if (li) li.removeAttribute('aria-busy');
        })
        .then(function () {
          var h = dialog.querySelector('#qsg-md-t');
          if (h && dialog.open) { h.setAttribute('tabindex', '-1'); h.focus(); }
        });
    });

    if (window.location.hash === '#qsg-md') open(null);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
