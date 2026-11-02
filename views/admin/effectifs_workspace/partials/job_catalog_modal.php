<?php
declare(strict_types=1);
// Fenêtre « Chercher dans le catalogue » : cadre et libellés fixes ici (traduits avec la page), contenu du catalogue
// rempli par assets/js/job-catalog.js depuis fonctions/catalogue (tous pays, langue du lecteur, marqué translate="no").
// Les libellés data-job-catalog-label servent aux puces des filtres actifs construites par le script.
$catalogUrl = effectifs_workspace_url('fonctions/catalogue');
?>
<dialog class="jobcat" id="job-catalog-dialog" aria-labelledby="job-catalog-title" aria-describedby="job-catalog-lead" data-job-catalog data-catalog-url="<?= $h($catalogUrl) ?>">
<div class="jobcat__frame">
    <header class="jobcat__head">
        <div>
            <p class="jobcat__kicker">Catalogue d’emplois de référence</p>
            <h2 id="job-catalog-title">Chercher dans le catalogue</h2>
            <p class="jobcat__lead" id="job-catalog-lead">Trouvez un emploi existant, puis copiez tout ou seulement un champ dans votre fiche. Rien n’est enregistré tant que vous n’avez pas validé l’emploi.</p>
        </div>
        <button type="button" class="jobcat__close" data-job-catalog-close aria-label="Fermer le catalogue">×</button>
    </header>
    <div class="jobcat__countries" role="radiogroup" aria-label="Pays du catalogue" data-job-catalog-countries>
        <button type="button" role="radio" aria-checked="true" tabindex="0" class="jobcat__country" data-job-catalog-country=""><span class="jobcat__flag is-all" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.6 3.9 5.6 3.9 9s-1.3 6.4-3.9 9c-2.6-2.6-3.9-5.6-3.9-9s1.3-6.4 3.9-9z"/></svg></span><span class="jobcat__country-name">Tous les pays</span><span class="jobcat__country-count" data-job-catalog-country-count></span></button>
    </div>
    <div class="jobcat__toolbar" role="search">
        <label class="jobcat__search"><span class="eff-sr-only">Rechercher un emploi du catalogue</span><input type="search" data-job-catalog-search placeholder="Nom, abrégé, code, unité, brevet (18B, CPA 10, nageur de combat…)" autocomplete="off" aria-controls="job-catalog-results"></label>
        <button type="button" class="jobcat__filters-toggle" data-job-catalog-filters-toggle aria-expanded="false" aria-controls="job-catalog-filters">Filtres et tri <span class="jobcat__badge" data-job-catalog-filter-badge hidden></span></button>
        <div class="jobcat__filters" id="job-catalog-filters">
            <label><span data-job-catalog-label="branch">Armée ou service</span><select data-job-catalog-filter="branch"><option value="">Toutes</option></select></label>
            <label><span data-job-catalog-label="command">Commandement</span><select data-job-catalog-filter="command"><option value="">Tous</option></select></label>
            <label><span data-job-catalog-label="formation">Unité</span><select data-job-catalog-filter="formation"><option value="">Toutes</option></select></label>
            <label><span data-job-catalog-label="type">Type</span><select data-job-catalog-filter="type"><option value="">Tous</option></select></label>
            <label><span>Trier par</span><select data-job-catalog-sort><option value="relevance">Pertinence</option><option value="name">Nom</option><option value="unit">Unité</option></select></label>
            <div class="jobcat__check"><input type="checkbox" id="job-catalog-group" data-job-catalog-group checked><label for="job-catalog-group">Regrouper par pays et armée</label></div>
        </div>
    </div>
    <div class="jobcat__status">
        <p class="jobcat__count" data-job-catalog-count role="status" aria-live="polite">Chargement du catalogue…</p>
        <div class="jobcat__active" data-job-catalog-active hidden>
            <span class="eff-sr-only">Filtres actifs</span>
            <span class="jobcat__active-list" data-job-catalog-chips></span>
            <button type="button" class="jobcat__reset" data-job-catalog-reset>Réinitialiser les filtres</button>
        </div>
        <span hidden data-job-catalog-label="name">Nom</span><span hidden data-job-catalog-label="lang-fr">Français</span><span hidden data-job-catalog-label="lang-en">Anglais</span><span hidden data-job-catalog-label="lang-de">Allemand</span><span hidden data-job-catalog-label="official">nom officiel</span><span hidden data-job-catalog-label="copy">Copier</span><span hidden data-job-catalog-label="copy-name">Copier le nom</span><span hidden data-job-catalog-label="country">Pays</span><span hidden data-job-catalog-label="q">Recherche</span><span hidden data-job-catalog-label="remove">Retirer le filtre</span>
        <span hidden data-job-catalog-label="lang-ru">Russe</span><span hidden data-job-catalog-label="lang-ru-Latn">Russe en alphabet latin</span>
    </div>
    <div class="jobcat__body">
        <div class="jobcat__results" id="job-catalog-results" data-job-catalog-results aria-label="Emplois du catalogue" translate="no"></div>
        <section class="jobcat__detail" data-job-catalog-detail aria-label="Fiche de l’emploi du catalogue" tabindex="-1" hidden>
            <button type="button" class="jobcat__back" data-job-catalog-back>← Retour à la liste</button>
            <div class="jobcat__detail-head">
                <span class="jobcat__icon" data-jc-icon aria-hidden="true"></span>
                <div>
                    <p class="jobcat__origin" translate="no"><span class="jobcat__flag" data-jc-flag aria-hidden="true"></span><span data-jc="country_label"></span><span data-jc="branch_label"></span></p>
                    <h3 data-jc="official_name" translate="no"></h3>
                    <p class="jobcat__official" translate="no" data-jc-translations></p>
                </div>
            </div>
            <p class="jobcat__chips" translate="no"><span data-jc="abbreviation" class="jobcat__chip is-abbr"></span><span data-jc="code" class="jobcat__chip"></span><span data-jc="command" class="jobcat__chip"></span><span data-jc="formation" class="jobcat__chip"></span><span data-jc="type_label" class="jobcat__chip is-type"></span></p>
            <div class="jobcat__actions"><button type="button" class="eff-btn eff-btn--primary" data-job-catalog-copy="all">Copier tout dans l’emploi</button><div class="jobcat__name-lang"><span id="job-catalog-name-lang">Nom copié</span><select data-job-catalog-name-lang aria-labelledby="job-catalog-name-lang" translate="no"></select></div><small>Nom, abrégé, description, icône, étiquettes et code. Vous confirmez avant de remplacer un champ déjà rempli.</small></div>
            <dl class="jobcat__fields">
                <div data-jc-row="names" class="is-wide"><dt>Noms</dt><dd translate="no"><ul class="jobcat__names" data-jc-names></ul></dd><dd></dd></div>
                <div data-jc-row="abbreviation"><dt>Abrégé</dt><dd translate="no" data-jc="abbreviation"></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="abbreviation">Copier l’abrégé</button></dd></div>
                <div data-jc-row="code"><dt>Code de spécialité</dt><dd translate="no"><span data-jc="code"></span> <small data-jc="code_system_label"></small></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="code">Copier le code</button></dd></div>
                <div data-jc-row="unit"><dt>Unité et commandement</dt><dd translate="no"><span data-jc="unit"></span></dd><dd></dd></div>
                <div data-jc-row="description" class="is-wide"><dt>Description</dt><dd translate="no" data-jc="description"></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="description">Copier la description</button></dd></div>
                <div data-jc-row="qualifications" class="is-wide is-card"><dt>Brevets, qualifications et formations typiques</dt><dd translate="no"><ul class="jobcat__list" data-jc-list="qualifications"></ul></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="qualifications">Ajouter à la description</button></dd></div>
                <div data-jc-row="billets" class="is-wide is-card"><dt>Postes typiques dans l’équipe</dt><dd translate="no"><ul class="jobcat__list" data-jc-list="billets"></ul></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="billets">Ajouter à la description</button></dd></div>
                <div data-jc-row="icon"><dt>Icône proposée</dt><dd><span data-jc-icon-label></span></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="icon">Utiliser cette icône</button></dd></div>
                <div data-jc-row="tags" class="is-wide"><dt>Étiquettes suggérées</dt><dd translate="no"><span data-jc-list="tags" class="jobcat__tags"></span></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="tags">Ajouter les étiquettes</button></dd></div>
                <div data-jc-row="sources" class="is-wide"><dt>Sources publiques</dt><dd translate="no"><ol data-jc-list="sources" class="jobcat__sources"></ol></dd><dd></dd></div>
            </dl>
        </section>
        <p class="jobcat__placeholder" data-job-catalog-placeholder>Choisissez un emploi dans la liste pour voir sa fiche.</p>
    </div>
    <footer class="jobcat__foot">
        <details class="jobcat__notes" data-job-catalog-notes>
            <summary><span class="jobcat__notes-line" data-job-catalog-note-line translate="no"></span><span class="jobcat__notes-toggle"><span class="jobcat__notes-more" data-job-catalog-note-more>Voir les sources</span><span class="jobcat__notes-less">Masquer les sources</span></span></summary>
            <div class="jobcat__notes-panel"><ul class="jobcat__notes-list" data-job-catalog-note-list translate="no"></ul><p>Informations publiques et reformulées, sans contenu classifié. Un champ laissé vide signifie qu’aucune source officielle ne le précise.</p></div>
        </details>
        <span hidden data-job-catalog-label="sources">Sources</span><span hidden data-job-catalog-label="catalogs">catalogues</span><span hidden data-job-catalog-label="see-sources">Voir les sources</span><span hidden data-job-catalog-label="see-more">Voir plus</span>
    </footer>
    <p class="eff-sr-only" data-job-catalog-live role="status" aria-live="polite"></p>
</div>
</dialog>
