/* COMSPEC ATAK — 9-Line MEDEVAC (distinct du CAS) */
window.ATAKMedevac = (function () {
  var LINE_LABELS = [
    '1. Position du point de ramassage',
    '2. Fréquence / indicatif / suffixe',
    '3. Nombre de blessés par précédence (A Urgent / B Prioritaire / C Routine / D Convenience)',
    '4. Équipement spécial requis',
    '5. Nombre de blessés par type (L Civière / A Ambulatoire)',
    '6. Sécurité du point de ramassage',
    '7. Méthode de marquage',
    '8. Nationalité / statut des blessés',
    '9. Contamination NBC'
  ];
  var TERMINAL = { CANCELLED: 1, COMPLETE: 1, ABORTED: 1 };
  var knownIds = {};
  var firstLoad = true;

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : (window.ATAK_API_BASE || '');
  }
  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  function author() {
    var u = window.ATAK_USER || {};
    return u.callsign || u.displayName || 'MEDEVAC';
  }
  function normalizeStatus(s) {
    return String(s || '').trim().toUpperCase();
  }
  function statusFr(s) {
    var k = normalizeStatus(s);
    var map = {
      REQUESTED: 'Demandée',
      ACKNOWLEDGED: 'Prise en compte',
      LAUNCHED: 'En route',
      INBOUND: 'En approche',
      ON_SCENE: 'Sur zone',
      COMPLETE: 'Terminée',
      CANCELLED: 'Annulée',
      ABORTED: 'Interrompue'
    };
    return map[k] || k || '—';
  }
  function isTerminal(status) {
    return !!TERMINAL[normalizeStatus(status)];
  }
  function statusPillClass(status) {
    var k = normalizeStatus(status);
    if (k === 'COMPLETE') return 'atak-medevac-pill atak-medevac-pill--done';
    if (k === 'CANCELLED' || k === 'ABORTED') return 'atak-medevac-pill atak-medevac-pill--cancelled';
    if (k === 'INBOUND' || k === 'LAUNCHED' || k === 'ON_SCENE') return 'atak-medevac-pill atak-medevac-pill--inbound';
    if (k === 'ACKNOWLEDGED') return 'atak-medevac-pill atak-medevac-pill--ack';
    return 'atak-medevac-pill atak-medevac-pill--requested';
  }

  function playMedevacSound() {
    if (window.ATAKSounds && typeof window.ATAKSounds.playEvent === 'function') {
      try { window.ATAKSounds.playEvent('medevac', { priority: true }); } catch (e) {}
    }
  }

  function setStatus(mid, st) {
    if (!mid || !st || !apiBase()) return;
    fetch(apiBase() + '/api/atak/medevac/' + encodeURIComponent(mid) + '/status', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status: st })
    }).then(function () { fetchList(); }).catch(function () {});
  }

  function deleteMedevac(mid) {
    if (!mid || !apiBase()) return;
    if (!window.confirm('Supprimer définitivement cette demande et toutes ses informations ? Cette action est irréversible.')) return;
    fetch(apiBase() + '/api/atak/medevac/' + encodeURIComponent(mid), {
      method: 'DELETE',
      credentials: 'include'
    }).then(function (r) {
      if (!r.ok) throw new Error('fail');
      delete knownIds[String(mid)];
      fetchList();
      if (window.ATAKShowNotification) {
        try { window.ATAKShowNotification('Demande MEDEVAC supprimée.'); } catch (e) {}
      }
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible de supprimer cette demande.');
    });
  }

  function bindCardActions(el) {
    el.querySelectorAll('[data-medevac-status]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setStatus(btn.getAttribute('data-id'), btn.getAttribute('data-medevac-status'));
      });
    });
    el.querySelectorAll('[data-medevac-delete]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        deleteMedevac(btn.getAttribute('data-id'));
      });
    });
  }

  function fetchList() {
    var el = document.getElementById('atak-medevac-list');
    if (!el || !apiBase()) return;
    fetch(apiBase() + '/api/atak/medevac?mapId=' + mapId(), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var list = Array.isArray(data) ? data : [];
        var hasNew = false;
        var nextKnown = {};
        for (var i = 0; i < list.length; i++) {
          var id = String(list[i].id || '');
          if (!id) continue;
          if (!firstLoad && !knownIds[id]) hasNew = true;
          nextKnown[id] = true;
        }
        knownIds = nextKnown;
        firstLoad = false;
        if (hasNew) playMedevacSound();

        if (!list.length) {
          el.innerHTML = '<div class="atak-empty-state"><p class="atak-empty-state-title">Aucune demande d’évacuation médicale</p>'
            + '<p class="atak-empty-state-text">Rédigez une demande pour coordonner une évacuation sanitaire.</p></div>';
          return;
        }
        el.innerHTML = list.map(formatItem).join('');
        bindCardActions(el);
      })
      .catch(function () {});
  }

  function statusButton(id, status, label, modifier) {
    return '<button type="button" class="atak-medevac-btn atak-medevac-btn--' + modifier + '" data-id="' + esc(id)
      + '" data-medevac-status="' + esc(status) + '">' + esc(label) + '</button>';
  }

  function formatItem(m) {
    var lines = '';
    for (var i = 1; i <= 9; i++) {
      var v = m['line' + i];
      if (v) lines += '<div class="atak-medevac-line"><span class="atak-medevac-line-k">L' + i + '</span> ' + esc(v) + '</div>';
    }
    var st = normalizeStatus(m.status);
    var actions = '';
    if (!isTerminal(st)) {
      actions += '<div class="atak-medevac-actions-flow" role="group" aria-label="Avancement de la mission">'
        + statusButton(m.id, 'ACKNOWLEDGED', 'Prise en compte', 'ack')
        + statusButton(m.id, 'INBOUND', 'En approche', 'inbound')
        + statusButton(m.id, 'COMPLETE', 'Terminée', 'done')
        + statusButton(m.id, 'CANCELLED', 'Annuler', 'cancel')
        + '</div>';
    } else {
      actions += '<p class="atak-medevac-terminal-note">Mission clôturée — vous pouvez retirer la fiche du panneau.</p>';
    }
    actions += '<div class="atak-medevac-actions-danger">'
      + '<button type="button" class="atak-medevac-btn atak-medevac-btn--delete" data-id="' + esc(m.id)
      + '" data-medevac-delete="1" title="Effacer définitivement cette demande et ses lignes">Supprimer définitivement</button>'
      + '</div>';

    return '<article class="atak-medevac-card' + (isTerminal(st) ? ' atak-medevac-card--terminal' : '') + '" data-id="' + esc(m.id) + '">'
      + '<header class="atak-medevac-card-head"><strong>MEDEVAC #' + esc(m.id) + '</strong>'
      + '<span class="' + statusPillClass(st) + '">' + esc(statusFr(m.status)) + '</span></header>'
      + '<p class="atak-medevac-meta">' + esc(m.author || '—') + '</p>'
      + lines
      + '<div class="atak-medevac-actions">' + actions + '</div></article>';
  }

  function ensureForm() {
    var wrap = document.getElementById('atak-medevac-form-fields');
    if (!wrap || wrap.dataset.built) return wrap;
    wrap.dataset.built = '1';
    var html = '';
    LINE_LABELS.forEach(function (lab, idx) {
      var name = 'line' + (idx + 1);
      if (idx === 8) {
        html += '<label class="atak-ops-field">' + esc(lab) + '<textarea name="' + name + '" rows="2"></textarea></label>';
      } else {
        html += '<label class="atak-ops-field">' + esc(lab) + '<input type="text" name="' + name + '" autocomplete="off" /></label>';
      }
    });
    html += '<button type="button" class="atak-ops-submit" id="atak-medevac-submit">Envoyer la demande MEDEVAC</button>';
    wrap.innerHTML = html;
    return wrap;
  }

  function submit() {
    if (!apiBase()) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Athena indisponible.');
      return;
    }
    var wrap = ensureForm();
    var payload = { mapId: mapId(), author: author(), lines: {} };
    for (var i = 1; i <= 9; i++) {
      var input = wrap.querySelector('[name="line' + i + '"]');
      payload.lines['line' + i] = payload['line' + i] = input ? input.value.trim() : '';
    }
    if (!payload.line1 && !payload.line2) {
      if (window.ATAKShowError) window.ATAKShowError('Indiquez au minimum la position et la fréquence / indicatif.');
      return;
    }
    fetch(apiBase() + '/api/atak/medevac', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) {
      if (!r.ok) throw new Error('fail');
      return r.json();
    }).then(function () {
      wrap.querySelectorAll('input, textarea').forEach(function (i) { i.value = ''; });
      wrap.style.display = 'none';
      fetchList();
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d’envoyer la demande MEDEVAC.');
    });
  }

  function bind() {
    var btn = document.getElementById('atak-medevac-new');
    if (btn && !btn._bound) {
      btn._bound = true;
      btn.addEventListener('click', function () {
        var wrap = ensureForm();
        if (wrap) wrap.style.display = wrap.style.display === 'none' || !wrap.style.display ? 'block' : 'none';
      });
    }
    document.addEventListener('click', function (e) {
      if (e.target && e.target.id === 'atak-medevac-submit') submit();
    });
    fetchList();
    setInterval(fetchList, 15000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();

  return { fetchList: fetchList, refresh: fetchList };
})();
