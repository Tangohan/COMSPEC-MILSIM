<?php
declare(strict_types=1);

/**
 * Liens d'invitation partageables — charte ATHENA.
 *
 * @var list<array<string, mixed>> $inviteLinks
 * @var array<int, array{click: int, signup: int, application: int, join: int, request: int}> $inviteLinkStats
 * @var array{max: int, active: int, can_create: bool, custom_slug: bool} $inviteLinkQuota
 */

use App\Repositories\InviteLinkRepository;
use App\Services\Recruitment\InviteLinkService;

$inviteLinks = is_array($inviteLinks ?? null) ? $inviteLinks : [];
$inviteLinkStats = is_array($inviteLinkStats ?? null) ? $inviteLinkStats : [];
$quota = is_array($inviteLinkQuota ?? null) ? $inviteLinkQuota : ['max' => 0, 'active' => 0, 'can_create' => true, 'custom_slug' => true];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$baseUrl = url('back-office/recruitments/liens-invitation');

$totals = InviteLinkRepository::emptyStats();
$counts = ['active' => 0, 'inactive' => 0];
foreach ($inviteLinks as $link) {
    $s = $inviteLinkStats[(int) $link['id']] ?? InviteLinkRepository::emptyStats();
    foreach ($totals as $k => $v) {
        $totals[$k] = $v + (int) $s[$k];
    }
    InviteLinkService::status($link) === InviteLinkService::STATUS_ACTIVE ? $counts['active']++ : $counts['inactive']++;
}
?>
<nav class="ath-periods" aria-label="Liens d’invitation">
    <span class="ath-periods__label">Liens</span>
    <span class="ath-table-toolbar__spacer" aria-hidden="true"></span>
    <?php if ($quota['can_create']): ?>
        <a href="<?= $h($baseUrl . '/creer') ?>" class="ath-btn ath-btn--solid">Créer un lien d’invitation</a>
    <?php else: ?>
        <span class="ath-btn" aria-disabled="true">Limite de liens actifs atteinte</span>
    <?php endif; ?>
</nav>

<div class="ath-note">
    <p class="ath-note__title">Un lien à partager, comme sur Discord</p>
    <p class="ath-note__text">
        Chaque lien ouvre une page de présentation de votre communauté, puis propose de <strong>candidater</strong>
        ou de <strong>rejoindre</strong> la communauté. Les visiteurs sans compte en créent un : l’invitation est gardée
        jusqu’au bout de l’inscription. Collez le lien sur Discord, un forum ou un message, ou affichez son QR code.
        <?php if ($quota['max'] > 0): ?>
            Votre formule permet <strong><?= (int) $quota['max'] ?></strong> liens actifs à la fois (<?= (int) $quota['active'] ?> en service).
        <?php endif; ?>
    </p>
</div>

<?php
$athKpis = [
    ['label' => 'LIENS ACTIFS', 'value' => (string) $counts['active'], 'delta' => '', 'tone' => $counts['active'] > 0 ? '#0b8a5c' : '#8c979b', 'pct' => '100%', 'note' => 'utilisables en ce moment'],
    ['label' => 'CLICS', 'value' => (string) $totals['click'], 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'visites des pages d’invitation'],
    ['label' => 'COMPTES CRÉÉS', 'value' => (string) $totals['signup'], 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'inscriptions depuis un lien'],
    ['label' => 'CANDIDATURES', 'value' => (string) $totals['application'], 'delta' => '', 'tone' => '#c98a12', 'pct' => '100%', 'note' => 'dossiers déposés'],
    ['label' => 'ADHÉSIONS', 'value' => (string) ($totals['join'] + $totals['request']), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => $totals['request'] > 0 ? $totals['request'] . ' demandes à valider comprises' : 'membres arrivés par un lien'],
];
require base_path('views/partials/ath_kpis.php');

$athTableTitle = 'Liens d’invitation';
$athTableCount = count($inviteLinks);
$athTableCols = ['NOM INTERNE', 'ADRESSE|m', 'ACTION', 'UTILISATIONS|r', 'CLICS|r', 'EXPIRATION|m', 'ÉTAT|b'];
$athTableRows = [];
$athTableRowHrefs = [];
$athTableRowActions = [];
foreach ($inviteLinks as $link) {
    $id = (int) $link['id'];
    $s = $inviteLinkStats[$id] ?? InviteLinkRepository::emptyStats();
    $isApply = (string) $link['link_action'] === InviteLinkRepository::ACTION_APPLY;
    $expires = trim((string) ($link['expires_at'] ?? ''));
    $athTableRows[] = [
        trim((string) ($link['label'] ?? '')) ?: 'Sans nom',
        'i/' . (string) $link['public_code'],
        $isApply ? 'Candidature' : (!empty($link['requires_approval']) ? 'Adhésion (validation)' : 'Adhésion directe'),
        (string) $link['uses_count'] . ($link['max_uses'] !== null ? ' / ' . $link['max_uses'] : ''),
        (string) $s['click'],
        $expires !== '' ? date('d/m/Y H:i', (int) strtotime($expires)) : 'Jamais',
        InviteLinkService::statusLabel(InviteLinkService::status($link)),
    ];
    $athTableRowHrefs[] = $baseUrl . '/' . $id;
    $athTableRowActions[] = '<a href="' . $h($baseUrl . '/' . $id) . '" class="ath-row-action">Détails</a> '
        . '<a href="' . $h($baseUrl . '/' . $id . '/modifier') . '" class="ath-row-action">Modifier</a>';
}
$athTableActionsLabel = 'FICHE';
$athTableFilters = [];
$athTableMinWidth = '1100px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableFoot = $inviteLinks === []
    ? 'Aucun lien d’invitation pour le moment : créez-en un pour l’envoyer à vos futurs membres.'
    : 'Cliquez une ligne pour copier le lien, afficher son QR code et voir ses statistiques.';
require base_path('views/partials/ath_table.php');
