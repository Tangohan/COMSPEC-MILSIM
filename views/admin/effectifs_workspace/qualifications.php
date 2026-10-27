<?php
declare(strict_types=1);

/** @var bool $qualificationsReady */
/** @var int $qualificationsHorizon */
/** @var list<array<string, mixed>> $qualificationsExpired */
/** @var list<array<string, mixed>> $qualificationsExpiring */

$ready = (bool) ($qualificationsReady ?? false);
$horizon = (int) ($qualificationsHorizon ?? 60);
$expired = is_array($qualificationsExpired ?? null) ? $qualificationsExpired : [];
$expiring = is_array($qualificationsExpiring ?? null) ? $qualificationsExpiring : [];

$moisFr = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$formatDate = static function (?string $iso) use ($moisFr): string {
    $iso = trim((string) $iso);
    if ($iso === '') {
        return '—';
    }
    $ts = strtotime($iso);
    if ($ts === false) {
        return $iso;
    }

    return sprintf('%d %s %d', (int) date('j', $ts), $moisFr[(int) date('n', $ts)], (int) date('Y', $ts));
};

/** Jours restants avant échéance (négatif si dépassée). */
$daysLeft = static function (?string $iso): ?int {
    $iso = trim((string) $iso);
    if ($iso === '') {
        return null;
    }
    $ts = strtotime(substr($iso, 0, 10) . ' 00:00:00');
    if ($ts === false) {
        return null;
    }

    return (int) floor(($ts - strtotime(date('Y-m-d') . ' 00:00:00')) / 86400);
};

$holderName = static function (array $row): string {
    $name = trim((string) ($row['display_name'] ?? ''));
    $callsign = trim((string) ($row['callsign'] ?? ''));
    if ($name === '' && $callsign === '') {
        return 'Membre #' . (int) ($row['user_id'] ?? 0);
    }
    if ($callsign !== '' && $name !== '') {
        return $name . ' · ' . $callsign;
    }

    return $name !== '' ? $name : $callsign;
};

$horizonUrl = static function (int $days): string {
    return effectifs_workspace_url('qualifications') . '?horizon=' . $days;
};
?>
<section class="eff-rh-hero">
    <div class="eff-pg__actions">
        <a class="eff-catalog__btn" href="<?= htmlspecialchars(url('back-office/referentiels/qualifications/cursus-pilote'), ENT_QUOTES, 'UTF-8') ?>">Cursus pilote</a>
        <a class="eff-catalog__btn" href="<?= htmlspecialchars(url('formation'), ENT_QUOTES, 'UTF-8') ?>">Catalogue de formation</a>
    </div>
    <p class="eff-page-kicker">Aptitudes</p>
    <h2 class="eff-page-title">Qualifications</h2>
    <p class="eff-page-lead">
        Qualifications échues ou proches de l’échéance. Elles sont délivrées automatiquement
        par les formations certifiantes : une attestation émise crée la qualification, et son
        renouvellement passe par un nouveau passage de la formation.
    </p>
    <?php if ($ready): ?>
    <div class="eff-rh-tiles" aria-label="Aperçu des échéances">
        <article class="eff-rh-tile <?= $expired === [] ? 'eff-rh-tile--ok' : 'eff-rh-tile--bad' ?>">
            <span class="eff-rh-tile__kicker">Échues</span>
            <strong class="eff-rh-tile__value"><?= count($expired) ?></strong>
            <span class="eff-rh-tile__label">qualification<?= count($expired) > 1 ? 's' : '' ?> à repasser</span>
        </article>
        <article class="eff-rh-tile <?= $expiring === [] ? 'eff-rh-tile--ok' : 'eff-rh-tile--warn' ?>">
            <span class="eff-rh-tile__kicker">À renouveler</span>
            <strong class="eff-rh-tile__value"><?= count($expiring) ?></strong>
            <span class="eff-rh-tile__label">dans les <?= $horizon ?> prochains jours</span>
        </article>
        <article class="eff-rh-tile">
            <span class="eff-rh-tile__kicker">Horizon</span>
            <strong class="eff-rh-tile__value"><?= $horizon ?> j</strong>
            <span class="eff-rh-tile__label">fenêtre de suivi affichée</span>
        </article>
    </div>
    <?php endif; ?>
