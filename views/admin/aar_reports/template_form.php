<?php
declare(strict_types=1);

$template = is_array($aarTemplate ?? null) ? $aarTemplate : [];
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$isEdit = (int) ($template['id'] ?? 0) > 0;
$fields = is_array($template['fields'] ?? null) ? $template['fields'] : [];
$initial = [];
foreach ($fields as $field) {
    if (!is_array($field)) {
        continue;
    }
    $opts = is_array($field['options'] ?? null) ? $field['options'] : [];
    $initial[] = [
        'id' => (string) ($field['id'] ?? ''),
        'type' => (string) ($field['type'] ?? 'text'),
        'label' => (string) ($field['label'] ?? ''),
        'help' => (string) ($field['help'] ?? ''),
        'required' => !empty($field['required']),
        'optionsText' => implode("\n", array_map(static fn (mixed $v): string => (string) $v, $opts)),
    ];
}
$initialJson = json_encode($initial, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
if (!is_string($initialJson)) {
    $initialJson = '[]';
}

$s = \App\Core\Session::getFlash('success');
$e = \App\Core\Session::getFlash('error');
$action = $isEdit
    ? url('back-office/atak/comptes-rendus/modeles/' . (int) $template['id'])
    : url('back-office/atak/comptes-rendus/modeles');
?>
<?php if ($s): ?><div class="ath-banner-warn ath-rise" style="background:#e6f8f0;border-color:#bfe9d8;margin-bottom:16px;" role="status"><div class="ath-banner-warn__text" style="color:#0b6b47;"><?= $h((string) $s) ?></div></div><?php endif; ?>
<?php if ($e): ?><div class="ath-banner-warn ath-rise" style="margin-bottom:16px;" role="alert"><div class="ath-banner-warn__text"><?= $h((string) $e) ?></div></div><?php endif; ?>

<form method="post" action="<?= $h($action) ?>" class="ath-card ath-aar-form-card ath-aar-builder ath-rise" x-data="aarTemplateBuilder(<?= $initialJson ?>)">
    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">

    <label for="tpl-title">Nom du modèle</label>
    <input id="tpl-title" name="title" required value="<?= $h((string) ($template['title'] ?? '')) ?>" placeholder="Ex. Debriefing d’assaut urbain">

    <label for="tpl-desc">Présentation (facultatif)</label>
    <textarea id="tpl-desc" name="description" rows="2" placeholder="À quel moment utiliser ce questionnaire"><?= $h((string) ($template['description'] ?? '')) ?></textarea>

    <?php if ($isEdit): ?>
    <label for="tpl-status">Statut</label>
    <select id="tpl-status" name="status">
        <option value="active" <?= (($template['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Actif</option>
        <option value="archived" <?= (($template['status'] ?? '') === 'archived') ? 'selected' : '' ?>>Archivé</option>
    </select>
    <?php endif; ?>

    <div class="ath-aar-builder__head">
        <h2>Questions</h2>
    </div>
    <p class="ath-aar-form-card__hint">
        Ajoutez les champs que les opérateurs rempliront. Pour une liste ou des cases à cocher, indiquez un choix par ligne.
        Sans choix, les cases à cocher deviennent une question Oui / Non.
    </p>
    <div class="ath-aar-builder__types">
        <button type="button" class="ath-btn" @click="addField('text')" :disabled="fields.length >= 40">Question courte</button>
        <button type="button" class="ath-btn" @click="addField('select')" :disabled="fields.length >= 40">Liste déroulante</button>
        <button type="button" class="ath-btn" @click="addField('checkbox')" :disabled="fields.length >= 40">Cases à cocher</button>
        <button type="button" class="ath-btn" @click="addField('textarea')" :disabled="fields.length >= 40">Texte libre</button>
    </div>

    <p class="ath-aar-form-card__hint" x-show="fields.length === 0">
        Ajoutez au moins une question avec les boutons ci-dessus.
    </p>

    <template x-for="(field, index) in fields" :key="field.uid">
        <article class="ath-aar-builder-card">
            <input type="hidden" :name="'fields[' + index + '][id]'" :value="field.id">
            <div class="ath-aar-builder-card__toolbar">
                <span class="ath-aar-builder-card__num" x-text="'Question ' + (index + 1)"></span>
                <button type="button" class="ath-btn" @click="move(index, -1)" :disabled="index === 0">Monter</button>
                <button type="button" class="ath-btn" @click="move(index, 1)" :disabled="index === fields.length - 1">Descendre</button>
                <button type="button" class="ath-btn" @click="removeField(index)">Retirer</button>
            </div>

            <label>Intitulé</label>
            <input type="text" :name="'fields[' + index + '][label]'" x-model="field.label" required placeholder="Question posée à l’opérateur">

            <label>Type de réponse</label>
            <select :name="'fields[' + index + '][type]'" x-model="field.type">
                <option value="text">Question courte</option>
                <option value="textarea">Zone de texte libre</option>
                <option value="select">Liste déroulante</option>
                <option value="checkbox">Cases à cocher</option>
            </select>

            <label>Précision (facultatif)</label>
            <input type="text" :name="'fields[' + index + '][help]'" x-model="field.help" placeholder="Texte d’aide sous la question">

            <label class="ath-aar-builder__check">
                <input type="checkbox" :name="'fields[' + index + '][required]'" value="1" x-model="field.required">
                Réponse obligatoire
            </label>

            <div x-show="needsOptions(field.type)">
                <label>Choix proposés (un par ligne)</label>
                <textarea :name="'fields[' + index + '][options]'" rows="4" x-model="field.optionsText" placeholder="Choix 1&#10;Choix 2"></textarea>
            </div>
        </article>
    </template>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
        <button type="submit" class="ath-btn ath-btn--solid" :disabled="fields.length === 0"><?= $isEdit ? 'Enregistrer le modèle' : 'Créer le modèle' ?></button>
        <a class="ath-btn" href="<?= $h(url('back-office/atak/comptes-rendus/modeles')) ?>">Annuler</a>
    </div>
</form>

<script>
function aarTemplateBuilder(initial) {
    let seq = 0;
    const blank = function (type) {
        return { uid: ++seq, id: '', type: type || 'text', label: '', help: '', required: false, optionsText: '' };
    };
    const seeded = (Array.isArray(initial) ? initial : []).map(function (row) {
        const next = blank(row.type || 'text');
        next.id = row.id || '';
        next.label = row.label || '';
        next.help = row.help || '';
        next.required = !!row.required;
        next.optionsText = row.optionsText || '';
        return next;
    });
    return {
        fields: seeded,
        addField(type) {
            if (this.fields.length >= 40) return;
            this.fields.push(blank(type));
        },
        removeField(index) {
            this.fields.splice(index, 1);
        },
        move(index, dir) {
            const target = index + dir;
            if (target < 0 || target >= this.fields.length) return;
            const row = this.fields.splice(index, 1)[0];
            this.fields.splice(target, 0, row);
        },
        needsOptions(type) {
            return type === 'select' || type === 'checkbox';
        }
    };
}
</script>
