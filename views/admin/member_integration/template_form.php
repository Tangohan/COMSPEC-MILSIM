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
    $steps = [['title' => '', 'step_type' => 'task', 'responsible_kind' => 'member', 'due_after_days' => 7, 'is_required' => 1]];
}
$action = $id > 0
    ? url('back-office/integration-membres/modeles/' . $id)
    : url('back-office/integration-membres/modeles');
?>
<link href="<?= $h(asset_url('assets/css/member-integration.css')) ?>" rel="stylesheet">
<p><a href="<?= $h(url('back-office/integration-membres/modeles')) ?>">← Modèles</a></p>
<form method="post" action="<?= $h($action) ?>" class="mi-form mi-panel">
    <?= \App\Core\Csrf::field() ?>
    <label>Nom du parcours <input name="name" required value="<?= $h($tpl['name'] ?? '') ?>"></label>
    <label>Description <textarea name="description" rows="3"><?= $h($tpl['description'] ?? '') ?></textarea></label>
    <label>Durée prévue (jours) <input type="number" name="duration_days" min="1" value="<?= (int) ($tpl['duration_days'] ?? 30) ?>"></label>
    <label>Règle de référent
        <select name="referent_rule">
            <?php foreach (['none' => 'Aucun par défaut', 'fixed_user' => 'Personne fixe', 'unit_leader' => 'Chef d’unité', 'template_role' => 'Rôle du modèle'] as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= (($tpl['referent_rule'] ?? 'none') === $k) ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><input type="checkbox" name="is_active" value="1" <?= !isset($tpl['is_active']) || !empty($tpl['is_active']) ? 'checked' : '' ?>> Parcours actif</label>

    <h2>Étapes</h2>
    <?php foreach ($steps as $i => $st): ?>
        <fieldset class="mi-panel" style="margin:.75rem 0">
            <legend>Étape <?= (int) $i + 1 ?></legend>
            <label>Titre <input name="steps[<?= (int) $i ?>][title]" required value="<?= $h($st['title'] ?? '') ?>"></label>
            <label>Description <textarea name="steps[<?= (int) $i ?>][description]" rows="2"><?= $h($st['description'] ?? '') ?></textarea></label>
            <label>Type
                <select name="steps[<?= (int) $i ?>][step_type]">
                    <?php foreach ($stepTypes as $k => $lab): ?>
                        <option value="<?= $h($k) ?>" <?= (($st['step_type'] ?? '') === $k) ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Responsable
                <select name="steps[<?= (int) $i ?>][responsible_kind]">
                    <?php foreach ($responsible as $k => $lab): ?>
                        <option value="<?= $h($k) ?>" <?= (($st['responsible_kind'] ?? '') === $k) ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Délai (jours après l’arrivée) <input type="number" name="steps[<?= (int) $i ?>][due_after_days]" min="0" value="<?= (int) ($st['due_after_days'] ?? 7) ?>"></label>
            <label>Groupe de suivi lié
                <select name="steps[<?= (int) $i ?>][linked_matrix_id]">
                    <option value="">Aucun</option>
                    <?php foreach ($matrices as $m): ?>
                        <option value="<?= (int) ($m['id'] ?? 0) ?>" <?= ((int) ($st['linked_matrix_id'] ?? 0) === (int) ($m['id'] ?? 0)) ? 'selected' : '' ?>><?= $h($m['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><input type="checkbox" name="steps[<?= (int) $i ?>][is_required]" value="1" <?= !isset($st['is_required']) || !empty($st['is_required']) ? 'checked' : '' ?>> Obligatoire</label>
            <label><input type="checkbox" name="steps[<?= (int) $i ?>][is_member_visible]" value="1" <?= !isset($st['is_member_visible']) || !empty($st['is_member_visible']) ? 'checked' : '' ?>> Visible du membre</label>
        </fieldset>
    <?php endforeach; ?>
    <p class="mi-muted">Pour ajouter une étape, enregistrez puis rouvrez le modèle, ou remplissez une ligne vide après enregistrement.</p>
    <button class="mi-btn" type="submit">Enregistrer</button>
</form>
