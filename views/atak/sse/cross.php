<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array{person:array,matches:list}> $matches */
/** @var list<array<string,mixed>> $entries */
/** @var bool $canManage */
$matchRows = 0;
foreach ($matches as $row) {
    $matchRows += count($row['matches'] ?? []);
}
$searchQuery = trim((string) ($searchQuery ?? ''));
$entriesCount = count($entries);
?>
<div class="breadcrumb">
    Athena / SSE / Renseignement /
    <strong>Croisements</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Surveillance // Correspondances</div>
        <h1>Croisements</h1>
        <p>
            Correspondances probables entre fiches terrain et listes de surveillance
            de la communauté.
        </p>
    </div>
    <div class="page-reference">
        <strong>Vue // Croisements</strong>
        Réf. ATH-SSE-CROISEMENTS
        <?php if ($canManage): ?>
            <div style="margin-top:.55rem">
                <button type="button" class="btn" data-sse-modal-open="sse-modal-watchlist-add">
                    Ajouter une entrée
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<form class="sse-toolbar-search" method="get" action="<?= $h(url('atak/sse/croisements')) ?>" role="search">
    <label for="cross-q">Rechercher</label>
    <div class="case-search-control">
        <input
            id="cross-q"
            name="q"
            type="search"
            value="<?= $h($searchQuery) ?>"
            placeholder="Nom, prénom, alias, notes, motif…"
        >
        <button type="submit" aria-label="Lancer la recherche">→</button>
    </div>
    <?php if ($searchQuery !== ''): ?>
        <a class="link" href="<?= $h(url('atak/sse/croisements')) ?>">Effacer</a>
    <?php endif; ?>
</form>

