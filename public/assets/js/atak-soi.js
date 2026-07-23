/* COMSPEC ATAK — Plan PACE (Primary / Alternate / Contingency / Emergency) */
window.ATAKSoi = (function () {
  var KEYS = ['primary', 'alternate', 'contingency', 'emergency'];
  var LABELS = {
    primary: 'Primaire',
    alternate: 'Alternatif',
    contingency: 'Contingence',
    emergency: 'Urgence'
  };

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : (window.ATAK_API_BASE || '');
  }
  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function load() {
    if (!apiBase()) return;
    fetch(apiBase() + '/api/atak/soi?mapId=' + mapId(), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(fillForm)
      .catch(function () {});
  }

  function fillForm(plan) {
    if (!plan) return;
    KEYS.forEach(function (k) {
      var slot = plan[k] || {};
      var freq = document.getElementById('atak-pace-' + k + '-freq');
      var net = document.getElementById('atak-pace-' + k + '-net');
      var label = document.getElementById('atak-pace-' + k + '-label');
      var notes = document.getElementById('atak-pace-' + k + '-notes');
      if (freq) freq.value = slot.freq || '';
      if (net) net.value = slot.net || '';
      if (label) label.value = slot.label || '';
      if (notes) notes.value = slot.notes || '';
    });
    var teamsEl = document.getElementById('atak-pace-teams');
    if (teamsEl) {
      var teams = Array.isArray(plan.teams) ? plan.teams : [];
      if (!teams.length) {
        teamsEl.innerHTML = '<p class="atak-panel-hint">Aucune ligne d’équipe. Ajoutez des fréquences par équipe ci-dessous.</p>';
      } else {
        teamsEl.innerHTML = '<table class="atak-ops-table"><thead><tr><th>Équipe</th><th>P</th><th>A</th><th>C</th><th>E</th></tr></thead><tbody>'
          + teams.map(function (t) {
            return '<tr><td>' + esc(t.name) + '</td><td>' + esc(t.primary) + '</td><td>' + esc(t.alternate)
              + '</td><td>' + esc(t.contingency) + '</td><td>' + esc(t.emergency) + '</td></tr>';
          }).join('')
          + '</tbody></table>';
      }
    }
    var meta = document.getElementById('atak-pace-meta');
    if (meta && plan.updated_at) {
      meta.textContent = 'Dernière mise à jour' + (plan.updated_by ? ' par ' + plan.updated_by : '') + '.';
    }
  }

  function readSlot(k) {
    return {
      label: ((document.getElementById('atak-pace-' + k + '-label') || {}).value || '').trim(),
      freq: ((document.getElementById('atak-pace-' + k + '-freq') || {}).value || '').trim(),
      net: ((document.getElementById('atak-pace-' + k + '-net') || {}).value || '').trim(),
      notes: ((document.getElementById('atak-pace-' + k + '-notes') || {}).value || '').trim()
    };
  }

  function save() {
    if (!apiBase()) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Athena indisponible.');
      return;
    }
    var payload = { mapId: mapId(), teams: [] };
    KEYS.forEach(function (k) { payload[k] = readSlot(k); });
    var teamName = ((document.getElementById('atak-pace-team-name') || {}).value || '').trim();
    if (teamName) {
      payload.teams.push({
        name: teamName,
        primary: ((document.getElementById('atak-pace-team-p') || {}).value || '').trim(),
        alternate: ((document.getElementById('atak-pace-team-a') || {}).value || '').trim(),
        contingency: ((document.getElementById('atak-pace-team-c') || {}).value || '').trim(),
        emergency: ((document.getElementById('atak-pace-team-e') || {}).value || '').trim()
      });
    }
    // Conserver équipes déjà affichées si on n’ajoute pas
    fetch(apiBase() + '/api/atak/soi?mapId=' + mapId(), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (prev) {
        var existing = (prev && Array.isArray(prev.teams)) ? prev.teams.slice() : [];
        if (payload.teams.length) {
          var name = payload.teams[0].name.toUpperCase();
          existing = existing.filter(function (t) { return String(t.name || '').toUpperCase() !== name; });
          existing.push(payload.teams[0]);
        }
        payload.teams = existing;
        return fetch(apiBase() + '/api/atak/soi', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
      })
      .then(function (r) {
        if (!r || !r.ok) throw new Error('fail');
        return r.json();
      })
      .then(function (plan) {
        fillForm(plan);
        ['team-name', 'team-p', 'team-a', 'team-c', 'team-e'].forEach(function (k) {
          var el = document.getElementById('atak-pace-' + k);
          if (el) el.value = '';
        });
      })
      .catch(function () {
        if (window.ATAKShowError) window.ATAKShowError('Impossible d’enregistrer le plan PACE.');
      });
  }

  function bind() {
    var btn = document.getElementById('atak-pace-save');
    if (btn && !btn._bound) {
      btn._bound = true;
      btn.addEventListener('click', save);
    }
    load();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();

  return { load: load, save: save, LABELS: LABELS };
})();
