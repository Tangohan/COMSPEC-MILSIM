<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $inboxItems */
$inboxItems = is_array($inboxItems ?? null) ? $inboxItems : [];
$inboxCounts = is_array($inboxCounts ?? null) ? $inboxCounts : ['all' => 0, 'unread' => 0];
$inboxSources = is_array($inboxSources ?? null) ? $inboxSources : [];
$inboxFilter = (string) ($inboxFilter ?? '');
$inboxQuery = (string) ($inboxQuery ?? '');
$inboxCanWrite = !empty($inboxCanWrite);
$csrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$icons = [
    'messages' => 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75',
    'notifications' => 'M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155',
    'courrier' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    'annonces' => 'M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46',
];

$now = time();
$todayStart = strtotime('today');
$bucketOf = static function (string $at) use ($todayStart): string {
    $t = strtotime($at);
    if ($t === false || $at === '') {
        return 'Sans date';
    }
    if ($t >= $todayStart) {
        return 'Aujourd’hui';
    }
    if ($t >= $todayStart - 86400) {
        return 'Hier';
    }
    if ($t >= $todayStart - 6 * 86400) {
        return 'Cette semaine';
    }

    return 'Plus ancien';
};
$when = static function (string $at) use ($now, $todayStart): string {
    $t = strtotime($at);
    if ($t === false || $at === '') {
        return '';
    }
    $diff = $now - $t;
    if ($diff < 60) {
        return 'à l’instant';
    }
    if ($diff < 3600) {
        return 'il y a ' . (int) floor($diff / 60) . ' min';
    }
    if ($t >= $todayStart) {
        return date('H:i', $t);
    }

    return date('d/m/Y', $t);
};
$tabs = ['' => ['Tout', $inboxCounts['all'] ?? 0], 'nonlus' => ['Non lus', $inboxCounts['unread'] ?? 0]];
foreach ($inboxSources as $key => $label) {
    $tabs[$key] = [$label, $key === 'annonces' ? ($inboxCounts['annonces'] ?? 0) : ($inboxCounts[$key . '_unread'] ?? 0)];
}
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/inbox.css')) ?>">

