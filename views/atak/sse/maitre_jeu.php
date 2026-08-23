<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $entries */
/** @var list<array<string,mixed>> $persons */
/** @var bool $canManage */
$searchQuery = trim((string) ($searchQuery ?? ''));
$entriesCount = count($entries);
$personsCount = count($persons);
?>
<div class="breadcrumb">
    Athena / SSE / Pilotage /
    <strong>Maître du jeu</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Scénario // SEEK Query</div>
        <h1>Maître du jeu</h1>
        <p>
            Préparez les identités surveillées et le rôleplay associé. Le bouton QUERY
            du terminal SEEK interroge ce catalogue réel : il n’invente plus de
            correspondance.
        </p>
    </div>
    <div class="page-reference">
        <strong>Vue // Maître du jeu</strong>
        Réf. ATH-SSE-MJ
    </div>
</div>

<div class="metrics-grid">
    <div class="metric">
        <div class="metric-label">Identités surveillées</div>
        <div class="metric-value"><?= $h(str_pad((string) $entriesCount, 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Lues par QUERY</div>
    </div>
    <div class="metric">
        <div class="metric-label">Fiches terrain</div>
        <div class="metric-value"><?= $h(str_pad((string) $personsCount, 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Registre récent</div>
    </div>
    <div class="metric">
        <div class="metric-label">Liens</div>
        <div class="metric-value"><a class="link" href="<?= $h(url('atak/sse/toiles')) ?>">Investigations</a></div>
        <div class="metric-detail">Toiles relationnelles</div>
    </div>
    <div class="metric">
        <div class="metric-label">Horodatage</div>
        <div class="metric-value"><?= $h(date('H:i')) ?></div>
        <div class="metric-detail">Heure locale</div>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">26.01</span>
            Nouvelle identité de scénario
        </div>
        <div class="panel-meta">Liste de surveillance // QUERY</div>
    </div>
    <p class="muted">
        Nom, alias et motif apparaissent sur le terminal SEEK lorsqu’un opérateur
        interroge la même personne. Reliez ensuite les fiches dans une investigation
        si l’histoire doit croiser plusieurs éléments.
    </p>
    <form method="post" action="<?= $h(url('atak/sse/croisements/watchlist')) ?>" class="sse-form">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="return_to" value="maitre-jeu">
        <div class="grid-2">
            <div>
                <label for="mj-last_name">Nom</label>
                <input id="mj-last_name" name="last_name" type="text" required autocomplete="off">
            </div>
            <div>
                <label for="mj-first_name">Prénom</label>
                <input id="mj-first_name" name="first_name" type="text" autocomplete="off">
            </div>
        </div>
        <label for="mj-alias">Alias</label>
        <input id="mj-alias" name="alias" type="text" autocomplete="off" placeholder="Nom de guerre, indicatif…">
        <label for="mj-threat_level">Niveau</label>
        <select id="mj-threat_level" name="threat_level">
            <option value="surveillance">Surveillance</option>
            <option value="prioritaire">Personne prioritaire</option>
        </select>
        <label for="mj-notes">Histoire / motif</label>
        <textarea id="mj-notes" name="notes" rows="4" placeholder="Pourquoi cette personne est suivie, liens connus, consigne de jeu…"></textarea>
        <footer style="margin-top:1rem">
            <button class="btn" type="submit">Enregistrer pour QUERY</button>
        </footer>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">26.02</span>
            Catalogue lu par le terminal
        </div>
        <div class="panel-meta"><?= $entriesCount ?> entrée<?= $entriesCount > 1 ? 's' : '' ?></div>
    </div>
    <form class="sse-toolbar-search" method="get" action="<?= $h(url('atak/sse/maitre-jeu')) ?>" role="search">
        <label for="mj-q">Rechercher</label>
        <div class="case-search-control">
            <input id="mj-q" name="q" type="search" value="<?= $h($searchQuery) ?>" placeholder="Nom, alias, motif…">
            <button type="submit" aria-label="Lancer la recherche">→</button>
        </div>
        <?php if ($searchQuery !== ''): ?>
            <a class="link" href="<?= $h(url('atak/sse/maitre-jeu')) ?>">Effacer</a>
        <?php endif; ?>
    </form>
    <?php if ($entries === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">MJ</div>
                <strong><?= $searchQuery !== '' ? 'Aucun résultat' : 'Catalogue vide' ?></strong>
                <p>
                    <?= $searchQuery !== ''
                        ? 'Aucune identité surveillée pour cette recherche.'
                        : 'Ajoutez une identité ci-dessus : QUERY sur le terrain restera muet tant que le catalogue est vide.' ?>
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Identité</th>
                    <th>Niveau</th>
                    <th>Histoire / motif</th>
                    <th class="sse-col-actions">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $e):
                    $eid = (int) ($e['id'] ?? 0);
                    $ename = (string) ($e['display_name'] ?? 'Entrée');
                    ?>
                    <tr>
                        <td>
                            <span class="record-name"><?= $h($ename) ?></span>
                            <?php if (!empty($e['alias'])): ?>
                                <span class="record-sub">alias « <?= $h($e['alias']) ?> »</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge"><?= $h($e['threat_level_label'] ?? '') ?></span></td>
                        <td class="muted"><?= $h($e['notes'] ?? '—') ?></td>
                        <td class="sse-col-actions">
                            <form method="post" action="<?= $h(url('atak/sse/croisements/watchlist/' . $eid . '/retirer')) ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="return_to" value="maitre-jeu">
                                <button class="btn btn--ghost btn--sm" type="submit">Retirer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">26.03</span>
            Fiches déjà remontées du terrain
        </div>
        <div class="panel-meta">
            <a class="link" href="<?= $h(url('atak/sse/identites')) ?>">Ouvrir le registre</a>
        </div>
    </div>
    <?php if ($persons === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">ID</div>
                <strong>Aucune fiche terrain</strong>
                <p>Les identités transmises depuis le terminal apparaîtront ici.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Identité</th>
                    <th>Alias</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($persons as $p):
                    $pid = (int) ($p['id'] ?? 0);
                    ?>
                    <tr>
                        <td>
                            <a class="record-name" href="<?= $h(url('atak/sse/identites/' . $pid)) ?>">
                                <?= $h($p['display_name'] ?? 'Fiche') ?>
                            </a>
                        </td>
                        <td class="muted"><?= $h($p['alias'] ?? '—') ?></td>
                        <td><span class="badge"><?= $h($p['status_label'] ?? $p['status'] ?? '') ?></span></td>
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
