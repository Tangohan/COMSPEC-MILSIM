<?php
$baseUrl = url('');
$targetUser = $targetUser ?? null;
$personnelExtras = $personnelExtras ?? [];
$personnelProfile = $personnelProfile ?? null;
$userProfile = $userProfile ?? null;
$grade = $grade ?? null;
$grades = $grades ?? [];
$assignments = $assignments ?? [];
$primaryAssignment = $primaryAssignment ?? null;
$commander = $commander ?? null;
$qualifications = $qualifications ?? [];
$serviceHistory = $serviceHistory ?? [];
$trainingCertificates = $trainingCertificates ?? [];
$completeness = $completeness ?? ['score' => 0, 'sections_critiques' => [], 'details' => []];
$adminPanels = $adminPanels ?? [];
$adminDataByPanel = $adminDataByPanel ?? [];
$canEditNotes = $canEditNotes ?? false;
$canEditProfile = $canEditProfile ?? false;
$canViewCivil = $canViewCivil ?? false;
$canViewCivilSection = $canViewCivilSection ?? $canViewCivil;
$redactPersonalPresentation = $redactPersonalPresentation ?? false;
$canViewCommandNotes = $canViewCommandNotes ?? true;
$displaySettings = $displaySettings ?? [];
$showEmailInContact = $showEmailInContact ?? true;
$showMatriculePublic = $showMatriculePublic ?? true;
$civilIdentity = $civilIdentity ?? ['first_name' => '', 'last_name' => '', 'source' => null];
$civilSourceLabel = $civilSourceLabel ?? '';
$primaryUnitFallbackName = $primaryUnitFallbackName ?? null;
$rpDossierNeedsAttention = $rpDossierNeedsAttention ?? false;
$latestEnlistment = $latestEnlistment ?? null;

if (!$targetUser) {
    echo '<p>Utilisateur non trouvé.</p>';
    return;
}

$userProfile = is_array($userProfile ?? null) ? $userProfile : [];
$personnelProfile = is_array($personnelProfile ?? null) ? $personnelProfile : [];
$personnelExtras = is_array($personnelExtras ?? null) ? $personnelExtras : [];

$matricule = $personnelProfile['matricule_internal'] ?? $personnelExtras['service_number'] ?? null;
$callsign = $personnelProfile['callsign'] ?? $targetUser['callsign'] ?? null;
$rpCharacterName = trim((string) ($personnelProfile['character_name'] ?? ''));
if ($rpCharacterName !== '') {
    $displayName = $rpCharacterName;
} elseif (!empty($redactPersonalPresentation)) {
    $dn = trim((string) ($targetUser['display_name'] ?? ''));
    $displayName = $dn !== '' ? $dn : (string) ($targetUser['email'] ?? '—');
} else {
    $civilFull = trim(($civilIdentity['first_name'] ?? '') . ' ' . ($civilIdentity['last_name'] ?? ''));
    $displayName = $civilFull !== '' ? $civilFull : ($targetUser['display_name'] ?: $targetUser['email']);
}
$readiness = isset($personnelProfile['readiness_score']) ? (int)$personnelProfile['readiness_score'] : (isset($personnelExtras['readiness_percent']) ? (int)$personnelExtras['readiness_percent'] : null);
$adminNotes = trim((string)($personnelProfile['command_notes'] ?? '')) ?: ($personnelExtras['admin_notes'] ?? null);
$clearanceLevel = trim((string)($personnelProfile['clearance_level'] ?? '')) ?: trim((string)($personnelExtras['clearance_level'] ?? ''));

$avatarUrl = !empty($targetUser['avatar_url']) ? $targetUser['avatar_url'] : null;
if ($avatarUrl && strpos($avatarUrl, 'http') !== 0) {
    $avatarUrl = $baseUrl . '/' . ltrim($avatarUrl, '/');
}
$portraitUrl = null;
if (!empty($personnelProfile['character_portrait_path'])) {
    $portraitUrl = $baseUrl . '/' . ltrim($personnelProfile['character_portrait_path'], '/');
}

$unitName = $primaryAssignment['unit_name'] ?? $primaryUnitFallbackName ?? ($personnelExtras['squadron'] ?? null);
$roleName = $primaryAssignment['role_name'] ?? null;
$enlistmentDate = $personnelProfile['enlistment_date'] ?? $personnelExtras['date_of_enlistment'] ?? null;
$enlistmentFormatted = null;
if ($enlistmentDate) {
    $d = date_create($enlistmentDate);
    $enlistmentFormatted = $d ? $d->format('d/m/Y') : $enlistmentDate;
}
$flightHours = $personnelExtras['flight_hours'] ?? null;
$specializations = $personnelExtras['specializations'] ?? null;

