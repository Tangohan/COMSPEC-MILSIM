<?php
declare(strict_types=1);
/**
 * Modales de suppression : mise à la corbeille (restaurable 30 jours) ou, pour un dossier déjà à la corbeille et un gestionnaire,
 * suppression définitive avec motif et référence retapée.
 * @var array<string,mixed> $interestCase
 * @var list<string> $purge_blockers
 */
$isTrashed = !empty($interestCase['deleted_at']);
$autoTrash = ($form['modal'] ?? '') === 'trash';
$autoPurge = ($form['modal'] ?? '') === 'purge';
$days = \App\Support\SseInterestCaseCatalog::TRASH_DAYS;
?>
<?php if (!$isTrashed && !empty($actor->canDelete)): ?>
<dialog class="di-dialog" id="di-trash" aria-labelledby="di-trash-title" data-di-dialog <?= $autoTrash ? 'data-di-autoopen' : '' ?>>
    <form method="post" action="<?= $diH(url('atak/sse/interet/' . (int) $interestCase['id'] . '/supprimer')) ?>" novalidate data-di-form>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="version" value="<?= (int) $interestCase['version'] ?>">
        <div class="di-dialog__head">
            <div>
                <h2 id="di-trash-title">Supprimer ce dossier ?</h2>
                <p class="di-lead">Il part à la corbeille et reste restaurable pendant <?= $days ?> jours. Rien n’est effacé tout de suite.</p>
            </div>
            <button type="button" class="di-close" data-di-close aria-label="Fermer"><?= $diIcon('close') ?></button>
        </div>
        <div class="di-dialog__body">
            <p><strong><?= $diH($interestCase['reference_code']) ?></strong> · <?= $diH($interestCase['temporary_designation']) ?></p>
            <div class="di-field<?= $diErr('reason') !== '' ? ' is-invalid' : '' ?>" data-field="reason">
                <label for="dit-reason">Motif de la suppression</label>
                <textarea class="di-textarea" id="dit-reason" name="reason" rows="3" maxlength="300" required placeholder="Doublon, ouvert par erreur, sans objet…"><?= $diH($diOld('reason', '')) ?></textarea>
                <?= $diFieldError('reason') ?>
            </div>
            <?= $diFieldError('version') ?>
        </div>
        <div class="di-dialog__foot">
            <button type="button" class="di-btn di-btn--ghost" data-di-close>Annuler</button>
            <button type="submit" class="di-btn di-btn--danger">Mettre à la corbeille</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php if ($isTrashed && !empty($actor->canManage)): ?>
<dialog class="di-dialog" id="di-purge" aria-labelledby="di-purge-title" data-di-dialog <?= $autoPurge ? 'data-di-autoopen' : '' ?>>
    <form method="post" action="<?= $diH(url('atak/sse/interet/' . (int) $interestCase['id'] . '/supprimer-definitivement')) ?>" novalidate data-di-form>
        <?= \App\Core\Csrf::field() ?>
        <div class="di-dialog__head">
            <div>
                <h2 id="di-purge-title">Supprimer définitivement ?</h2>
                <p class="di-lead">Cette action est irréversible : le dossier, son historique, ses liens et ses notes sont effacés. Une trace reste au journal d’audit.</p>
            </div>
            <button type="button" class="di-close" data-di-close aria-label="Fermer"><?= $diIcon('close') ?></button>
        </div>
        <div class="di-dialog__body">
            <?php if ($purge_blockers !== []): ?>
                <div class="di-alert di-alert--warn" role="status">
                    <div>
                        <strong>Suppression définitive impossible</strong>
                        <p><?= $diH(ucfirst(implode(' ; ', $purge_blockers))) ?>. Restaurez le dossier puis archivez-le : l’archivage garde tout et reste réversible.</p>
                    </div>
                </div>
            <?php endif; ?>
            <div class="di-field<?= $diErr('reason') !== '' ? ' is-invalid' : '' ?>" data-field="reason">
                <label for="dip-reason">Motif de la suppression définitive</label>
                <textarea class="di-textarea" id="dip-reason" name="reason" rows="3" maxlength="300" required <?= $purge_blockers !== [] ? 'disabled' : '' ?>><?= $diH($diOld('reason', '')) ?></textarea>
                <?= $diFieldError('reason') ?>
            </div>
            <div class="di-field<?= $diErr('confirm') !== '' ? ' is-invalid' : '' ?>" data-field="confirm">
                <label for="dip-confirm">Retapez la référence <span class="di-mono"><?= $diH($interestCase['reference_code']) ?></span> pour confirmer</label>
                <input class="di-input" type="text" id="dip-confirm" name="confirm" autocomplete="off" required <?= $purge_blockers !== [] ? 'disabled' : '' ?> data-di-confirm="<?= $diH($interestCase['reference_code']) ?>">
                <?= $diFieldError('confirm') ?>
            </div>
        </div>
        <div class="di-dialog__foot">
            <button type="button" class="di-btn di-btn--ghost" data-di-close>Annuler</button>
            <button type="submit" class="di-btn di-btn--danger" data-di-confirm-submit <?= $purge_blockers !== [] ? 'disabled' : '' ?>>Supprimer définitivement</button>
        </div>
    </form>
</dialog>
<?php endif; ?>
