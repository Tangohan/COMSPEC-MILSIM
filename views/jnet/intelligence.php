<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$intelFeed = is_array($intelFeed ?? null) ? $intelFeed : [];
$priorityTargets = is_array($priorityTargets ?? null) ? $priorityTargets : [];
$viewerLens = (string) ($viewerLens ?? 'operator');
$prioKey = static fn (array $t): string => strtolower((string) ($t['priority_key'] ?? 'low'));
?>
<section class="jnet-home-grid">
    <section class="jnet-panel" style="grid-column:1 / -1">
        <div class="jnet-panel__head">
            <h2>Journal de renseignement</h2>
            <span class="jnet-meta"><?= $h(match ($viewerLens) {
                'command' => 'Priorité : situation et alertes',
                'intel' => 'Priorité : dossiers et fiches terrain',
                default => 'Priorité : briefings diffusés',
            }) ?></span>
        </div>
        <div class="jnet-panel__body jnet-feed">
            <?php if ($intelFeed === []): ?>
                <div class="jnet-empty">
                    <p>Aucune entrée récente.</p>
                    <p>Les fiches terrain, dossiers suivis et opérations ouvertes alimentent ce journal.</p>
                    <p><a class="jnet-btn" href="<?= $h(url('atak/sse/fiches')) ?>">Ouvrir les fiches terrain</a></p>
                </div>
            <?php endif; ?>
            <?php foreach ($intelFeed as $ev): ?>
                <a class="jnet-feed__item" href="<?= $h((string) ($ev['href'] ?? '#')) ?>">
                    <?php if (trim((string) ($ev['time'] ?? '')) !== ''): ?>
                        <time><?= $h((string) $ev['time']) ?></time>
                    <?php endif; ?>
                    <div>
                        <strong><?= $h((string) ($ev['kind'] ?? '')) ?> · <?= $h((string) ($ev['title'] ?? '')) ?></strong>
                        <?php if (trim((string) ($ev['detail'] ?? '')) !== ''): ?>
                            <span><?= $h((string) $ev['detail']) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="jnet-panel" style="grid-column:1 / -1">
        <div class="jnet-panel__head">
            <h2>Dossiers en suivi</h2>
            <a class="jnet-btn" href="<?= $h(url('jnet/cibles')) ?>">Tous les dossiers</a>
        </div>
        <div class="jnet-panel__body">
            <?php if ($priorityTargets === []): ?>
                <div class="jnet-empty">
                    <p>Aucun dossier en suivi.</p>
                    <p><a class="jnet-btn" href="<?= $h(url('atak/sse/interet')) ?>">Ouvrir le bureau SSE</a></p>
                </div>
            <?php else: ?>
                <div class="jnet-target-rail">
                    <?php foreach ($priorityTargets as $t): ?>
                        <a class="jnet-target-card" href="<?= $h(url('jnet/cibles/' . rawurlencode((string) ($t['id'] ?? '')))) ?>">
                            <strong><?= $h((string) ($t['name'] ?? '')) ?></strong>
                            <?php if (trim((string) ($t['code'] ?? '')) !== ''): ?>
                                <span><?= $h((string) $t['code']) ?></span>
                            <?php endif; ?>
                            <em class="jnet-prio jnet-prio--<?= $h($prioKey($t)) ?>"><?= $h((string) ($t['priority'] ?? '')) ?></em>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</section>
