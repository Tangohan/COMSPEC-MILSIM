<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $tower */
$queue = is_array($tower['operator_queue'] ?? null) ? $tower['operator_queue'] : [];
?>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Exploitation // Validation</div>
        <h1>Files de validation</h1>
        <p>
            Rapprochements, consolidations, contradictions et dossiers d’intérêt —
            aucune proposition automatique ne devient un fait sans décision humaine.
        </p>
    </div>
</div>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">18.01</span> File opérateur</div>
        <div class="panel-meta"><?= count($queue) ?> files</div>
    </div>
    <?php if ($queue === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">HITL</div>
                <strong>Rien à valider</strong>
                <p>La file est vide. Les propositions de rapprochement et dossiers d’intérêt apparaîtront ici.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="panel-body iw-queue">
            <?php foreach ($queue as $q): ?>
                <a href="<?= $h($q['href'] ?? '#') ?>">
                    <span><?= $h($q['label'] ?? '') ?></span>
                    <b><?= (int) ($q['count'] ?? 0) ?></b>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<div class="security-notice">
    <div class="security-notice-code">HITL</div>
    <div>
        <strong>Validation humaine obligatoire</strong>
        <span>Une proposition de rapprochement ou de consolidation reste une hypothèse jusqu’à décision d’un opérateur habilité.</span>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
