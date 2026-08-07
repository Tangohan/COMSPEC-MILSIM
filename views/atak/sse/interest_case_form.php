<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,string> $confidenceLevels */
/** @var array<string,string> $interestLevels */
$confidenceLevels = is_array($confidenceLevels ?? null) ? $confidenceLevels : [];
$interestLevels = is_array($interestLevels ?? null) ? $interestLevels : [];
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/interet')) ?>">Dossiers d’intérêt</a> /
    <strong>Ouverture</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Acquisition // Levée de doute</div>
        <h1>Ouvrir un dossier d’intérêt</h1>
        <p>
            Le statut initial sera « Signalement reçu ». Aucune personne identifiée n’est créée :
            vous ouvrez une hypothèse de travail à instruire.
        </p>
    </div>
    <div class="page-reference">
        <strong>Nouveau // SSE-DI</strong>
        Référence attribuée automatiquement
    </div>
</div>

<form method="post" action="<?= $h(url('atak/sse/interet')) ?>" class="interest-form">
    <?= \App\Core\Csrf::field() ?>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">01</span> Hypothèse initiale</div>
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
            <label for="origin_operator">Unité ou opérateur à l’origine</label>
            <input id="origin_operator" name="origin_operator" maxlength="120">
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">02</span> Éléments observés</div>
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

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">03</span> Analyse</div>
        </div>
        <div class="panel-body">
            <div class="grid-2">
                <div>
                    <label for="analysis_facts">Faits établis</label>
                    <textarea id="analysis_facts" name="analysis_facts" rows="4"></textarea>
                </div>
                <div>
                    <label for="analysis_assumptions">Hypothèses (une par ligne)</label>
                    <textarea id="analysis_assumptions" name="analysis_assumptions" rows="4" placeholder="H1…&#10;H2…"></textarea>
                </div>
                <div>
                    <label for="analysis_contradictions">Contradictions</label>
                    <textarea id="analysis_contradictions" name="analysis_contradictions" rows="4"></textarea>
                </div>
                <div>
                    <label for="analysis_questions">Questions restantes</label>
                    <textarea id="analysis_questions" name="analysis_questions" rows="4"></textarea>
                </div>
                <div>
                    <label for="collection_needs">Besoins de collecte</label>
                    <textarea id="collection_needs" name="collection_needs" rows="4"></textarea>
                </div>
                <div>
                    <label for="operational_risk">Risque opérationnel</label>
                    <textarea id="operational_risk" name="operational_risk" rows="4"></textarea>
                </div>
            </div>
            <label for="recommendations">Recommandations de contrôle</label>
            <textarea id="recommendations" name="recommendations" rows="3"></textarea>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">04</span> Traçabilité</div>
        </div>
        <div class="panel-body">
            <div class="grid-2">
                <div>
                    <label for="source_label">Source</label>
                    <input id="source_label" name="source_label" maxlength="120">
                </div>
                <div>
                    <label for="source_reliability">Fiabilité de la source</label>
                    <select id="source_reliability" name="source_reliability">
                        <option value="">Non évaluée</option>
                        <option>A — fiable</option>
                        <option>B — généralement fiable</option>
                        <option>C — assez fiable</option>
                        <option>D — peu fiable</option>
                        <option>E — non fiable</option>
                    </select>
                </div>
            </div>
            <div class="toolbar-actions" style="margin-top:1rem">
                <button class="btn" type="submit">Enregistrer le signalement</button>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/interet')) ?>">Annuler</a>
            </div>
        </div>
    </section>
</form>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
