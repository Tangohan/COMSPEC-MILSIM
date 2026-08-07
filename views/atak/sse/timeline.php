<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $events */
$events = is_array($events ?? null) ? $events : [];
?>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Analyse // Chronologie</div>
        <h1>Chronologie unifiée</h1>
        <p>
            Contrôles, observations, corrélations et décisions — consolidés pour le périmètre
            de session. Les éléments restent des faits à corroborer.
        </p>
    </div>
</div>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">12.01</span> Événements consolidés</div>
        <div class="panel-meta"><?= count($events) ?></div>
    </div>
    <?php if ($events === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">TL</div>
                <strong>Chronologie vide</strong>
                <p>Les mises à jour d’identités, dossiers d’intérêt et investigations alimenteront cette ligne de temps.</p>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/operations')) ?>">Retour à la vue opérationnelle</a>
            </div>
        </div>
    <?php else: ?>
        <div class="panel-body">
            <ul class="iw-feed">
                <?php foreach ($events as $ev): ?>
                    <li>
                        <time><?= $h($ev['at'] ?? '') ?></time>
                        <span>
                            <strong><?= $h($ev['title'] ?? '') ?></strong>
                            — <?= $h($ev['detail'] ?? '') ?>
                            <?php if (!empty($ev['source'])): ?>
                                <span class="muted"> · <?= $h($ev['source']) ?></span>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
