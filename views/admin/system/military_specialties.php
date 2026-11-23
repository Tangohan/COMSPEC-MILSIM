<?php
declare(strict_types=1);
use App\Support\MilitarySpecialtyLabels as Labels;
$h = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="max-w-6xl mx-auto px-6 py-12">
    <header class="pa-head space-y-2">
        <h1 class="text-2xl font-black text-slate-900">Nomenclatures militaires</h1>
        <p class="text-sm text-slate-600">Références communes aux communautés : pays, armée, système de codes et niveaux documentés. Un code métier est distinct d’un grade, d’une qualification et d’une affectation.</p>
    </header>
    <p><a href="<?= $h(url('admin/system/military-referential')) ?>">Revenir au référentiel des unités</a></p>
    <?php if (empty($specialtyReady)): ?><p>Les nomenclatures seront disponibles après le passage de la migration 4910 dans la console des migrations.</p><?php endif; ?>
    <?php foreach (($specialtyCatalog ?? []) as $code): ?>
        <details class="rounded-xl border border-slate-200 bg-white shadow-sm px-4 py-3 mb-4">
            <summary><strong><?= $h(Labels::title($code)) ?></strong> · <?= $h($code['country_name_fr'] . ' · ' . $code['service_name']) ?></summary>
            <p><?= $h($code['system_name'] . ' · ' . Labels::kind((string) $code['kind'])) ?></p>
            <p><?= $h($code['official_title']) ?></p>
            <p><?= nl2br($h($code['description'] ?? '')) ?></p>
            <p><?= $h(Labels::verification((string) $code['verification_status'])) ?><?php if (!empty($code['verified_at'])): ?> · Contrôle le <?= $h($code['verified_at']) ?><?php endif; ?></p>
            <?php $sourceUrl = Labels::sourceUrl($code['source_url'] ?? ''); if ($sourceUrl !== ''): ?><p><a href="<?= $h($sourceUrl) ?>" target="_blank" rel="noopener noreferrer"><?= $h($code['source_name'] ?? 'Publication officielle') ?></a> · <?= $h($code['source_locator'] ?? '') ?></p><?php endif; ?>
            <?php if (($code['levels'] ?? []) !== []): ?><h2>Niveaux et limites de vérification</h2><ul><?php foreach ($code['levels'] as $level): ?><li><?= $h($level['level_code'] . ' · ' . $level['official_title'] . ' — ' . $level['label_fr'] . ' · ' . Labels::verification((string) $level['verification_status'])) ?></li><?php endforeach; ?></ul><?php endif; ?>
        </details>
    <?php endforeach; ?>
</div>
