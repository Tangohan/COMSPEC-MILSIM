<?php
declare(strict_types=1);

use App\Support\OrganizationRoleLabels;

/**
 * Zone 2 — Tableur plein page des invitations envoyées.
 *
 * @var list<array<string, mixed>> $invitations
 * @var list<array<string, mixed>> $inviteUnits
 * @var list<array{id: int, label: string, name: string}> $inviteJobRoleOptions
 * @var bool $canAdd
 * @var string $inviteFilterStatus
 * @var string $organizationRoleLabelMode
 * @var array{pending: int, accepted: int, revoked: int, expired: int, total: int} $inviteStatusCounts
 */
$invitations = $invitations ?? [];
$inviteUnits = $inviteUnits ?? [];
$inviteJobRoleOptions = $inviteJobRoleOptions ?? [];
$inviteFilterStatus = $inviteFilterStatus ?? '';
$organizationRoleLabelMode = $organizationRoleLabelMode ?? OrganizationRoleLabels::MODE_FR;
$canAdd = (bool) ($canAdd ?? false);
$inviteStatusCounts = $inviteStatusCounts ?? [
    'pending' => 0,
    'accepted' => 0,
    'revoked' => 0,
    'expired' => 0,
    'total' => 0,
];

$statusPresentation = static function (string $raw): array {
    return match ($raw) {
        'pending' => [
            'label' => 'En attente',
            'class' => 'inv-sheet__badge inv-sheet__badge--pending',
        ],
        'accepted' => [
            'label' => 'Compte rattaché',
            'class' => 'inv-sheet__badge inv-sheet__badge--ok',
        ],
        'revoked' => [
            'label' => 'Annulée',
            'class' => 'inv-sheet__badge inv-sheet__badge--muted',
        ],
        'expired' => [
            'label' => 'Expirée',
            'class' => 'inv-sheet__badge inv-sheet__badge--muted',
        ],
        default => [
            'label' => 'État indéterminé',
            'class' => 'inv-sheet__badge inv-sheet__badge--muted',
        ],
    };
};

$formatDt = static function (?string $mysql): string {
    if ($mysql === null || $mysql === '') {
        return '—';
    }
    $t = strtotime($mysql);

    return $t ? date('d/m/Y H:i', $t) : '—';
};

$payloadSummary = static function (?string $raw, array $unitsById, array $jobLabelsById): string {
    if ($raw === null || $raw === '') {
        return '';
    }
    $d = json_decode($raw, true);
    if (!is_array($d)) {
        return '';
    }
    $parts = [];
    $uid = isset($d['unit_id']) ? (int) $d['unit_id'] : 0;
    if ($uid > 0 && isset($unitsById[$uid])) {
        $lab = isset($d['assignment_label']) ? trim((string) $d['assignment_label']) : '';
        $parts[] = $unitsById[$uid] . ($lab !== '' ? ' — ' . $lab : '');
    }
    $jid = isset($d['personnel_job_role_id']) ? (int) $d['personnel_job_role_id'] : 0;
    if ($jid > 0 && isset($jobLabelsById[$jid])) {
        $parts[] = $jobLabelsById[$jid];
    }

    return $parts !== [] ? implode(' · ', $parts) : '';
};

$unitsById = [];
foreach ($inviteUnits as $u) {
    $unitsById[(int) ($u['id'] ?? 0)] = (string) ($u['name'] ?? '');
}
$jobLabelsById = [];
foreach ($inviteJobRoleOptions as $jo) {
    $jobLabelsById[(int) ($jo['id'] ?? 0)] = (string) ($jo['label'] ?? $jo['name'] ?? '');
}

