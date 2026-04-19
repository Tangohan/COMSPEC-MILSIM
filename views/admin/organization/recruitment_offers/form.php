<?php
declare(strict_types=1);
/** @var array<string,mixed>|null $opening */
/** @var array<string,mixed>|null $openingDecoded */
/** @var list<array<string,mixed>> $units */
/** @var list<array<string,mixed>> $jobRoles */
/** @var array<string,string> $personnelCategories */
/** @var array<string,string> $armDomains */
/** @var array<string,string> $clearanceLevels */
$opening = $opening ?? null;
$od = is_array($openingDecoded ?? null) ? $openingDecoded : [];
$units = $units ?? [];
$jobRoles = $jobRoles ?? [];
$personnelCategories = is_array($personnelCategories ?? null) ? $personnelCategories : [];
$armDomains = is_array($armDomains ?? null) ? $armDomains : [];
$clearanceLevels = is_array($clearanceLevels ?? null) ? $clearanceLevels : [];
$isEdit = $opening !== null;
/** @var array<string,mixed> $openingRow Toujours un tableau pour éviter l’accès à des clés sur null (page création). */
$openingRow = $isEdit && is_array($opening) ? $opening : [];
$canSubmit = $units !== [];
$stLocked = $isEdit && (string) ($openingRow['status'] ?? '') !== 'draft';
?>
<div class="max-w-3xl w-full space-y-6">
    <div class="lms-panel rounded-[2rem] p-6 md:p-8 border border-slate-200/80 overflow-hidden relative">
        <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-sky-500/80 via-sky-500/20 to-transparent" aria-hidden="true"></div>
        <a href="<?= htmlspecialchars(url('back-office/recruitment/offers'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-sky-700 hover:underline">← Retour à la liste</a>
        <h1 class="mt-4 text-2xl md:text-3xl font-black uppercase tracking-tight text-slate-900"><?= $isEdit ? 'Modifier une offre' : 'Nouvelle offre' ?></h1>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed">Rédigez l’avis comme un document RH : les champs structurés alimentent la fiche publique.</p>
    </div>
    <?php if ($stLocked): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-950" role="status">
            Cette offre n’est plus un brouillon : seule la clôture est possible depuis la liste.
        </div>
    <?php endif; ?>

    <?php if (!$canSubmit): ?>
        <div class="lms-panel rounded-2xl border border-amber-200 bg-amber-50/90 p-5 text-sm text-amber-950">
            <p class="font-semibold text-amber-900">Aucune unité n’est encore définie dans votre communauté.</p>
            <p class="mt-2 text-amber-900/90">Créez d’abord une unité (groupe / sous-structure) pour pouvoir rattacher un avis de poste.</p>
            <a href="<?= htmlspecialchars(url('back-office/groups/create'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 inline-flex rounded-lg bg-amber-900 px-4 py-2 text-sm font-bold text-white hover:bg-amber-950">Créer une unité</a>
            <a href="<?= htmlspecialchars(url('back-office/groups'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 ml-2 inline-flex rounded-lg border border-amber-800/30 bg-white px-4 py-2 text-sm font-semibold text-amber-950 hover:bg-amber-100/50">Voir les unités</a>
        </div>
    <?php endif; ?>

    <form action="<?= htmlspecialchars($isEdit ? url('back-office/recruitment/offers/' . (int) ($openingRow['id'] ?? 0) . '/update') : url('back-office/recruitment/offers/store'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-8">
        <?= \App\Core\Csrf::field() ?>

        <div class="lms-panel rounded-2xl border border-slate-200/80 p-6 space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Identification</h2>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Unité porteuse *</label>
                <select name="unit_id" required class="<?= htmlspecialchars(bo_select_class(), ENT_QUOTES, 'UTF-8') ?>" <?= $stLocked ? 'disabled' : '' ?> <?= !$canSubmit ? 'disabled' : '' ?>>
                    <option value="">— Choisir —</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>" <?= (int) ($od['unit_id'] ?? $openingRow['unit_id'] ?? 0) === (int) ($u['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($stLocked): ?>
                    <input type="hidden" name="unit_id" value="<?= (int) ($openingRow['unit_id'] ?? 0) ?>" />
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Emploi métier (référentiel)</label>
                <select name="personnel_job_role_id" class="<?= htmlspecialchars(bo_select_class(), ENT_QUOTES, 'UTF-8') ?>" <?= $stLocked ? 'disabled' : '' ?> <?= !$canSubmit ? 'disabled' : '' ?>>
                    <option value="">— Aucun —</option>
                    <?php foreach ($jobRoles as $jr): ?>
                        <option value="<?= (int) ($jr['id'] ?? 0) ?>" <?= (int) ($od['personnel_job_role_id'] ?? $openingRow['personnel_job_role_id'] ?? 0) === (int) ($jr['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($jr['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($stLocked): ?>
                    <input type="hidden" name="personnel_job_role_id" value="<?= (int) ($openingRow['personnel_job_role_id'] ?? 0) ?>" />
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Titre de l’avis *</label>
                <input type="text" name="title" required value="<?= htmlspecialchars((string) ($od['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $stLocked ? 'readonly' : '' ?> <?= !$canSubmit ? 'readonly' : '' ?> />
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Accroche (liste vitrine)</label>
                <textarea name="summary" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $stLocked ? 'readonly' : '' ?> <?= !$canSubmit ? 'readonly' : '' ?>><?= htmlspecialchars((string) ($od['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </div>

        <div class="lms-panel rounded-2xl border border-slate-200/80 p-6 space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Classification affichée</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Catégorie de personnel</label>
                    <select name="personnel_category" class="<?= htmlspecialchars(bo_select_class(), ENT_QUOTES, 'UTF-8') ?>" <?= $stLocked ? 'disabled' : '' ?> <?= !$canSubmit ? 'disabled' : '' ?>>
                        <?php foreach ($personnelCategories as $k => $lab): ?>
                            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($od['personnel_category'] ?? 'other') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Arme / domaine</label>
                    <select name="arm_domain" class="<?= htmlspecialchars(bo_select_class(), ENT_QUOTES, 'UTF-8') ?>" <?= $stLocked ? 'disabled' : '' ?> <?= !$canSubmit ? 'disabled' : '' ?>>
                        <option value="">—</option>
                        <?php foreach ($armDomains as $k => $lab): ?>
                            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($od['arm_domain'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Engagement (contrat)</label>
                    <input type="text" name="employment_contract_label" value="<?= htmlspecialchars((string) ($od['employment_contract_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="ex. Contrat 20 ans maximum" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $stLocked ? 'readonly' : '' ?> <?= !$canSubmit ? 'readonly' : '' ?> />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Contexte d’emploi</label>
                    <input type="text" name="employment_context_label" value="<?= htmlspecialchars((string) ($od['employment_context_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="ex. Unité de combat" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $stLocked ? 'readonly' : '' ?> <?= !$canSubmit ? 'readonly' : '' ?> />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Niveau d’habilitation demandé</label>
                    <select name="clearance_level" class="<?= htmlspecialchars(bo_select_class(), ENT_QUOTES, 'UTF-8') ?>" <?= $stLocked ? 'disabled' : '' ?> <?= !$canSubmit ? 'disabled' : '' ?>>
                        <?php foreach ($clearanceLevels as $k => $lab): ?>
                            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($od['clearance_level'] ?? 'none') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="lms-panel rounded-2xl border border-slate-200/80 p-6 space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Contenu</h2>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Accroche mission (fiche détail)</label>
                <textarea name="mission_lead" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $stLocked ? 'readonly' : '' ?> <?= !$canSubmit ? 'readonly' : '' ?>><?= htmlspecialchars((string) ($od['mission_lead'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description complète</label>
                <textarea name="description" rows="6" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $stLocked ? 'readonly' : '' ?> <?= !$canSubmit ? 'readonly' : '' ?>><?= htmlspecialchars((string) ($od['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Exigences (une par ligne)</label>
                <textarea name="requirements_lines" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono text-xs" <?= $stLocked ? 'readonly' : '' ?> <?= !$canSubmit ? 'readonly' : '' ?>><?= htmlspecialchars((string) ($od['requirements_lines'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Avis technique (encadré)</label>
                <textarea name="technical_notice" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $stLocked ? 'readonly' : '' ?> <?= !$canSubmit ? 'readonly' : '' ?>><?= htmlspecialchars((string) ($od['technical_notice'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </div>

        <div class="lms-panel rounded-2xl border border-slate-200/80 p-6 space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Profil candidat (jusqu’à 8 lignes)</h2>
            <?php
            $prof = is_array($od['candidate_profile_items'] ?? null) ? $od['candidate_profile_items'] : [];
            for ($i = 0; $i < 8; $i++):
                $row = $prof[$i] ?? null;
                $rub = is_array($row) ? (string) ($row['rubrique'] ?? '') : '';
                $det = is_array($row) ? (string) ($row['detail'] ?? '') : '';
                ?>
                <div class="grid gap-2 sm:grid-cols-2">
                    <input type="text" name="profile_rubrique[]" value="<?= htmlspecialchars($rub, ENT_QUOTES, 'UTF-8') ?>" placeholder="Rubrique" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $stLocked ? 'readonly' : '' ?> <?= !$canSubmit ? 'readonly' : '' ?> />
                    <input type="text" name="profile_detail[]" value="<?= htmlspecialchars($det, ENT_QUOTES, 'UTF-8') ?>" placeholder="Détail" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $stLocked ? 'readonly' : '' ?> <?= !$canSubmit ? 'readonly' : '' ?> />
                </div>
            <?php endfor; ?>
        </div>

        <div class="lms-panel rounded-2xl border border-slate-200/80 p-6 space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Grands axes (jusqu’à 6 blocs)</h2>
            <?php
            $blocks = is_array($od['responsibility_blocks'] ?? null) ? $od['responsibility_blocks'] : [];
            for ($i = 0; $i < 6; $i++):
                $b = $blocks[$i] ?? null;
                $th = is_array($b) ? (string) ($b['theme'] ?? '') : '';
                $ti = is_array($b) ? (string) ($b['titre'] ?? '') : '';
                $co = is_array($b) ? (string) ($b['corps'] ?? '') : '';
                ?>
                <div class="space-y-2 rounded-lg border border-slate-100 p-3">
                    <input type="text" name="block_theme[]" value="<?= htmlspecialchars($th, ENT_QUOTES, 'UTF-8') ?>" placeholder="Thème (ex. Opérationnel)" class="w-full rounded border border-slate-200 px-2 py-1 text-xs" <?= $stLocked ? 'readonly' : '' ?> <?= !$canSubmit ? 'readonly' : '' ?> />
                    <input type="text" name="block_titre[]" value="<?= htmlspecialchars($ti, ENT_QUOTES, 'UTF-8') ?>" placeholder="Titre" class="w-full rounded border border-slate-200 px-2 py-1 text-sm font-semibold" <?= $stLocked ? 'readonly' : '' ?> <?= !$canSubmit ? 'readonly' : '' ?> />
                    <textarea name="block_corps[]" rows="2" placeholder="Description" class="w-full rounded border border-slate-200 px-2 py-1 text-xs" <?= $stLocked ? 'readonly' : '' ?> <?= !$canSubmit ? 'readonly' : '' ?>><?= htmlspecialchars($co, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            <?php endfor; ?>
        </div>

        <?php if ((!$isEdit || (string) ($openingRow['status'] ?? '') === 'draft') && $canSubmit): ?>
            <button type="submit" class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-black text-white hover:bg-slate-800">Enregistrer le brouillon</button>
        <?php endif; ?>
    </form>
</div>
