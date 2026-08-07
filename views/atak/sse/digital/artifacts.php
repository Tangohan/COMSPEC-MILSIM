<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $artifacts */
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique')) ?>">Exploitation numérique</a> / <strong>Artefacts</strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Laboratoire // Visionneuse</div>
        <h1>Artefacts numériques</h1>
        <p>Données extraites — distinctes du support et de l’acquisition.</p>
    </div>
    <div class="page-reference"><strong>Vue // Artefacts</strong> ATH-SSE-LABNUM-ART</div>
</div>
<form class="toolbar" method="get">
    <div class="toolbar-field"><label for="category">Catégorie</label>
        <select id="category" name="category"><option value="">Toutes</option>
            <?php foreach ($categories as $k => $lab): ?><option value="<?= $h($k) ?>" <?= ($filters['category'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field"><label for="status">Statut</label>
        <select id="status" name="status"><option value="">Tous</option>
            <?php foreach ($statuses as $k => $lab): ?><option value="<?= $h($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field"><label for="q">Recherche</label><input id="q" name="q" type="search" value="<?= $h($filters['q'] ?? '') ?>"></div>
    <?php if (!empty($filters['device_id'])): ?><input type="hidden" name="device_id" value="<?= (int) $filters['device_id'] ?>"><?php endif; ?>
    <div class="toolbar-actions"><button class="btn" type="submit">Filtrer</button></div>
</form>
<section class="panel">
    <?php if ($artifacts === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucun artefact</strong></div></div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Nom</th><th>Catégorie</th><th>Taille</th><th>Intérêt</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($artifacts as $art): ?>
                <tr>
                    <td><?= $h($art['name'] ?? '') ?><?php if (!empty($art['is_deleted'])): ?> <em>(supprimé)</em><?php endif; ?><?php if (!empty($art['is_encrypted'])): ?> <em>(chiffré)</em><?php endif; ?></td>
                    <td><?= $h($art['category_label'] ?? '') ?></td>
                    <td><?= $h($art['size_label'] ?? '—') ?></td>
                    <td><?= $h($art['interest_level_label'] ?? '') ?></td>
                    <td><?= $h($art['status_label'] ?? '') ?></td>
                    <td><a class="btn-open" href="<?= $h(url('atak/sse/exploitation-numerique/artefacts/' . (int) $art['id'])) ?>">Fiche</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php';
