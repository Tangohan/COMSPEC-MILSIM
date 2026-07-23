/* COMSPEC ATAK — Compte rendu SALUTE structuré */
window.ATAKSalute = (function () {
  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : (window.ATAK_API_BASE || '');
  }
  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }
  function author() {
    var u = window.ATAK_USER || {};
    return u.callsign || u.displayName || 'Observateur';
  }

  function submit() {
    if (!apiBase()) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Athena indisponible.');
      return;
    }
    var payload = {
      mapId: mapId(),
      call_sign: author(),
      size: (document.getElementById('atak-salute-size') || {}).value || '',
      activity: (document.getElementById('atak-salute-activity') || {}).value || '',
      location: (document.getElementById('atak-salute-location') || {}).value || '',
      unit: (document.getElementById('atak-salute-unit') || {}).value || '',
      time: (document.getElementById('atak-salute-time') || {}).value || '',
      equipment: (document.getElementById('atak-salute-equipment') || {}).value || '',
      grid: (document.getElementById('atak-salute-grid') || {}).value || ''
    };
    var filled = [payload.size, payload.activity, payload.location, payload.unit, payload.time, payload.equipment]
      .some(function (v) { return String(v || '').trim() !== ''; });
    if (!filled) {
      if (window.ATAKShowError) window.ATAKShowError('Renseignez au moins un champ SALUTE.');
      return;
    }
    fetch(apiBase() + '/api/atak/salute', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) {
      if (!r.ok) throw new Error('fail');
      return r.json();
    }).then(function () {
      ['size', 'activity', 'location', 'unit', 'time', 'equipment', 'grid'].forEach(function (k) {
        var el = document.getElementById('atak-salute-' + k);
        if (el) el.value = '';
      });
      var ok = document.getElementById('atak-salute-feedback');
      if (ok) {
        ok.hidden = false;
        ok.textContent = 'Compte rendu SALUTE transmis.';
        setTimeout(function () { ok.hidden = true; }, 3000);
      }
      if (window.ATAKActivity && window.ATAKActivity.refresh) window.ATAKActivity.refresh();
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d’envoyer le compte rendu SALUTE.');
    });
  }

  function bind() {
    var btn = document.getElementById('atak-salute-submit');
    if (btn && !btn._bound) {
      btn._bound = true;
      btn.addEventListener('click', submit);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();

  return { submit: submit };
})();
