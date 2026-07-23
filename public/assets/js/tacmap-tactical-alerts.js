/**
 * Panneau signalements Tacmap — miroir web de l’inbox cTab Athena / ATAK Enhanced
 * (Contact, fin de contact, FRAGO, SALUTE, opérateur à terre, bilan des dégâts).
 */
(function (global) {
  'use strict';

  var FILTERS = [
    { id: 'all', label: 'Tout' },
    { id: 'tic', label: 'Contact' },
    { id: 'bda', label: 'BDA' },
    { id: 'frago', label: 'FRAGO' },
    { id: 'salute', label: 'SALUTE' },
    { id: 'eagle_down', label: 'À terre' },
  ];

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function severityClass(sev) {
    if (sev === 'critical') return 'tacmap-talert--critical';
    if (sev === 'high') return 'tacmap-talert--high';
    if (sev === 'info') return 'tacmap-talert--info';
    return 'tacmap-talert--mid';
  }

  function normalizeKind(k) {
    return String(k || 'tic').toLowerCase().replace(/[\s-]+/g, '_');
  }

  function hasMapPos(a) {
    var x = a && a.pos_x != null ? parseFloat(a.pos_x) : NaN;
    var y = a && a.pos_y != null ? parseFloat(a.pos_y) : NaN;
    return !isNaN(x) && !isNaN(y) && !(Math.abs(x) < 0.5 && Math.abs(y) < 0.5);
  }

  function ensureFilterBar(listEl) {
    if (!listEl || !listEl.parentNode) return null;
    var host = listEl.parentNode;
    var bar = host.querySelector('[data-tacmap-talert-filters]');
    if (bar) return bar;
    bar = document.createElement('div');
    bar.className = 'tacmap-talert-filters';
    bar.setAttribute('data-tacmap-talert-filters', '1');
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', 'Filtrer les signalements');
    bar.innerHTML = FILTERS.map(function (f, i) {
      return (
        '<button type="button" class="tacmap-talert-filter' + (i === 0 ? ' is-active' : '') + '" data-filter="' +
        escapeHtml(f.id) + '">' + escapeHtml(f.label) + '</button>'
      );
    }).join('');
    host.insertBefore(bar, listEl);
    return bar;
  }

  function currentFilter(bar) {
    if (!bar) return 'all';
    var active = bar.querySelector('.tacmap-talert-filter.is-active');
    return active ? String(active.getAttribute('data-filter') || 'all') : 'all';
  }

  function filterAlerts(alerts, kind) {
    var list = Array.isArray(alerts) ? alerts : [];
    if (!kind || kind === 'all') return list;
    if (kind === 'tic') {
      return list.filter(function (a) {
        var k = normalizeKind(a.kind);
        return k === 'tic' || k === 'tic_clear';
      });
    }
    return list.filter(function (a) { return normalizeKind(a.kind) === kind; });
  }

  function renderList(el, alerts, opts) {
    opts = opts || {};
    if (!el) return;
    var bar = ensureFilterBar(el);
    var kind = opts.filter != null ? opts.filter : currentFilter(bar);
    var filtered = filterAlerts(alerts, kind);

    if (!filtered.length) {
      el.innerHTML = '<p class="text-sm text-[color:var(--tm-muted)]">Aucun signalement pour ce filtre.</p>';
      return;
    }

    el.innerHTML = filtered.slice().reverse().map(function (a) {
      var id = a.id != null ? String(a.id) : '';
      var clickable = hasMapPos(a);
      return (
        '<article class="tacmap-talert ' + severityClass(a.severity) +
          (clickable ? ' tacmap-talert--locate' : '') + '"' +
          (id ? ' data-alert-id="' + escapeHtml(id) + '"' : '') +
          (clickable ? ' data-pos-x="' + escapeHtml(a.pos_x) + '" data-pos-y="' + escapeHtml(a.pos_y) + '" tabindex="0" role="button" title="Centrer sur la carte"' : '') +
          '>' +
          '<header><strong>' + escapeHtml(a.kind_label || 'Alerte') + '</strong>' +
          '<span>' + escapeHtml(a.call_sign || a.author || '') + '</span></header>' +
          '<p>' + escapeHtml(a.summary || '') + '</p>' +
          (a.grid ? '<p class="tacmap-talert__grid">Grille ' + escapeHtml(a.grid) + '</p>' : '') +
        '</article>'
      );
    }).join('');
  }

  function bindUi(listEl, getAlerts, onLocate) {
    if (!listEl || listEl.getAttribute('data-talert-bound') === '1') return;
    listEl.setAttribute('data-talert-bound', '1');
    var bar = ensureFilterBar(listEl);
    if (bar) {
      bar.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('[data-filter]') : null;
        if (!btn) return;
        bar.querySelectorAll('.tacmap-talert-filter').forEach(function (b) {
          b.classList.toggle('is-active', b === btn);
        });
        renderList(listEl, typeof getAlerts === 'function' ? getAlerts() : [], {
          filter: btn.getAttribute('data-filter'),
        });
      });
    }
    listEl.addEventListener('click', function (ev) {
      var art = ev.target && ev.target.closest ? ev.target.closest('.tacmap-talert--locate') : null;
      if (!art || typeof onLocate !== 'function') return;
      var x = parseFloat(art.getAttribute('data-pos-x'));
      var y = parseFloat(art.getAttribute('data-pos-y'));
      if (!isNaN(x) && !isNaN(y)) onLocate(x, y, art.getAttribute('data-alert-id'));
    });
    listEl.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Enter' && ev.key !== ' ') return;
      var art = ev.target && ev.target.closest ? ev.target.closest('.tacmap-talert--locate') : null;
      if (!art || typeof onLocate !== 'function') return;
      ev.preventDefault();
      var x = parseFloat(art.getAttribute('data-pos-x'));
      var y = parseFloat(art.getAttribute('data-pos-y'));
      if (!isNaN(x) && !isNaN(y)) onLocate(x, y, art.getAttribute('data-alert-id'));
    });
  }

  function buildUrl(apiBase, mapId) {
    var base = String(apiBase || '').replace(/\/$/, '');
    if (base.indexOf('/atak') >= 0) {
      return base + '/tactical-alerts?mapId=' + encodeURIComponent(mapId || 1) + '&limit=40';
    }
    return base + '/atak/tactical-alerts?mapId=' + encodeURIComponent(mapId || 1) + '&limit=40';
  }

  /**
   * @param {string} apiBase
   * @param {number|string} mapId
   * @param {HTMLElement} listEl
   * @param {{ onAlerts?: function, onLocate?: function }=} opts
   */
  function pollFlexible(apiBase, mapId, listEl, opts) {
    opts = opts || {};
    var cacheRef = { alerts: [] };
    bindUi(listEl, function () { return cacheRef.alerts; }, opts.onLocate);

    return fetch(buildUrl(apiBase, mapId), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var alerts = (data && data.alerts) ? data.alerts : [];
        cacheRef.alerts = alerts;
        renderList(listEl, alerts);
        if (typeof opts.onAlerts === 'function') opts.onAlerts(alerts);
        return alerts;
      })
      .catch(function () {
        cacheRef.alerts = [];
        renderList(listEl, []);
        if (typeof opts.onAlerts === 'function') opts.onAlerts([]);
        return [];
      });
  }

  /** Parse client (chat ATAK) — miroir TacticalAlertParser PHP. */
  function parseChatBody(body) {
    var raw = String(body || '').trim();
    raw = raw.replace(
      /^\[\d{1,2}:\d{2}:\d{2}\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\s*/u,
      ''
    );
    var upper = raw.toUpperCase();
    if (upper.indexOf('ALERTE TACTIQUE') !== 0) return null;
    var parts = raw.split('|').map(function (p) { return String(p || '').trim(); });
    var kindRaw = String(parts[1] || 'TIC').toUpperCase().replace(/[\s-]+/g, '_');
    var kindMap = {
      TIC: 'tic',
      CLEAR: 'tic_clear',
      TIC_CLEAR: 'tic_clear',
      TICCLEAR: 'tic_clear',
      FRAGO: 'frago',
      SALUTE: 'salute',
      EAGLE_DOWN: 'eagle_down',
      EAGLEDOWN: 'eagle_down',
      PANIC: 'eagle_down',
      BDA: 'bda',
      BDA_REPORT: 'bda',
    };
    var kind = kindMap[kindRaw] || 'tic';
    var labels = {
      tic: 'Contact',
      tic_clear: 'Fin de contact',
      frago: 'Ordre fragmentaire',
      salute: 'Compte rendu SALUTE',
      eagle_down: 'Opérateur à terre',
      bda: 'Bilan des dégâts',
    };
    var callSign = parts[2] || '';
    var grid = parts[3] || '';
    var summary = parts.slice(6).join(' — ').trim();
    if (!summary) {
      summary = (labels[kind] || 'Alerte') + (callSign ? ' — ' + callSign : '') + (grid ? ' — Grille ' + grid : '');
    }
    return {
      is_tactical: true,
      kind: kind,
      kind_label: labels[kind] || 'Alerte',
      call_sign: callSign,
      grid: grid,
      summary: summary,
      severity: (kind === 'eagle_down' || kind === 'tic') ? 'critical' : (kind === 'frago' || kind === 'bda' ? 'high' : (kind === 'tic_clear' ? 'info' : 'medium')),
    };
  }

  function formatChatBody(body) {
    var p = parseChatBody(body);
    if (!p) return null;
    return (
      '<span class="atak-chat-talert-badge">' + escapeHtml(p.kind_label) + '</span> ' +
      escapeHtml(p.call_sign || '') +
      (p.grid ? ' · grille ' + escapeHtml(p.grid) : '') +
      '<br/>' + escapeHtml(p.summary)
    );
  }

  function parseGroupBody(body) {
    var raw = String(body || '').trim();
    raw = raw.replace(
      /^\[\d{1,2}:\d{2}:\d{2}\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\s*/u,
      ''
    );
    if (raw.toUpperCase().indexOf('GROUPE|') !== 0 && raw.toUpperCase() !== 'GROUPE') return null;
    var parts = raw.split('|').map(function (p) { return String(p || '').trim(); });
    return {
      is_group: true,
      label: 'Message de groupe',
      group_id: parts[1] || '',
      call_sign: parts[2] || '',
      grid: parts[3] || '',
      text: parts.slice(4).join('|') || 'Message de groupe',
    };
  }

  function formatGroupChatBody(body) {
    var p = parseGroupBody(body);
    if (!p) return null;
    return (
      '<span class="atak-chat-group-badge">' + escapeHtml(p.label) + '</span> ' +
      escapeHtml(p.call_sign || '') +
      (p.group_id ? ' · ' + escapeHtml(p.group_id) : '') +
      (p.grid ? ' · grille ' + escapeHtml(p.grid) : '') +
      '<br/>' + escapeHtml(p.text)
    );
  }

  global.TacmapTacticalAlerts = {
    renderList: renderList,
    poll: pollFlexible,
    parseChatBody: parseChatBody,
    formatChatBody: formatChatBody,
    parseGroupBody: parseGroupBody,
    formatGroupChatBody: formatGroupChatBody,
    hasMapPos: hasMapPos,
  };
})(window);
