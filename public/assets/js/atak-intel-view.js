/**
 * Bandeau intel chiffrée + alertes appareils (TOC).
 */
(function () {
  'use strict';

  var POLL_MS = 8000;
  var lastAlertKeys = {};
  var bannerEl = null;
  var listEl = null;

  function apiBase() {
    if (typeof window.ATAK_API_BASE === 'string' && window.ATAK_API_BASE) {
      return window.ATAK_API_BASE.replace(/\/$/, '');
    }
    return '';
  }

  function ensureBanner() {
    if (bannerEl) return bannerEl;
    bannerEl = document.getElementById('atak-intel-banner');
    if (!bannerEl) {
      bannerEl = document.createElement('div');
      bannerEl.id = 'atak-intel-banner';
      bannerEl.className = 'atak-intel-banner';
      bannerEl.setAttribute('role', 'status');
      bannerEl.hidden = true;
      var host = document.querySelector('.atak-shell') || document.body;
      host.insertBefore(bannerEl, host.firstChild);
    }
    return bannerEl;
  }

  function ensureDeviceList() {
    if (listEl) return listEl;
    listEl = document.getElementById('atak-device-alerts-list');
    return listEl;
  }

  function applyBodyClass(mode) {
    document.body.classList.toggle('atak-intel-encrypted', mode === 'encrypted');
    document.body.classList.toggle('atak-intel-jammed', mode === 'jammed');
    document.body.classList.toggle('atak-intel-clear', !mode || mode === 'clear');
  }

  function renderBanner(view) {
    var el = ensureBanner();
    var mode = (view && view.mode) || 'clear';
    var label = (view && view.reason_label) || '';
    applyBodyClass(mode);
    if (mode === 'clear' || !label) {
      el.hidden = true;
      el.textContent = '';
      el.className = 'atak-intel-banner';
      return;
    }
    el.hidden = false;
    el.textContent = label;
    el.className = 'atak-intel-banner atak-intel-banner--' + mode;
  }

  function severityClass(sev) {
    if (sev === 'critical') return 'atak-device-alert--critical';
    return 'atak-device-alert--warn';
  }

  function renderDeviceAlerts(alerts) {
    var el = ensureDeviceList();
    if (!el) return;
    el.innerHTML = '';
    if (!alerts || !alerts.length) {
      el.innerHTML = '<p class="atak-health-muted">Aucun incident appareil.</p>';
      return;
    }
    alerts.slice(0, 12).forEach(function (a) {
      var row = document.createElement('div');
      row.className = 'atak-device-alert ' + severityClass(a.severity || 'warn');
      var title = document.createElement('strong');
      title.textContent = a.title || 'Alerte appareil';
      var msg = document.createElement('span');
      msg.textContent = a.message || '';
      row.appendChild(title);
      row.appendChild(msg);
      el.appendChild(row);

      var key = (a.code || '') + '|' + (a.call_sign || '') + '|' + (a.terminal_uid || '');
      if (!lastAlertKeys[key]) {
        lastAlertKeys[key] = true;
        if (window.AtakSounds && typeof window.AtakSounds.playAlert === 'function') {
          try { window.AtakSounds.playAlert('warn'); } catch (e) {}
        } else if (typeof window.atakShowNotification === 'function') {
          try { window.atakShowNotification(a.title || 'Alerte appareil', a.message || ''); } catch (e2) {}
        }
      }
    });
  }

  function poll() {
    var base = apiBase();
    var url = (base || '') + '/api/atak/device-alerts';
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data) return;
        renderBanner(data.intel_view || {});
        renderDeviceAlerts(data.alerts || []);
        window.ATAK_INTEL_VIEW = data.intel_view || null;
      })
      .catch(function () {});
  }

  function boot() {
    ensureBanner();
    poll();
    setInterval(poll, POLL_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.AtakIntelView = { poll: poll, renderBanner: renderBanner };
})();
