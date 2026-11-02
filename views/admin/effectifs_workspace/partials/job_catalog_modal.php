<?php
declare(strict_types=1);
// Fenêtre « Chercher dans le catalogue » : cadre et libellés fixes ici (traduits avec la page), contenu du catalogue
// rempli par assets/js/job-catalog.js depuis fonctions/catalogue (tous pays, langue du lecteur, marqué translate="no").
// Les libellés data-job-catalog-label servent aux textes construits par le script (puces, catalogues de communauté…).
// Catalogues de communauté : formulaires envoyés en JSON (fonctions/catalogue/collections|emplois), jeton CSRF de la page.
$catalogUrl = effectifs_workspace_url('fonctions/catalogue');
$jcIcons = \App\Support\JobRoleIconLibrary::all();
$jcIconOptions = static function () use ($jcIcons, $h): string {
    $html = '<option value="">Aucune</option>';
    foreach ($jcIcons as $key => $icon) {
        $html .= '<option value="' . $h($key) . '">' . $h($icon['label']) . '</option>';
    }

    return $html;
};
?>
<dialog class="jobcat" id="job-catalog-dialog" aria-labelledby="job-catalog-title" aria-describedby="job-catalog-lead" data-job-catalog data-catalog-url="<?= $h($catalogUrl) ?>" data-collection-url="<?= $h($catalogUrl . '/collections') ?>" data-entry-url="<?= $h($catalogUrl . '/emplois') ?>" data-csrf="<?= $h($csrf ?? '') ?>">
<div class="jobcat__frame">
    <header class="jobcat__head">
        <div>
            <p class="jobcat__kicker">Catalogue d’emplois de référence</p>
            <h2 id="job-catalog-title">Chercher dans le catalogue</h2>
            <p class="jobcat__lead" id="job-catalog-lead">Trouvez un emploi existant, puis copiez tout ou seulement un champ dans votre fiche. Rien n’est enregistré tant que vous n’avez pas validé l’emploi.</p>
        </div>
        <div class="jobcat__head-actions">
            <button type="button" class="jobcat__expand" data-job-catalog-expand aria-pressed="false" aria-keyshortcuts="Alt+Enter" title="Agrandir ou réduire la fenêtre (Alt + Entrée)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path class="jobcat__expand-on" d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/><path class="jobcat__expand-off" d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/></svg><span class="jobcat__expand-label">Agrandir</span><span class="jobcat__reduce-label">Réduire</span></button>
            <button type="button" class="jobcat__close" data-job-catalog-close aria-label="Fermer le catalogue">×</button>
        </div>
    </header>
    <div class="jobcat__countries" role="radiogroup" aria-label="Pays du catalogue" data-job-catalog-countries>
        <button type="button" role="radio" aria-checked="true" tabindex="0" class="jobcat__country" data-job-catalog-country=""><span class="jobcat__flag is-all" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.6 3.9 5.6 3.9 9s-1.3 6.4-3.9 9c-2.6-2.6-3.9-5.6-3.9-9s1.3-6.4 3.9-9z"/></svg></span><span class="jobcat__country-name">Tous les pays</span><span class="jobcat__country-count" data-job-catalog-country-count></span></button>
        <button type="button" class="jobcat__country jobcat__new" data-job-catalog-new-collection><span aria-hidden="true">+</span> Nouveau catalogue</button>
    </div>
    <div class="jobcat__banner" data-job-catalog-banner hidden>
        <img class="jobcat__banner-img" data-job-catalog-banner-img alt="" hidden>
        <div class="jobcat__banner-body">
            <p class="jobcat__banner-title" translate="no"><span class="jobcat__flag" data-job-catalog-banner-flag aria-hidden="true"></span><strong data-job-catalog-banner-name></strong><span class="jobcat__banner-shared" data-job-catalog-banner-shared hidden>Partagé avec les autres communautés</span></p>
            <p class="jobcat__banner-text" data-job-catalog-banner-text translate="no"></p>
            <p class="jobcat__banner-author" data-job-catalog-banner-author hidden>Créé par la communauté <strong translate="no" data-job-catalog-banner-author-name></strong> · lecture seule</p>
        </div>
        <div class="jobcat__banner-actions" data-job-catalog-banner-actions hidden>
            <button type="button" class="eff-btn eff-btn--primary" data-job-catalog-new-entry>Ajouter un emploi</button>
            <button type="button" class="eff-btn" data-job-catalog-edit-collection>Modifier le catalogue</button>
        </div>
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
        <span hidden data-job-catalog-label="name">Nom</span><span hidden data-job-catalog-label="lang-fr">Français</span><span hidden data-job-catalog-label="lang-en">Anglais</span><span hidden data-job-catalog-label="lang-de">Allemand</span><span hidden data-job-catalog-label="lang-zh">Chinois</span><span hidden data-job-catalog-label="lang-pinyin">Pinyin (chinois en alphabet latin)</span><span hidden data-job-catalog-label="official">nom officiel</span><span hidden data-job-catalog-label="copy">Copier</span><span hidden data-job-catalog-label="copy-name">Copier le nom</span><span hidden data-job-catalog-label="country">Pays</span><span hidden data-job-catalog-label="q">Recherche</span><span hidden data-job-catalog-label="remove">Retirer le filtre</span>
    </div>
    <div class="jobcat__body">
        <div class="jobcat__results" id="job-catalog-results" data-job-catalog-results aria-label="Emplois du catalogue" translate="no"></div>
        <section class="jobcat__detail" data-job-catalog-detail aria-label="Fiche de l’emploi du catalogue" tabindex="-1" hidden>
            <button type="button" class="jobcat__back" data-job-catalog-back>← Retour à la liste</button>
            <div class="jobcat__detail-head">
                <img class="jobcat__image" data-jc-image alt="" hidden>
                <span class="jobcat__icon" data-jc-icon aria-hidden="true"></span>
                <div>
                    <p class="jobcat__origin" translate="no"><span class="jobcat__flag" data-jc-flag aria-hidden="true"></span><span data-jc="country_label"></span><span data-jc="branch_label"></span></p>
                    <h3 data-jc="official_name" translate="no"></h3>
                    <p class="jobcat__official" translate="no" data-jc-translations></p>
                    <p class="jobcat__author" data-jc-author hidden>Créé par la communauté <strong translate="no" data-jc-author-name></strong></p>
                </div>
            </div>
            <div class="jobcat__owner" data-jc-owner hidden><button type="button" class="eff-btn" data-job-catalog-edit-entry>Modifier l’emploi</button><button type="button" class="eff-btn eff-btn--danger" data-job-catalog-delete-entry>Retirer du catalogue</button><small>L’image reste dans le catalogue : les emplois de la communauté n’ont pas de champ image.</small></div>
            <p class="jobcat__chips" translate="no"><span data-jc="abbreviation" class="jobcat__chip is-abbr"></span><span data-jc="code" class="jobcat__chip"></span><span data-jc="command" class="jobcat__chip"></span><span data-jc="formation" class="jobcat__chip"></span><span data-jc="type_label" class="jobcat__chip is-type"></span></p>
            <div class="jobcat__actions"><button type="button" class="eff-btn eff-btn--primary" data-job-catalog-copy="all">Copier tout dans l’emploi</button><div class="jobcat__name-lang"><span id="job-catalog-name-lang">Nom copié</span><select data-job-catalog-name-lang aria-labelledby="job-catalog-name-lang" translate="no"></select></div><label class="jobcat__link-quals" data-jc-link-quals hidden><input type="checkbox" data-job-catalog-link-quals> Importer les qualifications liées et les ajouter comme prérequis de l’emploi</label><small>Nom, abrégé, description, icône, étiquettes et code. Vous confirmez avant de remplacer un champ déjà rempli.</small></div>
            <dl class="jobcat__fields">
                <div data-jc-row="names" class="is-wide"><dt>Noms</dt><dd translate="no"><ul class="jobcat__names" data-jc-names></ul></dd><dd></dd></div>
                <div data-jc-row="abbreviation"><dt>Abrégé</dt><dd translate="no" data-jc="abbreviation"></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="abbreviation">Copier l’abrégé</button></dd></div>
                <div data-jc-row="code"><dt>Code de spécialité</dt><dd translate="no"><span data-jc="code"></span> <small data-jc="code_system_label"></small></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="code">Copier le code</button></dd></div>
                <div data-jc-row="unit"><dt>Unité et commandement</dt><dd translate="no"><span data-jc="unit"></span></dd><dd></dd></div>
                <div data-jc-row="description" class="is-wide"><dt>Description</dt><dd translate="no" data-jc="description"></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="description">Copier la description</button></dd></div>
                <div data-jc-row="qualifications" class="is-wide is-card"><dt>Brevets, qualifications et formations typiques</dt><dd translate="no"><ul class="jobcat__list" data-jc-list="qualifications"></ul></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="qualifications">Ajouter à la description</button></dd></div>
                <div data-jc-row="linked_qualifications" class="is-wide is-card"><dt>Qualifications liées (catalogue)</dt><dd translate="no"><ul class="jobcat__list jobcat__quals" data-jc-quals></ul></dd><dd><small>Qualifications de jeu inspirées des brevets réels, sans valeur réelle. Importables depuis la page Qualifications ou avec « Copier tout ».</small></dd></div>
                <div data-jc-row="billets" class="is-wide is-card"><dt>Postes typiques dans l’équipe</dt><dd translate="no"><ul class="jobcat__list" data-jc-list="billets"></ul></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="billets">Ajouter à la description</button></dd></div>
                <div data-jc-row="icon"><dt>Icône proposée</dt><dd><span data-jc-icon-label></span></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="icon">Utiliser cette icône</button></dd></div>
                <div data-jc-row="tags" class="is-wide"><dt>Étiquettes suggérées</dt><dd translate="no"><span data-jc-list="tags" class="jobcat__tags"></span></dd><dd><button type="button" class="jobcat__copy" data-job-catalog-copy="tags">Ajouter les étiquettes</button></dd></div>
                <div data-jc-row="sources" class="is-wide"><dt>Sources publiques</dt><dd translate="no"><ol data-jc-list="sources" class="jobcat__sources"></ol></dd><dd></dd></div>
            </dl>
        </section>
        <p class="jobcat__placeholder" data-job-catalog-placeholder>Choisissez un emploi dans la liste pour voir sa fiche.</p>
        <section class="jobcat__manage" data-job-catalog-manage aria-labelledby="job-catalog-manage-title" tabindex="-1" hidden>
            <button type="button" class="jobcat__back" data-job-catalog-manage-cancel>← Retour à la liste</button>
            <form class="jobcat__form" data-job-catalog-collection-form enctype="multipart/form-data" novalidate hidden>
                <h3 id="job-catalog-manage-title" data-job-catalog-manage-title>Nouveau catalogue</h3>
                <p class="jobcat__form-lead">Un catalogue rassemble les emplois d’un univers ou d’une unité (SCP Foundation, Warhammer 40k, unité fictive, PMC…). Il s’affiche à côté des pays, pour votre communauté seulement, sauf si vous le partagez.</p>
                <input type="hidden" name="collection_id" value="0">
                <label>Nom du catalogue<input name="name" maxlength="80" required autocomplete="off" translate="no"></label>
                <label>Description<textarea name="description" rows="3" maxlength="1000" translate="no"></textarea></label>
                <div class="jobcat__form-row">
                    <label>Icône<select name="icon"><?= $jcIconOptions() ?></select></label>
                    <label>Couleur<input type="color" name="color" value="#1f6f5c"></label>
                </div>
                <div class="jobcat__form-row">
                    <label>Emblème (JPG, PNG ou WebP, 8 Mo)<input type="file" name="emblem" accept="image/jpeg,image/png,image/webp"></label>
                    <label class="jobcat__check-line"><input type="checkbox" name="remove_emblem" value="1"> Retirer l’emblème</label>
                </div>
                <div class="jobcat__form-row">
                    <label>Bannière (JPG, PNG ou WebP, 8 Mo)<input type="file" name="banner" accept="image/jpeg,image/png,image/webp"></label>
                    <label class="jobcat__check-line"><input type="checkbox" name="remove_banner" value="1"> Retirer la bannière</label>
                </div>
                <label class="jobcat__check-line"><input type="checkbox" name="is_public" value="1"> Partager publiquement : les autres communautés le voient en lecture seule, avec le nom de votre communauté</label>
                <p class="jobcat__form-error" data-job-catalog-form-error role="alert" hidden></p>
                <div class="jobcat__form-actions"><button type="submit" class="eff-btn eff-btn--primary">Enregistrer le catalogue</button><button type="button" class="eff-btn" data-job-catalog-manage-cancel>Annuler</button><button type="button" class="eff-btn eff-btn--danger" data-job-catalog-delete-collection hidden>Supprimer le catalogue</button></div>
            </form>
            <form class="jobcat__form" data-job-catalog-entry-form enctype="multipart/form-data" novalidate hidden>
                <h3 data-job-catalog-entry-title>Nouvel emploi</h3>
                <p class="jobcat__form-lead">Emploi du catalogue <strong translate="no" data-job-catalog-entry-collection></strong>. Mêmes champs que les catalogues officiels : il se cherche, se filtre et se copie de la même façon.</p>
                <input type="hidden" name="collection_id" value="0"><input type="hidden" name="entry_id" value="0">
                <label>Nom de l’emploi<input name="name" maxlength="120" required autocomplete="off" translate="no"></label>
                <div class="jobcat__form-row">
                    <label>Abrégé (2 à 8 lettres ou chiffres)<input name="abbreviation" maxlength="12" autocomplete="off" translate="no"></label>
                    <label>Type<select name="type"><option value="operator">Opérateur</option><option value="command">Commandement et encadrement</option><option value="support">Soutien</option><option value="aviation">Aviation</option></select></label>
                </div>
                <div class="jobcat__form-row">
                    <label>Catégorie (armée, faction, département…)<input name="category" maxlength="80" autocomplete="off" translate="no"></label>
                    <label>Unité<input name="unit" maxlength="120" autocomplete="off" translate="no"></label>
                </div>
                <label>Description<textarea name="description" rows="4" maxlength="1500" translate="no"></textarea></label>
                <label>Qualifications et brevets typiques (un par ligne)<textarea name="qualifications" rows="3" translate="no"></textarea></label>
                <div class="jobcat__form-row">
                    <label>Étiquettes (séparées par des virgules)<input name="tags" maxlength="400" autocomplete="off" translate="no"></label>
                    <label>Icône<select name="icon"><?= $jcIconOptions() ?></select></label>
                </div>
                <div class="jobcat__form-row">
                    <label>Image (JPG, PNG ou WebP, 8 Mo)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
                    <label class="jobcat__check-line"><input type="checkbox" name="remove_image" value="1"> Retirer l’image</label>
                </div>
                <p class="jobcat__form-error" data-job-catalog-form-error role="alert" hidden></p>
                <div class="jobcat__form-actions"><button type="submit" class="eff-btn eff-btn--primary">Enregistrer l’emploi</button><button type="button" class="eff-btn" data-job-catalog-manage-cancel>Annuler</button></div>
            </form>
        </section>
    </div>
    <footer class="jobcat__foot">
        <details class="jobcat__notes" data-job-catalog-notes>
            <summary><span class="jobcat__notes-line" data-job-catalog-note-line translate="no"></span><span class="jobcat__notes-toggle"><span class="jobcat__notes-more" data-job-catalog-note-more>Voir les sources</span><span class="jobcat__notes-less">Masquer les sources</span></span></summary>
            <div class="jobcat__notes-panel"><ul class="jobcat__notes-list" data-job-catalog-note-list translate="no"></ul><p>Informations publiques et reformulées, sans contenu classifié. Un champ laissé vide signifie qu’aucune source officielle ne le précise.</p></div>
        </details>
        <span hidden data-job-catalog-label="sources">Sources</span><span hidden data-job-catalog-label="catalogs">catalogues</span><span hidden data-job-catalog-label="see-sources">Voir les sources</span><span hidden data-job-catalog-label="see-more">Voir plus</span>
    </footer>
    <p class="eff-sr-only" data-job-catalog-live role="status" aria-live="polite"></p>
    <div hidden>
        <span data-job-catalog-label="new-collection">Nouveau catalogue</span><span data-job-catalog-label="edit-collection">Modifier le catalogue</span>
        <span data-job-catalog-label="new-entry">Nouvel emploi</span><span data-job-catalog-label="edit-entry">Modifier l’emploi</span>
        <span data-job-catalog-label="confirm-delete-collection">Supprimer ce catalogue et tous ses emplois ? Les emplois déjà copiés dans votre communauté ne changent pas.</span>
        <span data-job-catalog-label="confirm-delete-entry">Retirer cet emploi du catalogue ?</span>
        <span data-job-catalog-label="saving">Enregistrement…</span><span data-job-catalog-label="save-failed">L’enregistrement a échoué. Réessayez.</span>
        <span data-job-catalog-label="shared-by">Partagé par la communauté</span>
        <span data-job-catalog-label="replace-field">Remplacer le champ déjà rempli :</span>
        <span data-job-catalog-label="copied">Emploi du catalogue copié. Vérifiez la fiche puis enregistrez.</span>
    </div>
