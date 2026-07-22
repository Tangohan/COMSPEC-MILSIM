/**
 * Panneau alertes tactiques Tacmap (Contact, fin de contact, FRAGO, SALUTE, opérateur à terre).
 */
(function (global) {
  'use strict';

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

  function renderList(el, alerts) {
    if (!el) return;
    if (!alerts || !alerts.length) {
      el.innerHTML = '<p class="text-sm text-[color:var(--tm-muted)]">Aucun signalement récent.</p>';
      return;
    }
    el.innerHTML = alerts.slice().reverse().map(function (a) {
      return (
        '<article class="tacmap-talert ' + severityClass(a.severity) + '">' +
          '<header><strong>' + escapeHtml(a.kind_label || 'Alerte') + '</strong>' +
          '<span>' + escapeHtml(a.call_sign || a.author || '') + '</span></header>' +
          '<p>' + escapeHtml(a.summary || '') + '</p>' +
          (a.grid ? '<p class="tacmap-talert__grid">Grille ' + escapeHtml(a.grid) + '</p>' : '') +
        '</article>'
      );
    }).join('');
  }

  function poll(apiBase, mapId, listEl) {
    if (!apiBase) return Promise.resolve([]);
    return fetch(apiBase + '/atak/tactical-alerts?mapId=' + encodeURIComponent(mapId || 1) + '&limit=30', {
      credentials: 'include',
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var alerts = (data && data.alerts) ? data.alerts : [];
        renderList(listEl, alerts);
        return alerts;
      })
      .catch(function () {
        renderList(listEl, []);
        return [];
      });
  }

  /**
   * Note : apiBase Tacmap est souvent « /api » → chemin = apiBase + '/atak/tactical-alerts'
   * ou apiBase déjà « /api/atak » selon le contexte.
   */
  function pollFlexible(apiBase, mapId, listEl) {
    var base = String(apiBase || '').replace(/\/$/, '');
    var url;
    if (base.indexOf('/atak') >= 0) {
      url = base + '/tactical-alerts?mapId=' + encodeURIComponent(mapId || 1) + '&limit=30';
    } else {
      url = base + '/atak/tactical-alerts?mapId=' + encodeURIComponent(mapId || 1) + '&limit=30';
    }
    return fetch(url, { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var alerts = (data && data.alerts) ? data.alerts : [];
        renderList(listEl, alerts);
        return alerts;
      })
      .catch(function () {
        renderList(listEl, []);
        return [];
      });
  }

  global.TacmapTacticalAlerts = {
    renderList: renderList,
    poll: pollFlexible,
  };
})(window);
