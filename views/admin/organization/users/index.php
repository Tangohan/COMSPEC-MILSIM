<?php
declare(strict_types=1);

$users = is_array($users ?? null) ? $users : [];
$roles = is_array($roles ?? null) ? $roles : [];
$filters = is_array($filters ?? null) ? $filters : [];
$usersTotal = (int) ($usersTotal ?? 0);
$usersPage = max(1, (int) ($usersPage ?? 1));
$usersTotalPages = max(1, (int) ($usersTotalPages ?? 1));
$athUserKpis = is_array($athUserKpis ?? null) ? $athUserKpis : [];

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$usersQuery = static function (int $page) use ($filters): string {
    $q = [
        'search' => $filters['search'] ?? null,
        'status' => !empty($filters['status']) ? $filters['status'] : null,
        'role_id' => !empty($filters['role_id']) ? (int) $filters['role_id'] : null,
        'filter_incomplete' => !empty($filters['filter_incomplete']) ? '1' : null,
        'filter_no_unit' => !empty($filters['filter_no_unit']) ? '1' : null,
        'filter_no_role' => !empty($filters['filter_no_role']) ? '1' : null,
        'page' => $page > 1 ? $page : null,
    ];
    $q = array_filter($q, static fn ($v) => $v !== null && $v !== '');

    return url('back-office/users') . ($q ? '?' . http_build_query($q) : '');
};

$statusLabel = static function (string $raw): string {
    return match ($raw) {
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'pending_verification' => 'En attente',
        'suspended' => 'Suspendu',
        default => $raw !== '' ? $raw : '—',
    };
};

$fmtDate = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts ? date('d/m/Y', $ts) : $raw;
};

$matricule = static function (array $user): string {
    $id = trim((string) ($user['athena_identifier'] ?? ''));
    if ($id !== '') {
        return $id;
    }

    return 'ATH-' . str_pad((string) ((int) ($user['id'] ?? 0)), 4, '0', STR_PAD_LEFT);
};

$athKpis = $athUserKpis;
require base_path('views/partials/ath_kpis.php');
?>

<form method="get" action="<?= $h(url('back-office/users')) ?>" class="ath-users-filters ath-rise">
    <label class="ath-users-filters__label" for="users-search">Recherche</label>
    <input type="search" name="search" id="users-search" class="bo-select" value="<?= $h((string) ($filters['search'] ?? '')) ?>" placeholder="Nom, indicatif, e-mail…" autocomplete="off">
    <label class="ath-users-filters__label" for="users-status">Statut du compte</label>
    <select name="status" id="users-status" class="bo-select">
        <option value="">Tous les statuts</option>
        <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Actifs</option>
        <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
        <option value="pending_verification" <?= ($filters['status'] ?? '') === 'pending_verification' ? 'selected' : '' ?>>En attente de vérification</option>
    </select>
    <label class="ath-users-filters__label" for="users-role">Rôle</label>
    <select name="role_id" id="users-role" class="bo-select">
        <option value="">Tous les rôles</option>
        <?php foreach ($roles as $role): ?>
        <option value="<?= (int) ($role['id'] ?? 0) ?>" <?= (int) ($filters['role_id'] ?? 0) === (int) ($role['id'] ?? 0) ? 'selected' : '' ?>>
            <?= $h((string) ($role['name'] ?? '')) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <label class="ath-users-filters__check">
        <input type="checkbox" name="filter_incomplete" value="1" <?= !empty($filters['filter_incomplete']) ? 'checked' : '' ?>>
        Profils incomplets
    </label>
    <label class="ath-users-filters__check">
        <input type="checkbox" name="filter_no_unit" value="1" <?= !empty($filters['filter_no_unit']) ? 'checked' : '' ?>>
        Sans affectation
    </label>
    <label class="ath-users-filters__check">
        <input type="checkbox" name="filter_no_role" value="1" <?= !empty($filters['filter_no_role']) ? 'checked' : '' ?>>
        Sans rôle
    </label>
    <button type="submit" class="ath-btn ath-btn--solid">Appliquer les filtres</button>
    <a href="<?= $h(url('back-office/users/create')) ?>" class="ath-btn">Nouveau membre</a>
</form>

<?php
$athTableRows = [];
$athTableRowHrefs = [];
foreach ($users as $user) {
    $uid = (int) ($user['id'] ?? 0);
    $roleLabel = trim((string) ($user['roles_display'] ?? $user['role_name'] ?? '—'));
    $athTableRows[] = [
        $matricule($user),
        trim((string) ($user['callsign'] ?? '—')) !== '' ? (string) $user['callsign'] : '—',
        trim((string) ($user['display_name'] ?? '—')) !== '' ? (string) $user['display_name'] : '—',
        '—',
        '—',
        $roleLabel !== '' ? $roleLabel : '—',
        $statusLabel((string) ($user['status'] ?? '')),
        'Hors ligne',
        '—',
        '—',
        '—',
        '—',
        '—',
        '—',
        $fmtDate(isset($user['created_at']) ? (string) $user['created_at'] : null),
    ];
    $athTableRowHrefs[] = $uid > 0 ? url('back-office/users/' . $uid) : null;
}

$pager = [];
if ($usersPage > 1) {
    $pager[] = ['label' => '‹', 'href' => $usersQuery($usersPage - 1)];
}
$from = max(1, $usersPage - 2);
$to = min($usersTotalPages, $usersPage + 2);
for ($p = $from; $p <= $to; $p++) {
    $pager[] = [
        'label' => (string) $p,
        'href' => $usersQuery($p),
        'active' => $p === $usersPage,
    ];
}
if ($usersPage < $usersTotalPages) {
    $pager[] = ['label' => '›', 'href' => $usersQuery($usersPage + 1)];
}

$athTableTitle = 'Annuaire';
$athTableCount = $usersTotal;
$athTableCols = [
    'MATRICULE|m', 'INDICATIF', 'NOM', 'GRADE', 'SECTION', 'FONCTION',
    'STATUT|b', 'CONNEXION|b', 'DERNIÈRE ACTIVITÉ|m', 'OPS|r', 'PRÉSENCE|r',
    'FORM.|r', 'ABS.|r', 'ATAK|b', 'ENGAGÉ LE|m',
];
$athTableFilters = ['Section', 'Grade', 'Statut', 'ATAK'];
$athTableMinWidth = '1720px';
$athTableFilterName = 'search';
$athTableFilterValue = (string) ($filters['search'] ?? '');
$perPage = (int) ($usersPerPage ?? 25);
$start = $usersTotal > 0 ? (($usersPage - 1) * $perPage) + 1 : 0;
$end = min($usersTotal, $usersPage * $perPage);
$athTableFoot = $usersTotal > 0
    ? 'Affichage ' . $start . ' – ' . $end . ' sur ' . $usersTotal . ' · ' . date('d/m/Y H:i')
    : 'Aucun membre · ' . date('d/m/Y H:i');
$athTablePager = $pager;
$athTableExportUrl = url('back-office/users') . '?export=csv';
require base_path('views/partials/ath_table.php');
