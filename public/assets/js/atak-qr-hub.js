/* ATAK — QR hub: panneau détaché dans le téléphone. */
(function () {
  'use strict';

  var selected = 'c2';
  var labels = { c2: 'C2', chat: 'Tchat', orders: 'Ordres', explosives: 'Explosifs' };
  var modes = { c2: 'C2', chat: 'TCHAT', orders: 'ORDRES', explosives: 'EXPLOSIFS' };

  function paintPhone(root, label, mode) {
    if (!root) return;
    root.querySelectorAll('[data-atak-qr-phone-label]').forEach(function (el) {
      el.textContent = label || 'Module';
    });
    root.querySelectorAll('[data-atak-qr-phone-mode]').forEach(function (el) {
      el.textContent = mode || 'MODULE';
    });
  }

  function init() {
    var generate = document.getElementById('atak-qr-generate');
    if (!generate) return;
    var result = document.getElementById('atak-qr-result');
    var image = document.getElementById('atak-qr-image');
    var error = document.getElementById('atak-qr-error');
    var code = document.getElementById('atak-qr-code');
    var open = document.getElementById('atak-qr-open');
    var expiry = document.getElementById('atak-qr-expiry');
    var title = document.getElementById('atak-qr-result-title');

    document.querySelectorAll('[data-qr-destination]').forEach(function (button) {
      button.addEventListener('click', function () {
        selected = button.getAttribute('data-qr-destination') || 'c2';
        document.querySelectorAll('[data-qr-destination]').forEach(function (item) {
          var active = item === button;
          item.classList.toggle('is-active', active);
          item.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        generate.textContent = 'Générer le QR ' + labels[selected];
        paintPhone(result, labels[selected], modes[selected]);
      });
    });

    generate.addEventListener('click', function () {
      generate.disabled = true;
      generate.textContent = 'Génération…';
      error.hidden = true;
      var endpoint = generate.getAttribute('data-qr-api') || '/api/atak/phone-pairing';
      fetch(endpoint + (endpoint.indexOf('?') >= 0 ? '&' : '?') + 'destination=' + encodeURIComponent(selected), {
        credentials: 'include', headers: { Accept: 'application/json' }
      }).then(function (response) {
        return response.json().then(function (body) { return { ok: response.ok, body: body }; });
      }).then(function (response) {
        if (!response.ok || !response.body || !response.body.token) {
          throw new Error((response.body && response.body.message) || 'Impossible de créer cette liaison.');
        }
        var body = response.body;
        image.src = body.qr_image_data_uri || body.qr_image_url || '';
        image.hidden = !image.src;
        code.textContent = body.code || '————';
        open.href = body.pair_url || '#';
        title.textContent = 'Accès ' + labels[selected];
        expiry.textContent = body.expires_at ? 'Expiration : ' + new Date(body.expires_at).toLocaleString('fr-FR') : 'Liaison temporaire';
        paintPhone(result, labels[selected], modes[selected]);
        result.hidden = false;
      }).catch(function (err) {
        error.textContent = err.message || 'Liaison indisponible. Réessayez.';
        error.hidden = false;
      }).finally(function () {
        generate.disabled = false;
        generate.textContent = 'Nouveau QR ' + labels[selected];
      });
    });

    document.getElementById('atak-qr-copy').addEventListener('click', function (event) {
      if (!navigator.clipboard || !code.textContent) return;
      navigator.clipboard.writeText(code.textContent).then(function () {
        event.currentTarget.textContent = 'Copié';
        window.setTimeout(function () { event.currentTarget.textContent = 'Copier'; }, 1400);
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
