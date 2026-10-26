<?php
declare(strict_types=1);

/**
 * Alertes & annonces sous l’en-tête du tableau de bord : une ligne d’en-tête (compteur, tout voir, gérer, replier)
 * et des cartes lisibles, l’urgent en pleine largeur.
 *
 * @var list<array{scope?:string,kind?:string,category?:string,title?:string,body?:string,cta_label?:?string,cta_url?:?string}> $announce_items
 * @var string|null $announce_manage_url
 * @var string|null $announce_list_url
 * @var string $announce_heading
 * @var string $announce_kicker
 * @var string $announce_empty
 * @var string $announce_id
 * @var bool $announce_start_open
 */

$announceItems = is_array($announce_items ?? null) ? $announce_items : [];
$announceManageUrl = isset($announce_manage_url) && is_string($announce_manage_url) && $announce_manage_url !== ''
    ? $announce_manage_url
    : null;
$announceListUrl = isset($announce_list_url) && is_string($announce_list_url) && $announce_list_url !== ''
    ? $announce_list_url
    : null;
$announceHeading = (string) ($announce_heading ?? 'Alertes & annonces');
$announceKicker = (string) ($announce_kicker ?? 'Transmission');
$announceEmpty = (string) ($announce_empty ?? 'Aucune alerte ni annonce pour le moment.');
$announceId = (string) ($announce_id ?? 'dashboard-announce');

$kindLabelFr = static function (string $kind): string {
    return match (strtolower(trim($kind))) {
        'urgent' => 'Urgent',
        'novelty' => 'Nouveau',
        'discount' => 'Promotion',
        'notice' => 'Annonce',
        default => 'Information',
    };
};

$announcePriority = static function (array $item): int {
    return match (strtolower(trim((string) ($item['kind'] ?? 'info')))) {
        'urgent', 'security' => 0,
        'notice' => 2,
        'novelty' => 3,
        'discount' => 4,
        default => 5,
    };
};
usort($announceItems, static function (array $a, array $b) use ($announcePriority): int {
    return $announcePriority($a) <=> $announcePriority($b);
});

$count = count($announceItems);
$statusLine = $count === 0
    ? 'Aucune transmission en cours'
    : ($count === 1
        ? '1 message actif'
        : $count . ' messages actifs');
$panelId = $announceId . '-panel';
$titleId = $announceId . '-title';
/* Vide : replié par défaut (évite double message + bandeau creux). */
$announceStartOpen = isset($announce_start_open)
    ? (bool) $announce_start_open
    : ($count > 0);
?>
<?php
$announceIcons = [
    'urgent' => '<path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>',
    'notice' => '<path d="M12 17v5M9 10.8V4h6v6.8l2.6 3.2H6.4L9 10.8Z"/>',
    'novelty' => '<path d="m12 3 2.4 5.6L20 9.3l-4.3 3.9 1.3 5.8L12 16l-5 3 1.3-5.8L4 9.3l5.6-.7L12 3Z"/>',
    'discount' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8ZM7.5 7.5h.01"/>',
    'info' => '<path d="M12 16v-4m0-4h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z"/>',
];
$urgentCount = 0;
foreach ($announceItems as $it) {
    if (in_array(strtolower(trim((string) ($it['kind'] ?? ''))), ['urgent', 'security'], true)) {
        $urgentCount++;
    }
}
$hEsc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<section
    id="<?= $hEsc($announceId) ?>"
    class="dash-announce scroll-mt-24<?= $announceStartOpen ? ' is-open' : '' ?><?= $count === 0 ? ' dash-announce--empty' : '' ?><?= $urgentCount > 0 ? ' dash-announce--has-urgent' : '' ?>"
    aria-labelledby="<?= $hEsc($titleId) ?>"
    data-announce-collapse
    data-announce-persist="<?= $hEsc($announceId) ?>"
    data-announce-default="<?= $announceStartOpen ? 'open' : 'closed' ?>"
