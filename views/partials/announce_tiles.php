<?php
declare(strict_types=1);

/**
 * Zone compacte alertes & annonces — tuiles sombres dépliables (pattern rail).
 *
 * @var list<array{scope?:string,kind?:string,category?:string,title?:string,body?:string,cta_label?:?string,cta_url?:?string}> $announce_items
 * @var string|null $announce_manage_url
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
$announceHeading = (string) ($announce_heading ?? 'Alertes & annonces');
$announceKicker = (string) ($announce_kicker ?? 'Transmission');
$announceEmpty = (string) ($announce_empty ?? 'Aucune alerte ni annonce pour le moment.');
$announceId = (string) ($announce_id ?? 'dashboard-announce');
$announceStartOpen = (bool) ($announce_start_open ?? true);

$kindLabelFr = static function (string $kind): string {
    return match (strtolower(trim($kind))) {
        'urgent' => 'Urgent',
        'novelty' => 'Nouveau',
        'discount' => 'Promotion',
        'notice' => 'Annonce',
        'forum_pin' => 'Épinglé',
        default => 'Information',
    };
};

$announcePriority = static function (array $item): int {
    return match (strtolower(trim((string) ($item['kind'] ?? 'info')))) {
        'urgent' => 0,
        'forum_pin' => 1,
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
?>
<section
    id="<?= htmlspecialchars($announceId, ENT_QUOTES, 'UTF-8') ?>"
    class="dash-announce scroll-mt-24<?= $announceStartOpen ? ' is-open' : '' ?>"
    aria-labelledby="<?= htmlspecialchars($announceId, ENT_QUOTES, 'UTF-8') ?>-title"
    data-announce-collapse
    data-announce-persist="<?= htmlspecialchars($announceId, ENT_QUOTES, 'UTF-8') ?>"
    data-announce-default="<?= $announceStartOpen ? 'open' : 'closed' ?>"
>
    <div class="dash-announce__brief">
        <button
            type="button"
            class="dash-announce__toggle"
            data-announce-toggle
            aria-expanded="<?= $announceStartOpen ? 'true' : 'false' ?>"
            aria-controls="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
        >
            <span class="dash-announce__brief-label">
                <p class="dash-announce__brief-kicker"><?= htmlspecialchars($announceKicker, ENT_QUOTES, 'UTF-8') ?></p>
                <h2 id="<?= htmlspecialchars($announceId, ENT_QUOTES, 'UTF-8') ?>-title" class="dash-announce__brief-title"><?= htmlspecialchars($announceHeading, ENT_QUOTES, 'UTF-8') ?></h2>
            </span>
            <p class="dash-announce__brief-status"><?= htmlspecialchars($statusLine, ENT_QUOTES, 'UTF-8') ?></p>
            <i class="dash-announce__meta" data-announce-meta aria-hidden="true"><?= $announceStartOpen ? '−' : '—' ?></i>
        </button>
        <?php if ($announceManageUrl !== null): ?>
            <a href="<?= htmlspecialchars($announceManageUrl, ENT_QUOTES, 'UTF-8') ?>" class="dash-announce__brief-link">Gérer →</a>
        <?php endif; ?>
    </div>

    <div
        id="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
        class="dash-announce__panel"
        data-announce-panel
        <?= $announceStartOpen ? '' : 'hidden' ?>
    >
        <?php if ($announceItems === []): ?>
            <p class="dash-announce__empty"><?= htmlspecialchars($announceEmpty, ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
            <div class="dash-announce__grid">
                <?php foreach ($announceItems as $item): ?>
                    <?php
                    $kind = strtolower(trim((string) ($item['kind'] ?? 'info')));
                    $isPinned = in_array($kind, ['forum_pin', 'notice'], true);
                    if ($kind === 'forum_pin') {
                        $kind = 'notice';
                    }
                    if (!in_array($kind, ['info', 'urgent', 'novelty', 'discount', 'notice'], true)) {
                        $kind = 'info';
                    }
                    $category = trim((string) ($item['category'] ?? ''));
                    if ($category === '') {
                        $category = $kindLabelFr($kind);
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
                    $hrefAttr = $ctaUrl !== null ? ' href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '"' : '';
                    ?>
                    <<?= $tag ?> class="dash-announce-tile dash-announce-tile--<?= htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') ?><?= $isPlatform ? ' dash-announce-tile--verified' : '' ?>"<?= $hrefAttr ?>>
                        <div class="dash-announce-tile__visual" aria-hidden="true">
                            <span class="dash-announce-tile__glyph"></span>
                            <?php if ($isPlatform): ?>
                            <span class="dash-announce-tile__verified-mark" title="Annonce officielle du site">
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="dash-announce-tile__panel">
                            <div class="dash-announce-tile__meta">
                                <p class="dash-announce-tile__kind">
                                    <?php if ($isPinned): ?><span aria-hidden="true">📌</span> <?php endif; ?>
                                    <?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <?php if ($isPlatform): ?>
                                <span class="dash-announce-tile__verified" title="Annonce officielle du site Athena">
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <span>Site vérifié</span>
                                </span>
                                <?php endif; ?>
                            </div>
                            <p class="dash-announce-tile__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if ($body !== ''): ?>
                                <p class="dash-announce-tile__body"><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php if ($ctaLabel !== null): ?>
                                <span class="dash-announce-tile__cta"><?= htmlspecialchars(mb_strtoupper($ctaLabel, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?> →</span>
                            <?php elseif ($ctaUrl !== null): ?>
                                <span class="dash-announce-tile__cta">OUVRIR →</span>
                            <?php endif; ?>
                        </div>
                    </<?= $tag ?>>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
    if (open) {
      panel.removeAttribute('hidden');
    } else {
      panel.setAttribute('hidden', '');
    }
    if (meta) meta.textContent = open ? '−' : '—';
    try { localStorage.setItem(persistKey, open ? '1' : '0'); } catch (e) {}
  }

  var stored = null;
  try { stored = localStorage.getItem(persistKey); } catch (e) {}
  if (stored === '1') apply(true);
  else if (stored === '0') apply(false);
  else apply(defOpen);

  toggle.addEventListener('click', function () {
    apply(toggle.getAttribute('aria-expanded') !== 'true');
  });
})();
</script>
