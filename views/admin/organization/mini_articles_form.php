<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Support\MiniArticleHtml;

/** @var array<string, mixed>|null $miniArticle */
/** @var string $formAction */
$row = is_array($miniArticle ?? null) ? $miniArticle : null;
$isEdit = $row !== null;
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$title = (string) ($row['title'] ?? '');
$excerpt = (string) ($row['excerpt'] ?? '');
$bodyHtml = (string) ($row['body_html'] ?? '');
$status = (string) ($row['status'] ?? 'draft');
$pinned = !empty($row['pinned']);
$tags = [];
$rawTags = $row['tags_json'] ?? null;
if (is_string($rawTags) && $rawTags !== '') {
    $decoded = json_decode($rawTags, true);
    if (is_array($decoded)) {
        $tags = array_values(array_filter(array_map('strval', $decoded)));
    }
}
$tagsValue = implode(', ', $tags);
$coverUrl = MiniArticleHtml::publicUrl(isset($row['cover_path']) ? (string) $row['cover_path'] : null);
$gallery = [];
$rawGal = $row['gallery_json'] ?? null;
if (is_string($rawGal) && $rawGal !== '') {
    $decoded = json_decode($rawGal, true);
    if (is_array($decoded)) {
        foreach ($decoded as $path) {
            $path = (string) $path;
            $url = MiniArticleHtml::publicUrl($path);
            if ($url !== null) {
                $gallery[] = ['path' => $path, 'url' => $url];
            }
        }
    }
}
?>
<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
    <div class="mb-6">
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-700">Mini-article</p>
        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900"><?= $isEdit ? 'Modifier' : 'Nouveau' ?> mini-article</h1>
        <p class="mt-1 text-sm text-slate-600">Rédigez rapidement un contenu permanent visible par les membres.</p>
    </div>

    <form id="mini-article-form" method="post" action="<?= $h((string) $formAction) ?>" enctype="multipart/form-data" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <input type="hidden" name="_csrf_token" value="<?= $h(Csrf::token()) ?>">

        <div>
            <label for="ma-title" class="block text-xs font-black uppercase tracking-wide text-slate-500">Titre</label>
            <input type="text" id="ma-title" name="title" required maxlength="255" value="<?= $h($title) ?>"
                   class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                   placeholder="Ex. Doctrine radio — règles de la semaine">
        </div>

        <div>
            <label for="ma-tags" class="block text-xs font-black uppercase tracking-wide text-slate-500">Tags</label>
            <input type="text" id="ma-tags" name="tags" value="<?= $h($tagsValue) ?>"
                   class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                   placeholder="ops, rh, formation (séparés par des virgules)">
            <p class="mt-1 text-[11px] text-slate-500">Jusqu’à 12 tags courts pour filtrer et classer.</p>
        </div>

        <div>
            <label for="ma-excerpt" class="block text-xs font-black uppercase tracking-wide text-slate-500">Description courte</label>
            <textarea id="ma-excerpt" name="excerpt" rows="3" maxlength="2000"
                      class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                      placeholder="Résumé affiché sur le tableau de bord et la liste des articles."><?= $h($excerpt) ?></textarea>
        </div>

        <div>
            <label for="ma-body" class="block text-xs font-black uppercase tracking-wide text-slate-500">Contenu (HTML)</label>
            <textarea id="ma-body" name="body_html" rows="14"
                      class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                      placeholder="Rédigez le corps de l’article…"><?= $h($bodyHtml) ?></textarea>
            <p class="mt-1 text-[11px] text-slate-500">Éditeur riche : titres, listes, liens, tableaux. Le HTML est assaini côté serveur.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="ma-cover" class="block text-xs font-black uppercase tracking-wide text-slate-500">Image de couverture</label>
                <?php if ($coverUrl !== null): ?>
                <div class="mt-1.5 overflow-hidden rounded-xl border border-slate-200">
                    <img src="<?= $h($coverUrl) ?>" alt="" class="h-36 w-full object-cover">
                </div>
                <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="remove_cover" value="1" class="rounded border-slate-300 text-emerald-600">
                    Retirer la couverture
                </label>
                <?php endif; ?>
                <input type="file" id="ma-cover" name="cover" accept="image/jpeg,image/png,image/webp"
                       class="mt-1.5 block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-emerald-800">
            </div>
            <div>
                <label for="ma-gallery" class="block text-xs font-black uppercase tracking-wide text-slate-500">Galerie (max. 6)</label>
                <?php if ($gallery !== []): ?>
                <div class="mt-1.5 grid grid-cols-3 gap-2">
                    <?php foreach ($gallery as $g): ?>
                    <label class="relative block overflow-hidden rounded-lg border border-slate-200">
                        <img src="<?= $h($g['url']) ?>" alt="" class="h-20 w-full object-cover">
                        <span class="absolute inset-x-0 bottom-0 bg-black/60 px-1 py-0.5 text-center text-[9px] font-bold uppercase text-white">
                            <input type="checkbox" name="remove_gallery[]" value="<?= $h($g['path']) ?>" class="mr-1 align-middle"> Retirer
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <input type="file" id="ma-gallery" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple
                       class="mt-1.5 block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-slate-700">
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
                <input type="radio" name="status" value="draft" <?= $status !== 'published' ? 'checked' : '' ?> class="text-emerald-600">
                Brouillon
            </label>
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
                <input type="radio" name="status" value="published" <?= $status === 'published' ? 'checked' : '' ?> class="text-emerald-600">
                Publié
            </label>
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 sm:ml-auto">
                <input type="checkbox" name="pinned" value="1" <?= $pinned ? 'checked' : '' ?> class="rounded border-slate-300 text-emerald-600">
                Épingler en tête
            </label>
        </div>

        <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            <button type="submit" class="inline-flex rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white hover:bg-emerald-700">
                <?= $isEdit ? 'Enregistrer' : 'Créer' ?>
            </button>
            <a href="<?= $h(url('back-office/articles')) ?>" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-slate-700 hover:bg-slate-50">Retour</a>
        </div>
    </form>
</div>
<script src="<?= htmlspecialchars(asset_url('assets/js/mini_article_editor.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