$completenessScore = (int)($completeness['score'] ?? 0);
$sectionsCritiques = $completeness['sections_critiques'] ?? [];

$gradeLabel = '';
if (is_array($grade)) {
    $gradeLabel = trim((string) ($grade['label_long'] ?? ''));
    if ($gradeLabel === '') {
        $gradeLabel = trim((string) ($grade['label_short'] ?? ''));
    }
}
?>
<main class="min-h-screen pt-20 pb-24">
    <!-- Hero -->
    <section class="w-full bg-slate-900 bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950/30 border-b border-slate-700/50">
        <div class="max-w-7xl mx-auto px-6 md:px-8 py-12 md:py-16">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-8">
                <div class="space-y-2">
                    <p class="text-[9px] font-black uppercase tracking-[0.4em] text-emerald-400/90 italic">Dossier personnel</p>
                    <h1 class="text-3xl md:text-4xl lg:text-5xl font-black uppercase tracking-tighter text-white italic">
                        <?= htmlspecialchars($displayName) ?>
                    </h1>
                    <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                        <?php if ($callsign): ?>
                        <span class="text-lg md:text-xl font-black text-slate-300 italic"><?= htmlspecialchars($callsign) ?></span>
                        <?php endif; ?>
                        <?php if ($matricule && !empty($showMatriculePublic)): ?>
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Matricule <?= htmlspecialchars($matricule) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($unitName): ?>
                    <p class="text-sm text-slate-400"><?= htmlspecialchars($unitName) ?></p>
                    <?php endif; ?>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-[10px] font-black uppercase <?= ($targetUser['status'] ?? '') === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-600/30 text-slate-400' ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= ($targetUser['status'] ?? '') === 'active' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500' ?>"></span>
                            <?= ($targetUser['status'] ?? '') === 'active' ? 'Actif' : htmlspecialchars($targetUser['status'] ?? '—') ?>
                        </span>
                        <?php if ($clearanceLevel): ?>
                        <span class="inline-flex px-2.5 py-0.5 rounded text-[10px] font-black uppercase bg-slate-600/30 text-slate-300">Clearance <?= htmlspecialchars($clearanceLevel) ?></span>
                        <?php endif; ?>
                        <?php if (($personnelProfile['deployable'] ?? 1)): ?>
                        <span class="inline-flex px-2.5 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-400">Déployable</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-4 md:gap-6">
                    <div class="w-20 h-20 md:w-24 md:h-24 rounded-2xl overflow-hidden border-2 border-slate-600/50 bg-slate-800 flex-shrink-0" title="Avatar compte">
                        <?php if ($avatarUrl): ?>
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" class="w-full h-full object-cover" />
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-slate-500">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="w-20 h-20 md:w-24 md:h-24 rounded-2xl overflow-hidden border-2 border-slate-600/50 bg-slate-800 flex-shrink-0" title="Portrait opérateur">
                        <?php if ($portraitUrl): ?>
                        <img src="<?= htmlspecialchars($portraitUrl) ?>" alt="Portrait opérateur" class="w-full h-full object-cover" />
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-slate-500">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Complétude -->
    <section class="w-full border-b border-slate-200 bg-white">
        <div class="max-w-7xl mx-auto px-6 md:px-8 py-4">
            <div class="flex flex-wrap items-center gap-4">
                <span class="text-sm font-black text-slate-700">Profil complété à <?= $completenessScore ?>%</span>
                <div class="w-32 h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: <?= min(100, max(0, $completenessScore)) ?>%"></div>
                </div>
                <?php if (!empty($sectionsCritiques) && $canEditProfile): ?>
                <span class="text-xs text-amber-700 font-semibold"><?= count($sectionsCritiques) ?> section(s) critique(s) incomplète(s) : <?= htmlspecialchars(implode(', ', $sectionsCritiques)) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Récap -->
    <section class="w-full border-b border-slate-200 bg-white">
        <div class="max-w-7xl mx-auto px-6 md:px-8 py-6">
            <div class="flex flex-wrap gap-6 md:gap-10">
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Rang</p>
                    <p class="text-sm font-black text-slate-900 italic"><?= $gradeLabel !== '' ? htmlspecialchars($gradeLabel) : '—' ?></p>
                </div>
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Unité</p>
                    <p class="text-sm font-black text-slate-900 italic"><?= $unitName ? htmlspecialchars($unitName) : '—' ?></p>
                </div>
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Habilitation</p>
                    <p class="text-sm font-black text-emerald-600 italic"><?= $clearanceLevel ? htmlspecialchars($clearanceLevel) : '—' ?></p>
                </div>
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Readiness</p>
                    <p class="text-sm font-black text-slate-900"><?= $readiness !== null ? $readiness . '%' : '—' ?></p>
                </div>
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Statut réseau</p>
                    <p class="text-sm font-bold <?= ($targetUser['status'] ?? '') === 'active' ? 'text-emerald-600' : 'text-slate-500' ?> italic"><?= ($targetUser['status'] ?? '') === 'active' ? 'Actif' : htmlspecialchars($targetUser['status'] ?? '—') ?></p>
                </div>
                <?php if ($enlistmentFormatted): ?>
                <div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Enrôlement</p>
                    <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($enlistmentFormatted) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="max-w-7xl mx-auto px-6 md:px-8 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            <!-- Sidebar -->
            <aside class="lg:col-span-3 lg:sticky lg:top-32 h-fit order-2 lg:order-1 space-y-6">
                <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-2">Photo de compte</p>
                    <div class="aspect-square max-w-[140px] bg-slate-100 rounded-2xl overflow-hidden border border-slate-200 mb-4">
                        <?php if ($avatarUrl): ?>
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" class="w-full h-full object-cover" />
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-slate-300"><svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div>
                        <?php endif; ?>
                    </div>
                    <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-2">Portrait opérateur</p>
                    <div class="aspect-[3/4] max-w-[140px] bg-slate-100 rounded-2xl overflow-hidden border border-slate-200 mb-4">
                        <?php if ($portraitUrl): ?>
                        <img src="<?= htmlspecialchars($portraitUrl) ?>" alt="Portrait" class="w-full h-full object-cover" />
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-slate-300"><svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-3">
                        <?php if (!empty($showMatriculePublic)): ?>
                        <div>
                            <p class="text-[7px] font-black text-slate-400 tracking-[0.3em] mb-0.5 uppercase">Matricule</p>
                            <?php if ($matricule): ?>
                            <p class="text-base font-black text-slate-900"><?= htmlspecialchars($matricule) ?></p>
                            <?php elseif ($canEditProfile): ?>
                            <form method="post" action="<?= url('personnel/' . (int)$targetUser['id'] . '/generate-matricule') ?>"><?= \App\Core\Csrf::field() ?><button type="submit" class="text-[9px] font-black uppercase text-emerald-600 hover:text-emerald-700">Générer</button></form>
                            <?php else: ?>
                            <p class="text-xs text-slate-400 italic">Non attribué</p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($callsign): ?>
                        <div>
                            <p class="text-[7px] font-black text-slate-400 uppercase mb-0.5">Callsign</p>
                            <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($callsign) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($unitName): ?>
                        <div>
                            <p class="text-[7px] font-black text-slate-400 uppercase mb-0.5">Unité</p>
                            <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($unitName) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($canEditProfile): ?>
                <div class="flex flex-col gap-2">
                    <a href="<?= url('personnel/' . (int)$targetUser['id'] . '/edit') ?>" class="text-[9px] font-black uppercase tracking-widest text-emerald-600 hover:text-emerald-700">Éditer le dossier</a>
                    <a href="<?= url('account/image') ?>" class="text-[9px] font-black uppercase tracking-widest text-slate-600 hover:text-slate-900">Photo de compte</a>
                    <a href="<?= url('account/portrait') ?>" class="text-[9px] font-black uppercase tracking-widest text-slate-600 hover:text-slate-900">Portrait opérateur</a>
                </div>
                <?php endif; ?>
                <a href="<?= url('orbat') ?>" class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 hover:text-slate-900 inline-flex items-center gap-3"><span class="h-[1px] w-5 bg-slate-200"></span>Voir ORBAT</a>
                <a href="<?= url('documents') ?>" class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 hover:text-slate-900 inline-flex items-center gap-3"><span class="h-[1px] w-5 bg-slate-200"></span>Documents</a>
                <a href="<?= url('formations') ?>" class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 hover:text-slate-900 inline-flex items-center gap-3"><span class="h-[1px] w-5 bg-slate-200"></span>Formations</a>
                <a href="<?= url('dashboard') ?>" class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 hover:text-slate-900 inline-flex items-center gap-3"><span class="h-[1px] w-5 bg-slate-200"></span>Dashboard</a>
            </aside>

            <div class="lg:col-span-9 space-y-8 order-1 lg:order-2">
                <!-- Identité opérationnelle -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Identité opérationnelle</h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nom opérateur</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($displayName) ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Indicatif radio</p><p class="text-sm font-black text-slate-900"><?= $callsign ? htmlspecialchars($callsign) : '—' ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Matricule</p><p class="text-sm font-black text-slate-900"><?= !empty($showMatriculePublic) ? ($matricule ? htmlspecialchars($matricule) : '—') : '—' ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Rôle principal</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['primary_role'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Rôle secondaire</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['secondary_role'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Unité</p><p class="text-sm font-black text-slate-900"><?= $unitName ? htmlspecialchars($unitName) : '—' ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Chef direct</p><p class="text-sm font-black text-slate-900"><?= $commander ? htmlspecialchars($commander['display_name'] ?? $commander['callsign'] ?? '') : '—' ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Date d'incorporation</p><p class="text-sm font-black text-slate-900"><?= $enlistmentFormatted ?? '—' ?></p></div>
                    </div>
                    <?php
                    $rpMotto = trim((string) ($personnelProfile['motto'] ?? ''));
                    $rpBlood = trim((string) ($personnelProfile['blood_type'] ?? ''));
                    $rpLangs = trim((string) ($personnelProfile['languages'] ?? ''));
                    $rpNat = trim((string) ($personnelProfile['nationality'] ?? ''));
                    $rpExtra = $rpMotto !== '' || $rpBlood !== '' || $rpLangs !== '' || $rpNat !== '';
                    ?>
                    <?php if ($rpExtra): ?>
                    <div class="mt-8 border-t border-slate-100 pt-6">
                        <h3 class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500 mb-4">Détails RP (dossier opérationnel)</h3>
                        <div class="grid md:grid-cols-2 gap-6">
                            <?php if ($rpMotto !== ''): ?>
                            <div class="md:col-span-2"><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Devise / motto</p><p class="text-sm font-semibold text-slate-800 italic"><?= htmlspecialchars($rpMotto) ?></p></div>
                            <?php endif; ?>
                            <?php if ($rpBlood !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Groupe sanguin</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($rpBlood) ?></p></div>
                            <?php endif; ?>
                            <?php if ($rpLangs !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Langues (RP)</p><p class="text-sm text-slate-800"><?= htmlspecialchars($rpLangs) ?></p></div>
                            <?php endif; ?>
                            <?php if ($rpNat !== ''): ?>
                            <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nationalité (RP)</p><p class="text-sm text-slate-800"><?= htmlspecialchars($rpNat) ?></p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Affectation -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Affectation</h2>
                    <div class="space-y-4">
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Unité</p><p class="text-sm font-black text-slate-900"><?= $unitName ? htmlspecialchars($unitName) : 'Non assignée' ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Rôle dans l'équipe</p><p class="text-sm font-black text-slate-900"><?= $roleName ? htmlspecialchars($roleName) : '—' ?></p></div>
                        <?php if ($enlistmentFormatted): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Date d'enrôlement</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($enlistmentFormatted) ?></p></div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Sécurité / habilitation -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Sécurité / habilitation</h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Niveau documentaire</p><p class="text-sm font-black text-emerald-600"><?= $clearanceLevel ? htmlspecialchars($clearanceLevel) : '—' ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Date dernière revue</p><p class="text-sm font-black text-slate-900"><?= !empty($personnelProfile['clearance_reviewed_at']) ? date('d/m/Y', strtotime($personnelProfile['clearance_reviewed_at'])) : '—' ?></p></div>
                    </div>
                </section>

                <!-- Certifications & formations -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.4em] text-slate-700 mb-6">Certifications & formations</h2>
                    <div class="flex flex-wrap gap-4">
                        <?php if ($specializations): ?>
                        <div class="px-6 py-4 border border-slate-200 rounded-2xl flex flex-col gap-2 min-w-[200px]">
                            <span class="text-[7px] font-black tracking-widest text-slate-400 uppercase">Spécialisations</span>
                            <p class="text-xs font-bold text-slate-900 leading-relaxed"><?= nl2br(htmlspecialchars($specializations)) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php foreach ($qualifications as $q): ?>
                        <div class="px-6 py-4 border border-slate-200 rounded-2xl flex flex-col gap-2">
                            <span class="text-[7px] font-black tracking-widest text-slate-400 uppercase"><?= htmlspecialchars($q['qualification_name']) ?></span>
                            <p class="text-xs font-bold text-slate-900"><?= htmlspecialchars($q['level'] ?? '') ?> — <?= htmlspecialchars($q['status']) ?></p>
                            <?php if (!empty($q['expires_at'])): ?><p class="text-[10px] text-slate-500">Expire <?= date('d/m/Y', strtotime($q['expires_at'])) ?></p><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php foreach ($trainingCertificates as $cert): ?>
                        <div class="px-6 py-4 border border-emerald-200 rounded-2xl flex flex-col gap-2 bg-emerald-50/50">
                            <span class="text-[7px] font-black tracking-widest text-emerald-700 uppercase"><?= htmlspecialchars($cert['course_title'] ?? 'Certificat') ?></span>
                            <p class="text-xs font-bold text-slate-900"><?= htmlspecialchars($cert['status'] ?? 'valid') ?></p>
                            <?php if (!empty($cert['expires_at'])): ?><p class="text-[10px] text-slate-500">Expire <?= date('d/m/Y', strtotime($cert['expires_at'])) ?></p><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($qualifications) && empty($trainingCertificates) && !$specializations): ?>
                        <div class="px-6 py-4 border border-slate-100 bg-slate-50 rounded-2xl">
                            <span class="text-[7px] font-black tracking-widest text-slate-300 uppercase italic">Non renseigné</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Équipement / dotation -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Équipement / dotation</h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Classe d'équipement</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['equipment_class'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Kit assigné</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['kit_assigned'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Radio</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['radio_assigned'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Véhicule autorisé</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['vehicle_authorized'] ?? '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Spécialité armement</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($personnelProfile['weapon_specialty'] ?? '—') ?></p></div>
                    </div>
                </section>

                <!-- Readiness -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6 flex items-center justify-between">
                        Operational Readiness
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-black italic"><?= $readiness !== null ? $readiness : 0 ?>%</span>
                            <div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full" style="width: <?= min(100, max(0, $readiness ?? 0)) ?>%"></div>
                            </div>
                        </div>
                    </h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <?php if ($flightHours !== null && $flightHours !== ''): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase mb-1">Heures de vol</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars((string)$flightHours) ?></p></div>
                        <?php endif; ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase mb-1">Déployable</p><p class="text-sm font-black text-slate-900"><?= ($personnelProfile['deployable'] ?? 1) ? 'Oui' : 'Non' ?></p></div>
                    </div>
                </section>

                <!-- Historique de service -->
                <?php if (!empty($serviceHistory)): ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Historique de service</h2>
                    <div class="space-y-4">
                        <?php foreach ($serviceHistory as $event): ?>
                        <div class="flex gap-4 border-l-2 border-slate-200 pl-4 py-1">
                            <span class="text-[10px] font-mono text-slate-500 shrink-0"><?= date('Y-m', strtotime($event['event_date'])) ?></span>
                            <div>
                                <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($event['title']) ?></p>
                                <?php if (!empty($event['description'])): ?><p class="text-xs text-slate-600"><?= nl2br(htmlspecialchars($event['description'])) ?></p><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Notes de commandement -->
                <?php if ($canViewCommandNotes): ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6 flex items-center gap-3">
                        <span class="w-1.5 h-1.5 bg-rose-500 rounded-full"></span>
                        Notes de commandement <?= $canEditNotes ? '(éditable)' : '' ?>
                    </h2>
                    <?php if ($canEditNotes): ?>
                    <form method="post" action="<?= url('personnel/' . (int)$targetUser['id'] . '/notes') ?>" class="space-y-4">
                        <?= \App\Core\Csrf::field() ?>
                        <textarea name="admin_notes" rows="4" class="w-full text-xs text-slate-700 border border-slate-200 rounded-xl p-4 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500" placeholder="Notes internes (visible par vous et les admins)"><?= $adminNotes ? htmlspecialchars($adminNotes) : '' ?></textarea>
                        <button type="submit" class="text-[9px] font-black uppercase tracking-widest text-emerald-600 hover:text-emerald-700 border border-emerald-500/50 rounded-lg px-4 py-2">Enregistrer</button>
                    </form>
                    <?php else: ?>
                    <p class="text-xs text-slate-500 font-medium leading-relaxed italic"><?= $adminNotes ? nl2br(htmlspecialchars($adminNotes)) : '— Aucune note enregistrée.' ?></p>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <!-- Identité civile / administrative -->
                <?php if ($canViewCivilSection): ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-2">Identité civile / administrative</h2>
                    <?php if ($civilSourceLabel): ?>
                    <p class="text-[10px] text-slate-500 mb-6">Source prénom / nom : <span class="font-semibold text-slate-700"><?= htmlspecialchars($civilSourceLabel) ?></span><?php if (($civilIdentity['source'] ?? null) === 'enlistment' && $latestEnlistment): ?> (candidature #<?= (int) ($latestEnlistment['id'] ?? 0) ?>, <?= htmlspecialchars((string) ($latestEnlistment['status'] ?? '')) ?>)<?php endif; ?>.</p>
                    <?php else: ?>
                    <p class="text-[10px] text-slate-500 mb-6">Renseignez le prénom et le nom dans <a href="<?= url('account/preferences') ?>" class="font-semibold text-emerald-700 underline">Compte → Préférences</a> pour alimenter la fiche.</p>
                    <?php endif; ?>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Prénom</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($civilIdentity['first_name'] !== '' ? $civilIdentity['first_name'] : '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nom</p><p class="text-sm font-black text-slate-900"><?= htmlspecialchars($civilIdentity['last_name'] !== '' ? $civilIdentity['last_name'] : '—') ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">E-mail</p><p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($targetUser['email']) ?></p></div>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Statut compte</p><p class="text-sm font-bold <?= ($targetUser['status'] ?? '') === 'active' ? 'text-emerald-600' : 'text-slate-500' ?>"><?= htmlspecialchars($targetUser['status'] ?? '—') ?></p></div>
                        <?php if (!empty($userProfile['birth_date'])): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Date de naissance</p><p class="text-sm text-slate-800"><?= htmlspecialchars((string) $userProfile['birth_date']) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty(trim((string) ($userProfile['nationality'] ?? '')))): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Nationalité (dossier)</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $userProfile['nationality'])) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty(trim((string) ($userProfile['phone'] ?? '')))): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Téléphone</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $userProfile['phone'])) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty(trim((string) ($userProfile['timezone'] ?? '')))): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Fuseau</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $userProfile['timezone'])) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty(trim((string) ($userProfile['language'] ?? '')))): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Langue</p><p class="text-sm text-slate-800"><?= htmlspecialchars(trim((string) $userProfile['language'])) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty(trim((string) ($userProfile['bio'] ?? '')))): ?>
                        <div class="md:col-span-2"><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Bio (compte)</p><p class="text-sm text-slate-700 leading-relaxed"><?= nl2br(htmlspecialchars(trim((string) $userProfile['bio']))) ?></p></div>
                        <?php endif; ?>
                        <?php if (!empty($targetUser['last_login_at'])): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Dernière connexion</p><p class="text-sm text-slate-700"><?= date('d/m/Y H:i', strtotime($targetUser['last_login_at'])) ?></p></div>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Contact -->
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6">Contact</h2>
                    <div class="space-y-4">
                        <?php if (!empty($showEmailInContact)): ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">E-mail Ops</p><p class="text-[11px] font-bold text-slate-900 italic"><?= htmlspecialchars($targetUser['email']) ?></p></div>
                        <?php else: ?>
                        <p class="text-[11px] text-slate-500 italic">E-mail masqué par les préférences de visibilité du titulaire.</p>
                        <?php endif; ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Statut Réseau</p><p class="text-[11px] font-bold <?= ($targetUser['status'] ?? '') === 'active' ? 'text-emerald-600' : 'text-slate-500' ?> italic"><?= ($targetUser['status'] ?? '') === 'active' ? 'Actif' : htmlspecialchars($targetUser['status'] ?? '—') ?></p></div>
                    </div>
                </section>

                <?php foreach ($adminPanels as $panel): ?>
                <?php $panelId = (int)$panel['id']; $data = $adminDataByPanel[$panelId] ?? []; ?>
                <section class="bg-white border border-slate-200 rounded-3xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900 mb-6"><?= htmlspecialchars($panel['name']) ?></h2>
                    <?php if (empty($data)): ?>
                    <p class="text-[10px] text-slate-400 italic uppercase tracking-wider">Non renseigné</p>
                    <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($data as $key => $value): ?>
                        <?php if ($value === null || $value === '') continue; ?>
                        <div><p class="text-[7px] font-black text-slate-400 uppercase mb-1"><?= htmlspecialchars(is_string($key) ? $key : 'Champ') ?></p><p class="text-[11px] font-bold text-slate-900"><?= nl2br(htmlspecialchars(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value)) ?></p></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>
