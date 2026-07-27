<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $alerts_active */
/** @var list<array<string, mixed>> $alerts_upcoming */
/** @var list<array<string, mixed>> $alerts_history */
/** @var bool $alerts_show_upcoming */
/** @var string|null $alerts_manage_url */

$alertsActive = is_array($alerts_active ?? null) ? $alerts_active : [];
$alertsUpcoming = is_array($alerts_upcoming ?? null) ? $alerts_upcoming : [];
$alertsHistory = is_array($alerts_history ?? null) ? $alerts_history : [];
// La section « à venir » n’apparaît que si la communauté l’a activée.
$alertsShowUpcoming = (bool) ($alerts_show_upcoming ?? false);
$alertsManageUrl = isset($alerts_manage_url) && is_string($alerts_manage_url) && $alerts_manage_url !== ''
    ? $alerts_manage_url
    : null;

$heroImageRel = null;
foreach (['assets/images/fog-team.jpg', 'assets/images/night-team.jpg', 'assets/images/hero-explosion.jpg', 'assets/images/fog-banner.jpg'] as $candidate) {
    if (is_file(base_path('public/' . $candidate))) {
        $heroImageRel = $candidate;
        break;
    }
}
$heroHasImage = $heroImageRel !== null;
$heroImageUrl = $heroHasImage ? asset_url($heroImageRel) : '';

$kindLabelFr = static function (string $kind): string {
    return match (strtolower(trim($kind))) {
        'urgent' => 'Urgent',
        'novelty' => 'Nouveau',
        'discount' => 'Promotion',
        'notice' => 'Annonce',
        'forum_pin' => 'Message épinglé',
        default => 'Information',
    };
};

$filterKeyForKind = static function (string $kind): string {
    $kind = strtolower(trim($kind));

    return match ($kind) {
        'urgent' => 'urgent',
        'novelty' => 'novelty',
        'discount' => 'discount',
        'notice', 'forum_pin' => 'notice',
        default => 'info',
    };
};

$formatEndedAt = static function (?string $raw): ?string {
    if ($raw === null || $raw === '') {
        return null;
    }
    $t = strtotime($raw);

    return $t !== false ? date('d/m/Y H:i', $t) : null;
};

$activeCount = count($alertsActive);
$historyCount = count($alertsHistory);
$urgentCount = 0;
$pinnedCount = 0;
$filterKeysPresent = [];
foreach ($alertsActive as $row) {
    if (!is_array($row)) {
        continue;
    }
    $k = strtolower(trim((string) ($row['kind'] ?? 'info')));
    if ($k === 'urgent') {
        $urgentCount++;
    }
    if (!empty($row['pinned']) || in_array($k, ['forum_pin', 'notice'], true)) {
        $pinnedCount++;
    }
    $filterKeysPresent[$filterKeyForKind($k)] = true;
}

$filterDefs = [
    'all' => 'Tous',
    'urgent' => 'Urgent',
    'notice' => 'Annonces',
    'novelty' => 'Nouveau',
    'discount' => 'Promotion',
    'info' => 'Information',
];

$csrfToken = \App\Core\Csrf::token();
$dismissUrl = url('api/alerts/dismiss');

/**
 * Rend une carte d’annonce.
 *
 * `$mode` distingue trois situations : `active` (diffusion en cours), `upcoming`
 * (programmée, pas encore diffusée) et `archived` (diffusion terminée). Une annonce à
 * venir n’est jamais masquable : la masquer avant sa diffusion la ferait disparaître
 * sans avoir été lue.
 */
