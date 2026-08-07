<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var string $q */
/** @var list<array{type:string,ref:string,label:string,href:string}> $results */
?>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Recherche // Globale</div>
        <h1>Résultats</h1>
        <p>Identités, sites, dossiers, dossiers d’intérêt et investigations correspondant à « <?= $h($q) ?> ».</p>
    </div>
</div>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">R.01</span> Objets trouvés</div>
        <div class="panel-meta"><?= count($results) ?> résultat<?= count($results) > 1 ? 's' : '' ?></div>
    </div>
    <?php if ($results === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucun résultat</strong><p>Élargissez la requête ou essayez un autre terme (indicatif, référence, nom…).</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Type</th><th>Référence</th><th>Libellé</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                    <tr>
                        <td><?= $h($r['type']) ?></td>
                        <td class="record-id"><?= $h($r['ref']) ?></td>
                        <td><?= $h($r['label']) ?></td>
                        <td><a class="btn-open" href="<?= $h($r['href']) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
