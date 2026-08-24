<?php
declare(strict_types=1);

/**
 * Saisie des réponses d’un modèle de debriefing (édition d’un compte rendu existant).
 *
 * @var list<array<string, mixed>> $customFields
 * @var array<string, mixed> $customAnswers
 */

$customFields = is_array($customFields ?? null) ? $customFields : [];
$customAnswers = is_array($customAnswers ?? null) ? $customAnswers : [];
$h = $h ?? static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$isOn = static function (mixed $raw): bool {
    if (is_bool($raw)) {
        return $raw;
    }
    if (is_array($raw)) {
        return $raw !== [];
    }
    $v = strtolower(trim((string) $raw));

    return in_array($v, ['1', 'true', 'oui', 'on', 'yes'], true);
};
?>
<div class="ath-aar-custom-block">
    <p class="ath-aar-custom-block__title">Questions du debriefing</p>
    <?php foreach ($customFields as $field): ?>
        <?php
        $fid = (string) ($field['id'] ?? '');
        $ftype = (string) ($field['type'] ?? 'text');
        $flabel = (string) ($field['label'] ?? 'Question');
        $fhelp = (string) ($field['help'] ?? '');
        $freq = !empty($field['required']);
        $fopts = is_array($field['options'] ?? null) ? $field['options'] : [];
        $raw = $customAnswers[$fid] ?? null;
        $inputId = 'aar-c-' . preg_replace('/[^a-z0-9_-]/i', '', $fid);
        ?>
        <div class="ath-aar-custom-q">
            <label for="<?= $h($inputId) ?>">
                <?= $h($flabel) ?>
                <?php if ($freq): ?><span class="ath-aar-req">obligatoire</span><?php endif; ?>
            </label>
            <?php if ($fhelp !== ''): ?>
            <p class="ath-aar-custom-q__help"><?= $h($fhelp) ?></p>
            <?php endif; ?>

            <?php if ($ftype === 'textarea'): ?>
            <textarea id="<?= $h($inputId) ?>" name="answers[<?= $h($fid) ?>]" rows="4" <?= $freq ? 'required' : '' ?>><?= $h(is_scalar($raw) ? (string) $raw : '') ?></textarea>
            <?php elseif ($ftype === 'select'): ?>
            <select id="<?= $h($inputId) ?>" name="answers[<?= $h($fid) ?>]" <?= $freq ? 'required' : '' ?>>
                <option value="">Choisir</option>
                <?php foreach ($fopts as $opt): ?>
                <option value="<?= $h((string) $opt) ?>" <?= (is_scalar($raw) && (string) $raw === (string) $opt) ? 'selected' : '' ?>><?= $h((string) $opt) ?></option>
                <?php endforeach; ?>
            </select>
            <?php elseif ($ftype === 'checkbox' && $fopts === []): ?>
            <div class="ath-aar-yesno" role="radiogroup" aria-label="<?= $h($flabel) ?>">
                <label class="ath-aar-yesno__opt">
                    <input type="radio" name="answers[<?= $h($fid) ?>]" value="1" <?= $isOn($raw) ? 'checked' : '' ?> <?= $freq ? 'required' : '' ?>>
                    Oui
                </label>
                <label class="ath-aar-yesno__opt">
                    <input type="radio" name="answers[<?= $h($fid) ?>]" value="0" <?= ($raw !== null && $raw !== '' && !$isOn($raw)) ? 'checked' : '' ?> <?= $freq ? 'required' : '' ?>>
                    Non
                </label>
            </div>
            <?php elseif ($ftype === 'checkbox'): ?>
            <div class="ath-aar-checks">
                <?php foreach ($fopts as $opt): ?>
                    <?php
                    $optS = (string) $opt;
                    $checked = is_array($raw) && in_array($optS, array_map('strval', $raw), true);
                    ?>
                <label class="ath-aar-checks__opt">
                    <input type="checkbox" name="answers[<?= $h($fid) ?>][]" value="<?= $h($optS) ?>" <?= $checked ? 'checked' : '' ?>>
                    <?= $h($optS) ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <input id="<?= $h($inputId) ?>" type="text" name="answers[<?= $h($fid) ?>]" value="<?= $h(is_scalar($raw) ? (string) $raw : '') ?>" <?= $freq ? 'required' : '' ?>>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