<div class="ibx" data-ibx data-mark-url="<?= $h(url('boite-reception/lu')) ?>" data-delete-url="<?= $h(url('boite-reception/supprimer')) ?>" data-csrf="<?= $h($csrf) ?>">
  <header class="ibx-head">
    <div>
      <p class="ibx-kicker">Messages et notifications</p>
      <h1 class="ibx-title">Boîte de réception</h1>
      <p class="ibx-sub" data-ibx-summary>
        <?php if (($inboxCounts['unread'] ?? 0) > 0): ?>
        <strong><?= (int) $inboxCounts['unread'] ?></strong> élément(s) non lu(s) sur <?= (int) $inboxCounts['all'] ?>.
        <?php else: ?>
        Tout est lu. Les nouveautés de la messagerie, les notifications, le courrier et les annonces arrivent ici.
        <?php endif; ?>
      </p>
    </div>
    <div class="ibx-head__actions">
      <?php if ($inboxCanWrite): ?><a class="ibx-btn ibx-btn--primary" href="<?= $h(url('messages')) ?>">Nouveau message</a><?php endif; ?>
      <form method="post" action="<?= $h(url('boite-reception/lu')) ?>" data-ibx-markall>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <input type="hidden" name="source" value="all">
        <button type="submit" class="ibx-btn" <?= ($inboxCounts['unread'] ?? 0) === 0 ? 'disabled' : '' ?>>Tout marquer comme lu</button>
      </form>
      <form method="post" action="<?= $h(url('boite-reception/supprimer')) ?>" data-ibx-purge>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <input type="hidden" name="lus" value="1">
        <input type="hidden" name="filtre" value="<?= $h($inboxFilter) ?>">
        <button type="submit" class="ibx-btn ibx-btn--ghost" title="Supprime les notifications et avis de courrier déjà lus">Supprimer les éléments lus</button>
      </form>
    </div>
  </header>

  <?php if ($flashOk): ?><p class="ibx-flash ibx-flash--ok" role="status"><?= $h((string) $flashOk) ?></p><?php endif; ?>
  <?php if ($flashErr): ?><p class="ibx-flash ibx-flash--err" role="alert"><?= $h((string) $flashErr) ?></p><?php endif; ?>

  <div class="ibx-layout">
    <aside class="ibx-side" aria-label="Filtres">
      <nav class="ibx-tabs" role="tablist">
        <?php foreach ($tabs as $key => [$label, $count]): ?>
        <a role="tab" class="ibx-tab" href="<?= $h(url('boite-reception') . ($key !== '' ? '?filtre=' . $key : '')) ?>" data-ibx-tab="<?= $h((string) $key) ?>" aria-selected="<?= $inboxFilter === $key ? 'true' : 'false' ?>">
          <?php if (isset($icons[$key])): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $h($icons[$key]) ?>"/></svg><?php endif; ?>
          <span><?= $h((string) $label) ?></span>
          <?php if ((int) $count > 0): ?><span class="ibx-count" data-ibx-count="<?= $h((string) $key) ?>"><?= (int) $count ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </nav>
      <a class="ibx-todo" href="<?= $h(url('centre-actions')) ?>">
        <strong>À traiter</strong>
        <span>Inscriptions, tâches et dossiers en attente sont dans le centre d’actions.</span>
      </a>
    </aside>

    <section class="ibx-main" aria-label="Éléments reçus">
      <div class="ibx-tools">
        <label class="ibx-search">
          <span class="sr-only">Rechercher</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
          <input type="search" value="<?= $h($inboxQuery) ?>" placeholder="Rechercher un sujet, un expéditeur, une référence…" autocomplete="off" data-ibx-q>
        </label>
        <form method="post" action="<?= $h(url('boite-reception/lu')) ?>" class="ibx-bulk" data-ibx-bulk>
          <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
          <input type="hidden" name="filtre" value="<?= $h($inboxFilter) ?>">
          <label class="ibx-check"><input type="checkbox" data-ibx-all> <span>Tout sélectionner</span></label>
          <button type="submit" class="ibx-btn ibx-btn--small" data-ibx-bulk-btn disabled>Marquer la sélection comme lue</button>
          <button type="button" class="ibx-btn ibx-btn--small ibx-btn--danger" data-ibx-bulk-del disabled>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>
            Supprimer la sélection
          </button>
        </form>
      </div>

      <?php if ($inboxItems === []): ?>
      <div class="ibx-empty">
        <p class="ibx-empty__title">Rien pour le moment</p>
        <p>Les messages de l’encadrement, vos notifications, le courrier officiel et les annonces de la communauté apparaîtront ici.</p>
      </div>
      <?php else: ?>
      <ol class="ibx-list" data-ibx-list>
        <?php $lastBucket = null; foreach ($inboxItems as $it):
          $bucket = $bucketOf((string) $it['at']);
          $source = (string) $it['source'];
        ?>
        <?php if ($bucket !== $lastBucket): $lastBucket = $bucket; ?>
        <li class="ibx-day" data-ibx-day><?= $h($bucket) ?></li>
        <?php endif; ?>
        <li class="ibx-item<?= $it['unread'] ? ' is-unread' : '' ?>" data-ibx-item data-key="<?= $h((string) $it['key']) ?>" data-deletable="<?= !empty($it['deletable']) ? '1' : '0' ?>" data-source="<?= $h($source) ?>" data-unread="<?= $it['unread'] ? '1' : '0' ?>" data-search="<?= $h(mb_strtolower((string) $it['title'] . ' ' . (string) $it['detail'])) ?>">
          <?php if ($source !== 'annonces' || !empty($it['deletable'])): ?>
          <input type="checkbox" class="ibx-item__check" aria-label="Sélectionner « <?= $h((string) $it['title']) ?> »" value="<?= $h((string) $it['key']) ?>" data-ibx-select>
          <?php else: ?>
          <span class="ibx-item__check" aria-hidden="true"></span>
          <?php endif; ?>
          <span class="ibx-badge ibx-badge--<?= $h($source) ?>" title="<?= $h(\App\Controllers\Web\InboxController::SOURCES[$source] ?? $source) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $h($icons[$source] ?? $icons['annonces']) ?>"/></svg>
          </span>
          <a class="ibx-item__body" href="<?= $h((string) $it['href']) ?>" data-ibx-open>
            <span class="ibx-item__top">
              <span class="ibx-item__title"><?= $h((string) $it['title']) ?></span>
              <span class="ibx-item__source"><?= $h(\App\Controllers\Web\InboxController::SOURCES[$source] ?? '') ?></span>
            </span>
            <?php if ((string) $it['detail'] !== ''): ?><span class="ibx-item__detail"><?= $h((string) $it['detail']) ?></span><?php endif; ?>
          </a>
          <span class="ibx-item__meta">
            <time datetime="<?= $h((string) $it['at']) ?>"><?= $h($when((string) $it['at'])) ?></time>
            <?php if ($it['unread']): ?>
            <form method="post" action="<?= $h(url('boite-reception/lu')) ?>" data-ibx-read>
              <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
              <input type="hidden" name="source" value="<?= $h($source) ?>">
              <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
              <input type="hidden" name="filtre" value="<?= $h($inboxFilter) ?>">
              <button type="submit" class="ibx-read" title="Marquer comme lu"><span class="ibx-dot" aria-hidden="true"></span><span class="sr-only">Marquer comme lu</span></button>
            </form>
            <?php endif; ?>
            <?php if (!empty($it['deletable'])): ?>
            <form method="post" action="<?= $h(url('boite-reception/supprimer')) ?>" data-ibx-del>
              <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
              <input type="hidden" name="source" value="<?= $h($source) ?>">
              <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
              <input type="hidden" name="filtre" value="<?= $h($inboxFilter) ?>">
              <button type="submit" class="ibx-del" title="<?= $source === 'messages' ? 'Retirer de ma boîte (la conversation revient si quelqu’un répond)' : ($source === 'annonces' ? 'Masquer cette annonce' : 'Supprimer') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>
                <span class="sr-only">Supprimer</span>
              </button>
            </form>
            <?php endif; ?>
          </span>
        </li>
        <?php endforeach; ?>
      </ol>
      <p class="ibx-empty ibx-empty--inline" data-ibx-none hidden>Aucun élément ne correspond à ce filtre.</p>
      <div class="ibx-toast" data-ibx-toast role="status" aria-live="polite" hidden><span data-ibx-toast-text></span><button type="button" class="ibx-toast__undo" data-ibx-undo>Annuler</button></div>
      <?php endif; ?>
    </section>
  </div>
