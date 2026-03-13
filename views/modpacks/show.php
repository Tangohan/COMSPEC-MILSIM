<?php
$modpack = $modpack ?? null;
if (!$modpack) {
    echo '<div class="max-w-4xl mx-auto px-6 py-12"><p>Modpack non trouvé.</p></div>';
    return;
}
$images = $modpack['images'] ?? [];
$releasedAt = !empty($modpack['released_at']) ? date('d.m.Y', strtotime($modpack['released_at'])) : '—';
$updatedAt = !empty($modpack['updated_at']) ? date('d.m.Y', strtotime($modpack['updated_at'])) : '—';
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <nav class="mb-6 text-sm text-slate-500">
        <a href="<?= url('modpacks') ?>" class="hover:text-slate-700">Modpacks</a>
        <span class="mx-2">/</span>
        <span class="text-slate-900"><?= htmlspecialchars($modpack['name']) ?></span>
    </nav>

    <h1 class="text-3xl font-black text-slate-900 mb-2"><?= htmlspecialchars($modpack['name']) ?></h1>
    <p class="text-lg text-slate-600 mb-8"><?= htmlspecialchars($modpack['version'] ?? '—') ?></p>

    <dl class="grid gap-4 sm:grid-cols-2 mb-8">
        <div class="p-4 bg-slate-50 rounded-lg">
            <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">URL / Téléchargement</dt>
            <dd class="mt-1">
                <?php if (!empty($modpack['download_url'])): ?>
                <a href="<?= htmlspecialchars($modpack['download_url']) ?>" class="text-slate-900 font-medium underline hover:no-underline">Télécharger le modpack</a>
                <?php else: ?>
                <span class="text-slate-500">—</span>
                <?php endif; ?>
            </dd>
        </div>
        <div class="p-4 bg-slate-50 rounded-lg">
            <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Version</dt>
            <dd class="mt-1 font-mono font-medium"><?= htmlspecialchars($modpack['version'] ?? '—') ?></dd>
        </div>
        <div class="p-4 bg-slate-50 rounded-lg">
            <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Taille</dt>
            <dd class="mt-1 font-medium"><?= htmlspecialchars($modpack['size_formatted'] ?? '—') ?></dd>
        </div>
        <div class="p-4 bg-slate-50 rounded-lg">
            <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Date de mise en ligne</dt>
            <dd class="mt-1"><?= htmlspecialchars($releasedAt) ?></dd>
        </div>
        <div class="p-4 bg-slate-50 rounded-lg">
            <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Dernière mise à jour</dt>
            <dd class="mt-1"><?= htmlspecialchars($updatedAt) ?></dd>
        </div>
        <div class="p-4 bg-slate-50 rounded-lg">
            <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Version actuelle</dt>
            <dd class="mt-1 font-mono font-bold"><?= htmlspecialchars($modpack['version'] ?? '—') ?> STABLE</dd>
        </div>
    </dl>

    <?php if (!empty(trim((string) ($modpack['description'] ?? '')))): ?>
    <section class="mb-8">
        <h2 class="text-lg font-black text-slate-900 mb-3">Description</h2>
        <div class="prose prose-slate max-w-none text-slate-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($modpack['description'])) ?></div>
    </section>
    <?php endif; ?>

    <?php if (!empty($images)): ?>
    <section>
        <h2 class="text-lg font-black text-slate-900 mb-3">Images</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($images as $img): ?>
            <div class="rounded-lg overflow-hidden border border-slate-200 bg-slate-50">
                <img src="<?= url('modpacks/images/' . $img['id']) ?>" alt="" class="w-full h-48 object-cover" loading="lazy" />
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('dashboard') ?>" class="underline">Retour au dashboard</a></p>
</div>
