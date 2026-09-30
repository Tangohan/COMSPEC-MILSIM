<?php
declare(strict_types=1);
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$grade = is_array($grade ?? null) ? $grade : null;
$filieres = is_array($filieres ?? null) ? $filieres : [];
$qualifications = is_array($qualifications ?? null) ? $qualifications : [];
$isEdit = $grade !== null;
$action = $isEdit
    ? url('back-office/organisation/grades/' . (int) ($grade['id'] ?? 0) . '/update')
    : url('back-office/organisation/grades/store');
?>
<div class="bo-adv">
    <p class="bo-adv__back"><a href="<?= $h(url('back-office/organisation/grades')) ?>">← Échelle de grades</a></p>
    <form method="post" action="<?= $h($action) ?>" class="bo-adv__panel bo-adv__form">
        <?= \App\Core\Csrf::field() ?>
        <div class="bo-adv__form-grid">
            <label>Code
                <input type="text" name="code" required maxlength="32" value="<?= $h((string) ($grade['code'] ?? '')) ?>" <?= $isEdit ? 'readonly' : '' ?>>
            </label>
            <label>Libellé
                <input type="text" name="label" required value="<?= $h((string) ($grade['label'] ?? '')) ?>">
            </label>
            <label>Libellé court
                <input type="text" name="short_label" value="<?= $h((string) ($grade['short_label'] ?? '')) ?>">
            </label>
            <label>Filière
                <select name="filiere_id">
                    <option value="0">—</option>
                    <?php foreach ($filieres as $f): ?>
                        <option value="<?= (int) ($f['id'] ?? 0) ?>" <?= (int) ($grade['filiere_id'] ?? 0) === (int) ($f['id'] ?? 0) ? 'selected' : '' ?>>
                            <?= $h((string) ($f['label'] ?? $f['code'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Ordre hiérarchique
                <input type="number" name="rank_order" min="0" value="<?= (int) ($grade['rank_order'] ?? 0) ?>">
            </label>
            <label>Temps mini dans le grade précédent (mois)
                <input type="number" name="min_time_in_previous_grade_months" min="0" value="<?= $h((string) ($grade['min_time_in_previous_grade_months'] ?? '')) ?>">
            </label>
            <label>Qualification requise
                <select name="required_qualification_id">
                    <option value="0">Aucune</option>
                    <?php foreach ($qualifications as $q): ?>
                        <option value="<?= (int) ($q['id'] ?? 0) ?>" <?= (int) ($grade['required_qualification_id'] ?? 0) === (int) ($q['id'] ?? 0) ? 'selected' : '' ?>>
                            <?= $h((string) (($q['code'] ?? '') . ' — ' . ($q['name'] ?? ''))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Niveau de qualification (id)
                <input type="number" name="required_qualification_level_id" min="0" value="<?= (int) ($grade['required_qualification_level_id'] ?? 0) ?>">
            </label>
        </div>
        <div class="bo-adv__checks">
            <label class="bo-adv__check">
                <input type="checkbox" name="advancement_seniority_enabled" value="1" <?= !empty($grade['advancement_seniority_enabled']) || !$isEdit ? 'checked' : '' ?>>
                Voie ancienneté
            </label>
            <label class="bo-adv__check">
                <input type="checkbox" name="advancement_choice_enabled" value="1" <?= !empty($grade['advancement_choice_enabled']) ? 'checked' : '' ?>>
                Voie au choix
            </label>
        </div>
        <div class="bo-adv__toolbar">
            <button type="submit" class="ath-btn ath-btn--solid"><?= $isEdit ? 'Enregistrer' : 'Créer' ?></button>
            <a class="ath-btn" href="<?= $h(url('back-office/organisation/grades')) ?>">Annuler</a>
        </div>
    </form>
</div>
