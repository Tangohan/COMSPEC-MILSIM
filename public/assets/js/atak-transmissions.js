/* COMSPEC ATAK — canaux de transmission (site / Athena / cTab / ATAK Enhanced) */
window.ATAKTransmissions = (function () {
  var KEYS = ['site', 'athena', 'ctab', 'atak_enhanced'];
  var FALLBACK = {
    site: { label: 'Sur le site', label_en: 'On the site', state: 'absent', state_label: 'Absent', state_label_en: 'Absent' },
    athena: { label: 'Mod Athena', label_en: 'Athena mod', state: 'absent', state_label: 'Absent', state_label_en: 'Absent' },
    ctab: { label: 'cTab', label_en: 'cTab', state: 'absent', state_label: 'Absent', state_label_en: 'Absent' },
    atak_enhanced: { label: 'ATAK Enhanced', label_en: 'ATAK Enhanced', state: 'absent', state_label: 'Absent', state_label_en: 'Absent' }
  };

  function useEn() {
    var lang = (document.documentElement && document.documentElement.lang) || '';
    return String(lang).toLowerCase().indexOf('en') === 0;
  }

  function applyRow(id, row) {
    var en = useEn();
    var chip = document.getElementById('atak-tx-' + id);
    var valueEl = document.getElementById('atak-tx-' + id + '-value');
    var healthEl = document.getElementById('health-tx-' + id);
    var state = (row && row.state) || 'absent';
    var stateLabel = en
      ? ((row && row.state_label_en) || FALLBACK[id].state_label_en)
      : ((row && row.state_label) || FALLBACK[id].state_label);
    var label = en
      ? ((row && row.label_en) || FALLBACK[id].label_en)
      : ((row && row.label) || FALLBACK[id].label);

    if (id === 'site' && row && typeof row.count === 'number' && row.count > 0 && state === 'linked') {
      stateLabel = en
        ? (row.count + ' linked')
        : (row.count + ' en liaison');
    }

    if (chip) {
      chip.classList.remove('atak-tx-chip--linked', 'atak-tx-chip--present', 'atak-tx-chip--absent');
      chip.classList.add('atak-tx-chip--' + state);
      chip.title = label + ' — ' + stateLabel;
      chip.setAttribute('data-state', state);
    }
    if (valueEl) valueEl.textContent = stateLabel;
    if (healthEl) {
      healthEl.textContent = stateLabel;
      healthEl.className = 'atak-health-cell'
        + (state === 'linked' ? ' atak-health-ok-text' : (state === 'present' ? ' atak-health-warn-text' : ' atak-health-muted'));
    }
  }

  function render(transmissions) {
    var data = transmissions && typeof transmissions === 'object' ? transmissions : {};
    KEYS.forEach(function (id) {
      applyRow(id, data[id] || FALLBACK[id]);
    });
  }

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase
      ? window.ATAKSocket.getApiBase()
      : (window.ATAK_API_BASE || '');
  }

  function mapId() {
    if (window.ATAKSocket && window.ATAKSocket.getMapId) {
      return window.ATAKSocket.getMapId();
    }
    return (window.ATAK_DEFAULT_MAP_ID != null && window.ATAK_DEFAULT_MAP_ID > 0)
      ? window.ATAK_DEFAULT_MAP_ID
      : 1;
  }

  function refresh() {
    var base = apiBase();
    if (!base && typeof window.ATAK_API_BASE === 'string') base = window.ATAK_API_BASE;
    var url = (base || '') + '/api/atak/stats?mapId=' + encodeURIComponent(mapId());
    return fetch(url, { credentials: 'include', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (d && d.transmissions) render(d.transmissions);
        return d;
      })
      .catch(function () { return null; });
  }

  return { render: render, refresh: refresh };
})();
