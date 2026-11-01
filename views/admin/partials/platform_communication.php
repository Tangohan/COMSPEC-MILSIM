<?php
declare(strict_types=1);

/**
 * Alertes et maintenances en cours (repris de l'ancienne synthèse opérationnelle).
 *
 * @var list<array<string, mixed>> $adminActiveAlerts
 * @var array{active: int, planned: int} $adminMaintenanceCounts
 */
$pcH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$pcAlerts = is_array($adminActiveAlerts ?? null) ? $adminActiveAlerts : [];
$pcMaint = is_array($adminMaintenanceCounts ?? null) ? $adminMaintenanceCounts : ['active' => 0, 'planned' => 0];
?>
<section id="hub-communication" class="pa-card scroll-mt-24" aria-labelledby="pa-comm-title">
    <div class="pa-card__head">
        <div>
            <h2 id="pa-comm-title" class="pa-card__title">Alertes et maintenances</h2>
            <p class="pa-card__help">Ce que les membres voient en ce moment sur le portail.</p>
        </div>
    </div>
    <ul class="pa-rows">
        <li>
            <a href="<?= $pcH(url('admin/system/alerts')) ?>">Alertes affichées sur le portail</a>
            <b><?= count($pcAlerts) ?></b>
        </li>
        <?php foreach (array_slice($pcAlerts, 0, 3) as $alert): ?>
        <li><span><?= $pcH((string) ($alert['title'] ?? $alert['message'] ?? 'Alerte')) ?></span></li>
        <?php endforeach; ?>
        <li>
            <a href="<?= $pcH(url('admin/maintenance')) ?>">Maintenances en cours</a>
            <b><?= (int) $pcMaint['active'] ?></b>
        </li>
        <li>
            <a href="<?= $pcH(url('admin/maintenance')) ?>">Maintenances planifiées</a>
            <b><?= (int) $pcMaint['planned'] ?></b>
        </li>
    </ul>
</section>
