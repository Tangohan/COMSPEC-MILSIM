<?php
declare(strict_types=1);
/**
 * Recherche et choix d'éléments à lier (personnes, identités, sites, véhicules, transmissions, fiches, documents, dossiers).
 * La recherche appelle /atak/sse/interet/liens/recherche : le serveur ne renvoie que ce que le lecteur a le droit de voir.
 * @var string $diPickerMode  'multi' (création : choix cumulés) ou 'single' (ajout à un dossier)
 * @var string $diPrefix
 */
$mode = $diPickerMode ?? 'single';
$px = (string) ($diPrefix ?? 'di');
$linkTypes = \App\Support\SseInterestCaseCatalog::LINK_TYPES;
?>
<div class="di-picker" data-di-picker="<?= $diH($mode) ?>" data-search-url="<?= $diH(url('atak/sse/interet/liens/recherche')) ?>">
    <div class="di-row2">
        <div class="di-field">
            <label for="<?= $px ?>-link_type">Type d’élément</label>
            <select class="di-select" id="<?= $px ?>-link_type" <?= $mode === 'single' ? 'name="link_type"' : '' ?> data-di-picker-type>
                <?php foreach ($linkTypes as $key => $label): ?>
                    <option value="<?= $diH($key) ?>"><?= $diH($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="di-field" data-di-picker-searchbox>
            <label for="<?= $px ?>-link_q">Rechercher</label>
            <input class="di-input" type="search" id="<?= $px ?>-link_q" autocomplete="off" placeholder="Nom, référence, plaque…" data-di-picker-q aria-controls="<?= $px ?>-link_results">
            <p class="di-hint" data-di-picker-hint>Tapez 2 lettres au moins : seuls les éléments que vous pouvez consulter sont proposés.</p>
        </div>
    </div>
    <ul class="di-results" id="<?= $px ?>-link_results" data-di-picker-results hidden></ul>
    <div class="di-field" data-di-picker-free hidden>
        <label for="<?= $px ?>-link_label">Plaque ou description du véhicule</label>
        <div class="di-inline">
            <input class="di-input di-grow" type="text" id="<?= $px ?>-link_label" maxlength="120" autocomplete="off" placeholder="Ex. AB-123-CD, pick-up gris" data-di-picker-label>
            <?php if ($mode === 'multi'): ?><button type="button" class="di-btn" data-di-picker-addfree>Ajouter</button><?php endif; ?>
        </div>
        <p class="di-hint">Il n’existe pas de registre des véhicules : saisissez ce qui permet de le reconnaître.</p>
    </div>
    <?php if ($mode === 'single'): ?>
        <input type="hidden" name="target_id" value="" data-di-picker-target>
        <input type="hidden" name="label" value="" data-di-picker-labelout>
        <div class="di-picked" data-di-picker-picked aria-live="polite"></div>
        <div class="di-field">
            <label for="<?= $px ?>-link_note">Note sur ce lien</label>
            <input class="di-input" type="text" id="<?= $px ?>-link_note" name="note" maxlength="300" autocomplete="off" placeholder="Pourquoi ce lien (facultatif)">
        </div>
    <?php else: ?>
        <div class="di-picked" data-di-picker-picked aria-live="polite"></div>
        <div data-di-picker-inputs></div>
    <?php endif; ?>
</div>
