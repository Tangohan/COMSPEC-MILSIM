<?php

declare(strict_types=1);

use App\Services\Account\PlatformBanNotice;
?>
<fieldset class="pu-dialog__field" data-ban-notice>
    <legend>Avis communiqué à la personne</legend>
    <p class="pu-muted">Le commentaire interne reste dans le journal. L’avis ci-dessous est visible par la personne après vérification de son adresse.</p>
    <label class="pu-dialog__sub">
        <span>Modèle de rédaction</span>
        <select data-ban-notice-template>
            <option value="">Choisir un modèle</option>
            <?php foreach (PlatformBanNotice::MODELS as $model => $copy): ?>
                <option value="<?= $bh($model) ?>" data-rule="<?= $bh($copy['rule']) ?>" data-message="<?= $bh($copy['text']) ?>"
                        data-closed-message="<?= $bh(PlatformBanNotice::displayMessage($copy['text'], true)) ?>"><?= $bh($copy['rule']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="button" class="pa-btn pa-btn--line pa-btn--sm" data-ban-use-template>Insérer le modèle</button>
    <label class="pu-dialog__sub">
        <span>Règle ou article cité</span>
        <input type="text" name="rule_reference" maxlength="160" placeholder="Ex. CGU Athena · Règles de conduite">
    </label>
    <label class="pu-dialog__sub">
        <span>Explication publique</span>
        <textarea name="public_reason" rows="4" maxlength="2000" placeholder="Décrivez les faits utiles, leur date et les dispositions concernées."></textarea>
    </label>
    <?php if (!empty($banNoticeSupportsClosure)): ?>
        <label class="pu-check"><input type="checkbox" name="appeal_closed" value="1"> Interdire les nouvelles contestations</label>
        <p class="pu-muted">La personne pourra consulter la décision. Vous pourrez rouvrir les contestations à tout moment.</p>
    <?php endif; ?>
    <details class="pa-card" data-ban-notice-preview>
        <summary>Aperçu de l’avis</summary>
        <p data-ban-preview-duration></p>
        <p data-ban-preview-rule hidden></p>
        <p data-ban-preview-body data-default-message="<?= $bh(PlatformBanNotice::DEFAULT_TEXT) ?>"
           data-closed-message="<?= $bh(PlatformBanNotice::displayMessage('', true)) ?>" style="white-space:pre-wrap"><?= $bh(PlatformBanNotice::DEFAULT_TEXT) ?></p>
        <button type="button" class="pa-btn pa-btn--line pa-btn--sm" data-ban-copy>Copier l’avis</button>
        <span class="pu-muted" role="status" data-ban-copy-status></span>
    </details>
</fieldset>
