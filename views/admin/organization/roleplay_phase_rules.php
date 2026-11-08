<?php
declare(strict_types=1);

/*
 * Parcours RH : à gauche les étapes dans l'ordre (déplacer, ajouter), à droite l'étape choisie et ses conditions.
 * Pour chaque condition, seuls les champs utiles à son type sont montrés (public/assets/js/roleplay-phase-rules.js) ;
 * sans script, tous restent visibles et le formulaire fonctionne. Styles : back-office-roleplay-suivi.css.
 */

/** @var list<array<string, mixed>> $phaseList */
/** @var array<string, mixed>|null $phaseSelected */
/** @var array<string, mixed>|null $phaseRuleSet */
/** @var list<array<string, mixed>> $phaseConditions */
/** @var list<string> $phasePreviews */
/** @var list<array{type: string, label: string, preview: string}> $phaseCatalog */
/** @var list<array<string, mixed>> $phaseCourses */
/** @var list<array{id: int, label: string}> $phaseQualifications */
/** @var list<string> $phaseStatuses */
/** @var list<string> $phaseHourCategories */
/** @var string $phaseFormAction */

use App\Services\Personnel\PhaseRules\PhaseRuleEngine;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$list = is_array($phaseList ?? null) ? $phaseList : [];
$selected = is_array($phaseSelected ?? null) ? $phaseSelected : null;
$ruleSet = is_array($phaseRuleSet ?? null) ? $phaseRuleSet : null;
$conditions = is_array($phaseConditions ?? null) ? $phaseConditions : [];
$catalog = is_array($phaseCatalog ?? null) ? $phaseCatalog : [];
$courses = is_array($phaseCourses ?? null) ? $phaseCourses : [];
$quals = is_array($phaseQualifications ?? null) ? $phaseQualifications : [];
$statuses = is_array($phaseStatuses ?? null) ? $phaseStatuses : ['En formation', 'Disponible', 'Actif'];
$cats = is_array($phaseHourCategories ?? null) ? $phaseHourCategories : [];
$formAction = (string) ($phaseFormAction ?? url('back-office/roleplay/regles-phases'));
$phaseUrl = static fn (int $id): string => url('back-office/roleplay-followup?onglet=phases&phase=' . $id);
$selectedId = $selected ? (int) $selected['id'] : 0;
$logic = (($ruleSet['logic'] ?? 'all') === 'any') ? 'any' : 'all';
$effect = (($ruleSet['effect'] ?? 'manual_gate') === 'automatic') ? 'automatic' : 'manual_gate';

