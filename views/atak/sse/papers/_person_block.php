<?php
declare(strict_types=1);
/**
 * Fiche personne SSE — bloc « Documents et contrôles » (COMSPEC IDENTITY).
 * @var array{identities: list<array<string,mixed>>, documents: list<array<string,mixed>>, checks: list<array<string,mixed>>, truth: bool} $idPapers
 */
$idPapers = is_array($idPapers ?? null) ? $idPapers : ['identities' => [], 'documents' => [], 'checks' => [], 'truth' => false];
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$papersPersonId = (int) ($person['id'] ?? 0);
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '">';
?>
<section class="panel" style="margin-top:10px" id="documents-controles">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">I.07</span> Documents et contrôles</div>
        <div class="panel-meta"><span class="badge badge--amber">Simulation</span> Papiers fictifs</div>
    </div>
    <div class="panel-body">
        <?php if ($idPapers['identities'] === [] && $idPapers['documents'] === []): ?>
            <p class="muted">Aucune identité ni aucun papier rattaché à cette fiche.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Papier</th><th>Pays</th><th>Numéro</th><th>Au nom de</th><th>État</th><?php if ($idPapers['truth']): ?><th>Nature</th><?php endif; ?><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($idPapers['documents'] as $d): ?>
                        <tr>
                            <td><?= $h($d['doc_type_label']) ?></td>
                            <td><?= $h($d['country_code']) ?></td>
                            <td><code><?= $h($d['doc_number']) ?></code></td>
                            <td><?= $h(trim(mb_strtoupper((string) $d['identity']['last_name']) . ' ' . $d['identity']['first_name'])) ?></td>
                            <td><?= $h($d['workflow_label']) ?></td>
                            <?php if ($idPapers['truth']): ?><td><?= $h(\App\Support\SseIdentityCatalog::label(\App\Support\SseIdentityCatalog::KINDS, (string) $d['identity']['kind'])) ?></td><?php endif; ?>
                            <td><a class="btn-open" href="<?= $h(url('atak/sse/papiers/documents/' . (int) $d['id'])) ?>">Ouvrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($idPapers['identities'] !== []): ?>
                <p class="muted" style="margin-top:8px">Identités rattachées :
                    <?php foreach ($idPapers['identities'] as $n => $i): ?>
                        <?= $n > 0 ? ' · ' : '' ?><a class="link" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $i['id'])) ?>"><?= $h($i['display_name']) ?></a>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <h3 style="margin:14px 0 6px;font-size:.9rem">Contrôles d’identité reçus</h3>
        <?php if ($idPapers['checks'] === []): ?>
            <p class="muted">Aucun contrôle reçu du terrain pour cette personne.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Contrôle</th><th>Reçu le</th><th>Papier présenté</th><th>Contrôleur</th><th>Verdict</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($idPapers['checks'] as $c): ?>
                        <tr>
                            <td><?= $h($c['ref']) ?></td>
                            <td><?= $h($c['created_at']) ?></td>
                            <td><?= $h(trim(($c['country_code'] ?? '') . ' ' . ($c['doc_number'] ?? '')) ?: '—') ?></td>
                            <td><?= $h($c['controller_callsign'] ?? '—') ?></td>
                            <td><span class="badge idp-verdict--<?= $h($c['verdict'] !== '' ? $c['verdict'] : 'none') ?>"><?= $h($c['verdict_label']) ?></span></td>
                            <td><a class="btn-open" href="<?= $h(url('atak/sse/papiers/controles/' . (int) $c['id'])) ?>">Ouvrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if (!empty($canManage) && $papersPersonId > 0): ?>
            <div class="idp-actions" style="margin-top:10px">
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/papiers/identites/nouvelle') . '?person_id=' . $papersPersonId) ?>">Créer une identité pour cette personne</a>
            </div>
        <?php endif; ?>
    </div>
</section>