$renderCard = static function (array $item, string $mode = 'active') use ($kindLabelFr, $filterKeyForKind, $formatEndedAt): void {
    $archived = $mode === 'archived';
    $upcoming = $mode === 'upcoming';
    $rawKind = strtolower(trim((string) ($item['kind'] ?? 'info')));
    $filterKey = $filterKeyForKind($rawKind);
    $kind = $filterKey;
    $category = trim((string) ($item['category'] ?? ''));
    if ($category === '') {
        $category = $kindLabelFr($rawKind);
    }
    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '') {
        return;
    }
    $body = trim((string) ($item['body'] ?? ''));
    $ctaLabel = isset($item['cta_label']) && is_string($item['cta_label']) && $item['cta_label'] !== ''
        ? (string) $item['cta_label']
        : null;
    $ctaUrl = isset($item['cta_url']) && is_string($item['cta_url']) && $item['cta_url'] !== ''
        ? (string) $item['cta_url']
        : null;
    $isPlatform = strtolower(trim((string) ($item['scope'] ?? ''))) === 'platform';
    $scope = strtolower(trim((string) ($item['scope'] ?? 'tenant')));
    $alertId = (int) ($item['id'] ?? 0);
    $dismissible = !$archived
        && !$upcoming
        && $alertId > 0
        && in_array($scope, ['platform', 'tenant'], true)
        && (!array_key_exists('dismissible', $item) || (bool) $item['dismissible']);
    $isPinned = !$archived && !$upcoming && (
        !empty($item['pinned'])
        || in_array($rawKind, ['forum_pin', 'notice'], true)
    );
    $endedLabel = $archived ? $formatEndedAt(isset($item['ended_at']) ? (string) $item['ended_at'] : null) : null;
    $startsAtLabel = $upcoming ? $formatEndedAt(isset($item['starts_at']) ? (string) $item['starts_at'] : null) : null;
    $extraClass = $archived ? ' alerts-page__card--archived' : ($upcoming ? ' alerts-page__card--upcoming' : '');
    $cardTag = ($ctaUrl !== null && !$dismissible) ? 'a' : 'article';
    $hrefAttr = ($cardTag === 'a') ? ' href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '"' : '';
    ?>
    <<?= $cardTag ?>
        class="alerts-page__card alerts-page__card--<?= htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') ?><?= $isPlatform ? ' alerts-page__card--verified' : '' ?><?= $extraClass ?>"
        data-alert-card
        data-filter="<?= htmlspecialchars($filterKey, ENT_QUOTES, 'UTF-8') ?>"
        <?= $hrefAttr ?>
    >
        <span class="alerts-page__card-rail" aria-hidden="true"></span>
        <div class="alerts-page__card-main">
            <div class="alerts-page__card-meta">
                <p class="alerts-page__card-kind"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($isPinned): ?>
                    <span class="alerts-page__chip alerts-page__chip--pinned">Épinglé</span>
                <?php endif; ?>
                <?php if ($isPlatform): ?>
                    <span class="alerts-page__chip alerts-page__chip--verified">Annonce officielle du site</span>
                <?php endif; ?>
                <?php if ($endedLabel !== null): ?>
                    <span class="alerts-page__chip alerts-page__chip--ended">Diffusion terminée le <?= htmlspecialchars($endedLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($startsAtLabel !== null): ?>
                    <span class="alerts-page__chip alerts-page__chip--upcoming">Diffusion prévue le <?= htmlspecialchars($startsAtLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
            <h3 class="alerts-page__card-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
            <?php if ($body !== ''): ?>
                <p class="alerts-page__card-body"><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <div class="alerts-page__card-foot">
                <?php if ($ctaUrl !== null): ?>
                    <?php if ($cardTag === 'a'): ?>
                        <span class="alerts-page__card-cta"><?= htmlspecialchars($ctaLabel !== null ? $ctaLabel : 'Ouvrir', ENT_QUOTES, 'UTF-8') ?> →</span>
                    <?php else: ?>
                        <a class="alerts-page__card-cta" href="<?= htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ctaLabel !== null ? $ctaLabel : 'Ouvrir', ENT_QUOTES, 'UTF-8') ?> →</a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($dismissible): ?>
                    <button
                        type="button"
                        class="alerts-page__dismiss"
                        data-alert-dismiss
                        data-scope="<?= htmlspecialchars($scope, ENT_QUOTES, 'UTF-8') ?>"
                        data-alert-id="<?= (int) $alertId ?>"
                    >
                        Masquer
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </<?= $cardTag ?>>
    <?php
};
?>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@600;700&display=swap" rel="stylesheet">
<link href="<?= htmlspecialchars(asset_url('assets/css/alerts-page.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div
    class="alerts-page"
    data-alerts-page
    data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
    data-dismiss-url="<?= htmlspecialchars($dismissUrl, ENT_QUOTES, 'UTF-8') ?>"
>
    <div class="alerts-page__frame">
        <header class="alerts-page__hero" aria-labelledby="alerts-page-title">
            <?php if ($heroHasImage): ?>
                <img
                    class="alerts-page__hero-img"
                    src="<?= htmlspecialchars($heroImageUrl, ENT_QUOTES, 'UTF-8') ?>"
                    alt=""
                    width="1600"
                    height="720"
                    decoding="async"
                    fetchpriority="high"
                >
            <?php endif; ?>
            <div class="alerts-page__hero-veil" aria-hidden="true"></div>
            <div class="alerts-page__hero-dots" aria-hidden="true"></div>
            <div class="alerts-page__hero-inner">
                <p class="alerts-page__brand">Athena · Transmission</p>
                <h1 id="alerts-page-title" class="alerts-page__title">Alertes &amp; annonces</h1>
                <p class="alerts-page__lead">
                    Retrouvez les messages officiels en cours pour votre communauté,
                    ainsi que les annonces dont la diffusion est terminée.
                    <?php if ($activeCount > 0): ?>
                        <strong><?= (int) $activeCount ?> message<?= $activeCount > 1 ? 's' : '' ?> actif<?= $activeCount > 1 ? 's' : '' ?></strong> pour le moment.
                    <?php else: ?>
                        <strong>Aucune transmission active</strong> pour l’instant.
                    <?php endif; ?>
                </p>
                <div class="alerts-page__hero-actions">
                    <a class="alerts-page__hero-link alerts-page__hero-link--muted" href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>">← Retour au tableau de bord</a>
                    <?php if ($alertsManageUrl !== null): ?>
                        <a class="alerts-page__hero-link" href="<?= htmlspecialchars($alertsManageUrl, ENT_QUOTES, 'UTF-8') ?>">Gérer les annonces →</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="alerts-page__metrics" aria-label="Résumé des transmissions">
            <div class="alerts-page__metric">
                <span class="alerts-page__metric-label">En cours</span>
                <span class="alerts-page__metric-value<?= $activeCount > 0 ? ' alerts-page__metric-value--accent' : '' ?>"><?= (int) $activeCount ?></span>
                <span class="alerts-page__metric-hint"><?= $activeCount === 1 ? 'Message visible aujourd’hui' : 'Messages visibles aujourd’hui' ?></span>
            </div>
            <div class="alerts-page__metric">
                <span class="alerts-page__metric-label">Urgents</span>
                <span class="alerts-page__metric-value<?= $urgentCount > 0 ? ' alerts-page__metric-value--hot' : '' ?>"><?= (int) $urgentCount ?></span>
                <span class="alerts-page__metric-hint">À traiter en priorité</span>
            </div>
            <div class="alerts-page__metric">
                <span class="alerts-page__metric-label">Historique</span>
                <span class="alerts-page__metric-value"><?= (int) $historyCount ?></span>
                <span class="alerts-page__metric-hint"><?= $pinnedCount > 0
                    ? ($pinnedCount === 1 ? 'Dont 1 message épinglé en cours' : 'Dont ' . $pinnedCount . ' messages épinglés en cours')
                    : 'Diffusions déjà terminées' ?></span>
            </div>
        </div>

        <?php if ($alertsActive !== [] && count($filterKeysPresent) > 1): ?>
        <div class="alerts-page__toolbar" data-alerts-filters>
            <p class="alerts-page__toolbar-label">Afficher</p>
            <div class="alerts-page__filters" role="group" aria-label="Filtrer les messages en cours">
                <?php foreach ($filterDefs as $key => $label): ?>
                    <?php if ($key !== 'all' && !isset($filterKeysPresent[$key])) {
                        continue;
                    } ?>
                    <button
                        type="button"
                        class="alerts-page__filter<?= $key === 'all' ? ' is-active' : '' ?>"
                        data-filter-btn
                        data-filter="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                        aria-pressed="<?= $key === 'all' ? 'true' : 'false' ?>"
                    >
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="alerts-page__sections">
            <section class="alerts-page__section" aria-labelledby="alerts-active-heading" data-alerts-active-section>
                <div class="alerts-page__section-head">
                    <p class="alerts-page__section-kicker">Canal actif</p>
                    <h2 id="alerts-active-heading" class="alerts-page__section-title">Messages en cours</h2>
                    <p class="alerts-page__section-lead">
                        <?= $activeCount === 0
                            ? 'Aucune transmission active pour le moment.'
                            : ($activeCount === 1
                                ? '1 message actif pour votre communauté.'
                                : $activeCount . ' messages actifs pour votre communauté.') ?>
                    </p>
                </div>
                <div class="alerts-page__section-body">
                    <?php if ($alertsActive === []): ?>
                        <div class="alerts-page__empty">
                            <p>Aucun message à afficher pour l’instant. Les nouvelles annonces apparaîtront ici dès leur publication.</p>
                            <a class="alerts-page__empty-link" href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>">Retourner au tableau de bord</a>
                        </div>
                    <?php else: ?>
                        <div class="alerts-page__stack alerts-page__stack--active" data-alerts-active-stack>
                            <?php foreach ($alertsActive as $item): ?>
                                <?php if (is_array($item)) {
                                    $renderCard($item, 'active');
                                } ?>
                            <?php endforeach; ?>
                        </div>
                        <p class="alerts-page__filter-empty" data-filter-empty hidden>Aucun message ne correspond à ce filtre.</p>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($alertsShowUpcoming): ?>
            <section class="alerts-page__section" aria-labelledby="alerts-upcoming-heading">
                <div class="alerts-page__section-head">
                    <p class="alerts-page__section-kicker">Programmé</p>
                    <h2 id="alerts-upcoming-heading" class="alerts-page__section-title">À venir</h2>
                    <p class="alerts-page__section-lead">Annonces déjà préparées dont la diffusion commencera plus tard.</p>
                </div>
                <div class="alerts-page__section-body">
                    <?php if ($alertsUpcoming === []): ?>
                        <div class="alerts-page__empty">
                            <p>Aucune annonce programmée pour l’instant.</p>
                        </div>
                    <?php else: ?>
                        <div class="alerts-page__stack">
                            <?php foreach ($alertsUpcoming as $item): ?>
                                <?php if (is_array($item)) {
                                    $renderCard($item, 'upcoming');
                                } ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <section class="alerts-page__section" aria-labelledby="alerts-history-heading">
                <div class="alerts-page__section-head">
                    <p class="alerts-page__section-kicker">Archives</p>
                    <h2 id="alerts-history-heading" class="alerts-page__section-title">Historique</h2>
                    <p class="alerts-page__section-lead">Annonces dont la période de diffusion est terminée.</p>
                </div>
                <div class="alerts-page__section-body">
                    <?php if ($alertsHistory === []): ?>
                        <div class="alerts-page__empty">
                            <p>Pas encore d’historique à afficher. Les messages terminés apparaîtront ici.</p>
                        </div>
                    <?php else: ?>
                        <div class="alerts-page__stack">
                            <?php foreach ($alertsHistory as $item): ?>
                                <?php if (is_array($item)) {
                                    $renderCard($item, 'archived');
                                } ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
(function () {
  var root = document.querySelector('[data-alerts-page]');
  if (!root) return;

  var csrf = root.getAttribute('data-csrf') || '';
  var dismissUrl = root.getAttribute('data-dismiss-url') || '';
  var LS = 'athena_alert_dismissed_';

  function storageKey(scope, id) {
    return LS + scope + '_' + id;
  }

  root.querySelectorAll('[data-alert-dismiss]').forEach(function (btn) {
    var scope = btn.getAttribute('data-scope') || '';
    var id = parseInt(btn.getAttribute('data-alert-id') || '0', 10);
    if (!scope || id < 1) return;
    try {
      if (localStorage.getItem(storageKey(scope, id)) === '1') {
        var card = btn.closest('[data-alert-card]');
        if (card) card.remove();
      }
    } catch (e) {}
  });

  root.addEventListener('click', function (ev) {
    var btn = ev.target && ev.target.closest ? ev.target.closest('[data-alert-dismiss]') : null;
    if (!btn || !root.contains(btn)) return;
    ev.preventDefault();
    ev.stopPropagation();
    var scope = btn.getAttribute('data-scope') || '';
    var id = parseInt(btn.getAttribute('data-alert-id') || '0', 10);
    if (!scope || id < 1 || !dismissUrl) return;
    btn.disabled = true;
    var body = new URLSearchParams();
    body.set('_csrf_token', csrf);
    body.set('scope', scope);
    body.set('alert_id', String(id));
    fetch(dismissUrl, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: body,
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json().catch(function () { return { success: false }; }).then(function (data) {
        if (!res.ok || !data || !data.success) {
          btn.disabled = false;
          return;
        }
        try { localStorage.setItem(storageKey(scope, id), '1'); } catch (e) {}
        var card = btn.closest('[data-alert-card]');
        if (card) card.remove();
        refreshFilterEmpty();
      });
    }).catch(function () {
      btn.disabled = false;
    });
  });

  var filterBtns = root.querySelectorAll('[data-filter-btn]');
  var cards = function () { return root.querySelectorAll('[data-alerts-active-stack] [data-alert-card]'); };
  var emptyEl = root.querySelector('[data-filter-empty]');

  function refreshFilterEmpty() {
    if (!emptyEl) return;
    var visible = 0;
    cards().forEach(function (card) {
      if (!card.classList.contains('is-hidden')) visible++;
    });
    var hasCards = cards().length > 0;
    emptyEl.hidden = !(hasCards && visible === 0);
  }

  filterBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var key = btn.getAttribute('data-filter') || 'all';
      filterBtns.forEach(function (b) {
        var on = b === btn;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      cards().forEach(function (card) {
        var fk = card.getAttribute('data-filter') || 'info';
        var show = key === 'all' || fk === key;
        card.classList.toggle('is-hidden', !show);
      });
      refreshFilterEmpty();
    });
  });
})();
</script>
