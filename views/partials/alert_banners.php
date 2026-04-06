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
<div id="alert-banners-root" class="alert-banners-stack relative z-[95]" role="region" aria-label="Annonces"
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

  var icons = {
    info: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    discount: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    novelty: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>',
    urgent: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>'
  };

  var badgeCls = {
    info: 'alert-banner-badge alert-banner-badge--info',
    discount: 'alert-banner-badge alert-banner-badge--discount',
    novelty: 'alert-banner-badge alert-banner-badge--novelty',
    urgent: 'alert-banner-badge alert-banner-badge--urgent'
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
      var kind = a.kind || 'info';
      var bar = document.createElement('div');
      bar.className = 'alert-banner-item alert-banner--' + kind + ' border-b px-4 py-3.5 md:py-4';
      bar.setAttribute('role', 'status');
      bar.setAttribute('data-alert-scope', a.scope);
      bar.setAttribute('data-alert-id', String(a.id));

      var inner = document.createElement('div');
      inner.className = 'relative z-[1] max-w-[1800px] mx-auto flex flex-wrap items-start gap-3 md:gap-4';

      var iconWrap = document.createElement('div');
      iconWrap.className = 'alert-banner-icon-wrap mt-0.5 flex shrink-0';
      iconWrap.innerHTML = icons[kind] || icons.info;

      var badge = document.createElement('span');
      badge.className = 'shrink-0 ' + (badgeCls[kind] || badgeCls.info);
      badge.textContent = labels[kind] || labels.info;

      var text = document.createElement('div');
      text.className = 'flex-1 min-w-0 text-sm';
      var title = document.createElement('p');
      title.className = 'alert-banner-title font-black tracking-tight';
      title.textContent = a.title;
      text.appendChild(title);
      if (a.body) {
        var p = document.createElement('p');
        p.className = 'alert-banner-body mt-1 leading-relaxed';
        p.textContent = a.body;
        text.appendChild(p);
      }

      var actions = document.createElement('div');
      actions.className = 'flex flex-wrap items-center gap-2 shrink-0';

      if (a.coupon_code) {
        var codeBtn = document.createElement('button');
        codeBtn.type = 'button';
        codeBtn.className = 'alert-banner-code text-xs font-mono font-bold px-3 py-1.5 rounded-xl border shadow-sm transition-transform hover:scale-[1.02] active:scale-[0.98]';
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
        link.className = 'alert-banner-cta inline-flex items-center text-xs font-black uppercase tracking-wider px-4 py-2 rounded-xl shadow-md transition-transform hover:scale-[1.03] active:scale-[0.98]';
        link.textContent = a.cta_label;
        actions.appendChild(link);
      }

      var closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'alert-banner-close text-current opacity-70 hover:opacity-100 p-1.5 rounded-xl transition hover:bg-black/5';
      closeBtn.setAttribute('aria-label', 'Fermer cette annonce');
      closeBtn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
      closeBtn.addEventListener('click', function() {
        bar.classList.add('alert-banner-item--out');
        window.setTimeout(function() {
          setDismissed(a);
          bar.remove();
        }, 220);
        if (loggedIn && dismissUrl && csrf) {
          var fd = new FormData();
          fd.append('_csrf_token', csrf);
          fd.append('scope', a.scope);
          fd.append('alert_id', String(a.id));
          fetch(dismissUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function() {});
        }
      });

      inner.appendChild(iconWrap);
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
    st.textContent = [
      '@keyframes alertFadeSlide{from{opacity:0;transform:translateY(-10px) scale(0.995)}to{opacity:1;transform:translateY(0) scale(1)}}',
      '@keyframes alertFadeOut{to{opacity:0;transform:translateY(-6px)}}',
      '@keyframes alertShimmer{0%,100%{opacity:0.35}50%{opacity:0.85}}',
      '@keyframes promoSweep{0%{transform:translateX(-120%) skewX(-12deg)}100%{transform:translateX(220%) skewX(-12deg)}}',
      '@keyframes noveltyGlow{0%,100%{filter:brightness(1)}50%{filter:brightness(1.06)}}',
      '.alert-banner-item{animation:alertFadeSlide 0.45s cubic-bezier(0.22,1,0.36,1) both}',
      '.alert-banner-item--out{animation:alertFadeOut 0.22s ease forwards}',
      '@media (prefers-reduced-motion:reduce){.alert-banner-item,.alert-banner-item--out{animation:none!important}.alert-banner--discount::after,.alert-banner--info::before,.alert-banner--novelty::before{animation:none!important}}',

      /* Info — fort contraste, bordure accent, halo */
      '.alert-banner--info{position:relative;overflow:hidden;border-left:4px solid #0284c7;background:linear-gradient(110deg,#e0f2fe 0%,#f8fafc 38%,#ffffff 100%);color:#0f172a;box-shadow:inset 0 1px 0 rgba(255,255,255,0.9),0 10px 40px -18px rgba(2,132,199,0.35)}',
      '.alert-banner--info .alert-banner-icon{color:#0369a1}',
      '.alert-banner--info::before{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(105deg,rgba(14,165,233,0.12) 0%,transparent 42%,rgba(56,189,248,0.08) 100%);animation:alertShimmer 5s ease-in-out infinite}',
      '.alert-banner--info .alert-banner-title{color:#0c4a6e}',
      '.alert-banner--info .alert-banner-body{color:#334155}',
      '.alert-banner-badge--info{background:linear-gradient(180deg,#fff,#f0f9ff);color:#0369a1;border:1px solid rgba(14,165,233,0.45);box-shadow:0 1px 2px rgba(2,132,199,0.12)}',

      /* Promo / discount — chaud + reflet animé */
      '.alert-banner--discount{position:relative;overflow:hidden;border-left:4px solid #d97706;background:linear-gradient(115deg,#fffbeb 0%,#ffedd5 35%,#fff7ed 100%);color:#431407;box-shadow:inset 0 1px 0 rgba(255,255,255,0.85),0 12px 36px -14px rgba(217,119,6,0.45)}',
      '.alert-banner--discount::after{content:"";position:absolute;top:-50%;left:0;width:55%;height:200%;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.55),transparent);animation:promoSweep 4.5s ease-in-out infinite;pointer-events:none}',
      '.alert-banner--discount .alert-banner-icon{color:#b45309}',
      '.alert-banner--discount .alert-banner-title{color:#7c2d12}',
      '.alert-banner--discount .alert-banner-body{color:#78350f}',
      '.alert-banner-badge--discount{background:linear-gradient(180deg,#fef3c7,#fde68a);color:#92400e;border:1px solid rgba(217,119,6,0.5);box-shadow:0 2px 6px rgba(180,83,9,0.2)}',
      '.alert-banner--discount .alert-banner-code{border-color:rgba(217,119,6,0.45);background:rgba(255,255,255,0.95);color:#92400e}',
      '.alert-banner--discount .alert-banner-cta{background:linear-gradient(180deg,#ea580c,#c2410c);color:#fff;border:1px solid rgba(124,45,18,0.25)}',

      /* Nouveauté — émeraude, léger scintillement */
      '.alert-banner--novelty{position:relative;overflow:hidden;border-left:4px solid #059669;background:linear-gradient(118deg,#ecfdf5 0%,#f0fdf4 40%,#f8fafc 100%);color:#064e3b;box-shadow:inset 0 1px 0 rgba(255,255,255,0.9),0 10px 32px -16px rgba(5,150,105,0.35)}',
      '.alert-banner--novelty::before{content:"";position:absolute;inset:0;pointer-events:none;background:radial-gradient(ellipse 80% 50% at 20% 0%,rgba(16,185,129,0.15),transparent 55%);animation:noveltyGlow 4s ease-in-out infinite}',
      '.alert-banner--novelty .alert-banner-icon{color:#047857}',
      '.alert-banner--novelty .alert-banner-title{color:#065f46}',
      '.alert-banner--novelty .alert-banner-body{color:#166534}',
      '.alert-banner-badge--novelty{background:linear-gradient(180deg,#d1fae5,#a7f3d0);color:#065f46;border:1px solid rgba(5,150,105,0.4);box-shadow:0 2px 6px rgba(5,150,105,0.15)}',
      '.alert-banner--novelty .alert-banner-cta{background:linear-gradient(180deg,#059669,#047857);color:#fff}',

      /* Urgent */
      '.alert-banner--urgent{position:relative;overflow:hidden;border-left:4px solid #e11d48;background:linear-gradient(115deg,#fff1f2 0%,#ffe4e6 45%,#fff 100%);color:#881337;box-shadow:inset 0 1px 0 rgba(255,255,255,0.85),0 12px 40px -12px rgba(225,29,72,0.4)}',
      '.alert-banner--urgent .alert-banner-icon{color:#be123c}',
      '.alert-banner--urgent .alert-banner-title{color:#9f1239}',
      '.alert-banner--urgent .alert-banner-body{color:#9f1239}',
      '.alert-banner-badge--urgent{background:linear-gradient(180deg,#fecdd3,#fda4af);color:#9f1239;border:1px solid rgba(225,29,72,0.45);box-shadow:0 2px 6px rgba(190,18,60,0.2)}',
      '.alert-banner--urgent .alert-banner-cta{background:linear-gradient(180deg,#e11d48,#be123c);color:#fff}',

      '.alert-banner-badge{display:inline-flex;align-items:center;font-size:9px;font-weight:900;letter-spacing:0.22em;text-transform:uppercase;padding:0.35rem 0.65rem;border-radius:0.5rem}',
      '.alert-banner--info .alert-banner-cta{background:linear-gradient(180deg,#0f172a,#1e293b);color:#fff;border:1px solid rgba(15,23,42,0.2)}'
    ].join('');
    document.head.appendChild(st);
  }
  build();
})();
</script>
