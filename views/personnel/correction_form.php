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
$hasOpen = !empty($hasOpen);
$isSelf = !empty($isSelf);
$applyImmediately = !empty($applyImmediately);
$embedded = !empty($embedded);
$canApplyImmediately = !empty($canApplyImmediately) || $applyImmediately;
$formAction = trim((string) ($formAction ?? ''));
if ($formAction === '') {
    $formAction = url('personnel/' . $targetId . '/correction');
}
$disabled = $hasOpen && !$applyImmediately && !$canApplyImmediately;

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
<?php if (!$embedded): ?>
<div class="pd-page rh-corr-form">
  <div class="pd-container pd-container--narrow">
    <header class="pd-header">
      <div>
        <p class="pd-header__eyebrow">Anomalie fiche</p>
        <h1 class="pd-header__title">Correction RH</h1>
        <p class="pd-header__sub">
          <?php if ($canApplyImmediately): ?>
            Corrigez <?= $isSelf ? 'votre fiche' : 'la fiche de <strong>' . $h($displayName) . '</strong>' ?>.
            Vous pouvez enregistrer tout de suite, ou envoyer une demande si quelqu’un d’autre doit confirmer.
          <?php else: ?>
            Proposez des corrections sur <?= $isSelf ? 'votre fiche' : 'la fiche de <strong>' . $h($displayName) . '</strong>' ?>.
            Chaque modification part en validation auprès d’un organisateur : un e-mail récapitulatif est envoyé aux deux parties, et la fiche n’est mise à jour qu’après confirmation.
          <?php endif; ?>
        </p>
      </div>
      <div class="pd-header__actions">
        <a href="<?= $h(url('personnel/' . $targetId)) ?>" class="pd-btn">← Fiche</a>
      </div>
    </header>
