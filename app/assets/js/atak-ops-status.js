/* COMSPEC ATAK — PERSTAT + logistique (agrégats BFT / médical) */
window.ATAKOpsStatus = (function () {
  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : (window.ATAK_API_BASE || '');
  }
  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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

  function openMedevacTab() {
    var tabBtn = document.querySelector('.atak-tab[data-tab="medical"], .atak-tab[data-tab="medevac"], button[data-tab="medical"]');
    if (tabBtn) {
      tabBtn.click();
      return;
    }
    var medSection = document.getElementById('atak-medevac-section') || document.getElementById('tab-medical');
    if (medSection && medSection.scrollIntoView) {
      medSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function requestResupply(callSign, need, grid) {
    if (!apiBase() || !callSign) return;
    fetch(apiBase() + '/api/atak/logistics/resupply', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        mapId: mapId(),
        call_sign: callSign,
        need: need || 'ravitaillement',
        grid_ref: grid || ''
      })
    }).then(function (r) {
      return r.json().then(function (d) { return { ok: r.ok, data: d }; });
    }).then(function (res) {
      var fb = document.getElementById('atak-logistics-feedback');
      if (fb) {
        fb.hidden = false;
        fb.textContent = (res.data && res.data.message)
          || (res.ok ? 'Demande enregistrée.' : 'Impossible d’enregistrer la demande.');
        fb.className = 'atak-logistics-feedback' + (res.ok ? ' atak-logistics-feedback--ok' : ' atak-logistics-feedback--err');
      }
      if (res.ok) refresh();
    }).catch(function () {
      var fb = document.getElementById('atak-logistics-feedback');
      if (fb) {
        fb.hidden = false;
        fb.textContent = 'Erreur réseau lors de la demande de ravitaillement.';
        fb.className = 'atak-logistics-feedback atak-logistics-feedback--err';
      }
    });
  }

  function renderLogistics(data) {
    var el = document.getElementById('atak-logistics-body');
    if (!el) return;
    var rows = (data && data.rows) || [];
    var section = document.getElementById('atak-logistics-section');
    var alerts = (data && data.alerts) || {};
    var lowCount = (data && data.low_stock_count) || ((alerts.critical || 0) + (alerts.low || 0));
    var requests = (data && data.resupply_requests) || [];
    var medevacOpen = (data && data.medevac_open) || 0;
    var transportHint = (data && data.transport_hint) || '';

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
      if (lowCount > 0) {
        var sum2 = section.querySelector('.atak-collapse-sum');
        if (sum2) {
          var b2 = document.createElement('span');
          b2.className = 'atak-collapse-badge atak-collapse-badge--warn';
          b2.textContent = lowCount + ' bas';
          sum2.appendChild(b2);
        }
      }
    }

    var head = '';
    if (lowCount > 0 || medevacOpen > 0) {
      head += '<div class="atak-logistics-alerts">';
      if (alerts.critical > 0) {
        head += '<span class="atak-logistics-chip atak-logistics-chip--crit">' + esc(alerts.critical) + ' critique(s)</span>';
      }
      if (alerts.low > 0) {
        head += '<span class="atak-logistics-chip atak-logistics-chip--low">' + esc(alerts.low) + ' bas</span>';
      }
      if (medevacOpen > 0) {
        head += '<button type="button" class="atak-logistics-chip atak-logistics-chip--med" id="atak-logistics-goto-medevac">'
          + esc(transportHint || (medevacOpen + ' évacuation(s)')) + '</button>';
      }
      head += '</div>';
      head += '<p class="atak-panel-hint">Seuils : critique ≤ 15 %, bas ≤ 35 %. Demandez un ravitaillement depuis la ligne concernée.</p>';
    }

    var reqHtml = '';
    if (requests.length) {
      reqHtml = '<div class="atak-logistics-requests"><p class="atak-iff-label">Demandes de ravitaillement</p><ul>'
        + requests.map(function (rq) {
          return '<li><strong>' + esc(rq.call_sign) + '</strong> · ' + esc(rq.need)
            + (rq.grid_ref ? ' · ' + esc(rq.grid_ref) : '')
            + (rq.at ? ' <span class="atak-drawer-muted">' + esc(String(rq.at).replace('T', ' ').slice(0, 16)) + '</span>' : '')
            + '</li>';
        }).join('')
        + '</ul></div>';
    }

    el.innerHTML = head + reqHtml
      + '<table class="atak-ops-table"><thead><tr><th>Indicatif</th><th>Équipe</th><th>Carburant</th><th>Munitions</th><th>Signal</th><th>Grille</th><th></th></tr></thead><tbody>'
      + rows.map(function (r) {
        var fuel = r.fuel != null ? (Math.round(r.fuel) + ' %') : '—';
        var cls = (r.fuel_level === 'critical' || r.ammo_level === 'critical')
          ? ' is-crit'
          : ((r.fuel_level === 'low' || r.ammo_level === 'low') ? ' is-warn' : '');
        var need = r.fuel_level === 'critical' || r.fuel_level === 'low'
          ? (r.ammo_level === 'critical' || r.ammo_level === 'low' ? 'complet' : 'carburant')
          : 'munitions';
        var btn = r.needs_resupply
          ? ('<button type="button" class="atak-ops-btn atak-ops-btn--sm atak-logistics-resupply" data-cs="'
            + esc(r.call_sign) + '" data-need="' + esc(need) + '" data-grid="' + esc(r.grid_ref || '')
            + '">Ravitailler</button>')
          : '—';
        return '<tr class="' + cls + '"><td>' + esc(r.call_sign) + '</td><td>' + esc(r.team) + '</td><td>'
          + esc(fuel) + '</td><td>' + esc(r.ammo || '—') + '</td><td>' + esc(r.signal || '—') + '</td><td>'
          + esc(r.grid_ref || '—') + '</td><td>' + btn + '</td></tr>';
      }).join('')
      + '</tbody></table>'
      + '<p class="atak-logistics-feedback" id="atak-logistics-feedback" hidden></p>';

    var gotoMed = document.getElementById('atak-logistics-goto-medevac');
    if (gotoMed) gotoMed.addEventListener('click', openMedevacTab);
    el.querySelectorAll('.atak-logistics-resupply').forEach(function (btn) {
      btn.addEventListener('click', function () {
        requestResupply(btn.getAttribute('data-cs'), btn.getAttribute('data-need'), btn.getAttribute('data-grid'));
      });
    });
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
