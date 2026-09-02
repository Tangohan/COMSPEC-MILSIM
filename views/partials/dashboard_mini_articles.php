<?php
declare(strict_types=1);

/**
 * Liste compacte des mini-articles publiés sur le tableau de bord membre.
 *
 * @var list<array<string, mixed>> $dashboard_mini_articles
 */

$items = is_array($dashboard_mini_articles ?? null) ? $dashboard_mini_articles : [];
if ($items === []) {
    return;
}
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<section class="dash-hub-panel" id="dashboard-mini-articles" aria-labelledby="dash-mini-articles-title">
    <div class="dash-hub-panel__head">
        <div>
            <p class="dash-hub-panel__kicker">Communauté</p>
            <h2 id="dash-mini-articles-title" class="dash-hub-panel__title">Articles</h2>
            <p class="dash-hub-panel__lead">Notes et contenus permanents publiés par l’organisation.</p>
        </div>
        <a href="<?= $h(url('articles')) ?>" class="dash-hub-panel__ghost">Tout voir</a>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <?php foreach (array_slice($items, 0, 4) as $item):
            $href = (string) ($item['href'] ?? '#');
            $title = (string) ($item['title'] ?? '');
            $excerpt = (string) ($item['excerpt'] ?? '');
            $cover = is_string($item['cover_url'] ?? null) ? (string) $item['cover_url'] : '';
            $tags = is_array($item['tags'] ?? null) ? $item['tags'] : [];
        ?>
        <a href="<?= $h($href) ?>" class="group flex gap-3 overflow-hidden rounded-xl border border-slate-200 bg-white p-3 transition hover:border-emerald-300 hover:bg-emerald-50/40">
            <?php if ($cover !== ''): ?>
            <div class="h-16 w-20 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                <img src="<?= $h($cover) ?>" alt="" class="h-full w-full object-cover">
            </div>
            <?php endif; ?>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-black text-slate-900 group-hover:text-emerald-800"><?= $h($title) ?></p>
                <?php if ($excerpt !== ''): ?>
                <p class="mt-0.5 line-clamp-2 text-xs text-slate-600"><?= $h($excerpt) ?></p>
                <?php endif; ?>
                <?php if ($tags !== []): ?>
                <p class="mt-1 truncate text-[10px] font-bold uppercase tracking-wide text-slate-400">
                    <?php foreach (array_slice($tags, 0, 3) as $i => $tag): ?><?= $i > 0 ? ' · ' : '' ?>#<?= $h((string) $tag) ?><?php endforeach; ?>
                </p>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
