<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $anomalies */
$anomalies = is_array($anomalies ?? null) ? $anomalies : [];
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
        <div class="page-heading-overline">Analyse // Anomalies</div>
        <h1>Anomalies</h1>
        <p>
            Incohérences chronologiques, biométrie partagée, contradictions documentaires
            et files saturées — à instruire avant toute consolidation.
        </p>
    </div>
</div>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">15.01</span> Signaux détectés</div>
        <div class="panel-meta"><?= count($anomalies) ?></div>
    </div>
    <?php if ($anomalies === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">OK</div>
                <strong>Aucune anomalie prioritaire</strong>
                <p>Le périmètre de session ne présente pas de signal analytique à traiter pour le moment.</p>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/croisements')) ?>">Voir les rapprochements</a>
            </div>
        </div>
    <?php else: ?>
        <div class="panel-body">
            <?php foreach ($anomalies as $a): ?>
                <div class="iw-alert is-<?= $h($a['level'] ?? 'moderee') ?>">
                    <strong><?= $h($levelLabel((string) ($a['level'] ?? 'moderee'))) ?> — <?= $h($a['title'] ?? '') ?></strong>
                    <p><?= $h($a['detail'] ?? '') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
