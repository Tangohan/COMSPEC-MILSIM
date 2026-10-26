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
<section class="eff-rh-hero">
    <div class="eff-pg__actions">
        <a href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn">Tableur des effectifs</a>
    </div>
    <p class="eff-page-kicker">Ressources humaines</p>
    <h2 class="eff-page-title">Anciens membres</h2>
    <p class="eff-page-lead">
        Historique des départs enregistrés : motif, date et statut de la reprise d’accès.
        Utile pour repérer une réintégration future ou vérifier qu’un départ a bien été clôturé.
    </p>
</section>

<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Registre</p>
            <h3 class="eff-catalog__title">Départs</h3>
            <p class="eff-catalog__lead"><?= $total ?> départ<?= $total > 1 ? 's' : '' ?> enregistré<?= $total > 1 ? 's' : '' ?><?= $reasonFilter !== null ? ' pour ce motif' : '' ?>.</p>
        </div>
        <nav class="eff-seg" aria-label="Filtrer par motif">
            <a href="<?= htmlspecialchars($filterUrl(null), ENT_QUOTES, 'UTF-8') ?>" class="<?= $reasonFilter === null ? 'is-on' : '' ?>">Tous</a>
            <?php foreach ($reasonLabels as $rValue => $rLabel): ?>
                <a href="<?= htmlspecialchars($filterUrl($rValue), ENT_QUOTES, 'UTF-8') ?>" class="<?= $reasonFilter === $rValue ? 'is-on' : '' ?>"><?= htmlspecialchars($rLabel, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </nav>
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
                        <th>Dossier</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $d): ?>
                    <?php
                    $depId = (int) ($d['id'] ?? 0);
                    $uid = (int) ($d['user_id'] ?? 0);
                    $userName = trim((string) ($d['user_display_name'] ?? '')) ?: trim((string) ($d['user_email'] ?? '')) ?: 'Membre';
                    $userStatus = (string) ($d['user_status'] ?? '');
                    $reason = (string) ($d['reason'] ?? 'other');
                    $departedAt = (string) ($d['departed_at'] ?? '');
                    $initiatorName = trim((string) ($d['initiator_display_name'] ?? '')) ?: trim((string) ($d['initiator_email'] ?? '')) ?: '—';
                    $accessRevoked = !empty($d['access_revoked']);
                    $archived = !empty($d['dossier_archived']);
                    $reinstated = !empty($d['reinstated_at']);
                    $note = trim((string) ($d['reason_note'] ?? ''));
                    $csrf = htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr>
                        <td>
                            <strong class="eff-sheets__name"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($reinstated): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--ok" title="Réintégration enregistrée">Réintégré</span>
                            <?php elseif ($userStatus !== '' && $userStatus !== 'inactive'): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--watch" title="Le compte n’est plus au statut inactif">Compte actif</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($reasonLabels[$reason] ?? $reason, ENT_QUOTES, 'UTF-8') ?><?php if ($note !== ''): ?><span class="eff-sheets__meta"><?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></td>
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
                            <?php if ($archived): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--muted">Archivé</span>
                            <?php else: ?>
                                <span class="eff-sheets__badge">Ouvert</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="eff-sheets__actions">
                            <?php if ($uid > 0): ?>
                                <a href="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $uid), ENT_QUOTES, 'UTF-8') ?>">Fiche</a>
                            <?php endif; ?>
                            <?php if ($depId > 0 && !$archived): ?>
                                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('departs/' . $depId . '/archiver'), ENT_QUOTES, 'UTF-8') ?>" style="display:inline" onsubmit="return confirm('Archiver ce dossier RH ?');">
                                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                    <button type="submit">Archiver</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($depId > 0 && !$reinstated): ?>
                                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('departs/' . $depId . '/reintegrer'), ENT_QUOTES, 'UTF-8') ?>" style="display:inline" onsubmit="return confirm('Réintégrer ce membre (réactiver le compte) ?');">
                                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                    <button type="submit">Réintégrer</button>
                                </form>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="eff-card-foot">
            <span>Page <strong><?= $page ?></strong> / <?= $totalPages ?></span>
            <div>
                <?php if ($page > 1): ?>
                    <a class="eff-catalog__btn" href="<?= htmlspecialchars($filterUrl($reasonFilter, $page - 1), ENT_QUOTES, 'UTF-8') ?>">Page précédente</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="eff-catalog__btn" href="<?= htmlspecialchars($filterUrl($reasonFilter, $page + 1), ENT_QUOTES, 'UTF-8') ?>">Page suivante</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
