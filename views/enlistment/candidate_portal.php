<?php
declare(strict_types=1);
$enlistment = is_array($enlistment ?? null) ? $enlistment : [];
$messages = is_array($messages ?? null) ? $messages : [];
$tenant = is_array($tenant ?? null) ? $tenant : [];
$tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
$recruitmentModeLabel = trim((string) ($portalRecruitmentModeLabel ?? ''));
$status = (string) ($enlistment['status'] ?? 'submitted');
$dossierRejected = $status === 'rejected';
$dossierBlocked = $status === 'blocked';
$dossierMemberLinked = $status === 'reviewed' && (int) ($enlistment['submitter_user_id'] ?? 0) > 0;
$dossierMessagingClosed = $dossierRejected || $dossierBlocked || $dossierMemberLinked;
$dossierJourneyClosedNegative = $dossierRejected || $dossierBlocked;
$dossierStatusLabel = [
    'submitted' => 'En cours d’instruction',
    'reviewed' => 'Accepté',
    'rejected' => 'Refusé',
    'blocked' => 'Non admis',
][$status] ?? 'En cours de traitement';
$dossierStatusBand = match ($status) {
    'submitted' => 'bg-amber-500',
    'reviewed' => 'bg-emerald-500',
    'rejected' => 'bg-rose-500',
    'blocked' => 'bg-slate-700',
    default => 'bg-slate-500',
};
$portalSteps = is_array($portalSteps ?? null) ? $portalSteps : [];
$portalPauseKind = '';
$portalCurrentNote = '';
foreach ($portalSteps as $stEarly) {
    if (!is_array($stEarly) || (($stEarly['state'] ?? '') !== 'current')) {
        continue;
    }
    $portalPauseKind = trim((string) ($stEarly['pause_kind'] ?? ''));
    $portalCurrentNote = trim((string) ($stEarly['current_note'] ?? ''));
    break;
}
if ($status === 'submitted' && $portalPauseKind === 'pending') {
    $dossierStatusLabel = 'Mis en attente';
    $dossierStatusBand = 'bg-sky-500';
} elseif ($status === 'submitted' && $portalPauseKind === 'interview') {
    $dossierStatusLabel = 'Entretien proposé';
    $dossierStatusBand = 'bg-violet-500';
}
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
$isDiscordChannel = trim((string) ($enlistment['form_channel'] ?? '')) === 'discord';
$discordMessagingEnabled = (int) ($enlistment['discord_portal_messaging_enabled'] ?? 0) === 1;
$discordCommsLocked = $isDiscordChannel && !$discordMessagingEnabled && !$dossierMessagingClosed;
$canUploadSomething = !$dossierMessagingClosed && !$discordCommsLocked && $portalUploadsReady && ($allowPortalFiles || $allowPortalAudio);
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
$portalViewer = is_array($portalViewer ?? null) ? $portalViewer : ['mode' => 'anonymous', 'label' => '', 'initials' => '', 'user_id' => 0];
$pvMode = (string) ($portalViewer['mode'] ?? 'anonymous');
$pvLabel = trim((string) ($portalViewer['label'] ?? ''));
$pvUserId = (int) ($portalViewer['user_id'] ?? 0);
$pvStaffInitials = trim((string) ($portalViewer['initials'] ?? ''));
if ($pvStaffInitials === '') {
    $pvStaffInitials = 'RH';
}
/** Lecteur « côté candidat » (lien seul ou compte du déposant) : les bulles candidat sont « les vôtres ». Sinon, le fil est lu comme recruteur / tiers. */
$viewerIsCandidateParty = ($pvMode === 'candidate' || $pvMode === 'anonymous');
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
$portalReferentLabel = trim((string) ($portalReferentLabel ?? ''));
$portalRecruitmentSlaHours = max(1, (int) ($portalRecruitmentSlaHours ?? \App\Services\Recruitment\TenantRecruitmentSettings::defaultEnlistmentSlaHours()));
$portalSubmittedAgeHours = isset($portalSubmittedAgeHours) ? (int) $portalSubmittedAgeHours : null;
if ($portalSubmittedAgeHours === null && $createdAt !== '') {
    $portalSubmittedAgeHours = \App\Services\Recruitment\TenantRecruitmentSettings::hoursElapsedSince($createdAt);
}
$portalSlaBreached = !empty($portalSlaBreached);
if (!$portalSlaBreached && $status === 'submitted' && $portalSubmittedAgeHours !== null) {
    $portalSlaBreached = $portalSubmittedAgeHours > $portalRecruitmentSlaHours;
}
$portalStatusMode = strtolower(trim((string) ($enlistment['candidate_portal_status_mode'] ?? 'steps')));
if ($portalStatusMode !== 'manual') {
    $portalStatusMode = 'steps';
}
$manualPortalStatus = trim((string) ($enlistment['candidate_portal_status_manual_text'] ?? ''));
$manualPortalBandKey = strtolower(trim((string) ($enlistment['candidate_portal_status_manual_band'] ?? 'amber')));
$manualBandClasses = [
    'amber' => 'bg-amber-500',
    'emerald' => 'bg-emerald-500',
    'rose' => 'bg-rose-500',
    'slate' => 'bg-slate-500',
    'sky' => 'bg-sky-500',
];
if (!isset($manualBandClasses[$manualPortalBandKey])) {
    $manualPortalBandKey = 'amber';
}
$currentPortalStepLabel = '';
foreach ($portalSteps as $st) {
    if (is_array($st) && (($st['state'] ?? '') === 'current')) {
        $currentPortalStepLabel = trim((string) ($st['label'] ?? ''));
        break;
    }
}
$useManualPortalStatus = $portalStatusMode === 'manual' && $manualPortalStatus !== '';
if ($dossierRejected) {
    $portalCardTitle = 'Candidature refusée';
    $portalCardSubtitle = 'Le dossier est clos. Les échanges et l’envoi de pièces sont désactivés.';
    $portalCardBand = 'bg-rose-600';
} elseif ($dossierBlocked) {
    // Non admis : pas de bandeau « Décision » en tête — statut neutre + fil clos (détail dans les étapes compactes).
    $portalCardTitle = 'Dossier clos';
    $portalCardSubtitle = 'Cette candidature n’a pas été retenue. Les échanges et l’envoi de pièces sont désactivés.';
    $portalCardBand = 'bg-slate-600';
} elseif ($dossierMemberLinked) {
    $portalCardTitle = 'Candidature acceptée';
    $portalCardSubtitle = 'Rattachement au compte membre effectué. Le dossier est clos.';
    $portalCardBand = 'bg-emerald-600';
} elseif ($useManualPortalStatus) {
    $portalCardTitle = $manualPortalStatus;
    $portalCardSubtitle = '';
    $portalCardBand = $manualBandClasses[$manualPortalBandKey];
} elseif ($portalPauseKind === 'pending') {
    $portalCardTitle = 'Dossier mis en attente';
    $portalCardSubtitle = 'Le traitement est temporairement suspendu. Consultez le fil ci-dessous pour le détail.';
    $portalCardBand = 'bg-sky-500';
} elseif ($portalPauseKind === 'interview') {
    $portalCardTitle = 'Entretien proposé';
    $portalCardSubtitle = 'L’équipe souhaite échanger avec vous. Suivez les consignes sur le fil.';
    $portalCardBand = 'bg-violet-500';
} else {
    $portalCardTitle = $currentPortalStepLabel !== '' ? $currentPortalStepLabel : $dossierStatusLabel;
    $portalCardSubtitle = $currentPortalStepLabel !== '' ? ('Statut dossier : ' . $dossierStatusLabel) : '';
    $portalCardBand = $dossierStatusBand;
}
$slaHuman = \App\Services\Recruitment\TenantRecruitmentSettings::formatSlaHoursLabel($portalRecruitmentSlaHours);
$slaElapsedHuman = null;
if ($portalSubmittedAgeHours !== null) {
    $slaElapsedHuman = $portalSubmittedAgeHours < 1
        ? 'moins d’une heure'
        : \App\Services\Recruitment\TenantRecruitmentSettings::formatSlaHoursLabel($portalSubmittedAgeHours);
}
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
      details.portal-pj summary {
        list-style: none;
      }
      details.portal-pj summary::-webkit-details-marker {
        display: none;
      }
      details.portal-pj .portal-pj-chevron {
        transition: transform 0.2s ease;
      }
      details.portal-pj[open] .portal-pj-chevron {
        transform: rotate(180deg);
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
                <div class="h-1.5 <?= htmlspecialchars($portalCardBand, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></div>
                <div class="border-b border-slate-100 px-5 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Statut du dossier</p>
                    <p class="mt-1 text-lg font-bold text-slate-900"><?= htmlspecialchars($portalCardTitle, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($portalCardSubtitle !== ''): ?>
                        <p class="mt-1 text-xs leading-relaxed text-slate-600"><?= htmlspecialchars($portalCardSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
                <dl class="space-y-3 px-5 py-4 text-sm">
                    <?php if ($recruitmentModeLabel !== ''): ?>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Mode de recrutement</dt>
                        <dd class="max-w-[58%] text-right text-xs font-medium text-slate-800"><?= htmlspecialchars($recruitmentModeLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Référent du dossier</dt>
                        <dd class="max-w-[58%] text-right text-xs font-medium text-slate-800"><?= $portalReferentLabel !== '' ? htmlspecialchars($portalReferentLabel, ENT_QUOTES, 'UTF-8') : 'Non précisé pour l’instant par l’équipe.' ?></dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Délai de réponse visé</dt>
                        <dd class="max-w-[58%] text-right text-xs text-slate-700">
                            <span class="font-medium text-slate-800"><?= htmlspecialchars($slaHuman, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="mt-0.5 block text-[11px] leading-snug text-slate-500">Fixé par <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($slaElapsedHuman !== null): ?>
                                <span class="mt-1.5 block text-[11px] leading-snug text-slate-600">Écoulé depuis le dépôt : <?= htmlspecialchars($slaElapsedHuman, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <?php if ($status === 'submitted' && $portalSubmittedAgeHours !== null): ?>
                                <span class="mt-1.5 inline-flex items-center rounded-md border px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide <?= $portalSlaBreached ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-sky-200 bg-sky-50 text-sky-900' ?>">
                                    <?= $portalSlaBreached ? 'Délai dépassé' : 'Dans le délai' ?>
                                </span>
                            <?php endif; ?>
                        </dd>
                    </div>
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
                        $isCancelled = $stState === 'cancelled' || ($dossierJourneyClosedNegative && !$isDone);
                        $stepPause = trim((string) ($st['pause_kind'] ?? ''));
                        $dotClass = $isCancelled
                            ? 'border-slate-300 bg-slate-100 text-slate-400'
                            : ($isDone
                            ? 'border-emerald-600 bg-emerald-500 text-white'
                            : ($isCurrent
                                ? ($stepPause === 'pending'
                                    ? 'border-sky-500 bg-sky-500 text-white ring-4 ring-sky-200'
                                    : ($stepPause === 'interview'
                                        ? 'border-violet-500 bg-violet-500 text-white ring-4 ring-violet-200'
                                        : 'border-amber-500 bg-amber-500 text-white ring-4 ring-amber-200'))
                                : 'border-slate-200 bg-white text-slate-300'));
                        $lineClass = $si < count($portalSteps) - 1 ? ($isDone && !$isCancelled ? 'bg-emerald-200' : 'bg-slate-200') : '';
                        ?>
                        <?php
                        $stepTooltip = trim((string) ($st['tooltip'] ?? ''));
                        $stepCompact = $dossierJourneyClosedNegative || ($dossierMemberLinked && (string) ($st['id'] ?? '') === 'adhesion');
                        $hideDecisionHint = $dossierBlocked && (string) ($st['id'] ?? '') === 'decision';
                        ?>
                        <li class="relative flex gap-4 <?= $stepCompact ? 'pb-4' : 'pb-8' ?> last:pb-0<?= $stepTooltip !== '' ? ' cursor-help' : '' ?><?= $isCancelled || $dossierJourneyClosedNegative ? ' opacity-70' : '' ?>"<?= $isCurrent ? ' aria-current="step"' : '' ?><?= $stepTooltip !== '' ? ' title="' . htmlspecialchars($stepTooltip, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                            <?php if ($si < count($portalSteps) - 1): ?>
                                <span class="absolute left-[0.65rem] top-8 bottom-0 w-0.5 <?= htmlspecialchars($lineClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span class="relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 text-[10px] font-black <?= $dotClass ?>" aria-hidden="true"><?= $isDone && !$isCancelled ? '✓' : (string) ($si + 1) ?></span>
                            <div class="min-w-0 pt-0.5">
                                <p class="flex flex-wrap items-center gap-1.5 text-sm font-bold text-slate-900<?= $isCancelled || $dossierJourneyClosedNegative ? ' line-through decoration-slate-400' : '' ?>">
                                    <span><?= htmlspecialchars((string) ($st['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($isCurrent && $stepPause === 'pending'): ?>
                                        <span class="inline-flex items-center rounded-md border border-sky-200 bg-sky-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-900">Mis en attente</span>
                                    <?php elseif ($isCurrent && $stepPause === 'interview'): ?>
                                        <span class="inline-flex items-center rounded-md border border-violet-200 bg-violet-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-900">Entretien</span>
                                    <?php endif; ?>
                                    <?php if ($stepTooltip !== '' && !$dossierJourneyClosedNegative): ?>
                                        <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-[10px] font-black leading-none text-slate-500" title="<?= htmlspecialchars($stepTooltip, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">i</span>
                                    <?php endif; ?>
                                </p>
                                <?php if (!$hideDecisionHint && (!$stepCompact || trim((string) ($st['hint'] ?? '')) !== '')): ?>
                                <p class="mt-1 text-xs leading-relaxed text-slate-600<?= $isCancelled || $dossierJourneyClosedNegative ? ' line-through decoration-slate-300' : '' ?>"><?= htmlspecialchars((string) ($st['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if ($isCurrent && $portalCurrentNote !== ''): ?>
                                    <p class="mt-2 text-[11px] font-semibold <?= $portalPauseKind === 'pending' ? 'text-sky-800' : ($portalPauseKind === 'interview' ? 'text-violet-800' : 'text-amber-800') ?>"><?= htmlspecialchars($portalCurrentNote, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php elseif ($isCurrent && (string) ($st['id'] ?? '') === 'instruction'): ?>
                                    <p class="mt-2 text-[11px] font-semibold text-amber-800">Étape en cours — l’équipe étudie votre dossier. Les premières réponses apparaîtront dans le fil de messages.</p>
                                <?php elseif ($isCurrent && (string) ($st['id'] ?? '') === 'suivi'): ?>
                                    <p class="mt-2 text-[11px] font-semibold text-amber-800">Étape en cours — poursuivez l’échange sur le fil ci-dessous si vous avez des pièces ou des questions.</p>
                                <?php elseif ($isCurrent && (string) ($st['id'] ?? '') === 'adhesion'): ?>
                                    <p class="mt-2 text-[11px] font-semibold text-amber-800">Étape en cours — suivez les consignes reçues par e-mail ou sur le fil pour activer votre accès membre.</p>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <?php if ($portalUploadsReady && $attachments !== []): ?>
            <details class="portal-pj overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
                <summary class="flex cursor-pointer items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-5 py-4 transition hover:bg-slate-100/90">
                    <div class="min-w-0 text-left">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Pièces jointes</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-600"><?= (int) $attachmentCount ?> fichier<?= (int) $attachmentCount > 1 ? 's' : '' ?> transmis · ouvrez pour voir la liste et télécharger.</p>
                    </div>
                    <svg class="portal-pj-chevron h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                </summary>
                <ul class="max-h-[min(24rem,55vh)] divide-y divide-slate-100 overflow-y-auto overscroll-contain">
                    <?php foreach ($attachments as $att): ?>
                        <?php
                        $aid = (int) ($att['id'] ?? 0);
                        $fn = trim((string) ($att['original_name'] ?? '—'));
                        $k = (string) ($att['kind'] ?? 'file');
                        $sz = (int) ($att['size_bytes'] ?? 0);
                        $when = trim((string) ($att['created_at'] ?? ''));
                        $whenFmt = $when !== '' ? date('d/m/Y à H:i', strtotime($when) ?: time()) : '—';
                        ?>
                        <li class="flex gap-3 px-5 py-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-slate-600" aria-hidden="true" title="<?= $k === 'audio' ? 'Audio' : 'Document' ?>">
                                <?php if ($k === 'audio'): ?>
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                                <?php else: ?>
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <?php endif; ?>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="break-words font-semibold leading-snug text-slate-900"><?= htmlspecialchars($fn, ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-0.5 text-xs text-slate-500"><?= $k === 'audio' ? 'Enregistrement audio' : 'Document' ?> · <?= htmlspecialchars($fmtBytes($sz), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($whenFmt, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if ($k === 'audio'): ?>
                                    <div class="snap-shell mt-3 max-w-full rounded-2xl p-3 sm:max-w-md">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-white/90">Écoute rapide</p>
                                        <audio controls preload="metadata" src="<?= htmlspecialchars(url('enlistment/suivi/' . rawurlencode((string) $token) . '/piece/' . $aid . '?inline=1'), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 w-full rounded-lg"></audio>
                                    </div>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars(url('enlistment/suivi/' . rawurlencode((string) $token) . '/piece/' . $aid . '/preparation'), ENT_QUOTES, 'UTF-8') ?>" class="mt-3 inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-800 transition hover:bg-slate-50"><?= $k === 'audio' ? 'Télécharger l’audio' : 'Télécharger' ?></a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
        </aside>

        <div class="space-y-6 lg:col-span-8">
            <?php if (!empty($flashOk)): ?>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-950 shadow-sm" role="status"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if (!empty($flashErr)): ?>
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-950 shadow-sm" role="alert"><?= htmlspecialchars((string) $flashErr, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php
            $portalIsDiscordChannel = trim((string) ($enlistment['form_channel'] ?? 'milsim')) === 'discord';
            if ($portalIsDiscordChannel):
                $portalDiscordAnswersRaw = $enlistment['discord_answers_json'] ?? null;
                $portalDiscordAnswers = [];
                if (is_string($portalDiscordAnswersRaw) && $portalDiscordAnswersRaw !== '') {
                    $decodedPortalAnswers = json_decode($portalDiscordAnswersRaw, true);
                    $portalDiscordAnswers = is_array($decodedPortalAnswers) ? $decodedPortalAnswers : [];
                } elseif (is_array($portalDiscordAnswersRaw)) {
                    $portalDiscordAnswers = $portalDiscordAnswersRaw;
                }
                $portalDiscordInterviewAt = trim((string) ($enlistment['discord_interview_at'] ?? ''));
            ?>
            <section class="overflow-hidden rounded-2xl border border-indigo-200 bg-white shadow-sm ring-1 ring-slate-900/[0.04]" aria-labelledby="fiche-discord">
                <div class="border-b border-indigo-100 bg-indigo-50 px-5 py-4 sm:px-6">
                    <h2 id="fiche-discord" class="text-[11px] font-bold uppercase tracking-[0.28em] text-indigo-900/90">Votre fiche de candidature Discord</h2>
                </div>
                <div class="space-y-4 p-5 sm:p-6">
                    <?php if ($portalDiscordInterviewAt !== ''): ?>
                    <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-indigo-800">Rendez-vous Discord</p>
                        <p class="mt-1 text-sm font-semibold text-indigo-950"><?= htmlspecialchars(date('d/m/Y à H:i', strtotime($portalDiscordInterviewAt) ?: time()), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <?php else: ?>
                    <p class="text-sm text-slate-600">Aucun rendez-vous Discord planifié pour le moment — l’équipe recrutement vous contactera.</p>
                    <?php endif; ?>

                    <?php if ($portalDiscordAnswers !== []): ?>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500 mb-2">Vos réponses</p>
                        <dl class="space-y-3">
                            <?php foreach ($portalDiscordAnswers as $portalAnswerRow): ?>
                                <?php if (!is_array($portalAnswerRow)) { continue; } ?>
                                <div>
                                    <dt class="text-sm font-semibold text-slate-800"><?= htmlspecialchars((string) ($portalAnswerRow['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dt>
                                    <dd class="mt-0.5 text-sm leading-relaxed text-slate-700 whitespace-pre-wrap"><?= htmlspecialchars((string) ($portalAnswerRow['answer'] ?? '—') ?: '—', ENT_QUOTES, 'UTF-8') ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
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
                        <?php if (trim((string) ($candidateRetroFeedback['comment'] ?? '')) !== ''): ?>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-800 whitespace-pre-wrap"><?= htmlspecialchars((string) ($candidateRetroFeedback['comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    <?php elseif ($portalRetroEligible && !$portalRetroTableReady): ?>
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

            <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]" aria-labelledby="fil-messages"<?php if ($pvMode === 'staff'): ?> x-data="{ hintDismissed: localStorage.getItem('athena_recruit_thread_hint_dismissed') === '1' }"<?php endif; ?>>
                <div class="border-b border-slate-100 bg-gradient-to-r from-slate-900 to-slate-800 px-5 py-4 sm:px-6">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 id="fil-messages" class="text-[11px] font-bold uppercase tracking-[0.28em] text-emerald-400/95">Fil de messages</h2>
                            <p class="mt-1 text-sm text-slate-300"<?php if ($pvMode === 'staff'): ?> x-show="!hintDismissed" x-cloak<?php endif; ?>><?php if ($dossierMessagingClosed): ?>
                                Historique en lecture seule — les envois sont désactivés pour ce dossier.
                            <?php elseif ($discordCommsLocked): ?>
                                Recrutement via Discord — ce fil est désactivé par défaut, activable ci-dessous.
                            <?php elseif ($viewerIsCandidateParty): ?>
                                Seuls vous et l’équipe recrutement de la communauté voyez ces échanges sur ce lien.
                            <?php elseif ($pvMode === 'staff'): ?>
                                Vue recruteur : à gauche, le <span class="font-semibold text-white">candidat</span> ; à droite, les messages <span class="font-semibold text-white">recrutement</span>. Rien n’est étiqueté « Vous » pour le candidat.
                            <?php else: ?>
                                Fil visible avec ce lien : le candidat à gauche, l’équipe recrutement à droite.
                            <?php endif; ?></p>
                        </div>
                        <?php if ($pvMode === 'staff'): ?>
                        <button type="button" class="shrink-0 rounded-lg border border-slate-600 bg-slate-800/80 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-200 hover:bg-slate-700" x-show="!hintDismissed" x-cloak @click="hintDismissed = true; localStorage.setItem('athena_recruit_thread_hint_dismissed', '1')">Compris</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($pvMode === 'staff' && $pvLabel !== ''): ?>
                    <div class="border-b border-amber-100 bg-amber-50/95 px-5 py-3 sm:px-6" x-show="!hintDismissed" x-cloak>
                        <p class="text-xs font-bold text-amber-950">Consultation recrutement</p>
                        <p class="mt-1 text-sm text-amber-950/95">Vous êtes connecté en tant que <span class="font-black"><?= htmlspecialchars($pvLabel, ENT_QUOTES, 'UTF-8') ?></span>. Les messages que vous envoyez depuis cette page sont enregistrés côté <span class="font-semibold">recrutement</span> (pas au nom du candidat) et restent visibles sur ce fil.</p>
                    </div>
                <?php endif; ?>
                <div class="max-h-[min(28rem,70vh)] space-y-4 overflow-x-hidden overflow-y-auto px-4 py-5 sm:px-6">
                    <?php if ($messages === []): ?>
                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                            <?php if ($dossierMessagingClosed): ?>
                                Aucun message sur ce fil. Le dossier est clos<?= $dossierMemberLinked ? ' — rattachement effectué' : ($dossierRejected ? ' — candidature refusée' : ($dossierBlocked ? ' — candidature non admise' : '')) ?>.
                            <?php else: ?>
                                Aucun message pour l’instant. Lorsque l’équipe mettra à jour votre dossier, la réponse apparaîtra ici et un e-mail vous sera envoyé si votre adresse est valide.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $i => $m): ?>
                            <?php
                            $entryKind = (string) ($m['entry_kind'] ?? '');
                            $msgIsStaff = ($entryKind === 'staff');
                            $msgIsCandidate = !$msgIsStaff;
                            if ($viewerIsCandidateParty) {
                                $bubbleIsViewerSide = $msgIsCandidate;
                            } else {
                                $bubbleIsViewerSide = $msgIsStaff;
                            }
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
                            if (!$msgIsCandidate && preg_match('/^Statut\s*:\s*([^\r\n]+)/u', $bodyRaw, $mm)) {
                                $statLine = trim((string) ($mm[1]));
                                $bodyDisplay = trim(preg_replace('/^Statut\s*:\s*[^\r\n]+\s*/u', '', $bodyRaw) ?? $bodyRaw);
                            }
                            $linkedAtt = ($linkedPieceId !== null && $linkedPieceId > 0) ? ($portalAttachmentsById[$linkedPieceId] ?? null) : null;
                            $linkedIsAudio = is_array($linkedAtt) && ((string) ($linkedAtt['kind'] ?? '')) === 'audio';
                            $audioSrc = ($linkedIsAudio && $linkedPieceId > 0)
                                ? htmlspecialchars(url('enlistment/suivi/' . rawurlencode((string) $token) . '/piece/' . $linkedPieceId . '?inline=1'), ENT_QUOTES, 'UTF-8')
                                : '';
                            if ($linkedIsAudio) {
                                $bodyDisplay = trim((string) preg_replace('/^\s*Enregistrement audio transmis\s*:\s*.+$/mu', '', $bodyDisplay));
                            }
                            $hasAudioPlayer = $linkedIsAudio && $audioSrc !== '';
                            $actorName = '';
                            $actorInitials = 'RH';
                            if ($msgIsStaff) {
                                $actorName = trim((string) ($m['actor_display_name'] ?? ''));
                                if ($actorName === '') {
                                    $actorName = trim((string) ($m['actor_callsign'] ?? ''));
                                }
                                if ($actorName === '') {
                                    $actorName = trim((string) ($m['actor_email'] ?? ''));
                                }
                                if ($actorName !== '') {
                                    $ap = preg_split('/\s+/u', $actorName, -1, PREG_SPLIT_NO_EMPTY);
                                    if (is_array($ap) && isset($ap[0])) {
                                        $actorInitials = mb_strtoupper(mb_substr((string) $ap[0], 0, 1));
                                        if (isset($ap[1])) {
                                            $actorInitials .= mb_strtoupper(mb_substr((string) $ap[1], 0, 1));
                                        } else {
                                            $actorInitials = mb_strtoupper(mb_substr($actorName, 0, 2));
                                        }
                                    }
                                }
                            }
                            $actorUserId = (int) ($m['actor_user_id'] ?? 0);
                            $avatarLetters = $msgIsCandidate
                                ? mb_substr($initials, 0, 2)
                                : (($bubbleIsViewerSide && $pvUserId > 0 && $actorUserId === $pvUserId) ? $pvStaffInitials : $actorInitials);
                            ?>
                            <div class="flex <?= $bubbleIsViewerSide ? 'justify-end' : 'justify-start' ?>">
                                <div class="flex min-w-0 max-w-[min(100%,34rem)] gap-3 <?= $bubbleIsViewerSide ? 'flex-row-reverse' : 'flex-row' ?>">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-black <?= $bubbleIsViewerSide ? 'bg-sky-600 text-white' : 'bg-emerald-600 text-white' ?>" aria-hidden="true">
                                        <?= htmlspecialchars(mb_substr($avatarLetters, 0, 2), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2 <?= $bubbleIsViewerSide ? 'justify-end' : 'justify-start' ?>">
                                            <span class="text-[10px] font-black uppercase tracking-wider <?= $bubbleIsViewerSide ? 'text-sky-800' : 'text-emerald-900' ?>"><?php
                                            if ($bubbleIsViewerSide) {
                                                if ($viewerIsCandidateParty) {
                                                    echo 'Vous';
                                                } elseif ($pvUserId > 0 && $actorUserId === $pvUserId) {
                                                    echo 'Vous';
                                                    if ($pvLabel !== '') {
                                                        echo '<span class="ml-1 font-semibold normal-case text-slate-600">· ' . htmlspecialchars($pvLabel, ENT_QUOTES, 'UTF-8') . '</span>';
                                                    }
                                                } elseif ($actorName !== '') {
                                                    echo 'Recrutement<span class="ml-1 font-semibold normal-case text-slate-600">· ' . htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') . '</span>';
                                                } else {
                                                    echo 'Recrutement';
                                                }
                                            } elseif ($msgIsCandidate) {
                                                echo 'Candidat';
                                                if ($displayName !== '') {
                                                    echo '<span class="ml-1 font-semibold normal-case text-slate-600">· ' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</span>';
                                                }
                                            } elseif ($actorName !== '') {
                                                echo 'Recrutement<span class="ml-1 font-semibold normal-case text-slate-600">· ' . htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') . '</span>';
                                            } else {
                                                echo 'Recrutement';
                                            }
                                            ?></span>
                                            <span class="text-[10px] font-semibold tabular-nums text-slate-400"><?= htmlspecialchars($dt, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-slate-500">#<?= (int) $i + 1 ?></span>
                                        </div>
                                        <div class="mt-1.5 max-w-full min-w-0 rounded-2xl border px-4 py-3 text-sm leading-relaxed shadow-sm <?= $bubbleIsViewerSide ? 'rounded-tr-sm border-sky-200 bg-sky-50 text-slate-900' : 'rounded-tl-sm border-emerald-200 bg-emerald-50/90 text-slate-900' ?>">
                                            <?php if ($statLine !== null && $statLine !== ''): ?>
                                                <p class="mb-2 inline-flex flex-wrap items-center gap-2 text-xs font-bold text-emerald-950">
                                                    <span class="rounded-full bg-emerald-600/15 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-emerald-900">Mise à jour</span>
                                                    <span class="min-w-0 max-w-full break-words [overflow-wrap:anywhere]"><?= htmlspecialchars($statLine, ENT_QUOTES, 'UTF-8') ?></span>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($bodyDisplay !== ''): ?>
                                                <div class="max-w-full whitespace-pre-wrap break-words text-[15px] text-slate-800 [overflow-wrap:anywhere]"><?= nl2br(htmlspecialchars($bodyDisplay, ENT_QUOTES, 'UTF-8')) ?></div>
                                            <?php elseif (!$hasAudioPlayer): ?>
                                                <div class="max-w-full whitespace-pre-wrap break-words text-[15px] text-slate-800 [overflow-wrap:anywhere]">—</div>
                                            <?php endif; ?>
                                            <?php if ($hasAudioPlayer): ?>
                                                <div class="mt-3 max-w-full rounded-2xl border <?= $bubbleIsViewerSide ? 'border-sky-300/80 bg-sky-100/90' : 'border-emerald-300/80 bg-emerald-100/80' ?> p-3">
                                                    <p class="mb-2 text-[10px] font-black uppercase tracking-wider <?= $bubbleIsViewerSide ? 'text-sky-900/90' : 'text-emerald-950/90' ?>">Lecture</p>
                                                    <audio controls preload="metadata" src="<?= $audioSrc ?>" class="w-full max-w-full rounded-lg"></audio>
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

                    <div class="border-t border-slate-200 pt-4">
                        <?php if ($dossierMessagingClosed): ?>
                            <?php
                            $closedBoxBorder = $dossierRejected
                                ? 'border-rose-200 bg-rose-50'
                                : ($dossierBlocked ? 'border-slate-300 bg-slate-50' : 'border-emerald-200 bg-emerald-50');
                            $closedBoxTitle = $dossierRejected
                                ? 'text-rose-900'
                                : ($dossierBlocked ? 'text-slate-800' : 'text-emerald-900');
                            $closedBoxBody = $dossierRejected
                                ? 'text-rose-950/90'
                                : ($dossierBlocked ? 'text-slate-800/90' : 'text-emerald-950/90');
                            ?>
                            <div class="rounded-xl border <?= $closedBoxBorder ?> px-4 py-4" role="status">
                                <p class="text-xs font-black uppercase tracking-wider <?= $closedBoxTitle ?>">Dossier clos</p>
                                <p class="mt-1.5 text-sm leading-relaxed <?= $closedBoxBody ?>">
                                    <?php if ($dossierRejected): ?>
                                        Candidature refusée — les messages sont désactivés. Vous pouvez encore consulter l’historique ci-dessus.
                                    <?php elseif ($dossierBlocked): ?>
                                        Candidature non admise — les messages sont désactivés. Vous pouvez encore consulter l’historique ci-dessus.
                                    <?php else: ?>
                                        Rattachement effectué — les messages sont désactivés. Vous pouvez encore consulter l’historique ci-dessus.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php elseif ($discordCommsLocked): ?>
                            <div class="rounded-xl border border-indigo-200 bg-indigo-50/90 px-5 py-5 text-center" role="status">
                                <p class="text-xs font-black uppercase tracking-wider text-indigo-950">Recrutement via Discord</p>
                                <p class="mt-1.5 text-sm leading-relaxed text-indigo-900/90">Les échanges pour ce dossier se font par défaut sur Discord. Vous pouvez activer ce fil pour discuter aussi ici, sur Athena.</p>
                                <form method="post" action="<?= htmlspecialchars(url('enlistment/suivi/' . rawurlencode((string) $token) . '/activer-discord'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl bg-indigo-700 px-6 text-xs font-black uppercase tracking-wide text-white shadow-md transition hover:bg-indigo-800">Activer la communication par Athena</button>
                                </form>
                            </div>
                        <?php else: ?>
                        <h3 id="nouveau-message" class="text-xs font-black uppercase tracking-wider text-slate-700"><?= $pvMode === 'staff' ? 'Message recrutement (visible du candidat)' : 'Écrire à l’équipe' ?></h3>
                        <p class="mt-1 text-sm text-slate-500"><?php if ($pvMode === 'staff' && $pvLabel !== ''): ?>
                            Vous répondez en tant que <span class="font-semibold text-slate-800"><?= htmlspecialchars($pvLabel, ENT_QUOTES, 'UTF-8') ?></span> : le message apparaît sur ce fil comme un message de l’équipe (pas une alerte « candidat » aux autres recruteurs).
                        <?php else: ?>
                            Précisions sur votre disponibilité, questions sur l’entretien ou documents demandés. Réponse par e-mail et dans ce fil.
                        <?php endif; ?></p>
                        <form method="post" action="<?= htmlspecialchars(url('enlistment/suivi/' . rawurlencode((string) $token) . '/message'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4">
                            <?= \App\Core\Csrf::field() ?>
                            <div>
                                <label for="candidate_message" class="sr-only">Votre message</label>
                                <textarea id="candidate_message" name="candidate_message" rows="5" maxlength="4000" required class="min-h-[8rem] w-full min-w-0 max-w-full resize-y rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 shadow-inner outline-none transition [overflow-wrap:anywhere] placeholder:text-slate-400 focus:border-slate-900 focus:bg-white focus:ring-2 focus:ring-slate-900/10" placeholder="Ex. : je confirme le créneau proposé, ma disponibilité change à partir de la semaine prochaine…"></textarea>
                                <p class="mt-1.5 text-[11px] text-slate-500">4&nbsp;000 caractères maximum. Soyez factuel : cela aide l’équipe à traiter votre dossier plus vite.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="submit" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl bg-slate-900 px-6 text-xs font-black uppercase tracking-wide text-white shadow-md transition hover:bg-slate-800">Transmettre le message</button>
                                <p class="text-[11px] text-slate-500">Un e-mail d’alerte est envoyé aux personnes habilitées au recrutement.</p>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
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
            <?php elseif ($portalUploadsReady && !$dossierMessagingClosed): ?>
            <section class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600">
                L’équipe n’a pas activé l’envoi de pièces jointes pour votre dossier. Si vous devez transmettre un document ou un audio, indiquez-le dans le bloc <span class="font-semibold text-slate-800">Écrire à l’équipe</span> en bas du fil de messages : l’équipe pourra vous répondre ou activer l’envoi ici.
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
