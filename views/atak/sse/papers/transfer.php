<?php
declare(strict_types=1);

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<string> $columns */
/** @var list<array{context_id:?int,op_key:?string}> $scopes */
/** @var array{view:bool,manage:bool,truth:bool} $rights */
require __DIR__ . '/_subnav.php';
?>

<div class="page-heading">
    <div>
        <h1>Import et export des identités et papiers</h1>
        <p>Exportez le registre en CSV (tableur) ou en JSON, ou importez des identités et des papiers préparés ailleurs. Tout papier importé arrive en brouillon : rien ne part en jeu sans validation.</p>
    </div>
</div>

<section class="panel">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Exporter</div><div class="panel-meta"><?= $rights['truth'] ? 'Vérité de jeu comprise (habilitation Confidentiel)' : 'Sans la vérité de jeu' ?></div></div>
    <form class="panel-body toolbar" method="get" action="<?= $h(url('atak/sse/papiers/echanges/export')) ?>">
        <div class="toolbar-field">
            <label for="x-op">Opération (code)</label>
            <input id="x-op" name="op" maxlength="64" list="x-ops" placeholder="Toutes">
            <datalist id="x-ops"><?php foreach ($scopes as $s): ?><?php if ($s['op_key'] !== null): ?><option value="<?= $h($s['op_key']) ?>"></option><?php endif; ?><?php endforeach; ?></datalist>
        </div>
        <div class="toolbar-field">
            <label for="x-format">Format</label>
            <select id="x-format" name="format"><option value="csv">CSV (tableur)</option><option value="json">JSON</option></select>
        </div>
        <div class="toolbar-actions"><button class="btn" type="submit">Télécharger</button></div>
    </form>
</section>

<?php if ($rights['manage']): ?>
    <section class="panel" style="margin-top:10px">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">02</span> Importer</div><div class="panel-meta">1 000 lignes et 2 Mo au plus</div></div>
        <form class="panel-body" method="post" enctype="multipart/form-data" action="<?= $h(url('atak/sse/papiers/echanges/import')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <p>Colonnes reconnues (première ligne du CSV, séparateur point-virgule ou virgule) :</p>
            <p><code translate="no"><?= $h(implode(';', $columns)) ?></code></p>
            <p class="muted">Une ligne par papier ; les lignes d’une même identité sont regroupées par identity_ref, sinon par nom, prénom et date de naissance. Pays inconnu, numéro hors format ou déjà attribué : ligne écartée.<?= $rights['truth'] ? '' : ' Couvertures et faux sont importés comme identités civiles et papiers officiels (habilitation Confidentiel requise).' ?></p>
            <div class="idp-actions">
                <input type="file" name="file" accept=".csv,.json,text/csv,application/json" required aria-label="Fichier à importer">
                <button class="btn" type="submit">Importer</button>
            </div>
        </form>
    </section>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
