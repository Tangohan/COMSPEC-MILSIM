<?php
/** @var list<array<string, mixed>> $rows */
/** @var int $total */
/** @var int $totalAll */
/** @var string $severityFilter */
/** @var string $statusFilter */
/** @var array{new?:int,in_progress?:int,fixed?:int} $statusCounts */
$rows = is_array($rows ?? null) ? $rows : [];
$total = (int) ($total ?? count($rows));
$totalAll = (int) ($totalAll ?? $total);
$severityFilter = trim((string) ($severityFilter ?? ''));
$statusFilter = trim((string) ($statusFilter ?? ''));
$statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];

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
$statusChoices = \App\Repositories\AtakModReportRepository::STATUS_LABELS;
?>
<div class="bo-atak-beta">
    <header class="bo-atak-beta__hero">
        <p class="bo-atak-beta__eyebrow">Tactique · Mod Arma</p>
        <h1>Rapports Overwatch</h1>
        <p class="bo-atak-beta__lead">
            Erreurs automatiques et signalements joueurs remontés depuis le jeu vers Athena.
            Suivez le traitement (Nouveau → En cours → Corrigé). Les doublons proches sont regroupés.
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

    <div class="bo-atak-beta__kpis bo-atak-beta__kpis--five" aria-label="Résumé">
        <div class="bo-atak-beta__kpi">
            <span class="bo-atak-beta__kpi-label">Au total</span>
            <span class="bo-atak-beta__kpi-value"><?= (int) $totalAll ?></span>
        </div>
        <div class="bo-atak-beta__kpi">
            <span class="bo-atak-beta__kpi-label">Nouveaux</span>
            <span class="bo-atak-beta__kpi-value"><?= (int) ($statusCounts['new'] ?? 0) ?></span>
        </div>
        <div class="bo-atak-beta__kpi">
            <span class="bo-atak-beta__kpi-label">En cours</span>
            <span class="bo-atak-beta__kpi-value"><?= (int) ($statusCounts['in_progress'] ?? 0) ?></span>
        </div>
        <div class="bo-atak-beta__kpi">
            <span class="bo-atak-beta__kpi-label">Corrigés</span>
            <span class="bo-atak-beta__kpi-value"><?= (int) ($statusCounts['fixed'] ?? 0) ?></span>
        </div>
        <div class="bo-atak-beta__kpi">
            <span class="bo-atak-beta__kpi-label">Affichés</span>
            <span class="bo-atak-beta__kpi-value"><?= (int) $total ?></span>
        </div>
    </div>

    <section class="bo-atak-beta__panel" aria-labelledby="atak-mod-reports-heading">
        <div class="bo-atak-beta__panel-head">
            <div>
                <h2 id="atak-mod-reports-heading">Journal des rapports</h2>
                <p>Filtrez par type ou par suivi, puis faites avancer chaque rapport.</p>
            </div>
        </div>

    <form method="get" action="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>" class="bo-atak-beta__filters">
        <label>
            <span class="bo-atak-beta__filter-label">Type</span>
            <select name="severity">
                <option value="" <?= $severityFilter === '' ? 'selected' : '' ?>>Tous</option>
                <option value="error" <?= $severityFilter === 'error' ? 'selected' : '' ?>>Erreurs</option>
                <option value="warn" <?= $severityFilter === 'warn' ? 'selected' : '' ?>>Alertes</option>
                <option value="bug" <?= $severityFilter === 'bug' ? 'selected' : '' ?>>Signalements joueurs</option>
                <option value="info" <?= $severityFilter === 'info' ? 'selected' : '' ?>>Infos</option>
            </select>
        </label>
        <label>
            <span class="bo-atak-beta__filter-label">Suivi</span>
            <select name="status">
                <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Tous les statuts</option>
                <?php foreach ($statusChoices as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
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
                        <th>Suivi</th>
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
                    $wfRaw = strtolower(trim((string) ($row['workflow_status'] ?? 'new')));
                    if (!isset($statusChoices[$wfRaw])) {
                        $wfRaw = 'new';
                    }
                    $msg = trim((string) ($row['message'] ?? ''));
                    $detail = trim((string) ($row['detail_text'] ?? ''));
                    $contextRaw = trim((string) ($row['context_json'] ?? ''));
                    $sessionLog = '';
                    if ($contextRaw !== '') {
                        $ctx = json_decode($contextRaw, true);
                        if (is_array($ctx) && !empty($ctx['session_log']) && is_string($ctx['session_log'])) {
                            $sessionLog = trim($ctx['session_log']);
                        }
                    }
                    $player = trim((string) ($row['player_name'] ?? ''));
                    $cs = trim((string) ($row['callsign'] ?? ''));
                    $who = $player !== '' ? $player : '—';
                    if ($cs !== '') {
                        $who .= ' · ' . $cs;
                    }
                    $packVer = trim((string) ($row['mod_version'] ?? ''));
                    $extVer = trim((string) ($row['extension_version'] ?? ''));
                    ?>
                    <tr>
                        <td><span class="bo-atak-beta__badge<?= $sev === 'error' ? ' bo-atak-beta__badge--danger' : ($sev === 'warn' ? ' bo-atak-beta__badge--warn' : '') ?>"><?= htmlspecialchars($severityLabel($sev), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                            <form method="post" action="<?= htmlspecialchars(url('admin/atak-mod-reports/status'), ENT_QUOTES, 'UTF-8') ?>" class="bo-atak-beta__status-form">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="report_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                <input type="hidden" name="return_severity" value="<?= htmlspecialchars($severityFilter, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="return_status" value="<?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?>">
                                <select name="workflow_status" aria-label="Statut de suivi" onchange="this.form.submit()">
                                    <?php foreach ($statusChoices as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $wfRaw === $value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($detail !== ''): ?>
                                <details class="bo-atak-beta__details">
                                    <summary>Détail technique</summary>
                                    <pre><?= htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') ?></pre>
                                </details>
                            <?php endif; ?>
                            <?php if ($sessionLog !== ''): ?>
                                <details class="bo-atak-beta__details">
                                    <summary>Journal de session</summary>
                                    <pre class="bo-atak-beta__log"><?= htmlspecialchars($sessionLog, ENT_QUOTES, 'UTF-8') ?></pre>
                                </details>
                            <?php endif; ?>
                            <div class="bo-atak-beta__meta-sub">
                                Version du pack <?= htmlspecialchars($packVer !== '' ? $packVer : '—', ENT_QUOTES, 'UTF-8') ?>
                                · Extension <?= htmlspecialchars($extVer !== '' ? $extVer : '—', ENT_QUOTES, 'UTF-8') ?>
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
    </section>
</div>
