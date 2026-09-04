<?php
declare(strict_types=1);

/**
 * Journal d’audit — rendu ATHENA (maquette).
 *
 * Variables : auditRows, auditTotal, auditPage, auditTotalPages, auditFilters,
 * auditScope, auditActionFilterOptions, hideAuditPageHeader (ignoré).
 */

use App\Support\Audit\AuditSnapshotPresenter;
use App\Support\ParisDateTime;

$auditScope = $auditScope ?? 'organization';
$auditRows = is_array($auditRows ?? null) ? $auditRows : [];
$auditTotal = (int) ($auditTotal ?? 0);
$auditPage = max(1, (int) ($auditPage ?? 1));
$auditTotalPages = max(1, (int) ($auditTotalPages ?? 1));
$auditFilters = is_array($auditFilters ?? null) ? $auditFilters : [];
$auditActionFilterOptions = is_array($auditActionFilterOptions ?? null) ? $auditActionFilterOptions : [];
$showTenantCol = $auditScope === 'system';

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$basePath = $auditScope === 'organization' ? 'back-office/audit' : 'admin/audit';

$buildLink = static function (int $page) use ($auditFilters, $basePath): string {
    $q = array_merge($auditFilters, ['page' => $page > 1 ? $page : null]);
    $q = array_filter($q, static fn ($v) => $v !== null && $v !== '');

    return url($basePath) . ($q ? '?' . http_build_query($q) : '');
};

$selectedSlug = (string) ($auditFilters['action_slug'] ?? '');
$selectedDomain = (string) ($auditFilters['action_domain'] ?? '');
$selectedEntityType = (string) ($auditFilters['entity_type'] ?? '');

$domainOptions = [
    'auth' => 'Authentification',
    'tenant' => 'Communauté',
    'invitation' => 'Invitations',
    'user' => 'Utilisateurs',
    'role' => 'Rôles',
    'group' => 'Groupes',
    'document' => 'Documents',
    'training' => 'Formation',
    'course' => 'Formation (cours)',
    'deployment' => 'Déploiement',
    'platform' => 'Plateforme',
    'site_role' => 'Rôles du site',
    'moderation' => 'Modération',
    'security' => 'Sécurité',
    'audit' => 'Journal',
];

$entityTypeOptions = [
    'user' => 'Compte',
    'auth' => 'Connexion',
    'tenant' => 'Communauté',
    'document' => 'Document',
    'role' => 'Rôle',
    'group' => 'Groupe',
    'invitation' => 'Invitation',
    'course' => 'Formation',
    'enrollment' => 'Inscription',
    'module' => 'Fonctionnalité',
    'access_rule' => 'Règle d’accès',
];

$sourceLabel = static function (array $row): string {
    $domain = (string) ($row['action_domain'] ?? '');
    return match ($domain) {
        'auth', 'security' => 'Sécurité',
        'training', 'course' => 'Formations',
        'moderation' => 'Modération',
        'deployment' => 'Déploiement',
        default => 'Portail',
    };
};

$severityLabel = static function (string $action): string {
    $a = mb_strtolower($action, 'UTF-8');
    if (str_contains($a, 'fail') || str_contains($a, 'lock') || str_contains($a, 'suspend') || str_contains($a, 'sanction')) {
        return 'Critique';
    }
    if (str_contains($a, 'delete') || str_contains($a, 'reject')) {
        return 'Échec';
    }
    if (str_contains($a, 'valid') || str_contains($a, 'approv')) {
        return 'Validé';
    }

    return 'Actif';
};

$athKpis = [
    ['label' => 'ÉVÉNEMENTS', 'value' => (string) $auditTotal, 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'sur la période filtrée'],
    ['label' => 'PAGE COURANTE', 'value' => (string) count($auditRows), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $auditTotal > 0 ? min(100, (int) round(count($auditRows) / max(1, $auditTotal) * 100)) . '%' : '0%', 'note' => 'lignes affichées'],
    ['label' => 'PAGINATION', 'value' => $auditPage . ' / ' . $auditTotalPages, 'delta' => '', 'tone' => '#1e4f80', 'pct' => $auditTotalPages > 0 ? (int) round($auditPage / $auditTotalPages * 100) . '%' : '0%', 'note' => 'navigation'],
    ['label' => 'RÉTENTION', 'value' => '24 mois', 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => 'conforme charte'],
];
require base_path('views/partials/ath_kpis.php');
?>

