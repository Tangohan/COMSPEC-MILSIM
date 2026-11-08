<?php
declare(strict_types=1);

use App\Controllers\Admin\System\SystemUsersController;
use App\Services\Admin\PlatformDataVolumeService;
use App\Support\PlatformUserColumns;
use App\Support\PlatformUsersDirectoryQuery;

$groups = is_array($platformUserGroups ?? null) ? $platformUserGroups : [];
$total = (int) ($platformUsersTotal ?? 0);
$page = max(1, (int) ($platformUsersPage ?? 1));
$pages = max(1, (int) ($platformUsersPages ?? 1));
// Anciennes variables (q, status, tenant, inactif) encore acceptées si la vue est appelée sans « platformUsersFilters ».
$filters = is_array($platformUsersFilters ?? null) ? $platformUsersFilters : PlatformUsersDirectoryQuery::normalize([
    'q' => $platformUsersQ ?? '',
    'status' => $platformUsersStatus ?? '',
    'tenant_id' => $platformUsersTenantId ?? 0,
    'inactif' => $platformUsersInactivity ?? '',
]);
$columns = is_array($platformUserColumns ?? null) ? array_values(array_map('strval', $platformUserColumns)) : PlatformUserColumns::DEFAULT;
$q = (string) ($filters['q'] ?? '');
$statusFilter = (string) ($filters['status'] ?? '');
$tenantFilter = (int) ($filters['tenant_id'] ?? 0);
$inactivityFilter = (string) ($filters['inactif'] ?? '');
$createdFilter = (string) ($filters['cree'] ?? '');
$sanctionFilter = (string) ($filters['sanction'] ?? '');
$sortKey = (string) ($filters['tri'] ?? '');
$sortDir = (string) ($filters['sens'] ?? 'asc');
$perPage = (int) ($filters['par'] ?? PlatformUsersDirectoryQuery::DEFAULT_PER_PAGE);
$tenants = is_array($platformTenants ?? null) ? $platformTenants : [];
$bansCount = (int) ($platformBansCount ?? 0);
$inactiveCount = isset($platformInactiveCount) && is_int($platformInactiveCount) ? $platformInactiveCount : null;
$banSupportsEnd = !empty($platformBanSupportsEnd);
$pendingPurgeRequests = is_array($pendingPurgeRequests ?? null) ? $pendingPurgeRequests : [];
$pendingPurgeRequestsCount = (int) ($pendingPurgeRequestsCount ?? count($pendingPurgeRequests));

