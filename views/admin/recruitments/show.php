<?php
declare(strict_types=1);
/** @var array<string,mixed> $enlistment */
/** @var list<array<string,mixed>> $enlistmentCannedMessages */
$e = $enlistment ?? [];
$enlistmentCannedMessages = $enlistmentCannedMessages ?? [];
$rpSnap = is_array($e['recruitment_rp_json'] ?? null) ? $e['recruitment_rp_json'] : null;
$id = (int) ($e['id'] ?? 0);
$statusRaw = (string) ($e['status'] ?? '');
$statusLabels = [
    'submitted' => 'À traiter',
    'reviewed' => 'Acceptée',
    'rejected' => 'Refusée',
    'blocked' => 'Non admis',
];
$statusLabel = $statusLabels[$statusRaw] ?? $statusRaw;
$reviewedById = (int) ($e['reviewed_by'] ?? 0);
$assigneeLabel = $reviewedById > 0 ? ('Compte interne #' . $reviewedById) : 'Non attribué';
$membershipRepairHint = $membershipRepairHint ?? null;
$enlistmentSlaHours = max(1, (int) ($enlistmentSlaHours ?? 72));
$submittedAgeHours = isset($e['submitted_age_hours']) ? (int) $e['submitted_age_hours'] : null;
$submittedSlaBreached = !empty($e['submitted_sla_breached']);

$enlistmentTimeline = is_array($enlistmentTimeline ?? null) ? $enlistmentTimeline : [];
$timelineActorLabels = is_array($enlistmentTimelineActorLabels ?? null) ? $enlistmentTimelineActorLabels : [];
$timelineTableMissing = !empty($enlistmentTimelineTableMissing);
$timelineStepLabels = [
    'reception' => 'Réception du dossier',
    'instruction' => 'Instruction et arbitrage',
    'decision' => 'Décision',
    'adhesion' => 'Rattachement au compte membre',
    'general' => 'Commentaire général',
];

$linkedRo = is_array($linkedRecruitmentOpening ?? null) ? $linkedRecruitmentOpening : null;
$submitterId = (int) ($e['submitter_user_id'] ?? 0);
$isInternalOpeningApplication = $submitterId > 0 && $linkedRo !== null;
$candidatePortalAttachments = is_array($candidatePortalAttachments ?? null) ? $candidatePortalAttachments : [];
$candidatePortalUploadsReady = !empty($candidatePortalUploadsReady);
$portalAllowFiles = !empty($e['candidate_portal_allow_files']);
$portalAllowAudio = !empty($e['candidate_portal_allow_audio']);

$sharedFields = [];
$sfRaw = $e['shared_fields'] ?? null;
if (is_string($sfRaw) && $sfRaw !== '') {
    $decodedSf = json_decode($sfRaw, true);
    $sharedFields = is_array($decodedSf) ? $decodedSf : [];
} elseif (is_array($sfRaw)) {
    $sharedFields = $sfRaw;
}

$transmissionLines = [];
if ($sharedFields !== []) {
    if (!empty($sharedFields['share_name'])) {
        $transmissionLines[] = 'Nom issu du profil portail';
    }
    if (!empty($sharedFields['share_email'])) {
        $transmissionLines[] = 'Adresse e-mail de connexion';
    }
    if (!empty($sharedFields['share_callsign'])) {
        $transmissionLines[] = 'Indicatif enregistré sur le profil';
    }
    $rpS = $sharedFields['rp_shares'] ?? null;
    if (is_array($rpS)) {
        $rpShareLabels = [
            'character_name' => 'Nom du personnage',
            'bio' => 'Biographie',
            'cv' => 'Parcours (CV)',
            'image_url' => 'Portrait (fichier)',
            'image_external_url' => 'Lien vers un portrait',
            'admin_notes' => 'Notes du profil',
            'availability' => 'Synthèse des disponibilités',
        ];
        foreach ($rpShareLabels as $rk => $rlab) {
            if (!empty($rpS[$rk])) {
                $transmissionLines[] = $rlab;
            }
        }
    }
    if (!empty($sharedFields['include_milsim_from_preset'])) {
        $transmissionLines[] = 'Réponses techniques du modèle (matériel, créneaux, motivation enregistrée dans le profil, etc.)';
    }
    $fm = $sharedFields['form_mode'] ?? null;
    if ($fm === 'compact') {
        $transmissionLines[] = 'Parcours de dépôt : formulaire court (avis ciblé)';
    } elseif ($fm === 'full') {
        $transmissionLines[] = 'Parcours de dépôt : questionnaire complet';
    }
}

$statusBand = match ($statusRaw) {
    'submitted' => 'from-amber-500 to-amber-600',
    'reviewed' => 'from-emerald-600 to-emerald-700',
    'rejected' => 'from-rose-500 to-rose-600',
    'blocked' => 'from-slate-600 to-slate-800',
    default => 'from-stone-400 to-stone-600',
};

