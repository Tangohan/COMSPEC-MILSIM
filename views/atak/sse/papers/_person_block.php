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

        <?php $dossier = is_array($idPapers['dossier'] ?? null) ? $idPapers['dossier'] : ['total' => 0, 'photos' => 0, 'groups' => []]; ?>
        <?php if ($dossier['groups'] !== []): ?>
            <h3 style="margin:14px 0 6px;font-size:.9rem">Contrôles par opération</h3>
            <p class="muted"><?= (int) $dossier['total'] ?> contrôle(s) · <?= (int) $dossier['photos'] ?> photo(s) jointe(s) aux fiches FRM des contrôles</p>
            <?php foreach ($dossier['groups'] as $g): ?>
                <details class="idp-op"<?= count($dossier['groups']) === 1 ? ' open' : '' ?>>
                    <summary>
                        <strong><?= $h($g['op_key'] !== '' ? $g['op_key'] : 'Hors opération') ?></strong>
                        <span class="muted"> · <?= $h($g['map_label']) ?> · <?= (int) $g['total'] ?> contrôle(s), du <?= $h(substr((string) $g['first_at'], 0, 16)) ?> au <?= $h(substr((string) $g['last_at'], 0, 16)) ?></span>
                        <span class="idp-op__verdicts">
                            <?php foreach ($g['verdicts'] as $v => $n): ?>
                                <span class="badge idp-verdict--<?= $h($v !== '' ? $v : 'none') ?>"><?= $h(\App\Support\SseIdentityCatalog::label(\App\Support\SseIdentityCatalog::VERDICTS, (string) $v, 'Sans verdict')) ?> × <?= (int) $n ?></span>
                            <?php endforeach; ?>
                            <?php if ($g['forgeries'] > 0): ?><span class="badge idp-verdict--hvt">Faux détecté × <?= (int) $g['forgeries'] ?></span><?php endif; ?>
                        </span>
                    </summary>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Contrôle</th><th>Reçu le</th><th>Papier présenté</th><th>Contrôleur</th><th>Niveaux</th><th>Verdict</th><th>Fiche FRM</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($g['checks'] as $c): ?>
                                <tr>
                                    <td><?= $h($c['ref']) ?></td>
                                    <td><?= $h($c['created_at']) ?></td>
                                    <td><?= $h(trim(($c['country_code'] ?? '') . ' ' . ($c['doc_number'] ?? '')) ?: '—') ?></td>
                                    <td><?= $h($c['controller_callsign'] ?? '—') ?></td>
                                    <td><?= $h(implode(' ', array_map(static fn (array $l): string => $l['code'] . ($l['tone'] === 'bad' ? ' ✗' : ($l['tone'] === 'warn' ? ' ?' : '')), $c['level_rows']))) ?></td>
                                    <td><span class="badge idp-verdict--<?= $h($c['verdict'] !== '' ? $c['verdict'] : 'none') ?>"><?= $h($c['verdict_label']) ?></span></td>
                                    <td><?php if (is_array($c['note'] ?? null)): ?><a class="link" href="<?= $h(url('atak/sse/fiches/' . (int) $c['note']['id'])) ?>"><?= $h($c['note']['ref']) ?></a> · <?= $h($c['note']['status_label']) ?><?php else: ?>—<?php endif; ?></td>
                                    <td><a class="btn-open" href="<?= $h(url('atak/sse/papiers/controles/' . (int) $c['id'])) ?>">Ouvrir</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($g['photos'] !== []): ?>
                        <div class="idp-photos">
                            <?php foreach (array_slice($g['photos'], 0, 24) as $p): ?>
                                <a href="<?= $h($p['url']) ?>" target="_blank" rel="noopener" title="<?= $h($p['check_ref']) ?>"><img src="<?= $h($p['url']) ?>" alt="<?= $h($p['caption'] !== '' ? $p['caption'] : 'Photo du contrôle ' . $p['check_ref']) ?>" loading="lazy"></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($dossier['groups'] === []): ?>
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
        <?php endif; ?>
        <?php $suggestions = is_array($idPapers['suggestions'] ?? null) ? $idPapers['suggestions'] : []; ?>
        <?php if ($suggestions !== []): ?>
            <h3 style="margin:14px 0 6px;font-size:.9rem">Possiblement la même personne</h3>
            <p class="muted">Même clé biométrique relevée lors de contrôles. À vérifier : rien n’est fusionné automatiquement.</p>
            <ul>
                <?php foreach ($suggestions as $s): ?>
                    <li><a class="link" href="<?= $h(url($s['type'] === 'person' ? 'atak/sse/identites/' . (int) $s['id'] : 'atak/sse/papiers/identites/' . (int) $s['id'])) ?>"><?= $h($s['ref']) ?> · <?= $h($s['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($canManage) && $papersPersonId > 0): ?>
            <div class="idp-actions" style="margin-top:10px">
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/papiers/identites/nouvelle') . '?person_id=' . $papersPersonId) ?>">Créer une identité pour cette personne</a>
            </div>
        <?php endif; ?>
    </div>
</section>
