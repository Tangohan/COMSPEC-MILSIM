<?php
/** @var list<array<string, mixed>> $rows */
/** @var int $matching */
/** @var array<string, int> $counts */
/** @var array{play: int, total_m: int, reports: int} $totals */
/** @var int $players */
/** @var list<array{id: int, name: string, n: int}> $tenants */
/** @var string $search */
/** @var int $tenantFilter */
/** @var string $stateFilter */
/** @var string $sort */
/** @var list<array<string, mixed>> $actions */
/** @var bool $tablesReady */
/** @var array{tenant: bool, base: string, home: string, home_label: string, by: string} $fleetScope */
/** @var array{atak: int, steam: int, no_account: int, denied: int} $kinds */
/** @var array<string, string> $planOptions */
/** @var string $typeFilter */
/** @var string $planFilter */
/** @var bool $reverse */

use App\Repositories\AtakAdminControlRepository as Fleet;
use App\Services\Community\TenantTypeConfig;
use App\Support\AtakFleetLink;
use App\Support\PlayerMetrics;
use App\Support\UtcDateTime;

$rows = is_array($rows ?? null) ? $rows : [];
$counts = is_array($counts ?? null) ? $counts : [];
$totals = is_array($totals ?? null) ? $totals : ['play' => 0, 'total_m' => 0, 'reports' => 0];
$tenants = is_array($tenants ?? null) ? $tenants : [];
$actions = is_array($actions ?? null) ? $actions : [];
$search = (string) ($search ?? '');
$tenantFilter = (int) ($tenantFilter ?? 0);
$stateFilter = (string) ($stateFilter ?? '');
$sort = (string) ($sort ?? 'recent');
$reverse = !empty($reverse);
$typeFilter = (string) ($typeFilter ?? '');
$planFilter = (string) ($planFilter ?? '');
$planOptions = is_array($planOptions ?? null) ? $planOptions : [];
$kinds = is_array($kinds ?? null) ? $kinds : ['atak' => (int) ($counts[''] ?? 0), 'steam' => 0, 'no_account' => 0, 'denied' => 0];

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$csrf = \App\Core\Csrf::token();
// Même page pour l'administration de la plateforme (toutes communautés) et la gestion d'une communauté (ses membres).
$fs = is_array($fleetScope ?? null) ? $fleetScope : ['tenant' => false, 'base' => 'admin/parc-atak', 'home' => 'admin', 'home_label' => 'Administration du site'];
$inTenant = !empty($fs['tenant']);
$base = url((string) $fs['base']);
$pageTitle = $inTenant ? 'Parc ATAK des membres' : 'Parc ATAK des joueurs';

$listUrl = static function (array $over = []) use ($base, $search, $tenantFilter, $stateFilter, $sort, $reverse, $typeFilter, $planFilter): string {
    $p = array_merge([
        'q' => $search, 'communaute' => $tenantFilter ?: '', 'type' => $typeFilter, 'formule' => $planFilter,
        'etat' => $stateFilter, 'tri' => $sort === 'recent' ? '' : $sort, 'sens' => $reverse ? 'inverse' : '',
    ], $over);
    $p = array_filter($p, static fn ($v): bool => trim((string) $v) !== '');

    return $p === [] ? $base : $base . '?' . http_build_query($p);
};

$ago = static function (?int $sec): string {
    if ($sec === null) {
        return 'jamais';
    }
    if ($sec < 120) {
        return 'à l’instant';
    }
    if ($sec < 3600) {
        return 'il y a ' . intdiv($sec, 60) . ' min';
    }
    if ($sec < 86400) {
        return 'il y a ' . intdiv($sec, 3600) . ' h';
    }
    $d = intdiv($sec, 86400);

    return $d < 60 ? 'il y a ' . $d . ' j' : 'il y a ' . intdiv($d, 30) . ' mois';
};

$fmtDate = static function (mixed $raw): string {
    $ts = UtcDateTime::timestamp($raw);

    return $ts !== null ? date('d/m/Y H:i', $ts) : '—';
};

$returnFields = '<input type="hidden" name="_csrf" value="' . $h($csrf) . '">'
    . '<input type="hidden" name="retour_q" value="' . $h($search) . '">'
    . '<input type="hidden" name="retour_communaute" value="' . ($tenantFilter ?: '') . '">'
    . '<input type="hidden" name="retour_etat" value="' . $h($stateFilter) . '">'
    . '<input type="hidden" name="retour_tri" value="' . $h($sort === 'recent' ? '' : $sort) . '">'
    . '<input type="hidden" name="retour_sens" value="' . ($reverse ? 'inverse' : '') . '">'
    . '<input type="hidden" name="retour_type" value="' . $h($typeFilter) . '">'
    . '<input type="hidden" name="retour_formule" value="' . $h($planFilter) . '">';

$hasFilters = $search !== '' || $tenantFilter > 0 || $stateFilter !== '' || $typeFilter !== '' || $planFilter !== '';