<div class="metrics-grid">
    <div class="metric">
        <div class="metric-label">Correspondances</div>
        <div class="metric-value"><?= $h(str_pad((string) $matchRows, 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Détectées</div>
    </div>
    <div class="metric">
        <div class="metric-label">Liste active</div>
        <div class="metric-value"><?= $h(str_pad((string) $entriesCount, 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Entrées surveillées</div>
    </div>
    <div class="metric">
        <div class="metric-label">Gestion</div>
        <div class="metric-value"><?= $canManage ? 'Oui' : 'Non' ?></div>
        <div class="metric-detail">Édition listes</div>
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
            <span class="panel-index">03.01</span>
            Correspondances détectées
        </div>
        <div class="panel-meta">
            <?= $matchRows ?> résultat<?= $matchRows > 1 ? 's' : '' ?>
            · seuil <?= (int) \App\Services\Sse\SseCrossMatchService::MATCH_THRESHOLD ?> %
        </div>
    </div>
    <?php if ($matches === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">—</div>
                <strong><?= $searchQuery !== '' ? 'Aucun résultat' : 'Aucune correspondance' ?></strong>
                <p><?= $searchQuery !== ''
                    ? 'Aucune correspondance ne correspond à cette recherche.'
                    : 'Ajoutez des entrées surveillées, puis laissez le moteur comparer aux fiches terrain.' ?></p>
                <?php if ($canManage && $entriesCount < 1): ?>
                    <button type="button" class="btn" data-sse-modal-open="sse-modal-watchlist-add">
                        Ajouter une première entrée
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <p class="sse-note" style="padding: 0 1rem;">
            Rapprochement nominatif sur nom, prénom et alias.
            Un score élevé n’est pas une identification : il appelle une confirmation du commandement.
        </p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Fiche terrain</th>
                    <th>Entrée surveillée</th>
                    <th>Similarité</th>
                    <th>Motif</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($matches as $row): ?>
                    <?php foreach ($row['matches'] as $m): ?>
                        <tr>
                            <td><span class="record-name"><?= $h($row['person']['display_name'] ?? '') ?></span></td>
                            <td>
                                <span class="record-name"><?= $h($m['entry']['display_name'] ?? '') ?></span>
                                <span class="record-sub"><?= $h($m['entry']['threat_level_label'] ?? '') ?></span>
                            </td>
                            <?php
                                $sc = (int) ($m['score'] ?? 0);
                                $scClass = $sc >= 85 ? 'is-alert' : ($sc >= 70 ? 'is-warn' : '');
                            ?>
                            <td>
                                <span class="sse-score-cell">
                                    <span class="sse-gauge <?= $h($scClass) ?>">
                                        <span style="width: <?= $h((string) min(100, max(0, $sc))) ?>%"></span>
                                    </span>
                                    <span class="sse-sample-score"><?= $sc ?>%</span>
                                </span>
                            </td>
                            <td><?= $h($m['reason'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">03.02</span>
            Liste de surveillance active
        </div>
        <div class="panel-meta sse-panel-meta-actions">
            <span><?= $entriesCount ?> entrée<?= $entriesCount > 1 ? 's' : '' ?></span>
            <?php if ($canManage): ?>
                <button type="button" class="btn btn--ghost btn--sm" data-sse-modal-open="sse-modal-watchlist-add">
                    Ajouter
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($entries === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">LST</div>
                <strong><?= $searchQuery !== '' ? 'Aucun résultat' : 'Liste vide' ?></strong>
                <p><?= $searchQuery !== ''
                    ? 'Aucune entrée surveillée pour cette recherche.'
                    : 'Les identités à surveiller apparaissent ici après enregistrement.' ?></p>
                <?php if ($canManage): ?>
                    <button type="button" class="btn" data-sse-modal-open="sse-modal-watchlist-add">
                        Ajouter une entrée
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Identité</th>
                    <th>Niveau</th>
                    <th>Motif</th>
                    <?php if ($canManage): ?><th class="sse-col-actions">Actions</th><?php endif; ?>
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
                        <td><span class="badge"><?= $h($e['threat_level_label']) ?></span></td>
                        <td class="muted"><?= $h($e['notes'] ?? '—') ?></td>
                        <?php if ($canManage): ?>
                            <td class="sse-col-actions">
                                <button
                                    type="button"
                                    class="btn btn--ghost btn--sm"
                                    data-sse-modal-open="sse-modal-watchlist-remove"
                                    data-watchlist-id="<?= $eid ?>"
                                    data-watchlist-name="<?= $h($ename) ?>"
                                >
                                    Retirer
                                </button>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($canManage): ?>
<dialog class="sse-modal" id="sse-modal-watchlist-add">
    <form method="post" action="<?= $h(url('atak/sse/croisements/watchlist')) ?>" class="sse-modal__card">
        <?= \App\Core\Csrf::field() ?>
        <header class="sse-modal__head">
            <div>
                <p class="sse-modal__kicker">Surveillance</p>
                <h2>Ajouter une entrée surveillée</h2>
            </div>
            <button type="button" class="sse-modal__close" data-sse-modal-close aria-label="Fermer">×</button>
        </header>
        <div class="sse-modal__body">
            <div class="grid-2">
                <div>
                    <label for="wl-last_name">Nom</label>
                    <input id="wl-last_name" name="last_name" type="text" required autocomplete="off">
                </div>
                <div>
                    <label for="wl-first_name">Prénom</label>
                    <input id="wl-first_name" name="first_name" type="text" autocomplete="off">
                </div>
            </div>
            <p class="muted" style="margin:0.35rem 0 0.85rem">
                Le croisement compare aussi l’ordre inverse (nom ↔ prénom) et les alias
                complets du type « Khalil Jawadi », même si la fiche terrain n’a pas
                encore séparé nom et prénom.
            </p>
            <label for="wl-alias">Alias</label>
            <input id="wl-alias" name="alias" type="text" autocomplete="off">
            <label for="wl-threat_level">Niveau</label>
            <select id="wl-threat_level" name="threat_level">
                <option value="surveillance">Surveillance</option>
                <option value="prioritaire">Personne prioritaire</option>
            </select>
            <label for="wl-notes">Motif</label>
            <textarea id="wl-notes" name="notes" rows="4" placeholder="Pourquoi cette personne est suivie…"></textarea>
        </div>
        <footer class="sse-modal__foot">
            <button type="button" class="btn btn--ghost" data-sse-modal-close>Annuler</button>
            <button class="btn" type="submit">Enregistrer</button>
        </footer>
    </form>
</dialog>

<dialog class="sse-modal" id="sse-modal-watchlist-remove">
    <form method="post" action="#" class="sse-modal__card" id="sse-watchlist-remove-form">
        <?= \App\Core\Csrf::field() ?>
        <header class="sse-modal__head">
            <div>
                <p class="sse-modal__kicker">Surveillance</p>
                <h2>Retirer de la liste</h2>
            </div>
            <button type="button" class="sse-modal__close" data-sse-modal-close aria-label="Fermer">×</button>
        </header>
        <div class="sse-modal__body">
            <p>
                Retirer <strong id="sse-watchlist-remove-name">cette entrée</strong> de la liste
                de surveillance active&nbsp;? Les correspondances déjà détectées disparaîtront
                au prochain affichage.
            </p>
        </div>
        <footer class="sse-modal__foot">
            <button type="button" class="btn btn--ghost" data-sse-modal-close>Annuler</button>
            <button class="btn" type="submit">Retirer</button>
        </footer>
    </form>
</dialog>
<script>
window.SSE_CROSS = {
  removeUrlTpl: <?= json_encode(url('atak/sse/croisements/watchlist/__ID__/retirer'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>
};
</script>
<?php endif; ?>

<?php
$sseContent = ob_get_clean();
$sseExtraScripts = '<script src="' . htmlspecialchars(asset_url('assets/js/sse-case-modals.js'), ENT_QUOTES, 'UTF-8') . '?v=202608162340"></script>'
    . '<script src="' . htmlspecialchars(asset_url('assets/js/sse-cross-modals.js'), ENT_QUOTES, 'UTF-8') . '?v=202608162340"></script>';
require __DIR__ . '/_layout.php';
