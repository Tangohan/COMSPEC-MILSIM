<?php
declare(strict_types=1);

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
$immersionUrl = url('back-office/roleplay/immersion');
$sessionsUrl = url('back-office/roleplay/sessions');
?>
<div class="bo-imm bo-community-settings">
    <section class="bo-imm__hero ath-rise">
        <div class="bo-imm__hero-copy">
            <span class="bo-imm__eyebrow">Roleplay · Parcours</span>
            <h2>Conditions de passage d’une étape à la suivante</h2>
            <p>
                Vous décrivez le parcours de vos membres, puis ce qu’ils doivent avoir fait pour entrer dans l’étape suivante.
                Un jeu de règles vide n’avance personne. Le passage peut attendre une validation, ou se faire tout seul.
            </p>
            <div class="bo-imm__hero-actions">
                <a href="<?= $h($immersionUrl) ?>" class="ath-btn">Cadences d’immersion</a>
                <a href="<?= $h($sessionsUrl) ?>" class="ath-btn">Sessions Arma</a>
            </div>
        </div>
    </section>

    <form method="post" action="<?= $h($formAction) ?>" class="bo-settings-grid" style="margin-bottom:16px;">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="phase_action" value="add_phase">
        <section class="ath-card ath-rise bo-setting-group">
            <p class="bo-setting-group__kicker">Étapes</p>
            <h2 class="bo-setting-group__title">Ajouter une étape</h2>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Nom de l’étape</div>
                    <input type="text" name="phase_label" maxlength="120" class="bo-setting-row__field--wide" placeholder="Ex. Formation">
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">État du dossier à l’arrivée</div>
                    <select name="phase_status" class="bo-setting-row__field--wide">
                        <?php foreach ($statuses as $st): ?>
                        <option value="<?= $h($st) ?>"><?= $h($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="bo-settings-save" style="margin-top:12px;">
                <button type="submit" class="ath-btn ath-btn--solid">Ajouter l’étape</button>
            </div>
        </section>
    </form>

    <?php if ($list !== []): ?>
    <section class="ath-card ath-rise bo-setting-group">
        <p class="bo-setting-group__kicker">Parcours</p>
        <h2 class="bo-setting-group__title">Ordre des étapes</h2>
        <ol class="bo-imm__orient" style="padding:12px 20px;">
            <?php foreach ($list as $i => $ph): ?>
            <li>
                <a href="<?= $h(url('back-office/roleplay/regles-phases?phase=' . (int) $ph['id'])) ?>">
                    <strong><?= $h((string) ($ph['label'] ?? '')) ?></strong>
                </a>
                <span><?= $h((string) ($ph['target_member_status'] ?? '')) ?><?= empty($ph['is_active']) ? ' · désactivée' : '' ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <?php endif; ?>

    <?php if ($selected): ?>
    <form method="post" action="<?= $h($formAction) ?>" class="bo-settings-grid">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="phase_action" value="save_rules">
        <input type="hidden" name="phase_id" value="<?= (int) $selected['id'] ?>">
        <section class="ath-card ath-rise bo-setting-group bo-setting-group--wide">
            <p class="bo-setting-group__kicker">Étape <?= $h((string) ($selected['label'] ?? '')) ?></p>
            <h2 class="bo-setting-group__title">Conditions d’entrée dans cette étape</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Ces conditions portent sur le passage <strong>vers</strong> cette étape, jamais sur un retour en arrière.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Nom</div>
                    <input type="text" name="phase_label" maxlength="120" class="bo-setting-row__field--wide" value="<?= $h((string) ($selected['label'] ?? '')) ?>">
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">État du dossier à l’arrivée</div>
                    <select name="phase_status" class="bo-setting-row__field--wide">
                        <?php foreach ($statuses as $st): ?>
                        <option value="<?= $h($st) ?>" <?= ((string) ($selected['target_member_status'] ?? '')) === $st ? 'selected' : '' ?>><?= $h($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label class="bo-setting-row" style="cursor:pointer;">
                    <input type="checkbox" name="phase_active" value="1" <?= !empty($selected['is_active']) ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy"><span class="bo-setting-row__label">Étape active</span></span>
                </label>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Pour être éligible</div>
                    <select name="rule_logic" class="bo-setting-row__field--wide">
                        <option value="all" <?= (($ruleSet['logic'] ?? 'all') === 'all') ? 'selected' : '' ?>>Toutes les conditions</option>
                        <option value="any" <?= (($ruleSet['logic'] ?? '') === 'any') ? 'selected' : '' ?>>Au moins une condition</option>
                    </select>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Une fois éligible</div>
                    <select name="rule_effect" class="bo-setting-row__field--wide">
                        <option value="manual_gate" <?= (($ruleSet['effect'] ?? 'manual_gate') === 'manual_gate') ? 'selected' : '' ?>>Un responsable valide le passage</option>
                        <option value="automatic" <?= (($ruleSet['effect'] ?? '') === 'automatic') ? 'selected' : '' ?>>Le passage se fait tout seul</option>
                    </select>
                </div>
            </div>

            <h3 class="bo-setting-group__title" style="margin-top:22px;">Conditions</h3>
            <?php
            $rows = $conditions !== [] ? $conditions : [['condition_type' => '', 'threshold_value' => 0]];
            foreach ($rows as $idx => $row):
                $type = (string) ($row['condition_type'] ?? '');
            ?>
            <div class="bo-setting-group__rows" style="margin-top:13px;border-top:1px solid #e2e8f0;padding-top:12px;">
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Type</div>
                    <select name="conditions[<?= $idx ?>][condition_type]" class="bo-setting-row__field--wide">
                        <option value="">— Ne pas utiliser cette ligne —</option>
                        <?php foreach ($catalog as $cat): ?>
                        <option value="<?= $h($cat['type']) ?>" <?= $type === $cat['type'] ? 'selected' : '' ?>><?= $h($cat['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Seuil (heures, jours, nombre ou %)</div>
                    <input type="number" step="0.1" min="0" name="conditions[<?= $idx ?>][threshold_value]" class="bo-setting-row__field--wide" value="<?= $h((string) ($row['threshold_value'] ?? '0')) ?>">
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Période (jours, 0 = depuis toujours)</div>
                    <input type="number" min="0" name="conditions[<?= $idx ?>][window_days]" class="bo-setting-row__field--wide" value="<?= (int) ($row['window_days'] ?? 0) ?>">
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Qualification</div>
                    <select name="conditions[<?= $idx ?>][qualification_id]" class="bo-setting-row__field--wide">
                        <option value="">—</option>
                        <?php foreach ($quals as $q): ?>
                        <option value="<?= (int) $q['id'] ?>" <?= ((int) ($row['qualification_id'] ?? 0)) === (int) $q['id'] ? 'selected' : '' ?>><?= $h($q['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label class="bo-setting-row" style="cursor:pointer;">
                    <input type="checkbox" name="conditions[<?= $idx ?>][require_validity]" value="1" <?= !empty($row['require_validity']) ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy"><span class="bo-setting-row__label">Exiger une qualification encore valable</span></span>
                </label>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Module de formation</div>
                    <select name="conditions[<?= $idx ?>][training_module_id]" class="bo-setting-row__field--wide">
                        <option value="">—</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?= (int) ($c['id'] ?? 0) ?>" <?= ((int) ($row['training_module_id'] ?? 0)) === (int) ($c['id'] ?? 0) ? 'selected' : '' ?>><?= $h((string) ($c['title'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Catégorie d’heures</div>
                    <select name="conditions[<?= $idx ?>][hour_category]" class="bo-setting-row__field--wide">
                        <option value="">—</option>
                        <?php foreach ($cats as $cat): ?>
                        <option value="<?= $h((string) $cat) ?>" <?= ((string) ($row['hour_category'] ?? '')) === (string) $cat ? 'selected' : '' ?>><?= $h((string) $cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Type de session</div>
                    <select name="conditions[<?= $idx ?>][session_kind]" class="bo-setting-row__field--wide">
                        <option value="">—</option>
                        <option value="officielle" <?= (($row['session_kind'] ?? '') === 'officielle') ? 'selected' : '' ?>>Officielle</option>
                        <option value="entrainement" <?= (($row['session_kind'] ?? '') === 'entrainement') ? 'selected' : '' ?>>Entraînement</option>
                    </select>
                </div>
                <?php if (isset($phasePreviews[$idx])): ?>
                <p class="bo-setting-row__help"><strong>Cette règle signifie :</strong> <?= $h((string) $phasePreviews[$idx]) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="bo-setting-group__rows" style="margin-top:13px;border-top:1px solid #e2e8f0;padding-top:12px;">
                <?php $idx = count($rows); ?>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Ajouter une condition</div>
                    <select name="conditions[<?= $idx ?>][condition_type]" class="bo-setting-row__field--wide">
                        <option value="">— Aucune —</option>
                        <?php foreach ($catalog as $cat): ?>
                        <option value="<?= $h($cat['type']) ?>"><?= $h($cat['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="conditions[<?= $idx ?>][threshold_value]" value="0">
            </div>
            <div class="bo-settings-save">
                <button type="submit" class="ath-btn ath-btn--solid">Enregistrer cette étape</button>
            </div>
        </section>
    </form>
    <?php endif; ?>
</div>
