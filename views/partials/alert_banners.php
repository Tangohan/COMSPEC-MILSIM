<?php
declare(strict_types=1);

use App\Core\Container;
use App\Services\Alerts\AlertPresentationService;

$alertBanners = $alertBanners ?? null;
if ($alertBanners === null) {
    try {
        $alertBanners = Container::get(AlertPresentationService::class)->forCurrentRequest();
    } catch (\Throwable) {
        $alertBanners = [];
    }
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
<div id="alert-banners-root" class="alert-banners-stack" role="region" aria-label="Annonces"
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

  var labels = { discount: 'Promo', novelty: 'Nouveau', urgent: 'Urgent', info: 'Info' };
  var wrapCls = {
    discount: 'border-amber-400/50 bg-gradient-to-r from-amber-50 to-orange-50/90 text-amber-950',
    novelty: 'border-emerald-400/50 bg-gradient-to-r from-emerald-50 to-slate-50 text-slate-900',
    urgent: 'border-rose-400/60 bg-gradient-to-r from-rose-50 to-red-50/80 text-rose-950',
    info: 'border-slate-300/60 bg-gradient-to-r from-slate-50 to-white text-slate-900'
  };

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function build() {
    root.innerHTML = '';
    alerts.forEach(function(a) {
      if (isDismissed(a)) return;
      var bar = document.createElement('div');
      bar.className = 'alert-banner-item border-b px-4 py-3 ' + (wrapCls[a.kind] || wrapCls.info);
      bar.setAttribute('role', 'status');
      bar.setAttribute('data-alert-scope', a.scope);
      bar.setAttribute('data-alert-id', String(a.id));

      var inner = document.createElement('div');
      inner.className = 'max-w-[1800px] mx-auto flex flex-wrap items-start gap-3 md:gap-4';

      var badge = document.createElement('span');
      badge.className = 'shrink-0 text-[8px] font-black uppercase tracking-[0.35em] px-2 py-0.5 rounded bg-white/60 border border-black/5';
      badge.textContent = labels[a.kind] || labels.info;

      var text = document.createElement('div');
      text.className = 'flex-1 min-w-0 text-sm';
      var title = document.createElement('p');
      title.className = 'font-black text-slate-900';
      title.textContent = a.title;
      text.appendChild(title);
      if (a.body) {
        var p = document.createElement('p');
        p.className = 'mt-1 text-slate-700/90 leading-relaxed';
        p.textContent = a.body;
        text.appendChild(p);
      }

      var actions = document.createElement('div');
      actions.className = 'flex flex-wrap items-center gap-2 shrink-0';

      if (a.coupon_code) {
        var codeBtn = document.createElement('button');
        codeBtn.type = 'button';
        codeBtn.className = 'text-xs font-mono font-bold px-2 py-1 rounded border border-amber-300 bg-white/80 hover:bg-white';
        codeBtn.textContent = a.coupon_code;
        codeBtn.title = 'Copier le code';
        codeBtn.addEventListener('click', function() {
          navigator.clipboard.writeText(a.coupon_code).then(function() {
            codeBtn.textContent = 'Copié ✓';
            setTimeout(function() { codeBtn.textContent = a.coupon_code; }, 1600);
          }).catch(function() {});
        });
        actions.appendChild(codeBtn);
      }

      if (a.cta_url && a.cta_label) {
        var link = document.createElement('a');
        link.href = a.cta_url;
        link.className = 'inline-flex items-center text-xs font-black uppercase tracking-wider px-3 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-emerald-600 transition-colors';
        link.textContent = a.cta_label;
        actions.appendChild(link);
      }

      var closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'text-slate-500 hover:text-slate-900 p-1 rounded';
      closeBtn.setAttribute('aria-label', 'Fermer cette annonce');
      closeBtn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
      closeBtn.addEventListener('click', function() {
        setDismissed(a);
        bar.remove();
        if (loggedIn && dismissUrl && csrf) {
          var fd = new FormData();
          fd.append('_csrf_token', csrf);
          fd.append('scope', a.scope);
          fd.append('alert_id', String(a.id));
          fetch(dismissUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function() {});
        }
      });

      inner.appendChild(badge);
      inner.appendChild(text);
      inner.appendChild(actions);
      inner.appendChild(closeBtn);
      bar.appendChild(inner);
      root.appendChild(bar);
    });
  }

  if (!document.getElementById('alert-banners-keyframes')) {
    var st = document.createElement('style');
    st.id = 'alert-banners-keyframes';
    st.textContent = '@keyframes fadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}' +
      '.alert-banner-item{animation:fadeSlide 0.35s ease-out}' +
      '@media (prefers-reduced-motion:reduce){.alert-banner-item{animation:none}}';
    document.head.appendChild(st);
  }
  build();
})();
</script>
