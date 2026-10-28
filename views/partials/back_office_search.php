<?php
declare(strict_types=1);
$boSearchApi = $boSearchApi ?? url('api/back-office/search');
$boSearchDocs = url('documentation');
?>
<dialog id="ath-bo-search" class="ath-bo-search" data-api-url="<?= htmlspecialchars((string) $boSearchApi, ENT_QUOTES, 'UTF-8') ?>" data-docs-url="<?= htmlspecialchars((string) $boSearchDocs, ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="ath-bo-search-title">
    <div class="ath-bo-search__head">
        <p id="ath-bo-search-title" class="ath-bo-search__kicker">Recherche du back-office</p>
        <button type="button" class="ath-bo-search__esc" data-ath-bo-search-close>Échap</button>
    </div>
    <label class="ath-bo-search__label" for="ath-bo-search-q">Rechercher une page, un membre, un document, un guide…</label>
    <input id="ath-bo-search-q" type="search" autocomplete="off" spellcheck="false" placeholder="Nom, indicatif, page, document, question…" role="combobox" aria-controls="ath-bo-search-results" aria-expanded="true">
    <p class="ath-bo-search__hint">Au moins 2 caractères pour le contenu. Les pages et les guides apparaissent tout de suite, même avec une faute de frappe. <kbd>↑</kbd> <kbd>↓</kbd> pour parcourir, <kbd>Entrée</kbd> pour ouvrir. Les résultats respectent vos droits.</p>
    <div id="ath-bo-search-results" class="ath-bo-search__results" role="listbox" aria-live="polite"></div>
</dialog>
