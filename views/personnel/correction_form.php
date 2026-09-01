<?php
/** @var array<string,mixed> $targetUser */
/** @var array<string,mixed> $snapshot */
/** @var array<string,string> $fieldLabels */
/** @var array<string,array<string,mixed>> $fieldCatalog */
/** @var array<string,string> $fieldGroups */
/** @var array<string,list<array{value: string, label: string}>> $choiceCatalog */
/** @var list<array<string,mixed>> $pending */
/** @var bool $hasOpen */
/** @var bool $isSelf */
/** @var string $csrf */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$targetId = (int) ($targetUser['id'] ?? 0);
$displayName = trim((string) ($targetUser['display_name'] ?? '')) ?: 'Opérateur';
$snapshot = is_array($snapshot ?? null) ? $snapshot : [];
$fieldCatalog = is_array($fieldCatalog ?? null) ? $fieldCatalog : [];
$fieldGroups = is_array($fieldGroups ?? null) ? $fieldGroups : [];
$choiceCatalog = is_array($choiceCatalog ?? null) ? $choiceCatalog : [];
$fieldLabels = is_array($fieldLabels ?? null) ? $fieldLabels : [];
$disabled = !empty($hasOpen);

$statusFr = static function (string $status): string {
    return match ($status) {
        'pending' => 'En attente',
        'approved' => 'Confirmée',
        'rejected' => 'Refusée',
        'cancelled' => 'Annulée',
        default => $status,
    };
};

$choiceOptions = static function (string $choiceKey, string $current) use ($choiceCatalog): array {
    $options = $choiceCatalog[$choiceKey] ?? [];
    $seen = [];
    foreach ($options as $opt) {
        $seen[(string) ($opt['value'] ?? '')] = true;
    }
    if ($current !== '' && !isset($seen[$current])) {
        $options[] = ['value' => $current, 'label' => $current];
    }

    return $options;
};

