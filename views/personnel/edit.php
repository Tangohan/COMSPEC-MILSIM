<?php
$targetUser = $targetUser ?? null;
$personnelProfile = $personnelProfile ?? null;
$units = $units ?? [];
$userProfile = $userProfile ?? [];
$currentGrade = $currentGrade ?? null;
$completeness = $completeness ?? ['score' => 0, 'missing_labels' => []];
$matriculeDisplay = $matriculeDisplay ?? null;
$personnelAssignments = $personnelAssignments ?? [];
$dossierPresets = $dossierPresets ?? [];
$jobRolesEnabled = $jobRolesEnabled ?? false;
$jobRoleOptions = $jobRoleOptions ?? [];
$jobRoleSlugToId = $jobRoleSlugToId ?? [];
$forumQuickMode = $forumQuickMode ?? '';
$forumFocus = $forumFocus ?? '';
$forumPreHideLevel = !empty($forumPreHideLevel);
$forumOrgRoleChoices = is_array($forumOrgRoleChoices ?? null) ? $forumOrgRoleChoices : [];
$memberCanChooseDisplayRole = !empty($memberCanChooseDisplayRole);
$roleplayFollowupConfig = is_array($roleplayFollowupConfig ?? null) ? $roleplayFollowupConfig : ['enabled' => false, 'stages' => [], 'recruitment_tracks' => []];
$rpTutorChoices = is_array($rpTutorChoices ?? null) ? $rpTutorChoices : [];

