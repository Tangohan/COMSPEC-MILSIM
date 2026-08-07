/**
 * ATAK — résumé mission / forces / alertes (panneau droit), données réelles.
 */
(function () {
  'use strict';

  function qs(id) {
    return document.getElementById(id);
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function units() {
    if (window.ATAKUnits && typeof window.ATAKUnits.getUnits === 'function') {
      return window.ATAKUnits.getUnits() || [];
    }
    return [];
  }

  function liveStatus(u) {
    if (window.ATAKUnits && typeof window.ATAKUnits.resolveLiveStatus === 'function') {
      return window.ATAKUnits.resolveLiveStatus(u);
    }
    return 'offline';
  }

  function mapLabel() {
    var sel = qs('atak-map-select');
    if (sel && sel.options && sel.selectedIndex >= 0) {
      return (sel.options[sel.selectedIndex].textContent || '').trim() || '—';
    }
    if (window.ATAK_MAP_CONFIG && window.ATAK_MAP_CONFIG.slug) {
      return String(window.ATAK_MAP_CONFIG.slug).toUpperCase();
    }
    return '—';
  }

  function workspaceLabel() {
    var sel = qs('atak-workspace-select');
    if (sel && sel.options && sel.selectedIndex >= 0) {
      return (sel.options[sel.selectedIndex].textContent || '').trim() || 'Mission';
    }
    return 'Mission';
  }

  function groupUnits(list) {
    var groups = {};
    list.forEach(function (u) {
      var key = String(u.fire_team_label || '').trim() || 'Sans équipe';
      if (!groups[key]) {
        groups[key] = {
          label: key,
          color: String(u.fire_team_color || '').trim(),
          members: [],
          linked: 0
        };
      }
      groups[key].members.push(u);
      var st = liveStatus(u);
      if (st === 'linked' || st === 'delayed') groups[key].linked += 1;
      if (!groups[key].color && u.fire_team_color) {
        groups[key].color = String(u.fire_team_color).trim();
      }
    });
    return Object.keys(groups).map(function (k) { return groups[k]; })
      .sort(function (a, b) {
        if (a.label === 'Sans équipe') return 1;
        if (b.label === 'Sans équipe') return -1;
        return a.label.localeCompare(b.label, 'fr');
      });
  }

  function collectAlerts(list) {
    var alerts = [];
    var medicalBadge = qs('atak-medical-tab-badge');
    if (medicalBadge && !medicalBadge.hidden && (medicalBadge.textContent || '').trim()) {
      alerts.push({
        title: 'Alerte médicale',
        sub: (medicalBadge.textContent || '').trim() + ' à traiter',
        time: ''
      });
    }
    var pingCount = 0;
    list.forEach(function (u) {
      var st = liveStatus(u);
      if (st === 'delayed') {
        alerts.push({
          title: 'Liaison retardée',
          sub: (u.call_sign || 'Contact') + ' · signal ancien',
          time: ''
        });
      }
      var health = String((u.extra && (u.extra.health || u.extra.medical_status)) || u.health || '').toLowerCase();
      if (health && health !== 'ok' && health !== 'stable' && health !== 'healthy') {
        alerts.push({
          title: 'État personnel',
          sub: (u.call_sign || 'Contact') + ' · ' + health,
          time: ''
        });
      }
    });
    var ordersBadge = qs('atak-orders-tab-badge');
    if (ordersBadge && !ordersBadge.hidden && (ordersBadge.textContent || '').trim()) {
      alerts.push({
        title: 'Ordres en attente',
        sub: (ordersBadge.textContent || '').trim() + ' directive(s)',
        time: ''
      });
    }
    return alerts.slice(0, 5);
  }

  function render() {
    var list = units();
    var linked = list.filter(function (u) {
      var s = liveStatus(u);
      return s === 'linked' || s === 'delayed';
    });
    var groups = groupUnits(list);
    var alerts = collectAlerts(list);

    var missionName = qs('atak-summary-mission-name');
    var missionMeta = qs('atak-summary-mission-meta');
    var metricUnits = qs('atak-summary-metric-units');
    var metricGroups = qs('atak-summary-metric-groups');
    var forcesBody = qs('atak-summary-forces-body');
    var alertsBody = qs('atak-summary-alerts-body');
    var alertsCount = qs('atak-summary-alerts-count');
    var mapTag = qs('atak-map-tag-name');
    var netTag = qs('atak-map-tag-net');

    if (missionName) missionName.textContent = workspaceLabel();
    if (missionMeta) missionMeta.textContent = mapLabel() + ' · ' + linked.length + ' en liaison';
    if (metricUnits) metricUnits.textContent = String(linked.length || list.length || 0);
    if (metricGroups) metricGroups.textContent = String(groups.filter(function (g) { return g.label !== 'Sans équipe'; }).length || groups.length || 0);
    if (mapTag) mapTag.textContent = mapLabel();
    if (netTag) {
      var status = qs('atak-status');
      var online = status && status.classList.contains('atak-chip--live') && !status.classList.contains('offline');
      netTag.textContent = online ? 'OPÉRATIONNEL' : 'ATTENTE';
      netTag.style.color = online ? 'var(--atak-c2-green, #35d6a1)' : '#d3a33c';
    }

    if (forcesBody) {
      if (!groups.length) {
        forcesBody.innerHTML = '<p class="atak-panel-hint">Aucun groupe pour le moment.</p>';
      } else {
        forcesBody.innerHTML = groups.slice(0, 8).map(function (g) {
          var pct = g.members.length ? Math.round((g.linked / g.members.length) * 100) : 0;
          var wia = g.linked < g.members.length && g.linked > 0;
          return '<div class="atak-force-row">' +
            '<span class="atak-force-dot' + (wia ? ' wia' : '') + '"' +
            (g.color ? ' style="background:' + esc(g.color) + '"' : '') + '></span>' +
            '<div><div class="atak-force-name">' + esc(g.label) + '</div>' +
            '<div class="atak-force-sub">' + g.members.length + ' membres · ' + g.linked + ' en liaison</div></div>' +
            '<div class="atak-force-grid">' + pct + '%</div></div>';
        }).join('');
      }
    }

    if (alertsCount) {
      alertsCount.textContent = alerts.length ? String(alerts.length) : '0';
      alertsCount.style.color = alerts.length ? 'var(--atak-c2-amber, #d3a33c)' : '';
    }
    if (alertsBody) {
      if (!alerts.length) {
        alertsBody.innerHTML = '<p class="atak-panel-hint">Aucune alerte active.</p>';
      } else {
        alertsBody.innerHTML = alerts.map(function (a) {
          return '<div class="atak-alert-row"><span class="a-dot"></span>' +
            '<div><strong>' + esc(a.title) + '</strong><small>' + esc(a.sub) + '</small></div>' +
            '<time>' + esc(a.time || '') + '</time></div>';
        }).join('');
      }
    }
  }

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    if (window.ATAK_POPOUT === 'left') return;
    render();
    window.setInterval(render, 4000);
    var mapSel = qs('atak-map-select');
    var wsSel = qs('atak-workspace-select');
    if (mapSel) mapSel.addEventListener('change', render);
    if (wsSel) wsSel.addEventListener('change', render);
    document.addEventListener('atak:units-updated', render);
    window.ATAKMissionSummary = { render: render };
  });
})();
