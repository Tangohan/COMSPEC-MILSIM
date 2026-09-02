<?php
declare(strict_types=1);

$doctrinePending = is_array($doctrine_pending ?? null) ? $doctrine_pending : [];
if ($doctrinePending === []) {
    return;
}
$doctrinePendingCount = count($doctrinePending);
?>
<section class="dash-doctrine-pending" id="documents-a-prendre-en-compte" aria-labelledby="dash-doctrine-pending-title">
    <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-5 shadow-sm">
        <h2 id="dash-doctrine-pending-title" class="text-sm font-black uppercase tracking-[0.14em] text-amber-950">Documents à prendre en compte</h2>
        <p class="mt-1 text-sm text-amber-900/90"><?= $doctrinePendingCount ?> document<?= $doctrinePendingCount > 1 ? 's' : '' ?> nécessite<?= $doctrinePendingCount > 1 ? 'nt' : '' ?> votre attention.</p>
        <ul class="mt-4 space-y-2">
            <?php foreach ($doctrinePending as $item): ?>
            <li>
                <a href="<?= htmlspecialchars((string) ($item['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-100 bg-white px-3 py-2.5 text-sm transition hover:border-amber-300">
                    <span><code class="font-mono text-xs font-bold"><?= htmlspecialchars((string) ($item['reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code> — <?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="text-xs font-bold uppercase text-amber-800"><?= htmlspecialchars((string) (($item['badge']['label'] ?? '') . ($item['deadline_label'] ? ' · ' . $item['deadline_label'] : '')), ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="mt-3"><a href="<?= url('documents') . '?category_slug=doctrine&doctrine_filter=action' ?>" class="text-xs font-black uppercase tracking-wide text-amber-900">Voir le référentiel doctrinal</a></p>
    </div>
</section>
