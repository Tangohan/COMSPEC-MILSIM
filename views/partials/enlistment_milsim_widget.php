<?php
declare(strict_types=1);
/**
 * Rendu d’un champ MilSim selon widget (text, textarea, select, yesno).
 *
 * @var string $fieldName
 * @var callable $fld
 * @var array<string, string> $prefill
 * @var bool $fieldRequired
 * @var array{label?: string, placeholder?: string, help?: string, widget?: string, options?: list<string>}|null $fieldOverride
 */
$fieldName = (string) ($fieldName ?? '');
$prefill = is_array($prefill ?? null) ? $prefill : [];
$m = $fld($fieldName);
if (is_array($fieldOverride ?? null)) {
    foreach (['label', 'placeholder', 'help', 'widget', 'options'] as $ok) {
        if (array_key_exists($ok, $fieldOverride)) {
            $m[$ok] = $fieldOverride[$ok];
        }
    }
}
$widget = (string) ($m['widget'] ?? 'text');
$opts = is_array($m['options'] ?? null) ? $m['options'] : [];
if ($widget === 'yesno' && $opts === []) {
    $opts = ['Oui', 'Non'];
}
$label = (string) ($m['label'] ?? $fieldName);
$ph = (string) ($m['placeholder'] ?? '');
$help = trim((string) ($m['help'] ?? ''));
$pv = (string) ($prefill[$fieldName] ?? '');
$isRequired = !empty($fieldRequired);
?>
<div class="ce-field">
    <label class="ce-label"><?= htmlspecialchars($label) ?><?php if ($isRequired): ?> <span class="ce-label-hint">(obligatoire)</span><?php endif; ?></label>
    <?php if ($help !== ''): ?>
        <p class="ce-label-hint"><?= htmlspecialchars($help) ?></p>
    <?php endif; ?>
    <?php
    $taH = $fieldName === 'past_milsim_experience' ? 'h-32' : 'h-24';
    $reqAttr = $isRequired ? ' required' : '';
    $tenantReqAttr = $isRequired ? ' data-tenant-required="1"' : ' data-tenant-required="0"';
    ?>
    <?php if ($widget === 'textarea'): ?>
        <textarea name="<?= htmlspecialchars($fieldName) ?>" class="input-field <?= $taH ?> track-field" placeholder="<?= htmlspecialchars($ph) ?>" style="min-height:<?= $fieldName === 'past_milsim_experience' ? '8rem' : '6rem' ?>"<?= $reqAttr . $tenantReqAttr ?>><?= htmlspecialchars($pv) ?></textarea>
    <?php elseif ($widget === 'select' || $widget === 'yesno'): ?>
        <select name="<?= htmlspecialchars($fieldName) ?>" class="input-field track-field"<?= $reqAttr . $tenantReqAttr ?>>
            <option value="">Sélectionner</option>
            <?php foreach ($opts as $opt): ?>
                <?php if ($opt === '') { continue; } ?>
                <option value="<?= htmlspecialchars($opt) ?>"<?= $pv === $opt ? ' selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($fieldName === 'age'): ?>
        <input type="number" name="<?= htmlspecialchars($fieldName) ?>" class="input-field track-field" placeholder="<?= htmlspecialchars($ph) ?>" min="16" max="99" value="<?= htmlspecialchars($pv) ?>"<?= $reqAttr . $tenantReqAttr ?>>
    <?php else: ?>
        <input type="text" name="<?= htmlspecialchars($fieldName) ?>" class="input-field track-field" placeholder="<?= htmlspecialchars($ph) ?>" value="<?= htmlspecialchars($pv) ?>"<?= $reqAttr . $tenantReqAttr ?>>
    <?php endif; ?>
</div>
<?php
$fieldRequired = false;
$fieldOverride = null;
?>
