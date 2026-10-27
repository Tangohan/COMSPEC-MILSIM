<?php
declare(strict_types=1);
$rows = is_array($platformTenants ?? null) ? $platformTenants : [];
$plans = is_array($platformSubscriptionPlans ?? null) ? $platformSubscriptionPlans : [];
$plansError = isset($platformSubscriptionPlansError) ? (string) $platformSubscriptionPlansError : '';
$planNameBySlug = is_array($platformPlanNameBySlug ?? null) ? $platformPlanNameBySlug : [];
$statusLabels = is_array($platformSubscriptionStatusLabels ?? null) ? $platformSubscriptionStatusLabels : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$fmt = static function (mixed $raw): string {
    if ($raw === null || $raw === '') {
        return '';
    }
    $t = strtotime((string) $raw);

    return $t ? date('d/m/Y', $t) : (string) $raw;
};
$iso = static function (mixed $raw): string {
    if ($raw === null || $raw === '') {
        return '';
    }
    $t = strtotime((string) $raw);

    return $t ? date('Y-m-d', $t) : '';
};
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
$typePills = [
    'full' => 'slate',
    'effectifs' => 'blue',
    'atak' => 'amber',
];
$sheet = [];
$planOptions = [];
foreach ($rows as $t) {
    if (!is_array($t)) {
        continue;
    }
    $id = (int) ($t['id'] ?? 0);
    $name = (string) ($t['name'] ?? '');
    $slug = (string) ($t['slug'] ?? '');
    $tenantType = (string) ($t['tenant_type'] ?? 'full');
    $typeLabel = \App\Services\Community\TenantTypeConfig::label($tenantType);
    $basePlanSlug = (string) ($t['plan_slug'] ?? 'free');
    $planSlug = (string) ($t['effective_plan_slug'] ?? $basePlanSlug);
    $planLabel = (string) ($planNameBySlug[$planSlug] ?? '');
    if ($planLabel === '') {
        $planLabel = ($planSlug === '' || $planSlug === 'free') ? 'Gratuite' : 'Formule non reconnue';
    }
    $basePlanLabel = (string) ($planNameBySlug[$basePlanSlug] ?? '');
    if ($basePlanLabel === '') {
        $basePlanLabel = ($basePlanSlug === '' || $basePlanSlug === 'free') ? 'Gratuite' : 'Formule non reconnue';
    }
    $status = (string) ($t['subscription_status'] ?? 'none');
    $statusLabel = (string) ($statusLabels[$status] ?? 'Statut inconnu');
    $archived = is_array($t['archive'] ?? null);
    $planGift = is_array($t['plan_gift'] ?? null) ? $t['plan_gift'] : null;
    $giftLabel = '';
    if ($planGift !== null) {
        $giftLabel = !empty($planGift['ends_at'])
            ? 'Offerte jusqu’au ' . $fmt($planGift['ends_at'])
            : 'Offerte sans limite';
    }
    $createdDisplay = $fmt($t['created_at'] ?? null);
    $loginIso = $iso($t['last_login_at'] ?? null);
    $gameIso = $iso($t['last_game_at'] ?? null);
    $latestIso = $loginIso !== '' && $gameIso !== '' ? max($loginIso, $gameIso) : ($loginIso !== '' ? $loginIso : $gameIso);
    $idleDays = null;
    if ($latestIso !== '') {
        $idleDays = (int) floor((time() - (int) strtotime($latestIso)) / 86400);
        if ($idleDays < 0) {
            $idleDays = 0;
        }
    }
    $accounts = (int) ($t['user_count'] ?? 0);
    $terminals = (int) ($t['terminal_count'] ?? 0);
    $mapUnits = (int) ($t['unit_count'] ?? 0);
    $profiles = (int) ($t['profile_count'] ?? 0);
    $sessions = (int) ($t['session_count'] ?? 0);
    if ($accounts === 0 && $latestIso === '') {
        $idleLabel = 'Sans compte';
        $idleKey = 'empty';
        $idleSort = 100000;
    } elseif ($latestIso === '') {
        $idleLabel = 'Jamais';
        $idleKey = 'never';
        $idleSort = 100000;
    } elseif ($idleDays === 0) {
        $idleLabel = 'Aujourd’hui';
        $idleKey = 'active';
        $idleSort = 0;
    } else {
        $idleLabel = $idleDays . ' jour' . ($idleDays > 1 ? 's' : '');
        $idleKey = $idleDays > 30 ? 'idle' : 'active';
        $idleSort = $idleDays;
    }
    $traceBits = [];
    if ($accounts > 0) {
        $traceBits[] = $accounts . ' compte' . ($accounts > 1 ? 's' : '');
    }
    if ($terminals > 0) {
        $traceBits[] = $terminals . ' ATAK';
    }
    if ($mapUnits > 0) {
        $traceBits[] = $mapUnits . ' sur la carte';
    }
    if ($profiles > 0) {
        $traceBits[] = $profiles . ' profil' . ($profiles > 1 ? 's' : '');
    }
    if ($sessions > 0) {
        $traceBits[] = $sessions . ' session' . ($sessions > 1 ? 's' : '');
    }
    $planOptions[$planLabel] = $planLabel;
    $sheet[] = [
        'id' => $id,
        'name' => $name,
        'slug' => $slug,
        'user_count' => (int) ($t['user_count'] ?? 0),
        'type_label' => $typeLabel,
        'type_pill' => $typePills[$tenantType] ?? 'slate',
        'plan_label' => $planLabel,
        'base_plan_label' => $planSlug !== $basePlanSlug ? $basePlanLabel : '',
        'gift_label' => $giftLabel,
        'status_label' => $statusLabel,
        'created_display' => $createdDisplay !== '' ? $createdDisplay : '—',
        'created_iso' => $iso($t['created_at'] ?? null),
        'login_display' => $loginIso !== '' ? $fmt($t['last_login_at'] ?? null) : '—',
        'login_iso' => $loginIso,
        'game_display' => $gameIso !== '' ? $fmt($t['last_game_at'] ?? null) : '—',
        'game_iso' => $gameIso,
        'idle_label' => $idleLabel,
        'idle_key' => $idleKey,
        'idle_sort' => $idleSort,
        'trace_label' => $traceBits === [] ? 'Aucune trace' : implode(' · ', $traceBits),
        'trace_total' => $accounts + $terminals + $mapUnits + $profiles + $sessions,
        'archived' => $archived,
        'public_url' => $slug !== '' && !$archived ? url('c/' . rawurlencode($slug)) : '',
        'edit_url' => $id > 0 ? url('admin/tenants/' . $id . '/edit') : '',
        'intervention_url' => $id > 1 ? url('admin/system/tenants/' . $id . '/intervention') : '',
        'removal_url' => $id > 1 && $slug !== 'default' ? url('admin/tenants/' . $id . '/retrait') : '',
    ];
}
ksort($planOptions, SORT_NATURAL | SORT_FLAG_CASE);
$communityCount = count($sheet);
?>
<div class="pa pa--wide" data-corr-url="<?= $h((string) ($platformCorrelationUrl ?? url('admin/system/donnees/correlations'))) ?>">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Communautés
            </p>
            <h1 class="pa-hero__title">Annuaire des communautés</h1>
            <p class="pa-hero__lead">
                Toute la largeur pour comparer les communautés : inactivité, traces rattachées, formule et abonnement. Ouvrez Corrélations pour le détail, Administrer pour la fiche, ou Accès super tenant pour intervenir.
            </p>
            <p class="pa-hero__lead">
                Pour retirer une communauté (doublon, test), archivez-la d’abord : rien n’est effacé et elle se restaure. La suppression définitive vient ensuite, après sauvegarde.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--solid" href="<?= $h(url('communities/create')) ?>">Créer une communauté</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('communities')) ?>">Annuaire public</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/annuaire')) ?>">Épingler dans l’annuaire</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/subscription-plans')) ?>">Formules d’accès</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/deployment')) ?>">Publications</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/tenant-recovery')) ?>">Récupération orpheline</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?>
                <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
            <?php endif; ?>
            <?php if ($err): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
            <?php endif; ?>

            <div class="pa-sheet" data-pa-sheet>
                <div class="pa-sheet__bar">
                    <label class="pa-sheet__field pa-sheet__field--grow">
                        Rechercher
                        <input type="search" data-pa-sheet-q placeholder="Nom, profil, formule, abonnement" autocomplete="off">
                    </label>
                    <label class="pa-sheet__field">
                        Profil
                        <select data-pa-sheet-filter="profil">
                            <option value="">Tous</option>
                            <?php foreach (\App\Services\Community\TenantTypeConfig::availableTypes() as $type): ?>
                                <option value="<?= $h((string) ($type['label'] ?? '')) ?>"><?= $h((string) ($type['label'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="pa-sheet__field">
                        Formule
                        <select data-pa-sheet-filter="formule">
                            <option value="">Toutes</option>
                            <?php foreach ($planOptions as $planOption): ?>
                                <option value="<?= $h($planOption) ?>"><?= $h($planOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="pa-sheet__field">
                        Abonnement
                        <select data-pa-sheet-filter="abonnement">
                            <option value="">Tous</option>
                            <?php foreach ($statusLabels as $statusLabelOption): ?>
                                <option value="<?= $h((string) $statusLabelOption) ?>"><?= $h((string) $statusLabelOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="pa-sheet__field">
                        Situation
                        <select data-pa-sheet-filter="situation">
                            <option value="">Toutes</option>
                            <option value="en-service">En service</option>
                            <option value="archivee">Archivées</option>
                        </select>
                    </label>
                    <p class="pa-sheet__count" data-pa-sheet-count aria-live="polite">
                        <?= $communityCount ?> communauté<?= $communityCount > 1 ? 's' : '' ?>
                    </p>
                    <button class="pa-btn pa-btn--line" type="button" data-pa-sheet-reset>Tout afficher</button>
                </div>
                <div class="pa-sheet__flags" role="group" aria-label="Signaux">
                    <button type="button" class="pa-sheet__flag is-on" data-pa-flag="">Toutes</button>
                    <button type="button" class="pa-sheet__flag" data-pa-flag="active">Actives depuis 30 jours</button>
                    <button type="button" class="pa-sheet__flag" data-pa-flag="idle">Inactives</button>
                    <button type="button" class="pa-sheet__flag" data-pa-flag="never">Jamais vues</button>
                    <button type="button" class="pa-sheet__flag" data-pa-flag="empty">Sans compte</button>
                    <button type="button" class="pa-sheet__flag" data-pa-flag="archived">Archivées</button>
                </div>

                <div class="pa-sheet__scroll">
                    <table class="pa-sheet__grid">
                        <thead>
                            <tr>
                                <th aria-sort="ascending" data-col="name"><button class="pa-sheet__sort" type="button" data-sort="text" data-col="0">Communauté</button></th>
                                <th data-col="profil"><button class="pa-sheet__sort" type="button" data-sort="text" data-col="1">Profil</button></th>
                                <th data-col="formule"><button class="pa-sheet__sort" type="button" data-sort="text" data-col="2">Formule</button></th>
                                <th data-col="abonnement"><button class="pa-sheet__sort" type="button" data-sort="text" data-col="3">Abonnement</button></th>
                                <th class="pa-sheet__num" data-col="comptes"><button class="pa-sheet__sort" type="button" data-sort="num" data-col="4">Comptes</button></th>
                                <th data-col="connexion"><button class="pa-sheet__sort" type="button" data-sort="date" data-col="5">Dernière connexion</button></th>
                                <th data-col="jeu"><button class="pa-sheet__sort" type="button" data-sort="date" data-col="6">Activité en jeu</button></th>
                                <th data-col="inactivite"><button class="pa-sheet__sort" type="button" data-sort="num" data-col="7">Inactivité</button></th>
                                <th data-col="traces"><button class="pa-sheet__sort" type="button" data-sort="num" data-col="8">Rattachés</button></th>
                                <th data-col="creation"><button class="pa-sheet__sort" type="button" data-sort="date" data-col="9">Création</button></th>
                                <th data-col="actions">Actions</th>
                            </tr>
                            <tr class="pa-sheet__filters">
                                <th><input type="search" data-col-filter="0" placeholder="Filtrer" aria-label="Filtrer la communauté"></th>
                                <th><input type="search" data-col-filter="1" placeholder="Filtrer" aria-label="Filtrer le profil"></th>
                                <th><input type="search" data-col-filter="2" placeholder="Filtrer" aria-label="Filtrer la formule"></th>
                                <th><input type="search" data-col-filter="3" placeholder="Filtrer" aria-label="Filtrer l’abonnement"></th>
                                <th><input type="search" data-col-filter="4" placeholder="Filtrer" aria-label="Filtrer les comptes"></th>
                                <th><input type="search" data-col-filter="5" placeholder="Filtrer" aria-label="Filtrer la dernière connexion"></th>
                                <th><input type="search" data-col-filter="6" placeholder="Filtrer" aria-label="Filtrer l’activité en jeu"></th>
                                <th><input type="search" data-col-filter="7" placeholder="Filtrer" aria-label="Filtrer l’inactivité"></th>
                                <th><input type="search" data-col-filter="8" placeholder="Filtrer" aria-label="Filtrer les éléments rattachés"></th>
                                <th><input type="search" data-col-filter="9" placeholder="Filtrer" aria-label="Filtrer la création"></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($sheet === []): ?>
                                <tr>
                                    <td colspan="11" class="pa-empty">Aucune communauté enregistrée (hors espace technique).</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sheet as $row): ?>
                                    <?php
                                    $haystack = implode(' ', array_filter([
                                        $row['name'],
                                        $row['type_label'],
                                        $row['plan_label'],
                                        $row['base_plan_label'],
                                        $row['gift_label'],
                                        $row['status_label'],
                                        $row['created_display'],
                                        $row['login_display'],
                                        $row['game_display'],
                                        $row['idle_label'],
                                        $row['trace_label'],
                                        (string) $row['user_count'],
                                        $row['archived'] ? 'Archivée' : 'En service',
                                    ], static fn (string $part): bool => $part !== '' && $part !== '—'));
                                    ?>
                                    <tr
                                        data-pa-row
                                        data-profil="<?= $h($row['type_label']) ?>"
                                        data-formule="<?= $h($row['plan_label']) ?>"
                                        data-abonnement="<?= $h($row['status_label']) ?>"
                                        data-situation="<?= $row['archived'] ? 'archivee' : 'en-service' ?>"
                                        data-idle="<?= $h($row['idle_key']) ?>"
                                        data-haystack="<?= $h($haystack) ?>"
                                    >
                                        <td class="pa-sheet__name" data-sort-value="<?= $h($row['name']) ?>">
                                            <p class="pa-name"><?= $h($row['name']) ?><?php if ($row['archived']): ?> <span class="pa-pill pa-pill--amber">Archivée</span><?php endif; ?></p>
                                            <?php if ($row['public_url'] !== ''): ?>
                                                <p class="pa-sub"><a href="<?= $h($row['public_url']) ?>" target="_blank" rel="noopener">Page publique</a></p>
                                            <?php endif; ?>
                                        </td>
                                        <td data-sort-value="<?= $h($row['type_label']) ?>"><span class="pa-pill pa-pill--<?= $h($row['type_pill']) ?>"><?= $h($row['type_label']) ?></span></td>
                                        <td class="pa-sheet__wrap" data-sort-value="<?= $h($row['plan_label']) ?>">
                                            <span class="pa-pill pa-pill--mint"><?= $h($row['plan_label']) ?></span>
                                            <?php if ($row['gift_label'] !== ''): ?>
                                                <p class="pa-sub"><?= $h($row['gift_label']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($row['base_plan_label'] !== ''): ?>
                                                <p class="pa-sub">Formule de base : <?= $h($row['base_plan_label']) ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td data-sort-value="<?= $h($row['status_label']) ?>"><?= $h($row['status_label']) ?></td>
                                        <td class="pa-sheet__num" data-sort-value="<?= (int) $row['user_count'] ?>"><?= (int) $row['user_count'] ?></td>
                                        <td data-sort-value="<?= $h($row['login_iso']) ?>"><?= $h($row['login_display']) ?></td>
                                        <td data-sort-value="<?= $h($row['game_iso']) ?>"><?= $h($row['game_display']) ?></td>
                                        <td data-sort-value="<?= (int) $row['idle_sort'] ?>"><span class="pa-pill pa-pill--<?= $row['idle_key'] === 'active' ? 'mint' : ($row['idle_key'] === 'idle' ? 'amber' : 'slate') ?>"><?= $h($row['idle_label']) ?></span></td>
                                        <td class="pa-sheet__wrap" data-sort-value="<?= (int) $row['trace_total'] ?>">
                                            <span><?= $h($row['trace_label']) ?></span>
                                            <button class="pa-sheet__corr" type="button" data-corr-id="<?= (int) $row['id'] ?>">Corrélations</button>
                                        </td>
                                        <td data-sort-value="<?= $h($row['created_iso']) ?>"><?= $h($row['created_display']) ?></td>
                                        <td>
                                            <?php if ($row['edit_url'] !== ''): ?>
                                                <div class="pa-sheet__actions">
                                                    <a href="<?= $h($row['edit_url']) ?>">Administrer</a>
                                                    <?php if ($row['intervention_url'] !== ''): ?>
                                                        <a href="<?= $h($row['intervention_url']) ?>">Accès super tenant</a>
                                                    <?php endif; ?>
                                                    <?php if ($row['removal_url'] !== ''): ?>
                                                        <a href="<?= $h($row['removal_url']) ?>"><?= $row['archived'] ? 'Restaurer ou supprimer' : 'Archiver ou supprimer' ?></a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="pa-sheet__empty" data-pa-sheet-empty hidden>Aucune communauté ne correspond à cette recherche.</p>
            </div>

            <section class="pa-card" style="margin-top:1.25rem;" aria-labelledby="plans-heading">
                <p class="pa-card__kicker">Offre</p>
                <h2 id="plans-heading" class="pa-card__title">Formules d’accès au service</h2>
                <p class="pa-card__help">Paliers proposés aux communautés. Pour en attribuer un, ouvrez Administrer dans le tableau.</p>
                <?php if ($plansError !== ''): ?>
                    <p class="pa-flash pa-flash--warn" style="margin-top:1rem;"><?= $h($plansError) ?></p>
                <?php elseif ($plans === []): ?>
                    <p class="pa-card__help" style="margin-top:1rem;">Aucune formule enregistrée.</p>
                <?php else: ?>
                    <div class="pa-table-wrap" style="margin-top:1rem;box-shadow:none;">
                        <table class="pa-table" style="min-width:36rem;">
                            <thead>
                                <tr>
                                    <th>Intitulé</th>
                                    <th>Ordre</th>
                                    <th>Paiement récurrent</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plans as $p): ?>
                                    <?php
                                    $pid = (int) ($p['id'] ?? 0);
                                    $pname = (string) ($p['name'] ?? '');
                                    $sort = (int) ($p['sort_order'] ?? 0);
                                    $m = trim((string) ($p['stripe_price_id_monthly'] ?? ''));
                                    $y = trim((string) ($p['stripe_price_id_yearly'] ?? ''));
                                    $billing = ($m !== '' || $y !== '') ? 'Réglages présents' : 'Non renseigné';
                                    ?>
                                    <tr>
                                        <td class="pa-name"><?= $h($pname) ?></td>
                                        <td><?= $sort ?></td>
                                        <td><?= $h($billing) ?></td>
                                        <td>
                                            <?php if ($pid > 0): ?>
                                                <a href="<?= $h(url('admin/system/subscription-plans/' . $pid . '/edit')) ?>">Modifier le palier</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
<div class="adr-drawer" data-pa-drawer hidden>
    <div class="adr-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="pa-corr-title">
        <header class="adr-drawer__head">
            <div>
                <p class="adr-drawer__kicker" data-pa-drawer-kicker>Communauté</p>
                <h2 class="adr-drawer__title" id="pa-corr-title" data-pa-drawer-title>Corrélations</h2>
            </div>
            <button type="button" class="pa-btn pa-btn--line" data-pa-drawer-close>Fermer</button>
        </header>
        <div class="adr-drawer__body" data-pa-drawer-body></div>
    </div>
</div>
<script src="<?= $h(asset_url('assets/js/platform-tenants-sheet.js')) ?>"></script>
