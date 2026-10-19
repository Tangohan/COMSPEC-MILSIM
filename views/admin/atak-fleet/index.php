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

use App\Repositories\AtakAdminControlRepository as Fleet;
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

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$csrf = \App\Core\Csrf::token();
// Même page pour l'administration de la plateforme (toutes communautés) et la gestion d'une communauté (ses membres).
$fs = is_array($fleetScope ?? null) ? $fleetScope : ['tenant' => false, 'base' => 'admin/parc-atak', 'home' => 'admin', 'home_label' => 'Administration du site'];
$inTenant = !empty($fs['tenant']);
$base = url((string) $fs['base']);
$pageTitle = $inTenant ? 'Parc ATAK des membres' : 'Parc ATAK des joueurs';

$listUrl = static function (array $over = []) use ($base, $search, $tenantFilter, $stateFilter, $sort): string {
    $p = array_merge(['q' => $search, 'communaute' => $tenantFilter ?: '', 'etat' => $stateFilter, 'tri' => $sort === 'recent' ? '' : $sort], $over);
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
    . '<input type="hidden" name="retour_tri" value="' . $h($sort === 'recent' ? '' : $sort) . '">';

$hasFilters = $search !== '' || $tenantFilter > 0 || $stateFilter !== '';
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
                        Tous les ATAK enregistrés par le mod, toutes communautés, avec ce que le jeu remonte de chaque joueur :
                        temps de jeu, distances, heures de vol, classe et rôle. Bloquez un ATAK, retirez-le, fermez ses sessions
                        ou imposez-lui un message en jeu.
                    </p>
                    <?php endif; ?>
                </div>
                <aside class="pa-hero__meta">
                    <p class="pa-hero__meta-kicker">Parc</p>
                    <dl>
                        <div class="pa-hero__meta-row"><dt>ATAK</dt><dd><?= (int) ($counts[''] ?? 0) ?></dd></div>
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
        </ul>

        <nav class="pa-jump paf-tabs" aria-label="Filtrer par état">
            <?php foreach (Fleet::STATES as $value => $label): ?>
            <a class="<?= $stateFilter === $value ? 'is-active' : '' ?>" href="<?= $h($listUrl(['etat' => $value])) ?>">
                <?= $h($label) ?> <span class="paf-tabs__n"><?= (int) ($counts[$value] ?? 0) ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="pa-panel">
            <form method="get" action="<?= $h($base) ?>" class="paf-search" role="search">
                <?php if ($stateFilter !== ''): ?>
                <input type="hidden" name="etat" value="<?= $h($stateFilter) ?>">
                <?php endif; ?>
                <label class="paf-field">
                    <span class="paf-label">Rechercher</span>
                    <input type="search" name="q" value="<?= $h($search) ?>" placeholder="Indicatif, nom, e-mail, Steam, identifiant ATAK…">
                </label>
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
                <?php endif; ?>
                <label class="paf-field">
                    <span class="paf-label">Trier par</span>
                    <select name="tri">
                        <?php foreach (Fleet::SORTS as $value => $label): ?>
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
                    <p>Un ATAK apparaît ici dès que le téléphone d’un joueur se relie au site depuis le jeu.</p>
                </div>
            <?php else: ?>
            <form id="paf-bulk" method="post" action="<?= $h($base . '/message') ?>" class="paf-bulk">
                <?= $returnFields ?>
                <div class="paf-bulk__head">
                    <label class="paf-check">
                        <input type="checkbox" data-paf-all aria-label="Cocher tous les ATAK affichés">
                        <span><strong data-paf-count>0</strong> ATAK coché(s)</span>
                    </label>
                    <p class="paf-hint">Cochez des lignes, puis choisissez une action. Chaque action est notée dans le journal.</p>
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
                            <button type="submit" class="pa-btn pa-btn--sm pa-btn--mint" data-paf-needs>Envoyer aux ATAK cochés</button>
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
                                    onclick="return confirm('Bloquer les ATAK cochés ? Le jeu sera refusé à leur prochaine connexion.');">Bloquer les ATAK cochés</button>
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
            <p class="paf-hint">Les <?= count($rows) ?> premiers ATAK sont affichés sur <?= (int) $matching ?>. Affinez avec la recherche<?= $inTenant ? '' : ' ou la communauté' ?>.</p>
            <?php endif; ?>

            <div class="pa-table-wrap">
                <table class="pa-table paf-table">
                    <thead>
                        <tr>
                            <th class="paf-col-check"><span class="paf-sr">Cocher</span></th>
                            <th>Joueur</th>
                            <th>ATAK</th>
                            <th>Classe et rôle</th>
                            <th class="paf-num">Jeu</th>
                            <th class="paf-num">Distance</th>
                            <th class="paf-num">CR</th>
                            <th>Dernier signe</th>
                            <th>État</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $id = (int) $r['id'];
                        $m = is_array($r['metrics'] ?? null) ? $r['metrics'] : null;
                        $name = Fleet::playerLabel($r);
                        $real = trim((string) ($r['display_name'] ?? ''));
                        $class = $m !== null ? ($m['game_class'] !== '' ? $m['game_class'] : $m['slot']) : '';
                        $role = $m['role'] ?? '';
                        $block = $r['block'] ?? null;
                        $compromised = !in_array((string) ($r['compromise_state'] ?? 'none'), ['', 'none'], true);
                        $fiche = $base . '/fiche?id=' . $id;
                        ?>
                        <tr class="<?= $block !== null ? 'is-blocked' : '' ?>">
                            <td class="paf-col-check">
                                <input type="checkbox" form="paf-bulk" name="terminal_ids[]" value="<?= $id ?>" data-paf-row aria-label="Cocher <?= $h($name) ?>">
                            </td>
                            <td>
                                <p class="pa-name"><a href="<?= $h($fiche) ?>"><?= $h($name) ?></a></p>
                                <p class="pa-sub paf-muted">
                                    <?php if ($inTenant): ?>
                                    <?= $h($real !== '' && $real !== $name ? $real : ((int) ($r['user_id'] ?? 0) > 0 ? 'Membre de la communauté' : 'ATAK sans compte du site')) ?>
                                    <?php else: ?>
                                    <?= $h($real !== '' && $real !== $name ? $real . ' · ' : '') ?><?= $h((string) ($r['tenant_name'] ?? ('Communauté #' . (int) $r['tenant_id']))) ?>
                                    <?php endif; ?>
                                </p>
                            </td>
                            <td>
                                <p class="paf-mono"><?= $h((string) $r['terminal_uid']) ?></p>
                                <p class="pa-sub paf-muted">
                                    <?= trim((string) ($r['mod_version'] ?? '')) !== '' ? 'ATAK v' . $h((string) $r['mod_version']) : $h((string) ($r['terminal_type'] ?? 'phone')) ?>
                                </p>
                            </td>
                            <td>
                                <p class="paf-strong"><?= $h($class !== '' ? $class : '—') ?></p>
                                <p class="pa-sub paf-muted"><?= $h($role !== '' ? $role : 'Rôle non remonté') ?></p>
                            </td>
                            <td class="paf-num">
                                <?= $h($m !== null && $m['play'] > 0 ? PlayerMetrics::duration((int) $m['play']) : '—') ?>
                                <?php if ($m !== null && $m['pilot_s'] > 0): ?><p class="pa-sub paf-muted"><?= $h(PlayerMetrics::duration((int) $m['pilot_s'])) ?> de vol</p><?php endif; ?>
                            </td>
                            <td class="paf-num">
                                <?= $h($m !== null && $m['total_m'] > 0 ? PlayerMetrics::distance((int) $m['total_m']) : '—') ?>
                                <?php if ($m !== null && $m['foot_m'] > 0): ?><p class="pa-sub paf-muted"><?= $h(PlayerMetrics::distance((int) $m['foot_m'])) ?> à pied</p><?php endif; ?>
                            </td>
                            <td class="paf-num"><?= (int) ($m['reports'] ?? 0) ?></td>
                            <td>
                                <p title="<?= $h($fmtDate($r['last_seen_at'] ?? null)) ?>"><?= $h($ago($r['seen_ago'] ?? null)) ?></p>
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
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
})();
</script>
