<?php
require __DIR__ . '/_helpers.php';
$grade = is_array($grade ?? null) ? $grade : null;
$filieres = is_array($filieres ?? null) ? $filieres : [];
$qualifications = is_array($qualifications ?? null) ? $qualifications : [];
$passes = is_array($passes ?? null) ? $passes : [];
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
    <form method="post" action="<?= adv_h($action) ?>" class="adv-form">
        <?= \App\Core\Csrf::field() ?>
        <p class="adv-muted">Grade de la communauté : vous pouvez adapter les libellés. Un code déjà présent (SD2, SGT…) est réutilisé, pas dupliqué.</p>
        <label>Code <?= adv_info('Code', 'Identifiant stable (SD2, SGT, COL…). On le garde même si le libellé change.') ?>
            <input name="code" required maxlength="64" value="<?= adv_h($val('code')) ?>">
        </label>
        <label>Libellé<input name="label" required maxlength="150" value="<?= adv_h($val('label')) ?>"></label>
        <label>Libellé court<input name="short_label" maxlength="40" value="<?= adv_h($val('short_label')) ?>"></label>
        <label>Filière <?= adv_info('Filière', 'L’avancement au choix et à l’ancienneté ne saute pas d’une filière à l’autre.') ?>
            <select name="filiere_id" class="adv-search" data-placeholder="Aucune">
                <option value="">Aucune</option>
                <?php foreach ($filieres as $filiere): ?>
                    <option value="<?= (int) $filiere['id'] ?>"<?= (string) $val('filiere_id') === (string) $filiere['id'] ? ' selected' : '' ?>><?= adv_h((string) $filiere['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Ordre <?= adv_info('Ordre', '1 = plus junior dans la filière. L’éligibilité exige que le grade actuel soit exactement l’ordre précédent.') ?>
            <input type="number" name="rank_order" min="1" value="<?= adv_h($val('rank_order', '1')) ?>">
        </label>
        <label class="adv-check"><input type="checkbox" name="advancement_seniority_enabled" value="1"<?= $val('advancement_seniority_enabled') === '1' ? ' checked' : '' ?>> Voie ancienneté <?= adv_info('Ancienneté', 'Dès que le temps mini est atteint, le grade suivant s’attribue tout seul. Pas de commission.') ?></label>
        <label class="adv-check"><input type="checkbox" name="advancement_choice_enabled" value="1"<?= $val('advancement_choice_enabled') === '1' ? ' checked' : '' ?>> Voie choix <?= adv_info('Choix', 'Campagne, candidature, commission, publication. C’est ici qu’un passage exceptionnel est possible.') ?></label>
        <label>Temps minimum dans le grade précédent (mois)
            <input type="number" min="0" name="min_time_in_previous_grade_months" value="<?= adv_h($val('min_time_in_previous_grade_months')) ?>">
        </label>
        <label>Qualification requise <?= adv_info('Qualification', 'Si renseignée, le dossier doit détenir ce brevet (et le niveau, le cas échéant) pour être éligible.') ?>
            <select name="required_qualification_id" class="adv-search" data-placeholder="Aucune">
                <option value="">Aucune</option>
                <?php foreach ($qualifications as $qual): ?>
                    <option value="<?= (int) $qual['id'] ?>"<?= (string) $val('required_qualification_id') === (string) $qual['id'] ? ' selected' : '' ?>><?= adv_h((string) ($qual['name'] ?? $qual['code'] ?? '')) ?> (<?= adv_h((string) ($qual['code'] ?? '')) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Code qualification (si absent de la liste)
            <input name="required_qualification_code" placeholder="Ex. CEFEO" autocomplete="off" value="">
        </label>
        <label>Niveau requis (identifiant, facultatif)
            <input type="number" name="required_qualification_level_id" value="<?= adv_h($val('required_qualification_level_id')) ?>">
        </label>
        <label>PASS requis <?= adv_info('PASS', 'Pack de conditions (formation, heures, avis hiérarchique…). Créé dans Organisation → PASS RH.') ?>
            <select name="required_pass_id" class="adv-search" data-placeholder="Aucun">
                <option value="">Aucun</option>
                <?php foreach ($passes as $pass): ?>
                    <option value="<?= (int) $pass['id'] ?>"<?= (string) $val('required_pass_id') === (string) $pass['id'] ? ' selected' : '' ?>><?= adv_h((string) ($pass['label'] ?? $pass['code'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="adv-actions">
            <button class="ath-btn ath-btn--solid" type="submit"><?= $isEdit ? 'Enregistrer' : 'Créer' ?></button>
            <a class="ath-btn" href="<?= adv_h(url('back-office/organisation/grades')) ?>">Retour</a>
        </div>
    </form>
    <?php if ($isEdit && empty($grade['archived_at'])): ?>
        <form method="post" action="<?= adv_h(url('back-office/organisation/grades/' . (int) $grade['id'] . '/archive')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <button class="ath-btn" type="submit">Archiver ce grade</button>
        </form>
    <?php endif; ?>
</div>
<script src="<?= adv_h(asset_url('assets/js/back-office-advancement.js')) ?>"></script>
