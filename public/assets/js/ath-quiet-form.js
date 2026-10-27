/**
 * Formulaires enregistrés sans recharger la page.
 *
 * <form method="post" data-ath-quiet> placé dans un élément [data-ath-quiet-scope="clé unique"] :
 * l'envoi passe par fetch, la page renvoyée par le serveur (après sa redirection habituelle) est lue,
 * et seul l'élément de même clé est remplacé, puis le message du serveur s'affiche en toast.
 * Sans élément correspondant dans la réponse (ligne filtrée, autre page), ou en cas d'erreur réseau,
 * la page suit la réponse comme avant. Un refus d'accès (403 marqué) laisse la page telle quelle
 * avec le toast d'ath-toast.js.
 * Après remplacement, l'évènement « ath:quiet-swap » est émis sur document (detail.element).
 */
(function () {
  'use strict';
  if (window.athQuietForm) return;
  window.athQuietForm = true;

  function messages(doc) {
    var out = [];
    doc.querySelectorAll('[data-flash-toast]').forEach(function (el) {
      var ps = el.querySelectorAll('p');
      var msg = ps.length ? ps[ps.length - 1].textContent.trim() : '';
      if (!msg) return;
      var c = el.className;
      out.push({ v: /bg-red/.test(c) ? 'error' : /bg-emerald/.test(c) ? 'success' : /bg-amber/.test(c) ? 'warning' : 'info', m: msg });
    });
    doc.querySelectorAll('.ath-main__flashes .ds-alert').forEach(function (el) {
      var ps = el.querySelectorAll('p');
      var msg = ps.length > 1 ? ps[1].textContent.trim() : el.textContent.trim();
      if (!msg) return;
      var m = /ds-alert--(success|warning|info|error)/.exec(el.className);
      out.push({ v: m ? m[1] : 'error', m: msg });
    });
    return out;
  }

  function cssEscape(v) {
    return window.CSS && CSS.escape ? CSS.escape(v) : String(v).replace(/["\\]/g, '\\$&');
  }

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form || !form.matches || !form.matches('form[data-ath-quiet]') || ev.defaultPrevented) return;
    if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post' || typeof window.fetch !== 'function') return;
    var scope = form.closest('[data-ath-quiet-scope]');
    if (!scope) return;
    var key = scope.getAttribute('data-ath-quiet-scope');
    ev.preventDefault();

    var body = new FormData(form);
    if (ev.submitter && ev.submitter.name) body.append(ev.submitter.name, ev.submitter.value || '');
    var buttons = form.querySelectorAll('button, input[type="submit"]');
    buttons.forEach(function (b) { b.disabled = true; });
    form.setAttribute('aria-busy', 'true');
    var done = function () {
      buttons.forEach(function (b) { b.disabled = false; });
      form.removeAttribute('aria-busy');
    };

    fetch(form.action, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'Accept': 'text/html' }
    }).then(function (res) {
      if (res.status === 403 && res.headers.get('X-Ath-Access-Denied') === '1') {
        done();
        return null;
      }
      return res.text().then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var fresh = doc.querySelector('[data-ath-quiet-scope="' + cssEscape(key) + '"]');
        if (!res.ok || !fresh || !scope.isConnected) {
          window.location.href = res.url || window.location.href;
          return null;
        }
        var dialog = form.closest('dialog');
        if (dialog && dialog.open) dialog.close();
        var imported = document.importNode(fresh, true);
        scope.replaceWith(imported);
        if (typeof window.athToast === 'function') {
          messages(doc).forEach(function (t) { window.athToast(t.v, t.m); });
        }
        document.dispatchEvent(new CustomEvent('ath:quiet-swap', { detail: { element: imported } }));
        return null;
      });
    }).catch(function () {
      done();
      form.removeAttribute('data-ath-quiet');
      form.submit();
    });
  });
})();
