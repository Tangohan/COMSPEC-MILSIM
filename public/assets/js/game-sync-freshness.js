/*
 * COMSPEC Athena — bandeau discret « Données du jeu il y a Xs » (pages 9-LINE, TOC web, outils opérateur).
 * Hôte : <div class="gsync" data-game-sync data-base="https://…" hidden></div> (CSS game-sync-freshness.css).
 * Lit GET /synchro-jeu toutes les 15 s (jamais en cache), relit tout de suite au retour sur l'onglet ou à la reprise du
 * réseau, et fait avancer les âges entre deux lectures. Le détail par flux (positions, aéronefs, marqueurs, messages,
 * 9-LINE, ordres, comptes rendus) s'ouvre d'un clic. Compte non relié à ATAK (401, 403) : le bandeau reste caché.
 * Textes en français : traduits à l'affichage par i18n_dom.js (lang/en/site_auto.php).
 */
(function () {
  'use strict';

  var POLL_MS = 15000;
  var hosts = Array.prototype.slice.call(document.querySelectorAll('[data-game-sync]'));
  if (!hosts.length) return;
  var base = String(hosts[0].getAttribute('data-base') || '').replace(/\/$/, '');
  var data = null;
  var fetchedAt = 0;
  var stopped = false;
  var failed = false;

  function fmt(tpl) {
    var args = arguments;
    return String(tpl).replace(/%(\d)/g, function (_m, d) { return String(args[Number(d)]); });
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function ago(sec) {
    if (sec === null || sec === undefined) return 'aucune donnée';
    sec = Math.max(0, Math.round(sec));
    if (sec < 60) return fmt('il y a %1 s', sec);
    if (sec < 3600) return fmt('il y a %1 min', Math.floor(sec / 60));
    return fmt('il y a %1 h', Math.floor(sec / 3600));
  }
  function stateOf(age) {
    if (age === null || age === undefined) return 'none';
    if (age <= 60) return 'live';
    return age <= 900 ? 'late' : 'stale';
  }
  function now(age) {
    return age === null || age === undefined ? null : age + (Date.now() - fetchedAt) / 1000;
  }

  function render() {
    if (!data) return;
    var latest = now(data.latest_age_s);
    var state = stateOf(latest);
    var head = latest === null ? 'Aucune donnée reçue du jeu' : fmt('Données du jeu %1', ago(latest));
    if (failed) head += ' · ' + 'lecture impossible, nouvel essai';
    var rows = (data.flows || []).map(function (f) {
      var a = now(f.age_s);
      return '<li data-state="' + stateOf(a) + '"><span>' + esc(f.label) + '</span><span>' + esc(ago(a)) + '</span></li>';
    }).join('');
    hosts.forEach(function (host) {
      var open = host.querySelector('details') && host.querySelector('details').open;
      host.setAttribute('data-state', state);
      host.innerHTML = '<details' + (open ? ' open' : '') + '><summary><span class="gsync-dot" aria-hidden="true"></span>'
        + '<span role="status">' + esc(head) + '</span></summary><div class="gsync-pop"><ul>' + rows + '</ul>'
        + '<p class="gsync-note">' + esc('Plus d’une minute : donnée en retard (envoi différé, liaison satellite ou relais, joueur sans téléphone).') + '</p></div></details>';
      host.hidden = false;
    });
  }

  function poll() {
    if (stopped || document.hidden) return;
    fetch(base + '/synchro-jeu', { credentials: 'include', cache: 'no-store', headers: { Accept: 'application/json' } })
      .then(function (r) {
        if (r.status === 401 || r.status === 403 || r.redirected) { stopped = true; throw new Error('off'); }
        return r.json();
      })
      .then(function (j) {
        if (!j || j.ok === false) throw new Error('bad');
        data = j;
        fetchedAt = Date.now();
        failed = false;
        render();
      })
      .catch(function () {
        if (stopped) { hosts.forEach(function (h) { h.hidden = true; }); return; }
        failed = true;
        render();
      });
  }

  poll();
  setInterval(poll, POLL_MS);
  setInterval(function () { if (!document.hidden) render(); }, 5000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
  window.addEventListener('online', poll);
})();
