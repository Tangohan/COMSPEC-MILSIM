<?php
declare(strict_types=1);
$publication = is_array($publication ?? null) ? $publication : [];
$revisions = is_array($revisions ?? null) ? $revisions : [];
?>
<section class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Change log</p>
    <h1 class="tc-hero-title mb-2">Publication #<?= (int) ($publication['id'] ?? 0) ?></h1>
    <p class="text-sm text-slate-600">Historique des révisions et deltas intelligents.</p>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-6 mt-6 space-y-4">
    <?php foreach ($revisions as $revision): ?>
        <article class="rounded-xl border border-slate-200 p-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-black">Révision v<?= (int) ($revision['revision_number'] ?? 0) ?></h2>
                <span class="text-xs text-slate-500"><?= htmlspecialchars((string) ($revision['created_at'] ?? '')) ?></span>
            </div>
            <p class="text-sm text-slate-700 mt-1"><?= htmlspecialchars((string) ($revision['change_summary'] ?? '—')) ?></p>
            <pre class="mt-3 text-xs bg-slate-50 rounded p-3 overflow-auto"><?= htmlspecialchars((string) ($revision['diff_payload_json'] ?? '{}')) ?></pre>
        </article>
    <?php endforeach; ?>
    <?php if ($revisions === []): ?>
        <p class="text-slate-500">Aucune révision.</p>
    <?php endif; ?>
</section>
