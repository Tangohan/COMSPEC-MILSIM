<?php
$c = $courrier ?? [];
$doc = $c['document'] ?? null;
$previewHtml = $c['preview_html'] ?? '';
$baseUrl = url('');
if (!$doc) {
    echo '<p class="p-8 text-slate-600">Document introuvable.</p>';
    return;
}
$status = $doc['status'] ?? 'draft';
$statusLabels = [
    'draft' => 'Brouillon',
    'pending_validation' => 'En attente de validation',
    'validated' => 'Validé',
    'rejected' => 'Refusé',
    'sent' => 'Envoyé',
    'archived' => 'Archivé',
];
$statusLabel = $statusLabels[$status] ?? $status;
$isDraft = $status === 'draft';
$isSigned = !empty($doc['signed_at']);
?>
<div class="max-w-4xl mx-auto px-4 py-8 font-['JetBrains_Mono',_monospace] bg-slate-50 min-h-screen">
    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars((string)\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars((string)\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>

    <nav class="flex items-center gap-2 text-sm text-slate-500 mb-8">
        <a href="<?= $baseUrl ?>/courrier" class="hover:text-slate-900 transition-colors">← Bureau Courrier</a>
        <span class="text-slate-400">/</span>
        <span class="text-slate-700 font-medium">Lecture</span>
    </nav>

    <header class="mb-10 pb-8 border-b-2 border-slate-200">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tighter uppercase mb-2">
                    <?= htmlspecialchars($doc['title'] ?: 'Sans titre') ?>
                </h1>
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span class="px-2.5 py-1 rounded font-bold text-slate-600 bg-slate-200">
                        <?= htmlspecialchars($statusLabel) ?>
                    </span>
                    <?php if (!empty($doc['reference_number'])): ?>
                    <span class="text-slate-600">Réf. <strong><?= htmlspecialchars($doc['reference_number']) ?></strong></span>
                    <?php endif; ?>
                    <?php if (!empty($doc['updated_at'])): ?>
                    <span class="text-slate-500">Modifié le <?= htmlspecialchars(date('d/m/Y à H:i', strtotime($doc['updated_at']))) ?></span>
                    <?php endif; ?>
                    <?php if ($isSigned): ?>
                    <span class="text-emerald-600 font-medium">Signé le <?= htmlspecialchars(date('d/m/Y à H:i', strtotime($doc['signed_at']))) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($doc['subject'])): ?>
                <p class="mt-3 text-slate-600"><span class="text-slate-500 uppercase text-xs tracking-wider">Objet</span> <?= htmlspecialchars($doc['subject']) ?></p>
                <?php endif; ?>
                <?php if (!empty($doc['destination_label']) || !empty($doc['issuer_label'])): ?>
                <div class="mt-3 flex flex-wrap gap-6 text-sm text-slate-600">
                    <?php if (!empty($doc['destination_label'])): ?>
                    <span><span class="text-slate-500 uppercase text-xs tracking-wider">Destinataire</span> <?= htmlspecialchars($doc['destination_label']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($doc['issuer_label'])): ?>
                    <span><span class="text-slate-500 uppercase text-xs tracking-wider">Émetteur</span> <?= htmlspecialchars($doc['issuer_label']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= $baseUrl ?>/courrier" class="px-4 py-2 border border-slate-300 text-slate-700 text-sm font-bold rounded hover:bg-slate-100 transition-colors uppercase tracking-wider">
                    Retour
                </a>
                <a href="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/print" target="_blank" rel="noopener" class="px-4 py-2 bg-slate-900 text-white text-sm font-bold rounded hover:bg-slate-800 transition-colors uppercase tracking-wider">
                    Imprimer
                </a>
                <?php if ($isDraft): ?>
                <a href="<?= $baseUrl ?>/courrier/editor/<?= (int)$doc['id'] ?>" class="px-4 py-2 bg-amber-600 text-white text-sm font-bold rounded hover:bg-amber-700 transition-colors uppercase tracking-wider">
                    Éditer
                </a>
                <?php endif; ?>
                <?php if ($isSigned): ?>
                <a href="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/verify" class="px-4 py-2 border border-emerald-600 text-emerald-700 text-sm font-bold rounded hover:bg-emerald-50 transition-colors uppercase tracking-wider">
                    Vérifier l'authenticité
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <article class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="courrier-preview-container p-8 md:p-12 min-h-[420px] text-slate-800">
            <?php if ($previewHtml): ?>
            <?= $previewHtml ?>
            <?php else: ?>
            <div class="prose prose-slate max-w-none">
                <?= !empty(trim(strip_tags($doc['body_rendered'] ?? ''))) ? $doc['body_rendered'] : '<p class="text-slate-400">Aucun contenu.</p>' ?>
            </div>
            <?php endif; ?>
        </div>
    </article>

    <p class="mt-6 text-center text-xs text-slate-400">
        <a href="<?= $baseUrl ?>/courrier" class="hover:text-slate-600">Retour au Bureau Courrier</a>
    </p>
</div>