</div>

<script>
(function () {
  var root = document.querySelector('[data-ibx]');
  if (!root) return;
  var markUrl = root.getAttribute('data-mark-url');
  var deleteUrl = root.getAttribute('data-delete-url');
  var csrf = root.getAttribute('data-csrf');
  var items = Array.prototype.slice.call(root.querySelectorAll('[data-ibx-item]'));
  var days = Array.prototype.slice.call(root.querySelectorAll('[data-ibx-day]'));
  var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-ibx-tab]'));
  var q = root.querySelector('[data-ibx-q]');
  var none = root.querySelector('[data-ibx-none]');
  var all = root.querySelector('[data-ibx-all]');
  var bulkForm = root.querySelector('[data-ibx-bulk]');
  var bulkBtn = root.querySelector('[data-ibx-bulk-btn]');
  var filter = '';
  tabs.forEach(function (t) { if (t.getAttribute('aria-selected') === 'true') filter = t.getAttribute('data-ibx-tab'); });

  function fold(v) {
    var s = String(v || '').toLowerCase();
    if (s.normalize) s = s.normalize('NFD').replace(/[̀-ͯ]/g, '');
    return s;
  }
  function visible(it) {
    if (it.classList.contains('is-removed')) return false;
    var src = it.getAttribute('data-source');
    if (filter === 'nonlus' && it.getAttribute('data-unread') !== '1') return false;
    if (filter && filter !== 'nonlus' && src !== filter) return false;
    var tokens = fold(q ? q.value : '').split(/\s+/).filter(Boolean);
    var hay = fold(it.getAttribute('data-search'));
    return tokens.every(function (t) { return hay.indexOf(t) !== -1; });
  }
  function apply() {
    var shown = 0;
    items.forEach(function (it) { var ok = visible(it); it.hidden = !ok; if (ok) shown++; });
    // Masque les intertitres de jour sans élément visible.
    days.forEach(function (d) {
      var n = d.nextElementSibling, any = false;
      while (n && !n.hasAttribute('data-ibx-day')) { if (!n.hidden) { any = true; break; } n = n.nextElementSibling; }
      d.hidden = !any;
    });
    if (none) none.hidden = shown > 0 || items.length === 0;
    syncBulk();
  }
  function selected() {
    return items.filter(function (it) { var c = it.querySelector('[data-ibx-select]'); return c && c.checked && !it.hidden; });
  }
  var bulkDel = root.querySelector('[data-ibx-bulk-del]');
  function syncBulk() {
    var sel = selected();
    if (bulkBtn) bulkBtn.disabled = sel.filter(function (it) { return it.getAttribute('data-source') !== 'annonces'; }).length === 0;
    if (bulkDel) bulkDel.disabled = sel.filter(function (it) { return it.getAttribute('data-deletable') === '1'; }).length === 0;
  }
  // Suppression différée : l’élément disparaît tout de suite, la requête part après 5 s (« Annuler » possible).
  var toast = root.querySelector('[data-ibx-toast]');
  var toastText = root.querySelector('[data-ibx-toast-text]');
  var pending = null;
  function flush(beacon) {
    if (!pending) return;
    var p = pending; pending = null;
    clearTimeout(p.timer);
    if (toast) toast.hidden = true;
    var fd = new FormData();
    fd.append('_csrf_token', csrf);
    p.list.forEach(function (it) { fd.append('keys[]', it.getAttribute('data-key')); });
    if (beacon && navigator.sendBeacon) { navigator.sendBeacon(deleteUrl, fd); }
    else { fetch(deleteUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } }).catch(function () {}); }
    p.list.forEach(function (it) { it.remove(); });
    items = items.filter(function (it) { return p.list.indexOf(it) === -1; });
    refreshCounts(); apply();
  }
  function removeItems(list) {
    list = list.filter(function (it) { return it.getAttribute('data-deletable') === '1'; });
    if (!list.length) return;
    flush(false);
    list.forEach(function (it) { it.classList.add('is-removed'); it.hidden = true; var c = it.querySelector('[data-ibx-select]'); if (c) c.checked = false; });
    if (all) all.checked = false;
    pending = { list: list, timer: setTimeout(function () { flush(false); }, 5000) };
    if (toast) { toastText.textContent = list.length > 1 ? list.length + ' éléments supprimés.' : 'Élément supprimé.'; toast.hidden = false; }
    var unreadGone = list.filter(function (it) { return it.getAttribute('data-unread') === '1'; });
    if (unreadGone.length) unreadGone.forEach(function (it) { it.setAttribute('data-unread', '0'); it.setAttribute('data-was-unread', '1'); });
    refreshCounts(); apply();
  }
  var undo = root.querySelector('[data-ibx-undo]');
  if (undo) undo.addEventListener('click', function () {
    if (!pending) return;
    clearTimeout(pending.timer);
    pending.list.forEach(function (it) { it.classList.remove('is-removed'); if (it.getAttribute('data-was-unread') === '1') { it.setAttribute('data-unread', '1'); it.removeAttribute('data-was-unread'); } });
    pending = null; toast.hidden = true; refreshCounts(); apply();
  });
  window.addEventListener('pagehide', function () { flush(true); });
  root.addEventListener('submit', function (e) {
    var f = e.target.closest('[data-ibx-del]');
    if (!f) return;
    e.preventDefault();
    removeItems([f.closest('[data-ibx-item]')]);
  });
  if (bulkDel) bulkDel.addEventListener('click', function () { removeItems(selected()); syncBulk(); });
  var purge = root.querySelector('[data-ibx-purge]');
  if (purge) purge.addEventListener('submit', function (e) {
    if (!window.confirm('Supprimer toutes les notifications et tous les avis de courrier déjà lus ? Les conversations et le courrier lui-même sont conservés.')) e.preventDefault();
  });

  function post(fields) {
    var fd = new FormData();
    fd.append('_csrf_token', csrf);
    Object.keys(fields).forEach(function (k) {
      var v = fields[k];
      if (Array.isArray(v)) v.forEach(function (x) { fd.append(k + '[]', x); }); else fd.append(k, v);
    });
    return fetch(markUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); });
  }
  function markItemsRead(list) {
    list.forEach(function (it) {
      if (it.getAttribute('data-unread') !== '1') return;
      it.setAttribute('data-unread', '0');
      it.classList.remove('is-unread');
      var f = it.querySelector('[data-ibx-read]');
      if (f) f.remove();
    });
    refreshCounts();
  }
  function refreshCounts() {
    var unread = items.filter(function (it) { return it.getAttribute('data-unread') === '1'; });
    root.querySelectorAll('[data-ibx-count]').forEach(function (c) {
      var key = c.getAttribute('data-ibx-count');
      if (key === '' || key === 'annonces') {
        var n0 = items.filter(function (it) { return !it.classList.contains('is-removed') && (key === '' || it.getAttribute('data-source') === key); }).length;
        c.textContent = String(n0); c.hidden = n0 === 0; return;
      }
      var n = key === 'nonlus' ? unread.length : unread.filter(function (it) { return it.getAttribute('data-source') === key; }).length;
      c.textContent = String(n);
      c.hidden = n === 0;
    });
    var summary = root.querySelector('[data-ibx-summary]');
    var live = items.filter(function (it) { return !it.classList.contains('is-removed'); }).length;
    if (summary) summary.innerHTML = live === 0 ? 'Boîte vide.' : (unread.length > 0 ? '<strong>' + unread.length + '</strong> élément(s) non lu(s) sur ' + live + '.' : 'Tout est lu.');
    var markAll = root.querySelector('[data-ibx-markall] button');
    if (markAll) markAll.disabled = unread.length === 0;
  }

  tabs.forEach(function (t) {
    t.addEventListener('click', function (e) {
      e.preventDefault();
      filter = t.getAttribute('data-ibx-tab');
      tabs.forEach(function (x) { x.setAttribute('aria-selected', x === t ? 'true' : 'false'); });
      try { history.replaceState(null, '', t.href); } catch (err) { /* ignore */ }
      apply();
    });
  });
  if (q) q.addEventListener('input', apply);
  if (all) all.addEventListener('change', function () {
    items.forEach(function (it) { var c = it.querySelector('[data-ibx-select]'); if (c && !it.hidden) c.checked = all.checked; });
    syncBulk();
  });
  root.addEventListener('change', function (e) { if (e.target && e.target.hasAttribute('data-ibx-select')) syncBulk(); });

  if (bulkForm) bulkForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var list = selected();
    if (!list.length) return;
    post({ keys: list.map(function (it) { return it.getAttribute('data-key'); }) }).then(function () {
      markItemsRead(list);
      list.forEach(function (it) { var c = it.querySelector('[data-ibx-select]'); if (c) c.checked = false; });
      if (all) all.checked = false;
      apply();
    });
  });
  root.querySelectorAll('[data-ibx-read]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var it = f.closest('[data-ibx-item]');
      post({ source: f.elements.source.value, id: f.elements.id.value }).then(function () { markItemsRead([it]); apply(); });
    });
  });
  var markAllForm = root.querySelector('[data-ibx-markall]');
  if (markAllForm) markAllForm.addEventListener('submit', function (e) {
    e.preventDefault();
    post({ source: 'all' }).then(function () { markItemsRead(items); apply(); });
  });
  // Ouvrir un élément le marque lu (la conversation se marque d’elle-même à l’ouverture).
  root.querySelectorAll('[data-ibx-open]').forEach(function (a) {
    a.addEventListener('click', function () {
      var it = a.closest('[data-ibx-item]');
      if (!it || it.getAttribute('data-unread') !== '1') return;
      var key = it.getAttribute('data-key').split(':');
      if (key[0] === 'messages' || !navigator.sendBeacon) return;
      var fd = new FormData();
      fd.append('_csrf_token', csrf); fd.append('source', key[0]); fd.append('id', key[1]);
      navigator.sendBeacon(markUrl, fd);
    });
  });
  apply();
})();
</script>
