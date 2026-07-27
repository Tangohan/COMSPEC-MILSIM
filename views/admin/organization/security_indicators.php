<?php
declare(strict_types=1);

/**
 * Blocages portail & sécurité — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office ; cette vue ne produit que
 * le bandeau d’explication, les indicateurs et le tableau.
 *
 * @var list<array<string, mixed>> $indicatorRows
 */

$rows = is_array($indicatorRows ?? null) ? $indicatorRows : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$typeLabel = static function (string $t): string {
    return match ($t) {
        'email' => 'Adresse e-mail',
        'ip' => 'Adresse réseau',
        default => $t !== '' ? ucfirst($t) : 'Indicateur',
    };
};

$fmtDt = static function (mixed $raw): string {
    $s = trim((string) ($raw ?? ''));
    if ($s === '') {
        return '—';
    }
    $t = strtotime($s);

    return $t ? date('d/m/Y H:i', $t) : '—';
};

$now = time();
$emailCount = 0;
$ipCount = 0;
$expiringSoon = 0;
$permanent = 0;
foreach ($rows as $r) {
    $type = (string) ($r['indicator_type'] ?? '');
    if ($type === 'email') {
        $emailCount++;
    } elseif ($type === 'ip') {
        $ipCount++;
    }
    $exp = trim((string) ($r['expires_at'] ?? ''));
    if ($exp === '') {
        $permanent++;
        continue;
    }
    $t = strtotime($exp);
    if ($t && $t > $now && ($t - $now) <= 7 * 86400) {
        $expiringSoon++;
    }
}
$total = count($rows);
$pctOf = static fn (int $n): string => $total > 0 ? (string) (int) round($n / $total * 100) . '%' : '0%';
?>
<div class="ath-note">
    <p class="ath-note__title">Liste locale à votre communauté</p>
    <p class="ath-note__text">
        Les entrées actives bloquent l’accès au portail public (candidatures, suivi invité) pour l’indicateur concerné.
        Elles sont souvent créées par la <strong>modération automatique</strong> du portail recrutement.
        Les valeurs réelles ne sont jamais affichées : seule une empreinte partielle apparaît.
    </p>
</div>

<?php
$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
?>
<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>

<?php
$athKpis = [
    [
        'label' => 'BLOCAGES ACTIFS',
        'value' => (string) $total,
        'delta' => '',
        'tone' => $total === 0 ? '#0b8a5c' : '#c98a12',
        'pct' => $total === 0 ? '0%' : '100%',
        'note' => 'sur le portail public',
    ],
    [
        'label' => 'ADRESSES E-MAIL',
        'value' => (string) $emailCount,
        'delta' => '',
        'tone' => '#1e4f80',
        'pct' => $pctOf($emailCount),
        'note' => 'empreintes d’adresse',
    ],
    [
        'label' => 'ADRESSES RÉSEAU',
        'value' => (string) $ipCount,
        'delta' => '',
        'tone' => '#1e4f80',
        'pct' => $pctOf($ipCount),
        'note' => 'empreintes réseau',
    ],
    [
        'label' => 'SANS ÉCHÉANCE',
        'value' => (string) $permanent,
        'delta' => $expiringSoon > 0 ? $expiringSoon . ' sous 7 j' : '',
        'tone' => $permanent === 0 ? '#0b8a5c' : '#c72e2e',
        'pct' => $pctOf($permanent),
        'note' => 'à revoir manuellement',
    ],
];
require base_path('views/partials/ath_kpis.php');

$revokeUrl = url('back-office/security-indicators/revoke');
$csrf = \App\Core\Csrf::token();

$athTableTitle = 'Blocages encore actifs';
$athTableCount = $total;
$athTableCols = ['TYPE', 'EMPREINTE|m', 'MOTIF', 'DEPUIS|m', 'FIN PRÉVUE|m', 'ÉTAT|b'];
$athTableRows = [];
$athTableRowActions = [];
foreach ($rows as $r) {
    $id = (int) ($r['id'] ?? 0);
    $valueHash = (string) ($r['value_hash'] ?? '');
    $fingerprint = $valueHash !== '' ? '…' . substr($valueHash, -10) : '—';
    $reason = trim((string) ($r['reason'] ?? ''));
    $exp = trim((string) ($r['expires_at'] ?? ''));
    $expTs = $exp !== '' ? strtotime($exp) : false;

    if ($exp === '') {
        $state = 'Sans limite';
    } elseif ($expTs && $expTs <= $now) {
        $state = 'Expiré';
    } else {
        $state = 'Actif';
    }

    $athTableRows[] = [
        $typeLabel((string) ($r['indicator_type'] ?? '')),
        $fingerprint,
        $reason !== '' ? $reason : '—',
        $fmtDt($r['created_at'] ?? null),
        $exp !== '' ? $fmtDt($exp) : 'Sans limite',
        $state,
    ];

    // Balisage d’action construit ici, échappements compris (cf. contrat de ath_table.php).
    $athTableRowActions[] = '<form method="post" action="' . $h($revokeUrl) . '"'
        . ' onsubmit="return confirm(\'Lever ce blocage pour toute la communauté ?\');">'
        . '<input type="hidden" name="_csrf_token" value="' . $h($csrf) . '">'
        . '<input type="hidden" name="indicator_id" value="' . $id . '">'
        . '<button type="submit" class="ath-row-action ath-row-action--accent">Lever</button>'
        . '</form>';
}
$athTableActionsLabel = 'LEVÉE';
$athTableFilters = [];
$athTableMinWidth = '1180px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableFoot = $rows === []
    ? 'Aucun blocage actif pour cette communauté.'
    : 'Lever un blocage rétablit immédiatement l’accès au portail public pour l’indicateur concerné.';
require base_path('views/partials/ath_table.php');
