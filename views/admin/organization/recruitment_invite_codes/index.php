<?php
declare(strict_types=1);

/**
 * Codes d’invitation — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office.
 *
 * @var list<array<string, mixed>> $inviteCodes
 * @var bool $showAll
 */

$inviteCodes = is_array($inviteCodes ?? null) ? $inviteCodes : [];
$showAll = (bool) ($showAll ?? false);

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$baseUrl = url('back-office/recruitments/codes-invitation');
$now = time();

$activeCount = 0;
$expiredCount = 0;
$maxedCount = 0;
$autoAcceptCount = 0;
$usesTotal = 0;
foreach ($inviteCodes as $code) {
    $expiresAt = $code['expires_at'] ?? null;
    $maxUses = $code['max_uses'] ?? null;
    $usesCount = (int) ($code['uses_count'] ?? 0);
    $usesTotal += $usesCount;
    if (!empty($code['auto_accept'])) {
        $autoAcceptCount++;
    }
    $isExpired = $expiresAt !== null && strtotime((string) $expiresAt) <= $now;
    $isMaxedOut = $maxUses !== null && $usesCount >= (int) $maxUses;
    if ($isExpired) {
        $expiredCount++;
    } elseif ($isMaxedOut) {
        $maxedCount++;
    } else {
        $activeCount++;
    }
}
$total = count($inviteCodes);
$pctOf = static fn (int $n): string => $total > 0 ? (string) (int) round($n / $total * 100) . '%' : '0%';
?>
<nav class="ath-periods" aria-label="Filtrer les codes">
    <span class="ath-periods__label">Affichage</span>
    <a href="<?= $h($baseUrl) ?>" class="ath-btn"<?= $showAll ? '' : ' aria-current="true"' ?>>Codes actifs</a>
    <a href="<?= $h($baseUrl . '?all=1') ?>" class="ath-btn"<?= $showAll ? ' aria-current="true"' : '' ?>>Tous les codes</a>
    <span class="ath-table-toolbar__spacer" aria-hidden="true"></span>
    <a href="<?= $h($baseUrl . '/creer') ?>" class="ath-btn ath-btn--solid">Créer un code</a>
</nav>

<div class="ath-note">
    <p class="ath-note__title">À propos des codes d’invitation</p>
    <p class="ath-note__text">
        Un code permet à une personne de rejoindre votre communauté sans passer par le circuit
        complet de candidature. Avec la <strong>validation automatique</strong>, la candidature est
        acceptée dès l’usage du code. Chaque code peut être limité en nombre d’utilisations et daté.
    </p>
</div>

<?php
$athKpis = [
    ['label' => 'CODES ACTIFS', 'value' => (string) $activeCount, 'delta' => '', 'tone' => $activeCount > 0 ? '#0b8a5c' : '#8c979b', 'pct' => $pctOf($activeCount), 'note' => 'utilisables en ce moment'],
    ['label' => 'VALIDATION AUTO', 'value' => (string) $autoAcceptCount, 'delta' => '', 'tone' => $autoAcceptCount > 0 ? '#c98a12' : '#0b8a5c', 'pct' => $pctOf($autoAcceptCount), 'note' => 'acceptent sans revue'],
    ['label' => 'LIMITE ATTEINTE', 'value' => (string) $maxedCount, 'delta' => '', 'tone' => $maxedCount === 0 ? '#0b8a5c' : '#c98a12', 'pct' => $pctOf($maxedCount), 'note' => 'quota épuisé'],
    ['label' => 'EXPIRÉS', 'value' => (string) $expiredCount, 'delta' => '', 'tone' => '#64748b', 'pct' => $pctOf($expiredCount), 'note' => 'hors service'],
    ['label' => 'UTILISATIONS', 'value' => (string) $usesTotal, 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'tous codes confondus'],
];
require base_path('views/partials/ath_kpis.php');

$athTableTitle = $showAll ? 'Tous les codes' : 'Codes actifs';
$athTableCount = $total;
$athTableCols = ['CODE|m', 'LIBELLÉ', 'VALIDATION', 'UTILISATIONS|r', 'EXPIRATION|m', 'ÉTAT|b'];
$athTableRows = [];
$athTableRowHrefs = [];
$athTableRowActions = [];
foreach ($inviteCodes as $code) {
    $codeId = (int) ($code['id'] ?? 0);
    $usesCount = (int) ($code['uses_count'] ?? 0);
    $maxUses = $code['max_uses'] !== null ? (int) $code['max_uses'] : null;
    $expiresAt = $code['expires_at'] ?? null;
    $isExpired = $expiresAt !== null && strtotime((string) $expiresAt) <= $now;
    $isMaxedOut = $maxUses !== null && $usesCount >= $maxUses;

    if ($isExpired) {
        $state = 'Expiré';
    } elseif ($isMaxedOut) {
        $state = 'Limite atteinte';
    } else {
        $state = 'Actif';
    }

    $label = trim((string) ($code['label'] ?? ''));
    $athTableRows[] = [
        (string) ($code['code'] ?? '—'),
        $label !== '' ? $label : 'Sans libellé',
        !empty($code['auto_accept']) ? 'Automatique' : 'Sur revue',
        $usesCount . ($maxUses !== null ? ' / ' . $maxUses : ''),
        $expiresAt !== null ? date('d/m/Y', (int) strtotime((string) $expiresAt)) : 'Sans limite',
        $state,
    ];
    $athTableRowHrefs[] = $baseUrl . '/' . $codeId;
    // Balisage d’action construit ici, échappements compris (cf. contrat de ath_table.php).
    $athTableRowActions[] = '<a href="' . $h($baseUrl . '/' . $codeId) . '" class="ath-row-action">Détails</a> '
        . '<a href="' . $h($baseUrl . '/' . $codeId . '/modifier') . '" class="ath-row-action">Modifier</a>';
}
$athTableActionsLabel = 'FICHE';
$athTableFilters = [];
$athTableMinWidth = '1180px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableFoot = $inviteCodes === []
    ? ($showAll ? 'Aucun code d’invitation n’a encore été créé.' : 'Aucun code actif : ajoutez « Tous les codes » pour voir les codes expirés ou épuisés.')
    : 'Cliquez une ligne pour ouvrir la fiche du code et son historique d’utilisation.';
require base_path('views/partials/ath_table.php');
