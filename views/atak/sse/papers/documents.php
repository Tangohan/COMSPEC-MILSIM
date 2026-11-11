<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $documents */
/** @var array<string,string> $filters */
/** @var array<string,int> $counts */
/** @var list<array<string,mixed>> $countries */
$canSeeTruth = !empty($canSeeTruth);
require __DIR__ . '/_subnav.php';
?>

<div class="page-heading">
    <div>
        <h1>Papiers d’identité</h1>
        <p>Cartes d’identité, passeports et permis des pays fictifs. Seuls les papiers émis sont chargés par le serveur de jeu et reconnus lors des contrôles.</p>
    </div>
</div>

<div class="metrics-grid">
    <?php foreach (C::WORKFLOWS as $k => $label): ?>
        <div class="metric"><div class="metric-label"><?= $h($label) ?></div><div class="metric-value"><?= (int) ($counts[$k] ?? 0) ?></div></div>
    <?php endforeach; ?>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/papiers')) ?>">
    <div class="toolbar-field">
        <label for="workflow">État</label>
        <select id="workflow" name="workflow">
            <option value="">Tous les états</option>
            <?php foreach (C::WORKFLOWS as $k => $label): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['workflow'] ?? '') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="doc_type">Type</label>
        <select id="doc_type" name="doc_type">
            <option value="">Tous les types</option>
            <?php foreach (C::DOC_TYPES as $k => $label): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['doc_type'] ?? '') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="country">Pays</label>
        <select id="country" name="country">
            <option value="">Tous les pays</option>
            <?php foreach ($countries as $c): ?>
                <option value="<?= $h($c['code']) ?>" <?= ($filters['country_code'] ?? '') === $c['code'] ? 'selected' : '' ?>><?= $h($c['name']) ?> (<?= $h($c['code']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($canSeeTruth): ?>
        <div class="toolbar-field">
            <label for="origin">Origine</label>
            <select id="origin" name="origin">
                <option value="">Toutes les origines</option>
                <?php foreach (C::ORIGINS as $k => $label): ?>
                    <option value="<?= $h($k) ?>" <?= ($filters['origin'] ?? '') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="toolbar-field">
        <label for="q">Recherche</label>
        <input id="q" name="q" type="search" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Numéro, nom…">
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
        <div class="panel-title"><span class="panel-index">01</span> Registre des papiers</div>
        <div class="panel-meta"><?= count($documents) ?> papier(s)</div>
    </div>
    <?php if ($documents === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucun papier</strong><p>Créez une identité, puis ajoutez-lui une carte, un passeport ou un permis.</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Référence</th><th>Type</th><th>Pays</th><th>Numéro</th><th>Titulaire</th><th>État</th><?php if ($canSeeTruth): ?><th>Origine</th><?php endif; ?><th></th></tr></thead>
                <tbody>
                <?php foreach ($documents as $d): ?>
                    <tr>
                        <td><?= $h($d['ref']) ?></td>
                        <td><?= $h($d['doc_type_label']) ?></td>
                        <td><?= $h($d['country_code']) ?></td>
                        <td><code><?= $h($d['doc_number']) ?></code></td>
                        <td><?= $h(trim(mb_strtoupper((string) $d['identity']['last_name']) . ' ' . $d['identity']['first_name'])) ?></td>
                        <td><span class="badge<?= $d['workflow'] === 'issued' ? '' : ' badge--gray' ?>"><?= $h($d['workflow_label']) ?></span><?php if ($d['expired']): ?> <span class="badge badge--amber">Expiré</span><?php endif; ?></td>
                        <?php if ($canSeeTruth): ?><td><?= $h(C::label(C::ORIGINS, $d['st_origin'])) ?></td><?php endif; ?>
                        <td><a class="btn-open" href="<?= $h(url('atak/sse/papiers/documents/' . (int) $d['id'])) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">02</span> Pays émetteurs</div>
        <div class="panel-meta">Pays fictifs, codes hors norme internationale</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Code</th><th>Pays</th><th>Format du numéro</th><th>Autorité</th><th>Validité</th></tr></thead>
            <tbody>
            <?php foreach ($countries as $c): ?>
                <tr>
                    <td><strong><?= $h($c['code']) ?></strong><?php if ($c['fictional']): ?> <span class="badge badge--amber">Fictif</span><?php endif; ?></td>
                    <td><?= $h($c['name']) ?></td>
                    <td><code><?= $h($c['number_format']) ?></code></td>
                    <td><?= $h($c['authority'] !== '' ? $c['authority'] : '—') ?></td>
                    <td><?= (int) $c['validity_years'] ?> ans</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="panel-body"><p class="muted">Format du numéro : « # » est un chiffre, « @ » une lettre ; les autres caractères sont imprimés tels quels.</p></div>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
