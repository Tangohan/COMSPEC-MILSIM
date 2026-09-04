<?php
/**
 * Écran de choix affiché à l’arrivée sur un dossier personnel pour les
 * comptes disposant au minimum des droits RH (accès personnel).
 *
 * Variables attendues depuis file.php (déjà résolues) :
 * $displayName, $avatarUrl, $personnelFileBaseUrl.
 */
$gatePublicUrl = $personnelFileBaseUrl . '?view=public';
$gateRhUrl = $personnelFileBaseUrl . '?view=rh';
?>
<section class="personnel-file-gate__hero" aria-label="Choix de la vue du dossier">
    <div class="personnel-file-gate__hero-inner">
        <p class="personnel-file-hero__eyebrow">Dossier personnel</p>
        <div class="personnel-file-gate__identity">
            <div class="personnel-file-gate__avatar">
                <?php if (!empty($avatarUrl)): ?>
                <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Photo de compte" loading="eager" decoding="async" class="h-full w-full object-cover" data-img-fallback="avatar" data-img-initials="<?= htmlspecialchars(function_exists('user_display_initials') ? user_display_initials((string) $displayName, 2) : '?', ENT_QUOTES, 'UTF-8') ?>" data-img-label="Photo de compte indisponible" />
                <?php else: ?>
                <div class="flex h-full w-full items-center justify-center text-slate-500">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                </div>
                <?php endif; ?>
            </div>
            <h1 class="personnel-file-gate__name">
                <?= htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') ?>
            </h1>
        </div>
        <p class="personnel-file-gate__lead">Vous disposez d’un accès RH sur ce dossier. Choisissez la vue à afficher.</p>
    </div>
</section>
<?php
$personnelFileNoticesIncludeRhSwitcher = false;
$personnelFileNoticesIncludeOperatorTabs = false;
require base_path('views/partials/personnel/file_page_notices.php');
?>
<section class="personnel-file-gate__choices">
    <div class="personnel-file-gate__choices-inner">
        <div class="grid gap-5 sm:grid-cols-2">
            <a href="<?= htmlspecialchars($gatePublicUrl, ENT_QUOTES, 'UTF-8') ?>" class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                <div class="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Vue publique</h2>
                <p class="mt-3 text-sm text-slate-600 leading-relaxed">Le dossier tel qu’il est visible par les autres membres : présentation, affectations, formations, historique.</p>
                <span class="mt-5 inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-emerald-700 group-hover:text-emerald-900">
                    Ouvrir
                    <svg class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </span>
            </a>
            <a href="<?= htmlspecialchars($gateRhUrl, ENT_QUOTES, 'UTF-8') ?>" class="group flex flex-col rounded-2xl border border-violet-200 bg-violet-50/40 p-6 shadow-sm transition hover:border-violet-400 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400">
                <div class="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-700">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
                </div>
                <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Dossier RH complet</h2>
                <p class="mt-3 text-sm text-slate-600 leading-relaxed">Une vue unique pour le suivi RH : situation actuelle calculée automatiquement, accès documentaire expliqué, affectations et données administratives.</p>
                <span class="mt-5 inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-violet-700 group-hover:text-violet-900">
                    Ouvrir
                    <svg class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </span>
            </a>
        </div>
    </div>
</section>