$fieldMap = PhaseRuleEngine::conditionFields();
$renderCondition = static function (array $row, string $idx) use ($h, $catalog, $quals, $courses, $cats): void {
    $type = (string) ($row['condition_type'] ?? '');
    ?>
    <div class="rpp-cond" data-rpp-cond>
        <div class="rpp-cond__head">
            <div class="rpp-field rpp-field--grow">
                <label for="rpp-type-<?= $h($idx) ?>">Le membre doit avoir</label>
                <select id="rpp-type-<?= $h($idx) ?>" name="conditions[<?= $h($idx) ?>][condition_type]" data-rpp-type>
                    <option value="">Choisir…</option>
                    <?php foreach ($catalog as $cat): ?>
                    <option value="<?= $h($cat['type']) ?>" <?= $type === $cat['type'] ? 'selected' : '' ?>><?= $h($cat['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="ath-btn rpp-cond__del" data-rpp-del>Retirer</button>
        </div>
        <div class="rpp-cond__fields">
            <div class="rpp-field" data-rpp-field="threshold_value">
                <label><span data-rpp-threshold-label>Seuil</span>
                    <input type="number" step="0.1" min="0" name="conditions[<?= $h($idx) ?>][threshold_value]" value="<?= $h((string) ($row['threshold_value'] ?? '0')) ?>" data-rpp-threshold>
                </label>
            </div>
            <div class="rpp-field" data-rpp-field="window_days">
                <label>Sur les derniers (jours, 0 = depuis toujours)
                    <input type="number" min="0" name="conditions[<?= $h($idx) ?>][window_days]" value="<?= (int) ($row['window_days'] ?? 0) ?>" data-rpp-window>
                </label>
            </div>
            <div class="rpp-field" data-rpp-field="qualification_id">
                <label>Qualification
                    <select name="conditions[<?= $h($idx) ?>][qualification_id]">
                        <option value="">Choisir…</option>
                        <?php foreach ($quals as $q): ?>
                        <option value="<?= (int) $q['id'] ?>" <?= ((int) ($row['qualification_id'] ?? 0)) === (int) $q['id'] ? 'selected' : '' ?>><?= $h((string) $q['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label class="rpp-check" data-rpp-field="require_validity">
                <input type="checkbox" name="conditions[<?= $h($idx) ?>][require_validity]" value="1" <?= !empty($row['require_validity']) ? 'checked' : '' ?> data-rpp-validity>
                <span>Encore valable aujourd’hui</span>
            </label>
            <div class="rpp-field" data-rpp-field="training_module_id">
                <label>Module de formation
                    <select name="conditions[<?= $h($idx) ?>][training_module_id]">
                        <option value="">Choisir…</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?= (int) ($c['id'] ?? 0) ?>" <?= ((int) ($row['training_module_id'] ?? 0)) === (int) ($c['id'] ?? 0) ? 'selected' : '' ?>><?= $h((string) ($c['title'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="rpp-field" data-rpp-field="hour_category">
                <label>Catégorie d’heures
                    <select name="conditions[<?= $h($idx) ?>][hour_category]">
                        <option value="">Choisir…</option>
                        <?php foreach ($cats as $cat): ?>
                        <option value="<?= $h((string) $cat) ?>" <?= ((string) ($row['hour_category'] ?? '')) === (string) $cat ? 'selected' : '' ?>><?= $h((string) $cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="rpp-field" data-rpp-field="session_kind">
                <label>Type de session
                    <select name="conditions[<?= $h($idx) ?>][session_kind]">
                        <option value="">Choisir…</option>
                        <option value="officielle" <?= (($row['session_kind'] ?? '') === 'officielle') ? 'selected' : '' ?>>Officielle</option>
                        <option value="entrainement" <?= (($row['session_kind'] ?? '') === 'entrainement') ? 'selected' : '' ?>>Entraînement</option>
                    </select>
                </label>
            </div>
        </div>
        <p class="rpp-cond__says" data-rpp-says aria-live="polite"><?= $type !== '' ? $h(PhaseRuleEngine::previewSentence($row)) : '' ?></p>
    </div>
    <?php
};
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/back-office-roleplay-suivi.css')) ?>">
<div class="rps-board rpp">
    <?php $rpGuideHere = 'phases'; require base_path('views/admin/organization/partials/roleplay_parcours_guide.php'); ?>

    <div class="rpp-layout">
        <aside class="ath-card rpp-steps">
            <h2>Les étapes, dans l’ordre</h2>
            <p class="rps-meta">Un membre les franchit de haut en bas. Choisissez-en une pour régler ce qu’il faut pour y entrer.</p>
            <?php if ($list !== []): ?>
            <form method="post" action="<?= $h($formAction) ?>" data-rpp-order>
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="phase_action" value="reorder">
                <ol class="rpp-steps__list">
                    <?php foreach ($list as $i => $ph): $pid = (int) $ph['id']; ?>
                    <li class="<?= $pid === $selectedId ? 'is-on' : '' ?><?= empty($ph['is_active']) ? ' is-off' : '' ?>">
                        <input type="hidden" name="phase_order[]" value="<?= $pid ?>">
                        <span class="rpp-steps__n"><?= $i + 1 ?></span>
                        <a href="<?= $h($phaseUrl($pid)) ?>"<?= $pid === $selectedId ? ' aria-current="true"' : '' ?>>
                            <strong><?= $h((string) ($ph['label'] ?? '')) ?></strong>
                            <small><?= $h((string) ($ph['target_member_status'] ?? '')) ?><?= empty($ph['is_active']) ? ' · désactivée' : '' ?></small>
                        </a>
                        <span class="rpp-steps__move">
                            <button type="button" data-rpp-up aria-label="Monter <?= $h((string) ($ph['label'] ?? '')) ?>" title="Monter"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
                            <button type="button" data-rpp-down aria-label="Descendre <?= $h((string) ($ph['label'] ?? '')) ?>" title="Descendre"<?= $i === count($list) - 1 ? ' disabled' : '' ?>>↓</button>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </form>
            <?php else: ?>
            <p class="rps-empty"><strong>Aucune étape pour l’instant.</strong> Ajoutez la première ci-dessous.</p>
            <?php endif; ?>
            <form method="post" action="<?= $h($formAction) ?>" class="rpp-add">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="phase_action" value="add_phase">
                <h3>Ajouter une étape</h3>
                <div class="rpp-field">
                    <label>Nom de l’étape
                        <input type="text" name="phase_label" maxlength="120" required placeholder="Ex. Formation">
                    </label>
                </div>
                <div class="rpp-field">
                    <label>État du dossier à l’arrivée
                        <select name="phase_status">
                            <?php foreach ($statuses as $st): ?><option value="<?= $h($st) ?>"><?= $h($st) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <button type="submit" class="ath-btn">Ajouter l’étape</button>
            </form>
        </aside>

        <?php if ($selected): ?>
        <form method="post" action="<?= $h($formAction) ?>" class="ath-card rpp-edit" data-rpp-edit data-fields="<?= $h((string) json_encode($fieldMap, JSON_UNESCAPED_UNICODE)) ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="phase_action" value="save_rules">
            <input type="hidden" name="phase_id" value="<?= $selectedId ?>">

            <div class="rpp-edit__head">
                <div class="rpp-field rpp-field--grow">
                    <label>Nom de l’étape
                        <input type="text" name="phase_label" maxlength="120" value="<?= $h((string) ($selected['label'] ?? '')) ?>">
                    </label>
                </div>
                <div class="rpp-field">
                    <label>État du dossier à l’arrivée
                        <select name="phase_status">
                            <?php foreach ($statuses as $st): ?>
                            <option value="<?= $h($st) ?>" <?= ((string) ($selected['target_member_status'] ?? '')) === $st ? 'selected' : '' ?>><?= $h($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label class="rpp-check rpp-check--end">
                    <input type="checkbox" name="phase_active" value="1" <?= !empty($selected['is_active']) ? 'checked' : '' ?>>
                    <span>Étape active</span>
                </label>
            </div>

            <h3>Ce qu’il faut pour y entrer</h3>
            <p class="rps-meta">Ces conditions portent sur le passage <strong>vers</strong> cette étape, jamais sur un retour en arrière. Sans condition, personne n’avance tout seul.</p>
            <div class="rpp-conds" data-rpp-conds>
                <?php foreach ($conditions as $idx => $row): $renderCondition($row, (string) $idx); endforeach; ?>
            </div>
            <p class="rps-empty" data-rpp-empty<?= $conditions !== [] ? ' hidden' : '' ?>><strong>Aucune condition.</strong> Le passage ne se fera que si un responsable le force sur la fiche.</p>
            <button type="button" class="ath-btn" data-rpp-add>Ajouter une condition</button>
            <template data-rpp-template><?php $renderCondition(['condition_type' => '', 'threshold_value' => 0], '__IDX__'); ?></template>

            <h3>Quand c’est rempli</h3>
            <div class="rpp-radios">
                <fieldset>
                    <legend>Pour être éligible, il faut</legend>
                    <label class="rpp-radio"><input type="radio" name="rule_logic" value="all" <?= $logic === 'all' ? 'checked' : '' ?>><span><b>Toutes les conditions</b><small>Une seule manquante bloque le passage.</small></span></label>
                    <label class="rpp-radio"><input type="radio" name="rule_logic" value="any" <?= $logic === 'any' ? 'checked' : '' ?>><span><b>Au moins une condition</b><small>Une seule remplie suffit.</small></span></label>
                </fieldset>
                <fieldset>
                    <legend>Une fois éligible</legend>
                    <label class="rpp-radio"><input type="radio" name="rule_effect" value="manual_gate" <?= $effect === 'manual_gate' ? 'checked' : '' ?>><span><b>Un responsable valide le passage</b><small>Le membre apparaît dans ce qu’il y a à traiter.</small></span></label>
                    <label class="rpp-radio"><input type="radio" name="rule_effect" value="automatic" <?= $effect === 'automatic' ? 'checked' : '' ?>><span><b>Le passage se fait tout seul</b><small>Sans intervention d’un responsable.</small></span></label>
                </fieldset>
            </div>

            <div class="rpp-edit__save">
                <button type="submit" class="ath-btn ath-btn--solid">Enregistrer cette étape</button>
                <a class="ath-btn" href="<?= $h(url('back-office/roleplay/sessions')) ?>">Sessions Arma</a>
            </div>
        </form>
        <?php else: ?>
        <div class="ath-card rpp-edit"><p class="rps-empty"><strong>Choisissez ou ajoutez une étape.</strong></p></div>
        <?php endif; ?>
    </div>
</div>
<script src="<?= $h(asset_url('assets/js/roleplay-phase-rules.js')) ?>" defer></script>
