<?php
$verify = $courrier['verify'] ?? [];
$document = $courrier['document'] ?? null;
$documentId = $courrier['document_id'] ?? null;
$uuid = $courrier['uuid'] ?? null;
$baseUrl = url('');
$valid = $verify['valid'] ?? null;
$message = $verify['message'] ?? '';
$signedAt = $verify['signed_at'] ?? null;
$contentHash = $verify['content_hash'] ?? null;
$verificationCode = $verify['verification_code'] ?? null;
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Vérification d'authenticité</h1>
    <?php if ($uuid): ?>
    <p class="mb-6"><a href="<?= $baseUrl ?>/courrier/verify" class="text-slate-500 hover:text-slate-900 text-sm">← Nouvelle vérification</a></p>
    <?php else: ?>
    <p class="mb-6"><a href="<?= $baseUrl ?>/courrier" class="text-slate-500 hover:text-slate-900 text-sm">← Bureau Courrier</a></p>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8">
        <?php if ($valid === true): ?>
        <p class="text-lg font-bold text-emerald-600 mb-2">Document authentique</p>
        <p class="text-slate-600"><?= htmlspecialchars($message) ?></p>
        <?php elseif ($valid === false): ?>
        <p class="text-lg font-bold text-red-600 mb-2">Document altéré</p>
        <p class="text-slate-600"><?= htmlspecialchars($message) ?></p>
        <?php else: ?>
        <p class="text-lg font-bold text-amber-600 mb-2">Non sécurisé par hash</p>
        <p class="text-slate-600"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <?php if ($signedAt): ?>
        <p class="mt-4 text-sm text-slate-500">Signé le : <?= htmlspecialchars($signedAt) ?></p>
        <?php endif; ?>
        <?php if ($verificationCode): ?>
        <p class="mt-3 text-sm text-slate-700"><span class="font-semibold">Identifiant de vérification :</span> <code class="bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($verificationCode) ?></code></p>
        <?php endif; ?>
        <?php if ($contentHash): ?>
        <p class="mt-2 text-xs font-mono text-slate-400 break-all">Empreinte SHA-256 : <?= htmlspecialchars($contentHash) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($document): ?>
    <div class="mt-8 border border-slate-200 rounded-xl p-6">
        <h2 class="text-sm font-semibold text-slate-600 uppercase mb-3">Détails du document</h2>
        <dl class="grid grid-cols-1 gap-2 text-sm">
            <div><dt class="text-slate-500">Référence</dt><dd><?= htmlspecialchars($document['reference_number'] ?? '—') ?></dd></div>
            <div><dt class="text-slate-500">Objet</dt><dd><?= htmlspecialchars($document['subject'] ?? '—') ?></dd></div>
            <?php if ($uuid): ?>
            <div><dt class="text-slate-500">UUID</dt><dd class="font-mono text-xs"><?= htmlspecialchars($uuid) ?></dd></div>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>

    <?php if ($uuid): ?>
    <p class="mt-8 text-slate-500 text-sm">Partagez ce lien pour permettre une vérification par tiers : <br/><code class="bg-slate-100 px-2 py-1 rounded"><?= htmlspecialchars($baseUrl) ?>/courrier/verify?uuid=<?= htmlspecialchars($uuid) ?></code></p>
    <?php endif; ?>
</div>
