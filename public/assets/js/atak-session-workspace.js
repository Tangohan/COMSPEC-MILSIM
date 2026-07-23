/* COMSPEC ATAK — Bloc-notes + tableurs temporaires (SOI / ETA / ID alliés), scopés à la carte. */
window.ATAKSessionWorkspace = (function () {
  var ETA_STATUS = [
    { v: 'en_route', l: 'En route' },
    { v: 'on_time', l: 'À l’heure' },
    { v: 'delayed', l: 'En retard' },
    { v: 'arrived', l: 'Arrivé' },
    { v: 'cancelled', l: 'Annulé' }
  ];

  var panelExpanded = false;

  function refreshMapSize() {
    try {
      var m = window.ATAKMap && typeof window.ATAKMap.getMap === 'function'
        ? window.ATAKMap.getMap()
        : (window.ATAKMap && window.ATAKMap._map);
      if (m && typeof m.invalidateSize === 'function') {
        m.invalidateSize({ animate: false });
      }
    } catch (e) {}
  }

  function updateExpandBtn() {
    var btn = document.getElementById('atak-notes-expand');
    if (!btn) return;
    btn.textContent = panelExpanded ? 'Réduire' : 'Agrandir';
    btn.title = panelExpanded
      ? 'Revenir à la largeur Notes standard'
      : 'Élargir encore le panneau (presque plein écran)';
    btn.setAttribute('aria-pressed', panelExpanded ? 'true' : 'false');
  }

  function setPanelWide(on) {
    var panel = document.getElementById('atak-panel-left');
    if (!panel) return;
    if (!on) {
      panelExpanded = false;
      panel.classList.remove('is-notes-wide', 'is-notes-expanded');
      updateExpandBtn();
      setTimeout(refreshMapSize, 220);
      return;
    }
    panel.classList.add('is-notes-wide');
    panel.classList.toggle('is-notes-expanded', panelExpanded);
    panel.classList.remove('collapsed');
    updateExpandBtn();
    setTimeout(refreshMapSize, 220);
  }

  function toggleExpanded() {
    var panel = document.getElementById('atak-panel-left');
    if (!panel || !panel.classList.contains('is-notes-wide')) {
      panelExpanded = true;
      setPanelWide(true);
      return;
    }
    panelExpanded = !panelExpanded;
    panel.classList.toggle('is-notes-expanded', panelExpanded);
    updateExpandBtn();
    setTimeout(refreshMapSize, 220);
  }

  var state = {
    notepad: '',
    soi: [],
    eta: [],
    allied_ids: [],
    updated_at: null,
    updated_by: null,
    dirty: false,
    saveTimer: null
  };

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

  function setMeta() {
    var el = document.getElementById('atak-notes-meta');
    if (!el) return;
    if (!state.updated_at) {
      el.textContent = 'Données temporaires — liées à cette carte / session, pas au tableur permanent.';
      return;
    }
    el.textContent = 'Dernière mise à jour'
      + (state.updated_by ? ' par ' + state.updated_by : '')
      + ' · temporaires (carte en cours)';
  }

  function emptySoi() {
    return { net: '', callsign: '', suffix: '', frequency: '', alt_frequency: '', role: '', remarks: '' };
  }
  function emptyEta() {
    return { callsign: '', eta: '', status: 'en_route', remarks: '' };
  }
  function emptyAllied() {
    return { callsign: '', military_id: '', affiliation: '', remarks: '' };
  }

  function statusSelectHtml(selected) {
    return '<select class="atak-sheet-input" data-k="status">'
      + ETA_STATUS.map(function (o) {
        return '<option value="' + o.v + '"' + (o.v === selected ? ' selected' : '') + '>' + esc(o.l) + '</option>';
      }).join('')
      + '</select>';
  }

  function renderSoi() {
    var host = document.getElementById('atak-sheet-soi');
    if (!host) return;
    var rows = state.soi.length ? state.soi : [emptySoi()];
    var html = '<table class="atak-ops-table atak-sheet-table"><thead><tr>'
      + '<th>Réseau / canal</th><th>Indicatif</th><th>Suffixe</th><th>Fréquence</th>'
      + '<th>Fréq. de secours</th><th>Rôle / équipe</th><th>Remarques</th><th></th>'
      + '</tr></thead><tbody>';
    rows.forEach(function (r, i) {
      html += '<tr data-i="' + i + '">'
        + cellInput(r.net, 'net')
        + cellInput(r.callsign, 'callsign')
        + cellInput(r.suffix, 'suffix')
        + cellInput(r.frequency, 'frequency')
        + cellInput(r.alt_frequency, 'alt_frequency')
        + cellInput(r.role, 'role')
        + cellInput(r.remarks, 'remarks')
        + '<td><button type="button" class="atak-sheet-del" data-sheet="soi" data-i="' + i + '" title="Retirer la ligne">×</button></td>'
        + '</tr>';
    });
    html += '</tbody></table>';
    host.innerHTML = html;
  }

  function renderEta() {
    var host = document.getElementById('atak-sheet-eta');
    if (!host) return;
    var rows = state.eta.length ? state.eta : [emptyEta()];
    var html = '<table class="atak-ops-table atak-sheet-table"><thead><tr>'
      + '<th>Indicatif</th><th>ETA</th><th>Statut</th><th>Remarques</th><th></th>'
      + '</tr></thead><tbody>';
    rows.forEach(function (r, i) {
      html += '<tr data-i="' + i + '">'
        + cellInput(r.callsign, 'callsign')
        + cellInput(r.eta, 'eta', 'ex. 14:30 Z')
        + '<td>' + statusSelectHtml(r.status || 'en_route') + '</td>'
        + cellInput(r.remarks, 'remarks')
        + '<td><button type="button" class="atak-sheet-del" data-sheet="eta" data-i="' + i + '" title="Retirer la ligne">×</button></td>'
        + '</tr>';
    });
    html += '</tbody></table>';
    host.innerHTML = html;
  }

  function renderAllied() {
    var host = document.getElementById('atak-sheet-allied');
    if (!host) return;
    var rows = state.allied_ids.length ? state.allied_ids : [emptyAllied()];
    var html = '<table class="atak-ops-table atak-sheet-table"><thead><tr>'
      + '<th>Indicatif / unité</th><th>Identifiant ATAK</th><th>Affiliation</th><th>Remarques</th><th></th>'
      + '</tr></thead><tbody>';
    rows.forEach(function (r, i) {
      html += '<tr data-i="' + i + '">'
        + cellInput(r.callsign, 'callsign')
        + cellInput(r.military_id, 'military_id')
        + cellInput(r.affiliation, 'affiliation', 'ex. OTAN, allié')
        + cellInput(r.remarks, 'remarks')
        + '<td><button type="button" class="atak-sheet-del" data-sheet="allied_ids" data-i="' + i + '" title="Retirer la ligne">×</button></td>'
        + '</tr>';
    });
    html += '</tbody></table>';
    host.innerHTML = html;
  }

  function cellInput(val, key, placeholder) {
    return '<td><input class="atak-sheet-input" data-k="' + key + '" type="text" value="'
      + esc(val || '') + '"'
      + (placeholder ? ' placeholder="' + esc(placeholder) + '"' : '')
      + ' autocomplete="off" /></td>';
  }

  function readSheet(sheet) {
    var hostId = sheet === 'soi' ? 'atak-sheet-soi'
      : (sheet === 'eta' ? 'atak-sheet-eta' : 'atak-sheet-allied');
    var host = document.getElementById(hostId);
    if (!host) return [];
    var rows = [];
    host.querySelectorAll('tbody tr').forEach(function (tr) {
      var obj = {};
      tr.querySelectorAll('[data-k]').forEach(function (inp) {
        obj[inp.getAttribute('data-k')] = (inp.value || '').trim();
      });
      rows.push(obj);
    });
    return rows;
  }

  function syncFromDom() {
    var note = document.getElementById('atak-notepad');
    if (note) state.notepad = note.value || '';
    state.soi = readSheet('soi');
    state.eta = readSheet('eta');
    state.allied_ids = readSheet('allied_ids');
  }

  function markDirty() {
    state.dirty = true;
    var badge = document.getElementById('atak-notes-dirty');
    if (badge) badge.hidden = false;
    if (state.saveTimer) clearTimeout(state.saveTimer);
    state.saveTimer = setTimeout(function () { save(false); }, 1800);
  }

  function applyWorkspace(data) {
    if (!data) return;
    state.notepad = data.notepad || '';
    state.soi = Array.isArray(data.soi) ? data.soi : [];
    state.eta = Array.isArray(data.eta) ? data.eta : [];
    state.allied_ids = Array.isArray(data.allied_ids) ? data.allied_ids : [];
    state.updated_at = data.updated_at || null;
    state.updated_by = data.updated_by || null;
    state.dirty = false;
    var note = document.getElementById('atak-notepad');
    if (note) note.value = state.notepad;
    renderSoi();
    renderEta();
    renderAllied();
    setMeta();
    var badge = document.getElementById('atak-notes-dirty');
    if (badge) badge.hidden = true;
  }

  function load() {
    if (!apiBase()) return;
    fetch(apiBase() + '/api/atak/session-workspace?mapId=' + mapId(), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(applyWorkspace)
      .catch(function () {});
  }

  function save(manual) {
    if (!apiBase()) {
      if (manual && window.ATAKShowError) window.ATAKShowError('Liaison Athena indisponible.');
      return;
    }
    syncFromDom();
    var payload = {
      mapId: mapId(),
      notepad: state.notepad,
      soi: state.soi,
      eta: state.eta,
      allied_ids: state.allied_ids
    };
    fetch(apiBase() + '/api/atak/session-workspace', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (r) {
        if (!r.ok) throw new Error('fail');
        return r.json();
      })
      .then(function (data) {
        applyWorkspace(data);
        if (manual && window.ATAKShowError) {
          /* succès silencieux via meta */
        }
      })
      .catch(function () {
        if (window.ATAKShowError) window.ATAKShowError('Impossible d’enregistrer les notes de session.');
      });
  }

  function addRow(sheet) {
    syncFromDom();
    if (sheet === 'soi') state.soi.push(emptySoi());
    else if (sheet === 'eta') state.eta.push(emptyEta());
    else state.allied_ids.push(emptyAllied());
    if (sheet === 'soi') renderSoi();
    else if (sheet === 'eta') renderEta();
    else renderAllied();
    markDirty();
  }

  function delRow(sheet, idx) {
    syncFromDom();
    var arr = sheet === 'soi' ? state.soi : (sheet === 'eta' ? state.eta : state.allied_ids);
    if (idx < 0 || idx >= arr.length) return;
    arr.splice(idx, 1);
    if (sheet === 'soi') renderSoi();
    else if (sheet === 'eta') renderEta();
    else renderAllied();
    markDirty();
  }

  function bind() {
    var root = document.getElementById('tab-notes');
    if (!root || root._atakNotesBound) return;
    root._atakNotesBound = true;

    var note = document.getElementById('atak-notepad');
    if (note) {
      note.addEventListener('input', markDirty);
    }

    root.addEventListener('input', function (ev) {
      if (ev.target && ev.target.classList && ev.target.classList.contains('atak-sheet-input')) {
        markDirty();
      }
    });
    root.addEventListener('change', function (ev) {
      if (ev.target && ev.target.classList && ev.target.classList.contains('atak-sheet-input')) {
        markDirty();
      }
    });

    root.addEventListener('click', function (ev) {
      var t = ev.target;
      if (!t) return;
      if (t.id === 'atak-notes-expand' || (t.closest && t.closest('#atak-notes-expand'))) {
        ev.preventDefault();
        toggleExpanded();
        return;
      }
      if (t.id === 'atak-notes-save' || (t.closest && t.closest('#atak-notes-save'))) {
        ev.preventDefault();
        if (state.saveTimer) clearTimeout(state.saveTimer);
        save(true);
        return;
      }
      if (t.classList && t.classList.contains('atak-sheet-add')) {
        ev.preventDefault();
        addRow(t.getAttribute('data-sheet') || 'soi');
        return;
      }
      if (t.classList && t.classList.contains('atak-sheet-del')) {
        ev.preventDefault();
        delRow(t.getAttribute('data-sheet') || 'soi', parseInt(t.getAttribute('data-i') || '-1', 10));
      }
    });
  }

  function init() {
    bind();
    load();
    updateExpandBtn();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    load: load,
    save: save,
    init: init,
    setPanelWide: setPanelWide,
    toggleExpanded: toggleExpanded
  };
})();
