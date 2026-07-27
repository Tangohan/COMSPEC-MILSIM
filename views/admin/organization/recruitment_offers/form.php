<?php
declare(strict_types=1);

/**
 * Rédaction d’un avis de vacance — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office. Une offre qui n’est plus un
 * brouillon reste consultable mais non modifiable : tous les champs passent en lecture
 * seule et le bouton d’enregistrement disparaît.
 *
 * @var array<string,mixed>|null $opening
 * @var array<string,mixed>|null $openingDecoded
 * @var list<array<string,mixed>> $units
 * @var list<array<string,mixed>> $jobRoles
 * @var array<string,string> $personnelCategories
 * @var array<string,string> $armDomains
 * @var array<string,string> $clearanceLevels
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$opening = $opening ?? null;
$od = is_array($openingDecoded ?? null) ? $openingDecoded : [];
$units = is_array($units ?? null) ? $units : [];
$jobRoles = is_array($jobRoles ?? null) ? $jobRoles : [];
$personnelCategories = is_array($personnelCategories ?? null) ? $personnelCategories : [];
$armDomains = is_array($armDomains ?? null) ? $armDomains : [];
$clearanceLevels = is_array($clearanceLevels ?? null) ? $clearanceLevels : [];

$isEdit = $opening !== null;
/** @var array<string,mixed> $openingRow Toujours un tableau : la page de création n’a pas de ligne. */
$openingRow = $isEdit && is_array($opening) ? $opening : [];
$canSubmit = $units !== [];
$stLocked = $isEdit && (string) ($openingRow['status'] ?? '') !== 'draft';

// Un champ est verrouillé si l’offre n’est plus un brouillon, ou si aucune unité n’existe
// encore : dans les deux cas, la saisie ne mènerait à rien.
$readonly = ($stLocked || !$canSubmit) ? ' readonly' : '';
$disabled = ($stLocked || !$canSubmit) ? ' disabled' : '';

$value = static fn (string $key): string => (string) ($od[$key] ?? '');
$selected = static fn (string $key, string $candidate): string => ((string) ($od[$key] ?? '')) === $candidate ? ' selected' : '';

$formAction = $isEdit
    ? url('back-office/recruitment/offers/' . (int) ($openingRow['id'] ?? 0) . '/update')
    : url('back-office/recruitment/offers/store');

$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
?>
<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>

<?php if ($stLocked): ?>
<div class="ath-note" style="background:#fdf3e2;border-color:#f2ddb4;">
    <p class="ath-note__title" style="color:#8a5a06;">Offre publiée : lecture seule</p>
    <p class="ath-note__text" style="color:#8a5a06;">
        Cette offre n’est plus un brouillon. Elle reste consultable ici, mais seule la clôture
        est possible, depuis le registre des offres.
    </p>
</div>
<?php endif; ?>

<?php if (!$canSubmit): ?>
<div class="ath-warn">
    <p class="ath-warn__title">Aucune unité définie</p>
    <p class="ath-warn__text">
        Un avis de vacance se rattache à une unité porteuse. Créez d’abord un groupe ou une
        sous-structure, puis revenez rédiger l’avis.
    </p>
    <div class="ath-form__actions" style="border-top:0;padding-top:11px;">
        <a href="<?= $h(url('back-office/groups/create')) ?>" class="ath-btn ath-btn--solid">Créer une unité</a>
        <a href="<?= $h(url('back-office/groups')) ?>" class="ath-btn">Voir les groupes</a>
    </div>
</div>
<?php endif; ?>

<div class="ath-form__actions" style="border-top:0;margin:0 0 16px;padding-top:0;">
    <a href="<?= $h(url('back-office/recruitment/offers')) ?>" class="ath-btn">Retour au registre</a>
</div>

