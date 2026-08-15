<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$intelFeed = is_array($intelFeed ?? null) ? $intelFeed : [];
$priorityTargets = is_array($priorityTargets ?? null) ? $priorityTargets : [];
$viewerLens = (string) ($viewerLens ?? 'operator');
?>
<section class="jnet-home-grid">
    <section class="jnet-panel" style="grid-column:1 / -1">
        <div class="jnet-panel__head">
            <h2>Tableau de renseignement courant</h2>
            <span class="jnet-meta"><?= $h(match ($viewerLens) {
                'command' => 'Priorité : readiness & alertes',
                'intel' => 'Priorité : HVT, PIR, corrélations',
                default => 'Priorité : briefings diffusés',
            }) ?></span>
        </div>
        <div class="jnet-panel__body jnet-feed">
            <?php foreach ($intelFeed as $ev): ?>
                <a class="jnet-feed__item" href="<?= $h((string) ($ev['href'] ?? '#')) ?>">
                    <time><?= $h((string) ($ev['time'] ?? '')) ?></time>
                    <div>
                        <strong><?= $h((string) ($ev['kind'] ?? '')) ?> · <?= $h((string) ($ev['title'] ?? '')) ?></strong>
                        <span><?= $h((string) ($ev['detail'] ?? '')) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="jnet-panel" style="grid-column:1 / -1">
        <div class="jnet-panel__head">
            <h2>Cibles en suivi</h2>
            <a class="jnet-btn" href="<?= $h(url('jnet/cibles')) ?>">Ouvrir les cibles</a>
        </div>
        <div class="jnet-panel__body jnet-target-rail">
            <?php foreach ($priorityTargets as $t): ?>
                <a class="jnet-target-card" href="<?= $h(url('jnet/cibles/' . rawurlencode((string) ($t['id'] ?? '')))) ?>">
                    <strong><?= $h((string) ($t['name'] ?? '')) ?></strong>
                    <span><?= $h((string) ($t['code'] ?? '')) ?></span>
                    <em class="jnet-prio jnet-prio--<?= strtolower((string) ($t['priority'] ?? 'low')) ?>"><?= $h((string) ($t['priority'] ?? '')) ?></em>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</section>
