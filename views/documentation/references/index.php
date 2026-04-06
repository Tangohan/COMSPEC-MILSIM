<?php
declare(strict_types=1);
/** @var array<string, array{rel: string, title: string, section: string}> $docEntries */
$docEntries = $docEntries ?? [];
$bySection = [];
foreach ($docEntries as $key => $meta) {
    $sec = $meta['section'] ?? 'Autre';
    $bySection[$sec] ??= [];
    $bySection[$sec][$key] = $meta;
}
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10 lg:py-12">
    <nav class="mb-6 text-sm">
        <a href="<?= htmlspecialchars(url('documentation'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-sky-700 hover:underline">← Guide du portail</a>
    </nav>
    <header class="mb-10">
        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-sky-700 mb-2">Références</p>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Textes techniques &amp; inventaires</h1>
        <p class="mt-3 text-slate-600 max-w-2xl leading-relaxed">
            Liste réservée à l’équipe : fiches détaillées (routes, modules, inventaire). Pour le fonctionnement général du site, utilisez le <a href="<?= htmlspecialchars(url('documentation'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-sky-700 underline">guide utilisateur</a>.
        </p>
    </header>
    <div class="space-y-10">
        <?php foreach ($bySection as $section => $items): ?>
            <section>
                <h2 class="text-xs font-black uppercase tracking-[0.18em] text-slate-500 mb-4"><?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?></h2>
                <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($items as $key => $meta): ?>
                        <li>
                            <a href="<?= htmlspecialchars(url('documentation/fichier/' . rawurlencode((string) $key)), ENT_QUOTES, 'UTF-8') ?>"
                               class="group flex flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-sky-300 hover:shadow-md transition">
                                <span class="font-bold text-slate-900 group-hover:text-sky-800"><?= htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="mt-1 text-xs text-slate-500">Ouvrir la fiche</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
</div>
