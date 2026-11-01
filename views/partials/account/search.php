<?php
declare(strict_types=1);

/**
 * Recherche de l'espace compte (bandeau). Index rendu côté serveur — les libellés passent ainsi par la traduction
 * de la page — puis filtré dans le navigateur par public/assets/js/account-search.js.
 *
 * Variables : list<array> $accountSearchEntries (AccountSpaceCatalog::searchEntries()), callable $accountIcon.
 */
$accountSearchEntries = is_array($accountSearchEntries ?? null) ? $accountSearchEntries : [];
$hs = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$searchFold = static function (string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', '’' => ' ', "'" => ' ']);

    return trim((string) preg_replace('/\s+/', ' ', $s));
};
$searchGroups = [];
foreach ($accountSearchEntries as $entry) {
    $searchGroups[(string) $entry['group']][] = $entry;
}
?>
<div class="ah-search" data-account-search>
    <form class="ah-search__field" role="search" action="<?= $hs(url('account')) ?>" method="get" onsubmit="return false;">
        <label for="account-search-input" class="account-hub__sr">Rechercher dans mon compte</label>
        <svg class="ah-search__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/></svg>
        <input type="search" id="account-search-input" class="ah-search__input"
               placeholder="Rechercher un réglage : mot de passe, thème, Steam…"
               autocomplete="off" spellcheck="false" enterkeyhint="go"
               role="combobox" aria-expanded="false" aria-autocomplete="list"
               aria-controls="account-search-results" aria-describedby="account-search-help">
        <span class="ah-search__keys" aria-hidden="true"><kbd>/</kbd></span>
    </form>
    <div class="ah-search__panel" id="account-search-panel" hidden>
        <p class="ah-search__status" id="account-search-status" role="status" aria-live="polite"></p>
        <ul class="ah-search__list" id="account-search-results" role="listbox" aria-label="Pages et réglages du compte">
            <?php foreach ($searchGroups as $groupTitle => $entries): ?>
            <li class="ah-search__group" role="presentation" data-search-group="<?= $hs($groupTitle) ?>"><?= $hs($groupTitle) ?></li>
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $blob = $searchFold($entry['title'] . ' ' . $entry['page'] . ' ' . $entry['group'] . ' ' . $entry['desc'] . ' ' . $entry['keywords']);
                    ?>
            <li class="ah-search__item<?= $entry['kind'] === 'page' ? ' is-page' : '' ?>" role="option" aria-selected="false"
                id="ahs-<?= $hs($entry['id']) ?>"
                data-search-item
                data-kind="<?= $hs($entry['kind']) ?>"
                data-group="<?= $hs($groupTitle) ?>"
                data-title="<?= $hs($searchFold($entry['title'])) ?>"
                data-keywords="<?= $hs($searchFold($entry['keywords'])) ?>"
                data-blob="<?= $hs($blob) ?>"
                <?= $entry['kind'] === 'page' ? '' : 'hidden' ?>>
                <a href="<?= $hs($entry['href']) ?>" tabindex="-1">
                    <span class="ah-search__item-icon" aria-hidden="true"><?= isset($accountIcon) && is_callable($accountIcon) ? $accountIcon((string) $entry['icon']) : '' ?></span>
                    <span class="ah-search__item-text">
                        <span class="ah-search__item-title" data-search-title><?= $hs($entry['title']) ?></span>
                        <span class="ah-search__item-desc"><?= $hs($entry['desc']) ?></span>
                    </span>
                    <span class="ah-search__item-where"><?= $entry['kind'] === 'page' ? $hs($groupTitle) : $hs($entry['page']) ?></span>
                </a>
            </li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
        <div class="ah-search__empty" hidden>
            <p class="ah-search__empty-title">Aucun réglage ne correspond.</p>
            <p class="ah-search__empty-desc">Essayez un autre mot : « mot de passe », « thème », « Steam », « supprimer ».</p>
        </div>
        <p class="ah-search__help" id="account-search-help">
            <span><kbd>↑</kbd><kbd>↓</kbd> choisir</span>
            <span><kbd>↵</kbd> ouvrir</span>
            <span><kbd>Échap</kbd> fermer</span>
            <span class="ah-search__help-shortcut"><kbd>/</kbd> ou <kbd>Ctrl</kbd><kbd>K</kbd> depuis n’importe quelle page du compte</span>
        </p>
    </div>
</div>
