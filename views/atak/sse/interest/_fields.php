<?php
declare(strict_types=1);
/**
 * Champs du dossier d'intérêt, partagés par la modale de création (deux étapes) et la modale de modification.
 * @var string $diMode            'create' ou 'edit'
 * @var array<string,mixed> $diCase  dossier (modification) ou []
 * @var string $diPrefix          préfixe des identifiants (create / edit)
 * @var array<string,string> $types
 * @var array<string,string> $classifications
 * @var list<array{id:int,label:string}> $members
 * @var array<string,bool> $diCaps
 */
$mode = $diMode ?? 'create';
$case = is_array($diCase ?? null) ? $diCase : [];
$px = (string) ($diPrefix ?? 'di');
$val = static fn (string $k, mixed $d = '') => $diOld($k, $case[$k] ?? $d);
$ext = !empty($diCaps['columns']);
$field = static function (string $name, string $label, string $control, string $hint = '') use ($px, $diFieldError, $diErr, $diH): string {
    $invalid = $diErr($name) !== '' ? ' is-invalid' : '';

    return '<div class="di-field' . $invalid . '" data-field="' . $diH($name) . '"><label for="' . $px . '-' . $diH($name) . '">' . $diH($label) . '</label>'
        . $control . ($hint !== '' ? '<p class="di-hint">' . $diH($hint) . '</p>' : '') . $diFieldError($name) . '</div>';
};
$attrs = static fn (string $name): string => 'id="' . $px . '-' . $name . '" name="' . $name . '"' . ($diErr($name) !== '' ? ' aria-invalid="true" aria-describedby="err-' . $name . '"' : '');
$step = static fn (int $n): string => $mode === 'create' ? ' data-di-step="' . $n . '"' . ($n > 1 ? ' hidden' : '') : '';
?>
<div class="di-dialog__stack"<?= $step(1) ?>>
    <?= $field('temporary_designation', 'Titre du dossier', '<input class="di-input" type="text" ' . $attrs('temporary_designation') . ' maxlength="120" required autocomplete="off" value="' . $diH($val('temporary_designation')) . '" placeholder="Ex. Homme au manteau gris, convoi nord">', '3 à 120 signes. Il sert de nom de travail : aucune identité n’est déduite.') ?>
    <div class="di-field<?= $diErr('opening_reason') !== '' ? ' is-invalid' : '' ?>" data-field="opening_reason">
        <label for="<?= $px ?>-opening_reason">Motif d’ouverture</label>
        <div class="di-rte" role="toolbar" aria-label="Mise en forme du motif">
            <button type="button" data-di-fmt="bold" aria-label="Gras" title="Gras">B</button>
            <button type="button" data-di-fmt="italic" aria-label="Italique" title="Italique"><em>I</em></button>
            <button type="button" data-di-fmt="list" aria-label="Liste" title="Liste">•</button>
        </div>
        <textarea class="di-textarea" <?= $attrs('opening_reason') ?> rows="4" maxlength="4000" <?= $mode === 'create' ? '' : 'required' ?> placeholder="Pourquoi ouvrir ce dossier : ce qui a été observé, par qui, où."><?= $diH($val('opening_reason')) ?></textarea>
        <p class="di-hint">10 signes au moins. Mise en forme : **gras**, *italique*, lignes commençant par « - » pour une liste.</p>
        <?= $diFieldError('opening_reason') ?>
    </div>
    <div class="di-row2">
        <?php if ($ext): ?>
            <?= $field('case_type', 'Type', '<select class="di-select" ' . $attrs('case_type') . '><option value="">Non précisé</option>' . implode('', array_map(
                static fn (string $k, string $l): string => '<option value="' . $diH($k) . '"' . ((string) $val('case_type') === $k ? ' selected' : '') . '>' . $diH($l) . '</option>',
                array_keys($types), array_values($types)
            )) . '</select>') ?>
        <?php endif; ?>
        <?= $field('interest_level', 'Priorité', '<select class="di-select" ' . $attrs('interest_level') . '>' . implode('', array_map(
            static fn (string $k, array $p): string => '<option value="' . $diH($k) . '"' . ((string) $val('interest_level', 'courant') === $k ? ' selected' : '') . '>' . $diH($p['code'] . ' · ' . $p['label']) . '</option>',
            array_keys(\App\Support\SseInterestCaseCatalog::PRIORITIES), array_values(\App\Support\SseInterestCaseCatalog::PRIORITIES)
        )) . '</select>', 'P1 : à traiter sans attendre. P4 : suivi courant.') ?>
    </div>