$recapMeta = match ($statusRaw) {
    'submitted' => [
        'step' => 'Étape 2 sur 3',
        'title' => 'En cours de traitement',
        'bar' => 'w-2/3',
        'barColor' => 'bg-amber-400',
        'hint' => 'Étape suivante : décision finale et notification candidat.',
    ],
    'reviewed' => [
        'step' => 'Étape 3 sur 3',
        'title' => 'Candidature acceptée',
        'bar' => 'w-full',
        'barColor' => 'bg-emerald-500',
        'hint' => 'Décision positive enregistrée. Vérifiez le rattachement au compte membre si besoin.',
    ],
    'rejected' => [
        'step' => 'Dossier clos',
        'title' => 'Candidature refusée',
        'bar' => 'w-full',
        'barColor' => 'bg-rose-500',
        'hint' => 'Décision négative enregistrée. Le journal conserve la trace des échanges.',
    ],
    'blocked' => [
        'step' => 'Dossier clos',
        'title' => 'Non admis',
        'bar' => 'w-full',
        'barColor' => 'bg-stone-500',
        'hint' => 'Candidature écartée sans suite d’adhésion.',
    ],
    default => [
        'step' => 'Étape 1 sur 3',
        'title' => 'Réception du dossier',
        'bar' => 'w-1/3',
        'barColor' => 'bg-stone-400',
        'hint' => 'Statut à préciser côté instruction.',
    ],
};
?>
<div class="recruitment-bureau space-y-6 max-w-[94rem] mx-auto w-full">

        <nav class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white px-4 py-3 shadow-sm sm:px-5" aria-label="Fil d’Ariane">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-stone-600">
                <a href="<?= htmlspecialchars(url('back-office/recruitments')) ?>" class="rounded-lg border border-transparent px-2 py-1.5 text-stone-700 transition hover:border-stone-200 hover:bg-stone-50">Dossiers de candidature</a>
                <span class="text-stone-300" aria-hidden="true">/</span>
                <span class="rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-1.5 font-bold text-stone-900">Dossier n°<?= $id ?></span>
                <a href="<?= htmlspecialchars(url('back-office/recruitments/settings')) ?>" class="ml-auto inline-flex min-h-[2.25rem] items-center rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.18em] text-stone-800 shadow-sm transition hover:border-stone-400 hover:bg-stone-50">Délais d’alerte</a>
            </div>
        </nav>

        <div class="grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <aside class="xl:sticky xl:top-24 xl:self-start">
                <div class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                    <div class="border-b border-stone-200 bg-stone-50 px-4 py-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Dans ce dossier</p>
                    </div>
                    <nav class="space-y-2 p-3" aria-label="Sections du dossier">
                        <a href="#recap-dossier" class="flex items-center justify-between rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-stone-800 shadow-sm transition hover:border-emerald-300/60 hover:bg-emerald-50/40">
                            <span>Récapitulatif</span>
                            <span class="text-[10px] font-black text-stone-400">01</span>
                        </a>
                        <?php if ($statusRaw === 'submitted'): ?>
                        <a href="#instruction-dossier" class="flex items-center justify-between rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-stone-800 shadow-sm transition hover:border-emerald-300/60 hover:bg-emerald-50/40">
                            <span>Décision</span>
                            <span class="text-[10px] font-black text-stone-400">02</span>
                        </a>
                        <?php endif; ?>
                        <a href="#journal-dossier" class="flex items-center justify-between rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-stone-800 shadow-sm transition hover:border-emerald-300/60 hover:bg-emerald-50/40">
                            <span>Journal</span>
                            <span class="text-[10px] font-black text-stone-400">03</span>
                        </a>
                    </nav>
                    <div class="border-t border-stone-200 bg-stone-50 px-4 py-3 text-[11px] text-stone-600 space-y-1.5">
                        <p><span class="font-bold text-stone-800">Statut</span> — <?= htmlspecialchars($statusLabel ?: '—') ?></p>
                        <p><span class="font-bold text-stone-800">Attribué</span> — <?= htmlspecialchars($assigneeLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
            </aside>

            <div class="space-y-6 min-w-0">
                <section id="recap-dossier" class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                    <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                        <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500"><?= htmlspecialchars($recapMeta['step'], ENT_QUOTES, 'UTF-8') ?></p>
                        <h2 class="mt-1 text-xl font-black tracking-tight text-stone-900"><?= htmlspecialchars($recapMeta['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-stone-200">
                            <div class="h-full <?= htmlspecialchars($recapMeta['bar'], ENT_QUOTES, 'UTF-8') ?> rounded-full <?= htmlspecialchars($recapMeta['barColor'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        </div>
                        <p class="mt-3 text-xs leading-relaxed text-stone-600"><?= htmlspecialchars($recapMeta['hint'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="grid gap-4 px-6 py-4 text-sm text-stone-800 md:grid-cols-2 xl:grid-cols-4">
                        <div><p class="text-[10px] font-bold uppercase tracking-wide text-stone-500">N° de dossier</p><p class="mt-1 font-semibold text-stone-900">#<?= $id ?></p></div>
                        <div><p class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Statut</p><p class="mt-1 font-semibold text-stone-900"><?= htmlspecialchars($statusLabel ?: '—', ENT_QUOTES, 'UTF-8') ?></p></div>
                        <div><p class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Attribué à</p><p class="mt-1 font-semibold text-stone-900"><?= htmlspecialchars($assigneeLabel, ENT_QUOTES, 'UTF-8') ?></p></div>
                        <div><p class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Nature</p><p class="mt-1 font-semibold text-stone-900"><?= $isInternalOpeningApplication ? 'Mobilité interne' : 'Candidature externe' ?></p></div>
                    </div>
                </section>

        <!-- Couverture dossier -->
        <header class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
            <div class="h-1.5 bg-gradient-to-r <?= htmlspecialchars($statusBand) ?>" aria-hidden="true"></div>
            <div class="flex flex-col gap-5 border-b border-stone-100 bg-white px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-7">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Dossier individuel</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-stone-900 sm:text-3xl">Candidature n°<?= $id ?></h1>
                    <p class="mt-2 text-sm text-stone-600">
                        <?= htmlspecialchars(trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?: 'Candidat') ?>
                        <?php if (!empty($e['created_at'])): ?>
                            <span class="text-stone-400"> · </span>
                            <span class="tabular-nums">Réception le <?= htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $e['created_at']))) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex shrink-0 flex-col items-start sm:items-end gap-2">
                    <span class="inline-flex items-center rounded-xl border border-stone-200 bg-stone-50 px-3 py-1.5 text-xs font-bold text-stone-900">
                        <?= htmlspecialchars($statusLabel ?: '—') ?>
                    </span>
                    <?php if ($statusRaw === 'submitted' && $submittedAgeHours !== null): ?>
                        <span class="inline-flex items-center rounded-lg border px-2 py-1 text-[10px] font-bold uppercase tracking-wide <?= $submittedSlaBreached ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-sky-200 bg-sky-50 text-sky-900' ?>">
                            <?= $submittedSlaBreached ? 'Délai dépassé' : 'Dans le délai' ?> · <?= $submittedAgeHours ?> h / <?= $enlistmentSlaHours ?> h
                        </span>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars(url('back-office/recruitments')) ?>" class="text-xs font-bold uppercase tracking-wider text-emerald-700 transition hover:text-emerald-900">← Retour à la liste</a>
                </div>
            </div>
        </header>

        <div class="mt-8 space-y-6">

            <section class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Suivi en ligne</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Portail candidat</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-stone-600">Autorisez ou non l’envoi de pièces depuis le lien de suivi sécurisé envoyé au candidat. Ces réglages valent uniquement pour ce dossier.</p>
                </div>
                <div class="px-6 py-6 sm:px-8">
                    <?php if (!$candidatePortalUploadsReady): ?>
                        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Cette fonctionnalité nécessite une mise à jour de la base de données (migration). Exécutez le script de migration du projet puis rechargez cette page.</p>
                    <?php else: ?>
                        <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/portal-options'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-5">
                            <?= \App\Core\Csrf::field() ?>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200 bg-[#faf8f3] p-4 transition hover:border-stone-300">
                                    <input type="hidden" name="candidate_portal_allow_files" value="0">
                                    <input type="checkbox" name="candidate_portal_allow_files" value="1" class="mt-1 h-4 w-4 rounded border-stone-400 text-[#1c2d41]" <?= $portalAllowFiles ? 'checked' : '' ?>>
                                    <span>
                                        <span class="block text-sm font-bold text-stone-900">Autoriser l’envoi de documents</span>
                                        <span class="mt-1 block text-xs leading-relaxed text-stone-600">PDF, images ou texte simple (taille limitée). Utile pour CV, captures d’écran, etc.</span>
                                    </span>
                                </label>
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200 bg-[#faf8f3] p-4 transition hover:border-stone-300">
                                    <input type="hidden" name="candidate_portal_allow_audio" value="0">
                                    <input type="checkbox" name="candidate_portal_allow_audio" value="1" class="mt-1 h-4 w-4 rounded border-stone-400 text-[#1c2d41]" <?= $portalAllowAudio ? 'checked' : '' ?>>
                                    <span>
                                        <span class="block text-sm font-bold text-stone-900">Autoriser l’envoi d’enregistrements audio</span>
                                        <span class="mt-1 block text-xs leading-relaxed text-stone-600">Fichiers audio courants (enregistrement sur téléphone ou ordinateur, puis envoi).</span>
                                    </span>
                                </label>
                            </div>
                            <button type="submit" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl border border-stone-900 bg-stone-900 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-stone-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 focus-visible:ring-offset-2">Enregistrer les options</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($candidatePortalUploadsReady && $candidatePortalAttachments !== []): ?>
                        <div class="mt-8 border-t border-stone-200 pt-6">
                            <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-stone-500">Pièces reçues via le portail</h3>
                            <ul class="mt-3 divide-y divide-stone-100 rounded-xl border border-stone-200 bg-white">
                                <?php foreach ($candidatePortalAttachments as $att): ?>
                                    <?php
                                    $aid = (int) ($att['id'] ?? 0);
                                    $fn = trim((string) ($att['original_name'] ?? '—'));
                                    $k = (string) ($att['kind'] ?? 'file');
                                    $sz = (int) ($att['size_bytes'] ?? 0);
                                    $hum = $sz >= 1048576 ? round($sz / 1048576, 1) . ' Mo' : ($sz >= 1024 ? round($sz / 1024, 1) . ' ko' : (string) max(0, $sz) . ' o');
                                    $when = trim((string) ($att['created_at'] ?? ''));
                                    $whenFmt = $when !== '' ? date('d/m/Y H:i', strtotime($when) ?: time()) : '—';
                                    ?>
                                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                                        <div>
                                            <p class="font-semibold text-stone-900"><?= htmlspecialchars($fn, ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="mt-0.5 text-xs text-stone-500"><?= $k === 'audio' ? 'Audio' : 'Document' ?> · <?= htmlspecialchars($hum, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($whenFmt, ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <a href="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/piece/' . $aid), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-xs font-bold text-stone-800 transition hover:bg-stone-50">Télécharger</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Rubrique 1</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Identité &amp; réception</h2>
                </div>
                <div class="divide-y divide-stone-100">
                    <?php if ($isInternalOpeningApplication): ?>
                    <div class="px-6 py-4 bg-violet-50/90 border-b border-violet-100">
                        <p class="text-xs font-bold uppercase tracking-wide text-violet-900">Candidature interne ciblée</p>
                        <p class="mt-1 text-sm leading-relaxed text-violet-950">Membre déjà rattaché à cette communauté, dossier positionné sur un avis de poste publié ici.</p>
                    </div>
                    <?php endif; ?>
                    <div class="px-6 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Nom complet</p>
                        <p class="mt-1 text-lg font-semibold text-stone-900"><?= htmlspecialchars(trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?: '—') ?></p>
                    </div>
                    <div class="grid gap-0 sm:grid-cols-2 sm:divide-x sm:divide-stone-100">
                        <div class="px-6 py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Courriel</p>
                            <p class="mt-1 break-all text-stone-800"><?= htmlspecialchars((string) ($e['email'] ?? '—')) ?></p>
                        </div>
                        <div class="px-6 py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Indicatif</p>
                            <p class="mt-1 text-stone-800"><?= htmlspecialchars((string) ($e['callsign'] ?? '—')) ?></p>
                        </div>
                    </div>
                    <div class="px-6 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Compte portail</p>
                        <div class="mt-1">
                            <?php if ($submitterId > 0): ?>
                                <a href="<?= htmlspecialchars(url('personnel/' . $submitterId)) ?>" class="font-semibold text-[#1c4d6e] underline decoration-[#1c4d6e]/30 underline-offset-2 hover:decoration-[#1c4d6e]">Ouvrir la fiche membre liée</a>
                                <?php if ($isInternalOpeningApplication): ?>
                                    <p class="mt-2 text-sm text-stone-700 leading-relaxed">Compte membre de la communauté — candidature associée à un avis interne (voir ci-dessous).</p>
                                <?php else: ?>
                                    <p class="mt-2 text-sm text-stone-600 leading-relaxed">Soumission avec compte — aucun avis de poste précis au dépôt.</p>
                                <?php endif; ?>
                                <span class="mt-2 block text-xs text-stone-500">Canal de soumission : <?= htmlspecialchars((string) ($e['submitted_via'] ?? '—')) ?></span>
                            <?php elseif ($linkedRo !== null): ?>
                                <span class="text-stone-700">Candidature reçue sans compte au moment du dépôt — dossier relié à un avis de poste.</span>
                            <?php else: ?>
                                <span class="text-stone-500">Candidature invitée (sans compte au dépôt)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($transmissionLines !== []): ?>
                    <div class="px-6 py-4 bg-[#faf8f3]">
                        <p class="text-xs font-bold uppercase tracking-wide text-stone-600">Éléments transmis par le candidat</p>
                        <ul class="mt-2 list-disc pl-5 text-sm text-stone-800 space-y-1">
                            <?php foreach ($transmissionLines as $line): ?>
                                <li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($e['reviewed_at']) || !empty($e['reviewer_comment']) || !empty($e['reviewed_by'])): ?>
                    <div class="border-t border-stone-200 bg-[#faf8f3]/60 px-6 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Instruction du dossier</p>
                        <div class="mt-2 text-sm text-stone-800">
                            <?php if (!empty($e['reviewed_at'])): ?>
                                <p class="tabular-nums font-medium"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $e['reviewed_at']))) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($e['reviewed_by'])): ?>
                                <p class="mt-1 text-xs text-stone-500">Traité par le compte interne n°<?= (int) $e['reviewed_by'] ?></p>
                            <?php endif; ?>
                            <?php if (!empty($e['reviewer_comment'])): ?>
                                <div class="mt-3 rounded-xl border border-stone-200 bg-white p-4 text-stone-800 shadow-inner whitespace-pre-wrap"><?= htmlspecialchars((string) $e['reviewer_comment']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($e['recruitment_preset_id'])): ?>
                    <div class="px-6 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Modèle de formulaire utilisé</p>
                        <p class="mt-1 text-stone-700">Référence interne n°<?= (int) $e['recruitment_preset_id'] ?></p>
                    </div>
                    <?php endif; ?>
                    <?php
                    $cslug = trim((string) ($communitySlug ?? ''));
                    $avisSlug = $linkedRo ? trim((string) ($linkedRo['public_page_slug'] ?? '')) : '';
                    ?>
                    <?php if ($linkedRo !== null): ?>
                    <div class="px-6 py-4 bg-sky-50/50">
                        <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Avis de poste associé</p>
                        <p class="mt-1 font-semibold text-stone-900"><?= htmlspecialchars((string) ($linkedRo['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (trim((string) ($linkedRo['reference_public'] ?? '')) !== ''): ?>
                            <p class="mt-1 text-sm text-stone-600">Référence affichée : <?= htmlspecialchars((string) $linkedRo['reference_public'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if ($cslug !== '' && $avisSlug !== ''): ?>
                            <a href="<?= htmlspecialchars(url('c/' . rawurlencode($cslug) . '/avis/' . rawurlencode($avisSlug)), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 inline-block text-sm font-semibold text-sky-800 underline hover:text-sky-950" target="_blank" rel="noopener">Ouvrir la fiche publique</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($statusRaw === 'reviewed'): ?>
            <section class="overflow-hidden rounded-2xl border border-sky-200/90 bg-white shadow-sm">
                <div class="border-b border-sky-100 bg-sky-50/90 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-sky-800/80">Après décision</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-sky-950">Rattachement au compte membre</h2>
                </div>
                <div class="p-6">
                    <?php if (!empty($membershipRepairHint)): ?>
                        <p class="text-sm leading-relaxed text-sky-950"><?= htmlspecialchars((string) $membershipRepairHint) ?></p>
                    <?php else: ?>
                        <p class="text-sm leading-relaxed text-sky-900/90">
                            Si le membre ne voit pas encore votre communauté comme prévu, vous pouvez relancer l’alignement du compte sur cette organisation.
                        </p>
                    <?php endif; ?>
                    <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/finalize-membership')) ?>" class="mt-5">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <button type="submit" class="enlist-membership-repair-btn inline-flex min-h-[2.75rem] items-center justify-center rounded-xl px-6 py-2.5 text-sm font-bold shadow-md transition">
                            Forcer le rattachement au compte de la communauté
                        </button>
                    </form>
                    <p class="mt-3 text-xs text-sky-800/80">Aucun nouvel e-mail automatique. Le membre peut se connecter s’il avait déjà un accès.</p>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($statusRaw === 'submitted'): ?>
            <section id="instruction-dossier" class="overflow-hidden rounded-2xl border border-amber-200/90 bg-white shadow-sm">
                <div class="border-b border-amber-100 bg-amber-50 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-amber-900/70">Instruction</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-amber-950">Décision à enregistrer</h2>
                    <p class="mt-2 max-w-2xl text-sm text-amber-950/85">Chaque action envoie un e-mail au candidat (statut, commentaire éventuel, lien de suivi).</p>
                </div>
                <div class="p-6">
                    <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/decision')) ?>" class="space-y-5">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <div class="rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-xs leading-relaxed text-stone-700">
                            Vérifiez le texte du message avant validation : le candidat le reçoit par e-mail avec le lien de suivi sécurisé.
                        </div>
                        <div>
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <label for="reviewer_comment" class="text-xs font-bold text-amber-950">Note interne (facultatif)</label>
                                <div class="flex flex-wrap items-center gap-2">
                                    <?php if (!empty($enlistmentCannedMessages)): ?>
                                    <label for="canned-msg-select" class="sr-only">Modèle de texte</label>
                                    <select id="canned-msg-select" class="<?= htmlspecialchars(bo_select_class('max-w-[min(100%,18rem)] border-amber-300 text-xs font-semibold text-amber-950 focus:border-amber-500 focus:ring-amber-500/25'), ENT_QUOTES, 'UTF-8') ?>">
                                        <option value="">— Insérer un modèle —</option>
                                        <?php foreach ($enlistmentCannedMessages as $cm): ?>
                                        <?php $ctx = (string) ($cm['context'] ?? 'generic'); ?>
                                        <option value="<?= (int) ($cm['id'] ?? 0) ?>" data-context="<?= htmlspecialchars($ctx, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars((string) ($cm['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php endif; ?>
                                    <a href="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits')) ?>" class="text-xs font-bold text-[#1c4d6e] underline underline-offset-2 hover:text-[#0c3d5c]">Gérer les modèles</a>
                                </div>
                            </div>
                            <textarea id="reviewer_comment" name="reviewer_comment" rows="4" class="w-full rounded-xl border border-amber-200 bg-white px-4 py-3 text-sm text-stone-900 shadow-inner placeholder:text-stone-400" placeholder="Motif, consignes pour l’équipe…"></textarea>
                            <?php if (!empty($enlistmentCannedMessages)): ?>
                            <script type="application/json" id="enlistment-canned-json"><?= json_encode($enlistmentCannedMessages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                            <script>
                            (function () {
                              var sel = document.getElementById('canned-msg-select');
                              var raw = document.getElementById('enlistment-canned-json');
                              var ta = document.getElementById('reviewer_comment');
                              var actionButtons = document.querySelectorAll('.enlist-decision-actions [name=\"decision\"]');
                              var interviewWrap = document.getElementById('interview-slot-wrap');
                              if (!sel || !raw || !ta) return;
                              var list = [];
                              try { list = JSON.parse(raw.textContent || '[]'); } catch (e) { return; }
                              var byId = {};
                              list.forEach(function (row) {
                                if (!row || !row.id) return;
                                byId[String(row.id)] = { body: row.body || '', context: row.context || 'generic' };
                              });
                              var currentDecision = 'accept';
                              var toContext = function (decision) {
                                if (decision === 'accept') return 'accept';
                                if (decision === 'reject') return 'reject';
                                if (decision === 'block') return 'reject';
                                if (decision === 'pending') return 'pending';
                                if (decision === 'interview') return 'pending';
                                return 'generic';
                              };
                              var syncInterviewField = function () {
                                if (!interviewWrap) return;
                                interviewWrap.classList.toggle('hidden', currentDecision !== 'interview');
                              };
                              var updateOptions = function () {
                                var wanted = toContext(currentDecision);
                                Array.prototype.forEach.call(sel.options, function (opt, idx) {
                                  if (idx === 0) { opt.hidden = false; return; }
                                  var c = opt.getAttribute('data-context') || 'generic';
                                  opt.hidden = !(c === 'generic' || c === wanted);
                                });
                                sel.selectedIndex = 0;
                              };
                              Array.prototype.forEach.call(actionButtons, function (btn) {
                                btn.addEventListener('focus', function () {
                                  currentDecision = btn.value || 'accept';
                                  updateOptions();
                                  syncInterviewField();
                                });
                                btn.addEventListener('mouseenter', function () {
                                  currentDecision = btn.value || 'accept';
                                  updateOptions();
                                  syncInterviewField();
                                });
                                btn.addEventListener('click', function () {
                                  currentDecision = btn.value || 'accept';
                                  syncInterviewField();
                                });
                              });
                              updateOptions();
                              syncInterviewField();
                              sel.addEventListener('change', function () {
                                var id = sel.value;
                                if (!id || !byId[id]) { sel.selectedIndex = 0; return; }
                                var chunk = byId[id].body;
                                if (ta.value.trim() !== '') ta.value += '\n\n';
                                ta.value += chunk;
                                sel.selectedIndex = 0;
                                ta.focus();
                              });
                            })();
                            </script>
                            <?php endif; ?>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 enlist-decision-actions" role="group" aria-label="Décision sur la candidature">
                            <?php
                            $btnBase = 'enlist-decision-btn inline-flex min-h-[2.75rem] items-center justify-center px-6 py-2.5 rounded-xl text-sm font-bold shadow-sm border transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 cursor-pointer';
                            ?>
                            <button type="submit" name="decision" value="accept" class="<?= $btnBase ?> enlist-decision-btn--accept">Accepter</button>
                            <button type="submit" name="decision" value="pending" class="<?= $btnBase ?> enlist-decision-btn--pending">Mettre en attente</button>
                            <button type="submit" name="decision" value="interview" class="<?= $btnBase ?> enlist-decision-btn--interview">Demander un entretien</button>
                            <button type="submit" name="decision" value="reject" class="<?= $btnBase ?> enlist-decision-btn--reject">Refuser</button>
                            <button type="submit" name="decision" value="block" class="<?= $btnBase ?> enlist-decision-btn--block">Marquer non admis</button>
                        </div>
                        <div id="interview-slot-wrap" class="hidden rounded-xl border border-violet-200 bg-violet-50/70 p-4">
                            <label for="interview_slot" class="block text-xs font-bold uppercase tracking-wide text-violet-900">Créneau d’entretien proposé (optionnel)</label>
                            <input type="datetime-local" id="interview_slot" name="interview_slot" class="mt-2 w-full max-w-xs rounded-lg border border-violet-300 bg-white px-3 py-2 text-sm text-violet-950">
                        </div>
                        <p class="text-xs text-amber-900/85"><strong>En attente</strong> et <strong>demande d’entretien</strong> gardent le dossier dans la file, avec notification e-mail. <strong>Non admis</strong> clôt le dossier de façon définitive pour cette candidature.</p>
                    </form>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($statusRaw === 'submitted'): ?>
            <section class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Aide à la décision</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Parcours possibles</h2>
                </div>
                <div class="grid gap-4 p-6 md:grid-cols-3">
                    <article class="rounded-xl border border-sky-200 bg-sky-50/70 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-sky-900">Pré-qualification</h3>
                        <p class="mt-2 text-sm text-sky-900/90">Utilisez « Mettre en attente » pour conserver le dossier actif le temps d’obtenir des informations complémentaires.</p>
                    </article>
                    <article class="rounded-xl border border-violet-200 bg-violet-50/70 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-violet-900">Entretien</h3>
                        <p class="mt-2 text-sm text-violet-900/90">« Demander un entretien » journalise le besoin d’échange et peut inclure un créneau proposé.</p>
                    </article>
                    <article class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-emerald-900">Décision finale</h3>
                        <p class="mt-2 text-sm text-emerald-900/90">Accepter, refuser ou non admettre finalise l’arbitrage RH et met à jour l’état du dossier.</p>
                    </article>
                </div>
            </section>
            <?php endif; ?>

            <?php
            $olympus = [
                'age' => 'Âge',
                'timezone' => 'Fuseau horaire',
                'weekly_availability' => 'Disponibilités hebdomadaires',
                'system_config' => 'Configuration matérielle',
                'microphone_quality' => 'Qualité microphone',
                'past_milsim_experience' => 'Expérience MilSim',
                'ace_acre_level' => 'Niveau ACE / ACRE',
                'motivation_why_join' => 'Motivation',
                'motivation_accountability' => 'Responsabilité & sérieux',
                'commitment_effort' => 'Engagement',
                'availability_wed_sat' => 'Mercredis & samedis soir',
                'availability' => 'Disponibilité (résumé)',
            ];
            $hasOlympus = false;
            foreach ($olympus as $k => $_) {
                if (isset($e[$k]) && $e[$k] !== '' && $e[$k] !== null) {
                    $hasOlympus = true;
                    break;
                }
            }
            ?>
            <?php if ($hasOlympus): ?>
            <section class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
                <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Rubrique 2</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Questionnaire MilSim</h2>
                </div>
                <div class="divide-y divide-stone-100 px-6 py-2">
                    <?php foreach ($olympus as $col => $label): ?>
                        <?php if (isset($e[$col]) && $e[$col] !== '' && $e[$col] !== null): ?>
                            <div class="py-4">
                                <p class="text-xs font-bold uppercase tracking-wide text-stone-500"><?= htmlspecialchars($label) ?></p>
                                <p class="mt-2 text-sm leading-relaxed text-stone-800 whitespace-pre-wrap"><?= htmlspecialchars((string) $e[$col]) ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($e['notes'])): ?>
            <section class="overflow-hidden rounded-2xl border border-stone-300/80 bg-stone-50/40 shadow-sm">
                <div class="border-b border-stone-200 bg-stone-50 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Synthèse</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-stone-900">Notes consolidées</h2>
                </div>
                <pre class="max-h-[28rem] overflow-auto p-6 text-sm leading-relaxed text-stone-800 whitespace-pre-wrap font-sans"><?= htmlspecialchars((string) $e['notes']) ?></pre>
            </section>
            <?php endif; ?>

            <?php if ($rpSnap): ?>
            <section class="overflow-hidden rounded-2xl border border-emerald-200/80 bg-white shadow-sm">
                <div class="border-b border-emerald-100 bg-emerald-50/80 px-6 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-emerald-800/80">Profil RP</p>
                    <h2 class="mt-1 text-base font-black tracking-tight text-emerald-950">Dossier personnage (copie au dépôt)</h2>
                </div>
                <div class="space-y-5 p-6">
                    <?php
                    $img = trim((string) ($rpSnap['image_url'] ?? ''));
                    $imgExt = trim((string) ($rpSnap['image_external_url'] ?? ''));
                    ?>
                    <?php if ($img !== ''): ?>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-900 mb-2">Portrait</p>
                            <img src="<?= htmlspecialchars(url($img)) ?>" alt="" class="max-h-52 rounded-xl border border-emerald-200 shadow-sm">
                        </div>
                    <?php endif; ?>
                    <?php if ($imgExt !== ''): ?>
                        <p class="text-sm"><a href="<?= htmlspecialchars($imgExt) ?>" class="break-all font-medium text-emerald-800 underline underline-offset-2 hover:text-emerald-950" target="_blank" rel="noopener">Lien vers portrait externe</a></p>
                    <?php endif; ?>
                    <?php if (trim((string) ($rpSnap['character_name'] ?? '')) !== ''): ?>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-900">Personnage</p>
                            <p class="mt-1 text-stone-900"><?= htmlspecialchars((string) $rpSnap['character_name']) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (trim((string) ($rpSnap['bio'] ?? '')) !== ''): ?>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-900">Biographie</p>
                            <pre class="mt-2 text-sm text-stone-800 whitespace-pre-wrap font-sans"><?= htmlspecialchars((string) $rpSnap['bio']) ?></pre>
                        </div>
                    <?php endif; ?>
                    <?php if (trim((string) ($rpSnap['cv'] ?? '')) !== ''): ?>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-900">Parcours (CV)</p>
                            <pre class="mt-2 text-sm text-stone-800 whitespace-pre-wrap font-sans"><?= htmlspecialchars((string) $rpSnap['cv']) ?></pre>
                        </div>
                    <?php endif; ?>
                    <?php if (trim((string) ($rpSnap['admin_notes'] ?? '')) !== ''): ?>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-900">Remarques candidat</p>
                            <pre class="mt-2 text-sm text-stone-800 whitespace-pre-wrap font-sans"><?= htmlspecialchars((string) $rpSnap['admin_notes']) ?></pre>
                        </div>
                    <?php endif; ?>
                    <?php
                    $derived = is_array($rpSnap['derived_availability'] ?? null) ? $rpSnap['derived_availability'] : null;
                    ?>
                    <?php if ($derived && (!empty($derived['availability']) || !empty($derived['weekly_availability']))): ?>
                        <div class="rounded-xl border border-emerald-200/60 bg-white/80 p-4 text-sm text-emerald-950">
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-900 mb-2">Disponibilités (synthèse)</p>
                            <?php if (!empty($derived['weekly_availability'])): ?>
                                <p class="whitespace-pre-wrap"><?= htmlspecialchars((string) $derived['weekly_availability']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($derived['availability']) && ($derived['availability'] ?? '') !== ($derived['weekly_availability'] ?? '')): ?>
                                <p class="mt-2 whitespace-pre-wrap"><?= htmlspecialchars((string) $derived['availability']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

        <section id="journal-dossier" class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm" aria-labelledby="journal-dossier-heading">
            <div class="border-b border-stone-200 bg-stone-50 px-6 py-4 sm:px-8">
                <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Traçabilité</p>
                <h2 id="journal-dossier-heading" class="mt-1 text-base font-black tracking-tight text-stone-900">Journal du dossier</h2>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-stone-600">Historique des événements et des notes internes. Ajoutez des commentaires par étape pour l’équipe — rien n’est envoyé automatiquement au candidat depuis ce bloc.</p>
            </div>
            <div class="px-6 py-6 sm:px-8 sm:py-8">
                <?php if ($timelineTableMissing): ?>
                    <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Le journal n’est pas encore activé sur cette base : exécutez les migrations pour créer la table dédiée.</p>
                <?php elseif ($enlistmentTimeline === []): ?>
                    <p class="text-sm text-stone-600">Aucune entrée pour l’instant.</p>
                <?php else: ?>
                    <ol class="space-y-5 border-l-2 border-stone-200 pl-5 sm:pl-6">
                        <?php foreach ($enlistmentTimeline as $ev): ?>
                            <?php
                            $evKind = (string) ($ev['entry_kind'] ?? '');
                            $evStep = (string) ($ev['step_code'] ?? 'general');
                            $stepTitle = $timelineStepLabels[$evStep] ?? $timelineStepLabels['general'];
                            $actorId = (int) ($ev['actor_user_id'] ?? 0);
                            $actorName = $actorId > 0 ? ($timelineActorLabels[$actorId] ?? ('Compte n°' . $actorId)) : null;
                            $created = trim((string) ($ev['created_at'] ?? ''));
                            $createdFmt = $created !== '' ? date('d/m/Y à H:i', strtotime($created) ?: time()) : '—';
                            $summary = trim((string) ($ev['summary'] ?? ''));
                            $body = trim((string) ($ev['body'] ?? ''));
                            ?>
                            <li class="relative pl-2">
                                <span class="absolute -left-[1.4rem] top-1.5 flex h-2.5 w-2.5 rounded-full ring-4 ring-white <?= $evKind === 'staff_note' ? 'bg-sky-500' : 'bg-emerald-500' ?>" aria-hidden="true"></span>
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                    <time class="text-xs font-bold tabular-nums text-stone-500" datetime="<?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></time>
                                    <span class="rounded-md bg-stone-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-stone-700"><?= htmlspecialchars($stepTitle, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-[10px] font-bold uppercase tracking-wide <?= $evKind === 'staff_note' ? 'text-sky-800' : 'text-emerald-800' ?>"><?= $evKind === 'staff_note' ? 'Note interne' : 'Événement' ?></span>
                                </div>
                                <?php if ($summary !== ''): ?>
                                    <p class="mt-2 text-sm font-semibold text-stone-900"><?= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if ($body !== ''): ?>
                                    <div class="mt-2 rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm leading-relaxed text-stone-800 whitespace-pre-wrap"><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                                <?php if ($actorName !== null): ?>
                                    <p class="mt-2 text-xs text-stone-500">Par <?= htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <?php if (!$timelineTableMissing): ?>
                    <div class="mt-8 border-t border-stone-200 pt-8">
                        <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-stone-500">Ajouter une note sur une étape</h3>
                        <p class="mt-2 text-sm text-stone-600">Visible uniquement dans ce dossier (pas envoyée au candidat).</p>
                        <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/timeline-comment'), ENT_QUOTES, 'UTF-8') ?>" class="mt-5 space-y-4">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                            <div>
                                <label for="timeline_step" class="mb-1 block text-xs font-bold text-stone-700">Étape concernée</label>
                                <select id="timeline_step" name="timeline_step" class="<?= htmlspecialchars(bo_select_class('w-full max-w-md border-stone-300 text-sm text-stone-900'), ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach (['instruction', 'decision', 'adhesion', 'reception', 'general'] as $code): ?>
                                        <?php if (!isset($timelineStepLabels[$code])) {
                                            continue;
                                        } ?>
                                        <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $timelineStepLabels[$code], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="timeline_body" class="mb-1 block text-xs font-bold text-stone-700">Commentaire</label>
                                <textarea id="timeline_body" name="timeline_body" rows="4" required maxlength="8000" class="w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-inner placeholder:text-stone-400" placeholder="Consignes, rappel d’échange, point de vigilance…"></textarea>
                            </div>
                            <button type="submit" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl border border-stone-900 bg-stone-900 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-stone-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 focus-visible:ring-offset-2">Enregistrer dans le journal</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </section>

                <p class="mt-10 text-center">
                    <a href="<?= htmlspecialchars(url('back-office/recruitments')) ?>" class="text-sm font-semibold text-stone-600 underline decoration-stone-300 underline-offset-4 transition hover:text-emerald-800">← Retour aux dossiers</a>
                </p>
            </div>
            </div>
        </div>
</div>
<?php if ($statusRaw === 'submitted'): ?>
<script>
(function () {
    var wrap = document.getElementById('interview-slot-wrap');
    var buttons = document.querySelectorAll('.enlist-decision-actions [name="decision"]');
    if (!wrap || !buttons.length) return;
    var sync = function (value) {
        wrap.classList.toggle('hidden', value !== 'interview');
    };
    buttons.forEach(function (btn) {
        btn.addEventListener('focus', function () { sync(btn.value || ''); });
        btn.addEventListener('mouseenter', function () { sync(btn.value || ''); });
        btn.addEventListener('click', function () { sync(btn.value || ''); });
    });
    sync('');
})();
</script>
<?php endif; ?>
