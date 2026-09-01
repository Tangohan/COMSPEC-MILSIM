<?php
declare(strict_types=1);
$boSearchApi = $boSearchApi ?? url('api/back-office/search');
?>
<dialog id="ath-bo-search" class="ath-bo-search" data-api-url="<?= htmlspecialchars((string) $boSearchApi, ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="ath-bo-search-title">
    <div class="ath-bo-search__head">
        <p id="ath-bo-search-title" class="ath-bo-search__kicker">Recherche du back-office</p>
        <button type="button" class="ath-bo-search__esc" data-ath-bo-search-close>Échap</button>
    </div>
    <label class="ath-bo-search__label" for="ath-bo-search-q">Rechercher une page, un membre, un document…</label>
    <input id="ath-bo-search-q" type="search" autocomplete="off" spellcheck="false" placeholder="Nom, indicatif, page, document…">
    <p class="ath-bo-search__hint">Au moins 2 caractères pour le contenu. Les pages apparaissent tout de suite. Les résultats respectent vos droits.</p>
    <div id="ath-bo-search-results" class="ath-bo-search__results" aria-live="polite"></div>
</dialog>
