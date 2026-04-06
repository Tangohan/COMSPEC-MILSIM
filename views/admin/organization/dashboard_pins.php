<?php
declare(strict_types=1);
/** @var list<array{row: array<string, mixed>, summary: string}> $dashboardPins */
/** @var int $maxPins */
/** @var list<array<string, mixed>> $previewPins */
$dashboardPins = $dashboardPins ?? [];
$maxPins = $maxPins ?? 30;
$previewPins = $previewPins ?? [];
$count = count($dashboardPins);
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Raccourcis du tableau de bord</h1>
            <p class="text-sm text-slate-600 mt-1">Épingles visibles par les membres (selon leurs droits de lecture).</p>
        </div>
        <div class="flex gap-3">
            <a href="<?= url('back-office') ?>" class="text-sm text-slate-600 hover:underline">Retour</a>
            <?php if ($count < $maxPins): ?>
                <a href="<?= url('back-office/dashboard-pins/create') ?>" class="inline-flex items-center px-4 py-2 bg-slate-900 text-white text-sm font-bold rounded-lg hover:bg-slate-800">Ajouter</a>
            <?php endif; ?>
        </div>
    </div>

    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <p class="text-xs text-slate-500 mb-6"><?= (int) $count ?> / <?= (int) $maxPins ?> raccourcis</p>

    <?php if ($dashboardPins === []): ?>
        <p class="text-slate-600 border border-dashed border-slate-200 rounded-xl p-8 text-center">Aucun raccourci. Les membres verront uniquement le hub standard.</p>
    <?php else: ?>
        <ul class="space-y-3">
            <?php foreach ($dashboardPins as $item): ?>
                <?php
                $row = $item['row'];
                $id = (int) ($row['id'] ?? 0);
                ?>
                <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900 truncate"><?= htmlspecialchars($item['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-[10px] font-mono text-slate-400 uppercase tracking-wider mt-1"><?= htmlspecialchars((string) ($row['pin_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="post" action="<?= url('back-office/dashboard-pins/' . $id . '/move') ?>" class="inline">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="px-2 py-1 text-xs font-bold border border-slate-200 rounded hover:bg-slate-50" title="Monter">↑</button>
                        </form>
                        <form method="post" action="<?= url('back-office/dashboard-pins/' . $id . '/move') ?>" class="inline">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="px-2 py-1 text-xs font-bold border border-slate-200 rounded hover:bg-slate-50" title="Descendre">↓</button>
                        </form>
                        <a href="<?= url('back-office/dashboard-pins/' . $id . '/edit') ?>" class="text-xs font-bold text-blue-700 hover:underline">Modifier</a>
                        <form method="post" action="<?= url('back-office/dashboard-pins/' . $id . '/delete') ?>" class="inline" onsubmit="return confirm('Supprimer ce raccourci ?');">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="text-xs font-bold text-red-600 hover:underline">Supprimer</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($previewPins !== []): ?>
        <div class="mt-10 border-t border-slate-200 pt-8">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500 mb-3">Aperçu (selon vos droits actuels)</h2>
            <ul class="flex flex-wrap gap-2">
                <?php foreach ($previewPins as $p): ?>
                    <li class="px-3 py-1.5 bg-slate-100 rounded-lg text-xs text-slate-800">
                        <?php if (!empty($p['href'])): ?>
                            <a href="<?= htmlspecialchars((string) $p['href'], ENT_QUOTES, 'UTF-8') ?>" class="font-semibold hover:underline"><?= htmlspecialchars((string) ($p['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                        <?php else: ?>
                            <span class="font-semibold"><?= htmlspecialchars((string) ($p['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
