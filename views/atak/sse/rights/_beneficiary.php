<?php
declare(strict_types=1);

use App\Support\SseAccessCatalog as C;

/**
 * Champ unique « ajouter un bénéficiaire » : on tape une position, une qualification ou un nom, la liste se filtre, on
 * choisit. Les personnes se cherchent par prénom, nom ou indicatif (annuaire, jamais d'identité masquée). Sans JavaScript,
 * une liste déroulante classique prend le relais (balise noscript). Aucun surtitre.
 *
 * @var list<array{ref: string, label: string, group: string, kind: string}> $picker options (positions, fonctions, qualifications, niveaux d'accès, unités)
 * @var string $pickId identifiant du champ
 * @var string $pickName nom du champ envoyé (défaut subject_ref)
 * @var string $pickLabel libellé visible
 * @var list<string> $pickKinds types acceptés (vide : tous)
 * @var bool $pickPeople proposer aussi les personnes
 * @var string $pickSelected référence « type:id » présélectionnée
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pickName = (string) ($pickName ?? 'subject_ref');
$pickKinds = isset($pickKinds) && is_array($pickKinds) ? array_values($pickKinds) : [];
$pickPeople = (bool) ($pickPeople ?? true) && ($pickKinds === [] || in_array(C::SUBJECT_USER, $pickKinds, true));
$pickSelected = (string) ($pickSelected ?? '');
$pickItems = array_values(array_filter($picker ?? [], static fn (array $o): bool => $pickKinds === [] || in_array($o['kind'], $pickKinds, true)));
$pickGroups = [];
foreach ($pickItems as $o) {
    $pickGroups[$o['group']][] = $o;
}
$data = ['items' => $pickItems, 'people' => $pickPeople, 'selected' => $pickSelected];
?>
<div class="sar-pick" data-sar-pick data-people-url="<?= $h(url($base . '/personnes')) ?>">
    <label class="fx-f" for="<?= $h($pickId) ?>"><span><?= $h($pickLabel) ?></span></label>
    <div class="sar-pick-js" hidden>
        <input class="fx-in" type="search" id="<?= $h($pickId) ?>" autocomplete="off" placeholder="<?= $h($pickPeople ? 'Position, qualification ou nom' : 'Position ou qualification') ?>" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="<?= $h($pickId) ?>-list" data-sar-q>
        <input type="hidden" name="<?= $h($pickName) ?>" value="<?= $h($pickSelected) ?>" data-sar-val>
        <div class="sar-pick-filters" role="group" aria-label="Filtrer les bénéficiaires" data-sar-filters></div>
        <ul class="sar-pick-list" id="<?= $h($pickId) ?>-list" role="listbox" hidden data-sar-list></ul>
        <p class="sar-pick-chosen" data-sar-chosen hidden><span class="fx-flg sar-allow" data-sar-chosen-label></span> <button type="button" class="btn sm" data-sar-clear>Changer</button></p>
    </div>
    <noscript>
        <select class="fx-in" name="<?= $h($pickName) ?>" required>
            <option value="">Choisir un bénéficiaire</option>
            <?php foreach ($pickGroups as $group => $items): ?>
                <optgroup label="<?= $h($group) ?>">
                    <?php foreach ($items as $o): ?>
                        <option value="<?= $h($o['ref']) ?>"<?= $pickSelected === $o['ref'] ? ' selected' : '' ?>><?= $h($o['label']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <small class="fx-help">Sans JavaScript, les personnes ne se cherchent pas : ouvrez la fiche du membre.</small>
    </noscript>
    <script type="application/json" data-sar-data><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</div>
