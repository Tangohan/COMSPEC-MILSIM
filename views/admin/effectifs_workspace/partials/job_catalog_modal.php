<?php
declare(strict_types=1);
// Fenêtre « Chercher dans le catalogue » : cadre et libellés fixes ici, contenu du catalogue rempli par
// assets/js/job-catalog.js depuis fonctions/catalogue (langue du lecteur, marqué translate="no").
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
    <div class="jobcat__toolbar" role="search">
        <label class="jobcat__search"><span class="eff-sr-only">Rechercher un emploi du catalogue</span><input type="search" data-job-catalog-search placeholder="Nom, abrégé, code (18B, 1Z1, 0372…), unité, étiquette…" autocomplete="off" aria-controls="job-catalog-results"></label>
        <label>Branche<select data-job-catalog-filter="branch"><option value="">Toutes</option></select></label>
        <label>Commandement<select data-job-catalog-filter="command"><option value="">Tous</option></select></label>
        <label>Type<select data-job-catalog-filter="type"><option value="">Tous</option></select></label>
    </div>
    <p class="jobcat__count" data-job-catalog-count role="status" aria-live="polite">Chargement du catalogue…</p>
    <div class="jobcat__body">
        <ul class="jobcat__results" id="job-catalog-results" data-job-catalog-results aria-label="Emplois du catalogue" translate="no"></ul>
        <section class="jobcat__detail" data-job-catalog-detail aria-label="Fiche de l’emploi du catalogue" tabindex="-1" hidden>
            <button type="button" class="jobcat__back" data-job-catalog-back>← Retour à la liste</button>
            <div class="jobcat__detail-head">
                <span class="jobcat__icon" data-jc-icon aria-hidden="true"></span>
                <div><h3 data-jc="name" translate="no"></h3><p class="jobcat__official" translate="no"><span data-jc="official_name"></span></p></div>
            </div>
            <p class="jobcat__chips" translate="no"><span data-jc="abbreviation" class="jobcat__chip is-abbr"></span><span data-jc="code" class="jobcat__chip"></span><span data-jc="branch_label" class="jobcat__chip"></span><span data-jc="command" class="jobcat__chip"></span><span data-jc="type_label" class="jobcat__chip"></span></p>
            <div class="jobcat__actions"><button type="button" class="eff-btn eff-btn--primary" data-job-catalog-copy="all">Copier tout dans l’emploi</button><small>Nom, abrégé, description, icône, étiquettes et code. Vous confirmez avant de remplacer un champ déjà rempli.</small></div>
            <dl class="jobcat__fields">
                <div data-jc-row="name"><dt>Nom</dt><dd translate="no" data-jc="name"></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="name">Copier le nom</button></dd></div>
                <div data-jc-row="abbreviation"><dt>Abrégé</dt><dd translate="no" data-jc="abbreviation"></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="abbreviation">Copier l’abrégé</button></dd></div>
                <div data-jc-row="code"><dt>Code de spécialité</dt><dd translate="no"><span data-jc="code"></span> <small data-jc="code_system_label"></small></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="code">Copier le code</button></dd></div>
                <div data-jc-row="unit"><dt>Unité et commandement</dt><dd translate="no"><span data-jc="unit"></span></dd><dd></dd></div>
                <div data-jc-row="description" class="is-wide"><dt>Description</dt><dd translate="no" data-jc="description"></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="description">Copier la description</button></dd></div>
                <div data-jc-row="qualifications" class="is-wide"><dt>Qualifications et formations typiques</dt><dd translate="no"><ul data-jc-list="qualifications"></ul></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="qualifications">Ajouter à la description</button></dd></div>
                <div data-jc-row="billets" class="is-wide"><dt>Postes typiques dans l’équipe</dt><dd translate="no"><ul data-jc-list="billets"></ul></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="billets">Ajouter à la description</button></dd></div>
                <div data-jc-row="icon"><dt>Icône proposée</dt><dd><span data-jc-icon-label></span></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="icon">Utiliser cette icône</button></dd></div>
                <div data-jc-row="tags" class="is-wide"><dt>Étiquettes suggérées</dt><dd translate="no"><span data-jc-list="tags" class="jobcat__tags"></span></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="tags">Ajouter les étiquettes</button></dd></div>
                <div data-jc-row="sources" class="is-wide"><dt>Sources publiques</dt><dd translate="no"><ul data-jc-list="sources" class="jobcat__sources"></ul></dd><dd></dd></div>
            </dl>
        </section>
        <p class="jobcat__placeholder" data-job-catalog-placeholder>Choisissez un emploi dans la liste pour voir sa fiche.</p>
    </div>
    <footer class="jobcat__foot"><p data-job-catalog-note translate="no"></p><p>Informations publiques et reformulées, sans contenu classifié. Un champ laissé vide signifie qu’aucune source officielle ne le précise.</p></footer>
    <p class="eff-sr-only" data-job-catalog-live role="status" aria-live="polite"></p>
</div>
</dialog>
