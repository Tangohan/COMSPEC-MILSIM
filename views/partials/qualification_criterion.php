<?php
declare(strict_types=1);

/**
 * Une condition d'obtention lisible : « 20 h — Heures de vol (UH-60 Black Hawk) sur les 90 derniers jours ».
 * Chaque morceau est un texte à part (traduit séparément).
 *
 * @var array<string, mixed> $criterion
 * @var array<string, mixed> $criterionNames
 */
$cp = \App\Support\QualificationCriteriaCatalog::parts(is_array($criterion ?? null) ? $criterion : [], is_array($criterionNames ?? null) ? $criterionNames : []);
$ce = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<span class="qfs-crit">
    <?php if ($cp['value'] !== ''): ?><b><?= $ce($cp['value']) ?></b> <span><?= $ce($cp['unit']) ?></span> <span>—</span><?php endif; ?>
    <span><?= $ce($cp['label']) ?></span>
    <?php if ($cp['scope'] !== ''): ?><em translate="no">(<?= $ce($cp['scope']) ?>)</em><?php endif; ?>
    <?php if ($cp['window'] > 0): ?><em>sur les <?= (int) $cp['window'] ?> derniers jours</em><?php endif; ?>
</span>
