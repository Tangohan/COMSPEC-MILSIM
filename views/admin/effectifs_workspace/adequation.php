<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/effk.php';
require_once __DIR__ . '/partials/adequacy.php';

use App\Services\Organization\EchelonStandards;
use App\Services\Organization\GradeLevel;
use App\Services\Organization\OrgAdequacyEngine;
use App\Services\Organization\OrgAdequacySettings;

/*
 * Effectifs > Chaîne > Adéquation de l’organisation : référentiel des échelons (valeurs par défaut sourcées) et
 * réglages de la communauté (règles actives, marge de surqualification, attentes par échelon).
 * Enregistrement : POST chaine/adequation, champs org_adequacy[…] (OrgAdequacySettings::sanitize).
 */

$settings = is_array($adequacySettings ?? null) ? $adequacySettings : OrgAdequacySettings::defaults();
$profile = (string) ($adequacyProfile ?? EchelonStandards::PROFILE_FR);
$standards = is_array($adequacyStandards ?? null) ? $adequacyStandards : [];
$gradeLabels = is_array($adequacyGradeLabels ?? null) ? $adequacyGradeLabels : [];
$qualifications = is_array($adequacyQualifications ?? null) ? $adequacyQualifications : [];
$jobRoles = is_array($adequacyJobRoles ?? null) ? $adequacyJobRoles : [];
$canEdit = !empty($canEditAdequacy);
$csrf = (string) ($csrfToken ?? '');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$overrides = is_array($settings['echelons'] ?? null) ? $settings['echelons'] : [];
$profileLabels = [
    OrgAdequacySettings::PROFILE_AUTO => 'Selon la doctrine de la communauté',
    EchelonStandards::PROFILE_FR => 'Référentiel français',
    EchelonStandards::PROFILE_US => 'Référentiel américain',
    EchelonStandards::PROFILE_MY => 'Référentiel malaisien (modèle britannique)',
    EchelonStandards::PROFILE_DE => 'Référentiel allemand (Bundeswehr)',
    EchelonStandards::PROFILE_RU => 'Référentiel russe',
    EchelonStandards::PROFILE_CN => 'Référentiel chinois (Armée populaire de libération)',
    EchelonStandards::PROFILE_NATO => 'Référentiel OTAN générique',
];
$dis = $canEdit ? '' : ' disabled';

