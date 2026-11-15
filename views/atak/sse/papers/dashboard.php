<?php
declare(strict_types=1);
use App\Services\Sse\SseIdCheckInsightService as Insight;
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $board tableau de bord (SseIdCheckInsightService::dashboard) */
/** @var list<array<string,mixed>> $operations */
/** @var array{map: int, op: string} $filters */
/** @var string $scopeLabel */
/** @var string $csrfToken */
$who = static fn (array $c): string => trim(mb_strtoupper((string) ($c['presented_last'] ?? '')) . ' ' . (string) ($c['presented_first'] ?? '')) ?: 'Identité non lue';
require __DIR__ . '/_subnav.php';

$mapId = (int) $filters['map'];
$opRaw = (string) $filters['op'];
$scoped = $mapId > 0 || $opRaw !== '';
$scopeQ = static fn (array $extra = []): string => Insight::scopeQuery($mapId > 0 ? $mapId : null, $opRaw, $extra);
$checksUrl = static fn (array $extra = []): string => url('atak/sse/papiers/controles') . $scopeQ($extra);
$returnTo = 'atak/sse/papiers/tableau' . $scopeQ() . '#frm';
$drafts = $board['drafts'];
?>

<div class="page-heading">
    <div>
        <h1>Tableau de bord identité</h1>
        <p>Le bilan des contrôles d’identité faits sur le terrain : ce qui a été détecté, qui est recherché, et les fiches de renseignement à valider.</p>
    </div>
    <div class="idp-board-head">
        <span class="badge <?= $scoped ? '' : 'badge--gray' ?>"><?= $h($scopeLabel) ?></span>
        <a class="btn btn--ghost" href="<?= $h($checksUrl()) ?>">Tous les contrôles</a>
    </div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/papiers/tableau')) ?>">
    <div class="toolbar-field">
        <label for="op">Opération</label>
        <select id="op" name="op" onchange="this.form.submit()">
            <option value="">Toutes les opérations</option>
            <?php $seenOps = []; foreach ($operations as $o): $code = $o['op_key'] !== '' ? $o['op_key'] : '-'; if (isset($seenOps[$code])) { continue; } $seenOps[$code] = true; ?>
                <option value="<?= $h($code) ?>" <?= $opRaw === $code ? 'selected' : '' ?>><?= $h($o['op_key'] !== '' ? $o['op_key'] : 'Hors opération') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="map">Carte</label>
        <select id="map" name="map" onchange="this.form.submit()">
            <option value="0">Toutes les cartes</option>
            <?php $seenMaps = []; foreach ($operations as $o): if (isset($seenMaps[$o['context_id']])) { continue; } $seenMaps[$o['context_id']] = true; ?>
                <option value="<?= (int) $o['context_id'] ?>" <?= $mapId === (int) $o['context_id'] ? 'selected' : '' ?>><?= $h($o['map_label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-actions">
        <button class="btn" type="submit">Afficher</button>
        <?php if ($scoped): ?><a class="btn btn--ghost" href="<?= $h(url('atak/sse/papiers/tableau')) ?>">Toutes les opérations</a><?php endif; ?>
    </div>
</form>

<div class="metrics-grid idp-board-metrics">
    <a class="metric" href="<?= $h($checksUrl()) ?>">
        <div class="metric-label">Contrôles</div>
        <div class="metric-value"><?= (int) $board['total'] ?></div>
        <div class="metric-detail"><?= count($board['controllers']) ?> contrôleur(s) · voir la liste</div>
    </a>
    <a class="metric" href="#detections">
        <div class="metric-label">Détections</div>
        <div class="metric-value"><?= (int) $board['detections'] ?></div>
        <div class="metric-detail">Verdict autre que RAS, ou faux détecté</div>
    </a>
    <a class="metric" href="#recherches">
        <div class="metric-label">Recherchés appréhendés</div>
        <div class="metric-value"><?= (int) $board['wanted'] ?></div>
        <div class="metric-detail">Personnes recherchées ou cibles prioritaires</div>
    </a>
    <a class="metric <?= $drafts !== [] ? 'metric--todo' : '' ?>" href="#frm">
        <div class="metric-label">Fiches à valider</div>
        <div class="metric-value"><?= count($drafts) ?></div>
        <div class="metric-detail"><?= $drafts !== [] ? 'Brouillons en attente de relecture' : 'Rien en attente' ?></div>
    </a>
</div>
<p class="muted idp-board-note">
    <?= (int) $board['forgeries'] ?> faux détecté(s)
    <?php if ($board['real_forgeries'] !== null): ?>· <?= (int) $board['real_forgeries'] ?> réellement présenté(s) d’après le registre
    <?php else: ?>· Vérité de jeu réservée à l’habilitation Secret (S)<?php endif; ?>
</p>

<?php if (!empty($board['truncated'])): ?><p class="muted">Seuls les 2 000 contrôles les plus récents sont comptés : choisissez une opération.</p><?php endif; ?>

<section class="panel idp-board-frm" id="frm" tabindex="-1" aria-labelledby="frm-title">
    <div class="panel-header">
        <div class="panel-title" id="frm-title">Fiches de renseignement à valider</div>
        <div class="panel-meta"><?= count($drafts) ?> brouillon(s) né(s) des contrôles</div>
    </div>
    <div class="panel-body">
        <?php if ($drafts === []): ?>
            <div class="empty-state"><div class="empty-state-inner">
                <strong>Aucune fiche en attente</strong>
                <p><?= $board['total'] > 0 ? 'Un brouillon arrive ici quand un joueur touche « Joindre à une FRM » sur un contrôle depuis son téléphone en jeu. Les contrôles déjà reçus restent consultables dans la liste.' : 'Un brouillon arrive ici quand un joueur touche « Joindre à une FRM » sur un contrôle depuis son téléphone en jeu.' ?></p>
                <a class="btn btn--ghost" href="<?= $h($checksUrl()) ?>">Voir les contrôles</a>
            </div></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Fiche</th><th>Contrôle</th><th>Personne contrôlée</th><th>Verdict</th><th>Auteur</th><th>Photos</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($drafts as $d): ?>
                        <tr>
                            <td><code><?= $h($d['note_ref']) ?></code></td>
                            <td><a class="link" href="<?= $h(url('atak/sse/papiers/controles/' . (int) $d['check_id'])) ?>"><?= $h($d['check_ref']) ?></a></td>
                            <td><?= $h($d['who'] !== '' ? $d['who'] : 'Identité non lue') ?></td>
                            <td><span class="badge idp-verdict--<?= $h($d['verdict'] !== '' ? $d['verdict'] : 'none') ?>"><?= $h($d['verdict_label']) ?></span></td>
                            <td><?= $h($d['author'] !== '' ? $d['author'] : '—') ?></td>
                            <td><?= (int) $d['photos'] ?></td>
                            <td>
                                <div class="idp-actions">
                                    <a class="btn btn--ghost" href="<?= $h(url('atak/sse/fiches/' . (int) $d['note_id'])) ?>">Relire</a>
                                    <?php if (!empty($canManage)): ?>
                                        <form method="post" action="<?= $h(url('atak/sse/fiches/' . (int) $d['note_id'] . '/suivi')) ?>">
                                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                            <input type="hidden" name="return" value="<?= $h($returnTo) ?>">
                                            <input type="hidden" name="status" value="transmise">
                                            <button class="btn" type="submit">Valider</button>
                                        </form>
                                        <form method="post" action="<?= $h(url('atak/sse/fiches/' . (int) $d['note_id'] . '/suivi')) ?>">
                                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                            <input type="hidden" name="return" value="<?= $h($returnTo) ?>">
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
            <?php if (empty($canManage)): ?><p class="muted">Vous pouvez relire les fiches ; la validation est réservée aux gestionnaires du bureau.</p><?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($board['total'] === 0): ?>
    <section class="panel">
        <div class="empty-state"><div class="empty-state-inner">
            <strong><?= $scoped ? 'Aucun contrôle pour ce périmètre' : 'Aucun contrôle reçu' ?></strong>
            <p><?= $scoped ? 'Aucun contrôle n’a été transmis pour cette opération ou cette carte. Élargissez le périmètre pour voir les autres.' : 'Les contrôles apparaissent ici quand un joueur les transmet depuis son terminal en jeu. Vous pouvez aussi activer l’envoi automatique dans la liste des contrôles.' ?></p>
            <?php if ($scoped): ?>
                <a class="btn" href="<?= $h(url('atak/sse/papiers/tableau')) ?>">Toutes les opérations</a>
            <?php else: ?>
                <a class="btn" href="<?= $h(url('atak/sse/papiers/controles')) ?>">Ouvrir la liste des contrôles</a>
            <?php endif; ?>
        </div></div>
    </section>
<?php else: ?>
    <section class="panel" id="recherches" tabindex="-1">
        <div class="panel-header">
            <div class="panel-title">Recherchés appréhendés</div>
            <div class="panel-meta"><?= (int) $board['wanted'] ?> personne(s)</div>
        </div>
        <div class="panel-body">
            <?php if ($board['wanted_list'] === []): ?>
                <p class="muted">Aucune personne recherchée n’a été contrôlée sur ce périmètre.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Personne contrôlée</th><th>Verdict</th><th>Contrôle</th><th>Contrôleur</th><th>Carroyage</th><th>Ouvrir</th></tr></thead>
                        <tbody>
                        <?php foreach ($board['wanted_list'] as $c): ?>
                            <tr>
                                <td><?= $h($who($c)) ?></td>
                                <td><span class="badge idp-verdict--<?= $h($c['verdict']) ?>"><?= $h($c['verdict_label']) ?></span></td>
                                <td><?= $h($c['ref']) ?> · <?= $h($c['created_at']) ?></td>
                                <td><?= $h($c['controller_callsign'] ?? '—') ?></td>
                                <td><?= $h($c['grid'] ?? '—') ?></td>
                                <td>
                                    <div class="idp-actions">
                                        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/papiers/controles/' . (int) $c['id'])) ?>">Contrôle</a>
                                        <?php if ((int) ($c['person_id'] ?? 0) > 0): ?><a class="btn btn--ghost" href="<?= $h(url('atak/sse/identites/' . (int) $c['person_id'])) ?>">Dossier</a><?php endif; ?>
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

    <section class="panel" id="detections" tabindex="-1">
        <div class="panel-header">
            <div class="panel-title">Dernières détections</div>
            <div class="panel-meta">15 dernières · <a class="link" href="<?= $h($checksUrl()) ?>">tous les contrôles</a></div>
        </div>
        <div class="panel-body">
            <?php if ($board['alerts'] === []): ?>
                <p class="muted">Aucune détection : tous les papiers contrôlés étaient en règle.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Contrôle</th><th>Reçu le</th><th>Personne contrôlée</th><th>Papier</th><th>Verdict</th><th>Photos</th><th>Ouvrir</th></tr></thead>
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
                                <td><a class="btn btn--ghost" href="<?= $h(url('atak/sse/papiers/controles/' . (int) $c['id'])) ?>">Ouvrir</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="idp-grid">
        <section class="panel">
            <div class="panel-header">
                <div class="panel-title">Verdicts</div>
                <div class="panel-meta">Du plus grave au moins grave</div>
            </div>
            <div class="panel-body">
                <ul class="idp-rank">
                    <?php foreach ($board['by_verdict'] as $v => $n): ?>
                        <li>
                            <a href="<?= $h($checksUrl($v !== '' ? ['verdict' => (string) $v] : [])) ?>">
                                <span class="badge idp-verdict--<?= $h($v !== '' ? $v : 'none') ?>"><?= $h(C::label(C::VERDICTS, (string) $v, 'Sans verdict')) ?></span>
                                <strong><?= (int) $n ?></strong>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <section class="panel">
            <div class="panel-header">
                <div class="panel-title">Contrôleurs</div>
                <div class="panel-meta">Les plus actifs</div>
            </div>
            <div class="panel-body">
                <ul class="idp-rank">
                    <?php foreach ($board['controllers'] as $cs => $n): ?>
                        <li><span><?= $h($cs) ?></span><strong><?= (int) $n ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>

    <?php if (!empty($board['points'])): ?>
        <?php
        $sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-id-workshop.css')) . '">';
        $sseExtraScripts = ($sseExtraScripts ?? '') . '<script src="' . $h(asset_url('assets/js/sse-id-checks-map.js')) . '"></script>';
        ?>
        <section class="panel">
            <div class="panel-header">
                <div class="panel-title">Carte des contrôles</div>
                <div class="panel-meta"><?= count($board['points']) ?> contrôle(s) positionné(s)</div>
            </div>
            <div class="panel-body">
                <div id="idw-checks-map" class="idw-map" role="img" aria-label="Carte des vérifications d’identité sur le terrain par position"
                     data-points="<?= $h(json_encode($board['points'], JSON_UNESCAPED_UNICODE)) ?>"
                     data-check-url="<?= $h(url('atak/sse/papiers/controles')) ?>"></div>
                <p class="muted">Vert : RAS · orange : signalé ou expiré · rouge : recherché, cible prioritaire, volé, faux ou inconnu. Touchez un point pour ouvrir le contrôle.</p>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
