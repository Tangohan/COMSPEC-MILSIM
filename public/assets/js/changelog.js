/* Journal produit Athena — Update Operations Log */
(function () {
  'use strict';

  var root = document.querySelector('[data-cl-ops]');
  if (!root) return;

  var dataEl = document.getElementById('cl-ops-data');
  var updates = [];
  try {
    updates = dataEl ? JSON.parse(dataEl.textContent || '[]') : [];
  } catch (e) {
    updates = [];
  }

  var listPanel = root.querySelector('[data-cl-ops-panel="list"]');
  var detailPanel = root.querySelector('[data-cl-ops-panel="detail"]');
  var modulesPanel = root.querySelector('[data-cl-ops-panel="modules"]');
  var releasesPanel = root.querySelector('[data-cl-ops-panel="releases"]');
  var roadmapPanel = root.querySelector('[data-cl-ops-panel="roadmap"]');
  var rows = root.querySelectorAll('[data-cl-ops-row]');
  var search = root.querySelector('[data-cl-ops-search]');
  var moduleSelect = root.querySelector('[data-cl-ops-module]');
  var kindSelect = root.querySelector('[data-cl-ops-kind]');
  var result = root.querySelector('[data-cl-ops-result]');
  var empty = root.querySelector('[data-cl-ops-empty]');
  var tabs = root.querySelectorAll('[data-cl-ops-tab]');
  var navLinks = root.querySelectorAll('[data-cl-ops-nav]');
  var tabKind = 'all';

  function norm(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  function setPanel(name) {
    [listPanel, detailPanel, modulesPanel, releasesPanel, roadmapPanel].forEach(function (p) {
      if (!p) return;
      var on = p.getAttribute('data-cl-ops-panel') === name;
      p.hidden = !on;
      p.classList.toggle('is-active', on);
    });
  }

  function activateNav(name) {
    navLinks.forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-cl-ops-nav') === name);
    });
  }

  function applyFilters() {
    var q = norm(search && search.value);
    var mod = moduleSelect ? moduleSelect.value : 'all';
    var kind = kindSelect ? kindSelect.value : 'all';
    if (tabKind !== 'all') kind = tabKind;
    var shown = 0;
    rows.forEach(function (row) {
      var hay = norm(row.getAttribute('data-search') || '');
      var rowMod = row.getAttribute('data-module') || '';
      var rowKind = row.getAttribute('data-kind') || '';
      var okQ = !q || hay.indexOf(q) !== -1;
      var okMod = mod === 'all' || rowMod === mod;
      var okKind = kind === 'all' || rowKind === kind;
      var on = okQ && okMod && okKind;
      row.classList.toggle('is-hidden', !on);
      if (on) shown += 1;
    });
    if (result) {
      result.textContent = shown + ' résultat' + (shown > 1 ? 's' : '');
    }
    if (empty) empty.hidden = shown !== 0;
  }

  function showList() {
    setPanel('list');
    activateNav(tabKind === 'spotrep' || tabKind === 'techrep' ? tabKind : 'updates');
    if (history.replaceState) {
      history.replaceState(null, '', location.pathname + location.search);
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function verbClass(v) {
    var x = String(v || '').toUpperCase();
    if (x === 'ADDED') return 'added';
    if (x === 'FIXED') return 'fixed';
    if (x === 'SECURED') return 'secured';
    return 'changed';
  }

  function openReport(id) {
    var u = null;
    for (var i = 0; i < updates.length; i++) {
      if (String(updates[i].id) === String(id)) {
        u = updates[i];
        break;
      }
    }
    if (!u) return;
    setPanel('detail');
    activateNav(u.kind === 'spotrep' || u.kind === 'techrep' ? u.kind : 'updates');

    var kindLabel = u.kind_label || 'UPDATE';
    var setText = function (sel, text) {
      var el = root.querySelector(sel);
      if (el) el.textContent = text || '';
    };
    setText('[data-cl-ops-kind-label]', kindLabel);
    setText('[data-cl-ops-r-kind]', kindLabel);
    setText('[data-cl-ops-r-id]', kindLabel + ' #' + u.id + ' // ' + (u.date || ''));
    setText('[data-cl-ops-r-title]', u.title);
    setText('[data-cl-ops-r-lead]', u.lead || u.summary);
    setText('[data-cl-ops-r-from]', u.from);
    setText('[data-cl-ops-r-to]', u.to);
    setText('[data-cl-ops-r-version]', u.version);
    setText('[data-cl-ops-r-status]', (u.status || 'DEPLOYED') + ' // ' + (u.channel || 'PROD'));
    setText('[data-cl-ops-r-situation]', u.situation);
    setText('[data-cl-ops-r-impact]', u.impact || u.summary);
    setText('[data-cl-ops-r-action]', u.action);
    setText('[data-cl-ops-r-author]', u.author);
    setText('[data-cl-ops-r-footer]', 'PAGE 1 / 1 — #' + u.id);
    var av = root.querySelector('[data-cl-ops-r-avatar]');
    if (av) av.textContent = String(u.author || 'AO').slice(0, 2).toUpperCase();

    var full = root.querySelector('[data-cl-ops-full]');
    if (full) full.setAttribute('href', u.href || '#');

    var changes = root.querySelector('[data-cl-ops-r-changes]');
    if (changes) {
      var items = Array.isArray(u.changes) ? u.changes : [];
      changes.innerHTML = items.map(function (c) {
        var verb = c[0] || 'CHANGED';
        var text = c[1] || '';
        return '<li><span class="cl-ops-verb cl-ops-verb--' + verbClass(verb) + '">' + verb +
          '</span><span>' + text.replace(/</g, '&lt;') + '</span></li>';
      }).join('');
    }
    var tags = root.querySelector('[data-cl-ops-r-tags]');
    if (tags) {
      var list = Array.isArray(u.tags) ? u.tags : [];
      tags.innerHTML = list.map(function (t) {
        return '<span class="cl-ops-pill">' + String(t).replace(/</g, '&lt;') + '</span>';
      }).join('');
    }

    if (history.replaceState) {
      history.replaceState(null, '', '#update-' + u.id);
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  rows.forEach(function (row) {
    function go() {
      openReport(row.getAttribute('data-id') || '');
    }
    row.addEventListener('click', go);
    row.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        go();
      }
    });
  });

  var back = root.querySelector('[data-cl-ops-back]');
  if (back) back.addEventListener('click', showList);

  if (search) search.addEventListener('input', applyFilters);
  if (moduleSelect) moduleSelect.addEventListener('change', applyFilters);
  if (kindSelect) {
    kindSelect.addEventListener('change', function () {
      tabKind = kindSelect.value || 'all';
      tabs.forEach(function (t) {
        t.classList.toggle('is-active', t.getAttribute('data-cl-ops-tab') === tabKind);
      });
      applyFilters();
    });
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      tabKind = tab.getAttribute('data-cl-ops-tab') || 'all';
      tabs.forEach(function (t) {
        t.classList.toggle('is-active', t === tab);
      });
      if (kindSelect) kindSelect.value = tabKind;
      setPanel('list');
      activateNav(tabKind === 'spotrep' || tabKind === 'techrep' ? tabKind : 'updates');
      applyFilters();
    });
  });

  navLinks.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var nav = btn.getAttribute('data-cl-ops-nav') || 'updates';
      activateNav(nav);
      if (nav === 'modules') {
        setPanel('modules');
        return;
      }
      if (nav === 'releases') {
        setPanel('releases');
        return;
      }
      if (nav === 'roadmap') {
        setPanel('roadmap');
        return;
      }
      if (nav === 'spotrep' || nav === 'techrep') {
        tabKind = nav;
        tabs.forEach(function (t) {
          t.classList.toggle('is-active', t.getAttribute('data-cl-ops-tab') === nav);
        });
        if (kindSelect) kindSelect.value = nav;
      } else {
        tabKind = 'all';
        tabs.forEach(function (t) {
          t.classList.toggle('is-active', t.getAttribute('data-cl-ops-tab') === 'all');
        });
        if (kindSelect) kindSelect.value = 'all';
      }
      setPanel('list');
      applyFilters();
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && detailPanel && !detailPanel.hidden) {
      showList();
    }
  });

  applyFilters();
  if (location.hash.indexOf('#update-') === 0) {
    openReport(location.hash.replace('#update-', ''));
  }
})();