// En-tête de colonne triable : un clic trie, un second clic inverse l'ordre.
$sortHead = static function (string $key, string $label) use ($sort, $reverse, $listUrl, $h): string {
    $active = $sort === $key;
    $url = $listUrl(['tri' => $key === 'recent' ? '' : $key, 'sens' => $active && !$reverse ? 'inverse' : '']);
    $arrow = $active ? ($reverse ? '▲' : '▼') : '↕';

    return '<a class="paf-sort' . ($active ? ' is-active' : '') . '" href="' . $h($url) . '"'
        . ($active ? ' aria-sort="' . ($reverse ? 'ascending' : 'descending') . '"' : '') . '>'
        . $h($label) . ' <span class="paf-sort__arrow" aria-hidden="true">' . $arrow . '</span></a>';
};
$shortSteam = static fn (string $s): string => strlen($s) > 8 ? '…' . substr($s, -7) : $s;
?>
<div class="pa paf">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a class="paf-link" href="<?= $h(url((string) $fs['home'])) ?>"><?= $h((string) $fs['home_label']) ?></a>
                <span aria-hidden="true"> / </span>
                <?= $h($pageTitle) ?>
            </p>
            <div class="pa-hero__row">
                <div>
                    <p class="pa-hero__kicker">Mod COMSPEC ATAK</p>
                    <h1 class="pa-hero__title"><?= $h($pageTitle) ?></h1>
                    <?php if ($inTenant): ?>
                    <p class="pa-hero__lead">
                        Les ATAK des membres de votre communauté, avec ce que le jeu remonte de chacun : temps de jeu, distances,
                        heures de vol, classe et rôle. Imposez un message en jeu, déclarez ou annulez une compromission,
                        fermez les sessions ou bloquez un ATAK dans votre communauté.
                    </p>
                    <?php else: ?>
                    <p class="pa-hero__lead">
                        Tous les ATAK enregistrés par le mod, toutes communautés, et les joueurs Steam vus en jeu sans ATAK
                        enregistré. Pour chaque ligne : le compte Athena retrouvé (ou pas), la communauté, son type et sa formule,
                        et pourquoi le jeu serait refusé. Bloquez, retirez, fermez les sessions ou imposez un message en jeu.
                    </p>
                    <?php endif; ?>
                </div>
                <aside class="pa-hero__meta">
                    <p class="pa-hero__meta-kicker">Parc</p>
                    <dl>
                        <div class="pa-hero__meta-row"><dt>ATAK enregistrés</dt><dd><?= (int) $kinds['atak'] ?></dd></div>
                        <?php if (!$inTenant): ?>
                        <div class="pa-hero__meta-row"><dt>Joueurs Steam sans ATAK</dt><dd><?= (int) $kinds['steam'] ?></dd></div>
                        <?php endif; ?>
                        <div class="pa-hero__meta-row"><dt>Vus depuis 7 jours</dt><dd><?= (int) ($counts['actifs'] ?? 0) ?></dd></div>
                        <div class="pa-hero__meta-row"><dt>Bloqués</dt><dd><?= (int) ($counts['bloques'] ?? 0) ?></dd></div>
                    </dl>
                </aside>
            </div>
        </header>

        <?php if ($flash = \App\Core\Session::getFlash('success')): ?>
            <p class="pa-flash pa-flash--ok" role="status"><?= $h((string) $flash) ?></p>
        <?php endif; ?>
        <?php if ($flash = \App\Core\Session::getFlash('error')): ?>
            <p class="pa-flash pa-flash--err" role="alert"><?= $h((string) $flash) ?></p>
        <?php endif; ?>
        <?php if (empty($tablesReady)): ?>
            <?php if ($inTenant): ?>
            <p class="pa-flash pa-flash--warn">Le blocage et les messages imposés ne sont pas encore activés sur le serveur : prévenez l’administration de la plateforme.</p>
            <?php else: ?>
            <p class="pa-flash pa-flash--warn">Les tables du blocage et des messages n’existent pas encore : lancez <code>php run-migrations.php</code> sur le serveur.</p>
            <?php endif; ?>
        <?php endif; ?>

        <ul class="pa-kpi-strip paf-kpis">
            <li class="pa-kpi">
                <span class="pa-kpi__label">Joueurs affichés</span>
                <span class="pa-kpi__value"><?= (int) ($players ?? 0) ?></span>
                <span class="pa-kpi__hint">avec des chiffres de jeu</span>
            </li>
            <li class="pa-kpi">
                <span class="pa-kpi__label">Temps de jeu</span>
                <span class="pa-kpi__value"><?= $h(PlayerMetrics::duration((int) $totals['play'])) ?></span>
                <span class="pa-kpi__hint">cumul des joueurs affichés</span>
            </li>
            <li class="pa-kpi">
                <span class="pa-kpi__label">Distance parcourue</span>
                <span class="pa-kpi__value"><?= $h(PlayerMetrics::distance((int) $totals['total_m'])) ?></span>
                <span class="pa-kpi__hint">à pied, véhicule, air, mer</span>
            </li>
            <li class="pa-kpi">
                <span class="pa-kpi__label">Comptes rendus</span>
                <span class="pa-kpi__value"><?= (int) $totals['reports'] ?></span>
                <span class="pa-kpi__hint">envoyés depuis l’ATAK</span>
            </li>
            <li class="pa-kpi<?= ($counts['messages'] ?? 0) > 0 ? ' pa-kpi--warn' : '' ?>">
                <span class="pa-kpi__label">Messages en attente</span>
                <span class="pa-kpi__value"><?= (int) ($counts['messages'] ?? 0) ?></span>
                <span class="pa-kpi__hint">pas encore lus en jeu</span>
            </li>
            <?php if (!$inTenant): ?>
            <li class="pa-kpi<?= $kinds['no_account'] > 0 ? ' pa-kpi--warn' : '' ?>">
                <span class="pa-kpi__label">Sans compte Athena</span>
                <span class="pa-kpi__value"><?= (int) $kinds['no_account'] ?></span>
                <span class="pa-kpi__hint">Steam vus en jeu, non reconnus</span>
            </li>
            <li class="pa-kpi<?= $kinds['denied'] > 0 ? ' pa-kpi--warn' : '' ?>">
                <span class="pa-kpi__label">Refus probables</span>
                <span class="pa-kpi__value"><?= (int) $kinds['denied'] ?></span>
                <span class="pa-kpi__hint">blocage, compte ou formule</span>
            </li>
            <?php endif; ?>
        </ul>

        <nav class="pa-jump paf-tabs" aria-label="Filtrer par état">
            <?php foreach (Fleet::STATES as $value => $label): ?>
            <?php if ($inTenant && in_array($value, Fleet::PLATFORM_ONLY, true)) { continue; } ?>
            <a class="<?= $stateFilter === $value ? 'is-active' : '' ?>" href="<?= $h($listUrl(['etat' => $value])) ?>">
                <?= $h($label) ?> <span class="paf-tabs__n"><?= (int) ($counts[$value] ?? 0) ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="pa-panel">
            <form method="get" action="<?= $h($base) ?>" class="paf-search<?= $inTenant ? '' : ' paf-search--wide' ?>" role="search">
                <?php if ($stateFilter !== ''): ?>
                <input type="hidden" name="etat" value="<?= $h($stateFilter) ?>">
                <?php endif; ?>
                <label class="paf-field">
                    <span class="paf-label">Rechercher</span>
                    <input type="search" name="q" value="<?= $h($search) ?>" placeholder="Indicatif, nom, e-mail, Steam, identifiant ATAK…">
                </label>
                <?php if ($reverse): ?>
                <input type="hidden" name="sens" value="inverse">
                <?php endif; ?>
                <?php if (!$inTenant): ?>
                <label class="paf-field">
                    <span class="paf-label">Communauté</span>
                    <select name="communaute">
                        <option value="">Toutes</option>
                        <?php foreach ($tenants as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= $tenantFilter === (int) $t['id'] ? 'selected' : '' ?>><?= $h($t['name']) ?> (<?= (int) $t['n'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="paf-field">
                    <span class="paf-label">Type de communauté</span>
                    <select name="type">
                        <option value="">Tous</option>
                        <?php foreach (TenantTypeConfig::availableTypes() as $value => $type): ?>
                        <option value="<?= $h($value) ?>" <?= $typeFilter === $value ? 'selected' : '' ?>><?= $h((string) $type['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="paf-field">
                    <span class="paf-label">Formule</span>
                    <select name="formule">
                        <option value="">Toutes</option>
                        <?php foreach ($planOptions as $value => $label): ?>
                        <option value="<?= $h($value) ?>" <?= $planFilter === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
                <label class="paf-field">
                    <span class="paf-label">Trier par</span>
                    <select name="tri">
                        <?php foreach (Fleet::SORTS as $value => $label): ?>
                        <?php if ($inTenant && in_array($value, Fleet::PLATFORM_ONLY, true)) { continue; } ?>
                        <option value="<?= $h($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="paf-search__actions">
                    <button type="submit" class="pa-btn pa-btn--ink">Filtrer</button>
                    <?php if ($hasFilters): ?>
                    <a class="pa-btn pa-btn--line" href="<?= $h($base) ?>">Réinitialiser</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($rows === []): ?>
                <div class="pa-empty-state">
                    <p><strong><?= $hasFilters ? 'Aucun ATAK pour ce filtre.' : 'Aucun ATAK enregistré pour le moment.' ?></strong></p>
                    <p><?= $inTenant ? 'Un ATAK apparaît ici dès que le téléphone d’un joueur se relie au site depuis le jeu.' : 'Un ATAK apparaît ici dès que le téléphone d’un joueur se relie au site depuis le jeu ; un joueur Steam, dès que le serveur ou le mod le remonte.' ?></p>
                </div>
            <?php else: ?>
            <form id="paf-bulk" method="post" action="<?= $h($base . '/message') ?>" class="paf-bulk">
                <?= $returnFields ?>
                <div class="paf-bulk__head">
                    <label class="paf-check">
                        <input type="checkbox" data-paf-all aria-label="Cocher tous les ATAK affichés">
                        <span><strong data-paf-count>0</strong> <span>ligne(s) cochée(s)</span></span>
                    </label>
                    <p class="paf-hint"><?= $inTenant ? 'Cochez des lignes, puis choisissez une action. Chaque action est notée dans le journal.' : 'Cochez des lignes, puis choisissez une action. Un joueur Steam sans ATAK peut être bloqué ou recevoir un message ; déconnecter et retirer ne visent que les ATAK enregistrés. Chaque action est notée dans le journal.' ?></p>
                </div>
                <div class="paf-bulk__grid">
                    <details class="paf-act">
                        <summary>Imposer un message en jeu</summary>
                        <div class="paf-act__body">
                            <label class="paf-field"><span class="paf-label">Titre</span><input type="text" name="titre" maxlength="120" placeholder="Ex. Rappel de règlement"></label>
                            <label class="paf-field"><span class="paf-label">Texte</span><textarea name="texte" rows="3" maxlength="2000" placeholder="Ce que le joueur verra sur son ATAK"></textarea></label>
                            <div class="paf-row">
                                <label class="paf-field"><span class="paf-label">Niveau</span>
                                    <select name="niveau"><?php foreach (Fleet::LEVELS as $v => $l): ?><option value="<?= $h($v) ?>"><?= $h($l) ?></option><?php endforeach; ?></select>
                                </label>
                                <label class="paf-field"><span class="paf-label">Valable</span>
                                    <select name="validite_heures"><option value="24">24 h</option><option value="72" selected>3 jours</option><option value="168">7 jours</option><option value="720">30 jours</option><option value="0">Jusqu’à lecture</option></select>
                                </label>
                            </div>
                            <label class="paf-check"><input type="hidden" name="validation" value="0"><input type="checkbox" name="validation" value="1" checked> <span>Le joueur doit valider pour fermer le message</span></label>
                            <button type="submit" class="pa-btn pa-btn--sm pa-btn--mint" data-paf-needs>Envoyer aux lignes cochées</button>
                        </div>
                    </details>
                    <details class="paf-act">
                        <summary>Bloquer</summary>
                        <div class="paf-act__body">
                            <div class="paf-row">
                                <?php if ($inTenant): ?>
                                <p class="paf-field paf-hint">Le blocage vaut dans votre communauté.</p>
                                <?php else: ?>
                                <label class="paf-field"><span class="paf-label">Portée</span>
                                    <select name="portee"><option value="communaute">Sa communauté</option><option value="toutes">Toutes les communautés</option></select>
                                </label>
                                <?php endif; ?>
                                <label class="paf-field"><span class="paf-label">Durée</span>
                                    <select name="duree_jours"><option value="1">1 jour</option><option value="7">7 jours</option><option value="30">30 jours</option><option value="0" selected>Sans fin</option></select>
                                </label>
                            </div>
                            <label class="paf-field"><span class="paf-label">Motif (interne)</span><input type="text" name="motif" maxlength="500"></label>
                            <label class="paf-field"><span class="paf-label">Message montré au joueur</span><input type="text" name="message_joueur" maxlength="500" placeholder="<?= $h($inTenant ? 'Votre ATAK a été bloqué par la gestion de votre communauté…' : 'Votre ATAK a été bloqué par l’administration ATHENA…') ?>"></label>
                            <button type="submit" formaction="<?= $h($base . '/bloquer') ?>" class="pa-btn pa-btn--sm paf-btn--danger" data-paf-needs
                                    onclick="return confirm('Bloquer les lignes cochées ? Le jeu sera refusé à leur prochaine connexion.');">Bloquer les lignes cochées</button>
                        </div>
                    </details>
                    <?php if ($inTenant): ?>
                    <details class="paf-act">
                        <summary>Déconnecter</summary>
                        <div class="paf-act__body">
                            <p class="paf-hint">Ferme les sessions de jeu dans votre communauté : le jeu se reconnecte seul s’il n’est pas bloqué. Le retrait définitif d’un ATAK reste réservé à l’administration de la plateforme.</p>
                            <div class="paf-buttons">
                                <button type="submit" formaction="<?= $h($base . '/deconnecter') ?>" class="pa-btn pa-btn--sm pa-btn--line" data-paf-needs>Déconnecter</button>
                            </div>
                        </div>
                    </details>
                    <?php else: ?>
                    <details class="paf-act">
                        <summary>Déconnecter ou retirer</summary>
                        <div class="paf-act__body">
                            <p class="paf-hint">Déconnecter ferme les sessions de jeu : le jeu se reconnecte seul. Retirer révoque les certificats et supprime la fiche : le joueur devra refaire la liaison.</p>
                            <div class="paf-buttons">
                                <button type="submit" formaction="<?= $h($base . '/deconnecter') ?>" class="pa-btn pa-btn--sm pa-btn--line" data-paf-needs>Déconnecter</button>
                                <button type="submit" formaction="<?= $h($base . '/retirer') ?>" class="pa-btn pa-btn--sm paf-btn--danger" data-paf-needs
                                        onclick="return confirm('Retirer définitivement les ATAK cochés ?');">Retirer</button>
                            </div>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($matching > count($rows)): ?>
            <p class="paf-hint">Les <?= count($rows) ?> premières lignes sont affichées sur <?= (int) $matching ?>. Affinez avec la recherche<?= $inTenant ? '' : ' ou la communauté' ?>.</p>
            <?php endif; ?>

            <div class="pa-table-wrap">
                <table class="pa-table paf-table<?= $inTenant ? '' : ' paf-table--platform' ?>">
                    <thead>
                        <tr>
                            <th class="paf-col-check"><span class="paf-sr">Cocher</span></th>
                            <th><?= $sortHead('nom', 'Joueur') ?></th>
                            <th>ATAK</th>
                            <?php if (!$inTenant): ?>
                            <th><?= $sortHead('compte', 'Compte Athena') ?></th>
                            <th><?= $sortHead('communaute', 'Communauté') ?> <span class="paf-sort-sep" aria-hidden="true">·</span> <?= $sortHead('formule', 'Formule') ?></th>
                            <?php else: ?>
                            <th>Classe et rôle</th>
                            <?php endif; ?>
                            <?php if ($inTenant): ?>
                            <th class="paf-num"><?= $sortHead('jeu', 'Jeu') ?></th>
                            <th class="paf-num"><?= $sortHead('distance', 'Distance') ?></th>
                            <th class="paf-num">CR</th>
                            <?php else: ?>
                            <th class="paf-num"><?= $sortHead('jeu', 'Jeu') ?> <span class="paf-sort-sep" aria-hidden="true">·</span> <?= $sortHead('distance', 'Distance') ?></th>
                            <?php endif; ?>
                            <th><?= $sortHead('recent', 'Dernier signe') ?></th>
                            <th><?= $sortHead('etat', 'État') ?></th>
                            <th class="paf-col-act"><span class="paf-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $id = (int) $r['id'];
                        $kind = (string) ($r['kind'] ?? 'atak');
                        $isAtak = $kind === 'atak';
                        $m = is_array($r['metrics'] ?? null) ? $r['metrics'] : null;
                        $name = Fleet::playerLabel($r);
                        $real = trim((string) ($r['display_name'] ?? ''));
                        $class = $m !== null ? ($m['game_class'] !== '' ? $m['game_class'] : $m['slot']) : '';
                        $role = $m['role'] ?? '';
                        if (!$isAtak && $class === '') {
                            $class = trim((string) ($r['game_faction'] ?? ''));
                            $role = trim((string) ($r['game_role'] ?? ''));
                        }
                        $block = $r['block'] ?? null;
                        $compromised = !in_array((string) ($r['compromise_state'] ?? 'none'), ['', 'none'], true);
                        $fiche = $base . '/fiche?id=' . $id;
                        $steam = trim((string) ($r['steam_id'] ?? ''));
                        $tid = (int) ($r['tenant_id'] ?? 0);
                        $tn = is_array($r['tenant'] ?? null) ? $r['tenant'] : null;
                        $link = (string) ($r['link'] ?? 'ok');
                        $linkInfo = AtakFleetLink::STATES[$link] ?? AtakFleetLink::STATES['ok'];
                        $planAccess = (string) ($r['plan_access'] ?? 'full');
                        $planInfo = AtakFleetLink::PLAN_ACCESS[$planAccess] ?? AtakFleetLink::PLAN_ACCESS['unknown'];
                        $denials = is_array($r['denials'] ?? null) ? $r['denials'] : [];
                        $linkUsers = is_array($r['link_users'] ?? null) ? $r['link_users'] : [];
                        $memberId = (int) ($r['user_id'] ?? 0) ?: (int) ($linkUsers[0]['id'] ?? 0);
                        ?>
                        <tr class="<?= trim(($block !== null ? 'is-blocked ' : '') . ($isAtak ? '' : 'is-steam')) ?>">
                            <td class="paf-col-check">
                                <?php if ($isAtak): ?>
                                <input type="checkbox" form="paf-bulk" name="terminal_ids[]" value="<?= $id ?>" data-paf-row aria-label="Cocher <?= $h($name) ?>">
                                <?php elseif ($steam !== ''): ?>
                                <input type="checkbox" form="paf-bulk" name="steam_keys[]" value="<?= $tid ?>:<?= $h($steam) ?>" data-paf-row aria-label="Cocher <?= $h($name) ?>">
                                <?php endif; ?>
                            </td>
                            <td>
                                <p class="pa-name"><?php if ($isAtak): ?><a href="<?= $h($fiche) ?>"><?= $h($name) ?></a><?php else: ?><?= $h($name) ?><?php endif; ?></p>
                                <p class="pa-sub paf-muted">
                                    <?php if ($inTenant): ?>
                                    <?= $h($real !== '' && $real !== $name ? $real : ((int) ($r['user_id'] ?? 0) > 0 ? 'Membre de la communauté' : 'ATAK sans compte du site')) ?>
                                    <?php else: ?>
                                    <?= $h($real !== '' && $real !== $name ? $real : '') ?>
                                    <?php endif; ?>
                                </p>
                                <?php if (!$inTenant && ($class !== '' || $role !== '')): ?>
                                <p class="pa-sub paf-muted"><?= $h(implode(' · ', array_filter([$class, $role]))) ?></p>
                                <?php endif; ?>
                                <?php if (!$inTenant && $steam !== ''): ?>
                                <p class="pa-sub paf-mono paf-steam" title="Steam <?= $h($steam) ?>">Steam <?= $h($shortSteam($steam)) ?></p>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isAtak): ?>
                                <p class="paf-mono"><?= $h((string) $r['terminal_uid']) ?></p>
                                <p class="pa-sub paf-muted">
                                    <?= trim((string) ($r['mod_version'] ?? '')) !== '' ? 'ATAK v' . $h((string) $r['mod_version']) : $h((string) ($r['terminal_type'] ?? 'phone')) ?>
                                </p>
                                <?php else: ?>
                                <p class="paf-noatak"><?= $kind === 'report' ? 'Vu par les rapports du mod' : 'Pas d’ATAK enregistré' ?></p>
                                <p class="pa-sub paf-muted">
                                    <?php if ($kind === 'report'): ?>
                                    <span><?= (int) ($r['reports'] ?? 0) ?> rapport(s)</span><?php if ((int) ($r['denied'] ?? 0) > 0): ?>, <span>dont <?= (int) $r['denied'] ?> refus</span><?php endif; ?>
                                    <?php else: ?>
                                    <?php if (trim((string) ($r['mod_version'] ?? '')) !== ''): ?>ATAK v<?= $h((string) $r['mod_version']) ?> · <span>vu en jeu</span><?php else: ?>Vu en jeu<?php endif; ?>
                                    <?php endif; ?>
                                </p>
                                <?php endif; ?>
                            </td>
                            <?php if (!$inTenant): ?>
                            <td>
                                <span class="pa-pill pa-pill--<?= $h($linkInfo[1]) ?>" title="<?= $h($linkInfo[2]) ?>"><?= $h($linkInfo[0]) ?></span>
                                <?php if ($link === 'duplicate'): ?>
                                <p class="pa-sub paf-muted"><?= count($linkUsers) ?> fiches membres</p>
                                <?php elseif ($link === 'other_tenant' && $linkUsers !== []): ?>
                                <p class="pa-sub paf-muted">Membre #<?= (int) $linkUsers[0]['id'] ?></p>
                                <?php elseif ($memberId > 0 && in_array($link, ['ok', 'no_terminal', 'steam_mismatch', 'no_steam', 'bad_email', 'disabled'], true)): ?>
                                <p class="pa-sub paf-muted">Membre #<?= $memberId ?></p>
                                <?php endif; ?>
                                <?php if ($denials !== []): ?>
                                <p class="paf-deny" title="<?= $h($linkInfo[2]) ?>"><span>Jeu refusé :</span> <?= implode(', ', array_map(static fn (string $d): string => '<span>' . $h($d) . '</span>', $denials)) ?></p>
                                <?php elseif ($kind === 'report' && trim((string) ($r['last_denial'] ?? '')) !== ''): ?>
                                <p class="paf-deny paf-deny--soft">Dernier refus : <?= $h((string) $r['last_denial']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tn !== null): ?>
                                <p class="paf-strong"><?= $h((string) $tn['name']) ?></p>
                                <p class="pa-sub paf-muted"><span><?= $h((string) $tn['type_label']) ?></span> · <span><?= $h((string) $tn['plan_label']) ?></span><?php if (!empty($tn['gift'])): ?> <span>(offerte)</span><?php endif; ?></p>
                                <?php elseif ($tid > 0): ?>
                                <p class="paf-strong"><?= $h((string) ($r['tenant_name'] ?? ('Communauté #' . $tid))) ?></p>
                                <?php endif; ?>
                                <span class="pa-pill pa-pill--<?= $h($planInfo[1]) ?>"><?= $h($planInfo[0]) ?></span>
                            </td>
                            <?php endif; ?>
                            <?php if ($inTenant): ?>
                            <td>
                                <p class="paf-strong"><?= $h($class !== '' ? $class : '—') ?></p>
                                <p class="pa-sub paf-muted"><?= $h($role !== '' ? $role : 'Rôle non remonté') ?></p>
                            </td>
                            <?php endif; ?>
                            <?php if ($inTenant): ?>
                            <td class="paf-num">
                                <?= $h($m !== null && $m['play'] > 0 ? PlayerMetrics::duration((int) $m['play']) : '—') ?>
                                <?php if ($m !== null && $m['pilot_s'] > 0): ?><p class="pa-sub paf-muted"><?= $h(PlayerMetrics::duration((int) $m['pilot_s'])) ?> de vol</p><?php endif; ?>
                            </td>
                            <td class="paf-num">
                                <?= $h($m !== null && $m['total_m'] > 0 ? PlayerMetrics::distance((int) $m['total_m']) : '—') ?>
                                <?php if ($m !== null && $m['foot_m'] > 0): ?><p class="pa-sub paf-muted"><?= $h(PlayerMetrics::distance((int) $m['foot_m'])) ?> à pied</p><?php endif; ?>
                            </td>
                            <td class="paf-num"><?= (int) ($m['reports'] ?? 0) ?></td>
                            <?php else: ?>
                            <td class="paf-num">
                                <?= $h($m !== null && $m['play'] > 0 ? PlayerMetrics::duration((int) $m['play']) : '—') ?>
                                <p class="pa-sub paf-muted"><?= $h($m !== null && $m['total_m'] > 0 ? PlayerMetrics::distance((int) $m['total_m']) : '—') ?><?= $m !== null && $m['pilot_s'] > 0 ? ' · ' . $h(PlayerMetrics::duration((int) $m['pilot_s'])) . ' de vol' : '' ?></p>
                            </td>
                            <?php endif; ?>
                            <td>
                                <p title="<?= $h($fmtDate($r['last_seen_at'] ?? null)) ?>"><?= $h($ago($r['seen_ago'] ?? null)) ?></p>
                                <?php if (!$isAtak && trim((string) ($r['server_name'] ?? '')) !== ''): ?>
                                <p class="pa-sub paf-muted"><?= $h((string) $r['server_name']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="paf-states">
                                <?php if ($block !== null): ?>
                                    <?php $platformBlock = (int) ($block['tenant_id'] ?? 0) === 0; ?>
                                    <span class="pa-pill pa-pill--rose" title="<?= $h((string) ($block['reason'] ?? '')) ?>">Bloqué<?= $platformBlock ? ($inTenant ? ' par la plateforme' : ' partout') : '' ?></span>
                                <?php elseif (($r['seen_ago'] ?? null) !== null && $r['seen_ago'] <= 7 * 86400): ?>
                                    <span class="pa-pill pa-pill--mint">Actif</span>
                                <?php else: ?>
                                    <span class="pa-pill pa-pill--slate">Dormant</span>
                                <?php endif; ?>
                                <?php if ((int) ($r['pending_messages'] ?? 0) > 0): ?>
                                    <span class="pa-pill pa-pill--amber"><?= (int) $r['pending_messages'] ?> message(s)</span>
                                <?php endif; ?>
                                <?php if ($compromised): ?>
                                    <span class="pa-pill pa-pill--amber">Compromis</span>
                                <?php endif; ?>
                            </td>
                            <td class="paf-col-act">
                                <details class="paf-menu">
                                    <summary aria-label="Actions pour <?= $h($name) ?>" title="Actions"><span aria-hidden="true">⋯</span></summary>
                                    <div class="paf-menu__pop">
                                        <?php if ($isAtak): ?>
                                        <a href="<?= $h($fiche) ?>">Ouvrir la fiche ATAK</a>
                                        <?php endif; ?>
                                        <?php if (!$inTenant && $memberId > 0): ?>
                                        <a href="<?= $h(url('admin/users/person') . '?user_id=' . $memberId) ?>">Voir le compte du membre</a>
                                        <?php endif; ?>
                                        <?php if (!$inTenant && $tid > 0): ?>
                                        <a href="<?= $h(url('admin/tenants/' . $tid . '/edit')) ?>">Voir la communauté</a>
                                        <?php if ($planAccess !== 'full'): ?>
                                        <a href="<?= $h(url('admin/formules-offertes') . '?tenant_id=' . $tid) ?>">Offrir une formule avec la carte</a>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (!$inTenant && $steam !== ''): ?>
                                        <a href="<?= $h(url('admin/atak-mod-reports') . '?q=' . rawurlencode($steam)) ?>">Rapports du mod de ce Steam</a>
                                        <a href="https://steamcommunity.com/profiles/<?= $h($steam) ?>" target="_blank" rel="noopener noreferrer">Profil Steam</a>
                                        <button type="button" data-paf-copy="<?= $h($steam) ?>">Copier le Steam ID</button>
                                        <?php endif; ?>
                                        <?php if ($block !== null && (!$inTenant || (int) ($block['tenant_id'] ?? 0) > 0)): ?>
                                        <form method="post" action="<?= $h($base . '/debloquer') ?>">
                                            <?= $returnFields ?>
                                            <input type="hidden" name="blocage_id" value="<?= (int) $block['id'] ?>">
                                            <button type="submit">Lever le blocage</button>
                                        </form>
                                        <?php elseif ($block === null && ($isAtak || $steam !== '')): ?>
                                        <form method="post" action="<?= $h($base . '/bloquer') ?>" onsubmit="return confirm('Bloquer <?= $h(addslashes($name)) ?> ? Le jeu sera refusé à sa prochaine connexion.');">
                                            <?= $returnFields ?>
                                            <?php if ($isAtak): ?>
                                            <input type="hidden" name="terminal_id" value="<?= $id ?>">
                                            <?php else: ?>
                                            <input type="hidden" name="steam_keys[]" value="<?= $tid ?>:<?= $h($steam) ?>">
                                            <?php endif; ?>
                                            <input type="hidden" name="duree_jours" value="0">
                                            <button type="submit" class="paf-menu__danger"><?= $tid > 0 ? 'Bloquer dans sa communauté' : 'Bloquer partout' ?></button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!$inTenant): ?>
            <details class="paf-legend">
                <summary>Lire la colonne « Compte Athena »</summary>
                <dl>
                    <?php foreach (AtakFleetLink::STATES as $info): ?>
                    <div><dt><span class="pa-pill pa-pill--<?= $h($info[1]) ?>"><?= $h($info[0]) ?></span></dt><dd><?= $h($info[2]) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </details>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($actions !== []): ?>
            <section class="pa-card paf-journal" aria-labelledby="paf-journal-title">
                <p class="pa-card__kicker">Journal</p>
                <h2 id="paf-journal-title" class="pa-card__title"><?= $h($inTenant ? 'Dernières actions sur les ATAK de la communauté' : 'Dernières actions de l’administration') ?></h2>
                <ol class="paf-log">
                    <?php foreach ($actions as $a): ?>
                    <li>
                        <span class="paf-log__when"><?= $h($fmtDate($a['created_at'] ?? null)) ?></span>
                        <span class="paf-log__what"><strong><?= $h(Fleet::ACTIONS[(string) $a['action']] ?? (string) $a['action']) ?></strong> · <?= $h((string) ($a['label'] ?? '')) ?><?php if (!$inTenant && trim((string) ($a['tenant_name'] ?? '')) !== ''): ?> (<?= $h((string) $a['tenant_name']) ?>)<?php endif; ?></span>
                        <span class="paf-log__who">par <?= $h((string) ($a['actor_label'] ?? '—')) ?><?php if (trim((string) ($a['detail'] ?? '')) !== ''): ?> · <?= $h((string) $a['detail']) ?><?php endif; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </section>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    var all = document.querySelector('[data-paf-all]');
    var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-paf-row]'));
    var count = document.querySelector('[data-paf-count]');
    var needs = Array.prototype.slice.call(document.querySelectorAll('[data-paf-needs]'));
    function refresh() {
        var n = boxes.filter(function (b) { return b.checked; }).length;
        if (count) { count.textContent = String(n); }
        needs.forEach(function (b) { b.disabled = n === 0; });
        if (all) { all.checked = n > 0 && n === boxes.length; all.indeterminate = n > 0 && n < boxes.length; }
    }
    if (all) {
        all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); refresh(); });
    }
    boxes.forEach(function (b) { b.addEventListener('change', refresh); });
    refresh();
    Array.prototype.forEach.call(document.querySelectorAll('[data-paf-copy]'), function (btn) {
        btn.addEventListener('click', function () {
            var v = btn.getAttribute('data-paf-copy') || '';
            var done = function () { btn.setAttribute('data-paf-copied', '1'); setTimeout(function () { btn.removeAttribute('data-paf-copied'); }, 1500); };
            if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(v).then(done, done); } else { window.prompt('Steam ID', v); }
        });
    });
    // Un seul menu d'actions ouvert à la fois. Le tableau défile en largeur : le menu ouvert est posé hors du panneau
    // animé (position fixe) pour ne pas être coupé, suit son bouton au défilement, et revient dans sa ligne à la fermeture.
    var place = function (d) {
        var pop = d._pafPop;
        var r = d.querySelector('summary').getBoundingClientRect();
        pop.style.left = Math.max(8, Math.min(window.innerWidth - pop.offsetWidth - 8, r.right - pop.offsetWidth)) + 'px';
        var below = r.bottom + 6;
        pop.style.top = (below + pop.offsetHeight > window.innerHeight - 8 ? Math.max(8, r.top - 6 - pop.offsetHeight) : below) + 'px';
        // Un parent transformé change le repère de « fixed » : on corrige l'écart mesuré.
        var got = pop.getBoundingClientRect();
        pop.style.left = (parseFloat(pop.style.left) * 2 - got.left) + 'px';
        pop.style.top = (parseFloat(pop.style.top) * 2 - got.top) + 'px';
    };
    Array.prototype.forEach.call(document.querySelectorAll('.paf-menu'), function (d) {
        var pop = d.querySelector('.paf-menu__pop');
        d._pafPop = pop;
        d.addEventListener('toggle', function () {
            if (!d.open) {
                if (pop.parentNode !== d) { d.appendChild(pop); }
                pop.removeAttribute('style');
                return;
            }
            Array.prototype.forEach.call(document.querySelectorAll('.paf-menu[open]'), function (o) { if (o !== d) { o.open = false; } });
            (d.closest('.paf') || document.body).appendChild(pop);
            pop.style.position = 'fixed';
            pop.style.right = 'auto';
            place(d);
        });
    });
    var closeMenus = function () {
        Array.prototype.forEach.call(document.querySelectorAll('.paf-menu[open]'), function (o) { o.open = false; });
    };
    var follow = function () {
        Array.prototype.forEach.call(document.querySelectorAll('.paf-menu[open]'), place);
    };
    window.addEventListener('scroll', follow, { passive: true });
    window.addEventListener('resize', closeMenus);
    document.addEventListener('click', function (e) {
        if (!e.target.closest || (!e.target.closest('.paf-menu') && !e.target.closest('.paf-menu__pop'))) { closeMenus(); }
    });
    var wrap = document.querySelector('.paf-table-wrap, .pa-table-wrap');
    if (wrap) { wrap.addEventListener('scroll', follow, { passive: true }); }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeMenus(); } });
})();
</script>
