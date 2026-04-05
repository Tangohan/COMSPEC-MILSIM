<?php
/** @var list<array<string, mixed>> $courrier_notifications */
$items = $courrier_notifications ?? [];
$baseUrl = url('');
?>
<div class="max-w-4xl mx-auto px-4 py-10 font-['JetBrains_Mono',_monospace] bg-slate-50 min-h-screen">
    <nav class="flex items-center gap-2 text-sm text-slate-500 mb-8">
        <a href="<?= $baseUrl ?>/courrier" class="hover:text-slate-900 transition-colors">← Bureau Courrier</a>
        <span class="text-slate-400">/</span>
        <span class="text-slate-700 font-medium">Notifications courrier</span>
    </nav>

    <header class="mb-10 pb-6 border-b border-slate-200">
        <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase mb-2">Notifications courrier</h1>
        <p class="text-sm text-slate-600">Documents signalés par d’autres membres de votre communauté.</p>
    </header>

    <?php if ($items === []): ?>
    <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-12 text-center text-slate-500 text-sm">
        Aucune notification pour le moment.
    </div>
    <?php else: ?>
    <ul class="space-y-3">
        <?php foreach ($items as $n): ?>
        <?php
        $docId = (int) ($n['document_id'] ?? 0);
        $readAt = $n['read_at'] ?? null;
        $isUnread = $readAt === null || $readAt === '';
        $title = (string) ($n['title'] ?? 'Sans titre');
        $ref = (string) ($n['reference_number'] ?? '');
        $subj = (string) ($n['subject'] ?? '');
        ?>
        <li>
            <a href="<?= $baseUrl ?>/courrier/read/<?= $docId ?>"
               class="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow-md">
                <div class="min-w-0">
                    <?php if ($isUnread): ?>
                    <span class="inline-block mb-1 rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-sky-900">Non lu</span>
                    <?php endif; ?>
                    <p class="font-bold text-slate-900 truncate"><?= htmlspecialchars($title) ?></p>
                    <?php if ($ref !== ''): ?>
                    <p class="text-xs text-slate-500 mt-1">Réf. <?= htmlspecialchars($ref) ?></p>
                    <?php endif; ?>
                    <?php if ($subj !== ''): ?>
                    <p class="text-sm text-slate-600 mt-2 line-clamp-2"><?= htmlspecialchars($subj) ?></p>
                    <?php endif; ?>
                </div>
                <div class="text-right text-xs text-slate-400 shrink-0">
                    <?php if (!empty($n['created_at'])): ?>
                    <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $n['created_at']))) ?>
                    <?php endif; ?>
                </div>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
