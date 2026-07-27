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
<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Aptitudes</p>
            <h1 class="eff-catalog__title">Qualifications</h1>
            <p class="eff-catalog__lead">
                Qualifications échues ou proches de l’échéance. Elles sont délivrées automatiquement
                par les formations certifiantes : une attestation émise crée la qualification, et son
                renouvellement passe par un nouveau passage de la formation.
            </p>
        </div>
        <div class="eff-catalog__tools">
            <a class="eff-catalog__btn" href="<?= htmlspecialchars(url('formation'), ENT_QUOTES, 'UTF-8') ?>">Catalogue de formation</a>
        </div>
    </div>

    <?php if (!$ready): ?>
        <div class="eff-catalog__empty">
            <strong>Suivi des qualifications indisponible</strong>
            La base n’a pas encore reçu la mise à jour qui relie les attestations de formation au
            dossier personnel. Demandez à un administrateur de lancer la mise à jour de la base,
            puis rechargez cette page.
        </div>
    <?php else: ?>

    <div class="eff-catalog-filters" style="grid-template-columns: repeat(3, minmax(0, 1fr)); border-bottom: 0; padding-bottom: 0.35rem;">
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">Échues</p>
            <p style="margin:0.15rem 0 0;font-size:1.35rem;font-weight:900;color:<?= $expired === [] ? '#0f172a' : '#991b1b' ?>;font-variant-numeric:tabular-nums"><?= count($expired) ?></p>
        </div>
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">À renouveler</p>
            <p style="margin:0.15rem 0 0;font-size:1.35rem;font-weight:900;color:<?= $expiring === [] ? '#0f172a' : '#92400e' ?>;font-variant-numeric:tabular-nums"><?= count($expiring) ?></p>
        </div>
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">Horizon</p>
            <p style="margin:0.35rem 0 0;display:flex;gap:0.35rem;flex-wrap:wrap">
                <?php foreach ([30, 60, 90] as $h): ?>
                    <a class="eff-catalog__btn<?= $h === $horizon ? ' eff-catalog__btn--primary' : '' ?>"
                       style="padding:0.2rem 0.6rem;font-size:0.75rem"
                       href="<?= htmlspecialchars($horizonUrl($h), ENT_QUOTES, 'UTF-8') ?>"><?= $h ?> j</a>
                <?php endforeach; ?>
            </p>
        </div>
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
                <colgroup>
                    <col style="width:16rem">
                    <col style="width:18rem">
                    <col style="width:9rem">
                    <col style="width:9rem">
                    <col style="width:10rem">
                </colgroup>
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
                        <td><span class="eff-sheets__meta"><?= htmlspecialchars($formatDate($row['expires_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                            <span class="eff-sheets__meta" style="font-variant-numeric:tabular-nums">
                                <?php if ($left === null): ?>
                                    —
                                <?php elseif ($left < 0): ?>
                                    <?= abs($left) ?> j de retard
                                <?php elseif ($left === 0): ?>
                                    aujourd’hui
                                <?php else: ?>
                                    <?= $left ?> j
                                <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($isExpired): ?>
                                <span class="eff-sheets__badge" style="background:#fef2f2;color:#991b1b">Échue</span>
                            <?php else: ?>
                                <span class="eff-sheets__badge" style="background:#fffbeb;color:#92400e">À renouveler</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="eff-catalog__lead" style="margin-top:0.75rem;font-size:0.8125rem">
            Une qualification échue n’ouvre plus les postes d’opération qui l’exigent. Pour la
            renouveler, inscrivez le membre à la formation certifiante correspondante : l’attestation
            réémise met la qualification à jour automatiquement.
        </p>
    <?php endif; ?>

    <?php endif; ?>
</div>