$filterTabs = [
    '' => ['label' => 'Toutes', 'count' => (int) ($inviteStatusCounts['total'] ?? 0)],
    'pending' => ['label' => 'En attente', 'count' => (int) ($inviteStatusCounts['pending'] ?? 0)],
    'accepted' => ['label' => 'Rattachées', 'count' => (int) ($inviteStatusCounts['accepted'] ?? 0)],
    'revoked' => ['label' => 'Annulées', 'count' => (int) ($inviteStatusCounts['revoked'] ?? 0)],
    'expired' => ['label' => 'Expirées', 'count' => (int) ($inviteStatusCounts['expired'] ?? 0)],
];
$baseSentUrl = url('back-office/invitations/envoyees');
$composeUrl = url('back-office/invitations');
$rowCount = count($invitations);
$isAthShell = !empty($isBackOfficeShell);
?>
<?php if (!$isAthShell): ?>
<link href="<?= htmlspecialchars(asset_url('assets/css/invitations-sheet.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<?php endif; ?>
<div class="inv-sheet<?= $isAthShell ? ' inv-sheet--ath' : '' ?>" x-data="{ q: '' }">
    <header class="inv-sheet__top">
        <?php if (!$isAthShell): ?>
        <div class="inv-sheet__top-main">
            <div>
                <p class="inv-sheet__kicker">Membres · Suivi</p>
                <h1 class="inv-sheet__title">Invitations envoyées</h1>
                <p class="inv-sheet__lead">
                    <?= $rowCount ?> ligne<?= $rowCount > 1 ? 's' : '' ?> affichée<?= $rowCount > 1 ? 's' : '' ?>
                    <?php if ($inviteFilterStatus !== ''): ?> · filtre actif<?php endif; ?>
                    · plus récentes en premier
                </p>
            </div>
            <div class="inv-sheet__top-actions">
                <?php if ($canAdd): ?>
                    <a href="<?= htmlspecialchars($composeUrl, ENT_QUOTES, 'UTF-8') ?>" class="inv-sheet__btn inv-sheet__btn--solid">Nouvelle invitation</a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(url('back-office/users'), ENT_QUOTES, 'UTF-8') ?>" class="inv-sheet__btn inv-sheet__btn--ghost">Membres</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isAthShell): ?>
        <?php
        $invTotal = max(1, (int) ($inviteStatusCounts['total'] ?? 0));
        $athKpis = [
            ['label' => 'TOTAL', 'value' => (string) $rowCount, 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'lignes affichées'],
            ['label' => 'EN ATTENTE', 'value' => (string) (int) ($inviteStatusCounts['pending'] ?? 0), 'delta' => '', 'tone' => '#c98a12', 'pct' => (int) round((int) ($inviteStatusCounts['pending'] ?? 0) / $invTotal * 100) . '%', 'note' => 'sans réponse'],
            ['label' => 'RATTACHÉES', 'value' => (string) (int) ($inviteStatusCounts['accepted'] ?? 0), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => (int) round((int) ($inviteStatusCounts['accepted'] ?? 0) / $invTotal * 100) . '%', 'note' => 'comptes créés'],
            ['label' => 'ANNULÉES', 'value' => (string) (int) ($inviteStatusCounts['revoked'] ?? 0), 'delta' => '', 'tone' => '#64748b', 'pct' => '—', 'note' => 'liens révoqués'],
        ];
        require base_path('views/partials/ath_kpis.php');
        ?>
        <?php endif; ?>

        <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
        <?php if ($f): ?>
            <div class="inv-sheet__flash inv-sheet__flash--err" role="alert"><?= htmlspecialchars($f) ?></div>
        <?php endif; ?>
        <?php if ($s): ?>
            <div class="inv-sheet__flash inv-sheet__flash--ok" role="status"><?= htmlspecialchars($s) ?></div>
        <?php endif; ?>

        <div class="inv-sheet__toolbar">
            <nav class="inv-sheet__filters" aria-label="Filtrer par état">
                <?php foreach ($filterTabs as $fkey => $ftab):
                    $isActive = $inviteFilterStatus === $fkey;
                    $href = $fkey === '' ? $baseSentUrl : $baseSentUrl . '?status=' . rawurlencode($fkey);
                    ?>
                    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                       class="inv-sheet__filter<?= $isActive ? ' is-active' : '' ?>"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <?= htmlspecialchars($ftab['label']) ?>
                        <span><?= (int) $ftab['count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <label class="inv-sheet__search">
                <span class="sr-only">Rechercher dans le tableau</span>
                <input type="search" x-model="q" placeholder="Filtrer l’affichage (e-mail, rôle…)" autocomplete="off">
            </label>
        </div>
    </header>

    <div class="inv-sheet__viewport" role="region" aria-label="Tableau des invitations" tabindex="0">
        <table class="inv-sheet__table">
            <thead>
                <tr>
                    <th class="num" scope="col">#</th>
                    <th scope="col">Personne invitée</th>
                    <th scope="col">État</th>
                    <th scope="col">Rôle prévu</th>
                    <th scope="col">À l’arrivée</th>
                    <th scope="col">Envoyée</th>
                    <th scope="col">Valable jusqu’au</th>
                    <th scope="col">Invitée par</th>
                    <th class="num" scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($invitations === []): ?>
                <tr>
                    <td colspan="9" class="inv-sheet__empty">
                        <p class="inv-sheet__empty-title">Aucune invitation pour ce filtre.</p>
                        <p class="inv-sheet__empty-text">Lorsque vous enverrez une invitation, elle apparaîtra ici avec son état et les détails prévus pour l’organigramme.</p>
                        <?php if ($canAdd): ?>
                            <a href="<?= htmlspecialchars($composeUrl, ENT_QUOTES, 'UTF-8') ?>" class="inv-sheet__btn inv-sheet__btn--solid">Créer une invitation</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($invitations as $idx => $i):
                    $rawStatus = (string) ($i['status'] ?? '');
                    $sp = $statusPresentation($rawStatus);
                    $pay = $payloadSummary($i['invitation_payload'] ?? null, $unitsById, $jobLabelsById);
                    $roleLabel = OrganizationRoleLabels::displayName([
                        'name' => $i['role_name'] ?? '',
                        'label_en' => $i['role_label_en'] ?? '',
                    ], $organizationRoleLabelMode);
                    if ($roleLabel === '' || $roleLabel === '—') {
                        $roleLabel = '—';
                    }
                    $created = $formatDt(isset($i['created_at']) ? (string) $i['created_at'] : null);
                    $expires = $rawStatus === 'pending'
                        ? $formatDt(isset($i['expires_at']) ? (string) $i['expires_at'] : null)
                        : '—';
                    $inviter = trim((string) ($i['inviter_email'] ?? ''));
                    $email = (string) ($i['email'] ?? '');
                    $searchBlob = mb_strtolower(trim(implode(' ', [$email, $sp['label'], $roleLabel, $pay, $inviter, $created, $expires])), 'UTF-8');
                    ?>
                    <tr data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>"
                        x-show="!q || ($el.dataset.search || '').includes(q.toLowerCase().trim())">
                        <td class="num muted"><?= (int) ($idx + 1) ?></td>
                        <td class="strong break"><?= htmlspecialchars($email) ?></td>
                        <td><span class="<?= htmlspecialchars($sp['class']) ?>"><?= htmlspecialchars($sp['label']) ?></span></td>
                        <td><?= htmlspecialchars($roleLabel) ?></td>
                        <td class="clamp"><?= $pay !== '' ? htmlspecialchars($pay) : '<span class="muted">—</span>' ?></td>
                        <td class="mono nowrap"><?= htmlspecialchars($created) ?></td>
                        <td class="mono nowrap"><?= htmlspecialchars($expires) ?></td>
                        <td class="break"><?= $inviter !== '' ? htmlspecialchars($inviter) : '<span class="muted">—</span>' ?></td>
                        <td class="num">
                            <?php if ($rawStatus === 'pending'): ?>
                            <form method="post" action="<?= url('back-office/invitations/revoke') ?>"
                                onsubmit="return confirm('Annuler cette invitation ? La personne ne pourra plus utiliser le lien reçu par e-mail.');"
                                class="inline">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) ($i['id'] ?? 0) ?>">
                                <?php if ($inviteFilterStatus !== ''): ?>
                                    <input type="hidden" name="return_status" value="<?= htmlspecialchars($inviteFilterStatus, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                                <button type="submit" class="inv-sheet__revoke">Annuler</button>
                            </form>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
