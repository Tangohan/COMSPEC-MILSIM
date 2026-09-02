<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $miniArticles */
$items = is_array($miniArticles ?? null) ? $miniArticles : [];
$canManage = !empty($canManageMiniArticles);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-700">Communauté</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Articles</h1>
            <p class="mt-1 text-sm text-slate-600">Notes et contenus permanents publiés par l’organisation.</p>
        </div>
        <?php if ($canManage): ?>
        <a href="<?= $h(url('back-office/articles/create')) ?>" class="inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-emerald-700">Rédiger</a>
        <?php endif; ?>
    </div>

    <?php if ($items === []): ?>
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-600">
        Aucun article publié pour le moment.
    </div>
    <?php else: ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <?php foreach ($items as $item):
            $href = (string) ($item['href'] ?? '#');
            $title = (string) ($item['title'] ?? '');
            $excerpt = (string) ($item['excerpt'] ?? '');
            $cover = $item['cover_url'] ?? null;
            $tags = is_array($item['tags'] ?? null) ? $item['tags'] : [];
            $pub = (string) ($item['published_at'] ?? '');
            $pubLabel = $pub !== '' ? date('d/m/Y', strtotime($pub) ?: time()) : '';
        ?>
        <a href="<?= $h($href) ?>" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
            <?php if (is_string($cover) && $cover !== ''): ?>
            <div class="aspect-[16/9] overflow-hidden bg-slate-100">
                <img src="<?= $h($cover) ?>" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
            </div>
            <?php endif; ?>
            <div class="p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <?php if (!empty($item['pinned'])): ?>
                    <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-amber-800">Épinglé</span>
                    <?php endif; ?>
                    <?php if ($pubLabel !== ''): ?>
                    <span class="text-[11px] text-slate-400"><?= $h($pubLabel) ?></span>
                    <?php endif; ?>
                </div>
                <h2 class="mt-1.5 text-base font-black text-slate-900 group-hover:text-emerald-800"><?= $h($title) ?></h2>
                <?php if ($excerpt !== ''): ?>
                <p class="mt-1 line-clamp-3 text-sm text-slate-600"><?= $h($excerpt) ?></p>
                <?php endif; ?>
                <?php if ($tags !== []): ?>
                <div class="mt-2 flex flex-wrap gap-1">
                    <?php foreach ($tags as $tag): ?>
                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600">#<?= $h((string) $tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
