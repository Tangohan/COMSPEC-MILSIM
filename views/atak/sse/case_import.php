<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var string $exampleJson */
$exampleJson = (string) ($exampleJson ?? '{}');
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">Dossiers</a> /
    <strong>Importer un scénario</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Préparation // Scénarios</div>
        <h1>Importer un scénario complet</h1>
        <p>
            Chargez un pack préparé (ChatGPT, Claude ou export Athena) pour créer d’un coup
            le dossier, les identités, les notes, les pièces et les sites. Réservé à la gestion.
        </p>
    </div>
    <div class="page-reference">
        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/dossiers')) ?>">Retour aux dossiers</a>
    </div>
</div>

<div class="security-notice">
    <div class="security-notice-code">SEC-PREP</div>
    <div>
        <strong>Fiction / entraînement uniquement</strong>
        <span>
            N’importez que des contenus fictifs destinés au milsim. Aucune donnée personnelle réelle.
            Après import, vous pourrez emporter le dossier vers Arma depuis la fiche du dossier.
        </span>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01</span> Charger le pack</div>
        <div class="panel-meta">Fichier ou contenu collé</div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('atak/sse/dossiers/importer')) ?>" enctype="multipart/form-data" class="lab-form">
            <?= \App\Core\Csrf::field() ?>
            <div class="lab-form-grid">
                <div class="lab-form-field lab-form-field--span2">
                    <label for="bundle_file">Fichier de scénario</label>
                    <input id="bundle_file" name="bundle_file" type="file" accept=".json,application/json,text/plain">
                    <p class="lab-form-help">Choisissez le fichier produit par l’assistant ou un emport Athena précédent.</p>
                </div>
                <div class="lab-form-field lab-form-field--span2">
                    <label for="bundle_text">Ou coller le contenu du pack</label>
                    <textarea id="bundle_text" name="bundle_text" rows="14" spellcheck="false" placeholder="Collez ici le contenu du pack…"></textarea>
                </div>
            </div>
            <div style="margin-top:16px;display:flex;flex-wrap:wrap;gap:8px">
                <button type="submit" class="btn">Créer le dossier depuis le pack</button>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/guide')) ?>">Aide opérateur</a>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">02</span> Modèle &amp; prompts</div>
        <div class="panel-meta">Pour générer un pack avec une IA</div>
    </div>
    <div class="panel-body">
        <p class="lab-form-lead">
            Utilisez les prompts documentés pour ChatGPT ou Claude, puis importez le résultat ici.
            Documentation : <code>docs/sse/prompts-dossiers-fictifs-json.md</code> (dépôt) — aussi résumée dans le guide SSE.
        </p>
        <details>
            <summary>Aperçu du modèle attendu (exemple)</summary>
            <pre style="margin-top:12px;max-height:320px;overflow:auto;padding:12px;border:1px solid var(--border,#333);font-size:12px;line-height:1.45;white-space:pre-wrap"><?= $h($exampleJson) ?></pre>
        </details>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
