<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $board tableau de bord (SseIdCheckInsightService::dashboard) */
/** @var list<array<string,mixed>> $operations */
/** @var array{map: int, op: string} $filters */
/** @var string $scopeLabel */
/** @var string $csrfToken */
$pad = static fn (int $n): string => str_pad((string) $n, 3, '0', STR_PAD_LEFT);
$who = static fn (array $c): string => trim(mb_strtoupper((string) ($c['presented_last'] ?? '')) . ' ' . (string) ($c['presented_first'] ?? '')) ?: 'Identité non lue';
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/papiers')) ?>">Identités &amp; documents</a> / <strong>Tableau de bord</strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Exploitation // Contrôles d’identité</div>
        <h1>Tableau de bord identité</h1>
        <p>Ce que les contrôles du terrain ont donné pour l’opération : papiers contrôlés, détections, faux, personnes recherchées interpellées, et les fiches de renseignement nées des contrôles, à valider.</p>
    </div>
    <div class="page-reference"><strong><?= $h($scopeLabel) ?></strong> Réf. ATH-SSE-IDENT-TDB</div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/papiers/tableau')) ?>">
    <div class="toolbar-field">
        <label for="op">Opération</label>
        <select id="op" name="op">
            <option value="">Toutes les opérations</option>
            <?php $seenOps = []; foreach ($operations as $o): $code = $o['op_key'] !== '' ? $o['op_key'] : '-'; if (isset($seenOps[$code])) { continue; } $seenOps[$code] = true; ?>
                <option value="<?= $h($code) ?>" <?= $filters['op'] === $code ? 'selected' : '' ?>><?= $h($o['op_key'] !== '' ? $o['op_key'] : 'Hors opération') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="map">Carte</label>
        <select id="map" name="map">
            <option value="0">Toutes les cartes</option>
            <?php $seenMaps = []; foreach ($operations as $o): if (isset($seenMaps[$o['context_id']])) { continue; } $seenMaps[$o['context_id']] = true; ?>
                <option value="<?= (int) $o['context_id'] ?>" <?= (int) $filters['map'] === (int) $o['context_id'] ? 'selected' : '' ?>><?= $h($o['map_label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-actions"><button class="btn" type="submit">Afficher</button></div>
</form>

<div class="metrics-grid">
    <div class="metric">
        <div class="metric-label">Contrôles</div>
        <div class="metric-value"><?= $h($pad((int) $board['total'])) ?></div>
        <div class="metric-detail"><?= count($board['controllers']) ?> contrôleur(s)</div>
    </div>
    <div class="metric">
        <div class="metric-label">Détections</div>
        <div class="metric-value"><?= $h($pad((int) $board['detections'])) ?></div>
        <div class="metric-detail">Verdict autre que RAS, ou faux détecté</div>
    </div>
    <div class="metric">
        <div class="metric-label">Faux détectés</div>
        <div class="metric-value"><?= $h($pad((int) $board['forgeries'])) ?></div>
        <div class="metric-detail">
            <?php if ($board['real_forgeries'] !== null): ?>
                <?= (int) $board['real_forgeries'] ?> faux réellement présenté(s)
            <?php else: ?>
                Vérité de jeu réservée à l’habilitation Confidentiel
            <?php endif; ?>
        </div>
    </div>
    <div class="metric">
        <div class="metric-label">Recherchés interpellés</div>
        <div class="metric-value"><?= $h($pad((int) $board['wanted'])) ?></div>
        <div class="metric-detail">Personnes recherchées ou cibles prioritaires contrôlées</div>
    </div>
</div>

<?php if ($board['total'] === 0): ?>
    <section class="panel" style="margin-top:10px">
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucun contrôle pour cette opération</strong><p>Les contrôles apparaissent ici quand un joueur les transmet depuis son terminal en jeu.</p></div></div>
    </section>
<?php else: ?>
    <?php if (!empty($board['truncated'])): ?><p class="muted">Seuls les 2 000 contrôles les plus récents sont comptés : choisissez une opération.</p><?php endif; ?>

    <section class="panel" id="frm" style="margin-top:10px">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">01</span> Fiches FRM à valider</div>
            <div class="panel-meta"><?= count($board['drafts']) ?> brouillon(s) né(s) des contrôles</div>
        </div>
        <div class="panel-body">
            <?php if ($board['drafts'] === []): ?>
                <p class="muted">Aucun brouillon en attente. Ils arrivent quand un joueur touche « Joindre à une FRM » sur un contrôle, depuis son vrai téléphone.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Fiche</th><th>Contrôle</th><th>Personne contrôlée</th><th>Verdict</th><th>Auteur</th><th>Photos</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($board['drafts'] as $d): ?>
                            <tr>
                                <td><code><?= $h($d['note_ref']) ?></code></td>
                                <td><a class="link" href="<?= $h(url('atak/sse/papiers/controles/' . (int) $d['check_id'])) ?>"><?= $h($d['check_ref']) ?></a></td>
                                <td><?= $h($d['who'] !== '' ? $d['who'] : '—') ?></td>
                                <td><span class="badge idp-verdict--<?= $h($d['verdict'] !== '' ? $d['verdict'] : 'none') ?>"><?= $h($d['verdict_label']) ?></span></td>
                                <td><?= $h($d['author'] !== '' ? $d['author'] : '—') ?></td>
                                <td><?= (int) $d['photos'] ?></td>
                                <td>
                                    <div class="idp-actions">
                                        <a class="btn-open" href="<?= $h(url('atak/sse/fiches/' . (int) $d['note_id'])) ?>">Relire</a>
                                        <?php if (!empty($canManage)): ?>
                                            <form method="post" action="<?= $h(url('atak/sse/fiches/' . (int) $d['note_id'] . '/suivi')) ?>">
                                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                                <input type="hidden" name="status" value="transmise">
                                                <button class="btn" type="submit">Valider</button>
                                            </form>
                                            <form method="post" action="<?= $h(url('atak/sse/fiches/' . (int) $d['note_id'] . '/suivi')) ?>">
                                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                                <input type="hidden" name="status" value="sans_suite">
                                                <button class="btn btn--ghost" type="submit">Écarter</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($board['points'])): ?>
        <?php
        $sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-id-workshop.css')) . '">';
        $sseExtraScripts = ($sseExtraScripts ?? '') . '<script src="' . $h(asset_url('assets/js/sse-id-checks-map.js')) . '"></script>';
        ?>
        <section class="panel" style="margin-top:10px">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">01b</span> Carte des contrôles</div>
                <div class="panel-meta"><?= count($board['points']) ?> contrôle(s) positionné(s), coordonnées du terrain</div>
            </div>
            <div class="panel-body">
                <div id="idw-checks-map" class="idw-map" role="img" aria-label="Carte des contrôles d’identité par position"
                     data-points="<?= $h(json_encode($board['points'], JSON_UNESCAPED_UNICODE)) ?>"
                     data-check-url="<?= $h(url('atak/sse/papiers/controles')) ?>"></div>
                <p class="muted" style="margin-top:6px">Vert : RAS · orange : signalé ou expiré · rouge : recherché, cible prioritaire, volé, faux ou inconnu. Touchez un point pour ouvrir le contrôle.</p>
            </div>
        </section>
    <?php endif; ?>

    <div class="idp-grid" style="margin-top:10px">
        <section class="panel">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">02</span> Verdicts</div>
                <div class="panel-meta">Du plus grave au moins grave</div>
            </div>
            <div class="panel-body">
                <div class="table-wrap">
                    <table>
                        <tbody>
                        <?php foreach ($board['by_verdict'] as $v => $n): ?>
                            <tr>
                                <td><span class="badge idp-verdict--<?= $h($v !== '' ? $v : 'none') ?>"><?= $h(C::label(C::VERDICTS, (string) $v, 'Sans verdict')) ?></span></td>
                                <td style="text-align:right"><?= (int) $n ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <section class="panel">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">03</span> Contrôleurs</div>
                <div class="panel-meta">Les plus actifs</div>
            </div>
            <div class="panel-body">
                <div class="table-wrap">
                    <table>
                        <tbody>
                        <?php foreach ($board['controllers'] as $cs => $n): ?>
                            <tr><td><?= $h($cs) ?></td><td style="text-align:right"><?= (int) $n ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <section class="panel" style="margin-top:10px">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">04</span> Recherchés interpellés</div>
            <div class="panel-meta"><?= (int) $board['wanted'] ?> personne(s)</div>
        </div>
        <div class="panel-body">
            <?php if ($board['wanted_list'] === []): ?>
                <p class="muted">Aucune personne recherchée contrôlée.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Personne contrôlée</th><th>Verdict</th><th>Contrôle</th><th>Contrôleur</th><th>Carroyage</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($board['wanted_list'] as $c): ?>
                            <tr>
                                <td><?= $h($who($c)) ?></td>
                                <td><span class="badge idp-verdict--<?= $h($c['verdict']) ?>"><?= $h($c['verdict_label']) ?></span></td>
                                <td><?= $h($c['ref']) ?> · <?= $h($c['created_at']) ?></td>
                                <td><?= $h($c['controller_callsign'] ?? '—') ?></td>
                                <td><?= $h($c['grid'] ?? '—') ?></td>
                                <td>
                                    <?php if ((int) ($c['person_id'] ?? 0) > 0): ?><a class="btn-open" href="<?= $h(url('atak/sse/identites/' . (int) $c['person_id'])) ?>">Dossier</a><?php endif; ?>
                                    <a class="btn-open" href="<?= $h(url('atak/sse/papiers/controles/' . (int) $c['id'])) ?>">Contrôle</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel" style="margin-top:10px">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">05</span> Dernières détections</div>
            <div class="panel-meta">15 au plus</div>
        </div>
        <div class="panel-body">
            <?php if ($board['alerts'] === []): ?>
                <p class="muted">Aucune détection : tous les papiers contrôlés étaient en règle.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Contrôle</th><th>Reçu le</th><th>Personne contrôlée</th><th>Papier</th><th>Verdict</th><th>Photos</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($board['alerts'] as $c): ?>
                            <tr>
                                <td><?= $h($c['ref']) ?></td>
                                <td><?= $h($c['created_at']) ?></td>
                                <td><?= $h($who($c)) ?></td>
                                <td><?= $h(trim(($c['country_code'] ?? '') . ' ' . ($c['doc_number'] ?? '')) ?: '—') ?></td>
                                <td>
                                    <span class="badge idp-verdict--<?= $h($c['verdict'] !== '' ? $c['verdict'] : 'none') ?>"><?= $h($c['verdict_label']) ?></span>
                                    <?php if (!empty($c['forgery'])): ?><span class="badge idp-verdict--hvt">Faux document</span><?php endif; ?>
                                </td>
                                <td><?= count($c['photos']) ?></td>
                                <td><a class="btn-open" href="<?= $h(url('atak/sse/papiers/controles/' . (int) $c['id'])) ?>">Ouvrir</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
