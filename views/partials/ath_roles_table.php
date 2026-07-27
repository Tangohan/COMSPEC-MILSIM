<?php
declare(strict_types=1);

/**
 * Table des rôles communautaires — rendu ATHENA.
 */

$roles = is_array($roles ?? null) ? $roles : [];
$permissionCounts = is_array($permissionCounts ?? null) ? $permissionCounts : [];
$memberCounts = is_array($memberCounts ?? null) ? $memberCounts : [];
$roleLayerFilter = (string) ($roleLayerFilter ?? '');
$roleTierFilter = (string) ($roleTierFilter ?? '');
$base = url('back-office/roles');

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$tierMeta = static function (string $t): string {
    return match ($t) {
        'authority' => 'Commandement',
        'function' => 'Emploi',
        'liaison' => 'Liaison',
        'support' => 'Soutien',
        'specialty' => 'Spécialité',
        'status' => 'Statut affiché',
        default => 'Emploi',
    };
};

$scopeMeta = static function (string $layer): string {
    return $layer === 'community' ? 'Communauté' : 'Unité';
};

$familyMeta = static function (string $layer): string {
    return $layer === 'community' ? 'Gouvernance' : 'Opérationnels';
};

$withRights = 0;
$withMembers = 0;
$totalRights = 0;
foreach ($roles as $r) {
    $rid = (int) ($r['id'] ?? 0);
    $pc = (int) ($permissionCounts[$rid] ?? 0);
    $mc = (int) ($memberCounts[$rid] ?? 0);
    $totalRights += $pc;
    if ($pc > 0) {
        $withRights++;
    }
    if ($mc > 0) {
        $withMembers++;
    }
}

$athKpis = [
    ['label' => 'RÔLES', 'value' => (string) count($roles), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '80%', 'note' => 'affichés'],
    ['label' => 'DROITS DISTINCTS', 'value' => (string) $totalRights, 'delta' => '', 'tone' => '#1e4f80', 'pct' => '76%', 'note' => 'au catalogue'],
    ['label' => 'AVEC MEMBRES', 'value' => (string) $withMembers, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => count($roles) > 0 ? (int) round($withMembers / count($roles) * 100) . '%' : '0%', 'note' => 'rôles pourvus'],
    ['label' => 'SANS TITULAIRE', 'value' => (string) max(0, count($roles) - $withMembers), 'delta' => '', 'tone' => '#c98a12', 'pct' => count($roles) > 0 ? (int) round((count($roles) - $withMembers) / count($roles) * 100) . '%' : '0%', 'note' => 'à pourvoir'],
];
require base_path('views/partials/ath_kpis.php');

$appendQuery = static function (string $baseUrl, array $params): string {
    $q = http_build_query(array_filter($params, static fn ($v) => $v !== null && $v !== ''));

    return $q === '' ? $baseUrl : $baseUrl . '?' . $q;
};
?>

<div class="ath-users-filters ath-rise">
    <a href="<?= $h($appendQuery($base, ['tier' => $roleTierFilter !== '' ? $roleTierFilter : null])) ?>" class="ath-btn<?= $roleLayerFilter === '' ? ' ath-btn--solid' : '' ?>">Tous</a>
    <a href="<?= $h($appendQuery($base, ['layer' => 'community', 'tier' => $roleTierFilter !== '' ? $roleTierFilter : null])) ?>" class="ath-btn<?= $roleLayerFilter === 'community' ? ' ath-btn--solid' : '' ?>">Gouvernance</a>
    <a href="<?= $h($appendQuery($base, ['layer' => 'intra', 'tier' => $roleTierFilter !== '' ? $roleTierFilter : null])) ?>" class="ath-btn<?= $roleLayerFilter === 'intra' ? ' ath-btn--solid' : '' ?>">Opérationnels</a>
    <a href="<?= $h(url('back-office/roles/presets')) ?>" class="ath-btn">Profils prêts</a>
</div>

<?php
$athTableRows = [];
$athTableRowHrefs = [];
foreach ($roles as $r) {
    $rid = (int) ($r['id'] ?? 0);
    $slug = strtoupper(str_replace('_', '-', substr((string) ($r['slug'] ?? ''), 0, 12)));
    if ($slug === '') {
        $slug = 'ROL-' . str_pad((string) $rid, 3, '0', STR_PAD_LEFT);
    }
    $layer = (string) ($r['role_layer'] ?? 'community');
    $tier = (string) ($r['semantic_tier'] ?? 'function');
    $locked = !empty($r['is_system']) ? 'Oui' : 'Non';
    $updated = isset($r['updated_at']) ? date('d/m/Y', strtotime((string) $r['updated_at']) ?: time()) : '—';
    $athTableRows[] = [
        $slug,
        (string) ($r['name'] ?? '—'),
        (string) ($r['label_en'] ?? $r['slug'] ?? '—'),
        $familyMeta($layer),
        $tierMeta($tier),
        $scopeMeta($layer),
        (string) (int) ($permissionCounts[$rid] ?? 0),
        (string) (int) ($memberCounts[$rid] ?? 0),
        $locked,
        '—',
        $updated,
        !empty($r['is_active']) || !isset($r['is_active']) ? 'Actif' : 'Inactif',
    ];
    $athTableRowHrefs[] = $rid > 0 ? url('back-office/roles/' . $rid) : null;
}

$athTableTitle = 'Rôles par famille';
$athTableCount = count($roles);
$athTableCols = [
    'CODE|m', 'RÔLE', 'LIBELLÉ TECHNIQUE|m', 'FAMILLE', 'TYPE', 'PÉRIMÈTRE',
    'DROITS|r', 'MEMBRES|r', 'VERROUILLÉ', 'HÉRITE DE', 'MODIFIÉ LE|m', 'ÉTAT|b',
];
$athTableFilters = ['Périmètre', 'Type', 'Statut'];
$athTableMinWidth = '1600px';
$athTableFoot = count($roles) > 0
    ? 'Affichage 1 – ' . count($roles) . ' sur ' . count($roles) . ' · ' . date('d/m/Y H:i')
    : 'Aucun rôle · ' . date('d/m/Y H:i');
require base_path('views/partials/ath_table.php');
