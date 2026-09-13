<?php
declare(strict_types=1);

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

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
// Aligné sur MemberDepartureRepository::REASONS

$filterUrl = static function (?string $reason, int $p = 1) {
    $q = array_filter([
        'motif' => $reason,
        'page' => $p > 1 ? $p : null,
    ], static fn ($v) => $v !== null && $v !== '');

    return effectifs_workspace_url('departs') . ($q ? '?' . http_build_query($q) : '');
};

$filterLabel = $reasonFilter === null
    ? 'Tous'
    : (string) ($reasonLabels[$reasonFilter] ?? $reasonFilter);
?>
<section class="eff-rh-hero">
    <p class="eff-page-kicker">Dossier RH</p>
    <h2 class="eff-page-title">Départs</h2>
    <p class="eff-page-lead">
        Historique des départs enregistrés : motif, date et statut de la reprise d’accès.
        Utile pour préparer une réintégration ou vérifier qu’un départ a bien été clôturé.
    </p>
    <div class="eff-rh-tiles" aria-label="Aperçu des départs">
        <article class="eff-rh-tile">
            <span class="eff-rh-tile__kicker">Registre</span>
            <strong class="eff-rh-tile__value"><?= $total ?></strong>
            <span class="eff-rh-tile__label">départ<?= $total > 1 ? 's' : '' ?> enregistré<?= $total > 1 ? 's' : '' ?></span>
        </article>
        <article class="eff-rh-tile">
            <span class="eff-rh-tile__kicker">Filtre</span>
            <strong class="eff-rh-tile__value"><?= $h($filterLabel) ?></strong>
            <span class="eff-rh-tile__label">motif affiché</span>
        </article>
        <a class="eff-rh-tile" href="<?= $h(effectifs_workspace_url('alertes')) ?>">
            <span class="eff-rh-tile__kicker">Suite</span>
            <strong class="eff-rh-tile__value">Alertes</strong>
            <span class="eff-rh-tile__label">Signaux de suivi RH</span>
        </a>
    </div>
</section>

<div class="eff-rh-pills" role="tablist" aria-label="Filtrer par motif">
    <a href="<?= $h($filterUrl(null)) ?>" class="eff-rh-pill <?= $reasonFilter === null ? 'is-on' : '' ?>">Tous</a>
    <?php foreach ($reasonLabels as $rValue => $rLabel): ?>
        <a href="<?= $h($filterUrl($rValue)) ?>" class="eff-rh-pill <?= $reasonFilter === $rValue ? 'is-on' : '' ?>"><?= $h($rLabel) ?></a>
    <?php endforeach; ?>
</div>

<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Historique</p>
            <h2 class="eff-catalog__title">Anciens membres</h2>
            <p class="eff-catalog__lead">Les départs saisis depuis une fiche membre apparaissent ici.</p>
        </div>
        <div class="eff-catalog__tools">
            <a href="<?= $h(effectifs_workspace_url()) ?>" class="eff-catalog__btn">← Tableur</a>
        </div>
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
                            <strong class="eff-sheets__name"><?= $h($userName) ?></strong>
                            <?php if ($reinstated): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--ok" title="Réintégration enregistrée">Réintégré</span>
                            <?php elseif ($userStatus !== '' && $userStatus !== 'inactive'): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--watch" title="Le compte n’est plus au statut inactif">Compte actif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $h($reasonLabels[$reason] ?? $reason) ?>
                            <?php if ($note !== ''): ?><br><span class="eff-sheets__meta"><?= $h($note) ?></span><?php endif; ?>
                        </td>
                        <td><?= $departedAt !== '' ? $h(date('d/m/Y', (int) strtotime($departedAt))) : '—' ?></td>
                        <td><?= $h($initiatorName) ?></td>
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
                            <?php if ($uid > 0): ?>
                                <a class="is-primary" href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>">Fiche</a>
                            <?php endif; ?>
                            <?php if ($depId > 0 && !$archived): ?>
                                <form method="post" action="<?= $h(effectifs_workspace_url('departs/' . $depId . '/archiver')) ?>" style="display:inline" onsubmit="return confirm('Archiver ce dossier RH ?');">
                                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                    <button type="submit" class="eff-catalog__btn">Archiver</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($depId > 0 && !$reinstated): ?>
                                <form method="post" action="<?= $h(effectifs_workspace_url('departs/' . $depId . '/reintegrer')) ?>" style="display:inline" onsubmit="return confirm('Réintégrer ce membre (réactiver le compte) ?');">
                                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                    <button type="submit" class="eff-catalog__btn">Réintégrer</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="eff-catalog-foot">
            <p style="margin:0">
                <strong><?= $total ?></strong>
                départ<?= $total > 1 ? 's' : '' ?> — page <?= $page ?> / <?= $totalPages ?>
            </p>
            <div class="eff-catalog-foot__links">
                <?php if ($page > 1): ?>
                    <a class="eff-catalog__btn" href="<?= $h($filterUrl($reasonFilter, $page - 1)) ?>">Page précédente</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="eff-catalog__btn" href="<?= $h($filterUrl($reasonFilter, $page + 1)) ?>">Page suivante</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
