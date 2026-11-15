<?php
declare(strict_types=1);
ob_start();
/**
 * Dossiers d'intérêt — file « ticketing » : onglets à compteurs, recherche et filtres, tri, pagination, tiroir d'aperçu,
 * modale de création. Les données arrivent déjà filtrées par le service (habilitation, accès nominatifs).
 * @var array<string,mixed> $page
 * @var array<string,array{label:string,hint:string,count:int}> $tabs
 * @var string $activeTab
 * @var array<string,mixed> $filters
 * @var array<string,string> $sorts
 * @var array<string,string> $types
 * @var array<string,string> $classifications
 * @var list<array{id:int,label:string}> $members
 * @var \App\Services\Sse\SseInterestActor $actor
 * @var array<string,mixed> $form
 */
$form = is_array($form ?? null) ? $form : ['modal' => '', 'errors' => [], 'old' => []];
require __DIR__ . '/interest/_helpers.php';
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $diH(asset_url('assets/css/sse-dossiers-interet.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $diH(asset_url('assets/js/sse-dossiers-interet.js')) . '"></script>';
$rows = $page['rows'];
$query = $page['query'];
$base = url('atak/sse/interet');
$canCreate = $actor->canWrite;
$diCaps = $page['capabilities'];
$link = static function (array $override = []) use ($base, $filters, $query): string {
    $params = array_filter([
        'onglet' => $filters['tab'] === '' ? 'tous' : $filters['tab'],
        'q' => $filters['q'], 'priorite' => $filters['priority'], 'type' => $filters['type'],
        'responsable' => $filters['assignee'] > 0 ? (string) $filters['assignee'] : ($filters['assignee'] === -1 ? 'aucun' : ''),
        'classification' => $filters['classification'], 'en_retard' => $filters['overdue'] ? '1' : '',
        'tri' => $query['sort'] !== 'recent' ? $query['sort'] : '', 'page' => $query['page'] > 1 ? (string) $query['page'] : '',
    ], static fn (mixed $v): bool => $v !== '' && $v !== null);
    foreach ($override as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }

    return $base . ($params !== [] ? '?' . http_build_query($params) : '');
};
$hasFilter = $filters['q'] !== '' || $filters['priority'] !== '' || $filters['type'] !== '' || $filters['assignee'] !== 0 || $filters['classification'] !== '' || $filters['overdue'];
$total = (int) $page['total'];
$activeMeta = $tabs[$activeTab] ?? null;
$mineValue = $filters['assignee'] === (int) ($actor->userId ?? -99) && $filters['assignee'] > 0 ? 'moi' : ($filters['assignee'] === -1 ? 'aucun' : ($filters['assignee'] > 0 ? (string) $filters['assignee'] : ''));
$resetOverrides = ['q' => null, 'priorite' => null, 'type' => null, 'responsable' => null, 'classification' => null, 'en_retard' => null, 'page' => null];
?>
<div class="di" data-di-root data-preview-base="<?= $diH($base) ?>" data-open-case="<?= (int) ($openCase ?? 0) ?>">
    <header class="di-head">
        <div class="di-head__text">
            <h1>Dossiers d’intérêt</h1>
            <p class="di-lead">Qualification, collecte et consolidation des sujets d’intérêt. Une hypothèse de travail n’est jamais une identité confirmée, et les traitements automatiques de Cerbère ne valent ni identification ni preuve.</p>
        </div>
        <?php if ($canCreate): ?>
            <div class="di-head__actions">
                <button type="button" class="di-btn di-btn--primary" data-di-open="di-create"><?= $diIcon('plus') ?><span>Ouvrir un dossier</span></button>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!empty($page['degraded']) && $canCreate): ?>
        <div class="di-alert di-alert--warn" role="status">
            <div>
                <strong>Mise à niveau de la base en attente</strong>
                <p>La file, la création et le changement d’état fonctionnent. Le responsable, l’échéance, la classification, la corbeille, l’historique détaillé et les éléments liés apparaîtront après la mise à jour : demandez à un administrateur de lancer l’étape « Dossiers d’intérêt SSE ».</p>
            </div>
        </div>
    <?php endif; ?>

    <nav class="di-tabs" aria-label="Files de dossiers">
        <?php foreach ($tabs as $key => $t): ?>
            <a class="di-tab<?= $activeTab === $key ? ' is-active' : '' ?>" href="<?= $diH($link(['onglet' => $key, 'page' => null])) ?>" <?= $activeTab === $key ? 'aria-current="page"' : '' ?> title="<?= $diH($t['hint']) ?>">
                <?= $diH($t['label']) ?><span class="di-count"><?= (int) $t['count'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <form class="di-toolbar" method="get" action="<?= $diH($base) ?>" role="search" data-di-toolbar <?= $hasFilter ? 'data-open' : '' ?>>
        <input type="hidden" name="onglet" value="<?= $diH($activeTab) ?>">
        <button type="button" class="di-btn di-filter-toggle" data-di-filter-toggle aria-expanded="<?= $hasFilter ? 'true' : 'false' ?>"><span>Filtres et recherche</span><?php if ($hasFilter): ?><span>(actifs)</span><?php endif; ?></button>
        <div class="di-toolbar__grid">
            <div class="di-field">
                <label for="di-q">Recherche</label>
                <input class="di-input" id="di-q" type="search" name="q" value="<?= $diH($filters['q']) ?>" placeholder="Référence, titre, alias, étiquette, motif…" autocomplete="off">
            </div>
            <div class="di-field">
                <label for="di-f-prio">Priorité</label>
                <select class="di-select" id="di-f-prio" name="priorite">
                    <option value="">Toutes</option>
                    <?php foreach (\App\Support\SseInterestCaseCatalog::PRIORITIES as $k => $p): ?>
                        <option value="<?= $diH($k) ?>" <?= $filters['priority'] === $k ? 'selected' : '' ?>><?= $diH($p['code'] . ' · ' . $p['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($diCaps['columns'])): ?>
                <div class="di-field">
                    <label for="di-f-type">Type</label>
                    <select class="di-select" id="di-f-type" name="type">
                        <option value="">Tous</option>
                        <?php foreach ($types as $k => $l): ?>
                            <option value="<?= $diH($k) ?>" <?= $filters['type'] === $k ? 'selected' : '' ?>><?= $diH($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="di-field">
                    <label for="di-f-assignee">Responsable</label>
                    <select class="di-select" id="di-f-assignee" name="responsable">
                        <option value="">Tous</option>
                        <?php if ($actor->userId !== null): ?><option value="moi" <?= $mineValue === 'moi' ? 'selected' : '' ?>>Moi</option><?php endif; ?>
                        <option value="aucun" <?= $mineValue === 'aucun' ? 'selected' : '' ?>>Sans responsable</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= (int) $m['id'] ?>" <?= $mineValue === (string) $m['id'] ? 'selected' : '' ?>><?= $diH($m['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="di-field">
                    <label for="di-f-class">Classification</label>
                    <select class="di-select" id="di-f-class" name="classification">
                        <option value="">Toutes</option>
                        <?php foreach ($classifications as $k => $l): ?>
                            <option value="<?= $diH($k) ?>" <?= $filters['classification'] === $k ? 'selected' : '' ?>><?= $diH($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="di-field">
                <label for="di-f-sort">Tri</label>
                <select class="di-select" id="di-f-sort" name="tri">
                    <?php foreach ($sorts as $k => $l): ?>
                        <option value="<?= $diH($k) ?>" <?= $query['sort'] === $k ? 'selected' : '' ?>><?= $diH($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="di-toolbar__actions di-toolbar__actions--more">
            <?php if (!empty($diCaps['columns'])): ?>
                <label class="di-check"><input type="checkbox" name="en_retard" value="1" <?= $filters['overdue'] ? 'checked' : '' ?>><span>Échéance dépassée</span></label>
            <?php endif; ?>
            <button type="submit" class="di-btn di-btn--primary">Appliquer</button>
            <?php if ($hasFilter): ?><a class="di-btn di-btn--ghost" href="<?= $diH($link($resetOverrides + ['tri' => null])) ?>">Réinitialiser</a><?php endif; ?>
        </div>
    </form>

    <section class="di-list" aria-labelledby="di-list-title">
        <h2 class="di-sr" id="di-list-title"><?= $diH($activeMeta['label'] ?? 'Tous les dossiers') ?></h2>
        <?php if ($rows === []): ?>
            <div class="di-empty">
                <?php if ($hasFilter): ?>
                    <h2>Aucun dossier ne correspond</h2>
                    <p>Aucun dossier de cet onglet ne répond à la recherche ou aux filtres. Élargissez la recherche ou réinitialisez les filtres.</p>
                    <div class="di-empty__actions"><a class="di-btn" href="<?= $diH($link($resetOverrides)) ?>">Réinitialiser les filtres</a></div>
                <?php elseif ($activeTab === 'corbeille'): ?>
                    <h2>La corbeille est vide</h2>
                    <p>Les dossiers supprimés apparaissent ici et restent restaurables pendant <?= \App\Support\SseInterestCaseCatalog::TRASH_DAYS ?> jours.</p>
                <?php else: ?>
                    <?php if ($activeTab === 'tous' || $activeTab === 'ouverts'): ?><h2>Aucun dossier d’intérêt ouvert</h2><?php else: ?><h2>Rien dans cet onglet</h2><?php endif; ?>
                    <p><?= $diH($activeMeta['hint'] ?? '') ?></p>
                    <p>Un dossier d’intérêt rassemble ce qu’on sait d’un sujet encore sans identité certaine : motif, observations, éléments liés et décisions.</p>
                    <div class="di-empty__actions">
                        <?php if ($canCreate): ?><button type="button" class="di-btn di-btn--primary" data-di-open="di-create"><?= $diIcon('plus') ?><span>Ouvrir un dossier</span></button><?php endif; ?>
                        <?php if ($activeTab !== 'ouverts'): ?><a class="di-btn" href="<?= $diH($link(['onglet' => 'ouverts', 'page' => null])) ?>">Voir les dossiers ouverts</a><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="di-table">
                <caption class="di-sr"><?= $diH($activeMeta['label'] ?? 'Dossiers') ?> : <?= $total ?> dossier<?= $total > 1 ? 's' : '' ?></caption>
                <thead>
                <tr>
                    <th scope="col">Dossier</th>
                    <th scope="col">État</th>
                    <th scope="col">Priorité</th>
                    <th scope="col" class="di-col-class">Classification</th>
                    <th scope="col" class="di-col-assignee">Responsable</th>
                    <th scope="col">Échéance</th>
                    <th scope="col">Activité</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $c): ?>
                    <?php $cid = (int) $c['id']; ?>
                    <tr data-case="<?= $cid ?>">
                        <td class="di-cell-title">
                            <a class="di-row__open" href="<?= $diH($base . '/' . $cid) ?>" data-di-preview="<?= $cid ?>" aria-label="Aperçu de <?= $diH($c['reference_code']) ?>">
                                <span class="di-row__title"><?= $diH($c['temporary_designation']) ?></span>
                                <span class="di-row__meta"><span class="di-mono"><?= $diH($c['reference_code']) ?></span><?= !empty($c['type_label']) ? ' · ' . $diH($c['type_label']) : '' ?><?= !empty($c['suspected_alias']) ? ' · alias ' . $diH($c['suspected_alias']) : '' ?></span>
                            </a>
                        </td>
                        <td class="di-cell-status"><?= $diStatus($c) ?></td>
                        <td class="di-cell-prio"><?= $diPrio($c) ?></td>
                        <td class="di-col-class"><?= $diClass($c) ?></td>
                        <td class="di-col-assignee"><?= !empty($c['assignee_label']) ? $diH($c['assignee_label']) : '<span class="di-sub">Non attribué</span>' ?></td>
                        <td class="di-cell-due is-num"><?php if (!empty($c['due_at'])): ?><span class="<?= !empty($c['is_overdue']) ? 'di-late' : '' ?>"><?= !empty($c['is_overdue']) ? 'En retard · ' : '' ?><?= $diH($diDate($c['due_at'], 'd/m/Y')) ?></span><?php else: ?><span class="di-sub">Aucune</span><?php endif; ?></td>
                        <td class="di-cell-activity is-num"><span class="di-sub">Mis à jour le </span><?= $diH($diDate($c['updated_at'] ?? '', 'd/m/Y')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="di-pager">
                <span class="di-sub"><?= $total ?> dossier<?= $total > 1 ? 's' : '' ?> · page <?= (int) $page['page'] ?> sur <?= (int) $page['pages'] ?></span>
                <?php if ((int) $page['pages'] > 1): ?>
                    <span class="di-pager__nav">
                        <?php if ((int) $page['page'] > 1): ?><a class="di-btn" href="<?= $diH($link(['page' => (int) $page['page'] - 1 > 1 ? (string) ((int) $page['page'] - 1) : null])) ?>">Page précédente</a><?php endif; ?>
                        <?php if ((int) $page['page'] < (int) $page['pages']): ?><a class="di-btn" href="<?= $diH($link(['page' => (string) ((int) $page['page'] + 1)])) ?>">Afficher la suite</a><?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="di-drawer-scrim" data-di-scrim hidden></div>
    <aside class="di-drawer" data-di-drawer role="dialog" aria-modal="true" aria-labelledby="di-drawer-title" hidden>
        <div class="di-drawer__head">
            <div>
                <h2 id="di-drawer-title" data-di-d-title>Aperçu du dossier</h2>
                <p class="di-sub di-mono" data-di-d-ref></p>
            </div>
            <button type="button" class="di-close" data-di-drawer-close aria-label="Fermer l’aperçu"><?= $diIcon('close') ?></button>
        </div>
        <div class="di-drawer__body" data-di-d-body>
            <p class="di-sub" data-di-d-loading>Chargement…</p>
        </div>
        <div class="di-drawer__foot">
            <a class="di-btn di-btn--primary" href="<?= $diH($base) ?>" data-di-d-open>Ouvrir la fiche</a>
            <button type="button" class="di-btn di-btn--ghost" data-di-drawer-close>Fermer</button>
        </div>
    </aside>

    <?php if ($canCreate): ?>
        <?php require __DIR__ . '/interest/_dialog_create.php'; ?>
    <?php endif; ?>
    <div hidden data-di-labels>
        <span data-k="status">État</span><span data-k="priority">Priorité</span><span data-k="class">Classification</span><span data-k="type">Type</span>
        <span data-k="assignee">Responsable</span><span data-k="due">Échéance</span><span data-k="links">Éléments liés</span><span data-k="reason">Motif d’ouverture</span>
        <span data-k="description">Description</span><span data-k="none">Non renseigné</span><span data-k="late">En retard</span><span data-k="error">Cet aperçu n’a pas pu être chargé.</span>
        <span data-k="nolink">Aucun élément lié</span><span data-k="searching">Recherche…</span><span data-k="noresult">Aucun résultat</span><span data-k="remove">Retirer</span>
        <span data-k="draftsaved">Brouillon conservé sur cet appareil</span><span data-k="required">Ce champ est obligatoire.</span><span data-k="reasonreq">obligatoire</span><span data-k="reasonopt">facultatif</span>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
