/**
 * TOC Athena — Personnes identifiées (SSE).
 */
(function () {
  'use strict';

  var POLL_MS = 8000;
  var lastIds = {};
  var timer = null;
  var seeded = false;

  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : '';
  }

  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function resolveMediaUrl(u) {
    if (!u) return '';
    u = String(u);
    if (u.indexOf('http') === 0 || u.indexOf('//') === 0) return u;
    var base = getApiBase();
    var origin = String(base || '').replace(/\/$/, '').replace(/\/api(?:\/atak)?$/, '');
    return origin + (u.charAt(0) === '/' ? u : '/' + u);
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function emptyHtml() {
    return (
      '<div class="atak-empty-state">' +
      '<div class="atak-empty-state-icon" aria-hidden="true">◎</div>' +
      '<p class="atak-empty-state-title">Aucune personne identifiée</p>' +
      '<p class="atak-empty-state-text">Les fiches créées depuis le terminal de renseignement interpersonnel apparaîtront ici.</p>' +
      '</div>'
    );
  }

  function weaponsLine(person) {
    var w = Array.isArray(person.weapons) ? person.weapons : [];
    if (!w.length) return '';
    return w
      .map(function (x) {
        return esc((x && x.name) || '');
      })
      .filter(Boolean)
      .join(', ');
  }

  function renderPerson(p) {
    var photo = p.primary_photo || null;
    var src = photo ? resolveMediaUrl(photo.url || photo.image_path || '') : '';
    var img = src
      ? '<img class="atak-cam-thumb" src="' + esc(src) + '" alt="Photo du visage" loading="lazy" />'
      : '<div class="atak-cam-thumb atak-cam-thumb--empty" aria-hidden="true">◎</div>';
    var arms = weaponsLine(p);
    var meta = [];
    if (p.status_label) meta.push(esc(p.status_label));
    if (p.circumstances_label && p.circumstances_label !== '—') meta.push(esc(p.circumstances_label));
    if (p.grid_reference) meta.push('Grille ' + esc(p.grid_reference));
    if (p.submitter_callsign) meta.push('par ' + esc(p.submitter_callsign));

    return (
      '<article class="atak-cam-card" data-sse-id="' + esc(p.id) + '">' +
      '<div class="atak-cam-card-media">' + img + '</div>' +
      '<div class="atak-cam-card-body">' +
      '<h4 class="atak-cam-card-title">' + esc(p.display_name || 'Personne') + '</h4>' +
      '<p class="atak-cam-card-meta">' + meta.join(' · ') + '</p>' +
      (arms ? '<p class="atak-cam-card-meta">Armement : ' + arms + '</p>' : '') +
      (p.biometrics_simulated ? '<p class="atak-cam-card-meta">Biométrie simulée enregistrée</p>' : '') +
      '</div></article>'
    );
  }

  function updateBadge(n) {
    var badge = document.getElementById('atak-sse-tab-badge');
    if (!badge) return;
    if (n > 0) {
      badge.hidden = false;
      badge.textContent = String(n > 99 ? '99+' : n);
    } else {
      badge.hidden = true;
      badge.textContent = '';
    }
  }

  function paint(persons) {
    var root = document.getElementById('atak-sse-persons-list');
    if (!root) return;
    if (!persons || !persons.length) {
      root.innerHTML = emptyHtml();
      updateBadge(0);
      return;
    }
    root.innerHTML = '<div class="atak-cams-grid">' + persons.map(renderPerson).join('') + '</div>';
    updateBadge(persons.length);
    persons.forEach(function (p) {
      if (!p || !p.id) return;
      if (seeded && !lastIds[p.id] && window.ATAKShowNotification) {
        window.ATAKShowNotification('Personne identifiée — ' + (p.display_name || ''));
      }
      lastIds[p.id] = true;
    });
    seeded = true;
  }

  function statusFilter() {
    var el = document.getElementById('atak-sse-status-filter');
    return el ? String(el.value || '') : '';
  }

  function refresh() {
    var base = getApiBase();
    if (!base) return Promise.resolve();
    var qs = 'mapId=' + encodeURIComponent(getMapId()) + '&limit=80';
    var st = statusFilter();
    if (st) qs += '&status=' + encodeURIComponent(st);
    var url = String(base).replace(/\/$/, '') + '/api/sse/persons?' + qs;

    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        if (!r.ok) throw new Error('http_' + r.status);
        return r.json();
      })
      .then(function (data) {
        paint((data && data.persons) || []);
      })
      .catch(function () {});
  }

  function start() {
    if (timer) return;
    refresh();
    timer = window.setInterval(refresh, POLL_MS);
  }

  function bind() {
    var filter = document.getElementById('atak-sse-status-filter');
    if (filter && !filter._sseBound) {
      filter._sseBound = true;
      filter.addEventListener('change', refresh);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      bind();
      start();
    });
  } else {
    bind();
    start();
  }

  window.ATAKSsePersons = { refresh: refresh };
})();
