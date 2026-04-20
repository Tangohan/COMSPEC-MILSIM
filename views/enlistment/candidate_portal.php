<?php
declare(strict_types=1);
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
][$status] ?? 'En cours de traitement';
$statusBand = match ($status) {
    'submitted' => 'bg-amber-500',
    'reviewed' => 'bg-emerald-500',
    'rejected' => 'bg-rose-500',
    'blocked' => 'bg-slate-700',
    default => 'bg-slate-500',
};
$first = trim((string) ($enlistment['first_name'] ?? ''));
$last = trim((string) ($enlistment['last_name'] ?? ''));
$callsign = trim((string) ($enlistment['callsign'] ?? ''));
$fullName = trim($first . ' ' . $last);
$displayName = $fullName !== '' ? $fullName : ($callsign !== '' ? $callsign : 'Candidat');
$emailRaw = trim((string) ($enlistment['email'] ?? ''));
$maskEmail = static function (string $e): string {
    if ($e === '' || !str_contains($e, '@')) {
        return '—';
    }
    [$u, $d] = explode('@', $e, 2);
    $u = (string) $u;
    $d = (string) $d;
    $head = $u !== '' ? mb_substr($u, 0, 1) : '?';

    return $head . '•••@' . $d;
};
$maskedEmail = $maskEmail($emailRaw);
$createdAt = trim((string) ($enlistment['created_at'] ?? ''));
$createdFmt = $createdAt !== '' ? date('d/m/Y à H:i', strtotime($createdAt) ?: time()) : '—';
$updatedAt = trim((string) ($enlistment['updated_at'] ?? ''));
$expiresAt = trim((string) ($enlistment['candidate_portal_expires_at'] ?? ''));
$expiresFmt = $expiresAt !== '' ? date('d/m/Y à H:i', strtotime($expiresAt) ?: time()) : '—';
$platform = trim((string) ($enlistment['platform'] ?? ''));
$country = trim((string) ($enlistment['country'] ?? ''));
$openingId = (int) ($enlistment['recruitment_opening_id'] ?? 0);
$attachments = is_array($attachments ?? null) ? $attachments : [];
$portalAttachmentsById = [];
foreach ($attachments as $a) {
    $aidMap = (int) ($a['id'] ?? 0);
    if ($aidMap > 0) {
        $portalAttachmentsById[$aidMap] = $a;
    }
}
$portalUploadsReady = !empty($portalUploadsReady);
$allowPortalFiles = !empty($allowPortalFiles);
$allowPortalAudio = !empty($allowPortalAudio);
$canUploadSomething = $portalUploadsReady && ($allowPortalFiles || $allowPortalAudio);
$attachmentCount = count($attachments);
$fmtBytes = static function (int $b): string {
    if ($b >= 1048576) {
        return round($b / 1048576, 1) . ' Mo';
    }
    if ($b >= 1024) {
        return round($b / 1024, 1) . ' ko';
    }

    return (string) max(0, $b) . ' o';
};
$uploadAccept = '';
if ($allowPortalFiles && $allowPortalAudio) {
    $uploadAccept = '.pdf,.png,.jpg,.jpeg,.webp,.txt,audio/*';
} elseif ($allowPortalFiles) {
    $uploadAccept = '.pdf,.png,.jpg,.jpeg,.webp,.txt';
} elseif ($allowPortalAudio) {
    $uploadAccept = 'audio/*';
}
$portalRetroEligible = !empty($portalRetroEligible);
$portalRetroTableReady = !empty($portalRetroTableReady);
$candidateRetroFeedback = is_array($candidateRetroFeedback ?? null) ? $candidateRetroFeedback : null;
$candidateRetroLabels = [5 => 'Très satisfaisant', 4 => 'Bon', 3 => 'Correct', 2 => 'En-dessous des attentes', 1 => 'À améliorer'];
$msgCount = count($messages);
$lastActivity = $createdAt;
foreach ($messages as $m) {
    $t = trim((string) ($m['created_at'] ?? ''));
    if ($t !== '' && ($lastActivity === '' || strtotime($t) > strtotime($lastActivity))) {
        $lastActivity = $t;
    }
}
if ($updatedAt !== '' && ($lastActivity === '' || strtotime($updatedAt) > strtotime($lastActivity))) {
    $lastActivity = $updatedAt;
}
$lastActivityFmt = $lastActivity !== '' ? date('d/m/Y à H:i', strtotime($lastActivity) ?: time()) : '—';
$portalSteps = [
    [
        'id' => 'depot',
        'label' => 'Dépôt reçu',
        'hint' => 'Votre candidature est enregistrée.',
        'state' => 'done',
    ],
    [
        'id' => 'suivi',
        'label' => 'Suivi & échanges',
        'hint' => 'Messages, pièces et enregistrements vocaux avec l’équipe.',
        'state' => in_array($status, ['reviewed', 'rejected', 'blocked'], true) ? 'done' : 'current',
    ],
    [
        'id' => 'decision',
        'label' => 'Décision',
        'hint' => match ($status) {
            'reviewed' => 'Dossier accepté par la communauté.',
            'rejected' => 'Dossier refusé. Vous pouvez écrire à l’équipe pour des précisions.',
            'blocked' => 'Dossier classé non admis.',
            default => 'L’équipe rend sa décision sur ce dossier.',
        },
        'state' => in_array($status, ['reviewed', 'rejected', 'blocked'], true) ? 'done' : 'upcoming',
    ],
];
$initials = '';
if ($fullName !== '') {
    $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY);
    if (is_array($parts) && $parts !== []) {
        $initials .= mb_strtoupper(mb_substr((string) $parts[0], 0, 1));
        if (isset($parts[1])) {
            $initials .= mb_strtoupper(mb_substr((string) $parts[1], 0, 1));
        }
    }
} elseif ($callsign !== '') {
    $initials = mb_strtoupper(mb_substr($callsign, 0, 2));
}
if ($initials === '') {
    $initials = 'CA';
}
$baseUrl = url('');
$tailwindBaseUrl = $baseUrl;
ob_start();
require base_path('views/partials/tailwind_cdn_or_build.php');
$tailwindHead = (string) ob_get_clean();
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi de candidature — <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<?= $tailwindHead ?>
    <style>
      body { font-family: Inter, system-ui, sans-serif; }
      .portal-grain::before {
        content: "";
        position: fixed;
        inset: 0;
        pointer-events: none;
        opacity: .035;
        z-index: 0;
        background-image: radial-gradient(circle at 20% 20%, #0f172a 0.5px, transparent 0.6px), radial-gradient(circle at 80% 70%, #0f172a 0.5px, transparent 0.6px);
        background-size: 20px 20px;
      }
      .snap-shell {
        background: linear-gradient(145deg, #7c3aed 0%, #db2777 45%, #f97316 100%);
        box-shadow: 0 20px 50px -12px rgba(124, 58, 237, 0.45);
      }
      .snap-btn {
        width: 5.5rem;
        height: 5.5rem;
        border-radius: 9999px;
        border: 4px solid rgba(255,255,255,0.35);
        background: rgba(255,255,255,0.15);
        backdrop-filter: blur(6px);
        transition: transform 0.12s ease, box-shadow 0.2s ease, background 0.2s ease;
      }
      .snap-btn:active, .snap-btn.snap-recording {
        transform: scale(1.08);
        background: rgba(239, 68, 68, 0.95);
        border-color: rgba(255,255,255,0.55);
        box-shadow: 0 0 0 10px rgba(239, 68, 68, 0.25);
      }
      .snap-audio-pill audio {
        width: 100%;
        max-width: 18rem;
        height: 2.5rem;
      }
    </style>
</head>
<body class="relative min-h-screen overflow-x-hidden bg-slate-100 font-sans text-slate-900 antialiased">
<div class="portal-grain" aria-hidden="true"></div>
<nav class="relative z-20 sticky top-0 border-b border-slate-800/80 bg-slate-950 text-white">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500 text-sm font-black text-slate-950"><?= htmlspecialchars(mb_substr($tenantName, 0, 1) ?: 'A', ENT_QUOTES, 'UTF-8') ?></span>
            <div class="min-w-0">
                <p class="text-[9px] font-black uppercase tracking-[0.28em] text-emerald-400/95">Suivi sécurisé</p>
                <p class="truncate text-xs font-semibold text-slate-200"><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <a href="<?= htmlspecialchars($baseUrl . '/', ENT_QUOTES, 'UTF-8') ?>" class="shrink-0 rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-200 transition hover:bg-white/10">Accueil Athena</a>
    </div>
</nav>

<main class="relative z-10 mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:py-10">
    <header class="mb-8">
        <p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-500">Portail candidature</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">Votre dossier</h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">Échanges avec l’équipe recrutement, statut du dossier et rappels utiles. Gardez ce lien précieux : il permet de reprendre la conversation sans compte sur le portail.</p>
    </header>

    <div class="grid gap-8 lg:grid-cols-12">
        <aside class="space-y-5 lg:col-span-4">
            <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
                <div class="h-1.5 <?= htmlspecialchars($statusBand, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></div>
                <div class="border-b border-slate-100 px-5 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Statut du dossier</p>
                    <p class="mt-1 text-lg font-bold text-slate-900"><?= htmlspecialchars($statusFr, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <dl class="space-y-3 px-5 py-4 text-sm">
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Référence</dt>
                        <dd class="font-mono text-xs font-semibold text-slate-800">#<?= (int) ($enlistment['id'] ?? 0) ?></dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Candidat</dt>
                        <dd class="max-w-[58%] text-right font-medium text-slate-900"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <?php if ($callsign !== '' && $fullName !== ''): ?>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Indicatif</dt>
                        <dd class="max-w-[58%] text-right font-medium text-slate-800"><?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Contact dossier</dt>
                        <dd class="max-w-[58%] text-right text-xs text-slate-700"><?= htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Dépôt initial</dt>
                        <dd class="text-right text-xs font-medium text-slate-800"><?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Dernière activité</dt>
                        <dd class="text-right text-xs font-medium text-slate-800"><?= htmlspecialchars($lastActivityFmt, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Messages</dt>
                        <dd class="text-right text-xs font-semibold text-slate-800"><?= (int) $msgCount ?></dd>
                    </div>
                    <?php if ($portalUploadsReady): ?>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Pièces transmises</dt>
                        <dd class="text-right text-xs font-semibold text-slate-800"><?= (int) $attachmentCount ?></dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Envoi de pièces</dt>
                        <dd class="text-right text-xs font-medium <?= $canUploadSomething ? 'text-emerald-800' : 'text-slate-500' ?>"><?= $canUploadSomething ? 'Autorisé par l’équipe' : 'Non activé sur ce dossier' ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($openingId > 0): ?>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Offre publiée</dt>
                        <dd class="text-right text-xs font-medium text-emerald-800">Dossier rattaché à une offre de la vitrine</dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($platform !== ''): ?>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Plateforme</dt>
                        <dd class="max-w-[58%] text-right text-xs text-slate-800"><?= htmlspecialchars($platform, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($country !== ''): ?>
                    <div class="flex justify-between gap-3 pb-1">
                        <dt class="text-slate-500">Pays / fuseau</dt>
                        <dd class="max-w-[58%] text-right text-xs text-slate-800"><?= htmlspecialchars($country, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
                <div class="border-t border-slate-100 bg-slate-50 px-5 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Lien de suivi</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600">Valide au moins jusqu’au <span class="font-semibold text-slate-800"><?= htmlspecialchars($expiresFmt, ENT_QUOTES, 'UTF-8') ?></span>. Un nouvel e-mail de l’équipe peut prolonger l’accès.</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
                <div class="border-b border-slate-100 px-5 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Étapes du dossier</p>
                    <p class="mt-1 text-xs text-slate-600">Où en est votre parcours côté recrutement.</p>
                </div>
                <ol class="relative px-5 py-5">
                    <?php foreach ($portalSteps as $si => $st): ?>
                        <?php
                        $stState = (string) ($st['state'] ?? 'upcoming');
                        $isDone = $stState === 'done';
                        $isCurrent = $stState === 'current';
                        $dotClass = $isDone
                            ? 'border-emerald-600 bg-emerald-500 text-white'
                            : ($isCurrent ? 'border-amber-500 bg-amber-500 text-white ring-4 ring-amber-200' : 'border-slate-200 bg-white text-slate-300');
                        $lineClass = $si < count($portalSteps) - 1 ? ($isDone ? 'bg-emerald-200' : 'bg-slate-200') : '';
                        ?>
                        <li class="relative flex gap-4 pb-8 last:pb-0"<?= $isCurrent ? ' aria-current="step"' : '' ?>>
                            <?php if ($si < count($portalSteps) - 1): ?>
                                <span class="absolute left-[0.65rem] top-8 bottom-0 w-0.5 <?= htmlspecialchars($lineClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span class="relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 text-[10px] font-black <?= $dotClass ?>" aria-hidden="true"><?= $isDone ? '✓' : (string) ($si + 1) ?></span>
                            <div class="min-w-0 pt-0.5">
                                <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars((string) ($st['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-1 text-xs leading-relaxed text-slate-600"><?= htmlspecialchars((string) ($st['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if ($isCurrent && (string) ($st['id'] ?? '') === 'suivi'): ?>
                                    <p class="mt-2 text-[11px] font-semibold text-amber-800">Étape en cours — continuez à échanger sur ce fil si besoin.</p>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </aside>

        <div class="space-y-6 lg:col-span-8">
            <?php if (!empty($flashOk)): ?>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-950 shadow-sm" role="status"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if (!empty($flashErr)): ?>
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-950 shadow-sm" role="alert"><?= htmlspecialchars((string) $flashErr, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($portalRetroEligible || $candidateRetroFeedback): ?>
            <section class="overflow-hidden rounded-2xl border border-sky-200 bg-white shadow-sm ring-1 ring-slate-900/[0.04]" aria-labelledby="bilan-processus">
                <div class="border-b border-sky-100 bg-sky-50 px-5 py-4 sm:px-6">
                    <h2 id="bilan-processus" class="text-[11px] font-bold uppercase tracking-[0.28em] text-sky-900/90">Votre ressenti sur le processus</h2>
                    <p class="mt-1 text-sm text-sky-950/90">Au moins 30&nbsp;jours après votre dépôt, vous pouvez partager une note anonyme côté équipe pour aider à améliorer l’accueil des candidats.</p>
                </div>
                <div class="space-y-5 px-5 py-5 sm:px-6">
                    <?php if ($candidateRetroFeedback): ?>
                        <p class="text-sm font-semibold text-slate-900">Merci : votre retour a bien été enregistré.</p>
                        <p class="text-sm text-slate-700">Note laissée : <span class="font-bold"><?= (int) ($candidateRetroFeedback['rating'] ?? 0) ?> / 5</span> — <?= htmlspecialchars($candidateRetroLabels[(int) ($candidateRetroFeedback['rating'] ?? 0)] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-800 whitespace-pre-wrap"><?= htmlspecialchars((string) ($candidateRetroFeedback['comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <p class="text-xs text-slate-500">Vous pouvez renvoyer le formulaire ci-dessous pour mettre à jour votre message.</p>
                    <?php endif; ?>
                    <?php if ($portalRetroEligible && !$portalRetroTableReady): ?>
                        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Cette communauté n’a pas encore activé le formulaire de bilan sur son installation.</p>
                    <?php elseif ($portalRetroEligible && $portalRetroTableReady): ?>
                        <form method="post" action="<?= htmlspecialchars(url('enlistment/suivi/' . rawurlencode($token) . '/bilan-candidat'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
                            <?= \App\Core\Csrf::field() ?>
                            <div>
                                <label for="candidate_retro_rating" class="text-xs font-bold text-slate-800">Votre note globale</label>
                                <select id="candidate_retro_rating" name="candidate_retro_rating" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900">
                                    <?php foreach ([5, 4, 3, 2, 1] as $cri): ?>
                                        <option value="<?= $cri ?>"><?= $cri ?> — <?= htmlspecialchars($candidateRetroLabels[$cri] ?? (string) $cri, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="candidate_retro_comment" class="text-xs font-bold text-slate-800">Votre message (obligatoire)</label>
                                <textarea id="candidate_retro_comment" name="candidate_retro_comment" rows="4" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900" placeholder="Ce qui vous a semblé clair, trop lent, manquant d’informations…"></textarea>
                            </div>
                            <button type="submit" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl bg-sky-700 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-sky-800">Envoyer mon retour</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="nouveau-message">
                <h2 id="nouveau-message" class="text-xs font-black uppercase tracking-wider text-slate-700">Écrire à l’équipe</h2>
                <p class="mt-1 text-sm text-slate-500">Précisions sur votre disponibilité, questions sur l’entretien ou documents demandés. Réponse par e-mail et dans ce fil.</p>
                <form method="post" action="<?= htmlspecialchars(url('enlistment/suivi/' . rawurlencode((string) $token) . '/message'), ENT_QUOTES, 'UTF-8') ?>" class="mt-5 space-y-4">
                    <?= \App\Core\Csrf::field() ?>
                    <div>
                        <label for="candidate_message" class="sr-only">Votre message</label>
                        <textarea id="candidate_message" name="candidate_message" rows="5" maxlength="4000" required class="min-h-[8rem] w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 shadow-inner outline-none transition placeholder:text-slate-400 focus:border-slate-900 focus:bg-white focus:ring-2 focus:ring-slate-900/10" placeholder="Ex. : je confirme le créneau proposé, ma disponibilité change à partir de la semaine prochaine…"></textarea>
                        <p class="mt-1.5 text-[11px] text-slate-500">4&nbsp;000 caractères maximum. Soyez factuel : cela aide l’équipe à traiter votre dossier plus vite.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl bg-slate-900 px-6 text-xs font-black uppercase tracking-wide text-white shadow-md transition hover:bg-slate-800">Transmettre le message</button>
                        <p class="text-[11px] text-slate-500">Un e-mail d’alerte est envoyé aux personnes habilitées au recrutement.</p>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]" aria-labelledby="fil-messages">
                <div class="border-b border-slate-100 bg-gradient-to-r from-slate-900 to-slate-800 px-5 py-4 sm:px-6">
                    <h2 id="fil-messages" class="text-[11px] font-bold uppercase tracking-[0.28em] text-emerald-400/95">Fil de messages</h2>
                    <p class="mt-1 text-sm text-slate-300">Seuls vous et l’équipe recrutement de la communauté voyez ces échanges sur ce lien.</p>
                </div>
                <div class="max-h-[min(28rem,70vh)] space-y-4 overflow-y-auto px-4 py-5 sm:px-6">
                    <?php if ($messages === []): ?>
                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                            Aucun message pour l’instant. Lorsque l’équipe mettra à jour votre dossier, la réponse apparaîtra ici et un e-mail vous sera envoyé si votre adresse est valide.
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $i => $m): ?>
                            <?php
                            $isCandidate = ((string) ($m['entry_kind'] ?? '')) === 'candidate';
                            $created = trim((string) ($m['created_at'] ?? ''));
                            $dt = $created !== '' ? date('d/m/Y à H:i', strtotime($created) ?: time()) : '—';
                            $bodyRaw = (string) ($m['body'] ?? '');
                            $linkedPieceId = null;
                            if (preg_match('/\n\n\[piece:#(\d+)\]\s*$/', $bodyRaw, $pm)) {
                                $linkedPieceId = (int) $pm[1];
                                $bodyRaw = trim((string) preg_replace('/\n\n\[piece:#\d+\]\s*$/', '', $bodyRaw));
                            }
                            $statLine = null;
                            $bodyDisplay = $bodyRaw;
                            if (!$isCandidate && preg_match('/^Statut\s*:\s*([^\r\n]+)/u', $bodyRaw, $mm)) {
                                $statLine = trim((string) ($mm[1]));
                                $bodyDisplay = trim(preg_replace('/^Statut\s*:\s*[^\r\n]+\s*/u', '', $bodyRaw) ?? $bodyRaw);
                            }
                            $linkedAtt = ($linkedPieceId !== null && $linkedPieceId > 0) ? ($portalAttachmentsById[$linkedPieceId] ?? null) : null;
                            $linkedIsAudio = is_array($linkedAtt) && ((string) ($linkedAtt['kind'] ?? '')) === 'audio';
                            $audioSrc = ($linkedIsAudio && $linkedPieceId > 0)
                                ? htmlspecialchars(url('enlistment/suivi/' . rawurlencode((string) $token) . '/piece/' . $linkedPieceId . '?inline=1'), ENT_QUOTES, 'UTF-8')
                                : '';
                            ?>
                            <div class="flex <?= $isCandidate ? 'justify-end' : 'justify-start' ?>">
                                <div class="flex max-w-[min(100%,34rem)] gap-3 <?= $isCandidate ? 'flex-row-reverse' : 'flex-row' ?>">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-black <?= $isCandidate ? 'bg-sky-600 text-white' : 'bg-emerald-600 text-white' ?>" aria-hidden="true">
                                        <?= $isCandidate ? htmlspecialchars(mb_substr($initials, 0, 2), ENT_QUOTES, 'UTF-8') : 'RH' ?>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2 <?= $isCandidate ? 'justify-end' : 'justify-start' ?>">
                                            <span class="text-[10px] font-black uppercase tracking-wider <?= $isCandidate ? 'text-sky-800' : 'text-emerald-900' ?>"><?= $isCandidate ? 'Vous' : 'Recrutement' ?></span>
                                            <span class="text-[10px] font-semibold tabular-nums text-slate-400"><?= htmlspecialchars($dt, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-slate-500">#<?= (int) $i + 1 ?></span>
                                        </div>
                                        <div class="mt-1.5 rounded-2xl border px-4 py-3 text-sm leading-relaxed shadow-sm <?= $isCandidate ? 'rounded-tr-sm border-sky-200 bg-sky-50 text-slate-900' : 'rounded-tl-sm border-emerald-200 bg-emerald-50/90 text-slate-900' ?>">
                                            <?php if ($statLine !== null && $statLine !== ''): ?>
                                                <p class="mb-2 inline-flex flex-wrap items-center gap-2 text-xs font-bold text-emerald-950">
                                                    <span class="rounded-full bg-emerald-600/15 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-emerald-900">Mise à jour</span>
                                                    <span><?= htmlspecialchars($statLine, ENT_QUOTES, 'UTF-8') ?></span>
                                                </p>
                                            <?php endif; ?>
                                            <div class="whitespace-pre-wrap text-[15px] text-slate-800"><?= $bodyDisplay !== '' ? nl2br(htmlspecialchars($bodyDisplay, ENT_QUOTES, 'UTF-8')) : '—' ?></div>
                                            <?php if ($linkedIsAudio && $audioSrc !== ''): ?>
                                                <div class="snap-audio-pill mt-3 rounded-2xl border border-white/30 bg-white/15 p-3 backdrop-blur-sm">
                                                    <p class="mb-2 text-[10px] font-black uppercase tracking-wider text-white/90">Lecture</p>
                                                    <audio controls preload="metadata" src="<?= $audioSrc ?>" class="rounded-lg opacity-95"></audio>
                                                </div>
                                            <?php elseif ($linkedPieceId !== null && $linkedPieceId > 0 && is_array($linkedAtt)): ?>
                                                <div class="mt-3">
                                                    <a href="<?= htmlspecialchars(url('enlistment/suivi/' . rawurlencode((string) $token) . '/piece/' . $linkedPieceId . '/preparation'), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-bold text-sky-800 underline decoration-sky-300 underline-offset-2 hover:text-sky-950">Préparer le téléchargement</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($canUploadSomething && $allowPortalAudio): ?>
            <section class="overflow-hidden rounded-[1.75rem] border border-fuchsia-300/60 shadow-lg ring-1 ring-fuchsia-500/10" aria-labelledby="message-vocal-rapide">
                <div class="snap-shell px-5 py-6 sm:px-8 sm:py-8">
                    <h2 id="message-vocal-rapide" class="text-center text-[11px] font-black uppercase tracking-[0.35em] text-white/90">Message vocal instantané</h2>
                    <p class="mx-auto mt-2 max-w-md text-center text-sm leading-relaxed text-white/85">Comme une story courte : maintenez le bouton, parlez, relâchez pour envoyer. Maximum <?= (int) (60) ?> secondes.</p>
                    <div id="snap-rec-root" class="mt-8 flex flex-col items-center">
                        <button type="button" id="snap-record-btn" class="snap-btn flex items-center justify-center text-3xl text-white shadow-xl outline-none focus-visible:ring-4 focus-visible:ring-white/50" aria-pressed="false" aria-label="Maintenir pour enregistrer un message vocal">
                            <span class="select-none" aria-hidden="true">●</span>
                        </button>
                        <p id="snap-hint" class="mt-5 text-center text-xs font-semibold text-white/90">Maintenez le bouton pour enregistrer · relâchez pour envoyer</p>
                        <p id="snap-timer" class="mt-2 hidden text-center text-3xl font-black tabular-nums text-white drop-shadow-md">0:00</p>
                        <p id="snap-status" class="mt-3 hidden text-center text-xs font-bold text-white"></p>
                        <p id="snap-err" class="mt-3 hidden text-center text-xs font-semibold text-amber-100"></p>
                    </div>
                    <p id="snap-nohttps" class="mt-6 hidden text-center text-xs text-white/90">L’enregistrement vocal nécessite une connexion sécurisée (https) ou un navigateur compatible.</p>
                </div>
                <div class="border-t border-white/25 bg-slate-950 px-4 py-3.5 text-center">
                    <p class="text-xs font-medium leading-relaxed text-slate-100">Après l’envoi, l’équipe reçoit la même alerte e-mail que pour un message écrit.</p>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($portalUploadsReady && $attachments !== []): ?>
            <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]" aria-labelledby="liste-pieces">
                <div class="border-b border-slate-100 bg-slate-50 px-5 py-4 sm:px-6">
                    <h2 id="liste-pieces" class="text-xs font-black uppercase tracking-wider text-slate-700">Vos pièces jointes</h2>
                    <p class="mt-1 text-sm text-slate-600">Fichiers déjà transmis. Vous pouvez les télécharger à tout moment tant que ce lien reste valide.</p>
                </div>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($attachments as $att): ?>
                        <?php
                        $aid = (int) ($att['id'] ?? 0);
                        $fn = trim((string) ($att['original_name'] ?? '—'));
                        $k = (string) ($att['kind'] ?? 'file');
                        $sz = (int) ($att['size_bytes'] ?? 0);
                        $when = trim((string) ($att['created_at'] ?? ''));
                        $whenFmt = $when !== '' ? date('d/m/Y à H:i', strtotime($when) ?: time()) : '—';
                        ?>
                        <li class="flex flex-col gap-3 px-5 py-4 sm:px-6 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-slate-900"><?= htmlspecialchars($fn, ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-0.5 text-xs text-slate-500"><?= $k === 'audio' ? 'Enregistrement audio' : 'Document' ?> · <?= htmlspecialchars($fmtBytes($sz), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($whenFmt, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if ($k === 'audio'): ?>
                                    <div class="snap-shell mt-3 max-w-md rounded-2xl p-4">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-white/90">Écoute rapide</p>
                                        <audio controls preload="metadata" src="<?= htmlspecialchars(url('enlistment/suivi/' . rawurlencode((string) $token) . '/piece/' . $aid . '?inline=1'), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 w-full rounded-lg"></audio>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <a href="<?= htmlspecialchars(url('enlistment/suivi/' . rawurlencode((string) $token) . '/piece/' . $aid . '/preparation'), ENT_QUOTES, 'UTF-8') ?>" class="shrink-0 self-start rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-800 transition hover:bg-slate-50"><?= $k === 'audio' ? 'Télécharger l’audio' : 'Télécharger' ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <?php if ($canUploadSomething): ?>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="envoi-piece">
                <h2 id="envoi-piece" class="text-xs font-black uppercase tracking-wider text-slate-700">Envoyer une pièce jointe</h2>
                <p class="mt-1 text-sm text-slate-600">
                    <?php if ($allowPortalFiles && $allowPortalAudio): ?>
                        Documents (PDF, images, texte, jusqu’à 15&nbsp;Mo) ou enregistrement audio (jusqu’à 25&nbsp;Mo).
                    <?php elseif ($allowPortalFiles): ?>
                        Documents uniquement : PDF, images JPEG/PNG/WebP ou fichier texte, jusqu’à 15&nbsp;Mo.
                    <?php else: ?>
                        Enregistrements audio : message vocal instantané ou fichier joint, jusqu’à 25&nbsp;Mo.
                    <?php endif; ?>
                </p>
                <form method="post" action="<?= htmlspecialchars(url('enlistment/suivi/' . rawurlencode((string) $token) . '/piece'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="mt-5 space-y-4">
                    <?= \App\Core\Csrf::field() ?>
                    <div>
                        <label for="portal_upload" class="sr-only">Choisir un fichier</label>
                        <input type="file" id="portal_upload" name="portal_upload" required class="block w-full cursor-pointer rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white" accept="<?= htmlspecialchars($uploadAccept, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <button type="submit" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl bg-emerald-600 px-6 text-xs font-black uppercase tracking-wide text-white shadow-md transition hover:bg-emerald-500">Transmettre le fichier</button>
                    <p class="text-[11px] text-slate-500">Un e-mail d’alerte est envoyé à l’équipe recrutement, comme pour un message texte.</p>
                </form>
            </section>
            <?php elseif ($portalUploadsReady): ?>
            <section class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600">
                L’équipe n’a pas activé l’envoi de pièces jointes pour votre dossier. Si vous devez transmettre un document ou un audio, indiquez-le dans le bloc <span class="font-semibold text-slate-800">Écrire à l’équipe</span> (au-dessus du fil de messages) : l’équipe pourra vous répondre ou activer l’envoi ici.
            </section>
            <?php endif; ?>

        </div>
    </div>
</main>
<?php if ($canUploadSomething && $allowPortalAudio): ?>
<script>
(function () {
  var root = document.getElementById('snap-rec-root');
  var btn = document.getElementById('snap-record-btn');
  if (!root || !btn) return;
  var nh = document.getElementById('snap-nohttps');
  if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
    if (nh) nh.classList.remove('hidden');
    btn.disabled = true;
    btn.classList.add('opacity-40');
    return;
  }
  if (!navigator.mediaDevices || !window.MediaRecorder) {
    if (nh) {
      nh.textContent = 'Votre navigateur ne propose pas l’enregistrement vocal intégré. Utilisez le formulaire fichier ci-dessous ou un autre navigateur.';
      nh.classList.remove('hidden');
    }
    btn.disabled = true;
    btn.classList.add('opacity-40');
    return;
  }
  var uploadUrl = <?= json_encode(url('enlistment/suivi/' . rawurlencode((string) $token) . '/piece'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES) ?>;
  var csrfEl = document.querySelector('input[name="_csrf_token"]');
  var csrf = csrfEl ? csrfEl.value : '';
  var MAX_MS = 60000;
  var mediaStream = null;
  var recorder = null;
  var chunks = [];
  var mimeType = '';
  var startedAt = 0;
  var tickTimer = null;
  var sending = false;
  var pointerDown = false;

  function pickMime() {
    var types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'];
    for (var i = 0; i < types.length; i++) {
      if (MediaRecorder.isTypeSupported(types[i])) return types[i];
    }
    return '';
  }

  function setErr(msg) {
    var e = document.getElementById('snap-err');
    if (!e) return;
    if (msg) { e.textContent = msg; e.classList.remove('hidden'); }
    else { e.textContent = ''; e.classList.add('hidden'); }
  }

  function setStatus(msg, show) {
    var s = document.getElementById('snap-status');
    if (!s) return;
    if (show) { s.textContent = msg; s.classList.remove('hidden'); }
    else { s.textContent = ''; s.classList.add('hidden'); }
  }

  function fmtMs(ms) {
    var s = Math.floor(ms / 1000);
    var m = Math.floor(s / 60);
    s = s % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
  }

  function stopStream() {
    if (mediaStream) {
      mediaStream.getTracks().forEach(function (t) { try { t.stop(); } catch (e) {} });
    }
    mediaStream = null;
  }

  function startRecord() {
    if (sending || recorder) return;
    setErr('');
    mimeType = pickMime();
    if (!mimeType) {
      setErr('Votre navigateur ne permet pas l’enregistrement audio ici. Utilisez le fichier joint ou un autre navigateur.');
      return;
    }
    chunks = [];
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
      mediaStream = stream;
      try {
        recorder = new MediaRecorder(stream, { mimeType: mimeType });
      } catch (e) {
        stopStream();
        setErr('Impossible de démarrer l’enregistrement.');
        return;
      }
      recorder.ondataavailable = function (ev) {
        if (ev.data && ev.data.size > 0) chunks.push(ev.data);
      };
      recorder.onerror = function () {
        setErr('Enregistrement interrompu.');
      };
      recorder.start(250);
      if (!pointerDown) {
        try { if (recorder.state !== 'inactive') recorder.stop(); } catch (e2) {}
        stopStream();
        recorder = null;
        if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }
        btn.classList.remove('snap-recording');
        btn.setAttribute('aria-pressed', 'false');
        var tm0 = document.getElementById('snap-timer');
        if (tm0) tm0.classList.add('hidden');
        var hint0 = document.getElementById('snap-hint');
        if (hint0) hint0.textContent = 'Maintenez le bouton pour enregistrer · relâchez pour envoyer';
        return;
      }
      startedAt = Date.now();
      btn.classList.add('snap-recording');
      btn.setAttribute('aria-pressed', 'true');
      var tm = document.getElementById('snap-timer');
      if (tm) { tm.classList.remove('hidden'); tm.textContent = '0:00'; }
      var hint = document.getElementById('snap-hint');
      if (hint) hint.textContent = 'Relâchez pour envoyer';
      tickTimer = setInterval(function () {
        var el = document.getElementById('snap-timer');
        if (!el) return;
        var d = Date.now() - startedAt;
        el.textContent = fmtMs(d);
        if (d >= MAX_MS) {
          finishRecord(true);
        }
      }, 200);
    }).catch(function () {
      pointerDown = false;
      setErr('Micro refusé ou indisponible. Vérifiez les autorisations du navigateur.');
    });
  }

  function finishRecord(autoMax) {
    if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }
    btn.classList.remove('snap-recording');
    btn.setAttribute('aria-pressed', 'false');
    var tm = document.getElementById('snap-timer');
    if (tm) tm.classList.add('hidden');
    var hint = document.getElementById('snap-hint');
    if (hint) hint.textContent = 'Maintenez le bouton pour enregistrer · relâchez pour envoyer';
    if (!recorder) return;
    var rec = recorder;
    recorder = null;
    try { if (rec.state !== 'inactive') rec.stop(); } catch (e) {}
    stopStream();
    var dur = Date.now() - startedAt;
    if (autoMax) setStatus('Durée max atteinte, envoi…', true);
    rec.onstop = function () {
      var blob = new Blob(chunks, { type: mimeType || 'audio/webm' });
      chunks = [];
      if (!autoMax && dur < 500) {
        setErr('Message trop court. Maintenez un peu plus longtemps.');
        setStatus('', false);
        return;
      }
      if (blob.size < 200) {
        setErr('Enregistrement vide.');
        setStatus('', false);
        return;
      }
      if (blob.size > 24 * 1024 * 1024) {
        setErr('Fichier trop volumineux. Réessayez plus court.');
        setStatus('', false);
        return;
      }
      sendBlob(blob);
    };
  }

  function sendBlob(blob) {
    if (sending) return;
    sending = true;
    setStatus('Envoi en cours…', true);
    var ext = (blob.type || '').indexOf('mp4') !== -1 ? 'm4a' : 'webm';
    var stamp = new Date();
    var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
    var fname = 'message-vocal-' + stamp.getFullYear() + pad(stamp.getMonth() + 1) + pad(stamp.getDate()) + '-' + pad(stamp.getHours()) + pad(stamp.getMinutes()) + '.' + ext;
    var file = new File([blob], fname, { type: blob.type || 'audio/webm' });
    var form = document.createElement('form');
    form.method = 'POST';
    form.enctype = 'multipart/form-data';
    form.action = uploadUrl;
    form.style.display = 'none';
    var c = document.createElement('input');
    c.type = 'hidden';
    c.name = '_csrf_token';
    c.value = csrf;
    form.appendChild(c);
    var inp = document.createElement('input');
    inp.type = 'file';
    inp.name = 'portal_upload';
    var dt = new DataTransfer();
    try {
      dt.items.add(file);
      inp.files = dt.files;
    } catch (e) {
      setErr('Votre navigateur ne permet pas l’envoi direct. Utilisez le formulaire « choisir un fichier » ci-dessous.');
      setStatus('', false);
      sending = false;
      return;
    }
    form.appendChild(inp);
    document.body.appendChild(form);
    form.submit();
  }

  function onDown(ev) {
    ev.preventDefault();
    pointerDown = true;
    startRecord();
  }
  function onUp(ev) {
    pointerDown = false;
    if (!recorder) return;
    ev.preventDefault();
    finishRecord(false);
  }

  btn.addEventListener('mousedown', onDown);
  btn.addEventListener('touchstart', onDown, { passive: false });
  window.addEventListener('mouseup', onUp);
  window.addEventListener('touchend', onUp, { passive: false });
  btn.style.touchAction = 'none';
})();
</script>
<?php endif; ?>
</body>
</html>
