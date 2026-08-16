<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,string> $confidenceLevels */
/** @var array<string,string> $interestLevels */
$confidenceLevels = is_array($confidenceLevels ?? null) ? $confidenceLevels : [];
$interestLevels = is_array($interestLevels ?? null) ? $interestLevels : [];
$signerLabel = trim((string) ($signerLabel ?? (\App\Core\Session::get('sse_guest_label') ?? \App\Core\Session::get('display_name') ?? 'Analyste')));
if ($signerLabel === '') {
    $signerLabel = 'Analyste';
}
$reliabilityOptions = [
    '' => 'Non évaluée',
    'A — fiable' => 'A — fiable',
    'B — généralement fiable' => 'B — généralement fiable',
    'C — assez fiable' => 'C — assez fiable',
    'D — peu fiable' => 'D — peu fiable',
    'E — non fiable' => 'E — non fiable',
];
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/interet')) ?>">Dossiers d’intérêt</a> /
    <strong>Ouverture</strong>
</div>

<section class="sse-desk-hero sse-desk-hero--compact" aria-labelledby="sse-di-form-title">
    <div class="sse-desk-hero__main">
        <div class="sse-desk-hero__kicker">
            <span class="interest-hero__ref">ATH-SSE-DI</span>
            <span class="badge badge--amber">Signalement</span>
            <span class="badge badge--gray">Validation humaine</span>
        </div>
        <h1 id="sse-di-form-title">Ouvrir un dossier d’intérêt</h1>
        <p class="sse-desk-hero__lead">
            Le statut initial sera « Signalement reçu ». Aucune identité n’est créée :
            vous ouvrez une hypothèse de travail à instruire, puis vous la signez numériquement.
        </p>
    </div>
    <aside class="sse-desk-hero__side">
        <p class="interest-hero__side-label">Parcours</p>
        <ol class="interest-form-steps" aria-label="Étapes du signalement">
            <li><span>01</span> Hypothèse</li>
            <li><span>02</span> Observations</li>
            <li><span>03</span> Analyse</li>
            <li><span>04</span> Traçabilité &amp; signature</li>
        </ol>
    </aside>
</section>

