<?php
declare(strict_types=1);
/**
 * Maître du jeu : tableau de bord, onglets (liste surveillée, fiches terrain, outils), création en modale,
 * détail et retrait en tiroir. Rendu par maitre_jeu.php ; testable seul (aucune dépendance à la coque).
 *
 * @var list<array<string,mixed>> $entries
 * @var list<array<string,mixed>> $persons
 * @var string $searchQuery
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$searchQuery = trim((string) ($searchQuery ?? ''));
$entries = is_array($entries ?? null) ? $entries : [];
$persons = is_array($persons ?? null) ? $persons : [];
$entriesCount = count($entries);
$personsCount = count($persons);
$priorityCount = 0;
$lastAdded = '';
foreach ($entries as $e) {
    if (in_array((string) ($e['threat_level'] ?? ''), ['prioritaire', 'hvt'], true)) {
        $priorityCount++;
    }
    $ts = strtotime((string) ($e['created_at'] ?? ''));
    if ($ts !== false && $lastAdded === '') {
        $lastAdded = date('d/m/Y', $ts);
    }
}
$listUrl = url('atak/sse/maitre-jeu');
$levelTone = static fn (string $l): string => match ($l) {
    'hvt' => 'badge--red',
    'prioritaire' => 'badge--amber',
    default => 'badge--gray',
};
$detail = [];
foreach ($entries as $e) {
    $ts = strtotime((string) ($e['created_at'] ?? ''));
    $detail[(string) (int) ($e['id'] ?? 0)] = [
        'name' => (string) ($e['display_name'] ?? ''),
        'alias' => (string) ($e['alias'] ?? ''),
        'levelLabel' => (string) ($e['threat_level_label'] ?? ''),
        'tone' => $levelTone((string) ($e['threat_level'] ?? '')),
        'notes' => (string) ($e['notes'] ?? ''),
        'origin' => !empty($e['game_ref']) ? 'game' : 'site',
        'docs' => implode(', ', (array) ($e['known_docs'] ?? [])),
        'added' => $ts !== false ? date('d/m/Y', $ts) : '',
        'removeUrl' => url('atak/sse/croisements/watchlist/' . (int) ($e['id'] ?? 0) . '/retirer'),
    ];
}
$tools = [
    ['Lore des sessions', 'Personnes, informations connues ou cachées et phases d’une session de jeu.', 'atak/sse/fichiers/lore'],
    ['Générer du contenu', 'Peupler la communauté d’un réseau fictif, par lots supprimables.', 'atak/sse/fichiers/generer'],
    ['Profils IA', 'Relier identité, papiers et comportement aux IA du jeu.', 'atak/sse/papiers/profils-ia'],
    ['Préparation de mission', 'Population, recherchés et HVT d’une opération.', 'atak/sse/papiers/preparation'],
    ['Campagnes', 'Personnages et pièces partagés entre communautés.', 'atak/sse/campagnes'],
    ['Investigations', 'Relier les fiches dans une toile si l’histoire croise plusieurs éléments.', 'atak/sse/toiles'],
];
?>
<div class="page-heading mj-heading">
    <div>
        <h1>Maître du jeu</h1>
        <p>Préparez les identités que le terminal SEEK reconnaît quand un opérateur lance QUERY.</p>
    </div>
    <div class="mj-actions">
        <button class="btn" type="button" data-mj-open="mj-create">Nouvelle identité</button>
        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/identites')) ?>">Registre des identités</a>
    </div>
</div>

<div class="metrics-grid" aria-label="État de la liste">
    <div class="metric">
        <div class="metric-label">Identités surveillées</div>
        <div class="metric-value"><?= $entriesCount ?></div>
        <div class="metric-detail">Lues par QUERY</div>
    </div>
    <div class="metric">
        <div class="metric-label">Prioritaires</div>
        <div class="metric-value"><?= $priorityCount ?></div>
        <div class="metric-detail">Prioritaires ou HVT</div>
    </div>
    <div class="metric">
        <div class="metric-label">Fiches terrain</div>
        <div class="metric-value"><?= $personsCount ?></div>
        <div class="metric-detail">Les plus récentes</div>
    </div>
    <div class="metric">
        <div class="metric-label">Dernier ajout</div>
        <div class="metric-value mj-metric-date"><?= $h($lastAdded !== '' ? $lastAdded : '—') ?></div>
        <div class="metric-detail">Liste surveillée</div>
    </div>
</div>

<div class="mj-tabs" role="tablist" aria-label="Sections du poste maître du jeu" data-mj-tabs>
    <button class="mj-tab" type="button" role="tab" id="mj-tab-liste" aria-controls="mj-liste" aria-selected="true">Liste surveillée <span class="mj-tab__n"><?= $entriesCount ?></span></button>
    <button class="mj-tab" type="button" role="tab" id="mj-tab-fiches" aria-controls="mj-fiches" aria-selected="false" tabindex="-1">Fiches terrain <span class="mj-tab__n"><?= $personsCount ?></span></button>
    <button class="mj-tab" type="button" role="tab" id="mj-tab-outils" aria-controls="mj-outils" aria-selected="false" tabindex="-1">Préparer le jeu</button>
</div>

<section class="panel mj-panel" id="mj-liste" role="tabpanel" aria-labelledby="mj-tab-liste">
    <div class="mj-panel__head">
        <h2>Liste surveillée</h2>
        <p>Nom, alias et motif s’affichent sur le terminal SEEK quand un opérateur interroge la même personne.</p>
    </div>
    <?php if ($entries !== [] || $searchQuery !== ''): ?>
    <div class="mj-filters">
        <form class="mj-search" method="get" action="<?= $h($listUrl) ?>#mj-liste" role="search">
            <label for="mj-q">Rechercher</label>
            <input id="mj-q" name="q" type="search" value="<?= $h($searchQuery) ?>" placeholder="Nom, alias, motif" autocomplete="off">
            <button class="btn btn--ghost" type="submit">Rechercher</button>
            <?php if ($searchQuery !== ''): ?>
                <a class="btn btn--ghost" href="<?= $h($listUrl) ?>#mj-liste">Effacer</a>
            <?php endif; ?>
        </form>
        <div class="mj-level">
            <label for="mj-level">Niveau</label>
            <select id="mj-level" data-mj-level>
                <option value="">Tous</option>
                <option value="surveillance">Surveillance</option>
                <option value="prioritaire">Personne prioritaire</option>
                <option value="hvt">Cible prioritaire (HVT)</option>
            </select>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($entries === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <strong><?= $searchQuery !== '' ? 'Aucun résultat' : 'La liste est vide' ?></strong>
                <p><?= $searchQuery !== ''
                    ? 'Aucune identité surveillée ne correspond à cette recherche.'
                    : 'QUERY restera muet sur le terrain tant qu’aucune identité n’est enregistrée.' ?></p>
                <?php if ($searchQuery === ''): ?>
                    <button class="btn" type="button" data-mj-open="mj-create">Nouvelle identité</button>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table data-mj-entries>
                <thead>
                <tr>
                    <th>Identité</th>
                    <th>Niveau</th>
                    <th>Motif</th>
                    <th class="sse-col-actions"><span class="sr-only">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $e):
                    $eid = (int) ($e['id'] ?? 0);
                    $level = (string) ($e['threat_level'] ?? '');
                    $notes = trim((string) ($e['notes'] ?? ''));
                    ?>
                    <tr data-level="<?= $h($level) ?>">
                        <td>
                            <span class="record-name"><?= $h($e['display_name'] ?? 'Entrée') ?></span>
                            <?php if (!empty($e['alias'])): ?>
                                <span class="record-sub">alias « <?= $h($e['alias']) ?> »</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $h($levelTone($level)) ?>"><?= $h($e['threat_level_label'] ?? '') ?></span></td>
                        <td class="mj-notes"><span class="mj-clamp"><?= $h($notes !== '' ? $notes : '—') ?></span></td>
                        <td class="sse-col-actions">
                            <button class="btn btn--ghost btn--sm" type="button" data-mj-detail="<?= $eid ?>" aria-label="Détails de <?= $h($e['display_name'] ?? 'l’entrée') ?>">Détails</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="mj-none" data-mj-none hidden>Aucune identité à ce niveau.</p>
        <div class="mj-more"><button class="btn btn--ghost" type="button" data-mj-more hidden>Afficher la suite</button><span class="mj-count" data-mj-count></span></div>
    <?php endif; ?>
</section>

<section class="panel mj-panel" id="mj-fiches" role="tabpanel" aria-labelledby="mj-tab-fiches" hidden>
    <div class="mj-panel__head">
        <h2>Fiches terrain</h2>
        <p>Identités déjà remontées depuis le terminal, les plus récentes d’abord.</p>
    </div>
    <?php if ($persons === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <strong>Aucune fiche terrain</strong>
                <p>Les identités transmises depuis le terminal apparaîtront ici.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="mj-filters">
            <div class="mj-level">
                <label for="mj-pf">Filtrer</label>
                <input id="mj-pf" type="search" data-mj-filter="mj-persons" placeholder="Nom, alias, statut" autocomplete="off">
            </div>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/identites')) ?>">Ouvrir le registre</a>
        </div>
        <div class="table-wrap">
            <table id="mj-persons">
                <thead>
                <tr>
                    <th>Identité</th>
                    <th>Alias</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($persons as $p):
                    $alias = trim((string) ($p['alias'] ?? ''));
                    ?>
                    <tr>
                        <td><a class="record-name mj-link" href="<?= $h(url('atak/sse/identites/' . (int) ($p['id'] ?? 0))) ?>"><?= $h($p['display_name'] ?? 'Fiche') ?></a></td>
                        <td class="muted"><?= $h($alias !== '' ? $alias : '—') ?></td>
                        <td><span class="badge badge--gray"><?= $h($p['status_label'] ?? $p['status'] ?? '') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="mj-none" data-mj-none hidden>Aucune fiche ne correspond.</p>
        <div class="mj-more"><button class="btn btn--ghost" type="button" data-mj-more hidden>Afficher la suite</button><span class="mj-count" data-mj-count></span></div>
    <?php endif; ?>
</section>

<section class="panel mj-panel" id="mj-outils" role="tabpanel" aria-labelledby="mj-tab-outils" hidden>
    <div class="mj-panel__head">
        <h2>Préparer le jeu</h2>
        <p>Les autres outils du bureau pour monter une session, chacun sur sa propre page.</p>
    </div>
    <ul class="mj-tools">
        <?php foreach ($tools as [$label, $hint, $path]): ?>
            <li>
                <a class="mj-tool" href="<?= $h(url($path)) ?>">
                    <strong><?= $h($label) ?></strong>
                    <span><?= $h($hint) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<dialog class="mj-dialog" id="mj-create" aria-labelledby="mj-create-title">
    <form method="post" action="<?= $h(url('atak/sse/croisements/watchlist')) ?>" class="mj-dialog__card">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="return_to" value="maitre-jeu">
        <header class="mj-dialog__head">
            <div>
                <h2 id="mj-create-title">Nouvelle identité</h2>
                <p>Reliez ensuite les fiches dans une investigation si l’histoire doit croiser plusieurs éléments.</p>
            </div>
            <button class="mj-close" type="button" data-mj-close aria-label="Fermer">×</button>
        </header>
        <div class="mj-dialog__body">
            <div class="mj-grid">
                <div>
                    <label for="mj-last_name">Nom</label>
                    <input id="mj-last_name" name="last_name" type="text" required autocomplete="off">
                </div>
                <div>
                    <label for="mj-first_name">Prénom</label>
                    <input id="mj-first_name" name="first_name" type="text" autocomplete="off">
                </div>
            </div>
            <div class="mj-grid">
                <div>
                    <label for="mj-alias">Alias</label>
                    <input id="mj-alias" name="alias" type="text" autocomplete="off" placeholder="Nom de guerre, indicatif">
                </div>
                <div>
                    <label for="mj-threat_level">Niveau</label>
                    <select id="mj-threat_level" name="threat_level">
                        <option value="surveillance">Surveillance</option>
                        <option value="prioritaire">Personne prioritaire</option>
                    </select>
                </div>
            </div>
            <label for="mj-notes">Histoire / motif</label>
            <textarea id="mj-notes" name="notes" rows="4" placeholder="Pourquoi cette personne est suivie, liens connus, consigne de jeu"></textarea>
        </div>
        <footer class="mj-dialog__foot">
            <button class="btn btn--ghost" type="button" data-mj-close>Annuler</button>
            <button class="btn" type="submit">Enregistrer pour QUERY</button>
        </footer>
    </form>
</dialog>

<dialog class="mj-dialog mj-drawer" id="mj-drawer" aria-labelledby="mj-drawer-title">
    <div class="mj-dialog__card">
        <header class="mj-dialog__head">
            <div>
                <h2 id="mj-drawer-title" data-mj-d="name"></h2>
                <p data-mj-d="alias"></p>
            </div>
            <button class="mj-close" type="button" data-mj-close aria-label="Fermer">×</button>
        </header>
        <div class="mj-dialog__body">
            <p><span class="badge" data-mj-d="levelLabel"></span></p>
            <h3>Histoire / motif</h3>
            <p class="mj-story" data-mj-d="notes"></p>
            <dl class="mj-facts">
                <div><dt>Origine</dt><dd data-mj-d="origin"></dd></div>
                <div><dt>Ajoutée le</dt><dd data-mj-d="added"></dd></div>
                <div data-mj-docs><dt>Papiers connus</dt><dd data-mj-d="docs"></dd></div>
            </dl>
        </div>
        <p hidden>
            <span data-mj-t="game">Remonté du jeu</span>
            <span data-mj-t="site">Saisi sur le site</span>
            <span data-mj-t="nonotes">Aucun motif renseigné.</span>
            <span data-mj-t="alias">alias</span>
        </p>
        <footer class="mj-dialog__foot">
            <button class="btn btn--danger" type="button" data-mj-remove>Retirer de la liste</button>
            <button class="btn btn--ghost" type="button" data-mj-close>Fermer</button>
        </footer>
    </div>
</dialog>

<dialog class="mj-dialog mj-dialog--small" id="mj-remove" aria-labelledby="mj-remove-title">
    <form method="post" action="#" class="mj-dialog__card" data-mj-remove-form>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="return_to" value="maitre-jeu">
        <header class="mj-dialog__head">
            <div>
                <h2 id="mj-remove-title">Retirer de la liste ?</h2>
                <p><strong data-mj-remove-name></strong> ne sera plus reconnu par QUERY sur le terrain.</p>
            </div>
        </header>
        <footer class="mj-dialog__foot">
            <button class="btn btn--ghost" type="button" data-mj-close>Annuler</button>
            <button class="btn btn--danger" type="submit">Retirer</button>
        </footer>
    </form>
</dialog>

<script type="application/json" id="mj-data"><?= json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_FORCE_OBJECT) ?></script>
