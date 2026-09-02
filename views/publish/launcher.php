<?php
declare(strict_types=1);

/** @var list<array{key: string, label: string, description: string, href: string, enabled: bool}> $publishOptions */
$publishOptions = is_array($publishOptions ?? null) ? $publishOptions : [];
$anyEnabled = false;
foreach ($publishOptions as $opt) {
    if (!empty($opt['enabled'])) {
        $anyEnabled = true;
        break;
    }
}

$icons = [
    'alert' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
    'forum' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'briefing' => '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
    'event' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    'article' => '<path d="M4 4h13a2 2 0 0 1 2 2v14H6a2 2 0 0 1-2-2z"/><path d="M8 8h8M8 12h6M8 16h4"/>',
];
?>
<div class="min-h-screen bg-slate-100">
    <div class="mx-auto max-w-4xl px-6 py-10 md:py-14">
        <p class="mb-3 text-[11px] font-black uppercase tracking-[0.35em] text-slate-400">Publication</p>
        <h1 class="mb-3 text-3xl font-black uppercase tracking-tight text-slate-950 md:text-4xl">Que voulez-vous publier<span class="text-emerald-600">&nbsp;?</span></h1>
        <p class="mb-8 max-w-2xl text-sm leading-relaxed text-slate-600">Choisissez un type de contenu : vous serez redirigé vers le bon formulaire. Seules les options que vous êtes habilité à créer s’affichent activées.</p>

        <?php if (!$anyEnabled): ?>
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="text-sm font-semibold text-slate-800">Aucune option de publication disponible pour votre compte.</p>
            <p class="mt-2 text-sm text-slate-500">Contactez un responsable de la communauté si vous pensez qu’il s’agit d’une erreur.</p>
        </div>
        <?php else: ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <?php foreach ($publishOptions as $opt):
                $enabled = !empty($opt['enabled']);
                $icon = $icons[(string) ($opt['key'] ?? '')] ?? '<circle cx="12" cy="12" r="9"/>';
            ?>
            <?php if ($enabled): ?>
            <a href="<?= htmlspecialchars((string) $opt['href'], ENT_QUOTES, 'UTF-8') ?>" class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
            <?php else: ?>
            <div class="flex cursor-not-allowed flex-col rounded-2xl border border-slate-200 bg-slate-50 p-6 opacity-60">
            <?php endif; ?>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl <?= $enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-400' ?>" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg>
                </div>
                <p class="mt-4 text-base font-black tracking-tight text-slate-950"><?= htmlspecialchars((string) $opt['label'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600"><?= htmlspecialchars((string) $opt['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!$enabled): ?>
                <span class="mt-3 inline-flex w-fit items-center rounded-full bg-slate-200 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">Non habilité</span>
                <?php else: ?>
                <span class="mt-3 inline-flex w-fit items-center gap-1 text-xs font-bold uppercase tracking-wide text-emerald-700">Ouvrir <svg class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                <?php endif; ?>
            <?php echo $enabled ? '</a>' : '</div>'; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
