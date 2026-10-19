<?php
declare(strict_types=1);

/**
 * Création / modification d’un PASS RH : identité, usages, conditions (champs utiles selon le type),
 * aperçu en direct et, pour un PASS enregistré, essai sur un membre.
 *
 * @var array<string, mixed>|null $pass
 * @var list<array<string, mixed>> $passConditions
 * @var list<array{type: string, label: string, preview: string}> $passCatalog
 * @var list<array{value: string, label: string}> $passAvisKinds
 * @var list<array{value: string, label: string}> $passBilanKinds
 * @var list<array<string, mixed>> $passCourses
 * @var list<array{id: int, label: string}> $passQualifications
 * @var list<array{id: int, label: string}> $passGrades
 * @var list<string> $passHourCategories
 * @var array<string, list<string>> $passFieldMap
 * @var array<string, array{label: string, types: list<string>}> $passFamilies
 * @var bool $passSchemaReady
 */

use App\Services\Personnel\Pass\PassConditionEngine as E;
use App\Support\PassConditionSummary;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pass = is_array($pass ?? null) ? $pass : null;
$isEdit = $pass !== null;
$conditions = is_array($passConditions ?? null) ? $passConditions : [];
$catalog = is_array($passCatalog ?? null) ? $passCatalog : [];
$avisKinds = is_array($passAvisKinds ?? null) ? $passAvisKinds : [];
$bilanKinds = is_array($passBilanKinds ?? null) ? $passBilanKinds : [];
$courses = is_array($passCourses ?? null) ? $passCourses : [];
$quals = is_array($passQualifications ?? null) ? $passQualifications : [];
$grades = is_array($passGrades ?? null) ? $passGrades : [];
$cats = is_array($passHourCategories ?? null) ? $passHourCategories : [];
$ready = !empty($passSchemaReady);
$fieldMap = is_array($passFieldMap ?? null) ? $passFieldMap : [];
if ($fieldMap === []) {
    foreach ($catalog as $row) {
        $fieldMap[$row['type']] = E::fieldsFor($row['type']);
    }
}
$families = is_array($passFamilies ?? null) ? $passFamilies : E::families();
$catalogByType = [];
foreach ($catalog as $row) {
    $catalogByType[$row['type']] = $row;
}

$lookups = ['courses' => [], 'qualifications' => [], 'grades' => []];
foreach ($courses as $course) {
    $lookups['courses'][(int) ($course['id'] ?? 0)] = (string) ($course['title'] ?? $course['name'] ?? ('#' . ($course['id'] ?? '')));
}
foreach ($quals as $q) {
    $lookups['qualifications'][(int) $q['id']] = (string) $q['label'];
}
foreach ($grades as $g) {
    $lookups['grades'][(int) $g['id']] = (string) $g['label'];
}

$val = static fn (string $key, mixed $default = ''): string => $pass === null ? (string) $default : (string) ($pass[$key] ?? $default);
$on = static fn (string $key, bool $default = true): bool => $pass === null ? $default : !empty($pass[$key]);
$logic = $val('logic', 'all') === 'any' ? 'any' : 'all';

$formAction = $isEdit
    ? url('back-office/organisation/passes/' . (int) $pass['id'] . '/update')
    : url('back-office/organisation/passes/store');
$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');

$icons = [
    E::TYPE_LMS => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v5c3 2.5 9 2.5 12 0v-5"/>',
    E::TYPE_QUALIFICATION => '<circle cx="12" cy="9" r="5"/><path d="M8.5 13 7 21l5-3 5 3-1.5-8"/>',
    E::TYPE_RAW_HOURS => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
    E::TYPE_VALIDATED_HOURS => '<path d="M20.5 12A8.5 8.5 0 1 1 12 3.5"/><path d="M12 7.5V12l3 2"/><path d="m15.5 5.5 2 2 4-4"/>',
    E::TYPE_CATEGORY_HOURS => '<circle cx="10" cy="12" r="7"/><path d="M10 8.5V12l2.5 1.5"/><path d="M17.5 3.5h4v4l-4 4"/>',
    E::TYPE_MIN_GRADE => '<path d="m5 11 7-5 7 5"/><path d="m5 17 7-5 7 5"/>',
    E::TYPE_HIERARCHICAL_AVIS => '<path d="M4 5h16v11H9l-5 4z"/><path d="m9 10.5 2 2 4-4"/>',
    E::TYPE_BILAN_RATING => '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/>',
];
$icon = static fn (string $type): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    . ($icons[$type] ?? '<circle cx="12" cy="12" r="8"/>') . '</svg>';

