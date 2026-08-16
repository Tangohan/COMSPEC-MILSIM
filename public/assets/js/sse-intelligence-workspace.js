/**
 * Intelligence Workspace — onglets, timeline filter, recherche, contexte.
 */
(function () {
  'use strict';
  var root = document.querySelector('[data-sse-intel-workspace]');
  if (!root) return;

  var tabs = root.querySelectorAll('[data-iw-tab]');
  var panels = root.querySelectorAll('[data-iw-panel]');

  function showTab(name) {
    tabs.forEach(function (t) {
      t.classList.toggle('is-active', t.getAttribute('data-iw-tab') === name);
    });
    panels.forEach(function (p) {
      var id = p.getAttribute('data-iw-panel');
      if (name === 'folder' && id === 'folder') {
        p.hidden = false;
        return;
      }
      if (name === 'inbox') {
        p.hidden = id !== 'timeline';
        return;
      }
      p.hidden = id !== name;
    });
    if (name === 'graph' || name === 'timeline' || name === 'search' || name === 'folder' || name === 'cycle' || name === 'analyse') {
      try { history.replaceState(null, '', '#' + name); } catch (e) { /* ignore */ }
    }
  }

  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      showTab(t.getAttribute('data-iw-tab') || 'timeline');
    });
  });

  var hash = (location.hash || '').replace('#', '');
  if (hash === 'graph' || hash === 'timeline' || hash === 'search' || hash === 'folder' || hash === 'cycle' || hash === 'analyse') {
    showTab(hash);
  } else if (root.getAttribute('data-mode') === 'case') {
    showTab('folder');
  } else {
    showTab('timeline');
  }

  var tabLinks = root.querySelectorAll('[data-iw-tab-link]');
  tabLinks.forEach(function (a) {
    a.addEventListener('click', function (e) {
      var name = a.getAttribute('data-iw-tab-link');
      if (!name) return;
      e.preventDefault();
      showTab(name);
    });
  });

  var until = document.querySelector('[data-iw-tl-until]');
  var timeline = document.querySelector('[data-iw-timeline]');
  if (until && timeline) {
    until.addEventListener('change', function () {
      var v = until.value;
      if (!v) {
        timeline.querySelectorAll('li').forEach(function (li) { li.hidden = false; });
        return;
      }
      var cut = new Date(v).getTime();
      timeline.querySelectorAll('li').forEach(function (li) {
        var t = li.getAttribute('data-event-time') || '';
        var ts = Date.parse(t.replace(' ', 'T') + 'Z');
        if (isNaN(ts)) ts = Date.parse(t);
        li.hidden = !isNaN(cut) && !isNaN(ts) && ts > cut;
      });
    });
  }

  var form = root.querySelector('[data-iw-univ-search]');
  var box = root.querySelector('[data-iw-univ-results]');
  if (form && box && window.SSE_IW && window.SSE_IW.searchUrl) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var q = (form.querySelector('input[name=q]') || {}).value || '';
      q = String(q).trim();
      if (q.length < 2) return;
      box.innerHTML = '<p class="iw-intel-empty">Recherche…</p>';
      fetch(window.SSE_IW.searchUrl + '?q=' + encodeURIComponent(q), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function (data) {
          var groups = data.groups || {};
          var html = '';
          Object.keys(groups).forEach(function (g) {
            var list = groups[g] || [];
            if (!list.length) return;
            html += '<div class="sse-search-group"><h3>' + g + '</h3><ul>';
            list.forEach(function (row) {
              html += '<li><a href="' + (row.href || '#') + '"><strong>' + (row.label || '') + '</strong>'
                + '<span class="record-id">' + (row.ref || '') + '</span>'
                + (row.hint ? '<em>' + row.hint + '</em>' : '')
                + '</a></li>';
            });
            html += '</ul></div>';
          });
          box.innerHTML = html || '<p class="iw-intel-empty">Aucun résultat.</p>';
        })
        .catch(function () {
          box.innerHTML = '<p class="iw-intel-empty">Recherche indisponible.</p>';
        });
    });
  }

  var ctx = root.querySelector('[data-iw-context]');
  document.addEventListener('sse-iw-select', function (ev) {
    var node = ev.detail || {};
    if (!ctx) return;
    var title = node.label || node.display_label || 'Élément';
    var conf = node.confidence_code || '';
    var type = node.entity_type || '';
    var href = '';
    if (node.source_table === 'sse_persons' && node.source_id) {
      href = '/atak/sse/identites/' + node.source_id;
    } else if (node.source_table === 'sse_cases' && node.source_id) {
      href = '/atak/sse/workspace?case=' + node.source_id;
    } else if (node.source_table === 'sse_sites' && node.source_id) {
      href = '/atak/sse/sites/' + node.source_id;
    }
    ctx.innerHTML = '<div class="iw-intel-context">'
      + '<span class="iw-intel-kicker">' + type + '</span>'
      + '<h3>' + title + '</h3>'
      + (conf ? '<p class="iw-intel-conf">Confiance ' + conf + '</p>' : '')
      + (href ? '<a class="iw-btn" href="' + href + '">Ouvrir</a>' : '')
      + '</div>';
    showTab('graph');
  });
})();
