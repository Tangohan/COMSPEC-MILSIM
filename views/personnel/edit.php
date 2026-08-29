<?php
$targetUser = $targetUser ?? null;
$personnelProfile = $personnelProfile ?? null;
$units = $units ?? [];
$userProfile = $userProfile ?? [];
$currentGrade = $currentGrade ?? null;
$completeness = $completeness ?? ['score' => 0, 'missing_labels' => []];
$matriculeDisplay = $matriculeDisplay ?? null;
$personnelAssignments = $personnelAssignments ?? [];
$currentUnitAssignments = $currentUnitAssignments ?? $personnelAssignments;
$dossierPresets = $dossierPresets ?? [];
$jobRolesEnabled = $jobRolesEnabled ?? false;
$jobRoleOptions = $jobRoleOptions ?? [];
$jobRoleSlugToId = $jobRoleSlugToId ?? [];
$maxUnitAssignmentsPerMember = (int) ($maxUnitAssignmentsPerMember ?? 8);
$forumQuickMode = $forumQuickMode ?? '';
$forumFocus = $forumFocus ?? '';
$forumPreHideLevel = !empty($forumPreHideLevel);
$forumOrgRoleChoices = is_array($forumOrgRoleChoices ?? null) ? $forumOrgRoleChoices : [];
$memberCanChooseDisplayRole = !empty($memberCanChooseDisplayRole);
$roleplayFollowupConfig = is_array($roleplayFollowupConfig ?? null) ? $roleplayFollowupConfig : ['enabled' => false, 'stages' => [], 'recruitment_tracks' => []];
$rpTutorChoices = is_array($rpTutorChoices ?? null) ? $rpTutorChoices : [];
$roleplayEventTypes = is_array($roleplayEventTypes ?? null) ? $roleplayEventTypes : ['administratif'];

$isMe = (int) ($targetUser['id'] ?? 0) === (int) (\App\Core\Session::get('user_id'));
$formAction = url('personnel/' . (int) ($targetUser['id'] ?? 0) . '/update');
if (!$targetUser) {
    echo '<div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">';
    echo '<p class="font-semibold">Fiche introuvable</p>';
    echo '<p class="mt-1">Cette fiche personnel n’est plus accessible. <a class="underline font-semibold" href="' . htmlspecialchars(url('personnel'), ENT_QUOTES, 'UTF-8') . '">Retour à l’annuaire</a></p>';
    echo '</div>';
    return;
}
$p = $personnelProfile ?? [];
$up = is_array($userProfile) ? $userProfile : [];
$d = $displaySettings ?? [];
$clearanceOptions = is_array($clearanceLevelOptions ?? null) ? $clearanceLevelOptions : [];
$currentClearance = trim((string) ($p['clearance_level'] ?? ''));
$clearanceReviewedAt = '';
if (!empty($p['clearance_reviewed_at'])) {
    $cr = date_create((string) $p['clearance_reviewed_at']);
    $clearanceReviewedAt = $cr ? $cr->format('Y-m-d') : '';
}
$readinessScoreVal = isset($p['readiness_score']) ? (int) $p['readiness_score'] : 0;
$score = (int) ($completeness['score'] ?? 0);
$missingLabels = $completeness['missing_labels'] ?? [];
$enlistmentDateVal = '';
if (!empty($p['enlistment_date'])) {
    $enlistmentDateVal = substr((string) $p['enlistment_date'], 0, 10);
}
$prePlatformDateVal = '';
if (!empty($seniorityPrePlatformDate)) {
    $prePlatformDateVal = substr((string) $seniorityPrePlatformDate, 0, 10);
}
$gradeLabel = '';
if ($currentGrade) {
    $gradeLabel = trim((string) ($currentGrade['label_short'] ?? $currentGrade['short_name'] ?? $currentGrade['label_long'] ?? $currentGrade['name'] ?? ''));
}
$bloodOptions = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Inconnu'];
$rpStages = is_array($roleplayFollowupConfig['stages'] ?? null) ? $roleplayFollowupConfig['stages'] : [];
$rpTracks = is_array($roleplayFollowupConfig['recruitment_tracks'] ?? null) ? $roleplayFollowupConfig['recruitment_tracks'] : [];
$rpOriginSel = trim((string) ($p['rp_recruitment_origin'] ?? ''));
$nicknames = is_array($nicknames ?? null) ? $nicknames : [];
$medalRackItems = is_array($medalRackItems ?? null) ? $medalRackItems : [];
$nicknamesText = implode("\n", array_map(static fn ($item) => trim((string) $item), $nicknames));
$medalRackText = implode("\n", array_map(static fn ($item) => trim((string) $item), $medalRackItems));
$advancedEditActive = !empty($advancedEditActive);
$athenaIdDisplay = trim((string) ($targetUser['athena_identifier'] ?? ''));

