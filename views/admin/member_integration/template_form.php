<?php
declare(strict_types=1);

/** @var array<string,mixed>|null $template */
/** @var list<array<string,mixed>> $steps */
/** @var array<string,string> $stepTypes */
/** @var array<string,string> $responsible */
/** @var list<array<string,mixed>> $matrices */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tpl = is_array($template ?? null) ? $template : [];
$id = (int) ($tpl['id'] ?? 0);
$steps = is_array($steps ?? null) ? $steps : [];
if ($steps === []) {
    $steps = [['title' => '', 'step_type' => 'task', 'responsible_kind' => 'member', 'due_after_days' => 7, 'is_required' => 1, 'is_member_visible' => 1]];
} else {
    $steps[] = ['title' => '', 'step_type' => 'task', 'responsible_kind' => 'member', 'due_after_days' => 7, 'is_required' => 1, 'is_member_visible' => 1];
}
$action = $id > 0
    ? url('back-office/integration-membres/modeles/' . $id)
    : url('back-office/integration-membres/modeles');
$listUrl = url('back-office/integration-membres/modeles');
$stepTypes = is_array($stepTypes ?? null) ? $stepTypes : [];
$responsible = is_array($responsible ?? null) ? $responsible : [];
$matrices = is_array($matrices ?? null) ? $matrices : [];
?>
<link href="<?= $h(asset_url('assets/css/member-integration.css')) ?>" rel="stylesheet">

<div class="mi-admin">
    <div class="ath-form__actions" style="border-top:0;margin:0 0 16px;padding-top:0;">
        <a class="ath-btn" href="<?= $h($listUrl) ?>">Retour aux modèles</a>
    </div>

    <form method="post" action="<?= $h($action) ?>">
        <?= \App\Core\Csrf::field() ?>

        <div class="ath-form ath-rise">
            <div class="ath-form__head">
                <span class="ath-form__title">Parcours</span>
                <span class="ath-form__hint">Ces réglages s’appliquent aux nouvelles arrivées. Les suivis déjà commencés conservent leur version.</span>
            </div>
            <div class="ath-form__grid ath-form__grid--wide">
                <label class="ath-field">
                    <span class="ath-field__label">Nom du parcours</span>
                    <input class="ath-field__input" name="name" required value="<?= $h($tpl['name'] ?? '') ?>">
                </label>
                <label class="ath-field">
                    <span class="ath-field__label">Description</span>
                    <textarea class="ath-field__textarea" name="description" rows="3"><?= $h($tpl['description'] ?? '') ?></textarea>
                </label>
            </div>
            <div class="ath-form__grid" style="margin-top:14px">
                <label class="ath-field mi-field--days">
                    <span class="ath-field__label">Durée prévue (jours)</span>
                    <input class="ath-field__input" type="number" name="duration_days" min="1" value="<?= (int) ($tpl['duration_days'] ?? 30) ?>">
                </label>
                <label class="ath-field">
                    <span class="ath-field__label">Règle de référent</span>
                    <select class="ath-field__select" name="referent_rule">
                        <?php foreach (['none' => 'Aucun par défaut', 'fixed_user' => 'Personne fixe', 'unit_leader' => 'Chef d’unité', 'template_role' => 'Rôle du modèle'] as $k => $lab): ?>
                            <option value="<?= $h($k) ?>" <?= (($tpl['referent_rule'] ?? 'none') === $k) ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="mi-checks">
                <label class="ath-check">
                    <input type="checkbox" name="is_active" value="1" <?= !isset($tpl['is_active']) || !empty($tpl['is_active']) ? 'checked' : '' ?>>
                    Parcours actif
                </label>
            </div>
        </div>

        <div class="ath-form ath-rise">
            <div class="ath-form__head">
                <span class="ath-form__title">Étapes</span>
                <span class="ath-form__hint">Chaque bloc est une étape, dans l’ordre. Une ligne sans titre n’est pas enregistrée : servez-vous-en pour en ajouter une.</span>
            </div>

            <?php foreach ($steps as $i => $st): ?>
                <fieldset class="mi-step-card">
                    <legend>Étape <?= (int) $i + 1 ?></legend>
                    <div class="ath-form__grid ath-form__grid--wide">
                        <label class="ath-field">
                            <span class="ath-field__label">Titre</span>
                            <input class="ath-field__input" name="steps[<?= (int) $i ?>][title]" value="<?= $h($st['title'] ?? '') ?>" <?= $i === 0 ? 'required' : '' ?>>
                        </label>
                        <label class="ath-field">
                            <span class="ath-field__label">Description</span>
                            <textarea class="ath-field__textarea" name="steps[<?= (int) $i ?>][description]" rows="2"><?= $h($st['description'] ?? '') ?></textarea>
                        </label>
                    </div>
                    <div class="ath-form__grid" style="margin-top:12px">
                        <label class="ath-field">
                            <span class="ath-field__label">Type</span>
                            <select class="ath-field__select" name="steps[<?= (int) $i ?>][step_type]">
                                <?php foreach ($stepTypes as $k => $lab): ?>
                                    <option value="<?= $h($k) ?>" <?= (($st['step_type'] ?? '') === $k) ? 'selected' : '' ?>><?= $h($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="ath-field">
                            <span class="ath-field__label">Responsable</span>
                            <select class="ath-field__select" name="steps[<?= (int) $i ?>][responsible_kind]">
                                <?php foreach ($responsible as $k => $lab): ?>
                                    <option value="<?= $h($k) ?>" <?= (($st['responsible_kind'] ?? '') === $k) ? 'selected' : '' ?>><?= $h($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="ath-field mi-field--days">
                            <span class="ath-field__label">Délai (jours après l’arrivée)</span>
                            <input class="ath-field__input" type="number" name="steps[<?= (int) $i ?>][due_after_days]" min="0" value="<?= (int) ($st['due_after_days'] ?? 7) ?>">
                        </label>
                        <label class="ath-field">
                            <span class="ath-field__label">Groupe de suivi lié</span>
                            <select class="ath-field__select" name="steps[<?= (int) $i ?>][linked_matrix_id]">
                                <option value="">Aucun</option>
                                <?php foreach ($matrices as $m): ?>
                                    <option value="<?= (int) ($m['id'] ?? 0) ?>" <?= ((int) ($st['linked_matrix_id'] ?? 0) === (int) ($m['id'] ?? 0)) ? 'selected' : '' ?>><?= $h($m['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="mi-checks">
                        <label class="ath-check">
                            <input type="checkbox" name="steps[<?= (int) $i ?>][is_required]" value="1" <?= !isset($st['is_required']) || !empty($st['is_required']) ? 'checked' : '' ?>>
                            Obligatoire
                        </label>
                        <label class="ath-check">
                            <input type="checkbox" name="steps[<?= (int) $i ?>][is_member_visible]" value="1" <?= !isset($st['is_member_visible']) || !empty($st['is_member_visible']) ? 'checked' : '' ?>>
                            Visible du membre
                        </label>
                    </div>
                </fieldset>
            <?php endforeach; ?>

            <div class="ath-form__actions">
                <button class="ath-btn ath-btn--accent" type="submit">Enregistrer</button>
                <a class="ath-btn" href="<?= $h($listUrl) ?>">Annuler</a>
            </div>
        </div>
    </form>
</div>
