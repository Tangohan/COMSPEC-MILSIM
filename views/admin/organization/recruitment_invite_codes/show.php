<?php
declare(strict_types=1);

/**
 * Fiche d’un code d’invitation — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office ; cette vue produit l’identité
 * du code, ses paramètres, les candidatures qui l’ont utilisé et sa désactivation.
 *
 * @var array<string, mixed> $inviteCode
 * @var array{uses: int, last_used_at: ?string, enlistments: list<array<string, mixed>>} $inviteCodeStats
 * @var bool $inviteCodeValid
 * @var array<string, mixed>|null $linkedRecruitmentOpening
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$inviteCode = is_array($inviteCode ?? null) ? $inviteCode : [];
$inviteCodeStats = is_array($inviteCodeStats ?? null) ? $inviteCodeStats : [];
$inviteCodeStats += ['uses' => 0, 'last_used_at' => null, 'enlistments' => []];
$inviteCodeValid = (bool) ($inviteCodeValid ?? false);
$linkedRecruitmentOpening = is_array($linkedRecruitmentOpening ?? null) ? $linkedRecruitmentOpening : null;
$csrfToken = \App\Core\Csrf::token();

$codeId = (int) ($inviteCode['id'] ?? 0);
$codeValue = trim((string) ($inviteCode['code'] ?? ''));
$labelRaw = trim((string) ($inviteCode['label'] ?? ''));
$label = $labelRaw !== '' ? $labelRaw : 'Sans libellé';
$usesCount = (int) ($inviteCode['uses_count'] ?? 0);
$maxUses = ($inviteCode['max_uses'] ?? null) !== null ? (int) $inviteCode['max_uses'] : null;
$expiresAt = $inviteCode['expires_at'] ?? null;
$autoAccept = !empty($inviteCode['auto_accept']);
$createdAt = $inviteCode['created_at'] ?? null;
$enlistments = is_array($inviteCodeStats['enlistments'] ?? null) ? $inviteCodeStats['enlistments'] : [];

$fmtDate = static function (mixed $raw, string $format = 'd/m/Y'): string {
    $s = trim((string) ($raw ?? ''));
    if ($s === '') {
        return '—';
    }
    $t = strtotime($s);

    return $t ? date($format, $t) : $s;
};

$baseUrl = url('back-office/recruitments/codes-invitation');
$quotaRatio = $maxUses !== null && $maxUses > 0 ? (int) round($usesCount / $maxUses * 100) : 0;
?>
<div class="ath-item ath-rise" style="margin-bottom:16px;">
    <div class="ath-item__head">
        <div style="min-width:0;">
            <p class="ath-item__name"><?= $h($label) ?></p>
            <p class="ath-item__meta">
                Code <span class="ath-mono"><?= $h($codeValue !== '' ? $codeValue : '—') ?></span>
                · créé le <?= $h($fmtDate($createdAt)) ?>
            </p>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <span class="ath-tag <?= $inviteCodeValid ? 'ath-tag--ok' : 'ath-tag--neut' ?>"><?= $inviteCodeValid ? 'Actif' : 'Inactif' ?></span>
            <?php if ($autoAccept): ?>
            <span class="ath-tag ath-tag--warn">Validation automatique</span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($maxUses !== null): ?>
    <div class="ath-meter" style="margin-top:12px;">
        <div class="ath-meter__head">
            <span>Quota d’utilisations</span>
            <span class="ath-meter__value"><?= $usesCount ?> / <?= $maxUses ?></span>
        </div>
        <div class="ath-meter__track">
            <span class="ath-meter__fill<?= $quotaRatio >= 100 ? ' ath-meter__fill--bad' : ($quotaRatio >= 80 ? ' ath-meter__fill--warn' : '') ?>"
                  style="width:<?= max(0, min(100, $quotaRatio)) ?>%"></span>
        </div>
    </div>
    <?php endif; ?>
    <div class="ath-item__actions">
        <a href="<?= $h($baseUrl . '/' . $codeId . '/modifier') ?>" class="ath-btn ath-btn--solid">Modifier</a>
        <a href="<?= $h($baseUrl) ?>" class="ath-btn">Retour aux codes</a>
    </div>
</div>

<div class="ath-stat-grid ath-rise" style="margin-bottom:16px;">
    <div class="ath-stat">
        <p class="ath-stat__value"><?= $usesCount ?><?= $maxUses !== null ? ' / ' . $maxUses : '' ?></p>
        <p class="ath-stat__label">Utilisations</p>
    </div>
    <div class="ath-stat">
        <p class="ath-stat__value"><?= $h($fmtDate($expiresAt)) ?></p>
        <p class="ath-stat__label">Expiration</p>
    </div>
    <div class="ath-stat">
        <p class="ath-stat__value"><?= $h($fmtDate($inviteCodeStats['last_used_at'] ?? null, 'd/m/Y H:i')) ?></p>
        <p class="ath-stat__label">Dernier usage</p>
    </div>
    <div class="ath-stat">
        <p class="ath-stat__value"><?= count($enlistments) ?></p>
        <p class="ath-stat__label">Candidatures</p>
    </div>
</div>

<?php
$settingsRows = [
    ['Validation automatique', $autoAccept ? 'Oui' : 'Non', $autoAccept ? 'Actif' : 'Sur revue'],
];
if ($linkedRecruitmentOpening !== null) {
    $openingTitle = trim((string) ($linkedRecruitmentOpening['title'] ?? ''));
    $settingsRows[] = ['Offre liée', $openingTitle !== '' ? $openingTitle : 'Sans titre', 'Actif'];
}
$specialty = trim((string) ($inviteCode['default_specialty'] ?? ''));
if ($specialty !== '') {
    $settingsRows[] = ['Spécialité par défaut', $specialty, 'Actif'];
}

$athTableTitle = 'Paramètres du code';
$athTableCount = count($settingsRows) . ' réglage' . (count($settingsRows) > 1 ? 's' : '');
$athTableCols = ['RÉGLAGE', 'VALEUR', 'ÉTAT|b'];
$athTableRows = $settingsRows;
$athTableFilters = [];
$athTableMinWidth = '720px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableRowActions = null;
$athTableFoot = 'Les réglages non renseignés ne sont pas listés.';
require base_path('views/partials/ath_table.php');

$statusLabel = static function (string $status): string {
    return match ($status) {
        'reviewed' => 'Acceptée',
        'rejected' => 'Refusée',
        'blocked' => 'Bloquée',
        default => 'En attente',
    };
};

$athTableTitle = 'Candidatures ayant utilisé ce code';
$athTableCount = count($enlistments);
$athTableCols = ['CANDIDAT', 'ADRESSE E-MAIL|m', 'UTILISÉ LE|m', 'ÉTAT|b'];
$athTableRows = [];
$athTableRowHrefs = [];
$athTableRowActions = [];
foreach ($enlistments as $enlistment) {
    $name = trim(((string) ($enlistment['first_name'] ?? '')) . ' ' . ((string) ($enlistment['last_name'] ?? '')));
    $dossierUrl = url('back-office/recruitments/' . (int) ($enlistment['id'] ?? 0));
    $athTableRows[] = [
        $name !== '' ? $name : 'Candidat',
        (string) ($enlistment['email'] ?? '—'),
        $fmtDate($enlistment['used_at'] ?? null, 'd/m/Y H:i'),
        $statusLabel((string) ($enlistment['status'] ?? 'submitted')),
    ];
    $athTableRowHrefs[] = $dossierUrl;
    // Balisage d’action construit ici, échappements compris (cf. contrat de ath_table.php).
    $athTableRowActions[] = '<a href="' . $h($dossierUrl) . '" class="ath-row-action">Ouvrir le dossier</a>';
}
$athTableActionsLabel = 'DOSSIER';
$athTableMinWidth = '1080px';
$athTableFoot = $enlistments === []
    ? 'Ce code n’a encore été utilisé par aucune candidature.'
    : 'Cliquez une ligne pour ouvrir le dossier du candidat.';
require base_path('views/partials/ath_table.php');
?>

<div class="ath-warn">
    <p class="ath-warn__title">Désactivation définitive</p>
    <p class="ath-warn__text">
        Désactiver ce code l’empêche immédiatement d’être utilisé. L’opération est irréversible :
        les candidatures déjà validées avec ce code ne sont pas remises en cause.
    </p>
    <form method="post" action="<?= $h($baseUrl . '/' . $codeId . '/desactiver') ?>" style="margin-top:11px;"
          onsubmit="return confirm('Désactiver ce code ? L’opération est irréversible.');">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <button type="submit" class="ath-row-action ath-row-action--danger">Désactiver ce code</button>
    </form>
</div>
