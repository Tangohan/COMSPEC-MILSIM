<?php
declare(strict_types=1);
/**
 * Pagination d'un espace (liens : aucune page à recharger à la main). Variables : $paged, $spaceQuery, $h.
 */
$pages = (int) ($paged['pages'] ?? 1);
$current = (int) ($paged['page'] ?? 1);
if ($pages < 2) {
    return;
}
$link = static fn (int $p): string => \App\Support\EquipmentSpaces::url(array_merge($spaceQuery, ['page' => $p]));
$window = array_values(array_unique(array_filter(
    array_merge([1, $pages], range(max(1, $current - 2), min($pages, $current + 2))),
    static fn (int $p): bool => $p >= 1 && $p <= $pages
)));
sort($window);
?>
<nav class="eqx-pager" aria-label="Pages de résultats">
    <?php if ($current > 1): ?><a class="eqx-pager__step" href="<?= $h($link($current - 1)) ?>" rel="prev">← Précédent</a><?php endif; ?>
    <ul>
        <?php $prev = 0; foreach ($window as $p): ?>
        <?php if ($prev > 0 && $p - $prev > 1): ?><li aria-hidden="true">…</li><?php endif; ?>
        <li><a href="<?= $h($link($p)) ?>"<?= $p === $current ? ' aria-current="page" class="is-active"' : '' ?>><span class="visually-hidden">Page </span><?= $p ?></a></li>
        <?php $prev = $p; endforeach; ?>
    </ul>
    <?php if ($current < $pages): ?><a class="eqx-pager__step" href="<?= $h($link($current + 1)) ?>" rel="next">Suivant →</a><?php endif; ?>
</nav>
