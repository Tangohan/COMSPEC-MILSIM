<?php
declare(strict_types=1);
/**
 * Modales de l'onglet « Éléments liés » : lier un élément (recherche), retirer un lien (avec motif).
 * @var array<string,mixed> $interestCase
 */
$autoLink = ($form['modal'] ?? '') === 'link';
$autoUnlink = ($form['modal'] ?? '') === 'unlink';
$base = url('atak/sse/interet/' . (int) $interestCase['id']);
?>
<dialog class="di-dialog" id="di-link" aria-labelledby="di-link-title" data-di-dialog <?= $autoLink ? 'data-di-autoopen' : '' ?>>
    <form method="post" action="<?= $diH($base . '/liens') ?>" novalidate data-di-form>
        <?= \App\Core\Csrf::field() ?>
        <div class="di-dialog__head">
            <div>
                <h2 id="di-link-title">Lier un élément</h2>
                <p class="di-lead">Choisissez une personne, une identité, un site, une transmission, une fiche, un document ou un dossier.</p>
            </div>
            <button type="button" class="di-close" data-di-close aria-label="Fermer"><?= $diIcon('close') ?></button>
        </div>
        <div class="di-dialog__body">
            <?php $diPickerMode = 'single'; $diPrefix = 'dil'; require __DIR__ . '/_link_picker.php'; ?>
            <?= $diFieldError('target') ?>
            <?= $diFieldError('label') ?>
            <?= $diFieldError('link_type') ?>
        </div>
        <div class="di-dialog__foot">
            <button type="button" class="di-btn di-btn--ghost" data-di-close>Annuler</button>
            <button type="submit" class="di-btn di-btn--primary">Lier à ce dossier</button>
        </div>
    </form>
</dialog>

<dialog class="di-dialog" id="di-unlink" aria-labelledby="di-unlink-title" data-di-dialog <?= $autoUnlink ? 'data-di-autoopen' : '' ?>>
    <form method="post" action="<?= $diH($base . '/liens/0/retirer') ?>" novalidate data-di-form data-di-unlink-base="<?= $diH($base . '/liens/') ?>">
        <?= \App\Core\Csrf::field() ?>
        <div class="di-dialog__head">
            <div>
                <h2 id="di-unlink-title">Retirer ce lien ?</h2>
                <p class="di-lead">L’élément n’est pas supprimé : seul son rattachement à ce dossier est retiré, et le motif est consigné.</p>
            </div>
            <button type="button" class="di-close" data-di-close aria-label="Fermer"><?= $diIcon('close') ?></button>
        </div>
        <div class="di-dialog__body">
            <p data-di-unlink-label class="di-mono"></p>
            <div class="di-field<?= $diErr('reason') !== '' ? ' is-invalid' : '' ?>" data-field="reason">
                <label for="diu-reason">Motif du retrait</label>
                <input class="di-input" type="text" id="diu-reason" name="reason" maxlength="300" required autocomplete="off" placeholder="Lien erroné, personne hors sujet…">
                <?= $diFieldError('reason') ?>
            </div>
        </div>
        <div class="di-dialog__foot">
            <button type="button" class="di-btn di-btn--ghost" data-di-close>Annuler</button>
            <button type="submit" class="di-btn di-btn--danger">Retirer le lien</button>
        </div>
    </form>
</dialog>
