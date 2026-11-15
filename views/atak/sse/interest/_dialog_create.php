<?php
declare(strict_types=1);
/**
 * Modale « Ouvrir un dossier d'intérêt » : trois étapes courtes (l'essentiel, le cadrage, les sujets liés et la signature),
 * brouillon enregistré automatiquement dans le navigateur, brouillon serveur sur demande.
 * @var array $form état du formulaire (erreurs par champ, saisie précédente)
 */
$diMode = 'create';
$diCase = [];
$diPrefix = 'dic';
$autoOpen = ($form['modal'] ?? '') === 'create';
$signer = (string) ($actor->label ?? 'Analyste');
?>
<dialog class="di-dialog" id="di-create" aria-labelledby="di-create-title" data-di-dialog data-di-draft-key="di-draft-<?= (int) ($actor->tenantId ?? 0) ?>-<?= (int) ($actor->userId ?? 0) ?>" <?= $autoOpen ? 'data-di-autoopen' : '' ?>>
    <form method="post" action="<?= $diH(url('atak/sse/interet')) ?>" novalidate data-di-form data-di-steps="3">
        <?= \App\Core\Csrf::field() ?>
        <div class="di-dialog__head">
            <div>
                <h2 id="di-create-title">Ouvrir un dossier d’intérêt</h2>
                <p class="di-lead">Un signalement à qualifier : une hypothèse de travail, jamais une identité confirmée.</p>
            </div>
            <button type="button" class="di-close" data-di-close aria-label="Fermer"><?= $diIcon('close') ?></button>
        </div>
        <ol class="di-steps" aria-label="Étapes">
            <li class="is-current" data-di-stepmark="1">1 · L’essentiel</li>
            <li data-di-stepmark="2">2 · Cadrage</li>
            <li data-di-stepmark="3">3 · Liens et signature</li>
        </ol>
        <div class="di-draftbar" data-di-draftbar hidden>
            <span>Un brouillon non envoyé a été retrouvé sur cet appareil.</span>
            <span class="di-inline">
                <button type="button" class="di-btn" data-di-draft-restore>Reprendre</button>
                <button type="button" class="di-btn di-btn--ghost" data-di-draft-clear>Effacer</button>
            </span>
        </div>
        <div class="di-dialog__body">
            <?php require __DIR__ . '/_fields.php'; ?>
            <div class="di-dialog__stack" data-di-step="3" hidden>
                <div class="di-panel__head">
                    <h3>Sujets liés</h3>
                    <p>Personnes, identités, sites, véhicules, transmissions, fiches : ajoutez ce qui se rattache déjà à ce dossier (facultatif, 10 au plus).</p>
                </div>
                <?php $diPickerMode = 'multi'; $diPrefix = 'dicl'; require __DIR__ . '/_link_picker.php'; $diPrefix = 'dic'; ?>
                <label class="di-check<?= $diErr('digital_signature') !== '' ? ' is-invalid' : '' ?>" data-field="digital_signature">
                    <input type="checkbox" name="digital_signature" value="1" <?= $diOld('digital_signature', '') !== '' ? 'checked' : '' ?>>
                    <span>Je signe numériquement ce signalement</span><strong><?= $diH($signer) ?></strong>
                </label>
                <?= $diFieldError('digital_signature') ?>
            </div>
        </div>
        <div class="di-dialog__foot">
            <button type="button" class="di-btn di-btn--ghost is-left" data-di-close>Annuler</button>
            <button type="submit" class="di-btn" name="draft" value="1" data-di-draftbtn>Enregistrer en brouillon</button>
            <button type="button" class="di-btn" data-di-prev hidden>Précédent</button>
            <button type="button" class="di-btn di-btn--primary" data-di-next>Suivant</button>
            <button type="submit" class="di-btn di-btn--primary" data-di-submit hidden>Ouvrir le dossier</button>
        </div>
    </form>
</dialog>
