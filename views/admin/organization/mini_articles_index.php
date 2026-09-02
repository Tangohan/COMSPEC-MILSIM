<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Support\MiniArticleHtml;

/** @var list<array<string, mixed>> $miniArticles */
$rows = is_array($miniArticles ?? null) ? $miniArticles : [];
$schemaReady = !empty($miniArticlesSchemaReady);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="mx-auto max-w-5xl px-4 py-6 sm:px-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-700">Organisation</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Mini-articles</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-600">Contenus permanents pour la communauté : titre, tags, description, images et mise en forme HTML.</p>
        </div>
        <a href="<?= $h(url('back-office/articles/create')) ?>" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white hover:bg-emerald-700">Nouvel article</a>
    </div>

    <?php if (!$schemaReady): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
        Table non installée. Lancez les migrations du portail pour activer les mini-articles.
    </div>
    <?php elseif ($rows === []): ?>
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
        <p class="text-sm font-semibold text-slate-800">Aucun article pour le moment.</p>
        <p class="mt-1 text-sm text-slate-500">Rédigez un premier mini-article pour le tableau de bord et la page Articles.</p>
        <a href="<?= $h(url('back-office/articles/create')) ?>" class="mt-5 inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-xs font-black uppercase tracking-wide text-white">Rédiger</a>
    </div>
    <?php else: ?>
    <ul class="space-y-3">
        <?php foreach ($rows as $row):
            $id = (int) ($row['id'] ?? 0);
            $title = (string) ($row['title'] ?? '');
            $status = (string) ($row['status'] ?? 'draft');
            $slug = (string) ($row['slug'] ?? '');
            $excerpt = trim((string) ($row['excerpt'] ?? ''));
            $tags = [];
            $rawTags = $row['tags_json'] ?? null;
            if (is_string($rawTags) && $rawTags !== '') {
                $decoded = json_decode($rawTags, true);
                if (is_array($decoded)) {
                    $tags = array_values(array_filter(array_map('strval', $decoded)));
                }
            }
            $cover = MiniArticleHtml::publicUrl(isset($row['cover_path']) ? (string) $row['cover_path'] : null);
            $published = (string) ($row['published_at'] ?? '');
            $pubLabel = $published !== '' ? date('d/m/Y H:i', strtotime($published) ?: time()) : '—';
        ?>
        <li class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-0 sm:flex-row">
                <?php if ($cover !== null): ?>
                <div class="sm:w-44 shrink-0 bg-slate-100">
                    <img src="<?= $h($cover) ?>" alt="" class="h-36 w-full object-cover sm:h-full">
                </div>
                <?php endif; ?>
                <div class="min-w-0 flex-1 p-4 sm:p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-wide <?= $status === 'published' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600' ?>">
                            <?= $status === 'published' ? 'Publié' : 'Brouillon' ?>
                        </span>
                        <?php if (!empty($row['pinned'])): ?>
                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-amber-800">Épinglé</span>
                        <?php endif; ?>
                        <span class="text-[11px] text-slate-400"><?= $h($pubLabel) ?></span>
                    </div>
                    <h2 class="mt-2 text-base font-black text-slate-900"><?= $h($title) ?></h2>
                    <?php if ($excerpt !== ''): ?>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-600"><?= $h($excerpt) ?></p>
                    <?php endif; ?>
                    <?php if ($tags !== []): ?>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <?php foreach ($tags as $tag): ?>
                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600">#<?= $h($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="<?= $h(url('back-office/articles/' . $id . '/edit')) ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 hover:border-emerald-300 hover:bg-emerald-50">Modifier</a>
                        <?php if ($status === 'published' && $slug !== ''): ?>
                        <a href="<?= $h(url('articles/' . rawurlencode($slug))) ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 hover:border-emerald-300 hover:bg-emerald-50">Voir</a>
                        <?php endif; ?>
                        <form method="post" action="<?= $h(url('back-office/articles/' . $id . '/delete')) ?>" onsubmit="return confirm('Supprimer cet article ?');">
                            <input type="hidden" name="_csrf_token" value="<?= $h(Csrf::token()) ?>">
                            <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-800 hover:bg-rose-100">Supprimer</button>
                        </form>
                    </div>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
