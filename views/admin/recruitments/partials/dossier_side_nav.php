<?php
declare(strict_types=1);

/**
 * Navigation latérale droite + déclencheurs du guide animé (fiche dossier).
 *
 * @var list<array{id: string, label: string, num: string, show?: bool}> $dossierNavItems
 * @var string|null $bureauRecrutementCourseUrl
 * @var 'mobile'|'desktop' $dossierSideNavMode
 */

$dossierNavItems = is_array($dossierNavItems ?? null) ? $dossierNavItems : [];
$dossierSideNavMode = ($dossierSideNavMode ?? 'desktop') === 'mobile' ? 'mobile' : 'desktop';
$bureauRecrutementCourseUrl = isset($bureauRecrutementCourseUrl) && is_string($bureauRecrutementCourseUrl) && $bureauRecrutementCourseUrl !== ''
    ? $bureauRecrutementCourseUrl
    : url('formations/parcours-bureau-recrutement');

$visibleNav = [];
foreach ($dossierNavItems as $ni) {
    if (isset($ni['show']) && !$ni['show']) {
        continue;
    }
    $visibleNav[] = $ni;
}

if ($dossierSideNavMode === 'mobile'):
?>
<nav class="recruitment-dossier-rail-mobile mb-4 lg:hidden" aria-label="Sommaire mobile du dossier">
    <div class="flex items-center justify-between gap-2 mb-2">
        <p class="text-[10px] font-black uppercase tracking-wider text-stone-500">Aller à</p>
        <button type="button" id="dossier-tour-start-mobile" class="text-[11px] font-bold text-emerald-700 underline decoration-emerald-300 underline-offset-2">Guide</button>
    </div>
    <div class="-mx-1 flex gap-2 overflow-x-auto pb-1 snap-x snap-mandatory px-1">
        <?php foreach ($visibleNav as $ni): ?>
            <a href="#<?= htmlspecialchars((string) $ni['id'], ENT_QUOTES, 'UTF-8') ?>"
               class="shrink-0 snap-start rounded-full border border-stone-200 bg-white px-3.5 py-2 text-xs font-semibold text-stone-700 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50">
                <?= htmlspecialchars((string) $ni['label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
<?php
    return;
endif;
?>
<aside class="recruitment-dossier-rail hidden w-full min-w-0 order-2 lg:block" aria-label="Navigation du dossier">
    <div class="recruitment-dossier-rail__sticky space-y-4 lg:sticky lg:top-6">
        <div class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm lg:shadow-md">
            <div class="border-b border-stone-200 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 px-4 py-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-emerald-300/90">Sur cette page</p>
                <p class="mt-1 text-sm font-black text-white">Sommaire du dossier</p>
            </div>
            <nav class="space-y-1.5 p-3" id="dossier-side-nav" aria-label="Sections du dossier">
                <?php foreach ($visibleNav as $ni): ?>
                    <a href="#<?= htmlspecialchars((string) $ni['id'], ENT_QUOTES, 'UTF-8') ?>"
                       data-dossier-nav="<?= htmlspecialchars((string) $ni['id'], ENT_QUOTES, 'UTF-8') ?>"
                       class="dossier-side-nav__link flex items-center justify-between gap-2 rounded-xl border border-transparent px-3 py-2.5 text-left text-xs font-bold text-slate-700 transition hover:border-emerald-300/50 hover:bg-emerald-50/60 hover:text-emerald-950">
                        <span class="min-w-0 leading-snug"><?= htmlspecialchars((string) $ni['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="shrink-0 text-[10px] font-black tabular-nums text-slate-400"><?= htmlspecialchars((string) ($ni['num'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="border-t border-stone-100 p-3 space-y-2">
                <button type="button"
                        id="dossier-tour-start"
                        class="inline-flex w-full min-h-[2.5rem] items-center justify-center gap-2 rounded-xl bg-emerald-700 px-3 text-xs font-black uppercase tracking-wide text-white shadow-sm transition hover:bg-emerald-800">
                    Lancer le guide
                </button>
                <a href="<?= htmlspecialchars($bureauRecrutementCourseUrl, ENT_QUOTES, 'UTF-8') ?>"
                   class="inline-flex w-full min-h-[2.5rem] items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-800 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-950">
                    Formation Bureau recrutement
                </a>
            </div>
        </div>
        <p class="text-[11px] leading-relaxed text-stone-500 px-1">
            Le guide surligne chaque zone utile. La formation complète le même parcours en profondeur, avec quiz.
        </p>
        <div class="rounded-xl border border-stone-200 bg-stone-50 px-3 py-3 text-[11px] text-stone-600 space-y-1.5">
            <p><span class="font-bold text-stone-800">Statut</span> — <?= htmlspecialchars($statusLabel ?: '—') ?></p>
            <p><span class="font-bold text-stone-800">Attribué</span> — <?= htmlspecialchars($assigneeLabel ?? '—', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>
</aside>
