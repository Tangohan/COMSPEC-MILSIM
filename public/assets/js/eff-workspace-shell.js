/**
 * Effectifs : menu « Plus » des onglets, défilement de la barre, fenêtre de recherche (Ctrl+K, « / »).
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-effectifs-workspace]');
  if (!root) return;

  /* ——— Menu « Plus » ——— */
  root.querySelectorAll('[data-eff-menu]').forEach(function (menu) {
    var summary = menu.querySelector('summary');
    var items = function () { return Array.prototype.slice.call(menu.querySelectorAll('[data-eff-menu-panel] a')); };
    var close = function (focusSummary) {
      if (!menu.open) return;
      menu.open = false;
      if (focusSummary && summary) summary.focus();
    };
    var focusItem = function (index) {
      var list = items();
      if (!list.length) return;
      list[(index + list.length) % list.length].focus();
    };
    // Revenir sur la page par « Précédent » ne doit pas laisser le menu ouvert.
    menu.open = false;
    window.addEventListener('pageshow', function () { menu.open = false; });
    document.addEventListener('pointerdown', function (e) {
      if (menu.open && !menu.contains(e.target)) close(false);
    });
    menu.addEventListener('focusout', function (e) {
      if (menu.open && e.relatedTarget && !menu.contains(e.relatedTarget)) close(false);
    });
    menu.addEventListener('toggle', function () {
      if (summary) summary.setAttribute('aria-expanded', menu.open ? 'true' : 'false');
      if (menu.open) place(menu);
    });
    if (summary) {
      summary.setAttribute('aria-expanded', 'false');
      summary.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          e.preventDefault();
          menu.open = true;
          var list = items();
          var current = list.findIndex(function (a) { return a.classList.contains('is-active'); });
          focusItem(e.key === 'ArrowUp' ? -1 : (current >= 0 ? current : 0));
        }
      });
    }
    menu.addEventListener('keydown', function (e) {
      var list = items();
      var i = list.indexOf(document.activeElement);
      if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        close(true);
      } else if (i >= 0 && e.key === 'ArrowDown') {
        e.preventDefault(); focusItem(i + 1);
      } else if (i >= 0 && e.key === 'ArrowUp') {
        e.preventDefault(); focusItem(i - 1);
      } else if (i >= 0 && e.key === 'Home') {
        e.preventDefault(); focusItem(0);
      } else if (i >= 0 && e.key === 'End') {
        e.preventDefault(); focusItem(-1);
      }
    });
  });

  // Le menu s'ouvre sous le bouton ; s'il déborde de l'écran, il s'aligne sur l'autre bord.
  function place(menu) {
    var panel = menu.querySelector('[data-eff-menu-panel]');
    if (!panel) return;
    panel.classList.remove('is-left');
    var r = panel.getBoundingClientRect();
    if (r.left < 8) panel.classList.add('is-left');
  }

  /* ——— Barre d'onglets : onglet actif visible, ombres de défilement ——— */
  var scroller = root.querySelector('[data-eff-tabs-scroll]');
  if (scroller) {
    var activeTab = scroller.querySelector('.is-active');
    if (activeTab && scroller.scrollWidth > scroller.clientWidth) {
      scroller.scrollLeft = Math.max(0, activeTab.offsetLeft - scroller.clientWidth / 3);
    }
    var edges = function () {
      var max = scroller.scrollWidth - scroller.clientWidth;
      scroller.classList.toggle('has-before', scroller.scrollLeft > 4);
      scroller.classList.toggle('has-after', scroller.scrollLeft < max - 4);
    };
    scroller.addEventListener('scroll', edges, { passive: true });
    window.addEventListener('resize', edges);
    edges();
  }

  /* ——— Tableur : densité, groupes repliables, tri en mode liste ——— */
  var sheet = root.querySelector('.eff-mq');
  if (sheet) {
    var densityButtons = sheet.querySelectorAll('[data-eff-density]');
    var applyDensity = function (dense) {
      sheet.classList.toggle('is-dense', dense);
      densityButtons.forEach(function (b) {
        var on = (b.getAttribute('data-eff-density') === '1') === dense;
        b.classList.toggle('is-on', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    };
    try { applyDensity(window.localStorage.getItem('eff-roster-dense') === '1'); } catch (e) { applyDensity(false); }
    densityButtons.forEach(function (b) {
      b.addEventListener('click', function () {
        var dense = b.getAttribute('data-eff-density') === '1';
        applyDensity(dense);
        try { window.localStorage.setItem('eff-roster-dense', dense ? '1' : '0'); } catch (e) { /* stockage indisponible */ }
      });
    });
    sheet.addEventListener('click', function (e) {
      var toggle = e.target.closest('[data-eff-group-toggle]');
      if (!toggle) return;
      var key = toggle.getAttribute('data-eff-group-toggle');
      var open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      var head = toggle.closest('.eff-mq-gh');
      if (head) head.classList.toggle('is-closed', !open);
      sheet.querySelectorAll('[data-eff-group-row="' + key + '"]').forEach(function (row) {
        row.classList.toggle('is-group-hidden', !open);
      });
    });
    var form = sheet.querySelector('form.eff-mq-bar');
    if (form) {
      // Choisir un ordre de tri passe en « Liste » (les groupes imposent leur propre ordre).
      form.addEventListener('change', function (e) {
        if (e.target && e.target.hasAttribute('data-eff-sort-select')) {
          var g = form.querySelector('[data-eff-group-input]');
          if (g) g.value = 'liste';
        }
      }, true);
    }
  }

  /* ——— Fenêtre de recherche ——— */
  var dlg = document.getElementById('eff-cmdk');
  if (!dlg || typeof dlg.showModal !== 'function') return;
  var input = dlg.querySelector('#eff-cmdk-q');
  var list = dlg.querySelector('#eff-cmdk-list');
  var status = dlg.querySelector('[data-eff-cmdk-status]');
  var endpoint = dlg.getAttribute('data-endpoint') || '';
  var pages = [];
  try { pages = JSON.parse(dlg.getAttribute('data-pages') || '[]'); } catch (e) { pages = []; }
  var opener = null;
  var timer = 0;
  var controller = null;
  var options = [];
  var activeIndex = -1;
  var lastQuery = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }
  function plate(code, adminId) {
    if (!code) return '';
    var segs = String(code).split('-').map(function (s, i) {
      return '<span class="eff-ucode__seg' + (i === 0 ? ' is-root' : '') + '">' + esc(s) + '</span>';
    }).join('');
    return '<span class="eff-ucode eff-ucode--sm" translate="no"><span class="eff-ucode__plate">' + segs + '</span>' +
      (adminId ? '<span class="eff-ucode__id">' + esc(adminId) + '</span>' : '') + '</span>';
  }

  function open(initial) {
    opener = document.activeElement;
    if (!dlg.open) dlg.showModal();
    input.value = initial || '';
    render({ members: [], units: [], jobs: [] }, input.value);
    if (input.value.trim().length >= 2) schedule();
    input.focus();
    input.select();
  }
  function close() {
    if (dlg.open) dlg.close();
  }
  dlg.addEventListener('close', function () {
    if (controller) controller.abort();
    if (opener && typeof opener.focus === 'function' && document.contains(opener)) opener.focus();
  });
  dlg.addEventListener('click', function (e) {
    if (e.target === dlg) close();
  });
  dlg.querySelectorAll('[data-eff-cmdk-close]').forEach(function (b) {
    b.addEventListener('click', close);
  });

  document.querySelectorAll('[data-eff-search-open]').forEach(function (b) {
    b.addEventListener('click', function (e) { e.preventDefault(); open(''); });
  });

  // Ctrl+K ouvre cette recherche dans les effectifs (avant la recherche générale du back-office).
  window.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && !e.altKey && String(e.key).toLowerCase() === 'k') {
      e.preventDefault();
      e.stopImmediatePropagation();
      if (dlg.open) { input.focus(); input.select(); } else { open(''); }
      return;
    }
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && !dlg.open) {
      var t = e.target;
      var typing = t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName));
      if (typing || document.querySelector('dialog[open]')) return;
      e.preventDefault();
      // Sur le tableur, « / » place le curseur dans sa recherche ; ailleurs il ouvre cette fenêtre.
      var local = document.querySelector('[data-eff-slash-focus]');
      if (local) { local.focus(); local.select && local.select(); } else { open(''); }
    }
  }, true);

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(fetchResults, 160);
  }

  function fetchResults() {
    var q = input.value.trim();
    if (q === lastQuery) return;
    lastQuery = q;
    if (q.length < 2 && !/^\d+$/.test(q)) {
      render({ members: [], units: [], jobs: [] }, q);
      return;
    }
    if (controller) controller.abort();
    controller = typeof AbortController === 'function' ? new AbortController() : null;
    status.textContent = 'Recherche…';
    fetch(endpoint + '?q=' + encodeURIComponent(q), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      signal: controller ? controller.signal : undefined
    }).then(function (r) {
      return r.json().then(function (data) { return { ok: r.ok, data: data }; });
    }).then(function (res) {
      if (input.value.trim() !== q) return;
      if (!res.ok || !res.data || res.data.ok === false) {
        status.textContent = (res.data && res.data.message) || 'La recherche n’a pas abouti. Réessayez.';
        return;
      }
      render(res.data, q);
    }).catch(function (err) {
      if (err && err.name === 'AbortError') return;
      status.textContent = 'La recherche n’a pas abouti. Vérifiez la connexion puis réessayez.';
    });
  }

  function render(data, q) {
    var groups = [];
    var members = (data.members || []).map(function (m) {
      var meta = [m.grade, m.callsign ? '« ' + m.callsign + ' »' : '', m.role || m.job].filter(Boolean).join(' · ');
      return { url: m.url, html: '<span class="eff-cmdk__main"><b>' + esc(m.name) + '</b><small>' + esc(meta || m.unit || 'Sans unité') + '</small></span>' + plate(m.unit_code, '') };
    });
    var units = (data.units || []).map(function (u) {
      return { url: u.url, html: '<span class="eff-cmdk__main"><b>' + esc(u.name) + '</b><small>' + esc(u.members + ' membre' + (u.members > 1 ? 's' : '')) + '</small></span>' + plate(u.code, u.admin_id) };
    });
    var jobs = (data.jobs || []).map(function (j) {
      var kind = j.kind === 'unit_role' ? 'Rôle dans une unité' : 'Emploi';
      return { url: j.url, html: '<span class="eff-cmdk__main"><b>' + esc(j.name) + '</b><small>' + esc(kind + ' · ' + j.holders + ' titulaire' + (j.holders > 1 ? 's' : '')) + '</small></span>' };
    });
    var nq = norm(q);
    var pageItems = pages.filter(function (p) { return !nq || norm(p.label).indexOf(nq) !== -1; }).slice(0, nq ? 4 : 8).map(function (p) {
      return { url: p.url, html: '<span class="eff-cmdk__main"><b>' + esc(p.label) + '</b><small>' + esc(p.group) + '</small></span>' };
    });
    if (members.length) groups.push(['Membres', members]);
    if (units.length) groups.push(['Unités', units]);
    if (jobs.length) groups.push(['Emplois et rôles', jobs]);
    if (pageItems.length) groups.push([nq ? 'Pages' : 'Aller à', pageItems]);

    options = [];
    var html = '';
    groups.forEach(function (g, gi) {
      html += '<div class="eff-cmdk__group" role="group" aria-labelledby="eff-cmdk-g' + gi + '"><p class="eff-cmdk__group-label" id="eff-cmdk-g' + gi + '">' + esc(g[0]) + '</p>';
      g[1].forEach(function (item) {
        var id = 'eff-cmdk-o' + options.length;
        options.push({ id: id, url: item.url });
        html += '<a class="eff-cmdk__opt" role="option" id="' + id + '" href="' + esc(item.url) + '" tabindex="-1" aria-selected="false">' + item.html + '</a>';
      });
      html += '</div>';
    });
    list.innerHTML = html;
    var searched = q.length >= 2 || /^\d+$/.test(q);
    var found = members.length + units.length + jobs.length;
    status.textContent = searched
      ? (found ? found + ' résultat' + (found > 1 ? 's' : '') : 'Aucun membre, unité ou emploi ne correspond. Essayez un indicatif, un code unité ou un numéro.')
      : 'Tapez au moins 2 caractères : nom, indicatif, code unité, numéro, emploi ou rôle.';
    setActive(options.length ? 0 : -1);
  }

  function setActive(i) {
    activeIndex = i;
    options.forEach(function (o, k) {
      var el = document.getElementById(o.id);
      if (!el) return;
      el.setAttribute('aria-selected', k === i ? 'true' : 'false');
      el.classList.toggle('is-active', k === i);
      if (k === i) el.scrollIntoView({ block: 'nearest' });
    });
    if (i >= 0 && options[i]) input.setAttribute('aria-activedescendant', options[i].id);
    else input.removeAttribute('aria-activedescendant');
  }

  input.addEventListener('input', function () { lastQuery = null; schedule(); });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); if (options.length) setActive((activeIndex + 1) % options.length); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); if (options.length) setActive((activeIndex - 1 + options.length) % options.length); }
    else if (e.key === 'Home' && options.length && e.ctrlKey) { e.preventDefault(); setActive(0); }
    else if (e.key === 'End' && options.length && e.ctrlKey) { e.preventDefault(); setActive(options.length - 1); }
    else if (e.key === 'Enter') {
      clearTimeout(timer);
      if (activeIndex >= 0 && options[activeIndex]) {
        e.preventDefault();
        if (e.ctrlKey || e.metaKey) window.open(options[activeIndex].url, '_blank', 'noopener');
        else window.location.href = options[activeIndex].url;
      } else {
        lastQuery = null;
        fetchResults();
      }
    }
  });
  list.addEventListener('mousemove', function (e) {
    var opt = e.target.closest('.eff-cmdk__opt');
    if (!opt) return;
    var k = options.findIndex(function (o) { return o.id === opt.id; });
    if (k >= 0 && k !== activeIndex) setActive(k);
  });
})();
