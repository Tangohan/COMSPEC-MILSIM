<?php
declare(strict_types=1);
/** @var list<array{version: string, date: string, title: string, items: list<string>}> $lmsChangelogEntries */
/** @var string $lmsPlatformVersion */
/** @var string $lmsPlatformLabel */

$lmsChangelogEntries = $lmsChangelogEntries ?? [];
$lmsPlatformVersion = (string) ($lmsPlatformVersion ?? '');
$lmsPlatformLabel = (string) ($lmsPlatformLabel ?? '');
?>
<div>
    <header class="training-studio-hero mb-8">
        <div class="max-w-3xl">
            <p class="text-[0.65rem] font-black tracking-[0.35em] uppercase text-emerald-600 mb-2">Studio formation</p>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Journal des évolutions</h1>
            <p class="text-slate-600 text-sm mt-3 leading-relaxed">
                Version actuelle de l’outil : <strong class="text-slate-900">v<?= htmlspecialchars($lmsPlatformVersion) ?></strong>
                <?php if ($lmsPlatformLabel !== ''): ?>
                <span class="text-slate-500">(<?= htmlspecialchars($lmsPlatformLabel) ?>)</span>
                <?php endif; ?>
            </p>
            <p class="text-sm text-slate-500 mt-2">
                Les formations mémorisent la version sous laquelle elles ont été <strong>créées</strong> et celle du <strong>dernier enregistrement</strong> dans le Studio.
                Si une formation a été initiée avant une montée de version, un rappel s’affiche sur sa fiche d’édition.
            </p>
            <p class="text-sm text-slate-500 mt-3">
                <a href="<?= url('admin/training/studio') ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Tableau des formations</a>
            </p>
        </div>
    </header>

    <div class="space-y-6 max-w-3xl">
        <?php if ($lmsChangelogEntries === []): ?>
        <div class="training-studio-panel p-6 text-slate-600 text-sm">
            Aucune entrée de journal pour le moment.
        </div>
        <?php else: ?>
        <?php foreach ($lmsChangelogEntries as $entry): ?>
        <article class="training-studio-panel p-6 md:p-8 border-l-4 border-l-emerald-500/80">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 mb-3">
                <h2 class="text-lg font-black text-slate-900">v<?= htmlspecialchars((string) ($entry['version'] ?? '')) ?></h2>
                <?php if (($entry['date'] ?? '') !== ''): ?>
                <time class="text-xs font-bold uppercase tracking-wider text-slate-400" datetime="<?= htmlspecialchars((string) $entry['date']) ?>"><?= htmlspecialchars((string) $entry['date']) ?></time>
                <?php endif; ?>
            </div>
            <?php if (($entry['title'] ?? '') !== ''): ?>
            <p class="text-sm font-bold text-violet-800 mb-3"><?= htmlspecialchars((string) $entry['title']) ?></p>
            <?php endif; ?>
            <?php if (!empty($entry['items']) && is_array($entry['items'])): ?>
            <ul class="list-disc pl-5 space-y-2 text-sm text-slate-700 leading-relaxed">
                <?php foreach ($entry['items'] as $item): ?>
                <li><?= htmlspecialchars((string) $item) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
