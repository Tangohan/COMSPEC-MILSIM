<?php
declare(strict_types=1);

use App\Core\Container;
use App\Services\Alerts\AlertPresentationService;

$alertBanners = $alertBanners ?? null;
if (!empty($skipGlobalAlertBanners)) {
    return;
}
if ($alertBanners === null) {
    try {
        $alertBanners = Container::get(AlertPresentationService::class)->forCurrentRequest();
    } catch (\Throwable) {
        $alertBanners = [];
    }
}
if (is_array($alertBanners)) {
    $alertBanners = array_values(array_filter(
        $alertBanners,
        static function (array $a): bool {
            $style = (string) ($a['display_style'] ?? 'classic');

            return \App\Support\AlertDisplayStyle::isClassicStyle($style);
        }
    ));
}
if (! is_array($alertBanners) || $alertBanners === []) {
    return;
}

$alertUserLoggedIn = (bool) \App\Core\Session::get('user_id');
$alertCsrf = \App\Core\Csrf::token();
$alertDismissUrl = url('api/alerts/dismiss');
$json = json_encode($alertBanners, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
if ($json === false) {
    return;
}
?>
<?php
if (empty($GLOBALS['__dsfr_service_css'])) {
    $GLOBALS['__dsfr_service_css'] = true;
    echo '<link href="' . htmlspecialchars(asset_url('assets/css/dsfr-service.css'), ENT_QUOTES, 'UTF-8') . '" rel="stylesheet">';
}
?>
<div id="alert-banners-root"
     class="ds-alert-banners relative z-[95]"
     role="region"
     aria-label="Annonces"
     data-alerts="<?= htmlspecialchars($json, ENT_QUOTES, 'UTF-8') ?>"
     data-logged-in="<?= $alertUserLoggedIn ? '1' : '0' ?>"
     data-csrf="<?= htmlspecialchars($alertCsrf, ENT_QUOTES, 'UTF-8') ?>"
     data-dismiss-url="<?= htmlspecialchars($alertDismissUrl, ENT_QUOTES, 'UTF-8') ?>">
</div>
<script>
(function() {
  var root = document.getElementById('alert-banners-root');
  if (!root) return;
  var raw = root.getAttribute('data-alerts');
  var alerts = [];
  try { alerts = JSON.parse(raw || '[]'); } catch (e) { return; }
  var loggedIn = root.getAttribute('data-logged-in') === '1';
  var csrf = root.getAttribute('data-csrf') || '';
  var dismissUrl = root.getAttribute('data-dismiss-url') || '';
  var LS = 'athena_alert_dismissed_';

  function storageKey(a) { return LS + a.scope + '_' + a.id; }
  function isDismissed(a) {
    try { return localStorage.getItem(storageKey(a)) === '1'; } catch (e) { return false; }
  }
  function setDismissed(a) {
    try { localStorage.setItem(storageKey(a), '1'); } catch (e) {}
  }

  var labels = {
    discount: 'Promotion', novelty: 'Nouveau', urgent: 'Urgent', info: 'Information',
    notice: 'Annonce', event: 'Événement', maintenance: 'Maintenance',
    training: 'Formation', recruitment: 'Recrutement', security: 'Sécurité'
  };
  var toneOf = {
    urgent: 'error', security: 'error', discount: 'warning', maintenance: 'warning',
    novelty: 'info', event: 'info', notice: 'info', training: 'info',
    recruitment: 'info', info: 'info'
  };

  function sendDismiss(a) {
    if (!loggedIn || !dismissUrl || !csrf) return;
    var fd = new FormData();
    fd.append('_csrf_token', csrf);
    fd.append('scope', a.scope);
    fd.append('alert_id', String(a.id));
    fetch(dismissUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function() {});
  }

  var visible = alerts.filter(function (a) {
    var canDismiss = a.dismissible !== false && a.dismissible !== 0;
    return !(canDismiss && isDismissed(a));
  });
  if (!visible.length) {
    root.remove();
    return;
  }

  visible.forEach(function (a) {
    var kind = a.kind || 'info';
    var tone = toneOf[kind] || 'info';
    var canDismiss = a.dismissible !== false && a.dismissible !== 0;
    var el = document.createElement('div');
    el.className = 'ds-alert ds-alert--' + tone;
    el.setAttribute('role', kind === 'urgent' ? 'alert' : 'status');

    var title = document.createElement('p');
    title.className = 'ds-alert__title';
    title.textContent = a.title || (labels[kind] || 'Information');
    el.appendChild(title);

    if (a.body) {
      var body = document.createElement('p');
      body.textContent = a.body;
      el.appendChild(body);
    }

    if (a.coupon_code) {
      var codeBtn = document.createElement('button');
      codeBtn.type = 'button';
      codeBtn.className = 'ds-alert__code';
      codeBtn.textContent = a.coupon_code;
      codeBtn.title = 'Copier le code';
      codeBtn.addEventListener('click', function () {
        navigator.clipboard.writeText(a.coupon_code).then(function () {
          codeBtn.textContent = 'Copié';
          setTimeout(function () { codeBtn.textContent = a.coupon_code; }, 1600);
        }).catch(function () {});
      });
      el.appendChild(codeBtn);
    }

    if (a.cta_url && a.cta_label) {
      var link = document.createElement('a');
      link.href = a.cta_url;
      link.className = 'ds-alert__cta';
      link.textContent = a.cta_label;
      el.appendChild(link);
    }

    if (canDismiss) {
      var closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'ds-alert__close';
      closeBtn.setAttribute('aria-label', 'Masquer cette annonce');
      closeBtn.innerHTML = '&times;';
      closeBtn.addEventListener('click', function () {
        setDismissed(a);
        el.remove();
        sendDismiss(a);
        if (!root.querySelector('.ds-alert')) root.remove();
      });
      el.appendChild(closeBtn);
    }

    root.appendChild(el);
  });
})();
</script>
