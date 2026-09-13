<?php
declare(strict_types=1);

$doctrinePending = is_array($doctrine_pending ?? null) ? $doctrine_pending : [];
$docSummary = is_array($document_inbox_summary ?? null) ? $document_inbox_summary : [];
$mandatory = (int) ($docSummary['mandatory'] ?? 0);
$overdue = (int) ($docSummary['overdue'] ?? 0);
$toRead = (int) ($docSummary['to_read'] ?? count($doctrinePending));

if ($doctrinePending === [] && $toRead < 1 && $mandatory < 1 && $overdue < 1) {
    return;
}
$doctrinePendingCount = count($doctrinePending);
?>
<section class="dash-doctrine-pending" id="documents-a-lire" aria-labelledby="dash-doctrine-pending-title">
    <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-5 shadow-sm">
        <h2 id="dash-doctrine-pending-title" class="text-sm font-black uppercase tracking-[0.14em] text-amber-950">Documents à lire</h2>
        <p class="mt-1 text-sm text-amber-900/90">
            <?php if ($mandatory > 0): ?>
                <strong><?= $mandatory ?> document<?= $mandatory > 1 ? 's' : '' ?> obligatoire<?= $mandatory > 1 ? 's' : '' ?></strong>
            <?php else: ?>
                <?= $toRead > 0 ? $toRead . ' document' . ($toRead > 1 ? 's' : '') . ' à consulter' : 'Documents en attente' ?>
            <?php endif; ?>
            <?php if ($overdue > 0): ?>
                · <span class="font-bold text-rose-800"><?= $overdue ?> lecture<?= $overdue > 1 ? 's' : '' ?> en retard</span>
            <?php endif; ?>
        </p>
        <?php if ($doctrinePending !== []): ?>
        <ul class="mt-4 space-y-2">
            <?php foreach ($doctrinePending as $item): ?>
            <li>
                <a href="<?= htmlspecialchars((string) ($item['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-100 bg-white px-3 py-2.5 text-sm transition hover:border-amber-300">
                    <span><code class="font-mono text-xs font-bold"><?= htmlspecialchars((string) ($item['reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code> — <?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="text-xs font-bold uppercase text-amber-800"><?= htmlspecialchars((string) (($item['badge']['label'] ?? '') . (!empty($item['deadline_label']) ? ' · ' . $item['deadline_label'] : '')), ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <p class="mt-3 flex flex-wrap gap-3">
            <a href="<?= url('documents/mes-documents') ?>" class="text-xs font-black uppercase tracking-wide text-amber-900">Ouvrir Mes documents</a>
            <a href="<?= url('documents') . '?category_slug=doctrine&doctrine_filter=action' ?>" class="text-xs font-bold uppercase tracking-wide text-amber-800/80">Référentiel</a>
        </p>
    </div>
</section>