$currentActorId = (int) \App\Core\Session::get('user_id');
// Tout ce qui passe par $h est converti en texte : sous strict_types, un entier ou une clé de tableau
// numérique ('30', '90'…) donnerait une erreur 500 avec une signature (string).
$h = static fn (string|int|float|null $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$csrf = $h(\App\Core\Csrf::token());
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');

$statusOptions = [
    '' => 'Tous les comptes',
    'active' => 'Actifs',
    'inactive' => 'Accès suspendu',
    'pending_verification' => 'E-mail pas encore vérifié',
    'no_org' => 'Sans communauté',
    'banned' => 'Bannis du site',
    'inactifs' => 'Inactifs (ni site ni jeu)',
    'deleted' => 'Comptes anonymisés',
];
$inactivityOptions = [
    '' => 'Peu importe',
    '30' => 'Plus de 30 jours',
    '90' => 'Plus de 90 jours',
    '180' => 'Plus de 180 jours',
    'jamais' => 'Jamais connecté',
];
$createdOptions = [
    '' => 'Peu importe',
    '7' => 'Les 7 derniers jours',
    '30' => 'Les 30 derniers jours',
    '90' => 'Les 90 derniers jours',
];
$sanctionOptions = [
    '' => 'Peu importe',
    'passee' => 'Déjà sanctionnés au moins une fois',
];
$sortOptions = [
    '' => 'Modifiés récemment',
    'nom' => 'Nom',
    'indicatif' => 'Indicatif',
    'email' => 'E-mail',
    'communautes' => 'Nombre de communautés',
    'etat' => 'État',
    'connexion_site' => 'Dernière connexion au site',
    'connexion_jeu' => 'Dernière connexion en jeu',
    'creation' => 'Création du compte',
    'volume' => 'Données reçues (30 jours)',
    'sanctions' => 'Sanctions passées',
];
// Premier clic sur une colonne : ordre naturel (A→Z pour le texte, le plus récent ou le plus gros d'abord pour le reste).
$descFirst = ['connexion_site', 'connexion_jeu', 'creation', 'volume', 'sanctions', 'communautes'];
$sortable = ['nom', 'indicatif', 'email', 'communautes', 'etat', 'connexion_site', 'connexion_jeu', 'creation', 'volume', 'sanctions'];

$directoryUrl = static function (array $override = []) use ($filters): string {
    $query = PlatformUsersDirectoryQuery::query($filters, $override);

    return url('admin/users') . ($query !== '' ? '?' . $query : '');
};
$returnQs = PlatformUsersDirectoryQuery::query($filters);
$hiddenReturns = static function () use ($h, $returnQs): string {
    return '<input type="hidden" name="return_qs" value="' . $h($returnQs) . '">';
};
$sortUrl = static function (string $col) use ($directoryUrl, $sortKey, $sortDir, $descFirst): string {
    if ($sortKey === $col) {
        $dir = $sortDir === 'asc' ? 'desc' : 'asc';
    } else {
        $dir = in_array($col, $descFirst, true) ? 'desc' : 'asc';
    }

    return $directoryUrl(['tri' => $col, 'sens' => $dir, 'page' => 1]);
};

$fmtDate = static function (?string $raw): string {
    $ts = $raw !== null && $raw !== '' ? strtotime($raw) : false;

    return $ts ? date('d/m/Y', $ts) : '';
};
$fmtDateTime = static function (?string $raw): string {
    $ts = $raw !== null && $raw !== '' ? strtotime($raw) : false;

    return $ts ? date('d/m/Y H:i', $ts) : '';
};
/** Date de fin d'une sanction, à l'heure de Paris. */
$fmtParis = static function (?string $raw): string {
    if ($raw === null || $raw === '') {
        return '';
    }
    try {
        return (new \DateTimeImmutable($raw, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i');
    } catch (\Throwable) {
        return '';
    }
};

/** Formulaire POST discret ; la confirmation (fenêtre) est gérée par le script de la page. */
$actionForm = static function (string $action, array $fields, string $label, string $btnClass, array $confirm = []) use ($h, $csrf, $hiddenReturns): string {
    $attrs = '';
    foreach ($confirm as $k => $v) {
        $attrs .= ' data-confirm-' . $h((string) $k) . '="' . $h((string) $v) . '"';
    }
    $html = '<form method="post" action="' . $h(url($action)) . '" class="pu-inline"' . ($confirm !== [] ? ' data-pu-confirm="1"' : '') . $attrs . '>'
        . '<input type="hidden" name="_csrf_token" value="' . $csrf . '">';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . $h((string) $name) . '" value="' . $h((string) $value) . '">';
    }

    return $html . $hiddenReturns() . '<button type="submit" class="' . $h($btnClass) . '">' . $h($label) . '</button></form>';
};

$statePill = static function (string $state): string {
    return match ($state) {
        'banni' => 'pa-pill--rose',
        'actif' => 'pa-pill--mint',
        'verif', 'suspendu' => 'pa-pill--amber',
        default => 'pa-pill--slate',
    };
};
$filtersActive = $q !== '' || $statusFilter !== '' || $tenantFilter > 0 || $inactivityFilter !== '' || $createdFilter !== '' || $sanctionFilter !== '';
$colCount = count($columns) + 1;
$exportAllUrl = url('admin/users/export') . '?' . PlatformUsersDirectoryQuery::query($filters, ['page' => 1, 'cols' => $columns]);
?>
<div class="pa pu pa--wide">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Comptes utilisateurs
            </p>
            <div class="pa-hero__row">
                <div>
                    <p class="pa-hero__kicker">Comptes &amp; accès</p>
                    <h1 class="pa-hero__title">Comptes utilisateurs</h1>
                    <p class="pa-hero__lead">
                        Un tableau, une ligne par personne. Triez, filtrez, choisissez les colonnes, cochez des lignes pour les exporter.
                        Ouvrez « Détails » pour agir sur une communauté ou sur le compte entier : chaque action explique ce qu’elle fait avant de s’exécuter.
                    </p>
                    <div class="pa-hero__actions">
                        <button type="button" class="pa-btn pa-btn--solid" data-pu-open="pu-guide">Quelle action choisir ?</button>
                        <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/users/donnees')) ?>">Données par jour</a>
                        <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/blocklist')) ?>">Adresses IP et e-mails bloqués</a>
                        <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/tenants')) ?>">Communautés</a>
                    </div>
                </div>
                <aside class="pa-hero__meta">
                    <p class="pa-hero__meta-kicker">Aperçu</p>
                    <dl>
                        <div class="pa-hero__meta-row">
                            <dt>Personnes trouvées</dt>
                            <dd><?= $h(number_format($total, 0, ',', ' ')) ?></dd>
                        </div>
                        <div class="pa-hero__meta-row">
                            <dt>Bannies du site</dt>
                            <dd><a class="pu-meta-link" href="<?= $h($directoryUrl(['status' => 'banned', 'page' => 1])) ?>"><?= $h($bansCount) ?></a></dd>
                        </div>
                        <div class="pa-hero__meta-row">
                            <dt>Demandes de suppression</dt>
                            <dd><?= $h($pendingPurgeRequestsCount) ?></dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </header>

        <?php if (is_string($flashOk) && trim($flashOk) !== ''): ?>
            <p class="pa-flash pa-flash--ok" role="status"><?= $h($flashOk) ?></p>
        <?php endif; ?>
        <?php if (is_string($flashErr) && trim($flashErr) !== ''): ?>
            <p class="pa-flash pa-flash--err" role="alert"><?= $h($flashErr) ?></p>
        <?php endif; ?>

        <?php if ($pendingPurgeRequests !== []): ?>
            <section id="demandes-purge" class="pa-card pu-requests" aria-labelledby="purge-requests-title">
                <p class="pa-card__kicker">À traiter</p>
                <h2 id="purge-requests-title" class="pa-card__title">
                    Demandes de suppression de compte
                    <span class="pa-pill pa-pill--amber"><?= $h($pendingPurgeRequestsCount) ?></span>
                </h2>
                <p class="pa-card__help">
                    Un gestionnaire de communauté demande à effacer la fiche d’un ancien membre de sa communauté.
                    « Accepter » efface cette fiche dans cette communauté seulement : les messages et événements restent signés
                    « Ancien membre ». Les autres communautés de la personne ne bougent pas.
                </p>
                <ul class="pu-requests__list">
                    <?php foreach ($pendingPurgeRequests as $req): ?>
                        <?php
                        $reqId = (int) ($req['id'] ?? 0);
                        $reqTenant = (string) ($req['tenant_name'] ?? '');
                        $reqTarget = (string) ($req['target_display_name'] ?? 'Compte supprimé');
                        $reqEmail = (string) ($req['target_email'] ?? '');
                        $reqBy = (string) ($req['requester_display_name'] ?? '');
                        if ($reqBy === '') {
                            $reqBy = (string) ($req['requester_email'] ?? 'Gestionnaire');
                        }
                        $reqNote = trim((string) ($req['note'] ?? ''));
                        ?>
                        <li class="pu-request">
                            <div class="pu-request__body">
                                <p class="pu-request__who"><?= $h($reqTarget) ?> <span class="pu-muted">· <?= $h($reqTenant !== '' ? $reqTenant : '—') ?></span></p>
                                <?php if ($reqEmail !== ''): ?>
                                    <p class="pu-mono"><?= $h($reqEmail) ?></p>
                                <?php endif; ?>
                                <p class="pu-muted">Demandé par <?= $h($reqBy) ?> le <?= $h($fmtDate((string) ($req['created_at'] ?? ''))) ?></p>
                                <?php if ($reqNote !== ''): ?>
                                    <p class="pu-request__note"><?= $h($reqNote) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="pu-actions">
                                <?= $actionForm('admin/users/purge-requests/approve', ['request_id' => $reqId], 'Accepter et effacer la fiche', 'pa-btn pa-btn--sm pu-btn-danger', [
                                    'title' => 'Effacer la fiche de ' . $reqTarget . ' dans « ' . $reqTenant . ' » ?',
                                    'body' => "La fiche de cette communauté est effacée définitivement.\nL’historique reste signé « Ancien membre ».\nLes autres communautés de la personne ne changent pas.",
                                    'tone' => 'danger',
                                    'ok' => 'Effacer la fiche',
                                ]) ?>
                                <?= $actionForm('admin/users/purge-requests/reject', ['request_id' => $reqId], 'Refuser', 'pa-btn pa-btn--sm pa-btn--line') ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <nav class="pu-tabs" aria-label="Catégories de comptes">
            <a class="pu-tab<?= $statusFilter !== 'inactifs' && $statusFilter !== 'banned' ? ' is-on' : '' ?>" href="<?= $h($directoryUrl(['status' => '', 'page' => 1])) ?>"<?= $statusFilter !== 'inactifs' && $statusFilter !== 'banned' ? ' aria-current="page"' : '' ?>>Tous les comptes</a>
            <a class="pu-tab<?= $statusFilter === 'inactifs' ? ' is-on' : '' ?>" href="<?= $h($directoryUrl(['status' => 'inactifs', 'page' => 1])) ?>"<?= $statusFilter === 'inactifs' ? ' aria-current="page"' : '' ?> title="Ni connexion au site ni session de jeu depuis le seuil choisi (90 jours par défaut, personnes jamais connectées comprises)">Inactifs<?php if ($inactiveCount !== null): ?> <span class="pu-count-pill"><?= $h($inactiveCount) ?></span><?php endif; ?></a>
            <a class="pu-tab<?= $statusFilter === 'banned' ? ' is-on' : '' ?>" href="<?= $h($directoryUrl(['status' => 'banned', 'page' => 1])) ?>"<?= $statusFilter === 'banned' ? ' aria-current="page"' : '' ?>>Bannis <span class="pu-count-pill"><?= $h($bansCount) ?></span></a>
        </nav>
        <?php if ($statusFilter === 'inactifs'): ?>
            <p class="pu-muted pu-tabs__note">Personnes sans connexion au site ni session de jeu depuis <?= $inactivityFilter === 'jamais' ? 'toujours (jamais connectées)' : $h($inactivityFilter !== '' ? $inactivityFilter : '90') . ' jours' ?>. Le seuil se règle avec « Sans connexion depuis ».</p>
        <?php endif; ?>

        <form method="get" action="<?= $h(url('admin/users')) ?>" class="pa-filters pu-filters" id="pu-filters">
            <div class="pu-filters__grid">
                <div class="pa-field pu-filters__q">
                    <label for="platform-users-q">Rechercher une personne</label>
                    <input id="platform-users-q" type="search" name="q" value="<?= $h($q) ?>" placeholder="E-mail, nom ou indicatif" maxlength="120">
                </div>
                <div class="pa-field">
                    <label for="platform-users-tenant">Communauté</label>
                    <select id="platform-users-tenant" name="tenant_id">
                        <option value="">Toutes</option>
                        <?php foreach ($tenants as $t): ?>
                            <?php $tid = (int) ($t['id'] ?? 0); ?>
                            <option value="<?= $h($tid) ?>" <?= $tid === $tenantFilter ? 'selected' : '' ?>><?= $h(community_display_name($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="platform-users-status">État</label>
                    <select id="platform-users-status" name="status">
                        <?php foreach ($statusOptions as $value => $label): $value = (string) $value; ?>
                            <option value="<?= $h($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="platform-users-inactif">Sans connexion depuis</label>
                    <select id="platform-users-inactif" name="inactif">
                        <?php foreach ($inactivityOptions as $value => $label): $value = (string) $value; ?>
                            <option value="<?= $h($value) ?>" <?= $inactivityFilter === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="platform-users-cree">Compte créé</label>
                    <select id="platform-users-cree" name="cree">
                        <?php foreach ($createdOptions as $value => $label): $value = (string) $value; ?>
                            <option value="<?= $h($value) ?>" <?= $createdFilter === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="platform-users-sanction">Sanctions</label>
                    <select id="platform-users-sanction" name="sanction">
                        <?php foreach ($sanctionOptions as $value => $label): $value = (string) $value; ?>
                            <option value="<?= $h($value) ?>" <?= $sanctionFilter === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="platform-users-tri">Trier par</label>
                    <select id="platform-users-tri" name="tri">
                        <?php foreach ($sortOptions as $value => $label): $value = (string) $value; ?>
                            <option value="<?= $h($value) ?>" <?= $sortKey === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="platform-users-sens">Sens</label>
                    <select id="platform-users-sens" name="sens">
                        <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>Croissant (A→Z, plus ancien)</option>
                        <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Décroissant (Z→A, plus récent)</option>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="platform-users-par">Lignes par page</label>
                    <select id="platform-users-par" name="par">
                        <?php foreach (PlatformUsersDirectoryQuery::PER_PAGE as $n): ?>
                            <option value="<?= $h($n) ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $h($n) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pu-filters__buttons">
                    <button type="submit" class="pa-btn pa-btn--solid">Filtrer</button>
                    <?php if ($filtersActive || $sortKey !== ''): ?>
                        <a href="<?= $h(url('admin/users')) ?>" class="pa-btn pa-btn--line">Tout afficher</a>
                    <?php endif; ?>
                </div>
            </div>

            <details class="pu-cols">
                <summary class="pa-btn pa-btn--sm pa-btn--line">Colonnes affichées (<?= $h(count($columns)) ?>)</summary>
                <div class="pu-cols__menu">
                    <input type="hidden" name="cols_set" value="1">
                    <input type="hidden" name="cols[]" value="nom">
                    <?php foreach (PlatformUserColumns::LABELS as $key => $label): $key = (string) $key; ?>
                        <label class="pu-check">
                            <input type="checkbox" name="cols[]" value="<?= $h($key) ?>"<?= in_array($key, $columns, true) ? ' checked' : '' ?><?= $key === 'nom' ? ' disabled' : '' ?>>
                            <?= $h($label) ?>
                        </label>
                    <?php endforeach; ?>
                    <button type="submit" class="pa-btn pa-btn--sm pa-btn--solid">Appliquer</button>
                </div>
            </details>
            <?php if ($tenantFilter > 0): ?>
                <p class="pu-filters__note">Le filtre par communauté montre ses membres actuels. Une personne retirée de cette communauté reste visible sans ce filtre, avec la mention « Retiré ».</p>
            <?php endif; ?>
        </form>

        <div class="pu-bar">
            <p class="pu-count">
                <?= $total === 0 ? 'Aucune personne trouvée.' : ($total === 1 ? '1 personne' : $h(number_format($total, 0, ',', ' ')) . ' personnes') ?>
                <?php if ($pages > 1): ?>
                    <span class="pu-muted">· page <?= $h($page) ?> sur <?= $h($pages) ?></span>
                <?php endif; ?>
                <span class="pu-muted" data-pu-selected-count hidden></span>
            </p>
            <div class="pu-bar__actions">
                <button type="submit" form="pu-select-form" class="pa-btn pa-btn--sm pa-btn--line" data-pu-export-selection disabled>Exporter la sélection (CSV)</button>
                <a class="pa-btn pa-btn--sm pa-btn--line" href="<?= $h($exportAllUrl) ?>" title="Jusqu’à 5 000 personnes, avec les filtres et les colonnes affichés">Exporter tous les résultats (CSV)</a>
            </div>
        </div>
        <p class="pu-muted pu-bar__note">L’export contient des e-mails : il est réservé à l’administration du site et chaque export est inscrit au journal.</p>

        <form method="post" action="<?= $h(url('admin/users/export')) ?>" id="pu-select-form" class="pu-inline">
            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="return_qs" value="<?= $h($returnQs) ?>">
            <input type="hidden" name="cols_set" value="1">
            <?php foreach ($columns as $col): ?>
                <input type="hidden" name="cols[]" value="<?= $h($col) ?>">
            <?php endforeach; ?>
        </form>

        <?php if ($groups === []): ?>
            <div class="pa-card pu-empty">
                <p>Aucun compte ne correspond à ces critères.</p>
            </div>
        <?php else: ?>
        <div class="pa-sheet__scroll pu-sheet" role="region" aria-label="Tableau des comptes" tabindex="0">
            <table class="pa-sheet__grid pu-grid" id="pu-grid">
                <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <?php
                            $isSorted = $sortKey === $col;
                            $ariaSort = $isSorted ? ($sortDir === 'desc' ? 'descending' : 'ascending') : null;
                            ?>
                            <th scope="col" data-col="<?= $h($col) ?>" class="pu-th pu-th--<?= $h($col) ?>"<?= $ariaSort !== null ? ' aria-sort="' . $h($ariaSort) . '"' : '' ?>>
                                <?php if ($col === 'nom'): ?>
                                    <label class="pu-selall"><input type="checkbox" data-pu-select-all aria-label="Tout cocher sur cette page"></label>
                                <?php endif; ?>
                                <?php if (in_array($col, $sortable, true)): ?>
                                    <a class="pa-sheet__sort" href="<?= $h($sortUrl($col)) ?>"><?= $h(PlatformUserColumns::LABELS[$col] ?? $col) ?></a>
                                <?php else: ?>
                                    <?= $h(PlatformUserColumns::LABELS[$col] ?? $col) ?>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                        <th scope="col" class="pu-th pu-th--actions">Dossier</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $gi => $group): ?>
                    <?php
                    $email = (string) ($group['email'] ?? '');
                    $display = trim((string) ($group['display_name'] ?? ''));
                    $callsign = trim((string) ($group['callsign'] ?? ''));
                    $memberships = is_array($group['memberships'] ?? null) ? $group['memberships'] : [];
                    $anchor = is_array($group['account_anchor'] ?? null) ? $group['account_anchor'] : null;
                    $primary = $callsign !== '' ? $callsign : ($display !== '' ? $display : $email);
                    $ban = is_array($group['ban'] ?? null) ? $group['ban'] : null;
                    $banEnds = $ban !== null ? trim((string) ($ban['ends_at'] ?? '')) : '';
                    $state = (string) ($group['state'] ?? 'actif');
                    $communityNames = is_array($group['community_names'] ?? null) ? $group['community_names'] : [];
                    $sanctionsCount = (int) ($group['sanctions_count'] ?? 0);
                    $anchorId = (int) ($anchor['id'] ?? 0);
                    $anchorTid = (int) ($anchor['tenant_id'] ?? 0);
                    $isSelf = $anchorId === $currentActorId;
                    foreach ($memberships as $m) {
                        if ((int) ($m['id'] ?? 0) === $currentActorId) {
                            $isSelf = true;
                        }
                    }
                    $isAnonymized = $anchor !== null && !empty($anchor['deleted_at']);
                    $distinctLive = [];
                    foreach ($memberships as $m) {
                        if ((int) ($m['id'] ?? 0) > 0 && empty($m['deleted_at']) && (string) ($m['status'] ?? '') !== 'merged') {
                            $distinctLive[(int) $m['id']] = true;
                        }
                    }
                    $personUrl = url('admin/users/person') . '?email=' . rawurlencode($email);
                    $initial = mb_strtoupper(mb_substr($primary !== '' ? $primary : '?', 0, 1));
                    $detailId = 'pu-detail-' . (int) $gi;
                    ?>
                    <tr class="pu-tr<?= $ban !== null ? ' is-banned' : '' ?>" data-pu-row>
                        <?php foreach ($columns as $col): ?>
                            <?php if ($col === 'nom'): ?>
                                <td data-col="nom" data-label="Nom" class="pu-td pu-td--nom">
                                    <label class="pu-pick"><input type="checkbox" value="<?= $h($email) ?>" form="pu-select-form" name="emails[]" data-pu-pick aria-label="Sélectionner <?= $h($primary) ?>"></label>
                                    <span class="pu-avatar" aria-hidden="true"><?= $h($initial) ?></span>
                                    <span class="pu-name">
                                        <a class="pu-person__link<?= staff_name_class($anchorId) ?>" href="<?= $h($personUrl) ?>" data-staff-done><?= $h($display !== '' ? $display : $primary) ?></a><?= staff_mark($anchorId, true) ?>
                                        <?php if ($isAnonymized): ?>
                                            <?= \App\Support\ConfidentialDisplay::badge(\App\Support\ConfidentialDisplay::ACCOUNT_DELETED) ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                            <?php elseif ($col === 'indicatif'): ?>
                                <td data-col="indicatif" data-label="Indicatif" class="pu-td"><?= $callsign !== '' ? $h($callsign) : '<span class="pu-muted">—</span>' ?></td>
                            <?php elseif ($col === 'email'): ?>
                                <td data-col="email" data-label="E-mail" class="pu-td"><span class="pu-mono"><?= $h($email) ?></span></td>
                            <?php elseif ($col === 'communautes'): ?>
                                <td data-col="communautes" data-label="Communautés" class="pu-td">
                                    <?php if ($communityNames === []): ?>
                                        <span class="pa-pill pa-pill--slate" title="Aucune communauté active : la personne a un compte seul, elle peut rejoindre ou créer une communauté.">Sans communauté</span>
                                    <?php else: ?>
                                        <span class="pu-count-pill"><?= $h(count($communityNames)) ?></span>
                                        <span class="pu-muted pu-clip" title="<?= $h(implode(', ', array_map('strval', $communityNames))) ?>"><?= $h(implode(', ', array_map('strval', array_slice($communityNames, 0, 2)))) ?><?= count($communityNames) > 2 ? ' …' : '' ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php elseif ($col === 'etat'): ?>
                                <td data-col="etat" data-label="État" class="pu-td">
                                    <span class="pa-pill <?= $h($statePill($state)) ?>"><?= $h(SystemUsersController::STATE_LABELS[$state] ?? $state) ?></span>
                                    <?php if ($ban !== null): ?>
                                        <span class="pu-muted pu-block"><?= $banEnds !== '' ? 'jusqu’au ' . $h($fmtParis($banEnds)) : 'sans date de fin' ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php elseif ($col === 'connexion_site'): ?>
                                <?php $login = (string) ($group['last_login'] ?? ''); ?>
                                <td data-col="connexion_site" data-label="Connexion au site" class="pu-td"><?= $login !== '' ? $h($fmtDateTime($login)) : '<span class="pu-muted">Jamais</span>' ?></td>
                            <?php elseif ($col === 'connexion_jeu'): ?>
                                <?php $gameSeen = (string) ($group['last_game'] ?? ''); ?>
                                <td data-col="connexion_jeu" data-label="Connexion en jeu" class="pu-td"><?= $gameSeen !== '' ? $h($fmtDateTime($gameSeen)) : '<span class="pu-muted">Jamais</span>' ?></td>
                            <?php elseif ($col === 'creation'): ?>
                                <td data-col="creation" data-label="Création" class="pu-td"><?= $h($fmtDate((string) ($group['created'] ?? ''))) ?></td>
                            <?php elseif ($col === 'volume'): ?>
                                <td data-col="volume" data-label="Données (30 j)" class="pu-td pu-td--num">
                                    <?php if (!empty($group['has_steam'])): ?>
                                        <?= $h(PlatformDataVolumeService::formatBytes((int) ($group['volume_bytes'] ?? 0))) ?>
                                    <?php else: ?>
                                        <span class="pu-muted" title="Aucun compte de jeu relié : rien ne peut être mesuré pour cette personne.">Non mesurable</span>
                                    <?php endif; ?>
                                </td>
                            <?php elseif ($col === 'sanctions'): ?>
                                <td data-col="sanctions" data-label="Sanctions" class="pu-td pu-td--num">
                                    <?php if ($sanctionsCount > 0): ?>
                                        <a class="pu-link" href="<?= $h($personUrl . '#historique-sanctions') ?>"><?= $h($sanctionsCount) ?></a>
                                    <?php else: ?>
                                        <span class="pu-muted">0</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <td class="pu-td pu-td--actions" data-label="Dossier">
                            <a class="pa-btn pa-btn--sm pa-btn--line" href="<?= $h($personUrl) ?>">Dossier complet</a>
                            <button type="button" class="pa-btn pa-btn--sm pa-btn--ghost" data-pu-toggle="<?= $h($detailId) ?>" aria-expanded="false" aria-controls="<?= $h($detailId) ?>">Détails</button>
                        </td>
                    </tr>
                    <tr class="pu-detail" id="<?= $h($detailId) ?>" hidden>
                        <td colspan="<?= $h($colCount) ?>">
                            <div class="pu-detail__box">
                                <div class="pu-person__actions">
                                    <p class="pu-person__links">
                                        <a class="pu-link" href="<?= $h($personUrl) ?>">Dossier complet</a>
                                        <?php if ($anchorId > 0 && !$isAnonymized): ?>
                                            <a class="pu-link" href="<?= $h(url('admin/users/' . $anchorId . '/edit')) ?>">Modifier le compte</a>
                                        <?php endif; ?>
                                        <?php if (count($distinctLive) > 1): ?>
                                            <a class="pu-link pu-link--warn" href="<?= $h($personUrl . '#fusion-comptes') ?>">Réunir ses <?= $h(count($distinctLive)) ?> comptes</a>
                                        <?php endif; ?>
                                        <?php if ($sanctionsCount > 0): ?>
                                            <a class="pu-link" href="<?= $h($personUrl . '#historique-sanctions') ?>">Historique des sanctions (<?= $h($sanctionsCount) ?>)</a>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($ban !== null): ?>
                                        <p class="pu-ban-note">
                                            Banni le <?= $h($fmtDate((string) ($ban['created_at'] ?? ''))) ?>
                                            <?= $banEnds !== '' ? ', jusqu’au ' . $h($fmtParis($banEnds)) : ', sans date de fin' ?>
                                            <?php if (trim((string) ($ban['reason'] ?? '')) !== ''): ?>
                                                · motif : <?= $h((string) $ban['reason']) ?>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="pu-actions">
                                    <?php if ($isSelf): ?>
                                        <span class="pu-muted">C’est votre compte</span>
                                    <?php elseif (!$isAnonymized && $anchorId > 0): ?>
                                        <?php if ($ban !== null): ?>
                                            <?= $actionForm('admin/users/lever-bannissement', ['email' => $email], 'Lever le bannissement', 'pa-btn pa-btn--sm pa-btn--line', [
                                                'title' => 'Lever le bannissement de ' . $primary . ' ?',
                                                'body' => "La personne peut de nouveau se connecter et s’inscrire.\nSes fiches retrouvent l’état qu’elles avaient avant le bannissement.\nLes blocages (e-mail, Steam, IP) créés par ce bannissement sont retirés.",
                                                'ok' => 'Lever le bannissement',
                                            ]) ?>
                                        <?php else: ?>
                                            <button type="button" class="pa-btn pa-btn--sm pu-btn-danger-soft"
                                                    data-pu-ban-open
                                                    data-ban-email="<?= $h($email) ?>"
                                                    data-ban-label="<?= $h($primary) ?>"
                                                    data-ban-prior="<?= $h($sanctionsCount) ?>"
                                                    data-ban-history="<?= $h($personUrl . '#historique-sanctions') ?>">Bannir du site…</button>
                                        <?php endif; ?>
                                        <details class="pu-more">
                                            <summary class="pa-btn pa-btn--sm pa-btn--line" aria-label="Autres actions sur le compte">Supprimer…</summary>
                                            <div class="pu-more__menu">
                                                <p class="pu-more__title">Actions sur tout le compte</p>
                                                <?= $actionForm('admin/users/delete', ['user_id' => $anchorId, 'tenant_id' => $anchorTid, 'scope' => 'site'], 'Supprimer le compte', 'pu-menu-item', [
                                                    'title' => 'Supprimer le compte de ' . $primary . ' ?',
                                                    'body' => "Le compte, son compte de jeu, ses rôles et ses données personnelles sont effacés dans toutes ses communautés.\nSes communautés passent au membre suivant, ou sont supprimées s’il était seul.\nCe que d’autres membres partagent (messages, comptes rendus) reste, signé « Ancien membre ».\nUne nouvelle inscription avec cette adresse repart de zéro. On ne peut pas revenir en arrière.",
                                                    'tone' => 'danger',
                                                    'ok' => 'Supprimer le compte',
                                                ]) ?>
                                                <p class="pu-more__hint">Efface la personne, garde ce que les autres membres partagent. Pour une demande RGPD.</p>
                                                <?= $actionForm('admin/users/purge', ['user_id' => $anchorId, 'tenant_id' => $anchorTid, 'scope' => 'site', 'confirm_email' => ''], 'Supprimer définitivement', 'pu-menu-item pu-menu-item--danger', [
                                                    'title' => 'Supprimer définitivement le compte de ' . $primary . ' ?',
                                                    'body' => "Toutes les fiches de cette adresse sont effacées de la base, dans toutes ses communautés.\nRien ne peut être restauré.",
                                                    'tone' => 'danger',
                                                    'type' => $email,
                                                    'ok' => 'Supprimer définitivement',
                                                ]) ?>
                                                <p class="pu-more__hint">Efface tout. À réserver aux faux comptes et comptes de test.</p>
                                            </div>
                                        </details>
                                    <?php endif; ?>
                                    </div>
                                </div>

                                <div class="pu-rows">
                                    <?php if ($memberships === []): ?>
                                        <div class="pu-row pu-row--solo">
                                            <div class="pu-row__main">
                                                <p class="pu-row__name">Aucune communauté</p>
                                                <p class="pu-muted">Compte seul : la personne peut rejoindre une communauté avec un code ou en créer une.</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php foreach ($memberships as $m): ?>
                                        <?php
                                        $uid = (int) ($m['id'] ?? 0);
                                        $tid = (int) ($m['tenant_id'] ?? 0);
                                        $tenantName = (string) ($m['tenant_name'] ?? '');
                                        $roleName = trim((string) ($m['role_name'] ?? ''));
                                        $st = (string) ($m['status'] ?? '');
                                        $ms = (string) ($m['membership_status'] ?? 'active');
                                        $isDeleted = !empty($m['deleted_at']);
                                        $isLeft = !$isDeleted && $ms !== 'active';
                                        $mCallsign = trim((string) ($m['callsign'] ?? ''));
                                        $fields = ['user_id' => $uid, 'tenant_id' => $tid];
                                        if ($isDeleted) {
                                            [$pill, $pillText] = ['pa-pill--slate', 'Compte anonymisé'];
                                        } elseif ($isLeft) {
                                            $leftAt = $fmtDate((string) ($m['membership_left_at'] ?? ''));
                                            [$pill, $pillText] = ['pa-pill--slate', 'Retiré' . ($leftAt !== '' ? ' le ' . $leftAt : '')];
                                        } elseif ($st === 'active') {
                                            [$pill, $pillText] = ['pa-pill--mint', 'Membre actif'];
                                        } elseif ($st === 'pending_verification') {
                                            [$pill, $pillText] = ['pa-pill--amber', 'E-mail pas encore vérifié'];
                                        } else {
                                            [$pill, $pillText] = ['pa-pill--amber', 'Accès suspendu'];
                                        }
                                        ?>
                                        <div class="pu-row<?= $isLeft || $isDeleted ? ' is-past' : '' ?>">
                                            <div class="pu-row__main">
                                                <p class="pu-row__name"><?= $h($tenantName !== '' ? $tenantName : '—') ?></p>
                                                <p class="pu-muted">
                                                    <?= $h($roleName !== '' ? $roleName : 'Sans rôle') ?>
                                                    <?php if ($mCallsign !== '' && $mCallsign !== 'Compte supprimé'): ?>
                                                        · <?= $h($mCallsign) ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <?php if ($isDeleted): ?>
                                            <span class="pu-row__state"><?= \App\Support\ConfidentialDisplay::badge(\App\Support\ConfidentialDisplay::ACCOUNT_DELETED) ?></span>
                                            <?php else: ?>
                                            <span class="pa-pill <?= $h($pill) ?> pu-row__state"><?= $h($pillText) ?></span>
                                            <?php endif; ?>
                                            <div class="pu-actions pu-row__actions">
                                                <?php if ($uid === $currentActorId): ?>
                                                    <span class="pu-muted">Vous</span>
                                                <?php elseif ($isDeleted): ?>
                                                    <?= $actionForm('admin/users/purge', $fields + ['scope' => 'org', 'confirm_email' => ''], 'Effacer cette fiche', 'pa-btn pa-btn--sm pa-btn--line', [
                                                        'title' => 'Effacer la fiche anonymisée dans « ' . $tenantName . ' » ?',
                                                        'body' => "La fiche « Compte supprimé » disparaît de l’annuaire de cette communauté.\nRien ne peut être restauré.",
                                                        'tone' => 'danger',
                                                        'type' => $email,
                                                        'ok' => 'Effacer',
                                                    ]) ?>
                                                <?php elseif ($isLeft): ?>
                                                    <?php if ($ban === null): ?>
                                                        <?= $actionForm('admin/users/reintegrer-communaute', $fields, 'Réintégrer', 'pa-btn pa-btn--sm pa-btn--line', [
                                                            'title' => 'Réintégrer ' . $primary . ' dans « ' . $tenantName . ' » ?',
                                                            'body' => "La personne redevient membre avec sa fiche, son rôle et son historique d’avant.",
                                                            'ok' => 'Réintégrer',
                                                        ]) ?>
                                                    <?php endif; ?>
                                                    <?= $actionForm('admin/users/purge', $fields + ['scope' => 'org', 'confirm_email' => ''], 'Effacer sa fiche ici', 'pa-btn pa-btn--sm pu-btn-ghost-danger', [
                                                        'title' => 'Effacer la fiche de ' . $primary . ' dans « ' . $tenantName . ' » ?',
                                                        'body' => "Sa fiche dans cette communauté est effacée de la base (grade, dossier, rôle…).\nSes autres communautés et son compte ne changent pas.\nRien ne peut être restauré.",
                                                        'tone' => 'danger',
                                                        'type' => $email,
                                                        'ok' => 'Effacer la fiche',
                                                    ]) ?>
                                                <?php else: ?>
                                                    <a class="pa-btn pa-btn--sm pa-btn--line" href="<?= $h(url('admin/users/' . $uid . '/edit')) ?>">Modifier</a>
                                                    <?php if ($ban === null): ?>
                                                        <?php if ($st === 'active'): ?>
                                                            <?= $actionForm('admin/users/set-status', $fields + ['status' => 'inactive'], 'Suspendre', 'pa-btn pa-btn--sm pa-btn--line', [
                                                                'title' => 'Suspendre l’accès de ' . $primary . ' à « ' . $tenantName . ' » ?',
                                                                'body' => "La personne reste membre mais ne peut plus entrer dans cette communauté.\nSes autres communautés ne changent pas. « Rétablir » annule.",
                                                                'ok' => 'Suspendre',
                                                            ]) ?>
                                                        <?php else: ?>
                                                            <?= $actionForm('admin/users/set-status', $fields + ['status' => 'active'], 'Rétablir l’accès', 'pa-btn pa-btn--sm pa-btn--line') ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?= $actionForm('admin/users/retirer-communaute', $fields, 'Retirer', 'pa-btn pa-btn--sm pu-btn-ghost-danger', [
                                                        'title' => 'Retirer ' . $primary . ' de « ' . $tenantName . ' » ?',
                                                        'body' => "La personne ne fait plus partie de cette communauté et n’y a plus accès.\nRien n’est effacé : sa fiche reste ici avec la mention « Retiré », et « Réintégrer » annule.",
                                                        'ok' => 'Retirer',
                                                    ]) ?>
                                                <?php endif; ?>
                                                <a class="pu-icon-link" href="<?= $h(url('admin/system/member-sanctions') . ($tid > 0 ? '?tenant_id=' . $tid : '')) ?>" title="Sanctions dans cette communauté (avertissement, restriction de messagerie…)">Sanctions</a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="pu-pager" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= $h($directoryUrl(['page' => $page - 1])) ?>" class="pa-btn pa-btn--line">Page précédente</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <span class="pu-muted">Page <?= $h($page) ?> sur <?= $h($pages) ?></span>
                <?php if ($page < $pages): ?>
                    <a href="<?= $h($directoryUrl(['page' => $page + 1])) ?>" class="pa-btn pa-btn--line">Page suivante</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <section class="pa-card pu-cleanup">
            <p class="pa-card__kicker">Entretien</p>
            <h2 class="pa-card__title">Nettoyer les comptes anonymisés</h2>
            <p class="pa-card__help">
                Après une anonymisation, une fiche « Compte supprimé » reste dans l’annuaire de chaque communauté.
                Ce nettoyage les retire toutes d’un coup. Les messages, événements et participations restent,
                signés « Ancien membre ». Aucun compte actif n’est touché.
            </p>
            <?= $actionForm('admin/users/purge-anonymises', ['confirm_phrase' => ''], 'Nettoyer les fiches anonymisées', 'pa-btn pa-btn--line', [
                'title' => 'Retirer toutes les fiches « Compte supprimé » ?',
                'body' => "Les fiches anonymisées disparaissent des annuaires de toutes les communautés.\nL’historique reste, signé « Ancien membre ». Aucun compte actif n’est touché.",
                'tone' => 'danger',
                'type' => 'supprimer definitivement',
                'ok' => 'Nettoyer',
            ]) ?>
        </section>
    </div>

    <dialog id="pu-ban-dialog" class="pu-dialog pu-dialog--wide" aria-labelledby="pu-ban-dialog-title">
        <form method="post" action="<?= $h(url('admin/users/bannir')) ?>" class="pu-dialog__form">
            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="email" value="" data-ban-email-input>
            <?= $hiddenReturns() ?>
            <p class="pu-dialog__kicker">Bannir du site</p>
            <h3 id="pu-ban-dialog-title" class="pu-dialog__title">Bannir une personne</h3>
            <?php
            $banForm = ['id' => 'pu-ban-shared', 'label' => '', 'email' => '', 'fiches' => 0, 'prior' => 0, 'history_url' => '', 'supports_end' => $banSupportsEnd, 'steam_id' => '', 'known_ips' => [], 'shared' => true];
            require base_path('views/admin/system/partials/platform_ban_form.php');
            ?>
            <div class="pu-dialog__buttons">
                <button type="button" class="pa-btn pa-btn--line" data-pu-close>Annuler</button>
                <button type="submit" class="pa-btn pu-btn-danger">Bannir du site</button>
            </div>
        </form>
    </dialog>

    <dialog id="pu-guide" class="pu-dialog pu-dialog--wide" aria-labelledby="pu-guide-title">
        <div class="pu-dialog__form">
            <p class="pu-dialog__kicker">Aide</p>
            <h3 id="pu-guide-title" class="pu-dialog__title">Quelle action choisir ?</h3>
            <div class="pu-guide">
                <section>
                    <h4>Dans une communauté</h4>
                    <dl>
                        <div><dt>Suspendre <span class="pa-pill pa-pill--mint">réversible</span></dt><dd>La personne reste membre mais ne peut plus entrer dans cette communauté. Pour une pause ou une enquête.</dd></div>
                        <div><dt>Retirer <span class="pa-pill pa-pill--mint">réversible</span></dt><dd>Elle ne fait plus partie de la communauté. Sa fiche reste visible ici avec « Retiré » et peut être réintégrée.</dd></div>
                        <div><dt>Effacer sa fiche ici <span class="pa-pill pa-pill--rose">définitif</span></dt><dd>Efface son dossier dans cette communauté (grade, rôle, dossier). Ses autres communautés et son compte restent.</dd></div>
                        <div><dt>Sanctions</dt><dd>Avertissement, restriction de la messagerie ou des modules, dans une communauté donnée.</dd></div>
                    </dl>
                </section>
                <section>
                    <h4>Sur tout le site</h4>
                    <dl>
                        <div><dt>Bannir du site <span class="pa-pill pa-pill--mint">réversible</span></dt><dd>Bloque la connexion et l’inscription (e-mail, Steam ID, IP au choix) sans rien effacer, pour toujours ou jusqu’à une date. Un avertissement, un motif et une confirmation sont demandés. Chaque sanction reste dans l’historique de la personne.</dd></div>
                        <div><dt>Supprimer le compte <span class="pa-pill pa-pill--rose">définitif</span></dt><dd>Efface la personne partout (compte de jeu, rôles, données personnelles) ; ses communautés passent au membre suivant, ou sont supprimées s’il était seul. Ce que les autres membres partagent reste sous « Ancien membre ». Pour une demande de suppression des données.</dd></div>
                        <div><dt>Supprimer définitivement <span class="pa-pill pa-pill--rose">définitif</span></dt><dd>Efface tout de la base. Pour les faux comptes et comptes de test.</dd></div>
                        <div><dt>Bloquer une adresse IP</dt><dd>Dans le dossier complet (rubrique Réseau) ou la liste « Adresses IP et e-mails bloqués ».</dd></div>
                    </dl>
                </section>
                <section>
                    <h4>Le tableau</h4>
                    <p>Cliquez sur un titre de colonne pour trier, « Colonnes affichées » pour choisir ce qui s’affiche, les cases à gauche pour exporter une sélection.
                        « Sans communauté » signale un compte seul, sans communauté active. Une personne retirée d’une communauté reste dans l’annuaire avec la mention « Retiré le … » : « Réintégrer » la remet en place.</p>
                </section>
            </div>
            <div class="pu-dialog__buttons">
                <button type="button" class="pa-btn pa-btn--solid" data-pu-close>J’ai compris</button>
            </div>
        </div>
    </dialog>

    <dialog id="pu-confirm" class="pu-dialog" aria-labelledby="pu-confirm-title">
        <form method="dialog" class="pu-dialog__form">
            <p class="pu-dialog__kicker" id="pu-confirm-kicker">Confirmer</p>
            <h3 id="pu-confirm-title" class="pu-dialog__title"></h3>
            <ul class="pu-dialog__facts" id="pu-confirm-body"></ul>
            <label class="pu-dialog__field" id="pu-confirm-type-wrap" hidden>
                <span>Pour confirmer, tapez <strong class="pu-mono" id="pu-confirm-type-expected"></strong></span>
                <input type="text" id="pu-confirm-type" autocomplete="off" spellcheck="false">
            </label>
            <div class="pu-dialog__buttons">
                <button type="button" class="pa-btn pa-btn--line" data-pu-close>Annuler</button>
                <button type="button" class="pa-btn pa-btn--solid" id="pu-confirm-ok">Confirmer</button>
            </div>
        </form>
    </dialog>
</div>
<script src="<?= $h(asset_url('assets/js/platform-users-sheet.js')) ?>" defer></script>
