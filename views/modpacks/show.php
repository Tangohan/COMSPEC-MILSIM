<?php
declare(strict_types=1);
$modpack = $modpack ?? null;
if (!$modpack) {
    echo '<div class="mx-auto max-w-3xl px-6 py-16 text-center"><p class="text-slate-600">Modpack introuvable.</p><a class="mt-4 inline-block text-sm font-semibold text-sky-700 hover:underline" href="' . htmlspecialchars(url('modpacks'), ENT_QUOTES, 'UTF-8') . '">Retour à la liste</a></div>';

    return;
}
$images = $modpack['images'] ?? [];
$heroImageId = isset($modpack['hero_image_id']) ? (int) $modpack['hero_image_id'] : 0;
$galleryImages = [];
if ($images !== []) {
    $galleryImages = count($images) > 1 ? array_slice($images, 1) : [];
}
$releasedTs = !empty($modpack['released_at']) ? strtotime((string) $modpack['released_at']) : false;
$updatedTs = !empty($modpack['updated_at']) ? strtotime((string) $modpack['updated_at']) : false;
$releasedAt = $releasedTs ? date('d/m/Y', $releasedTs) : '—';
$updatedAt = $updatedTs ? date('d/m/Y', $updatedTs) : '—';
$version = trim((string) ($modpack['version'] ?? ''));
$hasFile = !empty($modpack['file_path']);
$downloadUrl = (string) ($modpack['download_url'] ?? '');
$externalHref = (string) ($modpack['external_href'] ?? '');
$desc = trim((string) ($modpack['description'] ?? ''));
?>
<div class="relative overflow-hidden bg-gradient-to-b from-slate-50 via-white to-slate-100">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-64 bg-gradient-to-b from-emerald-500/8 to-transparent" aria-hidden="true"></div>

    <article class="relative mx-auto max-w-4xl px-4 pb-16 pt-8 sm:px-6 lg:px-8 lg:pb-20 lg:pt-10">
        <nav class="mb-8 flex flex-wrap items-center gap-2 text-sm font-medium text-slate-600" aria-label="Fil d’Ariane">
            <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="text-sky-700 hover:text-sky-900 hover:underline">Tableau de bord</a>
            <span class="text-slate-300" aria-hidden="true">/</span>
            <a href="<?= htmlspecialchars(url('modpacks'), ENT_QUOTES, 'UTF-8') ?>" class="text-sky-700 hover:text-sky-900 hover:underline">Modpacks</a>
            <span class="text-slate-300" aria-hidden="true">/</span>
            <span class="font-semibold text-slate-900 truncate max-w-[min(100%,12rem)] sm:max-w-xs"><?= htmlspecialchars((string) ($modpack['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </nav>

        <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-slate-900 shadow-xl ring-1 ring-black/5">
            <div class="relative aspect-[21/9] min-h-[200px] sm:min-h-[240px]">
                <?php if ($heroImageId > 0): ?>
                    <img src="<?= htmlspecialchars(url('modpacks/images/' . $heroImageId), ENT_QUOTES, 'UTF-8') ?>"
                         alt="Aperçu visuel du modpack <?= htmlspecialchars((string) ($modpack['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                         class="absolute inset-0 h-full w-full object-cover"
                         fetchpriority="high" />
                <?php else: ?>
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-900 via-slate-900 to-slate-950" aria-hidden="true"></div>
                    <div class="absolute inset-0 flex items-center justify-center opacity-25" aria-hidden="true">
                        <svg class="h-28 w-28 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 .41-.21.815-.688 1.194C17.064 15.876 14.718 16.5 12 16.5c-2.717 0-5.064-.624-7.062-1.931-.478-.38-.688-.784-.688-1.194z"/></svg>
                    </div>
                <?php endif; ?>
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/50 to-transparent" aria-hidden="true"></div>
                <div class="absolute inset-x-0 bottom-0 p-6 sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-300/90">Modpack</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl lg:text-4xl"><?= htmlspecialchars((string) ($modpack['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
                    <?php if ($version !== ''): ?>
                        <p class="mt-2 inline-flex items-center rounded-lg bg-white/10 px-3 py-1 text-sm font-semibold text-white backdrop-blur-sm">
                            Version <?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Taille du fichier</p>
                <p class="mt-1 text-lg font-bold text-slate-900 tabular-nums"><?= htmlspecialchars((string) ($modpack['size_formatted'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mise en ligne</p>
                <p class="mt-1 text-lg font-bold text-slate-900 tabular-nums"><?= htmlspecialchars($releasedAt, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:col-span-2 lg:col-span-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dernière mise à jour</p>
                <p class="mt-1 text-lg font-bold text-slate-900 tabular-nums"><?= htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <div class="mt-8 rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50 via-white to-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-bold text-slate-900">Téléchargement</h2>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                Utilisez le bouton ci-dessous pour récupérer l’archive validée par votre communauté. Conservez la version indiquée pour rester aligné avec les sessions officielles.
            </p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <?php if ($hasFile && $downloadUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') ?>"
                       class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-6 py-3.5 text-base font-semibold text-white shadow-md transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                        Télécharger le pack
                    </a>
                <?php else: ?>
                    <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-950">
                        Aucun fichier n’est associé à ce modpack pour le moment. Contactez un référent de votre communauté.
                    </p>
                <?php endif; ?>
                <?php if ($externalHref !== ''): ?>
                    <a href="<?= htmlspecialchars($externalHref, ENT_QUOTES, 'UTF-8') ?>"
                       target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center rounded-xl border-2 border-slate-200 bg-white px-6 py-3.5 text-base font-semibold text-slate-800 transition hover:border-slate-300 hover:bg-slate-50">
                        Lien externe (miroir ou installateur)
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($desc !== ''): ?>
            <section class="mt-10" aria-labelledby="modpack-desc">
                <h2 id="modpack-desc" class="text-xl font-bold text-slate-900">À propos de ce pack</h2>
                <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="prose prose-slate max-w-none text-base leading-relaxed text-slate-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')) ?></div>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($galleryImages)): ?>
            <section class="mt-10" aria-labelledby="modpack-gallery">
                <h2 id="modpack-gallery" class="text-xl font-bold text-slate-900">Autres visuels</h2>
                <p class="mt-1 text-sm text-slate-600">Captures ou illustrations fournies avec ce modpack.</p>
                <ul class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <?php foreach ($galleryImages as $img): ?>
                        <?php $iid = (int) ($img['id'] ?? 0); ?>
                        <?php if ($iid <= 0) {
                            continue;
                        } ?>
                        <li class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50 shadow-sm">
                            <img src="<?= htmlspecialchars(url('modpacks/images/' . $iid), ENT_QUOTES, 'UTF-8') ?>"
                                 alt="Visuel du modpack <?= htmlspecialchars((string) ($modpack['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                 class="aspect-video w-full object-cover"
                                 loading="lazy" />
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <p class="mt-12 border-t border-slate-200 pt-8 text-center sm:text-left">
            <a href="<?= htmlspecialchars(url('modpacks'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-sky-700 hover:text-sky-900 hover:underline">← Tous les modpacks</a>
        </p>
    </article>
</div>
