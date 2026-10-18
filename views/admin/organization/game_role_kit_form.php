<?php
declare(strict_types=1);

use App\Services\GameEquipment\RoleKitLoadout;
use App\Support\ArsenalLoadoutItems;

/**
 * Kits par rôle — création / modification.
 *
 * Chaque emplacement est un champ texte (nom de classe Arma) : sans JavaScript, on peut le saisir à la main.
 * Avec back-office-game-equipment.js, « Choisir » ouvre un sélecteur avec recherche dans le catalogue observé
 * (JSON embarqué), et le résumé calcule les mods requis en direct.
 *
 * @var array<string, mixed>|null $kit
 * @var array{name: string, description: string, job_role_id: int, slots: array{weapons: array<string, array<string, string>>, simple: array<string, string>, cargo: array<string, list<array{class: string, count: int}>>}} $values
 * @var array<int, string> $roleOptions
 * @var list<array{class: string, name: string, kind: string, mod: string, max_ammo: int|null, players: int}> $catalogue
 * @var array<string, string> $kindLabels
 * @var bool $kitsReady
 * @var string $formAction
 * @var string $csrf
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$kit = is_array($kit ?? null) ? $kit : null;
$values = is_array($values ?? null) ? $values : [];
$slots = RoleKitLoadout::normalize(is_array($values['slots'] ?? null) ? $values['slots'] : []);
$roleOptions = is_array($roleOptions ?? null) ? $roleOptions : [];
$catalogue = is_array($catalogue ?? null) ? $catalogue : [];
$kindLabels = is_array($kindLabels ?? null) ? $kindLabels : [];
$csrf = (string) ($csrf ?? '');
$roleId = (int) ($values['job_role_id'] ?? 0);

$byClass = [];
foreach ($catalogue as $c) {
    $byClass[strtolower($c['class'])] = $c;
}
$classes = RoleKitLoadout::classes($slots);
$mods = RoleKitLoadout::requiredMods($classes, $byClass);

$catalogueJson = json_encode(array_map(static fn (array $c): array => [
    'c' => $c['class'],
    'n' => $c['name'] !== '' ? $c['name'] : ArsenalLoadoutItems::displayName($c['class']),
    'k' => $c['kind'],
    'm' => $c['mod'],
    'p' => $c['players'],
], $catalogue), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?: '[]';

$weaponDefs = [
    'primary' => ['label' => 'Arme principale', 'att' => ['optic' => 'Optique', 'muzzle' => 'Bouche / silencieux', 'pointer' => 'Pointeur / lampe', 'bipod' => 'Bipied', 'magazine' => 'Chargeur engagé']],
    'secondary' => ['label' => 'Lanceur', 'att' => ['optic' => 'Optique', 'magazine' => 'Munition engagée']],
    'handgun' => ['label' => 'Arme de poing', 'att' => ['optic' => 'Optique', 'muzzle' => 'Silencieux', 'pointer' => 'Pointeur / lampe', 'magazine' => 'Chargeur engagé']],
];
$gearDefs = [
    'uniform' => 'Tenue',
    'vest' => 'Gilet',
    'backpack' => 'Sac',
    'headgear' => 'Casque / coiffe',
    'goggles' => 'Lunettes / masque',
    'nvg' => 'Vision nocturne',
    'binocular' => 'Jumelles',
];
$assignedDefs = [
    'radio' => 'Radio',
    'map' => 'Carte',
    'gps' => 'GPS / terminal',
    'compass' => 'Boussole',
    'watch' => 'Montre',
];
$containerLabels = ['uniform' => 'Dans la tenue', 'vest' => 'Dans le gilet', 'backpack' => 'Dans le sac'];

$field = static function (string $name, string $value, string $kind, string $label, string $id) use ($h, $byClass): void {
    $known = $value !== '' ? ($byClass[strtolower($value)] ?? null) : null;
    $display = $known !== null ? ($known['name'] !== '' ? $known['name'] : ArsenalLoadoutItems::displayName($value)) : '';
    echo '<div class="bo-ge-slot" data-ge-slot data-ge-kind="' . $h($kind) . '">'
        . '<label class="bo-ge-slot__label" for="' . $h($id) . '">' . $h($label) . '</label>'
        . '<div class="bo-ge-slot__row">'
        . '<input class="bo-ge-input bo-ge-input--mono" type="text" id="' . $h($id) . '" name="' . $h($name) . '" value="' . $h($value) . '" placeholder="Nom de classe" autocomplete="off" spellcheck="false" translate="no" data-ge-class>'
        . '<button type="button" class="ath-btn ath-btn--sm" data-ge-pick hidden>Choisir</button>'
        . '</div>'
        . '<span class="bo-ge-slot__name" data-ge-name translate="no">' . $h($display) . ($known !== null && $known['mod'] !== '' ? ' · ' . $h($known['mod']) : '') . '</span>'
        . '</div>';
};
$cargoRow = static function (string $container, int $i, string $class, int $count) use ($h): void {
    echo '<li class="bo-ge-cargo__row" data-ge-cargo-row>'
        . '<div class="bo-ge-slot" data-ge-slot data-ge-kind="">'
        . '<div class="bo-ge-slot__row">'
        . '<input class="bo-ge-input bo-ge-input--mono" type="text" name="cargo[' . $h($container) . '][' . $i . '][class]" value="' . $h($class) . '" placeholder="Nom de classe" aria-label="Objet" autocomplete="off" spellcheck="false" translate="no" data-ge-class>'
        . '<button type="button" class="ath-btn ath-btn--sm" data-ge-pick hidden>Choisir</button>'
        . '</div>'
        . '<span class="bo-ge-slot__name" data-ge-name translate="no"></span>'
        . '</div>'
        . '<label class="bo-ge-cargo__count"><span>Qté</span><input class="bo-ge-input" type="number" min="1" max="99" name="cargo[' . $h($container) . '][' . $i . '][count]" value="' . max(1, $count) . '"></label>'
        . '<button type="button" class="bo-ge-iconbtn" data-ge-cargo-remove aria-label="Retirer la ligne" hidden>×</button>'
        . '</li>';
};
?>
<div class="bo-ge" data-ge data-ge-form>
    <?php if (empty($kitsReady)): ?>
    <section class="bo-ge-note bo-ge-note--warn" role="status">
        <div>
            <p class="bo-ge-note__title">Mise à jour à appliquer</p>
            <p class="bo-ge-note__text">La table des kits n’existe pas encore sur ce serveur. Lancez <code>php run-migrations.php</code> avant d’enregistrer.</p>
        </div>
    </section>
    <?php endif; ?>
    <?php if ($catalogue === []): ?>
    <section class="bo-ge-note" role="status">
        <div>
            <p class="bo-ge-note__title">Catalogue vide</p>
            <p class="bo-ge-note__text">Aucun objet n’a encore été vu en jeu : le sélecteur sera vide. Vous pouvez saisir les noms de classe Arma à la main, ou attendre la prochaine session.</p>
        </div>
    </section>
    <?php endif; ?>

    <form method="post" action="<?= $h((string) $formAction) ?>" class="bo-ge-form" data-ge-kit-form>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <div class="bo-ge-form__layout">
            <div class="bo-ge-form__main">
                <section class="bo-ge-section">
                    <header class="bo-ge-section__head">
                        <div>
                            <h2 class="bo-ge-section__title">Le kit</h2>
                            <p class="bo-ge-section__lead">La tenue publiée s’appellera « [Rôle] Nom du kit ».</p>
                        </div>
                    </header>
                    <div class="bo-ge-fields">
                        <label class="bo-ge-field">
                            <span class="bo-ge-field__label">Nom du kit</span>
                            <input class="bo-ge-input" type="text" name="name" maxlength="90" required value="<?= $h((string) ($values['name'] ?? '')) ?>" placeholder="Kit standard">
                        </label>
                        <label class="bo-ge-field">
                            <span class="bo-ge-field__label">Proposé pour le rôle</span>
                            <select class="bo-ge-input" name="job_role_id">
                                <option value="0">Tous les rôles</option>
                                <?php foreach ($roleOptions as $id => $name): ?>
                                <option value="<?= (int) $id ?>"<?= (int) $id === $roleId ? ' selected' : '' ?> translate="no"><?= $h($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="bo-ge-field bo-ge-field--wide">
                            <span class="bo-ge-field__label">Description <small>(facultatif)</small></span>
                            <textarea class="bo-ge-input" name="description" rows="3" maxlength="1000" placeholder="Usage, consignes, variantes…"><?= $h((string) ($values['description'] ?? '')) ?></textarea>
                        </label>
                    </div>
                </section>

                <section class="bo-ge-section">
                    <header class="bo-ge-section__head">
                        <div>
                            <h2 class="bo-ge-section__title">Armes</h2>
                            <p class="bo-ge-section__lead">Les accessoires ne sont gardés que si l’arme est choisie.</p>
                        </div>
                    </header>
                    <?php foreach ($weaponDefs as $w => $def): ?>
                    <fieldset class="bo-ge-weapon">
                        <legend class="bo-ge-weapon__legend"><?= $h($def['label']) ?></legend>
                        <?php $field('slot[' . $w . '][class]', $slots['weapons'][$w]['class'], $w, 'Arme', 'ge-' . $w); ?>
                        <div class="bo-ge-weapon__att">
                            <?php foreach ($def['att'] as $att => $attLabel): ?>
                                <?php $field('slot[' . $w . '][' . $att . ']', $slots['weapons'][$w][$att], $att, $attLabel, 'ge-' . $w . '-' . $att); ?>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <?php endforeach; ?>
                </section>

                <section class="bo-ge-section">
                    <header class="bo-ge-section__head">
                        <div>
                            <h2 class="bo-ge-section__title">Tenue et protections</h2>
                        </div>
                    </header>
                    <div class="bo-ge-slots">
                        <?php foreach ($gearDefs as $s => $sLabel): ?>
                            <?php $field('slot[' . $s . ']', $slots['simple'][$s], RoleKitLoadout::SIMPLE[$s], $sLabel, 'ge-' . $s); ?>
                        <?php endforeach; ?>
                    </div>
                    <h3 class="bo-ge-subtitle">Objets assignés</h3>
                    <div class="bo-ge-slots">
                        <?php foreach ($assignedDefs as $s => $sLabel): ?>
                            <?php $field('slot[' . $s . ']', $slots['simple'][$s], RoleKitLoadout::SIMPLE[$s], $sLabel, 'ge-' . $s); ?>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="bo-ge-section">
                    <header class="bo-ge-section__head">
                        <div>
                            <h2 class="bo-ge-section__title">Contenu</h2>
                            <p class="bo-ge-section__lead">Chargeurs, grenades, médical et objets rangés dans la tenue, le gilet et le sac. Le contenu d’un conteneur vide est ignoré.</p>
                        </div>
                    </header>
                    <div class="bo-ge-cargo">
                        <?php foreach ($containerLabels as $container => $cLabel): ?>
                        <?php $lines = $slots['cargo'][$container] ?? []; ?>
                        <div class="bo-ge-cargo__box" data-ge-cargo="<?= $h($container) ?>" data-ge-next="<?= count($lines) + 1 ?>">
                            <h3 class="bo-ge-subtitle"><?= $h($cLabel) ?></h3>
                            <ul class="bo-ge-cargo__list" data-ge-cargo-list>
                                <?php foreach ($lines as $i => $line): ?>
                                    <?php $cargoRow($container, $i, $line['class'], $line['count']); ?>
                                <?php endforeach; ?>
                                <?php $cargoRow($container, count($lines), '', 1); ?>
                            </ul>
                            <button type="button" class="bo-ge-linkbtn" data-ge-cargo-add hidden>+ Ajouter un objet</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <aside class="bo-ge-form__side">
                <div class="bo-ge-summary" data-ge-summary>
                    <p class="bo-ge-summary__kicker">Résumé</p>
                    <p class="bo-ge-summary__count"><strong data-ge-count><?= count($classes) ?></strong> objet(s) choisi(s)</p>
                    <p class="bo-ge-summary__label">Mods requis</p>
                    <div class="bo-ge-chips" data-ge-mods>
                        <?php if ($mods === []): ?>
                        <span class="bo-ge-chip bo-ge-chip--soft">Arma 3 seul</span>
                        <?php else: ?>
                            <?php foreach ($mods as $m): ?>
                            <span class="bo-ge-chip" translate="no"><?= $h($m) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <p class="bo-ge-summary__warn" data-ge-unknown hidden>Certains objets n’ont jamais été vus en jeu : vérifiez l’orthographe des classes.</p>
                    <div class="bo-ge-summary__actions">
                        <button type="submit" class="ath-btn ath-btn--solid"><?= $kit !== null ? 'Enregistrer et republier' : 'Créer le kit' ?></button>
                        <a class="ath-btn" href="<?= $h(url('back-office/kits-roles')) ?>">Annuler</a>
                    </div>
                    <p class="bo-ge-summary__hint">Le kit est publié dans la collection « Kits par rôle » de l’arsenal en jeu, visible de toute la communauté.</p>
                </div>
            </aside>
        </div>
    </form>

    <dialog class="bo-ge-picker" data-ge-picker aria-labelledby="ge-picker-title">
        <div class="bo-ge-picker__inner">
            <header class="bo-ge-picker__head">
                <h2 class="bo-ge-picker__title" id="ge-picker-title">Choisir un objet</h2>
                <button type="button" class="bo-ge-iconbtn" data-ge-picker-close aria-label="Fermer">×</button>
            </header>
            <div class="bo-ge-picker__filters">
                <input class="bo-ge-input" type="search" placeholder="Rechercher un nom, une classe, un mod" aria-label="Rechercher" data-ge-picker-q>
                <select class="bo-ge-input" aria-label="Famille" data-ge-picker-kind>
                    <option value="">Toutes les familles</option>
                    <?php foreach ($kindLabels as $k => $kl): ?>
                    <option value="<?= $h($k) ?>"><?= $h($kl) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="bo-ge-input" aria-label="Mod" data-ge-picker-mod>
                    <option value="">Tous les mods</option>
                </select>
            </div>
            <p class="bo-ge-picker__info" data-ge-picker-info></p>
            <ul class="bo-ge-picker__list" data-ge-picker-list role="listbox" aria-label="Objets"></ul>
            <footer class="bo-ge-picker__foot">
                <button type="button" class="ath-btn" data-ge-picker-clear>Vider l’emplacement</button>
                <button type="button" class="ath-btn" data-ge-picker-close>Fermer</button>
            </footer>
        </div>
    </dialog>
    <script type="application/json" data-ge-catalogue><?= $catalogueJson ?></script>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-game-equipment.js')) ?>" defer></script>