<form method="get" action="<?= $h(url($basePath)) ?>" class="ath-users-filters ath-rise">
    <div>
        <label class="ath-users-filters__label" for="audit-date-from">Du</label>
        <input id="audit-date-from" type="date" name="date_from" value="<?= $h((string) ($auditFilters['date_from'] ?? '')) ?>" class="bo-select" style="height:40px;">
    </div>
    <div>
        <label class="ath-users-filters__label" for="audit-date-to">Au</label>
        <input id="audit-date-to" type="date" name="date_to" value="<?= $h((string) ($auditFilters['date_to'] ?? '')) ?>" class="bo-select" style="height:40px;">
    </div>
    <div>
        <label class="ath-users-filters__label" for="audit-action-slug">Type d’événement</label>
        <select id="audit-action-slug" name="action_slug" class="bo-select">
            <option value="">Tous</option>
            <?php foreach ($auditActionFilterOptions as $slug => $label): ?>
            <option value="<?= $h((string) $slug) ?>"<?= $selectedSlug === (string) $slug ? ' selected' : '' ?>><?= $h((string) $label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="ath-users-filters__label" for="audit-action-domain">Domaine</label>
        <select id="audit-action-domain" name="action_domain" class="bo-select">
            <option value="">Tous</option>
            <?php foreach ($domainOptions as $domain => $label): ?>
            <option value="<?= $h($domain) ?>"<?= $selectedDomain === $domain ? ' selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="ath-users-filters__label" for="audit-search">Recherche</label>
        <input id="audit-search" type="search" name="search" value="<?= $h((string) ($auditFilters['search'] ?? '')) ?>" class="bo-select" style="height:40px;" placeholder="Acteur, événement…">
    </div>
    <div>
        <label class="ath-users-filters__label" for="audit-entity-type">Élément</label>
        <select id="audit-entity-type" name="entity_type" class="bo-select">
            <option value="">Tous</option>
            <?php foreach ($entityTypeOptions as $type => $label): ?>
            <option value="<?= $h($type) ?>"<?= $selectedEntityType === $type ? ' selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="ath-btn ath-btn--solid">Filtrer</button>
    <a href="<?= $h(url($basePath)) ?>" class="ath-btn">Effacer</a>
    <?php
    $legacyAction = trim((string) ($auditFilters['action'] ?? ''));
    if ($legacyAction !== ''):
    ?>
    <input type="hidden" name="action" value="<?= $h($legacyAction) ?>">
    <?php endif; ?>
</form>

<?php
$athTableRows = [];
$athTableRowHrefs = [];
foreach ($auditRows as $row) {
    $rid = (int) ($row['id'] ?? 0);
    $action = (string) ($row['action'] ?? '');
    $createdAt = (string) ($row['created_at'] ?? '');
    $when = ParisDateTime::format($createdAt, 'd/m H:i:s');
    $actor = AuditSnapshotPresenter::actorPrimaryLabel($row);
    $target = AuditSnapshotPresenter::entityTargetLabels($row);
    $changes = AuditSnapshotPresenter::listSummary(
        isset($row['old_value']) ? (string) $row['old_value'] : null,
        isset($row['new_value']) ? (string) $row['new_value'] : null
    );
    $oldVal = '—';
    $newVal = '—';
    if ($changes !== '—' && str_contains($changes, '→')) {
        [$oldVal, $newVal] = array_map('trim', explode('→', $changes, 2) + ['', '']);
    } elseif ($changes !== '—') {
        $newVal = $changes;
    }
    $ip = AuditSnapshotPresenter::maskIpForDisplay(isset($row['ip']) ? (string) $row['ip'] : null);
    $browser = AuditSnapshotPresenter::browserHint(isset($row['user_agent']) ? (string) $row['user_agent'] : null);
    $ref = 'LOG-' . str_pad((string) $rid, 5, '0', STR_PAD_LEFT);

    $athTableRows[] = [
        $ref,
        $when,
        $sourceLabel($row),
        $actor !== '' ? $actor : '—',
        audit_action_label_fr($action),
        $target['primary'] !== '' ? $target['primary'] : '—',
        $oldVal,
        $newVal,
        $ip,
        $browser !== '' ? $browser : '—',
        $severityLabel($action),
    ];
    $athTableRowHrefs[] = $rid > 0 ? url($basePath . '/' . $rid) : null;
}

$pager = [];
if ($auditPage > 1) {
    $pager[] = ['label' => '‹', 'href' => $buildLink($auditPage - 1)];
}
$fromP = max(1, $auditPage - 2);
$toP = min($auditTotalPages, $auditPage + 2);
for ($p = $fromP; $p <= $toP; $p++) {
    $pager[] = ['label' => (string) $p, 'href' => $buildLink($p), 'active' => $p === $auditPage];
}
if ($auditPage < $auditTotalPages) {
    $pager[] = ['label' => '›', 'href' => $buildLink($auditPage + 1)];
}

$perPage = (int) ($auditPerPage ?? 25);
$start = $auditTotal > 0 ? (($auditPage - 1) * $perPage) + 1 : 0;
$end = min($auditTotal, $auditPage * $perPage);

$athTableTitle = 'Événements système';
$athTableCount = $auditTotal;
$athTableCols = [
    'ID|m', 'HORODATAGE|m', 'SOURCE', 'ACTEUR', 'ACTION', 'OBJET|m',
    'ANCIENNE VALEUR', 'NOUVELLE VALEUR', 'IP|m', 'AGENT', 'SÉVÉRITÉ|b',
];
$athTableFilters = ['Sévérité', 'Source', '24 h'];
$athTableMinWidth = '1620px';
$athTableFoot = $auditTotal > 0
    ? 'Affichage ' . $start . ' – ' . $end . ' sur ' . $auditTotal . ' · ' . date('d/m/Y H:i')
    : 'Aucun événement · ' . date('d/m/Y H:i');
$athTablePager = $pager;
require base_path('views/partials/ath_table.php');
