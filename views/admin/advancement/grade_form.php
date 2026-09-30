<?php
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$grade = is_array($grade ?? null) ? $grade : null;
$filieres = is_array($filieres ?? null) ? $filieres : [];
$qualifications = is_array($qualifications ?? null) ? $qualifications : [];
$isEdit = $grade !== null;
$action = $isEdit
    ? url('back-office/organisation/grades/' . (int) $grade['id'] . '/update')
    : url('back-office/organisation/grades/store');
$val = static function (string $key, mixed $default = '') use ($grade): string {
    if ($grade === null) {
        return (string) $default;
    }

    return (string) ($grade[$key] ?? $default);
};
?>
<div class="adv-page">
    <?php require __DIR__ . '/_flash.php'; ?>
    <form method="post" action="<?= $h($action) ?>" class="adv-form">
        <?= \App\Core\Csrf::field() ?>
        <label>Code<input name="code" required maxlength="64" value="<?= $h($val('code')) ?>"></label>
        <label>Libellé<input name="label" required maxlength="150" value="<?= $h($val('label')) ?>"></label>
        <label>Libellé court<input name="short_label" maxlength="40" value="<?= $h($val('short_label')) ?>"></label>
        <label>Filière
            <select name="filiere_id">
                <option value="">—</option>
                <?php foreach ($filieres as $filiere): ?>
                    <option value="<?= (int) $filiere['id'] ?>"<?= (string) $val('filiere_id') === (string) $filiere['id'] ? ' selected' : '' ?>><?= $h((string) $filiere['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Ordre<input type="number" name="rank_order" value="<?= $h($val('rank_order', '1')) ?>"></label>
        <label class="adv-check"><input type="checkbox" name="advancement_seniority_enabled" value="1"<?= $val('advancement_seniority_enabled') === '1' ? ' checked' : '' ?>> Voie ancienneté</label>
        <label class="adv-check"><input type="checkbox" name="advancement_choice_enabled" value="1"<?= $val('advancement_choice_enabled') === '1' ? ' checked' : '' ?>> Voie choix</label>
        <label>Temps minimum dans le grade précédent (mois)
            <input type="number" min="0" name="min_time_in_previous_grade_months" value="<?= $h($val('min_time_in_previous_grade_months')) ?>">
        </label>
        <label>Qualification requise
            <input name="required_qualification_code" list="adv-qual-codes" placeholder="Code, ex. CEFEO" autocomplete="off" value="">
            <datalist id="adv-qual-codes">
                <?php foreach ($qualifications as $qual): ?>
                    <option value="<?= $h((string) ($qual['code'] ?? '')) ?>"><?= $h((string) ($qual['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </datalist>
        </label>
        <label>Ou choisir dans le référentiel
            <select name="required_qualification_id">
                <option value="">Aucune</option>
                <?php foreach ($qualifications as $qual): ?>
                    <option value="<?= (int) $qual['id'] ?>"<?= (string) $val('required_qualification_id') === (string) $qual['id'] ? ' selected' : '' ?>><?= $h((string) ($qual['name'] ?? $qual['code'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Niveau requis (identifiant, facultatif)
            <input type="number" name="required_qualification_level_id" value="<?= $h($val('required_qualification_level_id')) ?>">
        </label>
        <div class="adv-actions">
            <button class="ath-btn ath-btn--solid" type="submit"><?= $isEdit ? 'Enregistrer' : 'Créer' ?></button>
            <a class="ath-btn" href="<?= $h(url('back-office/organisation/grades')) ?>">Retour</a>
        </div>
    </form>
    <?php if ($isEdit && empty($grade['archived_at'])): ?>
        <form method="post" action="<?= $h(url('back-office/organisation/grades/' . (int) $grade['id'] . '/archive')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <button class="ath-btn" type="submit">Archiver ce grade</button>
        </form>
    <?php endif; ?>
</div>
