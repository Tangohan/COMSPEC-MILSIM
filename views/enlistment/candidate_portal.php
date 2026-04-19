<?php
$enlistment = is_array($enlistment ?? null) ? $enlistment : [];
$messages = is_array($messages ?? null) ? $messages : [];
$tenant = is_array($tenant ?? null) ? $tenant : [];
$tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
$status = (string) ($enlistment['status'] ?? 'submitted');
$statusFr = [
    'submitted' => 'En cours d’instruction',
    'reviewed' => 'Accepté',
    'rejected' => 'Refusé',
    'blocked' => 'Non admis',
][$status] ?? $status;
$twCss = is_file(base_path('public/assets/css/tailwind.css')) ? url('assets/css/tailwind.css') : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi de candidature — <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if ($twCss !== null): ?>
    <link href="<?= htmlspecialchars($twCss, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<main class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
        <div class="bg-slate-900 px-6 py-6 text-white">
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-emerald-400">Portail candidature</p>
            <h1 class="mt-2 text-2xl font-black uppercase tracking-tight">Suivi du dossier #<?= (int) ($enlistment['id'] ?? 0) ?></h1>
            <p class="mt-2 text-sm text-slate-300"><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?> · Statut actuel : <strong><?= htmlspecialchars($statusFr, ENT_QUOTES, 'UTF-8') ?></strong></p>
        </div>
        <div class="space-y-6 p-6">
            <?php if (!empty($flashOk)): ?><p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <?php if (!empty($flashErr)): ?><p class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?= htmlspecialchars((string) $flashErr, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-600">Messages et échanges</h2>
                <ul class="mt-4 space-y-3">
                    <?php foreach ($messages as $m): ?>
                        <?php $isCandidate = ((string) ($m['entry_kind'] ?? '')) === 'candidate'; ?>
                        <li class="rounded-xl border px-4 py-3 text-sm <?= $isCandidate ? 'border-sky-200 bg-sky-50 text-sky-950' : 'border-emerald-200 bg-emerald-50 text-emerald-950' ?>">
                            <p class="text-[10px] font-bold uppercase tracking-wide"><?= $isCandidate ? 'Vous' : 'Recrutement' ?> · <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($m['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-1 whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars((string) ($m['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($messages === []): ?><li class="text-sm text-slate-500">Aucun message pour le moment.</li><?php endif; ?>
                </ul>
            </article>

            <form method="post" action="<?= htmlspecialchars(url('enlistment/suivi/' . rawurlencode((string) $token) . '/message'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-2xl border border-slate-200 bg-white p-4">
                <?= \App\Core\Csrf::field() ?>
                <label for="candidate_message" class="text-xs font-bold uppercase tracking-wide text-slate-700">Envoyer un message à l’équipe</label>
                <textarea id="candidate_message" name="candidate_message" rows="4" maxlength="4000" required class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Question, précision, confirmation de créneau..."></textarea>
                <button type="submit" class="mt-3 inline-flex min-h-[2.5rem] items-center rounded-xl bg-slate-900 px-5 py-2 text-xs font-black uppercase tracking-wide text-white">Transmettre</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