</section>

<?php if (!$ready): ?>
    <div class="eff-catalog">
        <div class="eff-catalog__empty">
            <strong>Suivi des qualifications indisponible</strong>
            La base n’a pas encore reçu la mise à jour qui relie les attestations de formation au
            dossier personnel. Demandez à un administrateur de lancer la mise à jour de la base,
            puis rechargez cette page.
        </div>
    </div>
<?php else: ?>

<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Échéances</p>
            <h3 class="eff-catalog__title">À renouveler</h3>
            <p class="eff-catalog__lead">Les qualifications échues d’abord, puis celles qui expirent dans l’horizon choisi.</p>
        </div>
        <nav class="eff-seg" aria-label="Horizon">
            <?php foreach ([30, 60, 90] as $days): ?>
                <a class="<?= $days === $horizon ? 'is-on' : '' ?>" href="<?= htmlspecialchars($horizonUrl($days), ENT_QUOTES, 'UTF-8') ?>"<?= $days === $horizon ? ' aria-current="true"' : '' ?>><?= $days ?> j</a>
            <?php endforeach; ?>
        </nav>
    </div>

    <?php if ($expired === [] && $expiring === []): ?>
        <div class="eff-catalog__empty">
            <strong>Aucune échéance dans les <?= $horizon ?> prochains jours</strong>
            Toutes les qualifications enregistrées restent valides sur cette période. Élargissez
            l’horizon pour anticiper davantage, ou consultez le catalogue de formation pour ouvrir
            de nouvelles sessions certifiantes.
        </div>
    <?php else: ?>
        <div class="eff-sheets" role="region" aria-label="Tableau des qualifications à renouveler" tabindex="0">
            <table class="eff-sheets__table">
                <thead>
                    <tr>
                        <th>Membre</th>
                        <th>Qualification</th>
                        <th>Échéance</th>
                        <th>Reste</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_merge($expired, $expiring) as $row):
                    $left = $daysLeft($row['expires_at'] ?? null);
                    $isExpired = $left !== null && $left < 0;
                    $level = trim((string) ($row['level'] ?? ''));
                    $uid = (int) ($row['user_id'] ?? 0);
                    ?>
                    <tr>
                        <td>
                            <?php if ($uid > 0): ?>
                                <a href="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $uid), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($holderName($row), ENT_QUOTES, 'UTF-8') ?></a>
                            <?php else: ?>
                                <span class="eff-sheets__cell-text"><?= htmlspecialchars($holderName($row), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="eff-sheets__cell-text"><?= htmlspecialchars((string) ($row['qualification_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($level !== ''): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--muted"><?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($formatDate($row['expires_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($left === null): ?>
                                <span class="eff-sheets__path-muted">—</span>
                            <?php elseif ($left < 0): ?>
                                <span class="is-late"><?= abs($left) ?> j de retard</span>
                            <?php elseif ($left === 0): ?>
                                <span class="is-soon">aujourd’hui</span>
                            <?php else: ?>
                                <span class="<?= $left <= 14 ? 'is-soon' : '' ?>"><?= $left ?> j</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isExpired): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--bad">Échue</span>
                            <?php else: ?>
                                <span class="eff-sheets__badge eff-sheets__badge--warn">À renouveler</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="eff-card-foot">
            Une qualification échue n’ouvre plus les postes d’opération qui l’exigent. Pour la
            renouveler, inscrivez le membre à la formation certifiante correspondante : l’attestation
            réémise met la qualification à jour automatiquement.
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>