>
    <div class="dash-announce__shell">
        <header class="dash-announce__head">
            <span class="dash-announce__bell" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>
                <?php if ($count > 0): ?><span class="dash-announce__count"><?= $count ?></span><?php endif; ?>
            </span>
            <div class="dash-announce__heading">
                <h2 id="<?= $hEsc($titleId) ?>" class="dash-announce__title"><?= $hEsc($announceHeading) ?></h2>
                <p class="dash-announce__status"><?= $hEsc($statusLine) ?><?= $urgentCount > 0 ? ' · <strong>' . $urgentCount . ' urgent' . ($urgentCount > 1 ? 's' : '') . '</strong>' : '' ?></p>
            </div>
            <div class="dash-announce__actions">
                <?php if ($announceListUrl !== null): ?>
                <a href="<?= $hEsc($announceListUrl) ?>" class="dash-announce__link" data-announce-list-link>Tout voir</a>
                <?php endif; ?>
                <?php if ($announceManageUrl !== null): ?>
                <a href="<?= $hEsc($announceManageUrl) ?>" class="dash-announce__link dash-announce__link--ghost">Gérer</a>
                <?php endif; ?>
                <button
                    type="button"
                    class="dash-announce__toggle"
                    data-announce-toggle
                    aria-expanded="<?= $announceStartOpen ? 'true' : 'false' ?>"
                    aria-controls="<?= $hEsc($panelId) ?>"
                    aria-label="<?= $announceStartOpen ? 'Replier les messages' : 'Déplier les messages' ?>"
                >
                    <svg class="dash-announce__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    <i class="dash-announce__meta" data-announce-meta aria-hidden="true" hidden><?= $announceStartOpen ? '−' : '+' ?></i>
                </button>
            </div>
        </header>

        <div
            id="<?= $hEsc($panelId) ?>"
            class="dash-announce__panel"
            data-announce-panel
            <?= $announceStartOpen ? '' : 'hidden' ?>
        >
            <?php if ($announceItems === []): ?>
                <p class="dash-announce__empty"><?= $hEsc($announceEmpty) ?></p>
            <?php else: ?>
                <div class="dash-announce__grid">
                    <?php foreach ($announceItems as $item): ?>
                        <?php
                        $kind = strtolower(trim((string) ($item['kind'] ?? 'info')));
                        $isPinned = $kind === 'notice';
                        $rawKind = $kind;
                        /* Types étendus des annonces communauté : même couleur que la famille la plus proche, libellé d’origine. */
                        $kind = match ($kind) {
                            'security' => 'urgent',
                            'maintenance' => 'notice',
                            'event', 'training', 'recruitment' => 'novelty',
                            default => $kind,
                        };
                        if (!in_array($kind, ['info', 'urgent', 'novelty', 'discount', 'notice'], true)) {
                            $kind = 'info';
                        }
                        $category = trim((string) ($item['category'] ?? ''));
                        if ($category === '') {
                            $category = (string) (\App\Support\TenantAlertVisuals::kinds()[$rawKind]['label'] ?? $kindLabelFr($kind));
                        }
                        $title = trim((string) ($item['title'] ?? ''));
                        $body = trim((string) ($item['body'] ?? ''));
                        $ctaLabel = isset($item['cta_label']) && is_string($item['cta_label']) && $item['cta_label'] !== ''
                            ? (string) $item['cta_label']
                            : null;
                        $ctaUrl = isset($item['cta_url']) && is_string($item['cta_url']) && $item['cta_url'] !== ''
                            ? (string) $item['cta_url']
                            : null;
                        $isPlatform = strtolower(trim((string) ($item['scope'] ?? ''))) === 'platform';
                        if ($title === '') {
                            continue;
                        }
                        $tag = ($ctaUrl !== null) ? 'a' : 'article';
                        $hrefAttr = $ctaUrl !== null ? ' href="' . $hEsc($ctaUrl) . '"' : '';
                        ?>
                        <<?= $tag ?> class="dash-announce-tile dash-announce-tile--<?= $hEsc($kind) ?><?= $isPlatform ? ' dash-announce-tile--verified' : '' ?>"<?= $hrefAttr ?>>
                            <span class="dash-announce-tile__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $announceIcons[$kind] ?></svg>
                            </span>
                            <span class="dash-announce-tile__panel">
                                <span class="dash-announce-tile__meta">
                                    <span class="dash-announce-tile__kind"><?= $isPinned ? 'Épinglé · ' : '' ?><?= $hEsc($category) ?></span>
                                    <?php if ($isPlatform): ?>
                                    <span class="dash-announce-tile__verified" title="Annonce officielle du site Athena">
                                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        Site Athena
                                    </span>
                                    <?php endif; ?>
                                </span>
                                <span class="dash-announce-tile__title"><?= $hEsc($title) ?></span>
                                <?php if ($body !== ''): ?>
                                    <span class="dash-announce-tile__body"><?= $hEsc($body) ?></span>
                                <?php endif; ?>
                                <?php if ($ctaUrl !== null): ?>
                                    <span class="dash-announce-tile__cta"><?= $hEsc($ctaLabel ?? 'Ouvrir') ?> <span aria-hidden="true">→</span></span>
                                <?php endif; ?>
                            </span>
                        </<?= $tag ?>>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<script>
(function () {
  var root = document.getElementById(<?= json_encode($announceId, JSON_UNESCAPED_UNICODE) ?>);
  if (!root || root.getAttribute('data-announce-bound') === '1') return;
  root.setAttribute('data-announce-bound', '1');

  var toggle = root.querySelector('[data-announce-toggle]');
  var panel = root.querySelector('[data-announce-panel]');
  var meta = root.querySelector('[data-announce-meta]');
  if (!toggle || !panel) return;

  var persistKey = 'athena_announce_open_' + (root.getAttribute('data-announce-persist') || 'default');
  var defOpen = root.getAttribute('data-announce-default') !== 'closed';

  function apply(open) {
    root.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Replier les messages' : 'Déplier les messages');
    if (open) {
      panel.removeAttribute('hidden');
    } else {
      panel.setAttribute('hidden', '');
    }
    if (meta) meta.textContent = open ? '−' : '+';
    try { localStorage.setItem(persistKey, open ? '1' : '0'); } catch (e) {}
  }

  var stored = null;
  try { stored = localStorage.getItem(persistKey); } catch (e) {}
  if (stored === '1') apply(true);
  else if (stored === '0') apply(false);
  else apply(defOpen);

  toggle.addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    apply(toggle.getAttribute('aria-expanded') !== 'true');
  });
})();
</script>
