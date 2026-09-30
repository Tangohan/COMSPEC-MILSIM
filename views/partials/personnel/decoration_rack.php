<?php

declare(strict_types=1);

use App\Support\DecorationCatalog;

/**
 * Rack de rubans interactif (CSS/SVG, pas de bitmap).
 *
 * @var list<array<string, mixed>> $dkItems
 * @var bool $dkShowDemoDevices
 * @var bool $dkShowDetail
 * @var string $dkCaption
 * @var bool $dkCompact
 */

$dkItems = is_array($dkItems ?? null) ? $dkItems : [];
$dkShowDemoDevices = !empty($dkShowDemoDevices);
$dkShowDetail = array_key_exists('dkShowDetail', get_defined_vars()) ? !empty($dkShowDetail) : true;
$dkCaption = isset($dkCaption) ? (string) $dkCaption : 'Survolez un ruban — cliquez pour l’état « sélectionné ».';

$demoDevices = [
    'rbn_service_distingue' => 'star',
    'rbn_service_multinational_nato' => 'numeral',
];

$rows = [];
$row = [];
foreach ($dkItems as $i => $item) {
    if (!is_array($item)) {
        continue;
    }
    $row[] = $item;
    if (count($row) === 3) {
        $rows[] = $row;
        $row = [];
    }
}
if ($row !== []) {
    $rows[] = $row;
}

$firstId = '';
if ($dkItems !== []) {
    $firstId = (string) ($dkItems[0]['id'] ?? '');
}

$glyphsJson = [
    'star' => DecorationCatalog::glyphSvg('star'),
    'cross' => DecorationCatalog::glyphSvg('cross'),
    'wreath' => DecorationCatalog::glyphSvg('wreath'),
    'circle' => DecorationCatalog::glyphSvg('circle'),
];
?>
<div class="dk-rack-panel" data-dk-rack>
    <?php if ($rows === []): ?>
        <p class="dk-rack-caption">Aucune décoration enregistrée sur ce dossier.</p>
    <?php else: ?>
        <div class="dk-rack" role="group" aria-label="Rack de rubans">
            <?php foreach ($rows as $rackRow): ?>
            <div class="dk-rack-row">
                <?php foreach ($rackRow as $item):
                    $id = (string) ($item['id'] ?? '');
                    $name = (string) ($item['name'] ?? '');
                    $family = (string) ($item['family'] ?? 'GENERIC');
                    $type = (string) ($item['type'] ?? 'ribbon');
                    $level = (string) ($item['level'] ?? '');
                    $pattern = (string) ($item['patternClass'] ?? 'dk-rb-svc2');
                    $drop = (string) ($item['dropClass'] ?? '');
                    $disc = (string) ($item['discClass'] ?? '');
                    $glyph = (string) ($item['glyph'] ?? '');
                    $device = $dkShowDemoDevices
                        ? ($demoDevices[$id] ?? null)
                        : ($item['device'] ?? null);
                    $isSelected = $id !== '' && $id === $firstId;
                    $label = $name !== '' ? $name : $id;
                    ?>
                <button
                    type="button"
                    class="dk-slot <?= htmlspecialchars($pattern, ENT_QUOTES, 'UTF-8') ?><?= $isSelected ? ' is-selected' : '' ?>"
                    aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                    aria-pressed="<?= $isSelected ? 'true' : 'false' ?>"
                    data-dk-id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                    data-dk-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                    data-dk-family="<?= htmlspecialchars($family, ENT_QUOTES, 'UTF-8') ?>"
                    data-dk-type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
                    data-dk-level="<?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8') ?>"
                    data-dk-pattern="<?= htmlspecialchars($pattern, ENT_QUOTES, 'UTF-8') ?>"
                    data-dk-drop="<?= htmlspecialchars($drop, ENT_QUOTES, 'UTF-8') ?>"
                    data-dk-disc="<?= htmlspecialchars($disc, ENT_QUOTES, 'UTF-8') ?>"
                    data-dk-glyph="<?= htmlspecialchars($glyph, ENT_QUOTES, 'UTF-8') ?>"
                >
                    <?php if (is_string($device) && $device !== ''): ?>
                    <span class="dk-device"><?= DecorationCatalog::deviceSvg($device) ?></span>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($dkCaption !== ''): ?>
        <div class="dk-rack-caption"><?= htmlspecialchars($dkCaption, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($dkShowDemoDevices): ?>
        <div class="dk-device-note"><?= htmlspecialchars(DecorationCatalog::DEVICE_NOTE, ENT_QUOTES, 'UTF-8') ?> <code>isOfficialReference: false</code></div>
        <?php endif; ?>
        <?php if ($dkShowDetail && $dkItems !== []):
            $sel = $dkItems[0];
            $selType = (string) ($sel['type'] ?? 'ribbon');
            $selDrop = (string) ($sel['dropClass'] ?? $sel['patternClass'] ?? '');
            $selDisc = (string) ($sel['discClass'] ?? 'dk-disc-svc');
            $selGlyph = (string) ($sel['glyph'] ?? '');
            $selName = (string) ($sel['name'] ?? '');
            $selFam = (string) ($sel['family'] ?? 'GENERIC');
            $selLevel = (string) ($sel['level'] ?? '');
            $selId = (string) ($sel['id'] ?? '');
            ?>
        <div class="dk-single-display" data-dk-detail style="margin-top:18px;">
            <div>
                <div class="dk-bel"></div>
                <div class="dk-m-neck"></div>
                <div class="dk-m-ribbon <?= htmlspecialchars($selDrop, ENT_QUOTES, 'UTF-8') ?>" data-dk-detail-drop></div>
                <div class="dk-m-disc <?= htmlspecialchars($selDisc, ENT_QUOTES, 'UTF-8') ?>" data-dk-detail-disc>
                    <?php if ($selType === 'medal' && $selGlyph !== ''): ?>
                        <?= DecorationCatalog::glyphSvg($selGlyph) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="dk-m-name" data-dk-detail-name style="font-size:15px;"><?= htmlspecialchars($selName, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="dk-m-fam" data-dk-detail-fam style="margin-top:4px;"><?= htmlspecialchars($selFam . ($selLevel !== '' ? ' · ' . $selLevel : '') . ($selId !== '' ? ' · ' . $selId : ''), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
window.DK_GLYPHS = window.DK_GLYPHS || <?= json_encode($glyphsJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
