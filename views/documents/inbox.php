<?php
declare(strict_types=1);

$filter = (string) ($filter ?? 'a_lire');
$counts = is_array($counts ?? null) ? $counts : [];
$items = is_array($items ?? null) ? $items : [];
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$tabs = [
    'a_lire' => ['label' => 'À lire', 'count' => (int) ($counts['a_lire'] ?? 0)],
    'obligatoires' => ['label' => 'Obligatoires', 'count' => (int) ($counts['obligatoires'] ?? 0)],
    'en_retard' => ['label' => 'En retard', 'count' => (int) ($counts['en_retard'] ?? 0)],
    'lus' => ['label' => 'Lus', 'count' => (int) ($counts['lus'] ?? 0)],
];
?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/doctrine-referential.css'), ENT_QUOTES, 'UTF-8') ?>">
<div class="max-w-5xl mx-auto px-4 py-8">
    <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Espace personnel</p>
    <h1 class="mt-1 text-2xl font-black text-slate-900">Mes documents</h1>
    <p class="mt-2 text-sm text-slate-600">Documents qui vous concernent : lectures obligatoires, accusés et historique.</p>

    <?php if ((int) ($counts['obligatoires'] ?? 0) > 0 || (int) ($counts['en_retard'] ?? 0) > 0): ?>
    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
        <?php if ((int) ($counts['obligatoires'] ?? 0) > 0): ?>
            <strong><?= (int) $counts['obligatoires'] ?> document<?= (int) $counts['obligatoires'] > 1 ? 's' : '' ?> obligatoire<?= (int) $counts['obligatoires'] > 1 ? 's' : '' ?></strong> en attente.
        <?php endif; ?>
        <?php if ((int) ($counts['en_retard'] ?? 0) > 0): ?>
            <span class="font-bold text-rose-800"><?= (int) $counts['en_retard'] ?> lecture<?= (int) $counts['en_retard'] > 1 ? 's' : '' ?> en retard</span>.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <nav class="mt-6 flex flex-wrap gap-2" aria-label="Catégories">
        <?php foreach ($tabs as $key => $tab): ?>
        <a href="<?= url('documents/mes-documents') . '?filtre=' . $h($key) ?>"
           class="rounded-full px-3 py-1.5 text-xs font-bold uppercase tracking-wide <?= $filter === $key ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700' ?>">
            <?= $h($tab['label']) ?>
            <span class="opacity-70">(<?= (int) $tab['count'] ?>)</span>
        </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($items === []): ?>
    <p class="mt-8 text-sm text-slate-500">Aucun document dans cette catégorie.</p>
    <?php else: ?>
    <ul class="mt-6 space-y-3">
        <?php foreach ($items as $item): ?>
        <li>
            <a href="<?= $h($item['href'] ?? '#') ?>" class="block rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-400">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wide" style="color:<?= $h($item['type_color'] ?? '#64748b') ?>">
                            <?= $h($item['type_label'] ?? 'Document') ?>
                            <?php if (!empty($item['mandatory'])): ?> · Obligatoire<?php endif; ?>
                        </p>
                        <p class="mt-0.5 font-mono text-xs font-bold text-slate-500"><?= $h($item['reference'] ?? '') ?></p>
                        <h2 class="text-base font-bold text-slate-900"><?= $h($item['title'] ?? '') ?></h2>
                    </div>
                    <span class="doctrine-ref__badge doctrine-ref__badge--<?= $h($item['badge']['tone'] ?? 'neutral') ?>">
                        <?= $h($item['badge']['label'] ?? '') ?>
                    </span>
                </div>
                <p class="mt-2 text-xs text-slate-500">
                    Version <?= $h($item['version_label'] ?? '') ?>
                    <?php if (!empty($item['deadline_label'])): ?> · <?= $h($item['deadline_label']) ?><?php endif; ?>
                </p>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <p class="mt-8 text-sm">
        <a href="<?= url('documents') ?>" class="font-bold text-slate-700 underline">Bibliothèque documentaire</a>
    </p>
</div>