$fieldsByGroup = [];
foreach ($fieldCatalog as $key => $meta) {
    $group = (string) ($meta['group'] ?? 'identity');
    $fieldsByGroup[$group][] = $key;
}
?>
<div class="pd-page rh-corr-form">
  <div class="pd-container pd-container--narrow">
    <header class="pd-header">
      <div>
        <p class="pd-header__eyebrow">Anomalie fiche</p>
        <h1 class="pd-header__title">Correction RH</h1>
        <p class="pd-header__sub">
          Proposez des corrections sur <?= $isSelf ? 'votre fiche' : 'la fiche de <strong>' . $h($displayName) . '</strong>' ?>.
          Chaque modification part en validation auprès d’un organisateur : un e-mail récapitulatif est envoyé aux deux parties, et la fiche n’est mise à jour qu’après confirmation.
        </p>
      </div>
      <div class="pd-header__actions">
        <a href="<?= $h(url('personnel/' . $targetId)) ?>" class="pd-btn">← Fiche</a>
      </div>
    </header>

    <?php if ($hasOpen): ?>
    <div class="pd-alert pd-alert--err" role="status">
      Une demande est déjà en attente pour cette fiche. Attendez la décision avant d’en envoyer une nouvelle.
    </div>
    <?php endif; ?>

    <form method="post" action="<?= $h(url('personnel/' . $targetId . '/correction')) ?>" class="pd-card">
      <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>" />
      <div class="pd-card__body">
        <?php foreach ($fieldGroups as $groupKey => $groupLabel): ?>
        <?php $keys = $fieldsByGroup[$groupKey] ?? []; if ($keys === []) { continue; } ?>
        <div class="pd-form-section">
          <h2 class="pd-form-section__title"><?= $h($groupLabel) ?></h2>
          <div class="pd-form-grid">
            <?php foreach ($keys as $key): ?>
            <?php
              $meta = $fieldCatalog[$key] ?? [];
              $label = (string) ($meta['label'] ?? $fieldLabels[$key] ?? $key);
              $type = (string) ($meta['type'] ?? 'text');
              $span = (int) ($meta['span'] ?? 1);
              $value = (string) ($snapshot[$key] ?? '');
              $help = trim((string) ($meta['help'] ?? ''));
              $wrapClass = $span > 1 ? 'pd-form-grid__full' : '';
            ?>
            <div class="<?= $h($wrapClass) ?>">
              <label class="mb-1 block text-xs font-bold text-slate-600" for="corr-<?= $h($key) ?>"><?= $h($label) ?></label>
              <?php if ($type === 'date'): ?>
              <input type="date" id="corr-<?= $h($key) ?>" name="<?= $h($key) ?>" value="<?= $h($value) ?>" <?= $disabled ? 'disabled' : '' ?> />
              <?php elseif ($type === 'number'): ?>
              <input type="number" id="corr-<?= $h($key) ?>" name="<?= $h($key) ?>" min="<?= (int) ($meta['min'] ?? 0) ?>" max="<?= (int) ($meta['max_num'] ?? 9999) ?>" value="<?= $h($value) ?>" <?= $disabled ? 'disabled' : '' ?> />
              <?php elseif ($type === 'textarea'): ?>
              <textarea id="corr-<?= $h($key) ?>" name="<?= $h($key) ?>" rows="<?= max(2, (int) ($meta['rows'] ?? 3)) ?>" <?= $disabled ? 'disabled' : '' ?>><?= $h($value) ?></textarea>
              <?php elseif ($type === 'select'): ?>
              <?php $opts = $choiceOptions((string) ($meta['choices'] ?? $key), $value); ?>
              <select id="corr-<?= $h($key) ?>" name="<?= $h($key) ?>" class="bo-select" <?= $disabled ? 'disabled' : '' ?>>
                <option value="">— Non renseigné —</option>
                <?php foreach ($opts as $opt): ?>
                <?php $ov = (string) ($opt['value'] ?? ''); ?>
                <option value="<?= $h($ov) ?>"<?= $ov === $value ? ' selected' : '' ?>><?= $h((string) ($opt['label'] ?? $ov)) ?></option>
                <?php endforeach; ?>
              </select>
              <?php else: ?>
              <input type="text" id="corr-<?= $h($key) ?>" name="<?= $h($key) ?>" value="<?= $h($value) ?>" <?= $disabled ? 'disabled' : '' ?> />
              <?php endif; ?>
              <?php if ($help !== ''): ?>
              <p class="pd-help"><?= $h($help) ?></p>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>

        <div class="pd-form-section">
          <h2 class="pd-form-section__title">Message pour l’organisateur</h2>
          <div class="pd-form-grid">
            <div class="pd-form-grid__full">
              <label class="mb-1 block text-xs font-bold text-slate-600" for="corr-note">Précision (optionnel)</label>
              <textarea id="corr-note" name="note" rows="3" maxlength="1000" placeholder="Précisez le contexte de l’anomalie…" <?= $disabled ? 'disabled' : '' ?>></textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="pd-card__foot">
        <button type="submit" class="pd-btn pd-btn--primary" <?= $disabled ? 'disabled' : '' ?>>Envoyer pour confirmation</button>
        <a href="<?= $h(url('personnel/' . $targetId)) ?>" class="pd-btn pd-btn--ghost">Retour à la fiche</a>
      </div>
    </form>

    <?php if ($pending !== []): ?>
    <div class="pd-card rh-corr-form__history" aria-labelledby="corr-history-title">
      <div class="pd-card__body">
        <h2 id="corr-history-title" class="pd-form-section__title">Historique récent</h2>
        <ul class="rh-corr-form__history-list">
          <?php foreach ($pending as $row): ?>
          <?php
            $st = trim((string) ($row['status'] ?? ''));
            $when = trim((string) ($row['created_at'] ?? ''));
            $whenFr = $when;
            try {
                if ($when !== '') {
                    $whenFr = (new DateTimeImmutable($when))->format('d/m/Y à H:i');
                }
            } catch (Throwable) {
            }
          ?>
          <li>
            <span class="rh-corr-form__history-status"><?= $h($statusFr($st)) ?></span>
            <?php if ($whenFr !== ''): ?>
            <span class="rh-corr-form__history-when"><?= $h($whenFr) ?></span>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
