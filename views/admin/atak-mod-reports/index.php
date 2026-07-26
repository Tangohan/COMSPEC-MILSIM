<?php
/** @var list<array<string, mixed>> $rows */
/** @var int $total */
/** @var int $totalAll */
/** @var string $severityFilter */
$rows = is_array($rows ?? null) ? $rows : [];
$total = (int) ($total ?? count($rows));
$totalAll = (int) ($totalAll ?? $total);
$severityFilter = trim((string) ($severityFilter ?? ''));

$fmtDate = static function (mixed $raw): string {
    $s = trim((string) $raw);
    if ($s === '') {
        return '—';
    }
    try {
        return (new \DateTimeImmutable($s))->format('d/m/Y H:i');
    } catch (\Throwable) {
        return $s;
    }
};

$maskSteam = static function (mixed $raw): string {
    $s = trim((string) $raw);
    if ($s === '') {
        return '—';
    }
    if (strlen($s) <= 8) {
        return $s;
    }

    return '…' . substr($s, -8);
};

$severityLabel = static function (string $sev): string {
    return match ($sev) {
        'error' => 'Erreur',
        'warn' => 'Alerte',
        'info' => 'Info',
        'bug' => 'Signalement',
        default => $sev,
    };
};

$csrfToken = \App\Core\Csrf::token();
$base = url('admin/atak-mod-reports');
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-atak-beta.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="bo-atak-beta">
    <header class="bo-atak-beta__hero">
        <p class="bo-atak-beta__eyebrow">Tactique · Mod Arma</p>
        <h1>Rapports Overwatch</h1>
        <p class="bo-atak-beta__lead">
            Erreurs automatiques et signalements joueurs remontés depuis le jeu vers Athena.
            Les doublons proches sont regroupés (compteur « occurrences »).
        </p>
        <nav class="bo-atak-beta__nav" aria-label="Liens associés">
            <a href="<?= htmlspecialchars(url('admin/atak-beta'), ENT_QUOTES, 'UTF-8') ?>">Accès anticipé</a>
            <span class="bo-atak-beta__nav-sep" aria-hidden="true">·</span>
            <a href="<?= htmlspecialchars(url('admin/atak-mod'), ENT_QUOTES, 'UTF-8') ?>">Pack Overwatch</a>
            <span class="bo-atak-beta__nav-sep" aria-hidden="true">·</span>
            <a href="<?= htmlspecialchars(url('admin/atak-config'), ENT_QUOTES, 'UTF-8') ?>">Configuration ATAK</a>
        </nav>
    </header>

    <?php if ($flash = \App\Core\Session::getFlash('success')): ?>
        <p class="bo-atak-beta__flash bo-atak-beta__flash--ok"><?= htmlspecialchars((string) $flash, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($flash = \App\Core\Session::getFlash('error')): ?>
        <p class="bo-atak-beta__flash bo-atak-beta__flash--err"><?= htmlspecialchars((string) $flash, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <section class="bo-atak-beta__stats" aria-label="Résumé">
        <div class="bo-atak-beta__stat">
            <span class="bo-atak-beta__stat-label">Au total</span>
            <strong><?= (int) $totalAll ?></strong>
        </div>
        <div class="bo-atak-beta__stat">
            <span class="bo-atak-beta__stat-label">Affichés</span>
            <strong><?= (int) $total ?></strong>
        </div>
    </section>

    <form method="get" action="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>" class="bo-atak-beta__filters" style="margin:1rem 0;display:flex;gap:.75rem;flex-wrap:wrap;align-items:end;">
        <label>
            <span style="display:block;font-size:.8rem;opacity:.75;margin-bottom:.25rem;">Type</span>
            <select name="severity">
                <option value="" <?= $severityFilter === '' ? 'selected' : '' ?>>Tous</option>
                <option value="error" <?= $severityFilter === 'error' ? 'selected' : '' ?>>Erreurs</option>
                <option value="warn" <?= $severityFilter === 'warn' ? 'selected' : '' ?>>Alertes</option>
                <option value="bug" <?= $severityFilter === 'bug' ? 'selected' : '' ?>>Signalements joueurs</option>
                <option value="info" <?= $severityFilter === 'info' ? 'selected' : '' ?>>Infos</option>
            </select>
        </label>
        <button type="submit" class="bo-atak-beta__btn">Filtrer</button>
    </form>

    <?php if ($rows === []): ?>
        <p class="bo-atak-beta__empty">Aucun rapport pour le moment. Dès qu’un joueur rencontre une erreur Overwatch, elle apparaîtra ici.</p>
    <?php else: ?>
        <div class="bo-atak-beta__table-wrap">
            <table class="bo-atak-beta__table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Joueur</th>
                        <th>Module</th>
                        <th>Occ.</th>
                        <th>Vu</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $sev = strtolower(trim((string) ($row['severity'] ?? 'error')));
                    $msg = trim((string) ($row['message'] ?? ''));
                    $detail = trim((string) ($row['detail_text'] ?? ''));
                    $player = trim((string) ($row['player_name'] ?? ''));
                    $cs = trim((string) ($row['callsign'] ?? ''));
                    $who = $player !== '' ? $player : '—';
                    if ($cs !== '') {
                        $who .= ' · ' . $cs;
                    }
                    ?>
                    <tr>
                        <td><span class="bo-atak-beta__badge"><?= htmlspecialchars($severityLabel($sev), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                            <strong><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($detail !== ''): ?>
                                <details style="margin-top:.35rem;">
                                    <summary style="cursor:pointer;font-size:.85rem;opacity:.8;">Détail technique</summary>
                                    <pre style="white-space:pre-wrap;font-size:.75rem;max-width:36rem;margin:.4rem 0 0;opacity:.85;"><?= htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') ?></pre>
                                </details>
                            <?php endif; ?>
                            <div style="font-size:.75rem;opacity:.65;margin-top:.25rem;">
                                Pack <?= htmlspecialchars((string) ($row['mod_version'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                · Ext <?= htmlspecialchars((string) ($row['extension_version'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                · Steam <?= htmlspecialchars($maskSteam($row['steam_uid'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($who, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['channel'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) ($row['hit_count'] ?? 1) ?></td>
                        <td><?= htmlspecialchars($fmtDate($row['last_seen_at'] ?? $row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <form method="post" action="<?= htmlspecialchars(url('admin/atak-mod-reports/delete'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Retirer ce rapport du journal ?');">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="report_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                <button type="submit" class="bo-atak-beta__btn bo-atak-beta__btn--ghost">Retirer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