$isMe = (int) ($targetUser['id'] ?? 0) === (int) (\App\Core\Session::get('user_id'));
$formAction = url('personnel/' . (int) $targetUser['id'] . '/update');
if (!$targetUser) {
    echo '<p>Utilisateur non trouvé.</p>';
    return;
}
$p = $personnelProfile ?? [];
$up = is_array($userProfile) ? $userProfile : [];
$d = $displaySettings ?? [];
$clearanceOptions = ['Non classifié', 'Restreint', 'Confidentiel', 'Secret', 'Très secret'];
$currentClearance = trim((string) ($p['clearance_level'] ?? ''));
$clearanceReviewedAt = '';
if (!empty($p['clearance_reviewed_at'])) {
    $cr = date_create((string) $p['clearance_reviewed_at']);
    $clearanceReviewedAt = $cr ? $cr->format('Y-m-d') : '';
}
$readinessScoreVal = isset($p['readiness_score']) ? (int) $p['readiness_score'] : 0;
$score = (int) ($completeness['score'] ?? 0);
$missingLabels = $completeness['missing_labels'] ?? [];
$civilBirth = '';
if (!empty($up['birth_date'])) {
    $bd = date_create((string) $up['birth_date']);
    $civilBirth = $bd ? $bd->format('Y-m-d') : '';
}
$enlistmentDateVal = '';
if (!empty($p['enlistment_date'])) {
    $enlistmentDateVal = substr((string) $p['enlistment_date'], 0, 10);
}
$gradeLabel = '';
if ($currentGrade) {
    $gradeLabel = trim((string) ($currentGrade['label_short'] ?? $currentGrade['short_name'] ?? $currentGrade['label_long'] ?? $currentGrade['name'] ?? ''));
}
$bloodOptions = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Inconnu'];
$rpStages = is_array($roleplayFollowupConfig['stages'] ?? null) ? $roleplayFollowupConfig['stages'] : [];
$rpTracks = is_array($roleplayFollowupConfig['recruitment_tracks'] ?? null) ? $roleplayFollowupConfig['recruitment_tracks'] : [];
?>
<div class="min-h-screen bg-gradient-to-b from-slate-50 to-slate-100/80 pb-16">
  <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
    <header class="mb-8 flex flex-col gap-4 border-b border-slate-200/80 pb-8 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="text-[10px] font-black uppercase tracking-[0.35em] text-emerald-700">Dossier opérationnel</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Éditer le dossier</h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">Identité RP, habilitations, équipement, visibilité forum et — pour votre compte — identité civile liée au profil.</p>
      </div>
      <div class="flex flex-wrap gap-3">
        <a href="<?= $isMe ? url('personnel/me') : url('personnel/' . (int) $targetUser['id']) ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm hover:border-slate-300">← Fiche</a>
        <a href="<?= url('account/preferences') ?>" class="inline-flex items-center rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-900 hover:bg-indigo-100">Préférences compte</a>
        <a href="<?= url('account/portrait') ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Portrait / médias</a>
        <a href="<?= htmlspecialchars(url('personnel/tutorials')) ?>" class="inline-flex items-center rounded-xl border border-emerald-200 bg-emerald-50/80 px-4 py-2.5 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Tutoriels &amp; presets</a>
      </div>
    </header>

    <?php $success = \App\Core\Session::getFlash('success'); if ($success): ?>
    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php $error = \App\Core\Session::getFlash('error'); if ($error): ?>
    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="mb-8 overflow-hidden rounded-2xl border border-amber-200/80 bg-gradient-to-r from-amber-50 to-white p-5 shadow-sm">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-[10px] font-black uppercase tracking-widest text-amber-900/80">Complétude du dossier</p>
          <p class="mt-1 text-2xl font-black text-slate-900"><?= $score ?>%</p>
          <?php if (!empty($missingLabels)): ?>
          <p class="mt-2 text-xs leading-relaxed text-amber-950/90"><span class="font-bold">À compléter :</span> <?= htmlspecialchars(implode(' · ', array_slice($missingLabels, 0, 8))) ?><?= count($missingLabels) > 8 ? '…' : '' ?></p>
          <?php endif; ?>
        </div>
        <div class="h-2.5 w-full max-w-xs overflow-hidden rounded-full bg-amber-100 sm:mt-0">
          <div class="h-full rounded-full bg-gradient-to-r from-amber-500 to-emerald-600 transition-all" style="width: <?= min(100, max(0, $score)) ?>%"></div>
        </div>
      </div>
    </div>

    <form method="post" action="<?= htmlspecialchars($formAction) ?>" class="space-y-8">
        <?= \App\Core\Csrf::field() ?>

        <?php if ($isMe): ?>
        <section class="overflow-hidden rounded-2xl border border-indigo-200/80 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
          <div class="border-b border-indigo-100 bg-indigo-50/80 px-6 py-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-indigo-950">Identité civile &amp; contact (compte)</h2>
            <p class="mt-1 text-xs text-indigo-900/80">Ces champs alimentent aussi la fiche « civile » et les exports ; le fuseau et la langue guident l’interface.</p>
          </div>
          <div class="grid gap-4 p-6 sm:grid-cols-2">
            <div>
              <label for="civil_first_name" class="mb-1 block text-xs font-bold text-slate-600">Prénom</label>
              <input type="text" name="civil_first_name" id="civil_first_name" value="<?= htmlspecialchars((string) ($up['first_name'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" maxlength="100">
            </div>
            <div>
              <label for="civil_last_name" class="mb-1 block text-xs font-bold text-slate-600">Nom</label>
              <input type="text" name="civil_last_name" id="civil_last_name" value="<?= htmlspecialchars((string) ($up['last_name'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" maxlength="100">
            </div>
            <div>
              <label for="civil_phone" class="mb-1 block text-xs font-bold text-slate-600">Téléphone</label>
              <input type="text" name="civil_phone" id="civil_phone" value="<?= htmlspecialchars((string) ($up['phone'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="50">
            </div>
            <div>
              <label for="civil_birth_date" class="mb-1 block text-xs font-bold text-slate-600">Date de naissance</label>
              <input type="date" name="civil_birth_date" id="civil_birth_date" value="<?= htmlspecialchars($civilBirth) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
            </div>
            <div>
              <label for="civil_nationality" class="mb-1 block text-xs font-bold text-slate-600">Nationalité (civile)</label>
              <input type="text" name="civil_nationality" id="civil_nationality" value="<?= htmlspecialchars((string) ($up['nationality'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
            </div>
            <div>
              <label for="civil_timezone" class="mb-1 block text-xs font-bold text-slate-600">Fuseau horaire</label>
              <input type="text" name="civil_timezone" id="civil_timezone" value="<?= htmlspecialchars((string) ($up['timezone'] ?? '')) ?>" placeholder="Europe/Paris" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="50">
            </div>
            <div>
              <label for="civil_language" class="mb-1 block text-xs font-bold text-slate-600">Langue (UI)</label>
              <input type="text" name="civil_language" id="civil_language" value="<?= htmlspecialchars((string) ($up['language'] ?? '')) ?>" placeholder="fr" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="10">
            </div>
            <div class="sm:col-span-2 rounded-xl border border-amber-100 bg-amber-50/60 px-4 py-3">
              <label class="flex items-start gap-3 text-sm text-slate-800">
                <input type="checkbox" name="hide_personal_info" value="1" class="mt-1 rounded border-slate-300 text-amber-900" <?= !empty($d['hide_personal_info']) ? 'checked' : '' ?>>
                <span><strong>Masquer mes informations personnelles</strong> sur la fiche publique (prénom, nom, téléphone, date de naissance, nationalité civile, fuseau, langue, bio compte). Seuls les <strong>administrateurs</strong> (accès fiche personnel) et les <strong>modérateurs forum</strong> pourront les consulter ; les autres membres ne verront que votre identité opérationnelle / RP (nom de personnage, indicatif, etc.).</span>
              </label>
            </div>
            <div class="sm:col-span-2">
              <label for="civil_bio" class="mb-1 block text-xs font-bold text-slate-600">Bio courte (compte)</label>
              <textarea name="civil_bio" id="civil_bio" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" placeholder="Présentation courte hors RP…"><?= htmlspecialchars((string) ($up['bio'] ?? '')) ?></textarea>
            </div>
          </div>
        </section>
        <?php endif; ?>

        <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
          <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">Identité opérationnelle (RP)</h2>
            <p class="mt-1 text-xs text-slate-600">Nom de personnage, indicatif et rôles tels qu’affichés en mission.</p>
          </div>
          <div class="grid gap-4 p-6 md:grid-cols-2">
            <div>
              <label for="character_name" class="mb-1 block text-xs font-bold text-slate-600">Nom opérateur / RP</label>
              <input type="text" name="character_name" id="character_name" value="<?= htmlspecialchars($p['character_name'] ?? $targetUser['display_name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/15" maxlength="150">
            </div>
            <div>
              <label for="callsign" class="mb-1 block text-xs font-bold text-slate-600">Callsign</label>
              <input type="text" name="callsign" id="callsign" value="<?= htmlspecialchars($p['callsign'] ?? $targetUser['callsign'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
            </div>
            <div>
              <label for="rank_display" class="mb-1 block text-xs font-bold text-slate-600">Grade / titre affiché (RP, optionnel)</label>
              <input type="text" name="rank_display" id="rank_display" value="<?= htmlspecialchars((string) ($p['rank_display'] ?? '')) ?>" placeholder="Sous-lieutenant, Chief…" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
              <?php if ($gradeLabel !== ''): ?>
              <p class="mt-1 text-[11px] text-slate-500">Grade communauté (ORBAT) : <strong class="text-slate-700"><?= htmlspecialchars($gradeLabel) ?></strong></p>
              <?php endif; ?>
            </div>
            <div>
              <label for="rank_display_override" class="mb-1 block text-xs font-bold text-slate-600">Surcharge libellé (optionnel)</label>
              <input type="text" name="rank_display_override" id="rank_display_override" value="<?= htmlspecialchars((string) ($p['rank_display_override'] ?? '')) ?>" placeholder="Remplace le libellé court automatique" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
            </div>
            <div class="md:col-span-2 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2 text-[11px] text-slate-600">
              <strong class="text-slate-800">Fonction métier :</strong> <?= $jobRolesEnabled ? 'choisissez un <strong>rôle métier</strong> défini par la communauté (back-office) et optionnellement un <strong>sous-rôle</strong> libre. La section ORBAT ci-dessous synchronise la même ligne d’affectation.' : 'renseignez le <strong>rôle dans l’unité</strong> dans la section ORBAT ci-dessous.' ?>
            </div>
            <div>
              <label for="secondary_role" class="mb-1 block text-xs font-bold text-slate-600">Rôle secondaire</label>
              <input type="text" name="secondary_role" id="secondary_role" value="<?= htmlspecialchars($p['secondary_role'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
            </div>
            <div class="md:col-span-2">
              <label for="motto" class="mb-1 block text-xs font-bold text-slate-600">Devise / motto</label>
              <input type="text" name="motto" id="motto" value="<?= htmlspecialchars((string) ($p['motto'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="255">
            </div>
            <div>
              <label for="languages" class="mb-1 block text-xs font-bold text-slate-600">Langues (RP)</label>
              <input type="text" name="languages" id="languages" value="<?= htmlspecialchars((string) ($p['languages'] ?? '')) ?>" placeholder="FR, EN…" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="255">
            </div>
            <div>
              <label for="nationality_rp" class="mb-1 block text-xs font-bold text-slate-600">Nationalité (RP)</label>
              <input type="text" name="nationality_rp" id="nationality_rp" value="<?= htmlspecialchars((string) ($p['nationality'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
            </div>
            <div>
              <label for="blood_type" class="mb-1 block text-xs font-bold text-slate-600">Groupe sanguin (RP)</label>
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
                ?>
              </select>
            </div>
          </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-cyan-200/90 bg-white shadow-sm ring-1 ring-cyan-900/[0.04]">
          <div class="border-b border-cyan-100 bg-cyan-50/70 px-6 py-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-cyan-950">Unité &amp; affectation (ORBAT)</h2>
            <p class="mt-1 text-xs text-cyan-900/85">Choisissez l’unité principale et le rôle dans cette unité : l’enregistrement met à jour le dossier <strong>et</strong> l’affectation ORBAT (fiche, forum, indicateurs admin « sans unité »).</p>
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
            <p class="rounded-xl border border-amber-200 bg-amber-50/80 px-3 py-2 text-xs text-amber-950">Aucune affectation active en base — après enregistrement avec une <strong>unité</strong> et un <strong>rôle dans l’unité</strong>, une ligne ORBAT sera créée.</p>
            <?php endif; ?>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="md:col-span-2">
                <label for="primary_unit_id" class="mb-1 block text-xs font-bold text-slate-600">Unité principale</label>
                <?php if (empty($units)): ?>
                <p class="mb-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                  Aucune unité : créez la structure dans l’<a class="font-semibold underline" href="<?= htmlspecialchars(url('orbat')) ?>">ORBAT</a>.
                </p>
                <?php endif; ?>
                <select name="primary_unit_id" id="primary_unit_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                  <option value="">— Aucune —</option>
                  <?php foreach ($units as $u): ?>
                  <option value="<?= (int) $u['id'] ?>" <?= (isset($p['primary_unit_id']) && (int) $p['primary_unit_id'] === (int) $u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="md:col-span-2 space-y-3">
                <?php if ($jobRolesEnabled): ?>
                <?php
                $selJob = isset($p['personnel_job_role_id']) ? (int) $p['personnel_job_role_id'] : 0;
                $subLab = trim((string) ($p['role_sub_label'] ?? ''));
                ?>
                <div>
                  <label for="personnel_job_role_id" class="mb-1 block text-xs font-bold text-slate-600">Rôle métier (référentiel)</label>
                  <select name="personnel_job_role_id" id="personnel_job_role_id" class="w-full rounded-xl border border-cyan-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20">
                    <option value="">— Non renseigné —</option>
                    <?php foreach ($jobRoleOptions as $jo): ?>
                    <option value="<?= (int) $jo['id'] ?>" <?= $selJob === (int) $jo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($jo['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label for="role_sub_label" class="mb-1 block text-xs font-bold text-slate-600">Sous-rôle (libre)</label>
                  <input type="text" name="role_sub_label" id="role_sub_label" value="<?= htmlspecialchars($subLab) ?>" placeholder="Ex. Spécialité, détachement, matière enseignée…" class="w-full rounded-xl border border-cyan-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20" maxlength="150" autocomplete="off">
                  <p class="mt-1 text-[11px] text-slate-600">Optionnel. Le libellé ORBAT / fiche est construit à partir du rôle métier + ce sous-rôle.</p>
                </div>
                <?php else: ?>
                <div>
                  <label for="primary_role" class="mb-1 block text-xs font-bold text-slate-600">Rôle dans l’unité (affectation ORBAT)</label>
                  <input type="text" name="primary_role" id="primary_role" value="<?= htmlspecialchars($p['primary_role'] ?? '') ?>" placeholder="Ex. Officier opérations, Chef de section, Fusilier…" class="w-full rounded-xl border border-cyan-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20" maxlength="100" autocomplete="off">
                  <p class="mt-1 text-[11px] text-slate-600">Après migration des rôles métier, ce champ sera remplacé par la liste référentielle + sous-rôle.</p>
                </div>
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
              <a href="<?= htmlspecialchars(url('orbat')) ?>" class="font-semibold text-cyan-800 underline-offset-2 hover:underline">Voir l’ORBAT</a>
              — Vue d’ensemble des unités ; les affectations détaillées peuvent aussi être gérées par le personnel habilité côté administration.
            </p>
          </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
          <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">Habilitation &amp; disponibilité</h2>
          </div>
          <div class="grid gap-4 p-6 md:grid-cols-2">
            <div>
              <label for="clearance_level" class="mb-1 block text-xs font-bold text-slate-600">Niveau de clearance</label>
              <select name="clearance_level" id="clearance_level" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                <option value="">— Non défini —</option>
                <?php foreach ($clearanceOptions as $opt): ?>
                <option value="<?= htmlspecialchars($opt) ?>" <?= $currentClearance === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
                <?php if ($currentClearance !== '' && !in_array($currentClearance, $clearanceOptions, true)): ?>
                <option value="<?= htmlspecialchars($currentClearance) ?>" selected><?= htmlspecialchars($currentClearance) ?></option>
                <?php endif; ?>
              </select>
            </div>
            <div>
              <label for="enlistment_date" class="mb-1 block text-xs font-bold text-slate-600">Date d’incorporation</label>
              <input type="date" name="enlistment_date" id="enlistment_date" value="<?= htmlspecialchars($enlistmentDateVal) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
            </div>
            <div>
              <label for="clearance_reviewed_at" class="mb-1 block text-xs font-bold text-slate-600">Dernière revue clearance</label>
              <input type="date" name="clearance_reviewed_at" id="clearance_reviewed_at" value="<?= htmlspecialchars($clearanceReviewedAt) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
            </div>
            <div>
              <label for="readiness_score" class="mb-1 block text-xs font-bold text-slate-600">Indicateur de disponibilité (0–100)</label>
              <input type="number" name="readiness_score" id="readiness_score" min="0" max="100" value="<?= $readinessScoreVal ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
              <p class="mt-1 text-[11px] text-slate-500">Compte pour la complétude si &gt; 0 (sinon une formation certifiante peut suffire).</p>
            </div>
            <div class="md:col-span-2 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
              <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Matricule dossier</p>
              <p class="mt-1 font-mono text-sm font-bold text-slate-900"><?= $matriculeDisplay ? htmlspecialchars((string) $matriculeDisplay) : '— non attribué —' ?></p>
              <?php if (!$matriculeDisplay): ?>
              <p class="mt-2 text-[11px] text-slate-600">Aucun matricule : utilisez le bouton sous le formulaire pour en générer un (reste sur cette page).</p>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <?php if (!empty($roleplayFollowupConfig['enabled'])): ?>
        <section class="overflow-hidden rounded-2xl border border-emerald-200/90 bg-white shadow-sm ring-1 ring-emerald-900/[0.05]">
          <div class="border-b border-emerald-100 bg-emerald-50/70 px-6 py-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-emerald-950">Back-office roleplay — suivi individuel</h2>
            <p class="mt-1 text-xs text-emerald-900/80">Tutorat, calendrier de suivi et progression de dossier/recrutement (config tenant).</p>
          </div>
          <div class="grid gap-4 p-6 md:grid-cols-2">
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
              <label for="rp_followup_progress" class="mb-1 block text-xs font-bold text-slate-600">Progression (%)</label>
              <input type="number" min="0" max="100" name="rp_followup_progress" id="rp_followup_progress" value="<?= htmlspecialchars((string) ($p['rp_followup_progress'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
            </div>
            <div>
              <label for="rp_recruitment_stream" class="mb-1 block text-xs font-bold text-slate-600">Filière recrutement</label>
              <select name="rp_recruitment_stream" id="rp_recruitment_stream" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                <option value="">— Non définie —</option>
                <?php foreach ($rpTracks as $tr): $tr = trim((string) $tr); if ($tr === '') { continue; } ?>
                <option value="<?= htmlspecialchars($tr) ?>" <?= (string) ($p['rp_recruitment_stream'] ?? '') === $tr ? 'selected' : '' ?>><?= htmlspecialchars($tr) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="md:col-span-2">
              <label for="rp_tutor_user_id" class="mb-1 block text-xs font-bold text-slate-600">Tuteur / référent tutorat</label>
              <select name="rp_tutor_user_id" id="rp_tutor_user_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                <option value="">— Aucun —</option>
                <?php $selTutor = (int) ($p['rp_tutor_user_id'] ?? 0); foreach ($rpTutorChoices as $tu): ?>
                <option value="<?= (int) $tu['id'] ?>" <?= $selTutor === (int) $tu['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $tu['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
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
            <div class="md:col-span-2">
              <label for="rp_followup_notes" class="mb-1 block text-xs font-bold text-slate-600">Notes de suivi roleplay</label>
              <textarea name="rp_followup_notes" id="rp_followup_notes" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" placeholder="Objectifs individuels, points de vigilance, observations tutorat…"><?= htmlspecialchars((string) ($p['rp_followup_notes'] ?? '')) ?></textarea>
            </div>
          </div>
        </section>
        <?php endif; ?>

        <section id="forum-community-settings" class="scroll-mt-24 overflow-hidden rounded-2xl border border-violet-200/80 bg-white shadow-sm ring-1 ring-violet-900/[0.06]">
          <div class="border-b border-violet-100 bg-violet-50/60 px-6 py-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-violet-950">Forum &amp; visibilité (communauté)</h2>
            <p class="mt-1 text-xs text-violet-900/80">Pseudo forum et éléments visibles sur les messages et la fiche pour les autres membres.</p>
          </div>
          <div class="space-y-6 p-6">
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="forum_alias" class="mb-1 block text-xs font-bold text-slate-600">Pseudo forum (optionnel)</label>
                <input type="text" name="forum_alias" id="forum_alias" value="<?= htmlspecialchars((string) ($d['forum_alias'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="80" placeholder="Si vide : selon le mode ci-dessous">
              </div>
              <div>
                <label for="forum_label_mode" class="mb-1 block text-xs font-bold text-slate-600">Mode d’étiquette forum (si pseudo vide)</label>
                <select name="forum_label_mode" id="forum_label_mode" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                  <?php
                  $mode = (string) ($d['forum_label_mode'] ?? 'display_name');
                  $modes = [
                      'display_name' => 'Nom d’affichage compte',
                      'callsign' => 'Callsign',
                      'character_name' => 'Nom opérateur / RP',
                      'forum_alias' => 'Pseudo forum uniquement (fallback si vide)',
                  ];
                  foreach ($modes as $k => $label) {
                      echo '<option value="' . htmlspecialchars($k) . '"' . ($mode === $k ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
                  }
                  ?>
                </select>
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
                <label class="flex items-center gap-2 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm text-slate-700"><input type="checkbox" name="show_bio_forum" value="1" <?= !empty($d['show_bio_forum']) ? 'checked' : '' ?>> Bio</label>
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
              <span><strong>Roster public</strong> — apparaître sur la page vitrine <code class="rounded bg-white px-1 text-xs">/c/…</code> si l’organisation l’active.</span>
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

        <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
          <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">Équipement / dotation</h2>
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
          </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
          <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">Notes de commandement</h2>
            <p class="mt-1 text-xs text-slate-500">Visibles par vous et le personnel habilité.</p>
          </div>
          <div class="p-6">
            <textarea name="command_notes" id="command_notes" rows="5" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400/20" placeholder="Notes internes…"><?= htmlspecialchars($p['command_notes'] ?? '') ?></textarea>
          </div>
        </section>

        <div class="flex flex-wrap gap-4 pt-2">
          <button type="submit" class="inline-flex min-w-[160px] items-center justify-center rounded-xl bg-slate-900 px-8 py-3 text-sm font-black uppercase tracking-wider text-white shadow-lg shadow-slate-900/15 transition hover:bg-emerald-600">Enregistrer</button>
          <a href="<?= $isMe ? url('personnel/me') : url('personnel/' . (int) $targetUser['id']) ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
        </div>
    </form>

    <?php if (!$matriculeDisplay): ?>
    <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-emerald-200/80 bg-emerald-50/50 px-5 py-4">
      <p class="text-sm text-emerald-950"><strong>Matricule interne</strong> — attribue un identifiant unique au dossier (ORBAT, courriers, forum).</p>
      <form method="post" action="<?= htmlspecialchars(url('personnel/' . (int) $targetUser['id'] . '/generate-matricule')) ?>" class="flex shrink-0 items-center gap-2">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="return_to" value="edit">
        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">Générer un matricule</button>
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
  var jobRolesEnabled = <?= $jobRolesEnabled ? 'true' : 'false' ?>;
  var fields = ['secondary_role', 'equipment_class', 'kit_assigned', 'radio_assigned', 'vehicle_authorized', 'weapon_specialty'];
  document.querySelectorAll('.personnel-preset-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-preset-id');
      var preset = PRESETS.find(function (p) { return p.id === id; });
      if (!preset) return;
      if (jobRolesEnabled) {
        var sel = document.getElementById('personnel_job_role_id');
        var sub = document.getElementById('role_sub_label');
        if (preset.job_role_slug && JOB_ROLE_BY_SLUG && JOB_ROLE_BY_SLUG[preset.job_role_slug] != null && sel) {
          sel.value = String(JOB_ROLE_BY_SLUG[preset.job_role_slug]);
        } else if (sel) {
          sel.value = '';
        }
        if (sub) {
          if (preset.job_role_slug && preset.primary_role) {
            sub.value = '';
          } else if (preset.primary_role != null) {
            sub.value = String(preset.primary_role);
          }
        }
      } else {
        var pr = document.getElementById('primary_role');
        if (pr && preset.primary_role != null) pr.value = String(preset.primary_role);
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
