<?php
declare(strict_types=1);

/** @var array<string, mixed> $miniArticle */
$item = is_array($miniArticle ?? null) ? $miniArticle : [];
$canManage = !empty($canManageMiniArticles);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$title = (string) ($item['title'] ?? '');
$excerpt = (string) ($item['excerpt'] ?? '');
$body = (string) ($item['body_html'] ?? '');
$cover = is_string($item['cover_url'] ?? null) ? (string) $item['cover_url'] : '';
$gallery = is_array($item['gallery'] ?? null) ? $item['gallery'] : [];
$tags = is_array($item['tags'] ?? null) ? $item['tags'] : [];
$pub = (string) ($item['published_at'] ?? '');
$pubLabel = $pub !== '' ? date('d/m/Y H:i', strtotime($pub) ?: time()) : '';
$id = (int) ($item['id'] ?? 0);
?>
<article class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <div class="mb-5 flex flex-wrap items-center gap-3 text-xs">
        <a href="<?= $h(url('articles')) ?>" class="font-bold text-emerald-700 hover:underline">← Articles</a>
        <?php if ($canManage && $id > 0): ?>
        <a href="<?= $h(url('back-office/articles/' . $id . '/edit')) ?>" class="font-bold text-slate-500 hover:text-slate-800">Modifier</a>
        <?php endif; ?>
    </div>

    <?php if ($cover !== ''): ?>
    <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
        <img src="<?= $h($cover) ?>" alt="" class="max-h-[28rem] w-full object-cover">
    </div>
    <?php endif; ?>

    <header class="mb-6 border-b border-slate-200 pb-5">
        <?php if (!empty($item['pinned'])): ?>
        <p class="mb-2 text-[10px] font-black uppercase tracking-[0.24em] text-amber-700">Épinglé</p>
        <?php endif; ?>
        <h1 class="text-3xl font-black tracking-tight text-slate-900"><?= $h($title) ?></h1>
        <?php if ($excerpt !== ''): ?>
        <p class="mt-3 text-base leading-relaxed text-slate-600"><?= $h($excerpt) ?></p>
        <?php endif; ?>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <?php if ($pubLabel !== ''): ?>
            <span class="text-[11px] font-semibold text-slate-400"><?= $h($pubLabel) ?></span>
            <?php endif; ?>
            <?php foreach ($tags as $tag): ?>
            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600">#<?= $h((string) $tag) ?></span>
            <?php endforeach; ?>
        </div>
    </header>

    <div class="prose prose-slate max-w-none prose-headings:font-black prose-a:text-emerald-700">
        <?= $body ?>
    </div>

    <?php if ($gallery !== []): ?>
    <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <?php foreach ($gallery as $url): ?>
        <a href="<?= $h((string) $url) ?>" target="_blank" rel="noopener" class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
            <img src="<?= $h((string) $url) ?>" alt="" class="aspect-square w-full object-cover">
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</article>
