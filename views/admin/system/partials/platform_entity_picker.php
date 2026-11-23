<?php
declare(strict_types=1);

$isAccountPicker = $pickerType === 'account';
?>
<div class="pa-picker" hidden data-entity-picker="<?= $h($pickerType) ?>"
     data-entity-url="<?= $h(url('admin/recherche')) ?>"
     <?php if (!empty($pickerTarget)): ?>data-entity-target="<?= $h($pickerTarget) ?>"<?php endif; ?>
     <?php if (!empty($pickerDestination)): ?>data-entity-destination="<?= $h($pickerDestination) ?>"<?php endif; ?>>
    <label for="<?= $h($pickerId) ?>"><?= $isAccountPicker ? 'Rechercher un compte' : 'Rechercher une organisation' ?></label>
    <input id="<?= $h($pickerId) ?>" type="search" maxlength="120" autocomplete="off"
           placeholder="<?= $isAccountPicker ? 'Pseudo, indicatif, e-mail, Steam ou n°' : 'Nom, code, adresse courte ou n°' ?>"
           data-entity-query>
    <p class="pu-muted" role="status" data-entity-status>Saisissez au moins deux caractères.</p>
    <ul class="pa-picker__results" data-entity-results></ul>
</div>