</div>
<dialog class="jobcat-confirm" data-job-catalog-confirm aria-labelledby="job-catalog-confirm-title" aria-describedby="job-catalog-confirm-lead">
    <form method="dialog" class="jobcat-confirm__frame">
        <h2 id="job-catalog-confirm-title">Copier cet emploi dans votre fiche ?</h2>
        <p id="job-catalog-confirm-lead" class="jobcat-confirm__lead">Les champs suivants seront copiés. <span class="jobcat-confirm__replaced-hint">Surlignés : déjà remplis, ils seront remplacés.</span></p>
        <dl class="jobcat-confirm__fields" data-job-catalog-confirm-fields translate="no"></dl>
        <p class="jobcat-confirm__author" data-job-catalog-confirm-author hidden>Créé par la communauté <strong translate="no" data-job-catalog-confirm-author-name></strong>.</p>
        <ul class="jobcat-confirm__notes">
            <li>Rien n’est enregistré tant que vous n’avez pas validé l’emploi.</li>
            <li data-job-catalog-confirm-reference>Données de référence issues de sources publiques, à titre indicatif ; vérifiez et adaptez à votre unité.</li>
        </ul>
        <div class="jobcat-confirm__skip"><input type="checkbox" id="job-catalog-confirm-skip" data-job-catalog-confirm-skip><label for="job-catalog-confirm-skip">Ne plus afficher</label></div>
        <div class="jobcat-confirm__actions"><button type="submit" value="copy" class="eff-btn eff-btn--primary" data-job-catalog-confirm-ok>Copier</button><button type="submit" value="cancel" class="eff-btn">Annuler</button></div>
        <div hidden><span data-job-catalog-confirm-label="name">Nom</span><span data-job-catalog-confirm-label="abbreviation">Abrégé</span><span data-job-catalog-confirm-label="description">Description</span><span data-job-catalog-confirm-label="icon">Icône</span><span data-job-catalog-confirm-label="tags">Étiquettes</span><span data-job-catalog-confirm-label="code">Code de spécialité</span><span data-job-catalog-confirm-label="replaced">remplacé</span></div>
    </form>
</dialog>
</dialog>
