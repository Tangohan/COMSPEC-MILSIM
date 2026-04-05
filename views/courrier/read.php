<?php
$c = $courrier ?? [];
$doc = $c['document'] ?? null;
$previewHtml = $c['preview_html'] ?? '';
$tenantUsers = $c['tenant_users'] ?? [];
$baseUrl = url('');
$currentUserId = (int) (\App\Core\Session::get('user_id') ?? 0);
if (!$doc) {
    echo '<p class="p-8 text-slate-600">Document introuvable.</p>';
    return;
}
$status = $doc['status'] ?? 'draft';
$statusLabels = [
    'draft' => 'Brouillon',
    'pending_validation' => 'En attente de validation',
    'validated' => 'Validé',
    'signed' => 'Signé',
    'rejected' => 'Refusé',
    'sent' => 'Envoyé',
    'archived' => 'Archivé',
];
$statusLabel = $statusLabels[$status] ?? $status;
$statusBadgeClass = match ($status) {
    'draft' => 'border-amber-300/80 bg-gradient-to-r from-amber-50 to-orange-50 text-amber-900 shadow-sm shadow-amber-200/40',
    'pending_validation' => 'border-amber-400/60 bg-gradient-to-r from-amber-100 to-yellow-50 text-amber-950',
    'validated' => 'border-sky-300/80 bg-gradient-to-r from-sky-50 to-cyan-50 text-sky-950 shadow-sm shadow-sky-200/30',
    'signed' => 'border-emerald-400/70 bg-gradient-to-r from-emerald-50 to-teal-50 text-emerald-950 shadow-sm shadow-emerald-200/40',
    'rejected' => 'border-rose-300/80 bg-gradient-to-r from-rose-50 to-red-50 text-rose-950',
    'sent' => 'border-blue-300/80 bg-gradient-to-r from-blue-50 to-indigo-50 text-blue-950',
    'archived' => 'border-slate-300 bg-slate-100 text-slate-700',
    default => 'border-slate-200 bg-slate-50 text-slate-800',
};
$isDraft = $status === 'draft';
$isSigned = !empty($doc['signed_at']);
?>
<link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/assets/css/courrier-document.css" />
<div class="min-h-screen bg-gradient-to-b from-slate-200 via-slate-100 to-slate-200/90">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 font-sans text-slate-900">
    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200/80 rounded-xl px-4 py-2"><?= htmlspecialchars((string)\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm font-medium text-red-800 bg-red-50 border border-red-200 rounded-xl px-4 py-2"><?= htmlspecialchars((string)\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>

    <nav class="mb-8 flex flex-wrap items-center gap-2 text-sm">
        <a href="<?= $baseUrl ?>/courrier" class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 bg-white/90 px-3 py-2 text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-white hover:text-slate-900 hover:shadow">
            <span aria-hidden="true">←</span>
            <span class="font-semibold">Bureau Courrier</span>
        </a>
        <span class="text-slate-300">/</span>
        <span class="font-bold text-slate-800 tracking-tight">Lecture</span>
    </nav>

    <section class="overflow-hidden rounded-[2rem] border border-slate-200/90 bg-white shadow-[0_24px_80px_-32px_rgba(15,23,42,0.35)] ring-1 ring-slate-900/5">
        <div class="h-1.5 bg-gradient-to-r from-slate-800 via-slate-600 to-amber-600"></div>
        <div class="grid gap-8 px-5 py-7 lg:grid-cols-[1.2fr_0.85fr] lg:px-10 lg:py-9">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-black uppercase tracking-[0.18em] <?= htmlspecialchars($statusBadgeClass) ?>">
                        <?= htmlspecialchars($statusLabel) ?>
                    </span>
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">
                        Bureau Courrier
                    </span>
                    <?php if ($isSigned): ?>
                    <span class="inline-flex items-center rounded-full border-2 border-red-600 bg-gradient-to-b from-red-50 to-red-100/90 px-3 py-1 text-[10px] font-black uppercase tracking-[0.22em] text-red-700 shadow-sm shadow-red-900/10" title="Document authentifié">
                        Original signé
                    </span>
                    <?php endif; ?>
                </div>

                <h1 class="mt-5 text-3xl font-black uppercase tracking-tight text-slate-950 md:text-4xl">
                    <?= htmlspecialchars($doc['title'] ?: 'Sans titre') ?>
                </h1>
                <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600">
                    Visualisation du document, de son état et des métadonnées. Impression et modification selon vos droits et le statut du dossier.
                </p>

                <div class="mt-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white p-4 shadow-inner">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Référence</p>
                        <p class="mt-2 font-mono text-sm font-bold text-slate-900"><?= !empty($doc['reference_number']) ? htmlspecialchars($doc['reference_number']) : '—' ?></p>
                    </div>
                    <div class="rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white p-4 shadow-inner">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Objet</p>
                        <p class="mt-2 text-sm font-semibold leading-snug text-slate-900"><?= !empty($doc['subject']) ? htmlspecialchars($doc['subject']) : '—' ?></p>
                    </div>
                    <div class="rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white p-4 shadow-inner">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Destinataire</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900"><?= !empty($doc['destination_label']) ? htmlspecialchars($doc['destination_label']) : '—' ?></p>
                    </div>
                    <div class="rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white p-4 shadow-inner">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Émetteur</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900"><?= !empty($doc['issuer_label']) ? htmlspecialchars($doc['issuer_label']) : '—' ?></p>
                    </div>
                </div>
            </div>

            <aside class="flex flex-col justify-between rounded-[1.5rem] border border-slate-200/80 bg-gradient-to-br from-slate-50 via-white to-slate-100/90 p-5 shadow-inner">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Dernière modification</p>
                    <p class="mt-2 text-sm font-bold text-slate-900"><?= !empty($doc['updated_at']) ? htmlspecialchars(date('d/m/Y à H:i', strtotime($doc['updated_at']))) : '—' ?></p>
                    <?php if ($isSigned && !empty($doc['signed_at'])): ?>
                    <p class="mt-3 text-[10px] font-black uppercase tracking-[0.16em] text-red-600">Signature</p>
                    <p class="text-sm font-bold text-red-800"><?= htmlspecialchars(date('d/m/Y à H:i', strtotime($doc['signed_at']))) ?></p>
                    <?php endif; ?>
                    <div class="mt-5 space-y-3">
                        <div class="rounded-xl border border-slate-200 bg-white/90 px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Statut</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900"><?= $isDraft ? 'Document modifiable' : 'Document figé ou validé selon workflow' ?></p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white/90 px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Support</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">A4 portrait</p>
                        </div>
                    </div>
                </div>
                <div class="mt-6 grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    <a href="<?= $baseUrl ?>/courrier" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-slate-700 transition hover:bg-slate-50">
                        Retour
                    </a>
                    <a href="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/print" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-white transition hover:bg-slate-800 shadow-lg shadow-slate-900/25">
                        Imprimer
                    </a>
                    <?php if ($isDraft): ?>
                    <a href="<?= $baseUrl ?>/courrier/editor/<?= (int)$doc['id'] ?>" class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-amber-500 to-orange-600 px-4 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-white transition hover:brightness-105 shadow-md shadow-amber-900/20">
                        Éditer
                    </a>
                    <?php endif; ?>
                    <?php if ($isSigned): ?>
                    <a href="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/verify" class="inline-flex items-center justify-center rounded-2xl border-2 border-emerald-600 bg-emerald-50/80 px-4 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-emerald-900 transition hover:bg-emerald-100">
                        Vérifier l'authenticité
                    </a>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </section>

    <div class="lg:grid lg:grid-cols-[1fr_17rem] xl:grid-cols-[1fr_19rem] gap-8 lg:items-start mt-10">
        <div class="min-w-0">
            <section class="overflow-hidden rounded-[2rem] border border-slate-200/90 bg-white shadow-[0_20px_70px_-30px_rgba(15,23,42,0.2)] ring-1 ring-slate-900/5">
                <div class="border-b border-slate-200/80 bg-gradient-to-r from-slate-50 to-white px-5 py-4 lg:px-8">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Prévisualisation</p>
                            <h2 class="mt-1 text-xl font-black tracking-tight text-slate-950">Lecture du document</h2>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-600">Format A4</span>
                            <span class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs font-bold text-slate-600">Portrait</span>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-b from-slate-100 to-slate-200/40 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="rounded-[1.75rem] border border-slate-200/90 bg-white p-3 shadow-inner sm:p-5 lg:p-6">
                        <div class="courrier-preview-container min-h-[320px] rounded-[1.5rem] bg-slate-50/80 p-2 sm:p-4 text-slate-800 ring-1 ring-inset ring-slate-200/60">
                            <?php if ($previewHtml): ?>
                            <?= $previewHtml ?>
                            <?php else: ?>
                            <div class="prose prose-slate max-w-none text-sm p-4">
                                <?= !empty(trim(strip_tags($doc['body_rendered'] ?? ''))) ? $doc['body_rendered'] : '<p class="text-slate-400">Aucun contenu.</p>' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <p class="mt-6 text-center text-xs text-slate-500 lg:hidden">
                <a href="<?= $baseUrl ?>/courrier" class="font-semibold hover:text-slate-800">Retour au Bureau Courrier</a>
            </p>
        </div>

        <aside class="mt-8 lg:mt-0 space-y-6 lg:sticky lg:top-6">
            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-md shadow-slate-900/5 space-y-3 ring-1 ring-slate-900/5">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Actions rapides</p>
                <div class="flex flex-col gap-2">
                    <a href="<?= $baseUrl ?>/courrier" class="text-center px-4 py-2.5 border border-slate-300 text-slate-800 text-xs font-bold rounded-xl hover:bg-slate-50 transition-colors uppercase tracking-wider">
                        Retour
                    </a>
                    <a href="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/print" target="_blank" rel="noopener" class="text-center px-4 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-colors uppercase tracking-wider shadow-md">
                        Imprimer
                    </a>
                    <?php if ($isDraft): ?>
                    <a href="<?= $baseUrl ?>/courrier/editor/<?= (int)$doc['id'] ?>" class="text-center px-4 py-2.5 bg-gradient-to-r from-amber-500 to-orange-600 text-white text-xs font-bold rounded-xl hover:brightness-105 transition uppercase tracking-wider">
                        Éditer
                    </a>
                    <?php endif; ?>
                    <?php if ($isSigned): ?>
                    <a href="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/verify" class="text-center px-4 py-2.5 border-2 border-emerald-600 text-emerald-900 text-xs font-bold rounded-xl hover:bg-emerald-50 transition-colors uppercase tracking-wider bg-emerald-50/50">
                        Vérifier l'authenticité
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-md shadow-slate-900/5 ring-1 ring-slate-900/5">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-3">Notifier des membres</p>
                <p class="text-xs text-slate-600 mb-4">Notification in-app avec lien vers ce document.</p>
                <form method="post" action="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/notify" class="space-y-3">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="notify_user_ids" class="sr-only">Utilisateurs</label>
                    <select name="notify_user_ids[]" id="notify_user_ids" multiple size="8" class="w-full rounded-xl border border-slate-300 bg-slate-50 text-xs text-slate-800 focus:ring-2 focus:ring-slate-400 focus:border-slate-400 py-2">
                        <?php foreach ($tenantUsers as $u): ?>
                        <?php
                        $uid = (int) ($u['id'] ?? 0);
                        if ($uid <= 0 || $uid === $currentUserId) {
                            continue;
                        }
                        $label = trim((string) ($u['display_name'] ?? ''));
                        if ($label === '') {
                            $label = (string) ($u['email'] ?? 'Utilisateur #' . $uid);
                        } else {
                            $label .= ' — ' . ($u['email'] ?? '');
                        }
                        ?>
                        <option value="<?= $uid ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[10px] text-slate-500">Ctrl / Cmd pour sélection multiple.</p>
                    <button type="submit" class="w-full px-4 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition uppercase tracking-wider shadow-md">
                        Envoyer les notifications
                    </button>
                </form>
            </div>

            <a href="<?= $baseUrl ?>/courrier/notifications" class="block text-center text-xs font-semibold text-slate-600 hover:text-slate-900 underline underline-offset-2">
                Mes notifications courrier
            </a>
        </aside>
    </div>

    <div class="mt-8 flex flex-col items-center justify-between gap-3 border-t border-slate-200/80 pt-6 text-center sm:flex-row sm:text-left">
        <p class="text-xs leading-5 text-slate-500 max-w-xl">
            Lecture documentaire. Impression et modification selon le statut du document et vos droits.
        </p>
        <a href="<?= $baseUrl ?>/courrier" class="text-sm font-bold text-slate-700 transition hover:text-slate-950 hidden lg:inline">
            Retour au Bureau Courrier →
        </a>
    </div>
</div>
</div>
