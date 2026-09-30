<?php

declare(strict_types=1);

use App\Support\DecorationCatalog;

/**
 * Médaille générique (disque unique, deux tailles CSS — 34px carte / 62px fiche).
 *
 * @var array<string, mixed> $dkMedal
 * @var bool $dkShowSmall
 */

$dkMedal = is_array($dkMedal ?? null) ? $dkMedal : [];
$dkShowSmall = array_key_exists('dkShowSmall', get_defined_vars()) ? !empty($dkShowSmall) : true;
$name = (string) ($dkMedal['name'] ?? '');
$family = (string) ($dkMedal['family'] ?? 'GENERIC');
$level = (string) ($dkMedal['level'] ?? '');
$drop = (string) ($dkMedal['dropClass'] ?? 'dk-drop-svc');
$disc = (string) ($dkMedal['discClass'] ?? 'dk-disc-svc');
$glyph = (string) ($dkMedal['glyph'] ?? 'circle');
$famLine = $family . ($level !== '' ? ' · ' . $level : '');
?>
<div class="dk-medal-card">
    <div class="dk-bel"></div>
    <div class="dk-m-neck"></div>
    <div class="dk-m-ribbon <?= htmlspecialchars($drop, ENT_QUOTES, 'UTF-8') ?>"></div>
    <div class="dk-m-disc <?= htmlspecialchars($disc, ENT_QUOTES, 'UTF-8') ?>">
        <?= DecorationCatalog::glyphSvg($glyph) ?>
    </div>
    <div class="dk-m-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="dk-m-fam"><?= htmlspecialchars($famLine, ENT_QUOTES, 'UTF-8') ?></div>
    <?php if ($dkShowSmall): ?>
    <div class="dk-size-row">
        <div class="dk-m-disc dk-m-disc--small <?= htmlspecialchars($disc, ENT_QUOTES, 'UTF-8') ?>">
            <?= DecorationCatalog::glyphSvg($glyph, true) ?>
        </div>
        <span class="dk-size-label">34px carte</span>
    </div>
    <?php endif; ?>
</div>
