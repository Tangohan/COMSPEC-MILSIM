<?php
declare(strict_types=1);

use App\Core\Container;
use App\Services\Alerts\AlertPresentationService;
use App\Support\AlertDisplayStyle;

if (!empty($GLOBALS['__navbar_info_banners_rendered'])) {
    return;
}
$GLOBALS['__navbar_info_banners_rendered'] = true;

$navbarInfoBanners = $navbarInfoBanners ?? null;
if ($navbarInfoBanners === null) {
    try {
        $all = Container::get(AlertPresentationService::class)->forCurrentRequest();
        $navbarInfoBanners = array_values(array_filter(
            is_array($all) ? $all : [],
            static fn (array $a): bool => AlertDisplayStyle::isNavbarStyle((string) ($a['display_style'] ?? 'classic'))
        ));
    } catch (\Throwable) {
        $navbarInfoBanners = [];
    }
}
if (!is_array($navbarInfoBanners) || $navbarInfoBanners === []) {
    return;
}

$alertUserLoggedIn = (bool) \App\Core\Session::get('user_id');
$alertCsrf = \App\Core\Csrf::token();
$alertDismissUrl = url('api/alerts/dismiss');
$json = json_encode($navbarInfoBanners, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
if ($json === false) {
    return;
}
?>
<div id="navbar-info-banners-root"
     class="navbar-info-banners"
     role="region"
     aria-label="Annonces sous le menu"
     data-alerts="<?= htmlspecialchars($json, ENT_QUOTES, 'UTF-8') ?>"
     data-logged-in="<?= $alertUserLoggedIn ? '1' : '0' ?>"
     data-csrf="<?= htmlspecialchars($alertCsrf, ENT_QUOTES, 'UTF-8') ?>"
     data-dismiss-url="<?= htmlspecialchars($alertDismissUrl, ENT_QUOTES, 'UTF-8') ?>">
</div>
<script>
(function () {
  var root = document.getElementById('navbar-info-banners-root');
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
  function dismiss(a, el) {
    setDismissed(a);
    if (el && el.parentNode) el.parentNode.removeChild(el);
    if (!loggedIn || !dismissUrl) return;
    try {
      fetch(dismissUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ scope: a.scope, id: a.id, _csrf_token: csrf }),
        credentials: 'same-origin'
      }).catch(function () {});
    } catch (e) {}
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function tickerItems(a) {
    var parts = [];
    var title = (a.title || '').trim();
    var body = (a.body || '').trim();
    if (title) parts.push(title);
    if (body) {
      body.split(/\r?\n+| · | • |\|/).forEach(function (p) {
        p = p.trim();
        if (p) parts.push(p);
      });
    }
    if (!parts.length) parts.push('Annonce');
    return parts;
  }

  function closeBtn(a, el) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'banner-close';
    btn.setAttribute('aria-label', 'Masquer');
    btn.innerHTML = '&times;';
    btn.addEventListener('click', function () { dismiss(a, el); });
    return btn;
  }

  function buildMini(a) {
    var style = a.display_style || 'mini_info';
    var tone = style === 'mini_success' ? 'success'
      : style === 'mini_warning' ? 'warning'
      : style === 'mini_danger' ? 'danger' : 'info';
    var tag = style === 'mini_success' ? 'Succès'
      : style === 'mini_warning' ? 'Alerte'
      : style === 'mini_danger' ? 'Critique' : 'Info';

    var el = document.createElement('div');
    el.className = 'mini-banner ' + tone;
    el.setAttribute('role', 'status');
    el.setAttribute('data-alert-scope', a.scope);
    el.setAttribute('data-alert-id', String(a.id));

    var tagEl = document.createElement('div');
    tagEl.className = 'banner-tag';
    tagEl.textContent = tag;
    el.appendChild(tagEl);

    var content = document.createElement('div');
    content.className = 'banner-content';
    var title = document.createElement('span');
    title.className = 'banner-title';
    title.textContent = a.title || '';
    content.appendChild(title);
    if (a.body) {
      var desc = document.createElement('span');
      desc.className = 'banner-desc';
      desc.textContent = a.body;
      content.appendChild(desc);
    }
    if (a.cta_url && a.cta_label) {
      var cta = document.createElement('a');
      cta.className = 'banner-cta';
      cta.href = a.cta_url;
      cta.textContent = a.cta_label;
      content.appendChild(cta);
    }
    el.appendChild(content);

    var end = document.createElement('div');
    end.className = 'banner-end';
    if (a.scope === 'platform') {
      var v = document.createElement('span');
      v.className = 'nib-verified';
      v.title = 'Annonce officielle du site Athena';
      v.innerHTML = '<svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>Site vérifié';
      end.appendChild(v);
    }
    var canDismiss = a.dismissible !== false && a.dismissible !== 0;
    if (canDismiss) end.appendChild(closeBtn(a, el));
    if (end.childNodes.length) el.appendChild(end);
    return el;
  }

  function buildBreaking(a) {
    var el = document.createElement('div');
    el.className = 'nib-breaking';
    el.setAttribute('role', 'status');
    el.setAttribute('data-alert-scope', a.scope);
    el.setAttribute('data-alert-id', String(a.id));

    var pill = document.createElement('div');
    pill.className = 'nib-breaking-pill';
    pill.innerHTML = '<span class="nib-live-dot" aria-hidden="true"></span><span>Attention</span>';
    el.appendChild(pill);

    var ticker = document.createElement('div');
    ticker.className = 'nib-breaking-ticker';
    var track = document.createElement('div');
    track.className = 'nib-ticker-track';
    var items = tickerItems(a);
    var html = '';
    // Double the sequence for seamless loop
    for (var pass = 0; pass < 2; pass++) {
      items.forEach(function (t, i) {
        if (pass > 0 || i > 0) html += '<span class="ticker-sep nib-ticker-sep" aria-hidden="true">◆</span>';
        html += '<span>' + esc(t) + '</span>';
      });
    }
    track.innerHTML = html;
    ticker.appendChild(track);
    el.appendChild(ticker);

    var canDismiss = a.dismissible !== false && a.dismissible !== 0;
    if (canDismiss) el.appendChild(closeBtn(a, el));
    return el;
  }

  function buildImportant(a) {
    var el = document.createElement('div');
    el.className = 'nib-important';
    el.setAttribute('role', 'status');
    el.setAttribute('data-alert-scope', a.scope);
    el.setAttribute('data-alert-id', String(a.id));

    var icon = document.createElement('div');
    icon.className = 'nib-important-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
    el.appendChild(icon);

    var body = document.createElement('div');
    body.className = 'nib-important-body';
    var titleRow = document.createElement('div');
    titleRow.className = 'nib-important-title';
    titleRow.appendChild(document.createTextNode(a.title || ''));
    var badge = document.createElement('span');
    badge.className = 'nib-important-badge';
    badge.textContent = 'Important';
    titleRow.appendChild(badge);
    body.appendChild(titleRow);
    if (a.body) {
      var desc = document.createElement('div');
      desc.className = 'nib-important-desc';
      desc.textContent = a.body;
      body.appendChild(desc);
    }
    if (a.cta_url && a.cta_label) {
      var cta = document.createElement('a');
      cta.className = 'nib-important-cta';
      cta.href = a.cta_url;
      cta.textContent = a.cta_label;
      body.appendChild(cta);
    }
    el.appendChild(body);

    var canDismiss = a.dismissible !== false && a.dismissible !== 0;
    if (canDismiss) el.appendChild(closeBtn(a, el));
    return el;
  }

  root.innerHTML = '';
  alerts.forEach(function (a) {
    var canDismiss = a.dismissible !== false && a.dismissible !== 0;
    if (canDismiss && isDismissed(a)) return;
    var style = a.display_style || 'classic';
    var el;
    if (style === 'breaking') el = buildBreaking(a);
    else if (style === 'important') el = buildImportant(a);
    else el = buildMini(a);
    root.appendChild(el);
  });
})();
</script>
