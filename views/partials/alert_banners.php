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

  var labels = {
    discount: 'Promo', novelty: 'Nouveau', urgent: 'Urgent', info: 'Info',
    notice: 'Consigne', event: 'Événement', maintenance: 'Maintenance',
    star: 'Annonce', tag: 'Offre', alert: 'Attention', megaphone: 'Annonce',
    calendar: 'Agenda', wrench: 'Maintenance', shield: 'Sécurité', flag: 'Signal'
  };

  var icons = {
    info: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    discount: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
    novelty: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>',
    urgent: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
    notice: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>',
    event: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
    maintenance: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    star: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>',
    tag: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
    alert: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
    megaphone: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>',
    calendar: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
    wrench: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    shield: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
    flag: '<svg class="alert-banner-icon h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>'
  };
  icons.tag = icons.discount;
  icons.star = icons.novelty;
  icons.alert = icons.urgent;
  icons.megaphone = icons.notice;
  icons.calendar = icons.event;
  icons.wrench = icons.maintenance;

  var badgeCls = {
    info: 'alert-banner-badge alert-banner-badge--info',
    discount: 'alert-banner-badge alert-banner-badge--discount',
    novelty: 'alert-banner-badge alert-banner-badge--novelty',
    urgent: 'alert-banner-badge alert-banner-badge--urgent',
    notice: 'alert-banner-badge alert-banner-badge--info',
    event: 'alert-banner-badge alert-banner-badge--novelty',
    maintenance: 'alert-banner-badge alert-banner-badge--discount'
  };

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function pickIcon(a) {
    var key = a.icon_key || a.kind || 'info';
    return icons[key] || icons[a.kind] || icons.info;
  }

  function build() {
    root.innerHTML = '';
    alerts.forEach(function(a) {
      var canDismiss = a.dismissible !== false && a.dismissible !== 0;
      if (canDismiss && isDismissed(a)) return;
      var kind = a.kind || 'info';
      var accent = a.accent_color || '';
      var bar = document.createElement('div');
      bar.className = 'alert-banner-item alert-banner--' + kind + ' border-b px-0 md:px-0';
      bar.setAttribute('role', 'status');
      bar.setAttribute('data-alert-scope', a.scope);
      bar.setAttribute('data-alert-id', String(a.id));
      if (!canDismiss) bar.setAttribute('data-alert-locked', '1');
      if (accent) {
        bar.style.borderLeft = '4px solid ' + accent;
        bar.style.setProperty('--alert-accent', accent);
      }

      if (a.banner_url) {
        var banner = document.createElement('div');
        banner.className = 'alert-banner-media';
        banner.style.backgroundImage = 'url("' + String(a.banner_url).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '")';
        bar.appendChild(banner);
      }

      var pad = document.createElement('div');
      pad.className = 'px-4 py-3.5 md:py-4';

      var inner = document.createElement('div');
      inner.className = 'relative z-[1] max-w-[1800px] mx-auto flex flex-wrap items-start gap-3 md:gap-4';

      if (a.image_url) {
        var thumb = document.createElement('img');
        thumb.src = a.image_url;
        thumb.alt = '';
        thumb.className = 'alert-banner-thumb h-14 w-14 shrink-0 rounded-xl object-cover border border-black/10 shadow-sm';
        inner.appendChild(thumb);
      }

      var iconWrap = document.createElement('div');
      iconWrap.className = 'alert-banner-icon-wrap mt-0.5 flex shrink-0';
      if (accent) iconWrap.style.color = accent;
      iconWrap.innerHTML = pickIcon(a);

      var badge = document.createElement('span');
      badge.className = 'shrink-0 ' + (badgeCls[kind] || badgeCls.info);
      badge.textContent = labels[kind] || labels.info;
      if (accent) {
        badge.style.borderColor = accent;
        badge.style.color = accent;
      }

      var metaRow = document.createElement('div');
      metaRow.className = 'flex flex-wrap items-center gap-2 shrink-0';
      metaRow.appendChild(badge);
      if (a.scope === 'platform') {
        var verified = document.createElement('span');
        verified.className = 'inline-flex items-center gap-1 rounded-full border border-emerald-400/35 bg-emerald-500/15 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-300';
        verified.title = 'Annonce officielle du site Athena';
        verified.innerHTML = '<svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg><span>Site vérifié</span>';
        metaRow.appendChild(verified);
      }

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
        if (accent) {
          link.style.background = accent;
          link.style.borderColor = accent;
          link.style.color = '#fff';
        }
        actions.appendChild(link);
      }

      inner.appendChild(iconWrap);
      inner.appendChild(metaRow);
      inner.appendChild(text);
      inner.appendChild(actions);

      if (canDismiss) {
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
        inner.appendChild(closeBtn);
      }

      pad.appendChild(inner);
      bar.appendChild(pad);
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
      '.alert-banner-media{height:7rem;background-size:cover;background-position:center;border-bottom:1px solid rgba(15,23,42,0.08)}',
      '.alert-banner-thumb{background:#f1f5f9}',
      '@media (prefers-reduced-motion:reduce){.alert-banner-item,.alert-banner-item--out{animation:none!important}.alert-banner--discount::after,.alert-banner--info::before,.alert-banner--novelty::before{animation:none!important}}',

      /* Info — thème sombre / indigo (aligné mini-banners) */
      '.alert-banner--info{position:relative;overflow:hidden;border-left:4px solid #6366f1;background:linear-gradient(110deg,#0c0c0e 0%,#121218 55%,#0a0a0c 100%);color:#e4e4e7;box-shadow:inset 0 1px 0 rgba(255,255,255,0.04),0 10px 40px -18px rgba(0,0,0,0.55)}',
      '.alert-banner--info .alert-banner-icon{color:#818cf8}',
      '.alert-banner--info::before{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(105deg,rgba(99,102,241,0.12) 0%,transparent 42%,rgba(99,102,241,0.06) 100%)}',
      '.alert-banner--info .alert-banner-title{color:#f4f4f5}',
      '.alert-banner--info .alert-banner-body{color:#a1a1aa}',
      '.alert-banner-badge--info{background:rgba(99,102,241,0.15);color:#818cf8;border:1px solid rgba(99,102,241,0.35);box-shadow:none}',

      /* Promo / discount — ambre sombre */
      '.alert-banner--discount{position:relative;overflow:hidden;border-left:4px solid #f59e0b;background:linear-gradient(115deg,#1c1408 0%,#0c0c0e 55%,#0a0a0c 100%);color:#ffedd5;box-shadow:inset 0 1px 0 rgba(255,255,255,0.04),0 12px 36px -14px rgba(0,0,0,0.55)}',
      '.alert-banner--discount::after{content:"";position:absolute;top:-50%;left:0;width:55%;height:200%;background:linear-gradient(90deg,transparent,rgba(245,158,11,0.08),transparent);animation:promoSweep 4.5s ease-in-out infinite;pointer-events:none}',
      '.alert-banner--discount .alert-banner-icon{color:#fbbf24}',
      '.alert-banner--discount .alert-banner-title{color:#fef3c7}',
      '.alert-banner--discount .alert-banner-body{color:#d6d3d1}',
      '.alert-banner-badge--discount{background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.35);box-shadow:none}',
      '.alert-banner--discount .alert-banner-code{border-color:rgba(245,158,11,0.4);background:rgba(0,0,0,0.35);color:#fde68a}',
      '.alert-banner--discount .alert-banner-cta{background:linear-gradient(180deg,#d97706,#b45309);color:#fff;border:1px solid rgba(180,83,9,0.35)}',

      /* Nouveauté — émeraude sombre */
      '.alert-banner--novelty{position:relative;overflow:hidden;border-left:4px solid #00a870;background:linear-gradient(118deg,#06140f 0%,#0c0c0e 50%,#0a0a0c 100%);color:#d1fae5;box-shadow:inset 0 1px 0 rgba(255,255,255,0.04),0 10px 32px -16px rgba(0,0,0,0.55)}',
      '.alert-banner--novelty::before{content:"";position:absolute;inset:0;pointer-events:none;background:radial-gradient(ellipse 80% 50% at 20% 0%,rgba(0,168,112,0.14),transparent 55%)}',
      '.alert-banner--novelty .alert-banner-icon{color:#00c887}',
      '.alert-banner--novelty .alert-banner-title{color:#ecfdf5}',
      '.alert-banner--novelty .alert-banner-body{color:#a7f3d0}',
      '.alert-banner-badge--novelty{background:rgba(0,168,112,0.15);color:#00c887;border:1px solid rgba(0,168,112,0.35);box-shadow:none}',
      '.alert-banner--novelty .alert-banner-cta{background:linear-gradient(180deg,#00a870,#047857);color:#fff}',

      /* Urgent */
      '.alert-banner--urgent{position:relative;overflow:hidden;border-left:4px solid #ef4444;background:linear-gradient(115deg,#1a0808 0%,#0c0c0e 50%,#0a0a0c 100%);color:#fecaca;box-shadow:inset 0 1px 0 rgba(255,255,255,0.04),0 12px 40px -12px rgba(0,0,0,0.55)}',
      '.alert-banner--urgent .alert-banner-icon{color:#f87171}',
      '.alert-banner--urgent .alert-banner-title{color:#fee2e2}',
      '.alert-banner--urgent .alert-banner-body{color:#fca5a5}',
      '.alert-banner-badge--urgent{background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.35);box-shadow:none}',
      '.alert-banner--urgent .alert-banner-cta{background:linear-gradient(180deg,#ef4444,#be123c);color:#fff}',

      '.alert-banner-badge{display:inline-flex;align-items:center;font-size:9px;font-weight:900;letter-spacing:0.22em;text-transform:uppercase;padding:0.35rem 0.65rem;border-radius:0.5rem}',
      '.alert-banner--info .alert-banner-cta{background:linear-gradient(180deg,#4f46e5,#3730a3);color:#fff;border:1px solid rgba(99,102,241,0.35)}'
    ].join('');
    document.head.appendChild(st);
  }
  build();
})();
</script>
