<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $tower */
$kpi = is_array($tower['kpi'] ?? null) ? $tower['kpi'] : [];
$activity = is_array($tower['activity'] ?? null) ? $tower['activity'] : [];
$alerts = is_array($tower['alerts'] ?? null) ? $tower['alerts'] : [];
$queue = is_array($tower['operator_queue'] ?? null) ? $tower['operator_queue'] : [];
$recent = is_array($tower['recent_objects'] ?? null) ? $tower['recent_objects'] : [];
$quality = is_array($tower['data_quality'] ?? null) ? $tower['data_quality'] : [];

$levelLabel = static function (string $level): string {
    return match (strtolower(trim($level))) {
        'critique' => 'Critique',
        'elevee', 'élevée' => 'Élevée',
        'faible' => 'Faible',
        default => 'Modérée',
    };
};
?>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Pilotage // Situation</div>
        <h1>Vue opérationnelle</h1>
        <p>
            Synthèse du centre SSE : dossiers, objets, signaux analytiques et file opérateur.
            Les compteurs reflètent le périmètre de votre session.
        </p>
    </div>
    <div class="page-reference">
        <strong>ATH-SSE-TOWER</strong>
        Fraîcheur : <?= $h((string) ($quality['freshness'] ?? '—')) ?>
    </div>
</div>

<div class="iw-tower-kpis">
    <div class="iw-kpi"><span>Dossiers actifs</span><strong><?= (int) ($kpi['active_cases'] ?? 0) ?></strong><em>Exploitation en cours</em></div>
    <div class="iw-kpi"><span>Dossiers d’intérêt</span><strong><?= (int) ($kpi['pressee_pending'] ?? 0) ?></strong><em>À instruire</em></div>
    <div class="iw-kpi"><span>Identités</span><strong><?= (int) ($kpi['people'] ?? 0) ?></strong><em>Registre visible</em></div>
    <div class="iw-kpi"><span>Rapprochements</span><strong><?= (int) ($kpi['cross_pending'] ?? 0) ?></strong><em>À confirmer</em></div>
    <div class="iw-kpi"><span>Sans activité</span><strong><?= (int) ($kpi['stale_cases'] ?? 0) ?></strong><em>Plus de 3 jours</em></div>
    <div class="iw-kpi"><span>Sources</span><strong><?= $h((string) ($quality['sources_ok'] ?? 0)) ?>/<?= $h((string) ($quality['sources_total'] ?? 0)) ?></strong><em><?= $h((string) ($quality['sync_label'] ?? 'Synchronisé')) ?></em></div>
</div>

<div class="iw-tower-grid">
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">00.01</span> Activité récente</div>
        </div>
        <div class="panel-body">
            <?php if ($activity === []): ?>
                <div class="empty-state" style="min-height:180px;padding:1.5rem">
                    <div class="empty-state-inner">
                        <strong>Aucune activité récente</strong>
                        <p>Les acquisitions terrain et mises à jour de dossiers apparaîtront ici.</p>
                    </div>
                </div>
            <?php else: ?>
                <ul class="iw-feed">
                    <?php foreach ($activity as $row): ?>
                        <li>
                            <time><?= $h($row['at'] ?? '') ?></time>
                            <span><?= $h($row['text'] ?? '') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">00.02</span> Signaux analytiques</div>
        </div>
        <div class="panel-body">
            <?php if ($alerts === []): ?>
                <p class="muted">Aucun signal prioritaire sur le périmètre.</p>
            <?php else: ?>
                <?php foreach ($alerts as $a): ?>
                    <div class="iw-alert is-<?= $h($a['level'] ?? 'moderee') ?>">
                        <strong><?= $h($levelLabel((string) ($a['level'] ?? 'moderee'))) ?> — <?= $h($a['title'] ?? '') ?></strong>
                        <p><?= $h($a['detail'] ?? '') ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">00.03</span> File opérateur</div>
        </div>
        <div class="panel-body iw-queue">
            <?php if ($queue === []): ?>
                <p class="muted">File vide.</p>
            <?php else: ?>
                <?php foreach ($queue as $q): ?>
                    <a href="<?= $h($q['href'] ?? '#') ?>">
                        <span><?= $h($q['label'] ?? '') ?></span>
                        <b><?= (int) ($q['count'] ?? 0) ?></b>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="panel" style="margin-top:14px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">00.04</span> Objets récemment détectés</div>
        <div class="panel-meta"><?= count($recent) ?> entrées</div>
    </div>
    <?php if ($recent === []): ?>
        <div class="panel-body"><p class="muted">Aucun objet récent sur le périmètre de session.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Type</th><th>Référence</th><th>Libellé</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $o): ?>
                    <tr>
                        <td><?= $h($o['type'] ?? '') ?></td>
                        <td class="record-id"><?= $h($o['ref'] ?? '') ?></td>
                        <td><?= $h($o['label'] ?? '') ?></td>
                        <td><a class="btn-open" href="<?= $h($o['href'] ?? '#') ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
