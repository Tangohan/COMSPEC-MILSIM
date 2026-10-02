/**
 * Overwatch Beta — réglages du poste lisibles.
 * Regroupe la longue liste de réglages en sections repliables (mémorisées sur ce
 * poste) et ajoute une recherche. Amélioration progressive : sans ce script, le
 * panneau reste une liste complète et fonctionnelle.
 */
(function () {
  'use strict';

  var STORE_KEY = 'athena:overwatch-settings-sections-v1';
  var DEFAULT_OPEN = { situation: true, 'fond-de-carte': true };

  function slug(text) {
    return String(text || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function normalize(text) {
    return String(text || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function loadState() {
    try {
      var raw = JSON.parse(localStorage.getItem(STORE_KEY) || 'null');
      return raw && typeof raw === 'object' ? raw : null;
    } catch (e) {
      return null;
    }
  }

  function saveState(state) {
    try {
      localStorage.setItem(STORE_KEY, JSON.stringify(state));
    } catch (e) { /* navigation privée : on ignore */ }
  }

  function init() {
    var body = document.querySelector('.ow-settings-body');
    if (!body || body.dataset.owSections === '1') return;
    body.dataset.owSections = '1';

    var stored = loadState();
    var state = stored || {};
    var sections = [];
    var current = null;

    // Chaque intertitre de premier niveau ouvre une section ; ses frères suivants y entrent.
    Array.prototype.slice.call(body.children).forEach(function (node) {
      if (node.matches('p.ow-kicker')) {
        var title = node.textContent.trim();
        var key = slug(title);
        var section = document.createElement('section');
        section.className = 'ow-set-sec';
        section.dataset.key = key;

        var head = document.createElement('button');
        head.type = 'button';
        head.className = 'ow-set-head';
        head.id = 'ow-set-head-' + key;
        head.innerHTML = '<span class="ow-set-title"></span><span class="ow-set-count" aria-hidden="true"></span>' +
          '<svg class="ow-set-chev" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
        head.querySelector('.ow-set-title').textContent = title;

        var panel = document.createElement('div');
        panel.className = 'ow-set-body';
        panel.id = 'ow-set-body-' + key;
        panel.setAttribute('role', 'region');
        panel.setAttribute('aria-labelledby', head.id);
        head.setAttribute('aria-controls', panel.id);

        section.appendChild(head);
        section.appendChild(panel);
        body.insertBefore(section, node);
        node.remove();
        current = { key: key, section: section, head: head, panel: panel };
        sections.push(current);
        return;
      }
      if (current && node !== current.section) current.panel.appendChild(node);
    });

    if (!sections.length) return;

    function setOpen(sec, open, persist) {
      sec.section.classList.toggle('is-open', open);
      sec.head.setAttribute('aria-expanded', open ? 'true' : 'false');
      sec.panel.hidden = !open;
      if (persist) {
        state[sec.key] = open;
        saveState(state);
      }
    }

    sections.forEach(function (sec) {
      var controls = sec.panel.querySelectorAll('input, select, button:not(.ow-i)').length;
      var count = sec.head.querySelector('.ow-set-count');
      if (count) count.textContent = controls > 0 ? String(controls) : '';
      var open = Object.prototype.hasOwnProperty.call(state, sec.key) ? !!state[sec.key] : !!DEFAULT_OPEN[sec.key];
      setOpen(sec, open, false);
      sec.head.addEventListener('click', function () {
        if (body.classList.contains('is-filtering')) return;
        setOpen(sec, !sec.section.classList.contains('is-open'), true);
      });
    });

    // ——— Barre d’outils : recherche + tout replier / déplier ———
    var bar = document.createElement('div');
    bar.className = 'ow-set-toolbar';
    bar.innerHTML =
      '<label class="ow-search ow-set-search"><span aria-hidden="true">⌕</span>' +
      '<input type="search" id="ow-settings-filter" placeholder="Rechercher…" autocomplete="off" aria-label="Rechercher un réglage"></label>' +
      '<button type="button" class="ow-set-all" data-ow-set-all aria-label="Tout replier ou déplier">Tout replier</button>';
    body.insertBefore(bar, body.firstChild);

    var empty = document.createElement('p');
    empty.className = 'ow-help ow-set-empty';
    empty.hidden = true;
    body.appendChild(empty);

    var allBtn = bar.querySelector('[data-ow-set-all]');
    function syncAllBtn() {
      var anyOpen = sections.some(function (sec) { return sec.section.classList.contains('is-open'); });
      allBtn.textContent = anyOpen ? 'Tout replier' : 'Tout déplier';
    }
    allBtn.addEventListener('click', function () {
      var anyOpen = sections.some(function (sec) { return sec.section.classList.contains('is-open'); });
      sections.forEach(function (sec) { setOpen(sec, !anyOpen, true); });
      syncAllBtn();
    });
    sections.forEach(function (sec) { sec.head.addEventListener('click', syncAllBtn); });
    syncAllBtn();

    // Une « ligne » est un réglage complet (case, liste, curseur, bouton) avec son libellé.
    var ROW = '.ow-toggle, .ow-row, .ow-opt, .ow-looks > label, fieldset > label, .ow-secondary, .ow-stat';

    function clearMarks(sec) {
      Array.prototype.forEach.call(sec.panel.querySelectorAll('.ow-set-miss'), function (el) {
        el.classList.remove('ow-set-miss');
      });
    }

    // Parcourt le panneau : garde les lignes qui correspondent, et les conteneurs qui en
    // contiennent au moins une ; masque tout le reste (intertitres, aides, inventaires…).
    function filterTree(node, tokens) {
      var kept = 0;
      Array.prototype.forEach.call(node.children, function (child) {
        var ok;
        if (child.matches(ROW)) {
          var hay = normalize(child.textContent + ' ' + (child.getAttribute('title') || '') + ' ' +
            Array.prototype.map.call(child.querySelectorAll('[data-help]'), function (el) {
              return el.getAttribute('data-help');
            }).join(' '));
          ok = tokens.every(function (t) { return hay.indexOf(t) !== -1; });
        } else if (child.querySelector(ROW)) {
          ok = filterTree(child, tokens) > 0;
        } else {
          ok = false;
        }
        child.classList.toggle('ow-set-miss', !ok);
        if (ok) kept += 1;
      });
      return kept;
    }

    var input = bar.querySelector('input');
    input.addEventListener('input', function () {
      var q = normalize(input.value);
      var tokens = q.split(' ').filter(Boolean);
      body.classList.toggle('is-filtering', q !== '');
      var hits = 0;

      sections.forEach(function (sec) {
        clearMarks(sec);
        if (!q) {
          sec.section.hidden = false;
          setOpen(sec, Object.prototype.hasOwnProperty.call(state, sec.key) ? !!state[sec.key] : !!DEFAULT_OPEN[sec.key], false);
          return;
        }
        var titleHit = tokens.every(function (t) { return normalize(sec.head.textContent).indexOf(t) !== -1; });
        var secHits = titleHit ? 1 : filterTree(sec.panel, tokens);
        sec.section.hidden = secHits === 0;
        sec.panel.hidden = sec.section.hidden;
        sec.section.classList.toggle('is-open', !sec.section.hidden);
        sec.head.setAttribute('aria-expanded', sec.section.hidden ? 'false' : 'true');
        hits += sec.section.hidden ? 0 : 1;
      });

      empty.hidden = !q || hits > 0;
      if (q && hits === 0) empty.textContent = 'Aucun réglage ne correspond à « ' + input.value.trim() + ' ».';
      syncAllBtn();
    });
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        input.value = '';
        input.dispatchEvent(new Event('input'));
        event.stopPropagation();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