</div>

<div class="di-dialog__stack"<?= $step(2) ?>>
    <?php if ($ext): ?>
        <div class="di-row2">
            <?= $field('classification', 'Classification', '<select class="di-select" ' . $attrs('classification') . '>' . implode('', array_map(
                static fn (string $k, string $l): string => '<option value="' . $diH($k) . '"' . ((string) $val('classification', 'interne') === $k ? ' selected' : '') . '>' . $diH($l) . '</option>',
                array_keys($classifications), array_values($classifications)
            )) . '</select>', 'Jamais au-dessus de votre habilitation.') ?>
            <?= $field('assignee_user_id', 'Responsable', '<select class="di-select" ' . $attrs('assignee_user_id') . '><option value="">Aucun responsable</option>' . implode('', array_map(
                static fn (array $m): string => '<option value="' . (int) $m['id'] . '"' . ((int) $val('assignee_user_id', 0) === (int) $m['id'] ? ' selected' : '') . '>' . $diH($m['label']) . '</option>',
                $members
            )) . '</select>', 'Il est prévenu dans le centre de notifications.') ?>
        </div>
        <div class="di-row2">
            <?= $field('due_at', 'Échéance', '<input class="di-input" type="date" ' . $attrs('due_at') . ' value="' . $diH(substr((string) $val('due_at'), 0, 10)) . '">', 'Facultative. Le dossier passe « en retard » le lendemain.') ?>
            <?= $field('tags', 'Étiquettes', '<input class="di-input" type="text" ' . $attrs('tags') . ' maxlength="300" autocomplete="off" value="' . $diH(str_replace(',', ', ', (string) $val('tags'))) . '" placeholder="convoi, nord, véhicule">', 'Séparées par des virgules : 8 au plus, 24 signes chacune.') ?>
        </div>
    <?php endif; ?>
    <div class="di-row2">
        <?= $field('suspected_alias', 'Alias supposé', '<input class="di-input" type="text" ' . $attrs('suspected_alias') . ' maxlength="160" autocomplete="off" value="' . $diH($val('suspected_alias')) . '">') ?>
        <?= $field('suspected_affiliation', 'Affiliation supposée', '<input class="di-input" type="text" ' . $attrs('suspected_affiliation') . ' maxlength="160" autocomplete="off" value="' . $diH($val('suspected_affiliation')) . '">') ?>
    </div>
    <div class="di-row2">
        <?= $field('confidence_level', 'Confiance dans le signalement', '<select class="di-select" ' . $attrs('confidence_level') . '>' . implode('', array_map(
            static fn (string $k, string $l): string => '<option value="' . $diH($k) . '"' . ((string) $val('confidence_level', 'non_evalue') === $k ? ' selected' : '') . '>' . $diH($l) . '</option>',
            array_keys($confidenceLevels), array_values($confidenceLevels)
        )) . '</select>') ?>
    </div>
    <div class="di-field<?= $diErr('description') !== '' ? ' is-invalid' : '' ?>" data-field="description">
        <label for="<?= $px ?>-description">Description</label>
        <div class="di-rte" role="toolbar" aria-label="Mise en forme de la description">
            <button type="button" data-di-fmt="bold" aria-label="Gras" title="Gras">B</button>
            <button type="button" data-di-fmt="italic" aria-label="Italique" title="Italique"><em>I</em></button>
            <button type="button" data-di-fmt="list" aria-label="Liste" title="Liste">•</button>
        </div>
        <textarea class="di-textarea" <?= $attrs('description') ?> rows="5" maxlength="8000" placeholder="Contexte, enjeux, périmètre de collecte…"><?= $diH($val('description')) ?></textarea>
        <p class="di-hint">Facultative. Aucune balise HTML n’est acceptée : seules les trois mises en forme ci-dessus.</p>
        <?= $diFieldError('description') ?>
    </div>
</div>