$usages = [
    'use_for_post' => ['Poste ouvert', 'Conditionne la candidature à un poste de l’ORBAT.', false],
    'use_for_advancement' => ['Avancement', 'Exigé pour proposer une promotion de grade.', false],
    'use_for_notation' => ['Notation', 'Pris en compte dans les campagnes de notation.', true],
];

/**
 * Carte d’une condition. Tous les champs sont présents (les tableaux postés restent alignés),
 * seuls ceux du type choisi sont visibles.
 *
 * @param array<string, mixed> $c
 */
$renderCondition = static function (array $c) use ($h, $catalog, $catalogByType, $fieldMap, $courses, $quals, $grades, $cats, $avisKinds, $bilanKinds, $lookups, $icon): string {
    $type = (string) ($c['condition_type'] ?? '');
    $fields = $fieldMap[$type] ?? [];
    $show = static fn (string $f): string => in_array($f, $fields, true) ? '' : ' hidden';
    $sum = PassConditionSummary::describe($c, $lookups);
    $sel = static fn (mixed $a, mixed $b): string => (string) $a !== '' && (string) $a === (string) $b ? ' selected' : '';
    $tv = (float) ($c['threshold_value'] ?? 0);
    // 40.00 → « 40 », 3.50 → « 3.5 » (sans rogner les zéros d’un entier).
    $threshold = $tv <= 0 ? '' : (abs($tv - round($tv)) < 0.0001 ? (string) (int) round($tv) : (string) round($tv, 1));
    $unit = $type === E::TYPE_BILAN_RATING ? 'note' : 'h';
    ob_start();
    ?>
    <li class="psf-cond<?= $sum['missing'] !== '' ? ' is-incomplete' : '' ?>" data-pass-condition data-type="<?= $h($type) ?>">
        <div class="psf-cond__head">
            <span class="psf-cond__ico" data-pass-icon><?= $icon($type) ?></span>
            <label class="psf-cond__type">
                <span class="sr-only">Type de condition</span>
                <select name="condition_type[]" data-pass-type>
                    <option value="">— Type de condition —</option>
                    <?php foreach ($catalog as $opt): ?>
                        <option value="<?= $h($opt['type']) ?>"<?= $sel($type, $opt['type']) ?>><?= $h($opt['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="button" class="psf-cond__remove" data-pass-remove-condition aria-label="Retirer cette condition">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <p class="psf-cond__help" data-pass-help><?= $h((string) ($catalogByType[$type]['preview'] ?? 'Choisissez ce que le membre doit remplir.')) ?></p>
        <div class="psf-cond__fields">
            <label class="psf-field psf-field--wide" data-pass-field="training"<?= $show('training') ?>>
                <span>Formation</span>
                <select name="training_module_id[]">
                    <option value="">— Choisir une formation —</option>
                    <?php foreach ($courses as $course): $cid = (int) ($course['id'] ?? 0); ?>
                        <option value="<?= $cid ?>"<?= $sel($c['training_module_id'] ?? '', $cid) ?>><?= $h($lookups['courses'][$cid] ?? ('#' . $cid)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($courses === []): ?><small class="psf-field__warn">Aucune formation publiée dans le LMS.</small><?php endif; ?>
            </label>
            <label class="psf-field psf-field--wide" data-pass-field="qualification"<?= $show('qualification') ?>>
                <span>Qualification</span>
                <select name="qualification_id[]">
                    <option value="">— Choisir une qualification —</option>
                    <?php foreach ($quals as $q): ?>
                        <option value="<?= (int) $q['id'] ?>"<?= $sel($c['qualification_id'] ?? '', $q['id']) ?>><?= $h($q['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($quals === []): ?><small class="psf-field__warn">Aucune qualification dans le référentiel.</small><?php endif; ?>
            </label>
            <label class="psf-field psf-field--check" data-pass-field="validity"<?= $show('validity') ?>>
                <input type="hidden" name="require_validity[]" value="<?= !empty($c['require_validity']) ? '1' : '0' ?>" data-pass-validity>
                <input type="checkbox" data-pass-validity-box<?= !empty($c['require_validity']) ? ' checked' : '' ?>>
                <span>Elle doit être encore valable (non expirée)</span>
            </label>
            <label class="psf-field psf-field--wide" data-pass-field="grade"<?= $show('grade') ?>>
                <span>Grade minimum</span>
                <select name="grade_id[]">
                    <option value="">— Choisir un grade —</option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?= (int) $g['id'] ?>"<?= $sel($c['grade_id'] ?? '', $g['id']) ?>><?= $h($g['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="psf-field" data-pass-field="hour_category"<?= $show('hour_category') ?>>
                <span>Type d’heure</span>
                <select name="hour_category[]">
                    <option value="">— Choisir —</option>
                    <?php foreach ($cats as $cat): ?>
                        <option value="<?= $h((string) $cat) ?>"<?= $sel($c['hour_category'] ?? '', $cat) ?>><?= $h((string) $cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="psf-field" data-pass-field="avis"<?= $show('avis') ?>>
                <span>Avis donné par</span>
                <select name="avis_kind[]">
                    <option value="">— Choisir —</option>
                    <?php foreach ($avisKinds as $ak): ?>
                        <option value="<?= $h($ak['value']) ?>"<?= $sel($c['avis_kind'] ?? '', $ak['value']) ?>><?= $h($ak['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="psf-field" data-pass-field="bilan"<?= $show('bilan') ?>>
                <span>Bilans pris en compte</span>
                <select name="bilan_kind[]">
                    <option value="">Tous les bilans</option>
                    <?php foreach ($bilanKinds as $bk): ?>
                        <option value="<?= $h($bk['value']) ?>"<?= $sel($c['bilan_kind'] ?? '', $bk['value']) ?>><?= $h($bk['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="psf-field psf-field--num" data-pass-field="threshold"<?= $show('threshold') ?>>
                <span data-pass-threshold-label><?= $unit === 'note' ? 'Note minimale' : 'Heures minimum' ?></span>
                <span class="psf-input-unit">
                    <input type="number" step="0.1" min="0" name="threshold_value[]" value="<?= $h($threshold) ?>" placeholder="0" inputmode="decimal">
                    <em data-pass-unit><?= $unit === 'note' ? '/ 5' : 'h' ?></em>
                </span>
            </label>
            <label class="psf-field psf-field--num" data-pass-field="window"<?= $show('window') ?>>
                <span>Sur les derniers</span>
                <span class="psf-input-unit">
                    <input type="number" min="0" name="window_days[]" value="<?= $h((string) ($c['window_days'] ?? '')) ?>" placeholder="tout">
                    <em>jours</em>
                </span>
            </label>
            <input type="hidden" name="session_kind[]" value="<?= $h((string) ($c['session_kind'] ?? '')) ?>">
        </div>
        <p class="psf-cond__sum">
            <span class="psf-cond__sum-text" data-pass-sentence><?= $h($sum['text']) ?></span>
            <span class="psf-cond__missing" data-pass-missing<?= $sum['missing'] === '' ? ' hidden' : '' ?>><?= $h($sum['missing']) ?></span>
        </p>
    </li>
    <?php

    return (string) ob_get_clean();
};

$presets = [
    ['key' => 'experience', 'label' => 'Expérience de terrain', 'hint' => 'Heures validées + avis du chef direct', 'items' => [
        ['condition_type' => E::TYPE_VALIDATED_HOURS, 'threshold_value' => 30],
        ['condition_type' => E::TYPE_HIERARCHICAL_AVIS, 'avis_kind' => E::AVIS_CHEF_N1],
    ]],
    ['key' => 'specialist', 'label' => 'Spécialiste', 'hint' => 'Formation + qualification encore valable', 'items' => [
        ['condition_type' => E::TYPE_LMS],
        ['condition_type' => E::TYPE_QUALIFICATION, 'require_validity' => 1],
    ]],
    ['key' => 'command', 'label' => 'Encadrement', 'hint' => 'Grade minimum + notation + avis du commandement', 'items' => [
        ['condition_type' => E::TYPE_MIN_GRADE],
        ['condition_type' => E::TYPE_BILAN_RATING, 'bilan_kind' => 'commandement', 'threshold_value' => 3.5, 'window_days' => 180],
        ['condition_type' => E::TYPE_HIERARCHICAL_AVIS, 'avis_kind' => E::AVIS_COMMANDEMENT],
    ]],
];
$jsConfig = [
    'fields' => $fieldMap,
    'help' => array_map(static fn (array $r): string => $r['preview'], $catalogByType),
    'icons' => array_map(static fn (string $t): string => $icon($t), array_combine(array_keys($catalogByType), array_keys($catalogByType)) ?: []),
    'avis' => array_column($avisKinds, 'label', 'value'),
    'bilan' => array_column($bilanKinds, 'label', 'value'),
    'presets' => array_map(static fn (array $p): array => $p['items'], array_column($presets, null, 'key')),
    'testUrl' => $isEdit ? url('back-office/organisation/passes/' . (int) $pass['id'] . '/test') : '',
];
?>
<div class="psf" data-pass-form>
    <header class="psf-head">
        <nav class="psf-crumb" aria-label="Fil d’Ariane">
            <a href="<?= $h(url('back-office/organisation/passes')) ?>">PASS RH</a>
            <span aria-hidden="true">/</span>
            <span><?= $isEdit ? $h($val('label')) : 'Nouveau PASS' ?></span>
        </nav>
        <h1 class="psf-title"><?= $isEdit ? 'Modifier le PASS' : 'Créer un PASS' ?></h1>
        <p class="psf-lead">Un PASS est une liste de conditions qu’un membre doit remplir — formation, qualification, heures de jeu, grade, avis, notation — pour obtenir un poste, une promotion ou une notation.</p>
    </header>

    <?php if ($flashError): ?><p class="psf-flash psf-flash--error" role="alert"><?= $h((string) $flashError) ?></p><?php endif; ?>
    <?php if ($flashSuccess): ?><p class="psf-flash psf-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p><?php endif; ?>

    <?php if (!$ready): ?>
        <div class="psf-flash psf-flash--warn">Le schéma PASS n’est pas encore appliqué sur cette instance. Demandez la mise à jour de la base, puis rechargez.</div>
    <?php else: ?>
    <form method="post" action="<?= $h($formAction) ?>" class="psf-grid" data-pass-main-form novalidate>
        <?= \App\Core\Csrf::field() ?>
        <div class="psf-main">
            <section class="psf-card" aria-labelledby="psf-s1">
                <h2 id="psf-s1" class="psf-card__title"><span>1</span>Identité</h2>
                <div class="psf-row">
                    <label class="psf-field psf-field--grow">
                        <span>Nom du PASS <b aria-hidden="true">*</b></span>
                        <input name="label" required maxlength="160" value="<?= $h($val('label')) ?>" placeholder="Ex. PASS JTAC" data-pass-label autocomplete="off">
                    </label>
                    <label class="psf-field psf-field--code">
                        <span>Code court</span>
                        <input name="code" maxlength="64" value="<?= $h($val('code')) ?>" placeholder="JTAC" data-pass-code<?= $val('code') !== '' ? ' data-touched="1"' : '' ?> autocomplete="off">
                    </label>
                </div>
                <label class="psf-field">
                    <span>Description <em>facultatif</em></span>
                    <textarea name="description" rows="2" placeholder="À quoi sert ce PASS, pour quel poste ou quelle promotion" data-pass-desc><?= $h($val('description')) ?></textarea>
                </label>
            </section>

            <section class="psf-card" aria-labelledby="psf-s2">
                <h2 id="psf-s2" class="psf-card__title"><span>2</span>Où s’applique-t-il ?</h2>
                <div class="psf-usages">
                    <?php foreach ($usages as $key => [$label, $hint, $default]): ?>
                        <label class="psf-usage">
                            <input type="checkbox" name="<?= $h($key) ?>" value="1"<?= $on($key, !$default) ? ' checked' : '' ?> data-pass-usage="<?= $h($label) ?>">
                            <span class="psf-usage__box">
                                <b><?= $h($label) ?></b>
                                <small><?= $h($hint) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <label class="psf-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"<?= $on('is_active') ? ' checked' : '' ?> data-pass-active>
                    <span class="psf-switch__track" aria-hidden="true"></span>
                    <span><b>PASS actif</b> — un PASS inactif n’est plus exigé nulle part, sans être supprimé.</span>
                </label>
            </section>

            <section class="psf-card" aria-labelledby="psf-s3">
                <div class="psf-card__bar">
                    <h2 id="psf-s3" class="psf-card__title"><span>3</span>Conditions <small class="psf-count" data-pass-count><?= count($conditions) ?></small></h2>
                    <div class="psf-logic" role="radiogroup" aria-label="Comment combiner les conditions">
                        <label><input type="radio" name="logic" value="all"<?= $logic === 'all' ? ' checked' : '' ?> data-pass-logic><span>Toutes <em>ET</em></span></label>
                        <label><input type="radio" name="logic" value="any"<?= $logic === 'any' ? ' checked' : '' ?> data-pass-logic><span>Au moins une <em>OU</em></span></label>
                    </div>
                </div>

                <ol class="psf-conds" data-pass-conditions data-logic="<?= $h($logic) ?>">
                    <?php foreach ($conditions as $c): ?>
                        <?= $renderCondition($c) ?>
                    <?php endforeach; ?>
                </ol>

                <div class="psf-empty" data-pass-empty<?= $conditions !== [] ? ' hidden' : '' ?>>
                    <p><b>Aucune condition pour l’instant.</b> Sans condition, tout le monde remplit le PASS. Partez d’un modèle ou ajoutez vos conditions une à une.</p>
                    <div class="psf-presets">
                        <?php foreach ($presets as $p): ?>
                            <button type="button" class="psf-preset" data-pass-preset="<?= $h($p['key']) ?>">
                                <b><?= $h($p['label']) ?></b>
                                <small><?= $h($p['hint']) ?></small>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="psf-palette" aria-label="Ajouter une condition">
                    <p class="psf-palette__title">Ajouter une condition</p>
                    <?php foreach ($families as $fam): ?>
                        <div class="psf-palette__fam">
                            <span class="psf-palette__label"><?= $h($fam['label']) ?></span>
                            <div class="psf-palette__items">
                                <?php foreach ($fam['types'] as $t): if (!isset($catalogByType[$t])) { continue; } ?>
                                    <button type="button" class="psf-add" data-pass-add="<?= $h($t) ?>" title="<?= $h($catalogByType[$t]['preview']) ?>">
                                        <?= $icon($t) ?><span><?= $h($catalogByType[$t]['label']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <aside class="psf-side">
            <div class="psf-preview" aria-live="polite">
                <p class="psf-preview__kicker">Aperçu</p>
                <div class="psf-badge">
                    <span class="psf-badge__code" data-pv-code><?= $h($val('code') !== '' ? $val('code') : 'PASS') ?></span>
                    <span class="psf-badge__state<?= $on('is_active') ? '' : ' is-off' ?>" data-pv-state><?= $on('is_active') ? 'Actif' : 'Inactif' ?></span>
                </div>
                <p class="psf-preview__name" data-pv-name><?= $h($val('label') !== '' ? $val('label') : 'Nom du PASS') ?></p>
                <p class="psf-preview__desc" data-pv-desc<?= $val('description') === '' ? ' hidden' : '' ?>><?= $h($val('description')) ?></p>
                <p class="psf-preview__usages" data-pv-usages></p>
                <p class="psf-preview__rule" data-pv-rule></p>
                <ol class="psf-preview__list" data-pv-list></ol>
                <p class="psf-preview__warn" data-pv-warn hidden></p>
            </div>

            <div class="psf-actions">
                <button type="submit" class="psf-btn psf-btn--primary"><?= $isEdit ? 'Enregistrer le PASS' : 'Créer le PASS' ?></button>
                <a href="<?= $h(url('back-office/organisation/passes')) ?>" class="psf-btn">Annuler</a>
            </div>

            <?php if ($isEdit): ?>
            <div class="psf-test" data-pass-test>
                <p class="psf-preview__kicker">Tester sur un membre</p>
                <p class="psf-test__hint">Vérifie la version enregistrée du PASS : enregistrez d’abord vos changements.</p>
                <div class="psf-test__search">
                    <input type="search" placeholder="Indicatif ou nom…" autocomplete="off" data-pass-test-q aria-label="Chercher un membre">
                    <ul class="psf-test__results" data-pass-test-results hidden></ul>
                </div>
                <div class="psf-test__out" data-pass-test-out hidden></div>
            </div>
            <?php endif; ?>
        </aside>
    </form>

    <?php if ($isEdit): ?>
    <form method="post" action="<?= $h(url('back-office/organisation/passes/' . (int) $pass['id'] . '/delete')) ?>" class="psf-danger" onsubmit="return confirm('Supprimer définitivement ce PASS ? Les postes et promotions qui l’exigent ne le demanderont plus.');">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <b>Supprimer ce PASS</b>
            <span>Pour le suspendre sans le perdre, décochez plutôt « PASS actif ».</span>
        </div>
        <button type="submit" class="psf-btn psf-btn--danger">Supprimer</button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
</div>
<template id="pass-condition-template"><?= $renderCondition(['condition_type' => '']) ?></template>
<script type="application/json" id="pass-form-config"><?= json_encode($jsConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= $h(asset_url('assets/js/pass-form.js')) ?>" defer></script>
