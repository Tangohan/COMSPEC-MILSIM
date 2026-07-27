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

$hasUrgent = false;
foreach ($alertBanners as $a) {
    if (!is_array($a)) {
        continue;
    }
    if (strtolower(trim((string) ($a['kind'] ?? ''))) === 'urgent') {
        $hasUrgent = true;
        break;
    }
}
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/announce-tiles.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<?php
// z-[95] : sous header Athena (96) / portail (100) pour laisser passer les menus.
?>
<div id="alert-banners-root"
     class="alert-banners-stack alert-banners-stack--tiles relative z-[95]"
     role="region"
     aria-label="Annonces"
     data-alerts="<?= htmlspecialchars($json, ENT_QUOTES, 'UTF-8') ?>"
     data-logged-in="<?= $alertUserLoggedIn ? '1' : '0' ?>"
     data-csrf="<?= htmlspecialchars($alertCsrf, ENT_QUOTES, 'UTF-8') ?>"
     data-dismiss-url="<?= htmlspecialchars($alertDismissUrl, ENT_QUOTES, 'UTF-8') ?>"
     data-start-open="<?= $hasUrgent ? '1' : '0' ?>">
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
  var startOpen = root.getAttribute('data-start-open') === '1';
  var LS = 'athena_alert_dismissed_';
  var persistKey = 'athena_alert_stack_open';

  function storageKey(a) { return LS + a.scope + '_' + a.id; }
  function isDismissed(a) {
    try { return localStorage.getItem(storageKey(a)) === '1'; } catch (e) { return false; }
  }
  function setDismissed(a) {
    try { localStorage.setItem(storageKey(a), '1'); } catch (e) {}
  }

  var labels = {
    discount: 'Promotion', novelty: 'Nouveau', urgent: 'Urgent', info: 'Information',
    notice: 'Consigne', event: 'Événement', maintenance: 'Maintenance',
    training: 'Formation', recruitment: 'Recrutement', security: 'Sécurité',
    star: 'Annonce', tag: 'Offre', alert: 'Attention', megaphone: 'Annonce',
    calendar: 'Agenda', wrench: 'Maintenance', shield: 'Sécurité', flag: 'Signal'
  };

  var kindClass = {
    info: 'info', discount: 'discount', novelty: 'novelty', urgent: 'urgent',
    notice: 'notice', event: 'novelty', maintenance: 'discount',
    training: 'novelty', recruitment: 'info', security: 'urgent'
  };

  function setOpen(shell, open) {
    shell.classList.toggle('is-open', open);
    var btn = shell.querySelector('[data-alert-stack-toggle]');
    var panel = shell.querySelector('[data-alert-stack-panel]');
    var meta = shell.querySelector('[data-alert-stack-meta]');
    if (btn) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.setAttribute('aria-label', open ? 'Replier les messages' : 'Déplier les messages');
    }
    if (panel) {
      if (open) panel.removeAttribute('hidden');
      else panel.setAttribute('hidden', '');
    }
    if (meta) meta.textContent = open ? '−' : '+';
    try { localStorage.setItem(persistKey, open ? '1' : '0'); } catch (e) {}
  }

  function build() {
    root.innerHTML = '';
    var visible = [];
    alerts.forEach(function(a) {
      var canDismiss = a.dismissible !== false && a.dismissible !== 0;
      if (canDismiss && isDismissed(a)) return;
      visible.push(a);
    });
    if (!visible.length) {
      root.remove();
      return;
    }

    var shell = document.createElement('div');
    shell.className = 'alert-banners-shell dash-announce';
    shell.id = 'alert-banners-shell';

    var brief = document.createElement('div');
    brief.className = 'dash-announce__brief';

    var main = document.createElement('div');
    main.className = 'dash-announce__brief-main';

    var goto = document.createElement('div');
    goto.className = 'dash-announce__brief-goto dash-announce__brief-goto--static';

    var labelWrap = document.createElement('span');
    labelWrap.className = 'dash-announce__brief-label';
    var kicker = document.createElement('p');
    kicker.className = 'dash-announce__brief-kicker';
    kicker.textContent = 'Transmission';
    var title = document.createElement('h2');
    title.className = 'dash-announce__brief-title';
    title.id = 'alert-banners-title';
    title.textContent = 'Alertes & annonces';
    labelWrap.appendChild(kicker);
    labelWrap.appendChild(title);
    goto.appendChild(labelWrap);

    var aside = document.createElement('div');
    aside.className = 'dash-announce__brief-aside';
    var status = document.createElement('p');
    status.className = 'dash-announce__brief-status';
    status.textContent = visible.length === 1
      ? '1 message actif'
      : visible.length + ' messages actifs';
    aside.appendChild(status);

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'dash-announce__toggle';
    toggle.setAttribute('data-alert-stack-toggle', '');
    toggle.setAttribute('aria-controls', 'alert-banners-panel');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Déplier les messages');

    var meta = document.createElement('i');
    meta.className = 'dash-announce__meta';
    meta.setAttribute('data-alert-stack-meta', '');
    meta.setAttribute('aria-hidden', 'true');
    meta.textContent = '+';
    toggle.appendChild(meta);

    main.appendChild(goto);
    main.appendChild(aside);
    main.appendChild(toggle);
    brief.appendChild(main);
    shell.appendChild(brief);

    var panel = document.createElement('div');
    panel.className = 'dash-announce__panel';
    panel.id = 'alert-banners-panel';
    panel.setAttribute('data-alert-stack-panel', '');
    panel.hidden = true;

    var grid = document.createElement('div');
    grid.className = 'alert-banners-grid dash-announce__grid';

    visible.forEach(function(a) {
      var canDismiss = a.dismissible !== false && a.dismissible !== 0;
      var kind = a.kind || 'info';
      var tone = kindClass[kind] || 'info';
      var accent = a.accent_color || '';

      var tile = document.createElement('article');
      tile.className = 'dash-announce-tile dash-announce-tile--' + tone + ' alert-banner-item';
      tile.setAttribute('role', 'status');
      tile.setAttribute('data-alert-scope', a.scope);
      tile.setAttribute('data-alert-id', String(a.id));
      if (!canDismiss) tile.setAttribute('data-alert-locked', '1');
      if (accent) tile.style.setProperty('--announce-accent', accent);

      var visual = document.createElement('div');
      visual.className = 'dash-announce-tile__visual';
      visual.setAttribute('aria-hidden', 'true');
      if (a.banner_url) {
        visual.style.backgroundImage = 'url("' + String(a.banner_url).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '")';
        visual.style.backgroundSize = 'cover';
        visual.style.backgroundPosition = 'center';
      } else {
        var glyph = document.createElement('span');
        glyph.className = 'dash-announce-tile__glyph';
        visual.appendChild(glyph);
      }
      if (a.scope === 'platform') {
        var mark = document.createElement('span');
        mark.className = 'dash-announce-tile__verified-mark';
        mark.title = 'Annonce du portail';
        mark.innerHTML = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
        visual.appendChild(mark);
      }
      tile.appendChild(visual);

      var panelInner = document.createElement('div');
      panelInner.className = 'dash-announce-tile__panel';

      var metaRow = document.createElement('div');
      metaRow.className = 'dash-announce-tile__meta';
      var kindEl = document.createElement('p');
      kindEl.className = 'dash-announce-tile__kind';
      kindEl.textContent = labels[kind] || labels.info;
      if (accent) kindEl.style.color = accent;
      metaRow.appendChild(kindEl);
      if (a.scope === 'platform') {
        var verified = document.createElement('span');
        verified.className = 'dash-announce-tile__verified';
        verified.title = 'Annonce officielle du site';
        verified.innerHTML = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg><span>Site vérifié</span>';
        metaRow.appendChild(verified);
      }
      panelInner.appendChild(metaRow);

      var titleEl = document.createElement('p');
      titleEl.className = 'dash-announce-tile__title';
      titleEl.textContent = a.title;
      panelInner.appendChild(titleEl);

      if (a.body) {
        var body = document.createElement('p');
        body.className = 'dash-announce-tile__body';
        body.textContent = a.body;
        panelInner.appendChild(body);
      }

      var foot = document.createElement('div');
      foot.className = 'alert-banner-tile-foot';

      if (a.coupon_code) {
        var codeBtn = document.createElement('button');
        codeBtn.type = 'button';
        codeBtn.className = 'alert-banner-code';
        codeBtn.textContent = a.coupon_code;
        codeBtn.title = 'Copier le code';
        codeBtn.addEventListener('click', function() {
          navigator.clipboard.writeText(a.coupon_code).then(function() {
            codeBtn.textContent = 'Copié';
            setTimeout(function() { codeBtn.textContent = a.coupon_code; }, 1600);
          }).catch(function() {});
        });
        foot.appendChild(codeBtn);
      }

      if (a.cta_url && a.cta_label) {
        var link = document.createElement('a');
        link.href = a.cta_url;
        link.className = 'dash-announce-tile__cta';
        link.textContent = String(a.cta_label).toUpperCase() + ' →';
        if (accent) link.style.color = accent;
        foot.appendChild(link);
      } else if (a.image_url) {
        var thumb = document.createElement('img');
        thumb.src = a.image_url;
        thumb.alt = '';
        thumb.className = 'alert-banner-thumb';
        foot.appendChild(thumb);
      }

      if (canDismiss) {
        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'alert-banner-close';
        closeBtn.setAttribute('aria-label', 'Fermer cette annonce');
        closeBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
        closeBtn.addEventListener('click', function() {
          tile.classList.add('alert-banner-item--out');
          window.setTimeout(function() {
            setDismissed(a);
            tile.remove();
            var left = grid.querySelectorAll('.alert-banner-item').length;
            status.textContent = left === 1 ? '1 message actif' : left + ' messages actifs';
            if (!left) root.remove();
          }, 220);
          if (loggedIn && dismissUrl && csrf) {
            var fd = new FormData();
            fd.append('_csrf_token', csrf);
            fd.append('scope', a.scope);
            fd.append('alert_id', String(a.id));
            fetch(dismissUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function() {});
          }
        });
        foot.appendChild(closeBtn);
      }

      if (foot.childNodes.length) {
        panelInner.appendChild(foot);
      }

      tile.appendChild(panelInner);
      grid.appendChild(tile);
    });

    panel.appendChild(grid);
    shell.appendChild(panel);
    root.appendChild(shell);

    toggle.addEventListener('click', function() {
      setOpen(shell, toggle.getAttribute('aria-expanded') !== 'true');
    });

    var stored = null;
    try { stored = localStorage.getItem(persistKey); } catch (e) {}
    if (stored === '1') setOpen(shell, true);
    else if (stored === '0') setOpen(shell, false);
    else setOpen(shell, startOpen);
  }

  if (!document.getElementById('alert-banners-keyframes')) {
    var st = document.createElement('style');
    st.id = 'alert-banners-keyframes';
    st.textContent = [
      '@keyframes alertFadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}',
      '@keyframes alertFadeOut{to{opacity:0;transform:translateY(-4px)}}',
      '.alert-banners-stack--tiles{background:#050505;border-bottom:1px solid #111;padding:0.55rem 1rem}',
      '@media (min-width:768px){.alert-banners-stack--tiles{padding:0.65rem 1.5rem}}',
      '@media (min-width:1024px){.alert-banners-stack--tiles{padding:0.7rem 2rem}}',
      '.alert-banners-shell.dash-announce{background:transparent;border:0;padding:0;max-width:1800px;margin:0 auto;font-family:Archivo,system-ui,-apple-system,sans-serif}',
      '.alert-banners-grid{display:grid;gap:0.75rem}',
      '@media (min-width:900px){.alert-banners-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}',
      '.alert-banner-item{animation:alertFadeSlide 0.28s ease both;position:relative}',
      '.alert-banner-item--out{animation:alertFadeOut 0.18s ease forwards}',
      '.alert-banner-tile-foot{display:flex;flex-wrap:wrap;align-items:center;gap:0.55rem;margin-top:0.35rem}',
      '.alert-banner-code{font-size:0.6875rem;font-family:ui-monospace,monospace;font-weight:700;padding:0.25rem 0.55rem;border-radius:0.4rem;border:1px solid rgba(255,255,255,0.14);background:rgba(0,0,0,0.35);color:#e5e5e5;cursor:pointer}',
      '.alert-banner-close{margin-left:auto;display:inline-flex;align-items:center;justify-content:center;padding:0.35rem;border-radius:0.45rem;border:0;background:transparent;color:#a3a3a3;cursor:pointer}',
      '.alert-banner-close:hover{color:#fff;background:rgba(255,255,255,0.06)}',
      '.alert-banner-thumb{width:2.25rem;height:2.25rem;border-radius:0.45rem;object-fit:cover;border:1px solid rgba(255,255,255,0.12)}',
      '@media (prefers-reduced-motion:reduce){.alert-banner-item,.alert-banner-item--out{animation:none!important}}'
    ].join('');
    document.head.appendChild(st);
  }
  build();
})();
</script>
