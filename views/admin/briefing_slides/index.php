<?php
declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $briefingSlides
 * @var string $briefingSlidesApiUrl
 */

$rows = is_array($briefingSlides ?? null) ? $briefingSlides : [];
$apiUrl = (string) ($briefingSlidesApiUrl ?? '');
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
?>
<div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
    <div>
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-500">Tactique</p>
        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Diapositives de briefing</h1>
        <p class="mt-2 max-w-2xl text-sm text-slate-600">
            Images consultables en jeu (écrans/tableaux placés dans Eden Editor, ou action « Consulter le briefing »).
            L’extension Arma télécharge et met en cache la diapositive active la plus récente à chaque rafraîchissement.
        </p>
    </div>

    <?php if ($flashSuccess): ?>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars((string) $flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($apiUrl !== ''): ?>
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
        Endpoint utilisé par l’extension Arma (fonction <code class="font-mono">GetBriefingSlides</code>) :
        <code class="font-mono font-bold text-slate-800"><?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?>?tenant_id=…</code>
    </div>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wide text-slate-900">Ajouter une diapositive</h2>
        <form method="post" action="<?= htmlspecialchars(url('back-office/atak/briefing-slides'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="mt-4 grid gap-4 sm:grid-cols-4">
            <?= \App\Core\Csrf::field() ?>
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="bs-title">Titre</label>
                <input type="text" id="bs-title" name="title" maxlength="160" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Ex. Ordre d’opération — Phase 1">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="bs-order">Ordre</label>
                <input type="number" id="bs-order" name="sort_order" value="0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">
                    Active
                </label>
            </div>
            <div class="sm:col-span-3">
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="bs-image">Image (JPG ou PNG, 12 Mo max — JPG recommandé pour l’affichage in-game)</label>
                <input type="file" id="bs-image" name="image_file" accept="image/jpeg,image/png" required class="mt-1 w-full text-sm">
            </div>
            <div class="flex items-end sm:col-span-1">
                <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white hover:bg-slate-800">Ajouter</button>
            </div>
        </form>
    </section>

    <section class="space-y-4">
        <?php if ($rows === []): ?>
        <div class="rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
            <p class="text-sm text-slate-600">Aucune diapositive pour le moment.</p>
        </div>
        <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <?php
            $id = (int) ($row['id'] ?? 0);
            $title = trim((string) ($row['title'] ?? ''));
            $imagePath = trim((string) ($row['image_path'] ?? ''));
            $imageUrl = $imagePath !== '' ? asset_url($imagePath) : null;
            $isActive = !empty($row['is_active']);
            $sortOrder = (int) ($row['sort_order'] ?? 0);
            ?>
        <article class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row">
            <div class="h-32 w-full shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-slate-100 sm:w-48">
                <?php if ($imageUrl): ?>
                <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="h-full w-full object-cover">
                <?php endif; ?>
            </div>
            <form method="post" action="<?= htmlspecialchars(url('back-office/atak/briefing-slides/' . $id . '/update'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="grid flex-1 gap-3 sm:grid-cols-4">
                <?= \App\Core\Csrf::field() ?>
                <div class="sm:col-span-2">
                    <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-500">Titre</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" maxlength="160" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-500">Ordre</label>
                    <input type="number" name="sort_order" value="<?= $sortOrder ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?> class="rounded border-slate-300">
                        Active
                    </label>
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-500">Remplacer l’image (optionnel)</label>
                    <input type="file" name="image_file" accept="image/jpeg,image/png" class="mt-1 w-full text-xs">
                </div>
                <div class="flex items-end justify-end gap-2 sm:col-span-1">
                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-800 hover:bg-slate-100">Enregistrer</button>
                </div>
            </form>
            <form method="post" action="<?= htmlspecialchars(url('back-office/atak/briefing-slides/' . $id . '/delete'), ENT_QUOTES, 'UTF-8') ?>" class="shrink-0">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-rose-800 hover:bg-rose-100" onclick="return confirm('Supprimer cette diapositive ?');">Supprimer</button>
            </form>
        </article>
        <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
