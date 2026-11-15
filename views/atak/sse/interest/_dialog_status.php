<?php
declare(strict_types=1);
/**
 * Modale « Changer l'état » : seuls les états atteignables depuis l'état courant sont proposés (table de transitions du serveur).
 * @var array<string,mixed> $interestCase
 * @var list<array{key:string,label:string,needs_reason:bool}> $next_statuses
 */
$autoOpen = ($form['modal'] ?? '') === 'status';
?>
<dialog class="di-dialog" id="di-status" aria-labelledby="di-status-title" data-di-dialog <?= $autoOpen ? 'data-di-autoopen' : '' ?>>
    <form method="post" action="<?= $diH(url('atak/sse/interet/' . (int) $interestCase['id'] . '/etat')) ?>" novalidate data-di-form>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="version" value="<?= (int) $interestCase['version'] ?>">
        <input type="hidden" name="updated_at" value="<?= $diH($interestCase['updated_at'] ?? '') ?>">
        <div class="di-dialog__head">
            <div>
                <h2 id="di-status-title">Changer l’état</h2>
                <p class="di-lead">État actuel : <strong><?= $diH($interestCase['status_label']) ?></strong>. Les décisions de validation demandent un motif.</p>
            </div>
            <button type="button" class="di-close" data-di-close aria-label="Fermer"><?= $diIcon('close') ?></button>
        </div>
        <div class="di-dialog__body">
            <div class="di-field<?= $diErr('status') !== '' ? ' is-invalid' : '' ?>" data-field="status" role="radiogroup" aria-label="Nouvel état">
                <?php foreach ($next_statuses as $n): ?>
                    <label class="di-radio">
                        <input type="radio" name="status" value="<?= $diH($n['key']) ?>" data-needs-reason="<?= $n['needs_reason'] ? '1' : '0' ?>" <?= (string) $diOld('status', '') === $n['key'] ? 'checked' : '' ?>>
                        <span><strong><?= $diH($n['label']) ?></strong><span class="di-sub"><?= $n['needs_reason'] ? 'Motif obligatoire.' : 'Motif facultatif.' ?></span></span>
                    </label>
                <?php endforeach; ?>
                <?= $diFieldError('status') ?>
            </div>
            <div class="di-field<?= $diErr('reason') !== '' ? ' is-invalid' : '' ?>" data-field="reason">
                <label for="dis-reason">Motif <span class="di-sub" data-di-reason-flag></span></label>
                <textarea class="di-textarea" id="dis-reason" name="reason" rows="3" maxlength="300" placeholder="Ce qui justifie ce changement d’état."><?= $diH($diOld('reason', '')) ?></textarea>
                <?= $diFieldError('reason') ?>
            </div>
            <?= $diFieldError('version') ?>
        </div>
        <div class="di-dialog__foot">
            <button type="button" class="di-btn di-btn--ghost" data-di-close>Annuler</button>
            <button type="submit" class="di-btn di-btn--primary">Enregistrer l’état</button>
        </div>
    </form>
</dialog>
