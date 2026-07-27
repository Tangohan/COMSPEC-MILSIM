/* COMSPEC ATAK — Équipes de feu : filtre BFT + composition ops */
window.ATAKFireTeams = (function () {
  var teams = [];
  var filterTeamId = '';
  var bound = false;
  var lastComposeFp = '';

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase
      ? window.ATAKSocket.getApiBase()
      : (window.ATAK_API_BASE || '');
  }

  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function getFilterTeamId() {
    return filterTeamId;
  }

  function setFilterTeamId(id) {
    filterTeamId = id == null ? '' : String(id);
    if (window.ATAKUnits && typeof window.ATAKUnits.setFireTeamFilter === 'function') {
      window.ATAKUnits.setFireTeamFilter(filterTeamId);
    }
    renderComposition();
    var sel = document.getElementById('atak-ft-filter');
    if (sel && sel.value !== filterTeamId) sel.value = filterTeamId;
  }

  function populateFilter() {
    var sel = document.getElementById('atak-ft-filter');
    if (!sel) return;
    var prev = filterTeamId;
    var opts = '<option value="">Toutes les équipes de feu</option>';
    opts += '<option value="__none__">Sans équipe</option>';
    teams.forEach(function (t) {
      var id = String(t.id || '');
      var label = String(t.label || ('Équipe ' + id));
      var color = String(t.color || '').trim();
      opts += '<option value="' + esc(id) + '"' +
        (color ? ' data-color="' + esc(color) + '"' : '') + '>' +
        esc(label) + '</option>';
    });
    sel.innerHTML = opts;
    if (prev) sel.value = prev;
  }

  function unitsForTeam(teamId) {
    var all = window.ATAKUnits && window.ATAKUnits.getUnits ? window.ATAKUnits.getUnits() : [];
    if (!teamId) return all;
    if (teamId === '__none__') {
      return all.filter(function (u) {
        return !u.fire_team_id && !String(u.fire_team_label || '').trim();
      });
    }
    var sid = String(teamId);
    return all.filter(function (u) {
      return String(u.fire_team_id || '') === sid;
    });
  }

  function liveLabel(u) {
    if (window.ATAKUnits && window.ATAKUnits.resolveLiveStatus) {
      var s = window.ATAKUnits.resolveLiveStatus(u);
      if (s === 'linked') return 'En liaison';
      if (s === 'delayed') return 'Liaison retardée';
      return 'Hors liaison';
    }
    return String(u.status || '—');
  }

  function renderComposition() {
    var el = document.getElementById('atak-ft-composition');
    if (!el) return;
    var focusId = filterTeamId && filterTeamId !== '__none__' ? filterTeamId : '';
    var list = focusId
      ? teams.filter(function (t) { return String(t.id) === String(focusId); })
      : teams.slice();

    if (!list.length) {
      var fpEmpty = 'empty|' + teams.length;
      if (fpEmpty === lastComposeFp) return;
      lastComposeFp = fpEmpty;
      el.innerHTML =
        '<div class="atak-empty-state atak-empty-state--compact">' +
        '<p class="atak-empty-state-title">Aucune équipe de feu</p>' +
        '<p class="atak-empty-state-text">Créez des équipes depuis le back-office, ou attendez qu’elles soient constituées en opération.</p></div>';
      return;
    }

    var html = list.map(function (t) {
      var tid = String(t.id || '');
      var color = String(t.color || '').trim();
      var membersApi = Array.isArray(t.members) ? t.members : [];
      var onMap = unitsForTeam(tid);
      var linked = onMap.filter(function (u) {
        if (!window.ATAKUnits || !window.ATAKUnits.resolveLiveStatus) return true;
        var s = window.ATAKUnits.resolveLiveStatus(u);
        return s === 'linked' || s === 'delayed';
      });
      var roster = onMap.length ? onMap : membersApi.map(function (m) {
        return {
          call_sign: m.callsign || m.call_sign || '',
          role: m.role === 'leader' ? 'Chef d’équipe' : 'Membre',
          status: 'offline',
          fire_team_color: color
        };
      });
      var rows = roster.map(function (u) {
        var cs = u.call_sign || u.callsign || '—';
        var role = u.role || '';
        if (role === 'leader') role = 'Chef d’équipe';
        if (role === 'member') role = 'Membre';
        return '<li class="atak-ft-compose-member">' +
          '<span class="atak-ft-compose-cs">' + esc(cs) + '</span>' +
          (role ? '<span class="atak-ft-compose-role">' + esc(role) + '</span>' : '') +
          '<span class="atak-ft-compose-live">' + esc(liveLabel(u)) + '</span>' +
          '</li>';
      }).join('');
      return '<article class="atak-ft-compose-card"' +
        (color ? ' style="--ft-color:' + esc(color) + '"' : '') + '>' +
        '<header class="atak-ft-compose-head">' +
        '<span class="atak-ft-chip"' + (color ? ' style="--ft-color:' + esc(color) + ';border-color:' + esc(color) + ';color:' + esc(color) + '"' : '') + '>' +
        (color ? '<span class="atak-ft-chip-dot" aria-hidden="true"></span>' : '') +
        esc(t.label || 'Équipe') + '</span>' +
        '<span class="atak-ft-compose-count">' + linked.length + ' / ' + roster.length + ' en liaison</span>' +
        '</header>' +
        (rows
          ? '<ul class="atak-ft-compose-list">' + rows + '</ul>'
          : '<p class="atak-panel-hint">Composition non renseignée pour le moment.</p>') +
        '</article>';
    }).join('');

    var fp = filterTeamId + '|' + html;
    if (fp === lastComposeFp) return;
    lastComposeFp = fp;
    el.innerHTML = html;
  }

  function loadTeams() {
    if (!apiBase()) return Promise.resolve([]);
    return fetch(apiBase() + '/api/atak/fire-teams?mapId=' + encodeURIComponent(mapId()), {
      credentials: 'include',
      cache: 'no-store'
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        teams = (data && Array.isArray(data.fire_teams)) ? data.fire_teams : [];
        populateFilter();
        renderComposition();
        return teams;
      })
      .catch(function () {
        teams = [];
        populateFilter();
        renderComposition();
        return [];
      });
  }

  function bind() {
    if (bound) return;
    bound = true;
    var sel = document.getElementById('atak-ft-filter');
    if (sel) {
      sel.addEventListener('change', function () {
        setFilterTeamId(sel.value || '');
      });
    }
    var refreshBtn = document.getElementById('atak-ft-refresh');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', function () { loadTeams(); });
    }
    window.addEventListener('atak:units-updated', function () {
      renderComposition();
    });
    loadTeams();
    setInterval(function () { loadTeams(); }, 30000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();

  return {
    load: loadTeams,
    getFilterTeamId: getFilterTeamId,
    setFilterTeamId: setFilterTeamId,
    getTeams: function () { return teams.slice(); },
    renderComposition: renderComposition
  };
})();