$editNavGroups = [
    [
        'title' => 'Qui êtes-vous',
        'items' => [
            ['id' => 'edit-compte', 'label' => 'Compte &amp; interface', 'show' => $isMe],
            ['id' => 'edit-identite-rp', 'label' => 'Personnage (RP)', 'show' => true],
        ],
    ],
    [
        'title' => 'Affectation',
        'items' => [
            ['id' => 'edit-orbat', 'label' => 'Unité &amp; rôle', 'show' => true],
            ['id' => 'edit-habilitation', 'label' => 'Habilitation', 'show' => true],
            ['id' => 'edit-suivi-immersion', 'label' => 'Suivi immersion', 'show' => !empty($roleplayFollowupConfig['enabled'])],
        ],
    ],
    [
        'title' => 'Affichage &amp; suite',
        'items' => [
            ['id' => 'forum-community-settings', 'label' => 'Forum &amp; fiche', 'show' => true],
            ['id' => 'edit-equipement', 'label' => 'Équipement', 'show' => true],
            ['id' => 'edit-notes', 'label' => 'Notes commandement', 'show' => true],
        ],
    ],
];
$editNavFlat = [];
foreach ($editNavGroups as $grp) {
    foreach ($grp['items'] as $ni) {
        if (!empty($ni['show'])) {
            $editNavFlat[] = $ni;
        }
    }
}
$editDefaultTab = $editNavFlat[0]['id'] ?? '';
$editValidTabIds = implode(',', array_map(
    static fn ($ni) => "'" . addslashes((string) $ni['id']) . "'",
    $editNavFlat
));
?>
<div class="pd-page" x-data="{ tab: '<?= htmlspecialchars($editDefaultTab, ENT_QUOTES, 'UTF-8') ?>' }" x-init="const h = window.location.hash.slice(1); if ([<?= $editValidTabIds ?>].includes(h)) { tab = h }; $watch('tab', v => { if (v) history.replaceState(null, '', '#' + v) })">
  <div class="pd-container">
    <header class="pd-header">
      <div>
        <p class="pd-header__eyebrow">Dossier personnel</p>
        <h1 class="pd-header__title">Éditer le dossier<?= trim((string) ($targetUser['display_name'] ?? '')) !== '' ? ' — ' . htmlspecialchars(trim((string) $targetUser['display_name']), ENT_QUOTES, 'UTF-8') : '' ?></h1>
        <p class="pd-header__sub">Identité, affectation, immersion et affichage — un onglet à la fois, comme un tableau de bord administratif.</p>
      </div>
      <div class="pd-header__actions">
        <a href="<?= $isMe ? url('personnel/me') : url('personnel/' . (int) $targetUser['id']) ?>" class="pd-btn">← Fiche</a>
        <a href="<?= url('account/preferences') ?>" class="pd-btn">Préférences</a>
        <a href="<?= url('account/portrait') ?>" class="pd-btn">Portrait</a>
        <a href="<?= htmlspecialchars(url('personnel/tutorials')) ?>" class="pd-btn">Tutoriels</a>
      </div>
    </header>

    <?php $success = \App\Core\Session::getFlash('success'); if ($success): ?>
    <div class="pd-alert pd-alert--ok" role="status"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php $error = \App\Core\Session::getFlash('error'); if ($error): ?>
    <div class="pd-alert pd-alert--err" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="pd-progress" role="group" aria-label="Complétude du dossier">
      <div class="pd-progress__meta">
        <p class="pd-progress__label">Complétude</p>
        <p class="pd-progress__value"><?= $score ?>%</p>
        <?php if (!empty($missingLabels)): ?>
        <p class="pd-progress__hint"><strong>À compléter :</strong> <?= htmlspecialchars(implode(' · ', array_slice($missingLabels, 0, 8))) ?><?= count($missingLabels) > 8 ? '…' : '' ?></p>
        <?php endif; ?>
      </div>
      <div class="pd-progress__bar" aria-hidden="true"><span style="width: <?= min(100, max(0, $score)) ?>%"></span></div>
    </div>

    <div class="pd-card">
      <nav class="pd-tabs" aria-label="Sections du dossier">
        <?php foreach ($editNavFlat as $ni): ?>
        <button
          type="button"
          class="pd-tabs__btn"
          :class="tab === '<?= htmlspecialchars($ni['id'], ENT_QUOTES, 'UTF-8') ?>' ? 'is-active' : ''"
          @click="tab = '<?= htmlspecialchars($ni['id'], ENT_QUOTES, 'UTF-8') ?>'"
        ><?= htmlspecialchars(str_replace('&amp;', '&', $ni['label']), ENT_QUOTES, 'UTF-8') ?></button>
        <?php endforeach; ?>
      </nav>

      <?php if ($advancedEditActive): ?>
      <div class="mb-4 rounded-xl border border-violet-300 bg-violet-50 px-4 py-3 text-sm text-violet-950">
        <p class="font-bold">Mode édition avancée actif</p>
        <p class="mt-1 text-xs leading-relaxed text-violet-900/90">
          Vous pouvez modifier l’ensemble de la fiche (y compris habilitation et matricule).
          L’identifiant Athena reste verrouillé.
          <?php
            $afeEnds = is_array($advancedEditGrant ?? null) ? (string) ($advancedEditGrant['ends_at'] ?? '') : '';
            if ($afeEnds !== '') {
                echo ' Expire le ' . htmlspecialchars(date('d/m/Y à H:i', strtotime($afeEnds)), ENT_QUOTES, 'UTF-8') . '.';
            }
          ?>
        </p>
      </div>
      <?php endif; ?>

      <form method="post" action="<?= htmlspecialchars($formAction) ?>">
        <?= \App\Core\Csrf::field() ?>
        <div class="pd-card__body">

        <?php if ($isMe): ?>
        <div x-cloak x-show="tab === 'edit-compte'">
        <section id="edit-compte" class="scroll-mt-24 overflow-hidden rounded-2xl border border-indigo-200/80 bg-white shadow-sm ring-1 ring-indigo-900/[0.06]">
          <div class="border-b border-indigo-100 bg-indigo-50/80 px-6 py-5">
            <h2 class="text-base font-black tracking-tight text-indigo-950">Compte &amp; interface</h2>
            <p class="mt-1.5 max-w-2xl text-xs leading-relaxed text-indigo-900/85">Fuseau horaire et langue de l’interface. Le prénom, le nom et la présentation du personnage se règlent dans l’onglet Personnage.</p>
          </div>
          <div class="space-y-8 p-6 sm:p-8">
            <div>
              <h3 class="mb-4 border-b border-indigo-100 pb-2 text-xs font-black uppercase tracking-wider text-indigo-900/70">Préférences d’interface</h3>
              <div class="grid gap-4 sm:grid-cols-2">
                <div>
                  <label for="civil_timezone" class="mb-1 block text-xs font-bold text-slate-600">Fuseau horaire</label>
                  <input type="text" name="civil_timezone" id="civil_timezone" value="<?= htmlspecialchars((string) ($up['timezone'] ?? '')) ?>" placeholder="Europe/Paris" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="50">
                </div>
                <div>
                  <label for="civil_language" class="mb-1 block text-xs font-bold text-slate-600">Langue de l’interface</label>
                  <input type="text" name="civil_language" id="civil_language" value="<?= htmlspecialchars((string) ($up['language'] ?? '')) ?>" placeholder="fr" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="10">
                  <p class="mt-1 text-[11px] text-slate-500">Ex. fr ou en. Vous pouvez aussi le régler dans les préférences du compte.</p>
                </div>
              </div>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50/60 px-4 py-3">
              <label class="flex items-start gap-3 text-sm text-slate-800">
                <input type="checkbox" name="hide_personal_info" value="1" class="mt-1 rounded border-slate-300 text-amber-900" <?= !empty($d['hide_personal_info']) ? 'checked' : '' ?>>
                <span><strong>Masquer le fuseau et la langue</strong> sur la fiche publique. Seuls les <strong>administrateurs</strong> (accès fiche personnel) et les <strong>modérateurs forum</strong> pourront les consulter. Le prénom, le nom et la présentation du personnage restent visibles comme le reste du dossier de jeu.</span>
              </label>
            </div>
          </div>
        </section>
        </div>
        <?php endif; ?>

        <div x-cloak x-show="tab === 'edit-identite-rp'">
        <section id="edit-identite-rp" class="scroll-mt-24 overflow-hidden rounded-2xl border border-emerald-200/80 bg-white shadow-sm ring-1 ring-emerald-900/[0.06]">
          <div class="border-b border-emerald-100 bg-emerald-50/70 px-6 py-5">
            <h2 class="text-base font-black tracking-tight text-emerald-950">Personnage (identité RP)</h2>
            <p class="mt-1.5 max-w-2xl text-xs leading-relaxed text-emerald-900/85">Prénom, nom et présentation du personnage, puis indicatif et détails de jeu. L’unité et le rôle métier se règlent dans le bloc suivant.</p>
          </div>
          <div class="space-y-8 p-6 sm:p-8">
            <div>
              <h3 class="mb-4 border-b border-emerald-100 pb-2 text-xs font-black uppercase tracking-wider text-emerald-900/70">Identité en jeu</h3>
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label for="rp_first_name" class="mb-1 block text-xs font-bold text-slate-600">Prénom</label>
                  <input type="text" name="rp_first_name" id="rp_first_name" value="<?= htmlspecialchars((string) ($up['first_name'] ?? '')) ?>" placeholder="Obligatoire" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100" autocomplete="off">
                  <p class="mt-1 text-[11px] text-slate-500">Prénom du personnage — identité unique (dossier, annuaire, forum).</p>
                </div>
                <div>
                  <label for="rp_last_name" class="mb-1 block text-xs font-bold text-slate-600">Nom</label>
                  <input type="text" name="rp_last_name" id="rp_last_name" value="<?= htmlspecialchars((string) ($up['last_name'] ?? '')) ?>" placeholder="Obligatoire" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100" autocomplete="off">
                  <p class="mt-1 text-[11px] text-slate-500">Nom du personnage — utilisé partout (dossier, annuaire, forum).</p>
                </div>
                <div class="md:col-span-2">
                  <label for="rp_bio" class="mb-1 block text-xs font-bold text-slate-600">Présentation du personnage</label>
                  <textarea name="rp_bio" id="rp_bio" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" placeholder="Quelques mots sur le personnage, son parcours, son ton…"><?= htmlspecialchars((string) ($up['bio'] ?? '')) ?></textarea>
                </div>
                <div>
                  <label for="callsign" class="mb-1 block text-xs font-bold text-slate-600">Indicatif</label>
                  <input type="text" name="callsign" id="callsign" value="<?= htmlspecialchars($p['callsign'] ?? $targetUser['callsign'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
                  <p class="mt-1 text-[11px] text-slate-500">Surnom radio / callsign utilisé en mission.</p>
                </div>
                <div>
                  <label for="motto" class="mb-1 block text-xs font-bold text-slate-600">Devise</label>
                  <input type="text" name="motto" id="motto" value="<?= htmlspecialchars((string) ($p['motto'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="255">
                </div>
                <div>
                  <label for="nickname_primary" class="mb-1 block text-xs font-bold text-slate-600">Surnom principal</label>
                  <input type="text" name="nickname_primary" id="nickname_primary" value="<?= htmlspecialchars((string) ($p['nickname_primary'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="120" placeholder="Ex. Le Renard">
                  <p class="mt-1 text-[11px] text-slate-500">Nom court complémentaire au nom du personnage ou à l’indicatif.</p>
                </div>
                <div>
                  <label for="nicknames_text" class="mb-1 block text-xs font-bold text-slate-600">Autres surnoms</label>
                  <textarea name="nicknames_text" id="nicknames_text" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" placeholder="Un surnom par ligne"><?= htmlspecialchars($nicknamesText) ?></textarea>
                  <p class="mt-1 text-[11px] text-slate-500">Pratique pour les variantes radio, sobriquets d’unité ou noms d’usage.</p>
                </div>
              </div>
            </div>
            <div>
              <h3 class="mb-4 border-b border-emerald-100 pb-2 text-xs font-black uppercase tracking-wider text-emerald-900/70">Grades &amp; fonctions affichés</h3>
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label for="rank_display" class="mb-1 block text-xs font-bold text-slate-600">Grade ou titre (optionnel)</label>
                  <input type="text" name="rank_display" id="rank_display" value="<?= htmlspecialchars((string) ($p['rank_display'] ?? '')) ?>" placeholder="Sous-lieutenant, Chief…" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
                  <?php if ($gradeLabel !== ''): ?>
                  <p class="mt-1 text-[11px] text-slate-500">Grade attribué par la communauté : <strong class="text-slate-700"><?= htmlspecialchars($gradeLabel) ?></strong></p>
                  <?php endif; ?>
                  <p class="mt-1 text-[11px] text-slate-500">Affiché en haut du site à la place du libellé de communauté, s’il est renseigné.</p>
                </div>
                <div>
                  <label for="rank_display_override" class="mb-1 block text-xs font-bold text-slate-600">Libellé court personnalisé (optionnel)</label>
                  <input type="text" name="rank_display_override" id="rank_display_override" value="<?= htmlspecialchars((string) ($p['rank_display_override'] ?? '')) ?>" placeholder="O-5, OF-4…" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
                  <p class="mt-1 text-[11px] text-slate-500">Remplace le code affiché à côté du grade en haut du site (par exemple O-5 à la place de OF-4).</p>
                </div>
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 px-4 py-3 text-[11px] leading-relaxed text-emerald-950/90 md:col-span-2 flex items-center">
                  <?= $jobRolesEnabled
                    ? 'Le <strong>rôle métier</strong> (principal et complémentaires, référentiel de la communauté) se choisit dans le bloc <button type="button" class="font-bold underline underline-offset-2" @click="tab = \'edit-orbat\'">Unité &amp; rôle</button> ci-dessous.'
                    : 'Le <strong>rôle dans l’unité</strong> se renseigne dans le bloc <button type="button" class="font-bold underline underline-offset-2" @click="tab = \'edit-orbat\'">Unité &amp; rôle</button> ci-dessous.' ?>
                </div>
              </div>
            </div>
            <div>
              <h3 class="mb-4 border-b border-emerald-100 pb-2 text-xs font-black uppercase tracking-wider text-emerald-900/70">Détails du personnage</h3>
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label for="languages" class="mb-1 block text-xs font-bold text-slate-600">Langues (en jeu)</label>
                  <input type="text" name="languages" id="languages" value="<?= htmlspecialchars((string) ($p['languages'] ?? '')) ?>" placeholder="FR, EN…" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="255">
                </div>
                <div>
                  <label for="nationality_rp" class="mb-1 block text-xs font-bold text-slate-600">Nationalité (personnage)</label>
                  <input type="text" name="nationality_rp" id="nationality_rp" value="<?= htmlspecialchars((string) ($p['nationality'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
                  <p class="mt-1 text-[11px] text-slate-500">Nationalité du personnage en jeu.</p>
                </div>
                <div>
                  <label for="public_flag_country_code" class="mb-1 block text-xs font-bold text-slate-600">Drapeau sur la fiche</label>
                  <select name="public_flag_country_code" id="public_flag_country_code" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                    <?php
                    $flagCur = strtoupper(trim((string) ($up['public_flag_country_code'] ?? '')));
                    foreach (\App\Support\Profile\PublicFlagCountryCatalog::optionsForSelect() as $code => $label) {
                        $sel = ($flagCur === strtoupper((string) $code)) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    if ($flagCur !== '' && !\App\Support\Profile\PublicFlagCountryCatalog::isAllowed($flagCur)) {
                        echo '<option value="' . htmlspecialchars($flagCur, ENT_QUOTES, 'UTF-8') . '" selected>(code inconnu : ' . htmlspecialchars($flagCur, ENT_QUOTES, 'UTF-8') . ')</option>';
                    }
                    ?>
                  </select>
                  <p class="mt-1 text-[11px] leading-relaxed text-slate-500">Optionnel : fond du portrait en tête de fiche. Choisissez « Ne pas afficher » pour un fond neutre.</p>
                </div>
                <div>
                  <label for="blood_type" class="mb-1 block text-xs font-bold text-slate-600">Groupe sanguin</label>
                  <select name="blood_type" id="blood_type" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                    <?php
                    $bt = trim((string) ($p['blood_type'] ?? ''));
                    foreach ($bloodOptions as $bo) {
                        $sel = ($bt === $bo || ($bo === '' && $bt === '')) ? ' selected' : '';
                        $lab = $bo === '' ? '— Non renseigné —' : $bo;
                        echo '<option value="' . htmlspecialchars($bo) . '"' . $sel . '>' . htmlspecialchars($lab) . '</option>';
                    }
                    if ($bt !== '' && !in_array($bt, $bloodOptions, true)) {
                        echo '<option value="' . htmlspecialchars($bt) . '" selected>' . htmlspecialchars($bt) . '</option>';
                    }
                    $armaBt = trim((string) ($p['rp_arma_blood_type'] ?? ''));
                    ?>
                  </select>
                  <?php if ($armaBt !== '' && $armaBt !== $bt): ?>
                  <p class="mt-1 text-[11px] leading-relaxed text-amber-800">En jeu actuellement : <?= htmlspecialchars($armaBt) ?>. À confirmer lors du prochain bilan médical.</p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div>
              <label for="sex" class="mb-1 block text-xs font-bold text-slate-600">Sexe</label>
              <select name="sex" id="sex" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                <?php
                $sexCur = trim((string) ($p['sex'] ?? ''));
                foreach (['' => '— Non renseigné —', 'Homme' => 'Homme', 'Femme' => 'Femme', 'Autre' => 'Autre'] as $sv => $sl) {
                    $sel = ($sexCur === $sv) ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($sv) . '"' . $sel . '>' . htmlspecialchars($sl) . '</option>';
                }
                if ($sexCur !== '' && !in_array($sexCur, ['Homme', 'Femme', 'Autre'], true)) {
                    echo '<option value="' . htmlspecialchars($sexCur) . '" selected>' . htmlspecialchars($sexCur) . '</option>';
                }
                ?>
              </select>
            </div>
            <div>
              <label for="birth_place" class="mb-1 block text-xs font-bold text-slate-600">Lieu de naissance (RP)</label>
              <input type="text" name="birth_place" id="birth_place" value="<?= htmlspecialchars((string) ($p['birth_place'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="150">
            </div>
            <div>
              <label for="family_situation" class="mb-1 block text-xs font-bold text-slate-600">Situation familiale (RP)</label>
              <input type="text" name="family_situation" id="family_situation" value="<?= htmlspecialchars((string) ($p['family_situation'] ?? '')) ?>" placeholder="Célibataire, marié(e)…" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
            </div>
            <div>
              <label for="weight_kg" class="mb-1 block text-xs font-bold text-slate-600">Poids (kg, RP)</label>
              <input type="number" name="weight_kg" id="weight_kg" value="<?= htmlspecialchars((string) ($p['weight_kg'] ?? '')) ?>" min="20" max="300" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
            </div>
            <div>
              <label for="operator_status" class="mb-1 block text-xs font-bold text-slate-600">Statut opérateur</label>
              <input type="text" name="operator_status" id="operator_status" value="<?= htmlspecialchars((string) ($p['operator_status'] ?? '')) ?>" placeholder="Ex. Opérateur Leader // Senior Instructor" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="160">
            </div>
            <div class="md:col-span-2">
              <label for="operator_tags" class="mb-1 block text-xs font-bold text-slate-600">Spécialités / tags (RP)</label>
              <input type="text" name="operator_tags" id="operator_tags" value="<?= htmlspecialchars((string) ($p['operator_tags'] ?? '')) ?>" placeholder="Ex. Breacher / Team Lead / Squad Lead" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="255">
            </div>
          </div>
        </section>
        </div>

        <div x-cloak x-show="['edit-orbat','edit-habilitation','edit-suivi-immersion'].includes(tab)">
        <section id="edit-orbat" x-show="tab === 'edit-orbat'" class="scroll-mt-24 overflow-hidden rounded-2xl border border-cyan-200/90 bg-white shadow-sm ring-1 ring-cyan-900/[0.04]">
          <div class="border-b border-cyan-100 bg-cyan-50/70 px-6 py-5">
            <h2 class="text-base font-black tracking-tight text-cyan-950">Unité &amp; rôle</h2>
            <p class="mt-1.5 max-w-2xl text-xs leading-relaxed text-cyan-900/85">Vous pouvez renseigner une affectation principale et des affectations complémentaires. La principale sert de référence pour le dossier, la fiche et le forum.</p>
          </div>
          <div class="space-y-4 p-6">
            <?php if (!empty($personnelAssignments)): ?>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
              <table class="min-w-full text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-600">
                  <tr>
                    <th class="px-3 py-2">Unité</th>
                    <th class="px-3 py-2">Rôle affectation</th>
                    <th class="px-3 py-2">Principal</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php foreach ($personnelAssignments as $pa): ?>
                  <tr class="bg-white">
                    <td class="px-3 py-2 font-semibold text-slate-900"><?= htmlspecialchars((string) ($pa['unit_name'] ?? '—')) ?></td>
                    <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($pa['role_name'] ?? '—')) ?></td>
                    <td class="px-3 py-2"><?= !empty($pa['is_primary']) ? '<span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-900">Oui</span>' : '—' ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
            <p class="rounded-xl border border-amber-200 bg-amber-50/80 px-3 py-2 text-xs text-amber-950">Aucune affectation active. Ajoutez au moins une unité ci-dessous si la personne doit apparaître dans l’organigramme.</p>
            <?php endif; ?>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="md:col-span-2">
                <?php
                $unitAssignmentsSeed = [];
                foreach ($currentUnitAssignments as $idx => $assignmentRow) {
                    $unitAssignmentsSeed[] = [
                        'unit_id' => (int) ($assignmentRow['unit_id'] ?? 0),
                        'role_name' => (string) ($assignmentRow['role_name'] ?? ''),
                        'is_primary' => !empty($assignmentRow['is_primary']),
                    ];
                }
                if ($unitAssignmentsSeed === [] && !empty($p['primary_unit_id'])) {
                    $unitAssignmentsSeed[] = [
                        'unit_id' => (int) $p['primary_unit_id'],
                        'role_name' => '',
                        'is_primary' => true,
                    ];
                }
                $unitOptionsJson = htmlspecialchars(json_encode(array_map(static function (array $u): array {
                    return [
                        'id' => (int) ($u['id'] ?? 0),
                        'name' => (string) ($u['name'] ?? ''),
                    ];
                }, $units), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');
                $currentUnitAssignmentsJson = htmlspecialchars(json_encode($unitAssignmentsSeed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');
                ?>
                <div x-data="personnelUnitAssignmentsEditor(<?= $currentUnitAssignmentsJson ?>, <?= $unitOptionsJson ?>, <?= (int) $maxUnitAssignmentsPerMember ?>)" class="space-y-3">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <label class="mb-1 block text-xs font-bold text-slate-600">Affectations d’unité</label>
                      <p class="text-[11px] text-slate-500">Renseignez une ou plusieurs lignes. Cochez l’affectation principale pour indiquer celle qui sert de référence partout sur le portail.</p>
                    </div>
                    <button type="button" class="rounded-lg border border-dashed border-cyan-300 px-3 py-1.5 text-xs font-semibold text-cyan-800 hover:bg-cyan-50" @click="addRow()" x-show="rows.length < maxRows">Ajouter une affectation</button>
                  </div>
                  <?php if (empty($units)): ?>
                  <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                    Aucune unité : créez la structure dans l’<a class="font-semibold underline" href="<?= htmlspecialchars(url('orbat')) ?>">organigramme</a>.
                  </p>
                  <?php endif; ?>
                  <input type="hidden" name="primary_unit_id" :value="primaryUnitId()">
                  <template x-for="(row, idx) in rows" :key="row.key">
                    <div class="rounded-2xl border border-cyan-200 bg-cyan-50/30 p-4">
                      <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <label class="flex shrink-0 items-center gap-2 text-xs font-bold text-slate-700">
                          <input type="radio" name="unit_assignments_primary" :value="idx" x-model.number="primaryIdx" class="text-emerald-600">
                          Affectation principale
                        </label>
                        <div class="min-w-[220px] flex-1">
                          <label class="mb-1 block text-[11px] font-bold text-slate-600">Unité</label>
                          <select :name="'unit_assignments[' + idx + '][unit_id]'" x-model.number="row.unit_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm">
                            <option value="0">— Aucune —</option>
                            <template x-for="unit in unitOptions" :key="unit.id">
                              <option :value="unit.id" x-text="unit.name"></option>
                            </template>
                          </select>
                        </div>
                        <div class="min-w-[220px] flex-1">
                          <label class="mb-1 block text-[11px] font-bold text-slate-600">Rôle dans l’unité</label>
                          <input type="text" :name="'unit_assignments[' + idx + '][role_name]'" x-model="row.role_name" maxlength="120" placeholder="Ex. Chef d’équipe, tireur, appui…" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm">
                        </div>
                        <button type="button" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50" @click="removeRow(idx)" x-show="rows.length > 1">Retirer</button>
                      </div>
                    </div>
                  </template>
                </div>
              </div>
              <div class="md:col-span-2 space-y-3" id="job_roles_editor">
                <?php if ($jobRolesEnabled): ?>
                <?php
                $jobRoleOptionsJson = htmlspecialchars(json_encode($jobRoleOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');
                $currentJobRolesJson = htmlspecialchars(json_encode($currentJobRoles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');
                ?>
                <div x-data="personnelJobRolesEditor(<?= $currentJobRolesJson ?>, <?= $jobRoleOptionsJson ?>, <?= (int) $maxJobRolesPerMember ?>)">
                  <label class="mb-1 block text-xs font-bold text-slate-600">Rôle(s) métier (référentiel)</label>
                  <div class="space-y-2">
                    <template x-for="(row, idx) in roles" :key="row.key">
                      <div class="flex flex-col gap-2 rounded-xl border border-cyan-200 bg-white p-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <label class="flex shrink-0 items-center gap-1.5 text-[10px] font-bold text-slate-600">
                          <input type="radio" name="job_roles_primary" :value="idx" x-model.number="primaryIdx" class="text-emerald-600">
                          Principal
                        </label>
                        <div class="min-w-[220px] flex-1">
                          <label class="mb-0.5 block text-[10px] font-bold uppercase text-slate-500">Emploi</label>
                          <select :name="'job_roles[' + idx + '][role_id]'" x-model.number="row.role_id" class="w-full rounded-lg border border-cyan-200 bg-white px-2.5 py-2 text-xs shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20">
                            <option value="0">— Non renseigné —</option>
                            <template x-for="opt in jobRoleOptions" :key="opt.id">
                              <option :value="opt.id" x-text="opt.label"></option>
                            </template>
                          </select>
                        </div>
                        <div class="min-w-[160px] flex-1">
                          <label class="mb-0.5 block text-[10px] font-bold uppercase text-slate-500">Précision</label>
                          <input type="text" :name="'job_roles[' + idx + '][detail]'" x-model="row.detail" maxlength="150" placeholder="Optionnel" class="w-full rounded-lg border border-cyan-200 px-2.5 py-2 text-xs shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20">
                        </div>
                        <button type="button" class="shrink-0 rounded-lg border border-rose-200 px-2.5 py-2 text-[10px] font-bold text-rose-700 hover:bg-rose-50" @click="removeRow(idx)" x-show="roles.length > 1">Retirer</button>
                      </div>
                    </template>
                    <button type="button" class="rounded-lg border border-dashed border-cyan-300 px-3 py-1.5 text-xs font-semibold text-cyan-800 hover:bg-cyan-50" @click="addRow()" x-show="roles.length < maxRoles">Ajouter un rôle</button>
                  </div>
                  <p class="mt-1 text-[11px] text-slate-600">Le rôle coché « Principal » sert de référence pour le dossier, l’organigramme et le forum. Les autres sont affichés comme rôles complémentaires.</p>
                </div>
                <?php else: ?>
                <p class="rounded-xl border border-amber-200 bg-amber-50/80 px-3 py-2 text-xs text-amber-950">Référentiel de rôles métier non disponible sur cet environnement (migration à exécuter).</p>
                <?php endif; ?>
              </div>
            </div>
            <?php if (!empty($dossierPresets)): ?>
            <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/50 p-4">
              <p class="text-[10px] font-black uppercase tracking-wider text-emerald-900">Presets de fonction</p>
              <p class="mt-1 text-xs text-emerald-950/90">Remplit le rôle ci-dessus et les suggestions d’équipement (section équipement). Choisissez l’unité vous-même. <a href="<?= htmlspecialchars(url('personnel/tutorials')) ?>" class="font-bold underline">Guide</a>.</p>
              <div class="mt-3 flex flex-wrap gap-2">
                <?php foreach ($dossierPresets as $pr): ?>
                <button type="button" class="personnel-preset-btn rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-left text-[11px] font-bold text-emerald-950 shadow-sm transition hover:border-emerald-500 hover:bg-emerald-50" data-preset-id="<?= htmlspecialchars((string) ($pr['id'] ?? '')) ?>" title="<?= htmlspecialchars((string) ($pr['description'] ?? '')) ?>">
                  <?= htmlspecialchars((string) ($pr['label'] ?? '')) ?>
                </button>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
            <p class="text-[11px] text-slate-500">
              <a href="<?= htmlspecialchars(url('orbat')) ?>" class="font-semibold text-cyan-800 underline-offset-2 hover:underline">Voir l’organigramme</a>
              — Vue d’ensemble des unités ; les affectations détaillées peuvent aussi être gérées par le personnel habilité.
            </p>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="assignment_change_reason" class="mb-1 block text-xs font-bold text-slate-600">Motif du changement d’affectation</label>
                <input type="text" name="assignment_change_reason" id="assignment_change_reason" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="255" placeholder="Ex. Renfort section Alfa, rotation trimestrielle">
                <p class="mt-1 text-[11px] text-slate-500">Ajoute un motif lisible dans l’historique si l’unité principale change.</p>
              </div>
              <div>
                <label for="job_role_change_reason" class="mb-1 block text-xs font-bold text-slate-600">Motif du changement de fonction</label>
                <input type="text" name="job_role_change_reason" id="job_role_change_reason" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="255" placeholder="Ex. Validation stage leader, besoin de cellule appui">
                <p class="mt-1 text-[11px] text-slate-500">Ajoute un motif lisible dans l’historique si la fonction principale change.</p>
              </div>
            </div>
          </div>
        </section>

        <section id="edit-habilitation" x-show="tab === 'edit-habilitation'" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
          <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-5">
            <h2 class="text-base font-black tracking-tight text-slate-900">Habilitation &amp; disponibilité</h2>
            <p class="mt-1.5 max-w-2xl text-xs leading-relaxed text-slate-600">Niveau d’accès, dates et indicateur de disponibilité pour le dossier.</p>
          </div>
          <div class="grid gap-4 p-6 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-bold text-slate-600">Niveau de clearance</label>
              <?php if ($advancedEditActive): ?>
              <select name="clearance_level" id="clearance_level" class="w-full rounded-xl border border-violet-200 bg-violet-50/40 px-3 py-2.5 text-sm">
                <option value="">— Non défini —</option>
                <?php foreach ($clearanceOptions as $ck => $clabel): ?>
                <option value="<?= htmlspecialchars((string) $ck) ?>" <?= $currentClearance === (string) $ck ? 'selected' : '' ?>><?= htmlspecialchars((string) $clabel) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="mt-1 text-[11px] text-violet-700">Déverrouillé par le mode édition avancée (24 h). L’ID Athena reste inchangé.</p>
              <?php else: ?>
              <p class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                <?= $currentClearance !== '' ? htmlspecialchars($clearanceOptions[$currentClearance] ?? ($currentClearance . ' (valeur héritée)')) : '— Non défini —' ?>
              </p>
              <p class="mt-1 text-[11px] text-slate-500">
                Se modifie via une demande d’élévation, examinée par une personne habilitée — pas directement ici, ce niveau conditionnant l’accès aux documents classifiés.
                <?php if (!empty($targetUser['id'])): ?>
                <a href="<?= htmlspecialchars(effectifs_workspace_url('membres/' . (int) $targetUser['id']), ENT_QUOTES, 'UTF-8') ?>" class="font-bold text-emerald-700 hover:underline">Ouvrir la fiche effectifs →</a>
                <?php endif; ?>
              </p>
              <?php endif; ?>
            </div>
            <div>
              <label for="enlistment_date" class="mb-1 block text-xs font-bold text-slate-600">Date d’incorporation</label>
              <input type="date" name="enlistment_date" id="enlistment_date" value="<?= htmlspecialchars($enlistmentDateVal) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
              <p class="mt-1 text-[11px] text-slate-500">Entrée dans la communauté sur la plateforme (ou date d’enrôlement retenue pour le dossier).</p>
            </div>
            <div>
              <label for="pre_platform_start_date" class="mb-1 block text-xs font-bold text-slate-600">Ancienneté antérieure à la plateforme</label>
              <input type="date" name="pre_platform_start_date" id="pre_platform_start_date" value="<?= htmlspecialchars($prePlatformDateVal) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
              <p class="mt-1 text-[11px] text-slate-500">Date à laquelle la personne a rejoint l’entité <em>avant</em> l’ouverture du site. Laisser vide si non applicable.</p>
            </div>
            <div>
              <label for="clearance_reviewed_at" class="mb-1 block text-xs font-bold text-slate-600">Dernière revue d’habilitation</label>
              <input type="date" name="clearance_reviewed_at" id="clearance_reviewed_at" value="<?= htmlspecialchars($clearanceReviewedAt) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
            </div>
            <div>
              <label for="readiness_score" class="mb-1 block text-xs font-bold text-slate-600">Indicateur de disponibilité (0–100)</label>
              <input type="number" name="readiness_score" id="readiness_score" min="0" max="100" value="<?= $readinessScoreVal ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
              <p class="mt-1 text-[11px] text-slate-500">Compte pour la complétude si &gt; 0 (sinon une formation certifiante peut suffire).</p>
            </div>
            <div class="md:col-span-2 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
              <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Identifiant plateforme</p>
              <p class="mt-1 font-mono text-sm font-bold text-slate-900"><?= $athenaIdDisplay !== '' ? htmlspecialchars($athenaIdDisplay) : '—' ?></p>
              <p class="mt-1 text-[11px] text-slate-500">Identifiant permanent attribué par la plateforme — non modifiable<?= !empty($advancedEditActive) ? ' (même en mode édition avancée)' : '' ?>.</p>
            </div>
            <?php
            $tmnLabel = trim((string) ($tenantMemberNumberLabel ?? "Matricule d'organisation"));
            $tmnValue = trim((string) ($tenantMemberNumber ?? ''));
            $tmnCanManage = !empty($canManageMemberNumber);
            $tmnEnabled = !empty($tenantMemberNumberEnabled);
            $tmnMode = (string) ($tenantMemberNumberMode ?? 'free');
            $tmnPreview = trim((string) ($tenantMemberNumberPreview ?? ''));
            ?>
            <?php if ($tmnEnabled || $tmnValue !== '' || $tmnCanManage): ?>
            <div class="md:col-span-2 rounded-xl border border-emerald-100 bg-emerald-50/40 px-4 py-3">
              <p class="text-[10px] font-black uppercase tracking-wider text-emerald-800"><?= htmlspecialchars($tmnLabel !== '' ? $tmnLabel : "Matricule d'organisation", ENT_QUOTES, 'UTF-8') ?></p>
              <?php if ($tmnCanManage): ?>
              <form method="post" action="<?= htmlspecialchars(url('personnel/' . (int) ($targetUser['id'] ?? 0) . '/member-number'), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 space-y-2">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="return_to" value="edit">
                <input type="text" name="tenant_member_number" maxlength="100"
                       value="<?= htmlspecialchars($tmnValue, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="<?= $tmnPreview !== '' ? htmlspecialchars($tmnPreview, ENT_QUOTES, 'UTF-8') : 'Ex. GEND-0458' ?>"
                       class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2.5 font-mono text-sm">
                <input type="text" name="member_number_reason" maxlength="255" placeholder="Motif (facultatif)"
                       class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2 text-sm">
                <div class="flex flex-wrap gap-2">
                  <button type="submit" class="rounded-xl bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-800">Enregistrer le matricule</button>
                </div>
              </form>
              <?php if (in_array($tmnMode, ['automatic', 'assisted'], true)): ?>
              <form method="post" action="<?= htmlspecialchars(url('personnel/' . (int) ($targetUser['id'] ?? 0) . '/member-number/regenerate'), ENT_QUOTES, 'UTF-8') ?>"
                    class="mt-2"
                    onsubmit="return confirm('Régénérer le matricule d\'organisation ?');">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="confirm_regenerate" value="1">
                <input type="hidden" name="member_number_reason" value="Régénération">
                <button type="submit" class="text-xs font-bold text-amber-800 hover:underline">Régénérer le matricule</button>
              </form>
              <?php endif; ?>
              <?php else: ?>
              <p class="mt-1 font-mono text-sm font-bold text-slate-900"><?= $tmnValue !== '' ? htmlspecialchars($tmnValue, ENT_QUOTES, 'UTF-8') : '— non attribué —' ?></p>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="md:col-span-2 rounded-xl border <?= $advancedEditActive ? 'border-violet-200 bg-violet-50/50' : 'border-slate-100 bg-slate-50/80' ?> px-4 py-3">
              <p class="text-[10px] font-black uppercase tracking-wider <?= $advancedEditActive ? 'text-violet-700' : 'text-slate-500' ?>">Matricule dossier (système)</p>
              <?php if ($advancedEditActive): ?>
              <input type="text" name="matricule_internal" id="matricule_internal" maxlength="64"
                     value="<?= htmlspecialchars((string) ($matriculeDisplay ?? '')) ?>"
                     class="mt-2 w-full rounded-xl border border-violet-200 bg-white px-3 py-2.5 font-mono text-sm"
                     placeholder="Matricule interne">
              <p class="mt-1 text-[11px] text-violet-700">Saisie libre autorisée pendant le mode avancé.</p>
              <?php else: ?>
              <p class="mt-1 font-mono text-sm font-bold text-slate-900"><?= $matriculeDisplay ? htmlspecialchars((string) $matriculeDisplay) : '— non attribué —' ?></p>
              <?php if (!$matriculeDisplay): ?>
              <p class="mt-2 text-[11px] text-slate-600">Aucun matricule dossier : utilisez le bouton sous le formulaire pour en générer un (reste sur cette page).</p>
              <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <?php if (!empty($roleplayFollowupConfig['enabled'])): ?>
        <section id="edit-suivi-immersion" x-show="tab === 'edit-suivi-immersion'" class="scroll-mt-24 overflow-hidden rounded-2xl border border-emerald-200/90 bg-white shadow-sm ring-1 ring-emerald-900/[0.05]">
          <div class="border-b border-emerald-100 bg-emerald-50/70 px-6 py-5 sm:px-8">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-800">Suivi d’immersion</p>
            <h2 class="mt-2 text-base font-black tracking-tight text-emerald-950 sm:text-lg">Tutorat, filière et jalons</h2>
            <p class="mt-2 max-w-3xl text-xs leading-relaxed text-emerald-900/85">Parcours, dates et notes staff. Les listes déroulantes « étape » et « filière » sont définies dans la configuration de la communauté.</p>
          </div>
          <div class="space-y-8 p-6 sm:p-8">
            <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-5 sm:p-6">
              <h3 class="border-b border-slate-200/80 pb-3 text-xs font-black uppercase tracking-wider text-slate-700">Parcours &amp; place dans l’unité</h3>
              <div class="mt-5 grid gap-5 md:grid-cols-2 lg:gap-x-8">
                <div>
                  <label for="rp_followup_stage" class="mb-1 block text-xs font-bold text-slate-600">Étape actuelle</label>
                  <select name="rp_followup_stage" id="rp_followup_stage" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                    <option value="">— Non définie —</option>
                    <?php foreach ($rpStages as $st): $st = trim((string) $st); if ($st === '') { continue; } ?>
                    <option value="<?= htmlspecialchars($st) ?>" <?= (string) ($p['rp_followup_stage'] ?? '') === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label for="rp_followup_status" class="mb-1 block text-xs font-bold text-slate-600">Statut de suivi</label>
                  <input type="text" name="rp_followup_status" id="rp_followup_status" value="<?= htmlspecialchars((string) ($p['rp_followup_status'] ?? '')) ?>" maxlength="60" placeholder="Ex. Sous observation / En mentorat actif" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                </div>
                <div>
                  <label for="rp_operational_function" class="mb-1 block text-xs font-bold text-slate-600">Fonction sur la feuille de route</label>
                  <input type="text" name="rp_operational_function" id="rp_operational_function" value="<?= htmlspecialchars((string) ($p['rp_operational_function'] ?? '')) ?>" maxlength="120" placeholder="Ex. Chef d’équipe feu, artilleur, analyste…" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                  <p class="mt-1 text-[11px] leading-relaxed text-slate-500">Poste suivi en jeu, distinct du rôle métier référentiel (bloc ORBAT).</p>
                </div>
                <div>
                  <label for="rp_recruitment_stream" class="mb-1 block text-xs font-bold text-slate-600">Filière de recrutement</label>
                  <select name="rp_recruitment_stream" id="rp_recruitment_stream" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                    <option value="">— Non définie —</option>
                    <?php foreach ($rpTracks as $tr): $tr = trim((string) $tr); if ($tr === '') { continue; } ?>
                    <option value="<?= htmlspecialchars($tr) ?>" <?= (string) ($p['rp_recruitment_stream'] ?? '') === $tr ? 'selected' : '' ?>><?= htmlspecialchars($tr) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="mt-1 text-[11px] leading-relaxed text-slate-500">Liste éditée dans le back-office, page configuration.</p>
                </div>
                <div>
                  <label for="rp_recruitment_origin" class="mb-1 block text-xs font-bold text-slate-600">Profil recrutement</label>
                  <select name="rp_recruitment_origin" id="rp_recruitment_origin" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                    <option value=""<?= $rpOriginSel === '' ? ' selected' : '' ?>>— Non renseigné —</option>
                    <option value="internal"<?= $rpOriginSel === 'internal' ? ' selected' : '' ?>>Interne (déjà membre / réseau proche)</option>
                    <option value="external"<?= $rpOriginSel === 'external' ? ' selected' : '' ?>>Externe (nouvelle recrue)</option>
                  </select>
                  <p class="mt-1 text-[11px] leading-relaxed text-slate-500">Interne ou externe pour le pilotage accueil / tutorat.</p>
                </div>
                <div>
                  <label for="rp_followup_progress" class="mb-1 block text-xs font-bold text-slate-600">Progression dossier (%)</label>
                  <input type="number" min="0" max="100" name="rp_followup_progress" id="rp_followup_progress" value="<?= htmlspecialchars((string) ($p['rp_followup_progress'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                </div>
                <div class="md:col-span-2">
                  <label for="rp_tutor_user_id" class="mb-1 block text-xs font-bold text-slate-600">Tuteur ou référent tutorat</label>
                  <select name="rp_tutor_user_id" id="rp_tutor_user_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                    <option value="">— Aucun —</option>
                    <?php $selTutor = (int) ($p['rp_tutor_user_id'] ?? 0); foreach ($rpTutorChoices as $tu): ?>
                    <option value="<?= (int) $tu['id'] ?>" <?= $selTutor === (int) $tu['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $tu['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-5 sm:p-6">
              <h3 class="border-b border-slate-200/80 pb-3 text-xs font-black uppercase tracking-wider text-slate-700">Échéances &amp; rendez-vous</h3>
              <div class="mt-5 grid gap-5 md:grid-cols-3 lg:gap-x-8">
                <div>
                  <label for="rp_next_interview_date" class="mb-1 block text-xs font-bold text-slate-600">Prochain entretien individuel</label>
                  <input type="date" name="rp_next_interview_date" id="rp_next_interview_date" value="<?= htmlspecialchars((string) ($p['rp_next_interview_date'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                </div>
                <div>
                  <label for="rp_medical_due_date" class="mb-1 block text-xs font-bold text-slate-600">Échéance visite médicale</label>
                  <input type="date" name="rp_medical_due_date" id="rp_medical_due_date" value="<?= htmlspecialchars((string) ($p['rp_medical_due_date'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                </div>
                <div>
                  <label for="rp_service_rotation_date" class="mb-1 block text-xs font-bold text-slate-600">Rotation de service prévue</label>
                  <input type="date" name="rp_service_rotation_date" id="rp_service_rotation_date" value="<?= htmlspecialchars((string) ($p['rp_service_rotation_date'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                </div>
              </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-5 sm:p-6">
              <h3 class="border-b border-slate-200/80 pb-3 text-xs font-black uppercase tracking-wider text-slate-700">Notes de suivi (staff)</h3>
              <div class="mt-5">
                <label for="rp_followup_notes" class="sr-only">Notes de suivi</label>
                <textarea name="rp_followup_notes" id="rp_followup_notes" rows="4" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm leading-relaxed" placeholder="Objectifs individuels, points de vigilance, observations tutorat…"><?= htmlspecialchars((string) ($p['rp_followup_notes'] ?? '')) ?></textarea>
              </div>
            </div>
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-5 sm:p-6">
              <h3 class="border-b border-emerald-200/60 pb-3 text-xs font-black uppercase tracking-wider text-emerald-900">Historique du dossier (optionnel)</h3>
              <p class="mt-3 text-[11px] leading-relaxed text-emerald-900/85">Si vous remplissez un titre ci-dessous, une entrée datée est ajoutée à la frise à l’enregistrement.</p>
              <div class="mt-3 grid gap-3 md:grid-cols-2">
                <div>
                  <label for="rp_timeline_type" class="mb-1 block text-xs font-bold text-slate-600">Type d’événement</label>
                  <select id="rp_timeline_type" name="rp_timeline_type" class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-sm">
                    <?php foreach ($roleplayEventTypes as $evt): $evt = trim((string) $evt); if ($evt === '') { continue; } ?>
                    <option value="<?= htmlspecialchars($evt) ?>"><?= htmlspecialchars(ucfirst($evt)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label for="rp_timeline_status" class="mb-1 block text-xs font-bold text-slate-600">Statut</label>
                  <select id="rp_timeline_status" name="rp_timeline_status" class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-sm">
                    <option value="planned">Prévu</option>
                    <option value="completed">Terminé</option>
                    <option value="blocked">Bloqué</option>
                    <option value="cancelled">Annulé</option>
                  </select>
                </div>
                <div class="md:col-span-2">
                  <label for="rp_timeline_title" class="mb-1 block text-xs font-bold text-slate-600">Titre (si vide : aucun événement ajouté)</label>
                  <input type="text" id="rp_timeline_title" name="rp_timeline_title" maxlength="180" class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-sm" placeholder="Ex. Debrief tutorat semaine 3">
                </div>
                <div>
                  <label for="rp_timeline_event_date" class="mb-1 block text-xs font-bold text-slate-600">Date événement</label>
                  <input type="date" id="rp_timeline_event_date" name="rp_timeline_event_date" class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-sm">
                </div>
                <div>
                  <label for="rp_timeline_due_date" class="mb-1 block text-xs font-bold text-slate-600">Échéance</label>
                  <input type="date" id="rp_timeline_due_date" name="rp_timeline_due_date" class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-sm">
                </div>
                <div>
                  <label for="rp_timeline_progress_delta" class="mb-1 block text-xs font-bold text-slate-600">Impact progression (-100 à +100)</label>
                  <input type="number" min="-100" max="100" id="rp_timeline_progress_delta" name="rp_timeline_progress_delta" class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-sm" placeholder="+10">
                </div>
                <div class="md:col-span-2">
                  <label for="rp_timeline_detail" class="mb-1 block text-xs font-bold text-slate-600">Détail</label>
                  <textarea id="rp_timeline_detail" name="rp_timeline_detail" rows="2" class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-sm" placeholder="Compte-rendu, décision, blocage, plan d’action…"></textarea>
                </div>
              </div>
            </div>
          </div>
        </section>
        <?php endif; ?>
        </div>

        <div x-cloak x-show="['forum-community-settings','edit-equipement','edit-notes'].includes(tab)">
        <section id="forum-community-settings" x-show="tab === 'forum-community-settings'" class="scroll-mt-24 overflow-hidden rounded-2xl border border-violet-200/80 bg-white shadow-sm ring-1 ring-violet-900/[0.06]">
          <div class="border-b border-violet-100 bg-violet-50/60 px-6 py-5">
            <h2 class="text-base font-black tracking-tight text-violet-950">Forum &amp; fiche</h2>
            <p class="mt-1.5 max-w-2xl text-xs leading-relaxed text-violet-900/85">Éléments visibles sur vos messages et sur votre fiche pour les autres membres.</p>
          </div>
          <div class="space-y-6 p-6">
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-bold text-slate-600">Identité forum</label>
                <input type="hidden" name="forum_alias" value="">
                <input type="hidden" name="forum_label_mode" value="character_name">
                <p class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">Le forum affiche le <strong>prénom + nom</strong> du personnage. Plus de pseudo séparé.</p>
              </div>
            </div>
            <?php if (!empty($forumOrgRoleChoices)): ?>
            <div class="md:col-span-2">
              <label for="forum_visible_role_id" class="mb-1 block text-xs font-bold text-slate-600">Rôle affiché sur le forum (carte auteur)</label>
              <select name="forum_visible_role_id" id="forum_visible_role_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                <?php
                $fvRole = isset($d['forum_visible_role_id']) && $d['forum_visible_role_id'] !== null && $d['forum_visible_role_id'] !== ''
                    ? (int) $d['forum_visible_role_id'] : 0;
                ?>
                <option value=""<?= $fvRole === 0 ? ' selected' : '' ?>>Rôle principal du compte (défaut)</option>
                <?php foreach ($forumOrgRoleChoices as $fro) {
                    $oid = (int) ($fro['id'] ?? 0);
                    $oname = trim((string) ($fro['name'] ?? ''));
                    if ($oid < 1) {
                        continue;
                    }
                    echo '<option value="' . $oid . '"' . ($fvRole === $oid ? ' selected' : '') . '>' . htmlspecialchars($oname !== '' ? $oname : ('#' . $oid)) . '</option>';
                } ?>
              </select>
              <p class="mt-1 text-[11px] text-slate-500">Parmi vos rôles réellement attribués (communauté et, le cas échéant, plateforme), choisissez l’intitulé affiché sur la carte auteur ; sinon le rôle principal s’applique.</p>
            </div>
            <?php endif; ?>
            <?php if ($isMe && $memberCanChooseDisplayRole && !empty($forumOrgRoleChoices)): ?>
            <?php $prefRole = (int) ($targetUser['preferred_display_role_id'] ?? 0); ?>
            <div class="md:col-span-2 rounded-xl border border-sky-100 bg-sky-50/50 px-4 py-3">
              <label for="preferred_display_role_id" class="mb-1 block text-xs font-bold text-slate-700">Rôle affiché en priorité (portail et forum)</label>
              <select name="preferred_display_role_id" id="preferred_display_role_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                <option value=""<?= $prefRole === 0 ? ' selected' : '' ?>>Automatique (recommandé)</option>
                <?php foreach ($forumOrgRoleChoices as $fro) {
                    $oid = (int) ($fro['id'] ?? 0);
                    $oname = trim((string) ($fro['name'] ?? ''));
                    if ($oid < 1) {
                        continue;
                    }
                    echo '<option value="' . $oid . '"' . ($prefRole === $oid ? ' selected' : '') . '>' . htmlspecialchars($oname !== '' ? $oname : ('#' . $oid)) . '</option>';
                } ?>
              </select>
              <p class="mt-1 text-[11px] text-slate-600">Votre organisation autorise ce choix : il prime sur l’ordre habituel lorsqu’il est défini.</p>
            </div>
            <?php endif; ?>
            <div>
              <p class="mb-2 text-[10px] font-black uppercase tracking-wider text-slate-500">Afficher sur le forum (carte auteur)</p>
              <div class="grid gap-2 sm:grid-cols-2">
                <label class="flex items-center gap-2 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm text-slate-700"><input type="checkbox" name="show_matricule_forum" value="1" <?= !empty($d['show_matricule_forum']) ? 'checked' : '' ?>> Matricule</label>
                <label class="flex items-center gap-2 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm text-slate-700"><input type="checkbox" name="show_grade_forum" value="1" <?= !empty($d['show_grade_forum']) ? 'checked' : '' ?>> Grade</label>
                <label class="flex items-center gap-2 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm text-slate-700"><input type="checkbox" name="show_unit_forum" value="1" <?= !empty($d['show_unit_forum']) ? 'checked' : '' ?>> Unité / rôle</label>
                <label class="flex items-center gap-2 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm text-slate-700"><input type="checkbox" name="show_bio_forum" value="1" <?= !empty($d['show_bio_forum']) ? 'checked' : '' ?>> Présentation du personnage</label>
                <label class="flex items-center gap-2 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm text-slate-700"><input type="checkbox" name="hide_forum_level" id="hide_forum_level" value="1" <?= (!empty($d['hide_forum_level']) || ($forumPreHideLevel && $isMe)) ? 'checked' : '' ?>> Masquer le niveau forum (LVL)</label>
              </div>
            </div>
            <div>
              <p class="mb-2 text-[10px] font-black uppercase tracking-wider text-slate-500">Fiche personnelle (autres membres)</p>
              <input type="hidden" name="fiche_show_email_to_others" value="0">
              <p class="mb-2 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 text-xs text-slate-600 leading-relaxed">Pour protéger votre vie privée, votre adresse e-mail n’apparaît pas sur votre fiche pour les autres membres. Elle reste visible pour vous et pour le personnel habilité (gestion des effectifs ou accès RH sensible).</p>
              <div class="grid gap-2 sm:grid-cols-2">
                <label class="flex items-center gap-2 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm text-slate-700"><input type="checkbox" name="fiche_show_matricule_to_others" value="1" <?= !empty($d['fiche_show_matricule_to_others']) ? 'checked' : '' ?>> Afficher le matricule (en-tête)</label>
              </div>
            </div>
            <label class="flex items-start gap-3 rounded-xl border border-emerald-100 bg-emerald-50/40 px-4 py-3 text-sm text-slate-800">
              <input type="checkbox" name="public_roster_opt_in" value="1" class="mt-0.5" <?= !empty($d['public_roster_opt_in']) ? 'checked' : '' ?>>
              <span><strong>Liste publique des membres</strong> — apparaître sur la page vitrine de la communauté si elle est activée.</span>
            </label>
          </div>
        </section>
        <?php if (($forumQuickMode !== '' || $forumFocus !== '' || $forumPreHideLevel) && $isMe): ?>
        <script>
        (function () {
          function go() {
            var mode = <?= json_encode($forumQuickMode) ?>;
            var focus = <?= json_encode($forumFocus) ?>;
            var sel = document.getElementById('forum_label_mode');
            if (mode && sel) { sel.value = mode; }
            var sec = document.getElementById('forum-community-settings');
            if (focus === 'label' && sel) {
              sel.focus();
              sec && sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else if (focus === 'hide_level') {
              var h = document.getElementById('hide_forum_level');
              h && h.closest('label') && h.closest('label').scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else if (mode && sec) {
              sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          }
          if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go);
          else go();
        })();
        </script>
        <?php endif; ?>

        <section id="edit-equipement" x-show="tab === 'edit-equipement'" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
          <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-5">
            <h2 class="text-base font-black tracking-tight text-slate-900">Équipement / dotation</h2>
            <p class="mt-1.5 max-w-2xl text-xs leading-relaxed text-slate-600">Classe, kit et matériels assignés au personnage.</p>
          </div>
          <div class="grid gap-4 p-6 md:grid-cols-2">
            <div>
              <label for="equipment_class" class="mb-1 block text-xs font-bold text-slate-600">Classe d’équipement</label>
              <input type="text" name="equipment_class" id="equipment_class" value="<?= htmlspecialchars($p['equipment_class'] ?? '') ?>" placeholder="Rifleman Light…" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
            </div>
            <div>
              <label for="kit_assigned" class="mb-1 block text-xs font-bold text-slate-600">Kit assigné</label>
              <input type="text" name="kit_assigned" id="kit_assigned" value="<?= htmlspecialchars($p['kit_assigned'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="255">
            </div>
            <div>
              <label for="radio_assigned" class="mb-1 block text-xs font-bold text-slate-600">Radio</label>
              <input type="text" name="radio_assigned" id="radio_assigned" value="<?= htmlspecialchars($p['radio_assigned'] ?? '') ?>" placeholder="PRC-152…" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
            </div>
            <div>
              <label for="vehicle_authorized" class="mb-1 block text-xs font-bold text-slate-600">Véhicule autorisé</label>
              <input type="text" name="vehicle_authorized" id="vehicle_authorized" value="<?= htmlspecialchars($p['vehicle_authorized'] ?? '') ?>" placeholder="MRAP, Utility…" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="255">
            </div>
            <div>
              <label for="weapon_specialty" class="mb-1 block text-xs font-bold text-slate-600">Spécialité armement</label>
              <input type="text" name="weapon_specialty" id="weapon_specialty" value="<?= htmlspecialchars($p['weapon_specialty'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
            </div>
            <div class="flex items-center gap-3 md:col-span-2">
              <input type="checkbox" name="deployable" id="deployable" value="1" <?= ($p['deployable'] ?? 1) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-emerald-600">
              <label for="deployable" class="text-sm font-semibold text-slate-800">Déployable</label>
            </div>
            <div class="md:col-span-2">
              <label for="medal_rack_text" class="mb-1 block text-xs font-bold text-slate-600">Décorations et placards</label>
              <textarea name="medal_rack_text" id="medal_rack_text" rows="4" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" placeholder="Un élément par ligne&#10;Ex. Croix de la Valeur militaire&#10;Placard commémoratif - opération Atlas"><?= htmlspecialchars($medalRackText) ?></textarea>
              <p class="mt-1 text-[11px] text-slate-500">Base déclarative pour préparer la fiche visuelle des décorations, sans imposer encore un format graphique figé.</p>
            </div>
          </div>
        </section>

        <section id="edit-notes" x-show="tab === 'edit-notes'" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
          <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-5">
            <h2 class="text-base font-black tracking-tight text-slate-900">Notes de commandement</h2>
            <p class="mt-1.5 text-xs text-slate-600">Visibles par vous et le personnel habilité.</p>
          </div>
          <div class="p-6">
            <textarea name="command_notes" id="command_notes" rows="5" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400/20" placeholder="Notes internes…"><?= htmlspecialchars($p['command_notes'] ?? '') ?></textarea>
          </div>
        </section>
        </div>

        </div>

        <div class="pd-card__foot">
          <button type="submit" class="pd-btn pd-btn--primary">Enregistrer</button>
          <a href="<?= $isMe ? url('personnel/me') : url('personnel/' . (int) $targetUser['id']) ?>" class="pd-btn">Annuler</a>
        </div>
    </form>
    </div>

    <?php if (!$matriculeDisplay): ?>
    <div class="pd-matricule">
      <p><strong>Matricule interne</strong> — attribue un identifiant unique au dossier (organigramme, courriers, forum).</p>
      <form method="post" action="<?= htmlspecialchars(url('personnel/' . (int) $targetUser['id'] . '/generate-matricule')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="return_to" value="edit">
        <button type="submit" class="pd-btn pd-btn--primary">Générer un matricule</button>
      </form>
    </div>
    <?php endif; ?>

  </div>
</div>
<?php if (!empty($dossierPresets)): ?>
<script>
(function () {
  var PRESETS = <?= json_encode($dossierPresets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
  var JOB_ROLE_BY_SLUG = <?= json_encode($jobRoleSlugToId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
  var fields = ['equipment_class', 'kit_assigned', 'radio_assigned', 'vehicle_authorized', 'weapon_specialty'];
  document.querySelectorAll('.personnel-preset-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-preset-id');
      var preset = PRESETS.find(function (p) { return p.id === id; });
      if (!preset) return;
      var roleId = (preset.job_role_slug && JOB_ROLE_BY_SLUG && JOB_ROLE_BY_SLUG[preset.job_role_slug] != null)
        ? JOB_ROLE_BY_SLUG[preset.job_role_slug]
        : 0;
      if (roleId > 0) {
        window.dispatchEvent(new CustomEvent('personnel-preset-job-role', { detail: { roleId: roleId } }));
      }
      fields.forEach(function (key) {
        if (preset[key] === undefined || preset[key] === null) return;
        var el = document.getElementById(key);
        if (el) el.value = String(preset[key]);
      });
      btn.classList.add('ring-2', 'ring-emerald-500');
      setTimeout(function () { btn.classList.remove('ring-2', 'ring-emerald-500'); }, 400);
    });
  });
})();
</script>
<?php endif; ?>
<script>
function personnelJobRolesEditor(initialRows, jobRoleOptions, maxRoles) {
  var rows = (initialRows || []).map(function (r, i) {
    return { key: i, role_id: r.role_id || 0, detail: r.detail || '' };
  });
  if (rows.length === 0) {
    rows = [{ key: 0, role_id: 0, detail: '' }];
  }
  var primaryIdx = 0;
  (initialRows || []).forEach(function (r, i) {
    if (r.is_primary) { primaryIdx = i; }
  });
  return {
    roles: rows,
    primaryIdx: primaryIdx,
    maxRoles: maxRoles || 5,
    jobRoleOptions: jobRoleOptions || [],
    nextKey: rows.length,
    addRow: function () {
      if (this.roles.length >= this.maxRoles) return;
      this.roles.push({ key: this.nextKey++, role_id: 0, detail: '' });
    },
    removeRow: function (idx) {
      if (this.roles.length <= 1) return;
      this.roles.splice(idx, 1);
      if (this.primaryIdx === idx) {
        this.primaryIdx = 0;
      } else if (this.primaryIdx > idx) {
        this.primaryIdx--;
      }
    },
    applyPresetRole: function (detail) {
      var roleId = detail && detail.roleId ? parseInt(detail.roleId, 10) : 0;
      if (!roleId) return;
      this.roles[this.primaryIdx].role_id = roleId;
    },
    init: function () {
      var self = this;
      window.addEventListener('personnel-preset-job-role', function (ev) {
        self.applyPresetRole(ev.detail);
      });
    }
  };
}

function personnelUnitAssignmentsEditor(initialRows, unitOptions, maxRows) {
  var rows = (initialRows || []).map(function (row, index) {
    return {
      key: index,
      unit_id: row.unit_id || 0,
      role_name: row.role_name || '',
      is_primary: !!row.is_primary
    };
  });
  if (rows.length === 0) {
    rows = [{ key: 0, unit_id: 0, role_name: '', is_primary: true }];
  }
  var primaryIdx = 0;
  rows.forEach(function (row, index) {
    if (row.is_primary) {
      primaryIdx = index;
    }
  });
  return {
    rows: rows,
    unitOptions: unitOptions || [],
    maxRows: maxRows || 8,
    primaryIdx: primaryIdx,
    nextKey: rows.length,
    addRow: function () {
      if (this.rows.length >= this.maxRows) return;
      this.rows.push({ key: this.nextKey++, unit_id: 0, role_name: '', is_primary: false });
    },
    removeRow: function (idx) {
      if (this.rows.length <= 1) {
        this.rows[0].unit_id = 0;
        this.rows[0].role_name = '';
        this.primaryIdx = 0;
        return;
      }
      this.rows.splice(idx, 1);
      if (this.primaryIdx === idx) {
        this.primaryIdx = 0;
      } else if (this.primaryIdx > idx) {
        this.primaryIdx--;
      }
    },
    primaryUnitId: function () {
      if (!this.rows.length) return '';
      var row = this.rows[this.primaryIdx] || this.rows[0];
      return row && row.unit_id ? String(row.unit_id) : '';
    }
  };
}
</script>
