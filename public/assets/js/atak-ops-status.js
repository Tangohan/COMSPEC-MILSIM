/* COMSPEC ATAK — PERSTAT + logistique (agrégats BFT / médical) */
window.ATAKOpsStatus = (function () {
  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : (window.ATAK_API_BASE || '');
  }
  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function renderPerstat(data) {
    var el = document.getElementById('atak-perstat-body');
    if (!el) return;
    var t = (data && data.totals) || {};
    var teams = (data && data.teams) || [];
    var head = '<div class="atak-perstat-totals">'
      + '<span class="atak-perstat-chip atak-perstat-chip--ras">RAS <strong>' + esc(t.ras || 0) + '</strong></span>'
      + '<span class="atak-perstat-chip atak-perstat-chip--wia">WIA <strong>' + esc(t.wia || 0) + '</strong></span>'
      + '<span class="atak-perstat-chip atak-perstat-chip--kia">KIA <strong>' + esc(t.kia || 0) + '</strong></span>'
      + '<span class="atak-perstat-chip">Hors liaison <strong>' + esc(t.offline || 0) + '</strong></span>'
      + '</div>';
    if (!teams.length) {
      el.innerHTML = head + '<div class="atak-empty-state"><p class="atak-empty-state-title">Aucun effectif</p>'
        + '<p class="atak-empty-state-text">Les effectifs en liaison alimenteront ce tableau automatiquement.</p></div>';
      return;
    }
    var rows = teams.map(function (tm) {
      return '<tr><td>' + esc(tm.team) + '</td><td>' + esc(tm.ras) + '</td><td>' + esc(tm.wia)
        + '</td><td>' + esc(tm.kia) + '</td><td>' + esc(tm.offline) + '</td><td>' + esc(tm.total) + '</td></tr>';
    }).join('');
    el.innerHTML = head
      + '<table class="atak-ops-table"><thead><tr><th>Équipe</th><th>RAS</th><th>WIA</th><th>KIA</th><th>Hors liaison</th><th>Total</th></tr></thead>'
      + '<tbody>' + rows + '</tbody></table>';
  }

  function renderLogistics(data) {
    var el = document.getElementById('atak-logistics-body');
    if (!el) return;
    var rows = (data && data.rows) || [];
    var section = document.getElementById('atak-logistics-section');
    if (!rows.length) {
      el.innerHTML = '<div class="atak-empty-state atak-empty-state--compact"><p class="atak-empty-state-title">Aucune donnée logistique</p>'
        + '<p class="atak-empty-state-text">Le carburant et les munitions remontés depuis le jeu apparaîtront ici.</p></div>';
      if (section) {
        section.classList.add('atak-collapse--empty');
        var sum = section.querySelector('.atak-collapse-sum');
        if (sum && !sum.querySelector('.atak-collapse-badge')) {
          var badge = document.createElement('span');
          badge.className = 'atak-collapse-badge';
          badge.textContent = 'Vide';
          sum.appendChild(badge);
        }
      }
      return;
    }
    if (section) {
      section.classList.remove('atak-collapse--empty');
      var oldBadge = section.querySelector('.atak-collapse-badge');
      if (oldBadge) oldBadge.remove();
    }
    el.innerHTML = '<table class="atak-ops-table"><thead><tr><th>Indicatif</th><th>Équipe</th><th>Carburant</th><th>Munitions</th><th>Grille</th></tr></thead><tbody>'
      + rows.map(function (r) {
        var fuel = r.fuel != null ? (Math.round(r.fuel) + ' %') : '—';
        var cls = r.fuel_level === 'critical' ? ' is-crit' : (r.fuel_level === 'low' ? ' is-warn' : '');
        return '<tr class="' + cls + '"><td>' + esc(r.call_sign) + '</td><td>' + esc(r.team) + '</td><td>'
          + esc(fuel) + '</td><td>' + esc(r.ammo || '—') + '</td><td>' + esc(r.grid_ref || '—') + '</td></tr>';
      }).join('')
      + '</tbody></table>';
  }

  function refresh() {
    if (!apiBase()) return;
    fetch(apiBase() + '/api/atak/perstat?mapId=' + mapId(), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(renderPerstat)
      .catch(function () {});
    fetch(apiBase() + '/api/atak/logistics?mapId=' + mapId(), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(renderLogistics)
      .catch(function () {});
  }

  function bind() {
    refresh();
    setInterval(refresh, 12000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();

  return { refresh: refresh };
})();
