(function () {
  'use strict';

  var current = typeof window.APP_VERSION === 'string' ? window.APP_VERSION : '';
  if (!current) {
    return;
  }

  var endpoint = (typeof window.APP_BASE_URL === 'string' ? window.APP_BASE_URL.replace(/\/$/, '') : '')
    + '/api/system/version';
  var bannerId = 'athena-app-version-banner';
  var dismissedKey = 'athena_dismissed_version';

  function showBanner(version) {
    if (document.getElementById(bannerId)) {
      return;
    }
    if (sessionStorage.getItem(dismissedKey) === version) {
      return;
    }
    var el = document.createElement('div');
    el.id = bannerId;
    el.setAttribute('role', 'status');
    el.style.cssText = [
      'position:fixed',
      'z-index:9999',
      'left:1rem',
      'right:1rem',
      'bottom:1rem',
      'max-width:28rem',
      'margin:0 auto',
      'padding:0.875rem 1rem',
      'border-radius:0.75rem',
      'background:#0f172a',
      'color:#f8fafc',
      'box-shadow:0 10px 40px rgba(15,23,42,0.35)',
      'font:600 0.875rem/1.4 system-ui,sans-serif',
      'display:flex',
      'gap:0.75rem',
      'align-items:center',
      'justify-content:space-between'
    ].join(';');
    el.innerHTML = '<span>Une nouvelle version de la plateforme est disponible. Actualisez la page pour charger les modifications.</span>';
    var actions = document.createElement('div');
    actions.style.cssText = 'display:flex;gap:0.5rem;flex-shrink:0';
    var reload = document.createElement('button');
    reload.type = 'button';
    reload.textContent = 'Actualiser';
    reload.style.cssText = 'border:0;border-radius:0.5rem;background:#f59e0b;color:#0f172a;font:700 0.75rem/1 system-ui,sans-serif;padding:0.5rem 0.75rem;cursor:pointer';
    reload.addEventListener('click', function () {
      window.location.reload();
    });
    var dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.textContent = 'Plus tard';
    dismiss.style.cssText = 'border:1px solid #475569;border-radius:0.5rem;background:transparent;color:#e2e8f0;font:600 0.75rem/1 system-ui,sans-serif;padding:0.5rem 0.75rem;cursor:pointer';
    dismiss.addEventListener('click', function () {
      sessionStorage.setItem(dismissedKey, version);
      el.remove();
    });
    actions.appendChild(reload);
    actions.appendChild(dismiss);
    el.appendChild(actions);
    document.body.appendChild(el);
  }

  function check() {
    fetch(endpoint, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (payload) {
        var data = payload && payload.data ? payload.data : payload;
        var version = data && typeof data.version === 'string' ? data.version : '';
        if (version && version !== current) {
          showBanner(version);
        }
      })
      .catch(function () { /* silencieux */ });
  }

  check();
  setInterval(check, 60000);
})();
