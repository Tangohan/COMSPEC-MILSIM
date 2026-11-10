<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $identities */
/** @var array<string,string> $filters */
/** @var list<array<string,mixed>> $countries */
$canSeeTruth = !empty($canSeeTruth);
require __DIR__ . '/_subnav.php';
?>

<div class="page-heading">
    <div>
        <h1>Identités fictives</h1>
        <p>Les personnes telles que leurs papiers les présentent : identité civile, alias, couverture préparée, identité falsifiée ou tirée au sort pour la partie.</p>
    </div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/papiers/identites')) ?>">
    <?php if ($canSeeTruth): ?>
        <div class="toolbar-field">
            <label for="kind">Nature</label>
            <select id="kind" name="kind">
                <option value="">Toutes les natures</option>
                <?php foreach (C::KINDS as $k => $label): ?>
                    <option value="<?= $h($k) ?>" <?= ($filters['kind'] ?? '') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="toolbar-field">
        <label for="country">Nationalité</label>
        <select id="country" name="country">
            <option value="">Toutes</option>
            <?php foreach ($countries as $c): ?>
                <option value="<?= $h($c['code']) ?>" <?= ($filters['nationality'] ?? '') === $c['code'] ? 'selected' : '' ?>><?= $h($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="q">Recherche</label>
        <input id="q" name="q" type="search" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Nom, prénom…">
    </div>
    <div class="toolbar-actions">
        <button class="btn" type="submit">Appliquer</button>
        <?php if (!empty($canManage)): ?>
            <a class="btn" href="<?= $h(url('atak/sse/papiers/identites/nouvelle')) ?>">Nouvelle identité</a>
        <?php endif; ?>
    </div>
</form>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01</span> Registre des identités</div>
        <div class="panel-meta"><?= count($identities) ?> identité(s)</div>
    </div>
    <?php if ($identities === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucune identité</strong><p>Créez-en une à la main ou tirez-la au sort avec « Générer ».</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Référence</th><th>Nom</th><th>Naissance</th><th>Nationalité</th><?php if ($canSeeTruth): ?><th>Nature</th><?php endif; ?><th>Papiers</th><th>Fiche personne</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($identities as $i): ?>
                    <tr>
                        <td><?= $h($i['ref']) ?></td>
                        <td><strong><?= $h($i['display_name']) ?></strong></td>
                        <td><?= $h($i['birth_date'] ?? '—') ?></td>
                        <td><?= $h($i['nationality'] ?? '—') ?></td>
                        <?php if ($canSeeTruth): ?><td><?= $h($i['kind_label']) ?></td><?php endif; ?>
                        <td><?= (int) $i['documents_count'] ?></td>
                        <td>
                            <?php if (($i['person_id'] ?? 0) > 0 && ($canSeeTruth || !in_array($i['kind'], ['cover', 'forged'], true))): ?>
                                <a class="link" href="<?= $h(url('atak/sse/identites/' . (int) $i['person_id'])) ?>">IDN-<?= $h(str_pad((string) (int) $i['person_id'], 5, '0', STR_PAD_LEFT)) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><a class="btn-open" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $i['id'])) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
