<?php
/**
 * Assistant d'importation du catalogue de référence (onglets Qualifications et Brevets du catalogue Academy) :
 * portée, aperçu, formule, import par lots (public/assets/js/academy-import-wizard.js, contrôleur
 * AcademyCatalogImportController). Affiché seulement à qui peut importer (mêmes droits que l'import unitaire).
 *
 * @var callable $h
 */
$aiwSteps = [['label' => 'Portée', 'active' => true], ['label' => 'Aperçu'], ['label' => 'Formule'], ['label' => 'Import']];
?>
<section class="aiw-launch" aria-labelledby="aiw-launch-title">
    <span class="aiw-launch__flags" aria-hidden="true"><?php foreach (array_keys(\App\Support\QualificationCatalog\QualificationCatalog::COUNTRIES) as $cc) { echo \App\Support\QualificationCatalog\CatalogCountry::flag($cc); } ?></span>
    <div>
        <h2 id="aiw-launch-title">Assistant d’importation</h2>
        <p>Importez dans votre référentiel ce qui est recommandé pour votre communauté, tout le catalogue, un pays, une catégorie, un domaine, un niveau ou un émetteur, avec un aperçu avant de valider.</p>
    </div>
    <button type="button" class="button small" data-aiw-open><span>Ouvrir l’assistant</span></button>
</section>

<dialog class="aiw" id="aiw" aria-labelledby="aiw-title" data-aiw
    data-url="<?= $h(url('academy/catalogue/assistant')) ?>"
    data-csrf="<?= $h(\App\Core\Csrf::token()) ?>"
    data-upgrade="<?= $h(\App\Services\Billing\QualificationCatalogQuota::upgradeUrl()) ?>">
    <header class="aiw__head">
        <div>
            <p class="aiw__kicker">Catalogue de référence</p>
            <h2 id="aiw-title">Assistant d’importation</h2>
        </div>
        <button type="button" class="aiw__close" data-aiw-close aria-label="Fermer l’assistant">×</button>
    </header>
    <div class="aiw__steps"><?php $steps = $aiwSteps; require base_path('views/partials/ui/stepper.php'); ?></div>

    <div class="aiw__body">
        <p class="aiw__error" role="alert" data-aiw-error hidden></p>

        <section data-aiw-step="1" aria-labelledby="aiw-s1">
            <h3 id="aiw-s1" class="aiw-sr">Portée</h3>
            <section class="aiw-reco" aria-labelledby="aiw-reco-title" data-aiw-reco>
                <h4 id="aiw-reco-title" class="aiw-reco__title">Recommandé pour votre communauté</h4>
                <p class="aiw-hint">D’après vos unités dans l’ORBAT et les fonctions de vos membres. Cochez un groupe pour l’ajouter à la sélection.</p>
                <div data-aiw-reco-body><p class="aiw-hint" role="status">Analyse de votre communauté…</p></div>
            </section>
            <div class="aiw-live" role="status" aria-live="polite" data-aiw-count>Chargement du catalogue…</div>
            <div class="aiw-scope">
                <button type="button" class="aiw-all" data-aiw-all aria-pressed="true"><b>Tout le catalogue</b> <span data-aiw-all-count></span></button>
                <label class="aiw-search"><span class="aiw-sr">Rechercher dans le catalogue</span>
                    <input type="search" data-aiw-q placeholder="Rechercher : JTAC, parachutiste, Ranger…" autocomplete="off" maxlength="120">
                </label>
            </div>
            <div class="aiw-dims" data-aiw-dims></div>
            <details class="aiw-manual" data-aiw-manual>
                <summary><b>Sélection à la main</b> <span data-aiw-manual-count></span></summary>
                <p class="aiw-hint">Cochez des éléments pour n’importer qu’eux. Sans case cochée, tous les éléments retenus ci-dessus sont importés.</p>
                <div class="aiw-manual__tools">
                    <button type="button" class="text-link as-button" data-aiw-check="all"><span>Tout cocher</span></button>
                    <button type="button" class="text-link as-button" data-aiw-check="none"><span>Tout décocher</span></button>
                </div>
                <ul class="aiw-list" data-aiw-list></ul>
            </details>
        </section>

        <section data-aiw-step="2" aria-labelledby="aiw-s2" hidden>
            <h3 id="aiw-s2" class="aiw-sr">Aperçu</h3>
            <div class="aiw-options">
                <label class="aiw-check"><input type="checkbox" data-aiw-deps checked> <span><b>Importer les dépendances</b> <small>Qualifications préalables citées par les prérequis et absentes de votre référentiel.</small></span></label>
                <fieldset class="aiw-radio">
                    <legend>Prérequis détectés</legend>
                    <label><input type="radio" name="aiw-prereqs" value="sure" checked> <span>Ajouter les prérequis sûrs</span></label>
                    <label><input type="radio" name="aiw-prereqs" value="none"> <span>Aucun</span></label>
                </fieldset>
            </div>
            <div class="aiw-live" role="status" aria-live="polite" data-aiw-preview-count></div>
            <div class="aiw-preview" data-aiw-preview></div>
        </section>

        <section data-aiw-step="3" aria-labelledby="aiw-s3" hidden>
            <h3 id="aiw-s3" class="aiw-sr">Formule</h3>
            <div data-aiw-quota></div>
        </section>

        <section data-aiw-step="4" aria-labelledby="aiw-s4" hidden>
            <h3 id="aiw-s4" class="aiw-sr">Import</h3>
            <div class="aiw-progress" data-aiw-progress>
                <p class="aiw-progress__label" role="status" aria-live="polite" data-aiw-progress-label>Préparation de l’import…</p>
                <div class="aiw-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Avancement de l’import"><i data-aiw-bar></i></div>
            </div>
            <div class="aiw-report" data-aiw-report></div>
        </section>
    </div>

    <footer class="aiw__foot">
        <button type="button" class="button small outline" data-aiw-back hidden><span>Retour</span></button>
        <span class="aiw__spacer"></span>
        <button type="button" class="button small" data-aiw-next disabled><span data-aiw-next-label>Suivant</span></button>
    </footer>
</dialog>
<script src="<?= $h(asset_url('assets/js/academy-import-wizard.js')) ?>" defer></script>
