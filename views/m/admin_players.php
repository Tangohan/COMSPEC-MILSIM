<?php

declare(strict_types=1);

/**
 * Joueurs et ATAK de la communauté (40 par page, recherche).
 *
 * @var string $q
 * @var int $offset
 * @var int $pageSize
 * @var array{total: int, rows: list<array<string, mixed>>} $fleet
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ago = static function (int $s): string {
    if ($s < 0) {
        return 'jamais vu';
    }
    if ($s < 120) {
        return 'en ligne';
    }
    if ($s < 7200) {
        return 'vu il y a ' . intdiv($s, 60) . ' min';
    }
    if ($s < 172800) {
        return 'vu il y a ' . intdiv($s, 3600) . ' h';
    }

    return 'vu il y a ' . intdiv($s, 86400) . ' j';
};
$total = (int) $fleet['total'];
$prev = max(0, $offset - $pageSize);
$next = $offset + $pageSize;
$link = static fn (int $o): string => url('m/admin/joueurs') . '?' . http_build_query(array_filter(['q' => $q, 'offset' => $o > 0 ? $o : null]));
require __DIR__ . '/_admin_head.php';
?>
    <form class="mm-search" method="get" action="<?= $h(url('m/admin/joueurs')) ?>">
      <input class="mm-input" type="search" name="q" value="<?= $h($q) ?>" maxlength="80" placeholder="Chercher un joueur" aria-label="Chercher un joueur">
      <button class="mm-btn" type="submit">CHERCHER</button>
    </form>
    <?php if ($fleet['rows'] === []): ?>
      <section class="mm-card"><p class="mm-muted">Aucun joueur trouvé.</p></section>
    <?php else: ?>
      <ul class="mm-list">
        <?php foreach ($fleet['rows'] as $r): ?>
          <li>
            <a class="mm-row-card<?= $r['block_id'] > 0 || $r['compromise'] !== 'none' ? ' is-alert' : '' ?>" href="<?= $h(url('m/admin/joueurs/' . (int) $r['id'])) ?>">
              <span class="mm-row-card__top">
                <strong translate="no"><?= $h($r['label']) ?></strong>
                <?php if ($r['block_id'] > 0): ?><span class="mm-badge mm-badge--hot">Bloqué</span><?php endif; ?>
                <?php if ($r['compromise'] !== 'none'): ?><span class="mm-badge mm-badge--hot">Compromis</span><?php endif; ?>
                <?php if ($r['account'] === 'none'): ?><span class="mm-badge">Sans compte</span><?php endif; ?>
                <?php if ($r['account'] === 'disabled'): ?><span class="mm-badge">Compte fermé</span><?php endif; ?>
              </span>
              <span class="mm-row-card__meta mm-mono"><?= $h($ago((int) $r['seen_ago'])) ?><?php if ((int) $r['pending'] > 0): ?> · <?= (int) $r['pending'] ?> message(s) non lu(s)<?php endif; ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <nav class="mm-sub" aria-label="Pages">
        <?php if ($offset > 0): ?><a class="mm-sub__btn" href="<?= $h($link($prev)) ?>">Précédents</a><?php endif; ?>
        <?php if ($next < $total): ?><a class="mm-sub__btn" href="<?= $h($link($next)) ?>">Suivants</a><?php endif; ?>
      </nav>
    <?php endif; ?>
<?php require __DIR__ . '/_admin_foot.php'; ?>
