<?php
declare(strict_types=1);

/**
 * Articles de la communauté : l’épinglé le plus récent en grand, les autres en cartes, recherche et filtre par mot-clé.
 *
 * @var list<array<string, mixed>> $miniArticles
 */
$items = is_array($miniArticles ?? null) ? $miniArticles : [];
$canManage = !empty($canManageMiniArticles);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$dateLabel = static function (string $raw): string {
    $t = $raw !== '' ? strtotime($raw) : false;
    if (!$t) {
        return '';
    }
    $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    return (int) date('j', $t) . ' ' . $months[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
};
$allTags = [];
foreach ($items as $it) {
    foreach ((array) ($it['tags'] ?? []) as $t) {
        $t = trim((string) $t);
        if ($t !== '') {
            $allTags[mb_strtolower($t)] = $t;
        }
    }
}
ksort($allTags);
$featuredIndex = null;
foreach ($items as $i => $it) {
    if (!empty($it['pinned'])) {
        $featuredIndex = $i;
        break;
    }
}
?>
<link href="<?= $h(asset_url('assets/css/mini-articles.css')) ?>" rel="stylesheet">
<div class="ma-pub" data-ma-list>
    <div class="ma-pub__head">
        <div>
            <p class="ma-pub__kicker">Communauté</p>
            <h1 class="ma-pub__title">Articles</h1>
            <p class="ma-pub__sub">Règles, guides et présentations publiés par l’encadrement.</p>
        </div>
        <?php if ($canManage): ?>
        <a href="<?= $h(url('back-office/articles/create')) ?>" class="ma-pub__btn">Rédiger un article</a>
        <?php endif; ?>
    </div>

    <?php if ($items === []): ?>
    <div class="ma-pub__empty">
        <strong>Aucun article pour l’instant</strong>
        Les règles, guides et présentations de la communauté apparaîtront ici.
    </div>
    <?php else: ?>
    <?php if (count($items) > 3 || $allTags !== []): ?>
    <div class="ma-pub__tools">
        <input type="search" class="ma-pub__search" placeholder="Rechercher un article" aria-label="Rechercher un article" data-ma-search>
        <?php if ($allTags !== []): ?>
        <button type="button" class="ma-pub__tag" aria-pressed="true" data-ma-tag="">Tous</button>
        <?php foreach (array_slice($allTags, 0, 12, true) as $key => $label): ?>
        <button type="button" class="ma-pub__tag" aria-pressed="false" data-ma-tag="<?= $h($key) ?>">#<?= $h($label) ?></button>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="ma-pub__grid">
        <?php foreach ($items as $i => $item):
            $href = (string) ($item['href'] ?? '#');
            $title = (string) ($item['title'] ?? '');
            $excerpt = (string) ($item['excerpt'] ?? '');
            $cover = is_string($item['cover_url'] ?? null) ? (string) $item['cover_url'] : '';
            $tags = is_array($item['tags'] ?? null) ? $item['tags'] : [];
            $pubLabel = $dateLabel((string) ($item['published_at'] ?? ''));
            $minutes = (int) ($item['reading_minutes'] ?? 0);
            $meta = trim($pubLabel . ($minutes > 0 ? ' · ' . $minutes . ' min de lecture' : ''), ' ·');
            $tagKeys = implode('|', array_map(static fn ($t): string => mb_strtolower(trim((string) $t)), $tags));
            $search = mb_strtolower($title . ' ' . $excerpt . ' ' . implode(' ', array_map('strval', $tags)));
        ?>
        <a href="<?= $h($href) ?>" class="ma-card<?= $i === $featuredIndex ? ' ma-card--featured' : '' ?>" data-ma-card data-tags="<?= $h($tagKeys) ?>" data-search="<?= $h($search) ?>">
            <?php if ($cover !== ''): ?>
            <div class="ma-card__cover" style="background-image:url('<?= $h($cover) ?>')">
            <?php else: ?>
            <div class="ma-card__cover ma-card__cover--empty"><span aria-hidden="true"><?= $h(mb_strtoupper(mb_substr($title, 0, 1))) ?></span>
            <?php endif; ?>
                <?php if (!empty($item['pinned'])): ?><span class="ma-card__pin">Épinglé</span><?php endif; ?>
            </div>
            <div class="ma-card__body">
                <?php if ($meta !== ''): ?><span class="ma-card__meta"><?= $h($meta) ?></span><?php endif; ?>
                <h2 class="ma-card__title"><?= $h($title) ?></h2>
                <?php if ($excerpt !== ''): ?><p class="ma-card__excerpt"><?= $h($excerpt) ?></p><?php endif; ?>
                <?php if ($tags !== []): ?>
                <div class="ma-card__tags">
                    <?php foreach (array_slice($tags, 0, 4) as $tag): ?><span class="ma-chip">#<?= $h((string) $tag) ?></span><?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <p class="ma-pub__none" data-ma-none>Aucun article ne correspond.</p>
    <?php endif; ?>
</div>
<script>
(function () {
  var root = document.querySelector('[data-ma-list]');
  if (!root) return;
  var input = root.querySelector('[data-ma-search]');
  var tags = root.querySelectorAll('[data-ma-tag]');
  var cards = root.querySelectorAll('[data-ma-card]');
  var none = root.querySelector('[data-ma-none]');
  var tag = '';
  function apply() {
    var q = (input && input.value || '').trim().toLowerCase();
    var shown = 0;
    cards.forEach(function (c) {
      var okTag = tag === '' || ('|' + c.getAttribute('data-tags') + '|').indexOf('|' + tag + '|') !== -1;
      var ok = okTag && (q === '' || (c.getAttribute('data-search') || '').indexOf(q) !== -1);
      c.hidden = !ok;
      if (ok) shown++;
    });
    if (none) none.style.display = shown === 0 ? 'block' : 'none';
  }
  tags.forEach(function (b) {
    b.addEventListener('click', function () {
      tag = b.getAttribute('data-ma-tag');
      tags.forEach(function (o) { o.setAttribute('aria-pressed', o === b ? 'true' : 'false'); });
      apply();
    });
  });
  if (input) input.addEventListener('input', apply);
})();
</script>