<form method="post" action="<?= $h(url('atak/sse/interet')) ?>" class="interest-form" id="sse-di-form">
    <?= \App\Core\Csrf::field() ?>

    <section class="panel interest-form-panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">01</span> Hypothèse initiale</div>
            <div class="panel-meta">Qui est signalé, sans conclure</div>
        </div>
        <div class="panel-body">
            <div class="grid-2">
                <div>
                    <label for="temporary_designation">Désignation temporaire</label>
                    <input id="temporary_designation" name="temporary_designation" required maxlength="120" placeholder="Ex. Alpha-01">
                </div>
                <div>
                    <label for="suspected_alias">Alias supposé</label>
                    <input id="suspected_alias" name="suspected_alias" maxlength="120">
                </div>
                <div>
                    <label for="apparent_sex">Sexe apparent</label>
                    <input id="apparent_sex" name="apparent_sex" maxlength="40">
                </div>
                <div>
                    <label for="estimated_age_range">Tranche d’âge estimée</label>
                    <input id="estimated_age_range" name="estimated_age_range" maxlength="40" placeholder="Ex. 30–40 ans">
                </div>
                <div>
                    <label for="suspected_nationality">Nationalité supposée</label>
                    <input id="suspected_nationality" name="suspected_nationality" maxlength="80">
                </div>
                <div>
                    <label for="suspected_affiliation">Affiliation supposée</label>
                    <input id="suspected_affiliation" name="suspected_affiliation" maxlength="120">
                </div>
                <div>
                    <label for="confidence_level">Niveau de confiance initial</label>
                    <select id="confidence_level" name="confidence_level">
                        <?php foreach ($confidenceLevels as $k => $v): ?>
                            <option value="<?= $h($k) ?>"><?= $h($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="interest_level">Intérêt opérationnel</label>
                    <select id="interest_level" name="interest_level">
                        <?php foreach ($interestLevels as $k => $v): ?>
                            <option value="<?= $h($k) ?>"><?= $h($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label for="opening_reason">Motif d’ouverture</label>
            <textarea id="opening_reason" name="opening_reason" required rows="4" placeholder="Pourquoi ce signalement mérite une instruction ?"></textarea>
            <label for="description">Description du dossier</label>
            <textarea id="description" name="description" rows="3" maxlength="8000"
                      placeholder="Synthèse opérationnelle : enjeux, périmètre, points de vigilance…"></textarea>
            <label for="origin_operator">Unité ou opérateur à l’origine</label>
            <input id="origin_operator" name="origin_operator" maxlength="120" value="<?= $h($signerLabel) ?>">
        </div>
    </section>

    <section class="panel interest-form-panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">02</span> Éléments observés</div>
            <div class="panel-meta">Ce qui a été vu ou saisi</div>
        </div>
        <div class="panel-body">
            <p class="sse-note">
                Décrire photographies, signes distinctifs, tenue, véhicule, lieux, accompagnants
                et tout élément utile — sans conclure à une identité.
            </p>
            <label for="observed_elements">Observations</label>
            <textarea id="observed_elements" name="observed_elements" rows="8"></textarea>
            <div class="grid-2">
                <div>
                    <label for="acquisition_at">Date et heure d’acquisition</label>
                    <input id="acquisition_at" type="datetime-local" name="acquisition_at">
                </div>
                <div>
                    <label for="mission_label">Mission d’origine</label>
                    <input id="mission_label" name="mission_label" maxlength="120">
                </div>
            </div>
        </div>
    </section>

    <section class="panel interest-form-panel interest-form-panel--analyse" id="analyse">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">03</span> Analyse</div>
            <div class="panel-meta">Faits → hypothèses → risques → suite à donner</div>
        </div>
        <div class="panel-body">
            <p class="interest-form-lead">
                Séparez clairement ce qui est établi, ce qui est interprété et ce qui reste à vérifier.
                Une ligne = une hypothèse ou une question.
            </p>

            <div class="interest-ana-grid" role="group" aria-label="Blocs d’analyse">
                <article class="interest-ana-card interest-ana-card--facts">
                    <header>
                        <span class="interest-ana-card__step">A</span>
                        <div>
                            <h3><label for="analysis_facts">Faits établis</label></h3>
                            <p>Éléments constatés, sans interprétation.</p>
                        </div>
                    </header>
                    <textarea id="analysis_facts" name="analysis_facts" rows="5" placeholder="Ce qui est acquis et vérifiable…"></textarea>
                </article>

                <article class="interest-ana-card interest-ana-card--hyp">
                    <header>
                        <span class="interest-ana-card__step">B</span>
                        <div>
                            <h3><label for="analysis_assumptions">Hypothèses</label></h3>
                            <p>Une hypothèse par ligne (H1, H2…).</p>
                        </div>
                    </header>
                    <textarea id="analysis_assumptions" name="analysis_assumptions" rows="5" placeholder="H1…&#10;H2…"></textarea>
                </article>

                <article class="interest-ana-card">
                    <header>
                        <span class="interest-ana-card__step">C</span>
                        <div>
                            <h3><label for="analysis_contradictions">Contradictions</label></h3>
                            <p>Éléments qui s’opposent ou fragilisent le récit.</p>
                        </div>
                    </header>
                    <textarea id="analysis_contradictions" name="analysis_contradictions" rows="5"></textarea>
                </article>

                <article class="interest-ana-card">
                    <header>
                        <span class="interest-ana-card__step">D</span>
                        <div>
                            <h3><label for="analysis_questions">Questions restantes</label></h3>
                            <p>Points encore ouverts pour l’instruction.</p>
                        </div>
                    </header>
                    <textarea id="analysis_questions" name="analysis_questions" rows="5"></textarea>
                </article>

                <article class="interest-ana-card">
                    <header>
                        <span class="interest-ana-card__step">E</span>
                        <div>
                            <h3><label for="collection_needs">Besoins de collecte</label></h3>
                            <p>Ce qu’il faut encore obtenir ou confirmer.</p>
                        </div>
                    </header>
                    <textarea id="collection_needs" name="collection_needs" rows="5"></textarea>
                </article>

                <article class="interest-ana-card interest-ana-card--risk">
                    <header>
                        <span class="interest-ana-card__step">F</span>
                        <div>
                            <h3><label for="operational_risk">Risque opérationnel</label></h3>
                            <p>Menaces, sensibilités, effets collatéraux.</p>
                        </div>
                    </header>
                    <textarea id="operational_risk" name="operational_risk" rows="5"></textarea>
                </article>

                <article class="interest-ana-card interest-ana-card--wide interest-ana-card--reco">
                    <header>
                        <span class="interest-ana-card__step">G</span>
                        <div>
                            <h3><label for="recommendations">Recommandations de contrôle</label></h3>
                            <p>Suite à donner proposée à la cellule.</p>
                        </div>
                    </header>
                    <textarea id="recommendations" name="recommendations" rows="4" placeholder="Contrôles, priorités, diffusion restreinte…"></textarea>
                </article>
            </div>
        </div>
    </section>

    <section class="panel interest-form-panel interest-form-panel--trace" id="tracabilite">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">04</span> Traçabilité</div>
            <div class="panel-meta">Provenance, fiabilité et signature numérique</div>
        </div>
        <div class="panel-body">
            <div class="interest-trace">
                <div class="interest-trace__meta">
                    <div>
                        <label for="source_label">Source</label>
                        <input id="source_label" name="source_label" maxlength="120" placeholder="Unité, capteur, renseignement humain…">
                        <p class="sse-desk-hint">Qui a fourni ou produit l’élément fondateur.</p>
                    </div>
                    <div>
                        <label for="source_reliability">Fiabilité de la source</label>
                        <select id="source_reliability" name="source_reliability">
                            <?php foreach ($reliabilityOptions as $k => $lab): ?>
                                <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="sse-desk-hint">Évaluation initiale — révisable à l’instruction.</p>
                    </div>
                </div>

                <aside class="interest-sign" aria-labelledby="sse-di-sign-title">
                    <p class="interest-sign__title" id="sse-di-sign-title">Signature</p>
                    <div class="interest-sign__box" id="sse-di-sign-box">Signature numérique</div>
                    <p class="interest-sign__name"><?= $h($signerLabel) ?></p>
                    <p class="interest-sign__meta" id="sse-di-sign-meta">En attente de confirmation</p>
                    <label class="interest-sign__confirm">
                        <input type="checkbox" name="digital_signature" value="1" id="sse-di-sign-check" required>
                        <span>Je confirme signer numériquement ce signalement sous mon identité de session.</span>
                    </label>
                    <input type="hidden" name="signed_by_label" value="<?= $h($signerLabel) ?>">
                </aside>
            </div>

            <div class="interest-form-actions">
                <button class="btn" type="submit">Enregistrer le signalement</button>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/interet')) ?>">Annuler</a>
            </div>
        </div>
    </section>
</form>
<script>
(function () {
    var check = document.getElementById('sse-di-sign-check');
    var box = document.getElementById('sse-di-sign-box');
    var meta = document.getElementById('sse-di-sign-meta');
    if (!check || !box || !meta) return;
    var sync = function () {
        if (check.checked) {
            box.classList.add('is-signed');
            box.textContent = 'Original signé';
            meta.textContent = 'Signature prête — sera horodatée à l’enregistrement';
        } else {
            box.classList.remove('is-signed');
            box.textContent = 'Signature numérique';
            meta.textContent = 'En attente de confirmation';
        }
    };
    check.addEventListener('change', sync);
    sync();
})();
</script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