<?php else: ?>
<div class="rh-corr-form rh-corr-form--embed">
<?php endif; ?>

    <?php if ($hasOpen && !$applyImmediately): ?>
    <div class="pd-alert pd-alert--err" role="status">
      <?php if ($canApplyImmediately): ?>
        Une demande est déjà en attente pour cette fiche. Vous pouvez l’enregistrer tout de suite : la demande en cours sera close.
      <?php else: ?>
        Une demande est déjà en attente pour cette fiche. Attendez la décision avant d’en envoyer une nouvelle.
      <?php endif; ?>
    </div>
    <?php elseif ($hasOpen && $applyImmediately): ?>
    <p class="rh-corr__note">Une demande est déjà en attente. Enregistrer tout de suite met à jour le dossier et clôture cette demande.</p>
    <?php endif; ?>

    <form method="post" action="<?= $h($formAction) ?>" class="<?= $embedded ? 'rh-corr__direct-form' : 'pd-card' ?>">
      <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>" />
      <?php if ($applyImmediately && $targetId > 0): ?>
      <input type="hidden" name="target_user_id" value="<?= $targetId ?>">
      <?php endif; ?>
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
              $unitRows = $key === 'unit_assignments' ? \App\Services\Personnel\PersonnelCorrectionRequestService::decodeAssignmentRows($snapshot[$key] ?? []) : [];
              $jobRows = $key === 'job_roles' ? \App\Services\Personnel\PersonnelCorrectionRequestService::decodeJobRoleRows($snapshot[$key] ?? []) : [];
              $primaryUnit = $unitRows[0] ?? ['unit_id' => 0, 'role_name' => '', 'is_primary' => 1];
              foreach ($unitRows as $ur) {
                  if (!empty($ur['is_primary'])) {
                      $primaryUnit = $ur;
                      break;
                  }
              }
              $primaryJob = $jobRows[0] ?? ['role_id' => 0, 'detail' => '', 'is_primary' => 1];
              foreach ($jobRows as $jr) {
                  if (!empty($jr['is_primary'])) {
                      $primaryJob = $jr;
                      break;
                  }
              }
            ?>
            <div class="<?= $h($wrapClass) ?>">
              <label class="mb-1 block text-xs font-bold text-slate-600" for="corr-<?= $h($key) ?>"><?= $h($label) ?></label>
              <?php if ($type === 'unit_assignments'): ?>
              <input type="hidden" name="unit_assignments[0][is_primary]" value="1">
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label class="mb-1 block text-[11px] font-bold text-slate-500" for="corr-unit-id">Unité principale</label>
                  <select id="corr-unit-id" name="unit_assignments[0][unit_id]" class="bo-select" <?= $disabled ? 'disabled' : '' ?>>
                    <option value="">— Aucune —</option>
                    <?php foreach ($choiceOptions('units', (string) ((int) ($primaryUnit['unit_id'] ?? 0))) as $opt): ?>
                    <?php $ov = (string) ($opt['value'] ?? ''); ?>
                    <option value="<?= $h($ov) ?>"<?= (string) ((int) ($primaryUnit['unit_id'] ?? 0)) === $ov ? ' selected' : '' ?>><?= $h((string) ($opt['label'] ?? $ov)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-[11px] font-bold text-slate-500" for="corr-unit-role">Rôle dans l’unité</label>
                  <input type="text" id="corr-unit-role" name="unit_assignments[0][role_name]" value="<?= $h((string) ($primaryUnit['role_name'] ?? '')) ?>" maxlength="120" <?= $disabled ? 'disabled' : '' ?>>
                </div>
              </div>
              <?php elseif ($type === 'job_roles'): ?>
              <input type="hidden" name="job_roles[0][is_primary]" value="1">
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label class="mb-1 block text-[11px] font-bold text-slate-500" for="corr-job-id">Emploi principal</label>
                  <select id="corr-job-id" name="job_roles[0][role_id]" class="bo-select" <?= $disabled ? 'disabled' : '' ?>>
                    <option value="">— Non renseigné —</option>
                    <?php foreach ($choiceOptions('job_roles', (string) ((int) ($primaryJob['role_id'] ?? 0))) as $opt): ?>
                    <?php $ov = (string) ($opt['value'] ?? ''); ?>
                    <option value="<?= $h($ov) ?>"<?= (string) ((int) ($primaryJob['role_id'] ?? 0)) === $ov ? ' selected' : '' ?>><?= $h((string) ($opt['label'] ?? $ov)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-[11px] font-bold text-slate-500" for="corr-job-detail">Précision</label>
                  <input type="text" id="corr-job-detail" name="job_roles[0][detail]" value="<?= $h((string) ($primaryJob['detail'] ?? '')) ?>" maxlength="150" <?= $disabled ? 'disabled' : '' ?>>
                </div>
              </div>
              <?php elseif ($type === 'date'): ?>
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
          <h2 class="pd-form-section__title"><?= $applyImmediately || $canApplyImmediately ? 'Commentaire (optionnel)' : 'Message pour l’organisateur' ?></h2>
          <div class="pd-form-grid">
            <div class="pd-form-grid__full">
              <label class="mb-1 block text-xs font-bold text-slate-600" for="corr-note"><?= $applyImmediately || $canApplyImmediately ? 'Précision pour le dossier' : 'Précision (optionnel)' ?></label>
              <textarea id="corr-note" name="note" rows="3" maxlength="1000" placeholder="<?= $applyImmediately || $canApplyImmediately ? 'Contexte de la correction…' : 'Précisez le contexte de l’anomalie…' ?>" <?= $disabled ? 'disabled' : '' ?>></textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="<?= $embedded ? 'rh-corr__direct-foot' : 'pd-card__foot' ?>">
        <?php if ($applyImmediately): ?>
          <button type="submit" class="pd-btn pd-btn--primary" <?= $disabled ? 'disabled' : '' ?>>Enregistrer tout de suite</button>
        <?php else: ?>
          <button type="submit" class="pd-btn pd-btn--primary" <?= $hasOpen ? 'disabled' : '' ?>>Envoyer pour confirmation</button>
          <?php if ($canApplyImmediately): ?>
          <button type="submit" name="apply_now" value="1" class="pd-btn">Enregistrer tout de suite</button>
          <?php endif; ?>
        <?php endif; ?>
        <?php if (!$embedded): ?>
        <a href="<?= $h(url('personnel/' . $targetId)) ?>" class="pd-btn pd-btn--ghost">Retour à la fiche</a>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($pending !== [] && !$embedded): ?>
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
<?php if (!$embedded): ?>
  </div>
</div>
<?php else: ?>
</div>
<?php endif; ?>