/** Sélecteur de grade (code OTAN) : « par défaut » sauf surcharge. */
$gradeSelect = static function (string $name, string $id, int $default, ?string $current, string $aria) use ($h, $gradeLabels, $dis): string {
    $out = '<select class="effk-sel" name="' . $h($name) . '" id="' . $h($id) . '" aria-label="' . $h($aria) . '"' . $dis . '>'
        . '<option value="">' . $h('Par défaut : ' . GradeLevel::rangeLabel($default, $default, $gradeLabels)) . '</option>';
    foreach (GradeLevel::codes() as $code) {
        $level = (int) GradeLevel::fromCode($code);
        $labels = $gradeLabels[$level] ?? [];
        $out .= '<option value="' . $h($code) . '"' . ($current === $code ? ' selected' : '') . '>'
            . $h($code . ($labels !== [] ? ' · ' . implode(' / ', $labels) : '')) . '</option>';
    }

    return $out . '</select>';
};
/** Sélecteur multiple (qualifications, emplois). */
$multi = static function (string $name, string $id, array $options, array $selected, string $aria) use ($h, $dis): string {
    if ($options === []) {
        return '<span class="effk-muted">Aucun élément dans le référentiel</span>';
    }
    $out = '<select class="effk-sel" multiple name="' . $h($name) . '[]" id="' . $h($id) . '" aria-label="' . $h($aria) . '"' . $dis . '>';
    foreach ($options as $o) {
        $oid = (int) ($o['id'] ?? 0);
        $out .= '<option value="' . $oid . '"' . (in_array($oid, $selected, true) ? ' selected' : '') . '>' . $h((string) ($o['label'] ?? '')) . '</option>';
    }

    return $out . '</select>';
};
?>
<?= adq_styles() ?>
<div class="effk adq-scope" data-eff-adequacy>
    <?php
    $backLink = '<a class="effk-btn" href="' . $h(effectifs_workspace_url('chaine') . '#eff-adequacy') . '">' . effk_icon('route') . '<span>' . $h('Retour à la chaîne') . '</span></a>';
    echo effk_page_head(
        'Organisation',
        'Adéquation de l’organisation',
        'Ce que chaque échelon attend de son chef et de son adjoint : grade, qualifications de commandement, effectif et sous-unités. La chaîne de commandement, la fiche membre et les alertes RH comparent l’organisation réelle à ces attentes.',
        $backLink
    );
    $profileName = $profileLabels[EchelonStandards::sanitizeProfile($profile)] ?? 'Référentiel français';
    ?>

    <section class="effk-panel">
        <div class="effk-fold__body">
            <p class="effk-fold__lead">Les écarts portent sur la capacité de commandement d’un poste au regard de son échelon, jamais sur la valeur des personnes.</p>
            <ul class="adq-rules">
                <li><b>Sous-capacité de commandement.</b> <span>Un chef ou un adjoint dont le grade, la qualification ou l’emploi est en dessous de ce que demande l’échelon.</span></li>
                <li><b>Surqualification.</b> <span>Un grade nettement au-dessus de l’échelon tenu, ou des qualifications d’un échelon supérieur : une expérience qui pourrait servir plus haut.</span></li>
            </ul>
            <p class="adq-note">Grades comparés par leur code OTAN (STANAG 2116), renseigné dans le référentiel des grades : un grade sans équivalence n’est pas évalué.</p>
            <p class="adq-note"><span>Référentiel retenu :</span> <b><?= $h($profileName) ?></b></p>
        </div>
    </section>

    <form method="post" action="<?= $h(effectifs_workspace_url('chaine/adequation')) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <section class="effk-panel">
            <div class="effk-fold__body">
                <h3 class="effk-k">Règles</h3>
                <p>
                    <input type="hidden" name="org_adequacy[enabled]" value="0">
                    <label><input type="checkbox" name="org_adequacy[enabled]" value="1"<?= !empty($settings['enabled']) ? ' checked' : '' ?><?= $dis ?>> Contrôler l’adéquation de l’organisation</label>
                </p>
                <p>
                    <label for="adq-profile">Attentes appliquées</label>
                    <select class="effk-sel" id="adq-profile" name="org_adequacy[profile]"<?= $dis ?>>
                        <?php foreach ($profileLabels as $pk => $pl): ?>
                            <option value="<?= $h($pk) ?>"<?= ($settings['profile'] ?? '') === $pk ? ' selected' : '' ?>><?= $h($pl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="adq-margin">Surqualification signalée à partir de</label>
                    <input class="effk-sel" type="number" min="1" max="6" id="adq-margin" name="org_adequacy[overqualification_margin]" value="<?= (int) ($settings['overqualification_margin'] ?? 2) ?>"<?= $dis ?>>
                    <span class="effk-muted">niveaux de grade au-dessus de la plage attendue</span>
                </p>
                <ul class="adq-rules">
                    <?php foreach (OrgAdequacySettings::RULES as $rk => $rl): ?>
                        <li>
                            <input type="hidden" name="org_adequacy[rules][<?= $h($rk) ?>]" value="0">
                            <label><input type="checkbox" name="org_adequacy[rules][<?= $h($rk) ?>]" value="1"<?= !empty($settings['rules'][$rk]) ? ' checked' : '' ?><?= $dis ?>> <?= $h($rl) ?></label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="effk-panel" aria-labelledby="adq-echelons-title">
            <div class="effk-fold__body">
                <h3 class="effk-k" id="adq-echelons-title">Référentiel des échelons</h3>
                <p class="adq-note">Valeur laissée sur « par défaut » ou vide : la valeur du référentiel s’applique. Les qualifications et emplois choisis deviennent attendus du chef de l’échelon.</p>
                <div class="effk-table-wrap" role="region" aria-label="Référentiel des échelons" tabindex="0">
                    <table class="adq-table">
                        <thead>
                            <tr>
                                <th scope="col">Échelon</th>
                                <th scope="col">Chef attendu</th>
                                <th scope="col">Adjoint attendu</th>
                                <th scope="col">Effectif et sous-unités</th>
                                <th scope="col">Qualifications et emploi du chef</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($standards as $key => $std):
                            $o = is_array($overrides[$key] ?? null) ? $overrides[$key] : [];
                            $def = is_array($std['default'] ?? null) ? $std['default'] : $std;
                            $f = 'org_adequacy[echelons][' . $key . ']';
                            $id = 'adq-' . $key;
                            $depDefault = is_array($def['deputy_grade'] ?? null) ? $def['deputy_grade'] : null;
                            ?>
                            <tr>
                                <td>
                                    <b><?= $h((string) $std['label']) ?></b>
                                    <small class="effk-muted effk-block"><?= $h((string) $std['composition']) ?></small>
                                    <small class="effk-muted effk-block"><span>Sources</span> : <?= implode(' ; ', array_map(static fn ($src): string => '<span>' . $h((string) $src) . '</span>', $std['sources'])) ?></small>
                                    <input type="hidden" name="<?= $h($f) ?>[enabled]" value="0">
                                    <label><input type="checkbox" name="<?= $h($f) ?>[enabled]" value="1"<?= !empty($std['enabled']) ? ' checked' : '' ?><?= $dis ?>> Évaluer cet échelon</label>
                                </td>
                                <td>
                                    <b><?= $h((string) $std['leader_label']) ?></b>
                                    <small class="effk-muted effk-block">Retenu : <?= $h(GradeLevel::rangeLabel((int) $std['leader_grade'][0], (int) $std['leader_grade'][1], $gradeLabels)) ?></small>
                                    <label class="effk-block" for="<?= $h($id) ?>-lmin">Grade minimum</label>
                                    <?= $gradeSelect($f . '[leader_grade_min]', $id . '-lmin', (int) $def['leader_grade'][0], isset($o['leader_grade_min']) ? (string) $o['leader_grade_min'] : null, 'Grade minimum du chef') ?>
                                    <label class="effk-block" for="<?= $h($id) ?>-lmax">Grade maximum</label>
                                    <?= $gradeSelect($f . '[leader_grade_max]', $id . '-lmax', (int) $def['leader_grade'][1], isset($o['leader_grade_max']) ? (string) $o['leader_grade_max'] : null, 'Grade maximum du chef') ?>
                                </td>
                                <td>
                                    <b><?= $h((string) $std['deputy_label']) ?></b>
                                    <?php if (!empty($std['deputy_from_subunit'])): ?><small class="effk-muted effk-block">Le chef de sous-unité le plus ancien tient lieu d’adjoint.</small><?php endif; ?>
                                    <label class="effk-block" for="<?= $h($id) ?>-dreq">Adjoint exigé</label>
                                    <select class="effk-sel" id="<?= $h($id) ?>-dreq" name="<?= $h($f) ?>[deputy_required]"<?= $dis ?>>
                                        <option value=""><?= $h('Par défaut : ' . (!empty($def['deputy_required']) ? 'oui' : 'non')) ?></option>
                                        <option value="1"<?= array_key_exists('deputy_required', $o) && $o['deputy_required'] === true ? ' selected' : '' ?>>Oui</option>
                                        <option value="0"<?= array_key_exists('deputy_required', $o) && $o['deputy_required'] === false ? ' selected' : '' ?>>Non</option>
                                    </select>
                                    <?php $depBase = $depDefault ?? $def['leader_grade']; ?>
                                    <label class="effk-block" for="<?= $h($id) ?>-dmin">Grade minimum</label>
                                    <?= $gradeSelect($f . '[deputy_grade_min]', $id . '-dmin', (int) $depBase[0], isset($o['deputy_grade_min']) ? (string) $o['deputy_grade_min'] : null, 'Grade minimum de l’adjoint') ?>
                                    <label class="effk-block" for="<?= $h($id) ?>-dmax">Grade maximum</label>
                                    <?= $gradeSelect($f . '[deputy_grade_max]', $id . '-dmax', (int) $depBase[1], isset($o['deputy_grade_max']) ? (string) $o['deputy_grade_max'] : null, 'Grade maximum de l’adjoint') ?>
                                </td>
                                <td>
                                    <label class="effk-block" for="<?= $h($id) ?>-smin">Effectif minimum</label>
                                    <input class="effk-sel" type="number" min="0" id="<?= $h($id) ?>-smin" name="<?= $h($f) ?>[strength_min]" value="<?= isset($o['strength_min']) ? (int) $o['strength_min'] : '' ?>" placeholder="<?= (int) $def['strength'][0] ?>"<?= $dis ?>>
                                    <label class="effk-block" for="<?= $h($id) ?>-smax">Effectif maximum</label>
                                    <input class="effk-sel" type="number" min="0" id="<?= $h($id) ?>-smax" name="<?= $h($f) ?>[strength_max]" value="<?= isset($o['strength_max']) ? (int) $o['strength_max'] : '' ?>" placeholder="<?= (int) $def['strength'][1] ?>"<?= $dis ?>>
                                    <?php if ((int) $def['subunits'][1] > 0): ?>
                                        <label class="effk-block" for="<?= $h($id) ?>-umin">Sous-unités minimum</label>
                                        <input class="effk-sel" type="number" min="0" id="<?= $h($id) ?>-umin" name="<?= $h($f) ?>[subunits_min]" value="<?= isset($o['subunits_min']) ? (int) $o['subunits_min'] : '' ?>" placeholder="<?= (int) $def['subunits'][0] ?>"<?= $dis ?>>
                                    <?php endif; ?>
                                    <small class="effk-muted effk-block">Retenu : <?= $h(OrgAdequacyEngine::rangeText($std['strength'])) ?><?= (int) $std['subunits'][1] > 0 ? ' · ' . $h(OrgAdequacyEngine::rangeText($std['subunits'])) . ' sous-unités' : '' ?></small>
                                </td>
                                <td>
                                    <label class="effk-block" for="<?= $h($id) ?>-q">Qualifications de commandement</label>
                                    <?= $multi($f . '[qualification_ids]', $id . '-q', $qualifications, array_map('intval', $std['qualification_ids']), 'Qualifications de commandement attendues') ?>
                                    <?php if (trim((string) ($std['qualification_hint'] ?? '')) !== ''): ?><small class="effk-muted effk-block">Repère : <?= $h((string) $std['qualification_hint']) ?></small><?php endif; ?>
                                    <label class="effk-block" for="<?= $h($id) ?>-j">Emploi attendu</label>
                                    <?= $multi($f . '[job_role_ids]', $id . '-j', $jobRoles, array_map('intval', $std['job_role_ids']), 'Emploi attendu du chef') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($canEdit): ?>
                    <div class="effk-fold__foot">
                        <button type="submit" name="reset" value="1" class="effk-btn">Rétablir les valeurs par défaut</button>
                        <button type="submit" class="effk-btn is-dark">Enregistrer les réglages</button>
                    </div>
                <?php else: ?>
                    <p class="adq-note">Lecture seule : les réglages sont modifiés par les gestionnaires des affectations.</p>
                <?php endif; ?>
            </div>
        </section>
    </form>
</div>
