<?php
declare(strict_types=1);

/** Fenêtre « Rechercher dans les effectifs » (Ctrl+K, « / » ou bouton Rechercher). */
$searchPages = is_array($searchPages ?? null) ? $searchPages : [];
?>
<dialog class="eff-cmdk" id="eff-cmdk" aria-labelledby="eff-cmdk-title"
        data-endpoint="<?= htmlspecialchars(effectifs_workspace_url('recherche'), ENT_QUOTES, 'UTF-8') ?>"
        data-pages="<?= htmlspecialchars((string) json_encode($searchPages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>">
    <h2 class="sr-only" id="eff-cmdk-title">Rechercher dans les effectifs</h2>
    <div class="eff-cmdk__field">
        <svg viewBox="0 0 16 16" width="18" height="18" aria-hidden="true"><circle cx="7" cy="7" r="4.6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m10.4 10.4 3.6 3.6" stroke="currentColor" stroke-width="1.6"/></svg>
        <input type="search" id="eff-cmdk-q" autocomplete="off" spellcheck="false" maxlength="80"
               placeholder="Nom, indicatif, code unité (SOAR-B), numéro (0042-0137), emploi, rôle…"
               role="combobox" aria-expanded="true" aria-controls="eff-cmdk-list" aria-autocomplete="list"
               aria-describedby="eff-cmdk-help">
        <button type="button" class="eff-cmdk__esc" data-eff-cmdk-close>Échap</button>
    </div>
    <div class="eff-cmdk__list" id="eff-cmdk-list" role="listbox" aria-label="Résultats"></div>
    <p class="eff-cmdk__status" role="status" aria-live="polite" data-eff-cmdk-status></p>
    <footer class="eff-cmdk__foot" id="eff-cmdk-help">
        <span><kbd>↑</kbd><kbd>↓</kbd> parcourir</span>
        <span><kbd>Entrée</kbd> ouvrir</span>
        <span><kbd>Échap</kbd> fermer</span>
        <button type="button" class="eff-cmdk__all" data-ath-search-open data-eff-cmdk-close>Chercher dans tout le back-office</button>
    </footer>
</dialog>
