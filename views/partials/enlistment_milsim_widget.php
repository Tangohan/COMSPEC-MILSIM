<?php
declare(strict_types=1);
/**
 * Rendu d’un champ MilSim selon widget (text, textarea, select, yesno).
 *
 * @var string $fieldName
 * @var callable $fld
 * @var array<string, string> $prefill
 */
$fieldName = (string) ($fieldName ?? '');
$prefill = is_array($prefill ?? null) ? $prefill : [];
$m = $fld($fieldName);
$widget = (string) ($m['widget'] ?? 'text');
$opts = is_array($m['options'] ?? null) ? $m['options'] : [];
if ($widget === 'yesno' && $opts === []) {
    $opts = ['Oui', 'Non'];
}
$label = (string) ($m['label'] ?? $fieldName);
$ph = (string) ($m['placeholder'] ?? '');
$pv = (string) ($prefill[$fieldName] ?? '');
?>
<div class="space-y-2">
    <label class="ce-label"><?= htmlspecialchars($label) ?></label>
    <?php
    $taH = $fieldName === 'past_milsim_experience' ? 'h-32' : 'h-24';
    ?>
    <?php if ($widget === 'textarea'): ?>
        <textarea name="<?= htmlspecialchars($fieldName) ?>" class="input-field <?= $taH ?> track-field" placeholder="<?= htmlspecialchars($ph) ?>" style="min-height:<?= $fieldName === 'past_milsim_experience' ? '8rem' : '6rem' ?>"><?= htmlspecialchars($pv) ?></textarea>
    <?php elseif ($widget === 'select' || $widget === 'yesno'): ?>
        <select name="<?= htmlspecialchars($fieldName) ?>" class="input-field track-field">
            <option value="">Sélectionner</option>
            <?php foreach ($opts as $opt): ?>
                <?php if ($opt === '') { continue; } ?>
                <option value="<?= htmlspecialchars($opt) ?>"<?= $pv === $opt ? ' selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($fieldName === 'age'): ?>
        <input type="number" name="<?= htmlspecialchars($fieldName) ?>" class="input-field track-field" placeholder="<?= htmlspecialchars($ph) ?>" min="16" max="99" value="<?= htmlspecialchars($pv) ?>">
    <?php else: ?>
        <input type="text" name="<?= htmlspecialchars($fieldName) ?>" class="input-field track-field" placeholder="<?= htmlspecialchars($ph) ?>" value="<?= htmlspecialchars($pv) ?>">
    <?php endif; ?>
</div>
