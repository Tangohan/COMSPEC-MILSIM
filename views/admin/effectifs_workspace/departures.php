<?php
declare(strict_types=1);

/**
 * Offboarding structuré — historique des départs, filtrable par motif.
 *
 * @var list<array<string,mixed>> $departures
 * @var string|null $departureReasonFilter
 * @var int $departureTotal
 * @var int $departurePage
 * @var int $departureTotalPages
 */

$rows = is_array($departures ?? null) ? $departures : [];
$reasonFilter = $departureReasonFilter ?? null;
$total = (int) ($departureTotal ?? count($rows));
$page = (int) ($departurePage ?? 1);
$totalPages = (int) ($departureTotalPages ?? 1);

$reasonLabels = [
    'end_of_engagement' => 'Fin d’engagement',
    'exclusion' => 'Exclusion',
    'pause' => 'Pause',
    'other' => 'Autre',
];

$filterUrl = static function (?string $reason, int $p = 1) {
    $q = array_filter([
        'motif' => $reason,
        'page' => $p > 1 ? $p : null,
    ], static fn ($v) => $v !== null && $v !== '');

    return effectifs_workspace_url('departs') . ($q ? '?' . http_build_query($q) : '');
};
?>
<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Ressources humaines</p>
            <h1 class="eff-catalog__title">Anciens membres</h1>
            <p class="eff-catalog__lead">
                Historique des départs enregistrés : motif, date et statut de la reprise d’accès.
                Utile pour repérer une réintégration future ou vérifier qu’un départ a bien été clôturé.
            </p>
        </div>
        <div class="eff-catalog__tools">
            <a href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn">← Tableur</a>
        </div>
    </div>

    <div class="eff-catalog__tools" style="margin-bottom:1rem">
        <a href="<?= htmlspecialchars($filterUrl(null), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn <?= $reasonFilter === null ? 'eff-catalog__btn--primary' : '' ?>">Tous</a>
        <?php foreach ($reasonLabels as $rValue => $rLabel): ?>
            <a href="<?= htmlspecialchars($filterUrl($rValue), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn <?= $reasonFilter === $rValue ? 'eff-catalog__btn--primary' : '' ?>"><?= htmlspecialchars($rLabel, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($rows === []): ?>
        <div class="eff-catalog__empty">
            <strong>Aucun départ enregistré<?= $reasonFilter !== null ? ' pour ce motif' : '' ?>.</strong>
            Les départs enregistrés depuis une fiche membre apparaîtront ici.
        </div>
    <?php else: ?>
        <div class="eff-sheets" role="region" aria-label="Historique des départs" tabindex="0">
            <table class="eff-sheets__table">
                <thead>
                    <tr>
                        <th>Membre</th>
                        <th>Motif</th>
                        <th>Date</th>
                        <th>Enregistré par</th>
                        <th>Accès</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $d): ?>
                    <?php
                    $uid = (int) ($d['user_id'] ?? 0);
                    $userName = trim((string) ($d['user_display_name'] ?? '')) ?: trim((string) ($d['user_email'] ?? '')) ?: 'Membre';
                    $userStatus = (string) ($d['user_status'] ?? '');
                    $reason = (string) ($d['reason'] ?? 'other');
                    $departedAt = (string) ($d['departed_at'] ?? '');
                    $initiatorName = trim((string) ($d['initiator_display_name'] ?? '')) ?: trim((string) ($d['initiator_email'] ?? '')) ?: '—';
                    $accessRevoked = !empty($d['access_revoked']);
                    $note = trim((string) ($d['reason_note'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <strong class="eff-sheets__name"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($userStatus !== '' && $userStatus !== 'inactive'): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--watch" title="Le compte n’est plus au statut inactif">Réintégré ?</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($reasonLabels[$reason] ?? $reason, ENT_QUOTES, 'UTF-8') ?><?php if ($note !== ''): ?><br><span class="eff-sheets__meta"><?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></td>
                        <td><?= $departedAt !== '' ? htmlspecialchars(date('d/m/Y', strtotime($departedAt)), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                        <td><?= htmlspecialchars($initiatorName, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($accessRevoked): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--ok">Retirés</span>
                            <?php else: ?>
                                <span class="eff-sheets__badge eff-sheets__badge--muted">Non retirés</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($uid > 0): ?>
                                <a class="is-primary" href="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $uid), ENT_QUOTES, 'UTF-8') ?>">Fiche</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="eff-catalog-foot">
            <p style="margin:0">
                <strong style="color:#0f172a"><?= $total ?></strong>
                départ<?= $total > 1 ? 's' : '' ?> — page <?= $page ?> / <?= $totalPages ?>
            </p>
            <div class="eff-catalog-foot__links">
                <?php if ($page > 1): ?>
                    <a class="eff-catalog__btn" href="<?= htmlspecialchars($filterUrl($reasonFilter, $page - 1), ENT_QUOTES, 'UTF-8') ?>">Page précédente</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="eff-catalog__btn" href="<?= htmlspecialchars($filterUrl($reasonFilter, $page + 1), ENT_QUOTES, 'UTF-8') ?>">Page suivante</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