<form method="post" action="<?= $h($formAction) ?>">
    <?= \App\Core\Csrf::field() ?>

    <div class="ath-form ath-rise">
        <div class="ath-form__head">
            <span class="ath-form__title">Identification</span>
            <span class="ath-form__hint">Les champs structurés alimentent la fiche publique.</span>
        </div>
        <div class="ath-form__grid">
            <label class="ath-field">
                <span class="ath-field__label">Unité porteuse *</span>
                <select name="unit_id" class="ath-field__select"<?= $disabled ?>>
                    <option value="">— Choisir —</option>
                    <?php foreach ($units as $u): ?>
                        <?php $unitId = (string) (int) ($u['id'] ?? 0); ?>
                    <option value="<?= $h($unitId) ?>"<?= $selected('unit_id', $unitId) ?>><?= $h((string) ($u['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Emploi métier</span>
                <select name="personnel_job_role_id" class="ath-field__select"<?= $disabled ?>>
                    <option value="">— Aucun —</option>
                    <?php foreach ($jobRoles as $jr): ?>
                        <?php $roleId = (string) (int) ($jr['id'] ?? 0); ?>
                    <option value="<?= $h($roleId) ?>"<?= $selected('personnel_job_role_id', $roleId) ?>><?= $h((string) ($jr['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="ath-field__help">Issu du référentiel des emplois métier.</span>
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Titre de l’avis *</span>
                <input type="text" name="title" value="<?= $h($value('title')) ?>" maxlength="180" required class="ath-field__input"<?= $readonly ?>>
            </label>
        </div>
        <div class="ath-form__grid ath-form__grid--wide" style="margin-top:14px;">
            <label class="ath-field">
                <span class="ath-field__label">Accroche</span>
                <textarea name="summary" rows="2" class="ath-field__textarea"<?= $readonly ?>><?= $h($value('summary')) ?></textarea>
                <span class="ath-field__help">Une ou deux phrases, affichées dans la liste des offres.</span>
            </label>
        </div>
    </div>

    <div class="ath-form ath-rise">
        <div class="ath-form__head">
            <span class="ath-form__title">Classification affichée</span>
            <span class="ath-form__hint">Sert au filtrage et à l’en-tête de la fiche publique.</span>
        </div>
        <div class="ath-form__grid">
            <label class="ath-field">
                <span class="ath-field__label">Catégorie de personnel</span>
                <select name="personnel_category" class="ath-field__select"<?= $disabled ?>>
                    <option value="">— Non précisée —</option>
                    <?php foreach ($personnelCategories as $key => $label): ?>
                    <option value="<?= $h((string) $key) ?>"<?= $selected('personnel_category', (string) $key) ?>><?= $h((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Arme ou domaine</span>
                <select name="arm_domain" class="ath-field__select"<?= $disabled ?>>
                    <option value="">— Non précisé —</option>
                    <?php foreach ($armDomains as $key => $label): ?>
                    <option value="<?= $h((string) $key) ?>"<?= $selected('arm_domain', (string) $key) ?>><?= $h((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Niveau d’habilitation demandé</span>
                <select name="clearance_level" class="ath-field__select"<?= $disabled ?>>
                    <option value="">— Aucun —</option>
                    <?php foreach ($clearanceLevels as $key => $label): ?>
                    <option value="<?= $h((string) $key) ?>"<?= $selected('clearance_level', (string) $key) ?>><?= $h((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Engagement</span>
                <input type="text" name="employment_contract_label" value="<?= $h($value('employment_contract_label')) ?>" maxlength="120" class="ath-field__input" placeholder="Contrat 20 ans maximum"<?= $readonly ?>>
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Contexte d’emploi</span>
                <input type="text" name="employment_context_label" value="<?= $h($value('employment_context_label')) ?>" maxlength="120" class="ath-field__input" placeholder="Unité de combat"<?= $readonly ?>>
            </label>
        </div>
    </div>

    <div class="ath-form ath-rise">
        <div class="ath-form__head">
            <span class="ath-form__title">Contenu de l’avis</span>
        </div>
        <div class="ath-form__grid ath-form__grid--wide">
            <label class="ath-field">
                <span class="ath-field__label">Accroche mission</span>
                <textarea name="mission_lead" rows="3" class="ath-field__textarea"<?= $readonly ?>><?= $h($value('mission_lead')) ?></textarea>
                <span class="ath-field__help">Ouvre la fiche détaillée.</span>
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Description complète</span>
                <textarea name="description" rows="7" class="ath-field__textarea"<?= $readonly ?>><?= $h($value('description')) ?></textarea>
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Exigences</span>
                <textarea name="requirements_lines" rows="5" class="ath-field__textarea" style="font-family:var(--ath-mono);"<?= $readonly ?>><?= $h($value('requirements_lines')) ?></textarea>
                <span class="ath-field__help">Une exigence par ligne. Chaque ligne devient une puce sur la fiche publique.</span>
            </label>
            <label class="ath-field">
                <span class="ath-field__label">Avis technique</span>
                <textarea name="technical_notice" rows="3" class="ath-field__textarea"<?= $readonly ?>><?= $h($value('technical_notice')) ?></textarea>
                <span class="ath-field__help">Affiché dans un encadré distinct.</span>
            </label>
        </div>
    </div>

    <div class="ath-form ath-rise">
        <div class="ath-form__head">
            <span class="ath-form__title">Profil candidat</span>
            <span class="ath-form__hint">Jusqu’à 8 lignes. Les lignes vides sont ignorées.</span>
        </div>
        <?php
        $profileItems = is_array($od['candidate_profile_items'] ?? null) ? $od['candidate_profile_items'] : [];
        for ($i = 0; $i < 8; $i++):
            $row = $profileItems[$i] ?? null;
            $rubrique = is_array($row) ? (string) ($row['rubrique'] ?? '') : '';
            $detail = is_array($row) ? (string) ($row['detail'] ?? '') : '';
            ?>
        <div class="ath-form__grid" style="margin-bottom:9px;">
            <label class="ath-field">
                <span class="ath-field__label"<?= $i === 0 ? '' : ' style="visibility:hidden;"' ?>>Rubrique</span>
                <input type="text" name="profile_rubrique[]" value="<?= $h($rubrique) ?>" maxlength="120" class="ath-field__input" placeholder="Rubrique"<?= $readonly ?>>
            </label>
            <label class="ath-field">
                <span class="ath-field__label"<?= $i === 0 ? '' : ' style="visibility:hidden;"' ?>>Détail</span>
                <input type="text" name="profile_detail[]" value="<?= $h($detail) ?>" maxlength="255" class="ath-field__input" placeholder="Détail"<?= $readonly ?>>
            </label>
        </div>
        <?php endfor; ?>
    </div>

    <div class="ath-form ath-rise">
        <div class="ath-form__head">
            <span class="ath-form__title">Grands axes</span>
            <span class="ath-form__hint">Jusqu’à 6 blocs. Les blocs vides sont ignorés.</span>
        </div>
        <?php
        $blocks = is_array($od['responsibility_blocks'] ?? null) ? $od['responsibility_blocks'] : [];
        for ($i = 0; $i < 6; $i++):
            $block = $blocks[$i] ?? null;
            $theme = is_array($block) ? (string) ($block['theme'] ?? '') : '';
            $titre = is_array($block) ? (string) ($block['titre'] ?? '') : '';
            $corps = is_array($block) ? (string) ($block['corps'] ?? '') : '';
            ?>
        <div class="ath-meter" style="margin-bottom:10px;padding:11px 12px;">
            <div class="ath-form__grid">
                <label class="ath-field">
                    <span class="ath-field__label">Thème</span>
                    <input type="text" name="block_theme[]" value="<?= $h($theme) ?>" maxlength="120" class="ath-field__input" placeholder="Opérationnel"<?= $readonly ?>>
                </label>
                <label class="ath-field">
                    <span class="ath-field__label">Titre</span>
                    <input type="text" name="block_titre[]" value="<?= $h($titre) ?>" maxlength="180" class="ath-field__input" placeholder="Titre du bloc"<?= $readonly ?>>
                </label>
            </div>
            <label class="ath-field" style="margin-top:9px;">
                <span class="ath-field__label">Description</span>
                <textarea name="block_corps[]" rows="2" class="ath-field__textarea" placeholder="Description"<?= $readonly ?>><?= $h($corps) ?></textarea>
            </label>
        </div>
        <?php endfor; ?>
    </div>

    <?php if ((!$isEdit || (string) ($openingRow['status'] ?? '') === 'draft') && $canSubmit): ?>
    <div class="ath-form__actions" style="border-top:0;padding-top:0;">
        <button type="submit" class="ath-btn ath-btn--solid">Enregistrer le brouillon</button>
        <a href="<?= $h(url('back-office/recruitment/offers')) ?>" class="ath-btn">Annuler</a>
    </div>
    <?php endif; ?>
</form>
