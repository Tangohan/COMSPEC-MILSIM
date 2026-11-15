<?php
declare(strict_types=1);
/**
 * Modale « Modifier le dossier » : tous les champs en place, avec le numéro de version lu (verrou optimiste) et un motif.
 * @var array<string,mixed> $interestCase
 */
$diMode = 'edit';
$diCase = $interestCase;
$diPrefix = 'die';
$autoOpen = ($form['modal'] ?? '') === 'edit';
?>
<dialog class="di-dialog" id="di-edit" aria-labelledby="di-edit-title" data-di-dialog <?= $autoOpen ? 'data-di-autoopen' : '' ?>>
    <form method="post" action="<?= $diH(url('atak/sse/interet/' . (int) $interestCase['id'] . '/modifier')) ?>" novalidate data-di-form>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="version" value="<?= (int) $interestCase['version'] ?>">
        <input type="hidden" name="updated_at" value="<?= $diH($interestCase['updated_at'] ?? '') ?>">
        <div class="di-dialog__head">
            <div>
                <h2 id="di-edit-title">Modifier le dossier</h2>
                <p class="di-lead">Chaque champ modifié est consigné dans l’historique avec l’ancienne et la nouvelle valeur.</p>
            </div>
            <button type="button" class="di-close" data-di-close aria-label="Fermer"><?= $diIcon('close') ?></button>
        </div>
        <div class="di-dialog__body">
            <?php require __DIR__ . '/_fields.php'; ?>
            <div class="di-field<?= $diErr('reason') !== '' ? ' is-invalid' : '' ?>" data-field="reason">
                <label for="die-reason">Motif de la modification</label>
                <input class="di-input" type="text" id="die-reason" name="reason" maxlength="300" autocomplete="off" value="<?= $diH($diOld('reason', '')) ?>" placeholder="Facultatif ; obligatoire pour abaisser la classification">
                <?= $diFieldError('reason') ?>
            </div>
        </div>
        <div class="di-dialog__foot">
            <button type="button" class="di-btn di-btn--ghost" data-di-close>Annuler</button>
            <button type="submit" class="di-btn di-btn--primary">Enregistrer les modifications</button>
        </div>
    </form>
</dialog>
