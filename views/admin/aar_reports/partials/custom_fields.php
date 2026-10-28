<?php
declare(strict_types=1);

/**
 * Saisie des réponses d’un questionnaire (modèle de debriefing).
 *
 * @var list<array<string, mixed>> $customFields
 * @var array<string, mixed> $customAnswers
 * @var string $customPrefix préfixe d'identifiants (plusieurs questionnaires sur la page)
 */
$customFields = is_array($customFields ?? null) ? $customFields : [];
$customAnswers = is_array($customAnswers ?? null) ? $customAnswers : [];
$customPrefix = (string) ($customPrefix ?? '');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$isOn = static function (mixed $raw): bool {
    if (is_bool($raw)) {
        return $raw;
    }
    if (is_array($raw)) {
        return $raw !== [];
    }

    return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'oui', 'on', 'yes'], true);
};
?>
<?php if ($customFields === []): ?>
  <p class="bo-aar-muted">Ce questionnaire n’a pas de question.</p>
<?php endif; ?>
<ol class="bo-aar-qs">
<?php foreach ($customFields as $i => $field):
    $fid = (string) ($field['id'] ?? '');
    $type = (string) ($field['type'] ?? 'text');
    $label = (string) ($field['label'] ?? 'Question');
    $help = (string) ($field['help'] ?? '');
    $req = !empty($field['required']);
    $opts = is_array($field['options'] ?? null) ? $field['options'] : [];
    $raw = $customAnswers[$fid] ?? null;
    $inputId = 'aar-c-' . $customPrefix . preg_replace('/[^a-z0-9_-]/i', '', $fid);
    $name = 'answers[' . $h($fid) . ']';
    ?>
  <li class="bo-aar-q">
    <div class="bo-aar-q__head">
      <span class="bo-aar-q__n"><?= $i + 1 ?></span>
      <label for="<?= $h($inputId) ?>" class="bo-aar-q__label"><?= $h($label) ?><?= $req ? ' <em>obligatoire</em>' : '' ?></label>
    </div>
    <?php if ($help !== ''): ?><p class="bo-aar-q__help"><?= $h($help) ?></p><?php endif; ?>
    <?php if ($type === 'textarea'): ?>
      <textarea id="<?= $h($inputId) ?>" name="<?= $name ?>" rows="4"<?= $req ? ' required' : '' ?>><?= $h(is_scalar($raw) ? $raw : '') ?></textarea>
    <?php elseif ($type === 'select'): ?>
      <select id="<?= $h($inputId) ?>" name="<?= $name ?>"<?= $req ? ' required' : '' ?>>
        <option value="">Choisir…</option>
        <?php foreach ($opts as $opt): ?><option value="<?= $h($opt) ?>"<?= is_scalar($raw) && (string) $raw === (string) $opt ? ' selected' : '' ?>><?= $h($opt) ?></option><?php endforeach; ?>
      </select>
    <?php elseif ($type === 'checkbox' && $opts === []): ?>
      <div class="bo-aar-pills" role="radiogroup" aria-label="<?= $h($label) ?>">
        <label><input type="radio" id="<?= $h($inputId) ?>" name="<?= $name ?>" value="1"<?= $isOn($raw) ? ' checked' : '' ?><?= $req ? ' required' : '' ?>> Oui</label>
        <label><input type="radio" name="<?= $name ?>" value="0"<?= $raw !== null && $raw !== '' && !$isOn($raw) ? ' checked' : '' ?><?= $req ? ' required' : '' ?>> Non</label>
      </div>
    <?php elseif ($type === 'checkbox'): ?>
      <div class="bo-aar-pills">
        <?php foreach ($opts as $j => $opt): $o = (string) $opt; ?>
          <label><input type="checkbox"<?= $j === 0 ? ' id="' . $h($inputId) . '"' : '' ?> name="<?= $name ?>[]" value="<?= $h($o) ?>"<?= is_array($raw) && in_array($o, array_map('strval', $raw), true) ? ' checked' : '' ?>> <?= $h($o) ?></label>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <input id="<?= $h($inputId) ?>" type="text" name="<?= $name ?>" value="<?= $h(is_scalar($raw) ? $raw : '') ?>"<?= $req ? ' required' : '' ?>>
    <?php endif; ?>
  </li>
<?php endforeach; ?>
</ol>
