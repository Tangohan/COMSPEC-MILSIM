<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $docs */
/** @var array{workflow:string,q:string} $filters */
/** @var array{view:bool,manage:bool,truth:bool} $rights */
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/papiers')) ?>">Identités &amp; documents</a> / <strong>Atelier</strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Préparation // Atelier de documents</div>
        <h1>Atelier de documents</h1>
        <p>Composez un papier avec son aperçu en direct (recto et verso, ou pages du passeport), aux couleurs du pays émetteur imaginaire, puis validez-le et émettez-le pour qu’il parte en jeu. L’aperçu s’exporte en image pour le jeu de rôle.</p>
    </div>
    <div class="page-reference"><strong><?= count($docs) ?> papier(s)</strong> Réf. ATH-SSE-IDENT-ATL</div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/papiers/atelier')) ?>">
    <div class="toolbar-field">
        <label for="q">Recherche</label>
        <input id="q" name="q" value="<?= $h($filters['q']) ?>" placeholder="Nom, prénom ou numéro">
    </div>
    <div class="toolbar-field">
        <label for="workflow">Étape</label>
        <select id="workflow" name="workflow">
            <option value="">Toutes</option>
            <?php foreach (C::WORKFLOWS as $k => $label): ?><option value="<?= $h($k) ?>" <?= $filters['workflow'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-actions">
        <button class="btn btn--ghost" type="submit">Filtrer</button>
        <?php if ($rights['manage']): ?><a class="btn" href="<?= $h(url('atak/sse/papiers/atelier/nouveau')) ?>">Nouveau papier</a><?php endif; ?>
    </div>
</form>

<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Papiers</div><div class="panel-meta">100 au plus, les plus récents d’abord</div></div>
    <div class="panel-body">
        <?php if ($docs === []): ?>
            <p class="muted">Aucun papier. Créez-en un, appliquez un kit type à des membres ou générez la population d’une opération.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Papier</th><th>Titulaire</th><th>Type</th><th>Pays</th><th>Étape</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($docs as $d): ?>
                        <tr>
                            <td><code translate="no"><?= $h($d['ref']) ?></code> <span class="muted" translate="no"><?= $h($d['doc_number']) ?></span></td>
                            <td><?= $h(trim(mb_strtoupper((string) $d['identity']['last_name']) . ' ' . $d['identity']['first_name'])) ?></td>
                            <td><?= $h($d['doc_type_label']) ?></td>
                            <td><?= $h($d['country_code']) ?></td>
                            <td><span class="badge"><?= $h($d['workflow_label']) ?></span><?php if (!empty($d['duplicated_from_id'])): ?> <span class="badge">Copie</span><?php endif; ?></td>
                            <td><a class="btn-open" href="<?= $h(url('atak/sse/papiers/atelier/' . (int) $d['id'])) ?>">Ouvrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
