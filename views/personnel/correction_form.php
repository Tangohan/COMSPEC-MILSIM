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
$staffDossier = $applyImmediately || $canApplyImmediately;

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
$visibleGroups = [];
foreach ($fieldGroups as $groupKey => $groupLabel) {
    if (($fieldsByGroup[$groupKey] ?? []) !== []) {
        $visibleGroups[$groupKey] = $groupLabel;
    }
}
$assignmentSlots = \App\Services\Personnel\PersonnelCorrectionRequestService::ASSIGNMENT_SLOT_COUNT;
$personnelProfile = is_array($personnelProfile ?? null) ? $personnelProfile : [];
$portraitUrl = '';
if ($staffDossier && function_exists('personnel_operator_portrait_url')) {
    $portraitUrl = (string) (personnel_operator_portrait_url($personnelProfile) ?: '');
}
$initials = function_exists('user_display_initials')
    ? (string) user_display_initials($displayName, 2)
    : mb_strtoupper(mb_substr($displayName, 0, 2));
$padSlots = static function (array $rows, int $count): array {
    $rows = array_values($rows);
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $out[$i] = is_array($rows[$i] ?? null) ? $rows[$i] : [];
    }

    return $out;
};
$primaryIndex = static function (array $rows, string $flag = 'is_primary'): int {
    foreach ($rows as $i => $row) {
        if (!empty($row[$flag])) {
            return (int) $i;
        }
    }

    return 0;
};
$firstTab = $staffDossier ? 'portrait' : (string) (array_key_first($visibleGroups) ?: 'identity');
$ficheUrl = url('personnel/' . $targetId);
$effectifsUrl = function_exists('effectifs_workspace_url')
    ? effectifs_workspace_url('membres/' . $targetId)
    : '';
?>
<?php if (!$embedded): ?>
<div class="pd-page rh-corr-form" x-data="{ tab: '<?= $h($firstTab) ?>' }" x-init="const h = window.location.hash.slice(1); if (h) { tab = h }; $watch('tab', v => { if (v) history.replaceState(null, '', '#' + v) })">
  <div class="pd-container">
    <header class="pd-header rh-corr-form__hero">
      <div class="rh-corr-form__identity">
        <div class="rh-corr-form__avatar" aria-hidden="true">
          <?php if ($portraitUrl !== ''): ?>
          <img src="<?= $h($portraitUrl) ?>" alt="" class="rh-corr-form__avatar-img">
          <?php else: ?>
          <span><?= $h($initials) ?></span>
          <?php endif; ?>
        </div>
        <div>
          <p class="pd-header__eyebrow">Corrections RH</p>
          <h1 class="pd-header__title"><?= $h($displayName) ?></h1>
          <p class="pd-header__sub">
            <?php if ($canApplyImmediately): ?>
              Toutes les informations du dossier se gèrent ici : personnage, affectations, immersion, équipement, identifiants et notes.
              Enregistrez tout de suite, ou envoyez une demande si quelqu’un d’autre doit confirmer.
            <?php else: ?>
              Proposez des corrections sur <?= $isSelf ? 'votre fiche' : 'la fiche de <strong>' . $h($displayName) . '</strong>' ?>.
              Chaque modification part en validation auprès d’un organisateur : un e-mail récapitulatif est envoyé aux deux parties, et la fiche n’est mise à jour qu’après confirmation.
            <?php endif; ?>
          </p>
        </div>
      </div>
      <div class="pd-header__actions">
        <a href="<?= $h($ficheUrl) ?>" class="pd-btn">← Fiche</a>
        <?php if ($staffDossier && $effectifsUrl !== ''): ?>
        <a href="<?= $h($effectifsUrl) ?>" class="pd-btn">Effectifs</a>
        <?php endif; ?>
      </div>
    </header>
<?php else: ?>
<div class="rh-corr-form rh-corr-form--embed" x-data="{ tab: '<?= $h($firstTab) ?>' }">
  <header class="rh-corr-form__hero rh-corr-form__hero--embed">
    <div class="rh-corr-form__identity">
      <div class="rh-corr-form__avatar" aria-hidden="true">
        <?php if ($portraitUrl !== ''): ?>
        <img src="<?= $h($portraitUrl) ?>" alt="" class="rh-corr-form__avatar-img">
        <?php else: ?>
        <span><?= $h($initials) ?></span>
        <?php endif; ?>
      </div>
      <div>
        <p class="pd-header__eyebrow">Corrections RH — dossier complet</p>
        <h2 class="pd-header__title"><?= $h($displayName) ?></h2>
        <p class="pd-header__sub">Personnage, affectations, immersion, équipement, identifiants et notes se règlent dans les onglets ci-dessous.</p>
      </div>
    </div>
    <div class="pd-header__actions">
      <a href="<?= $h($ficheUrl) ?>" class="pd-btn">Fiche</a>
      <?php if ($effectifsUrl !== ''): ?>
      <a href="<?= $h($effectifsUrl) ?>" class="pd-btn">Effectifs</a>
      <?php endif; ?>
    </div>
  </header>
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

    <nav class="pd-tabs" aria-label="Sections du dossier">
      <?php if ($staffDossier): ?>
      <div class="pd-tabs__group">
        <span class="pd-tabs__group-label">Présentation</span>
        <div class="pd-tabs__group-items">
          <button type="button" class="pd-tabs__btn" :class="tab === 'portrait' ? 'is-active' : ''" @click="tab = 'portrait'">Portrait</button>
        </div>
      </div>
      <?php endif; ?>
      <?php foreach ($visibleGroups as $groupKey => $groupLabel): ?>
      <div class="pd-tabs__group">
        <span class="pd-tabs__group-label"><?= $h($groupLabel) ?></span>
        <div class="pd-tabs__group-items">
          <button type="button" class="pd-tabs__btn" :class="tab === '<?= $h($groupKey) ?>' ? 'is-active' : ''" @click="tab = '<?= $h($groupKey) ?>'"><?= $h($groupLabel) ?></button>
        </div>
      </div>
      <?php endforeach; ?>
    </nav>

    <?php if ($staffDossier): ?>
    <div class="<?= $embedded ? '' : 'pd-card' ?> rh-corr-form__panel" x-show="tab === 'portrait'" x-cloak>
      <div class="<?= $embedded ? '' : 'pd-card__body' ?>">
        <div class="pd-form-section">
          <h2 class="pd-form-section__title">Portrait opérateur</h2>
          <p class="pd-help">Photo du dossier : fiche, organigramme et portail. Distincte de la photo du compte de connexion.</p>
          <div class="rh-corr-form__portrait-row">
            <div class="rh-corr-form__avatar rh-corr-form__avatar--lg" aria-hidden="true">
              <?php if ($portraitUrl !== ''): ?>
              <img src="<?= $h($portraitUrl) ?>" alt="" class="rh-corr-form__avatar-img">
              <?php else: ?>
              <span><?= $h($initials) ?></span>
              <?php endif; ?>
            </div>
            <form method="post" action="<?= $h(url('personnel/' . $targetId . '/portrait')) ?>" enctype="multipart/form-data" class="rh-corr-form__portrait-form">
              <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
              <input type="hidden" name="from_corrections" value="1">
              <label class="mb-1 block text-xs font-bold text-slate-600" for="corr-portrait">Choisir une image</label>
              <input id="corr-portrait" type="file" name="portrait" accept="image/jpeg,image/png,image/webp" required>
              <p class="pd-help">JPG, PNG ou WebP — 2 Mo maximum.</p>
              <button type="submit" class="pd-btn pd-btn--primary">Enregistrer le portrait</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= $h($formAction) ?>" class="<?= $embedded ? 'rh-corr__direct-form' : 'pd-card' ?>"<?php if ($staffDossier): ?> x-show="tab !== 'portrait'"<?php endif; ?>>
      <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>" />
      <?php if ($applyImmediately && $targetId > 0): ?>
      <input type="hidden" name="target_user_id" value="<?= $targetId ?>">
      <?php endif; ?>
      <div class="pd-card__body">
        <?php foreach ($visibleGroups as $groupKey => $groupLabel): ?>
        <?php $keys = $fieldsByGroup[$groupKey] ?? []; if ($keys === []) { continue; } ?>
        <div class="pd-form-section rh-corr-form__panel" x-show="tab === '<?= $h($groupKey) ?>'" x-cloak>
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
              $unitRows = $key === 'unit_assignments'
                  ? $padSlots(\App\Services\Personnel\PersonnelCorrectionRequestService::decodeAssignmentRows($snapshot[$key] ?? []), $assignmentSlots)
                  : [];
              $jobRows = $key === 'job_roles'
                  ? $padSlots(\App\Services\Personnel\PersonnelCorrectionRequestService::decodeJobRoleRows($snapshot[$key] ?? []), $assignmentSlots)
                  : [];
              $unitPrimary = $key === 'unit_assignments' ? $primaryIndex($unitRows) : 0;
              $jobPrimary = $key === 'job_roles' ? $primaryIndex($jobRows) : 0;
            ?>
            <div class="<?= $h($wrapClass) ?>">
              <?php if ($type === 'checkbox'): ?>
              <input type="hidden" name="<?= $h($key) ?>" value="0">
              <label class="rh-corr-form__check" for="corr-<?= $h($key) ?>">
                <input type="checkbox" id="corr-<?= $h($key) ?>" name="<?= $h($key) ?>" value="1" <?= $value === '1' ? 'checked' : '' ?> <?= $disabled ? 'disabled' : '' ?>>
                <span><?= $h($label) ?></span>
              </label>
              <?php else: ?>
              <label class="mb-1 block text-xs font-bold text-slate-600" for="corr-<?= $h($key) ?>"><?= $h($label) ?></label>
              <?php endif; ?>
              <?php if ($type === 'unit_assignments'): ?>
              <div class="rh-corr-form__slots">
                <?php for ($slot = 0; $slot < $assignmentSlots; $slot++): ?>
                <?php
                  $urow = $unitRows[$slot] ?? [];
                  $uidVal = (string) ((int) ($urow['unit_id'] ?? 0));
                  if ($uidVal === '0') {
                      $uidVal = '';
                  }
                ?>
                <div class="rh-corr-form__slot">
                  <label class="rh-corr-form__slot-primary">
                    <input type="radio" name="unit_primary_index" value="<?= $slot ?>" <?= $unitPrimary === $slot ? 'checked' : '' ?> <?= $disabled ? 'disabled' : '' ?>>
                    <?= $slot === 0 ? 'Principale' : 'Complémentaire ' . $slot ?>
                  </label>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                      <label class="mb-1 block text-[11px] font-bold text-slate-500" for="corr-unit-id-<?= $slot ?>">Unité</label>
                      <select id="corr-unit-id-<?= $slot ?>" name="unit_assignments[<?= $slot ?>][unit_id]" class="bo-select" <?= $disabled ? 'disabled' : '' ?>>
                        <option value="">— Aucune —</option>
                        <?php foreach ($choiceOptions('units', $uidVal) as $opt): ?>
                        <?php $ov = (string) ($opt['value'] ?? ''); ?>
                        <option value="<?= $h($ov) ?>"<?= $uidVal === $ov ? ' selected' : '' ?>><?= $h((string) ($opt['label'] ?? $ov)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div>
                      <label class="mb-1 block text-[11px] font-bold text-slate-500" for="corr-unit-role-<?= $slot ?>">Place dans l’équipe</label>
                      <input type="text" id="corr-unit-role-<?= $slot ?>" name="unit_assignments[<?= $slot ?>][role_name]" value="<?= $h((string) ($urow['role_name'] ?? '')) ?>" maxlength="120" <?= $disabled ? 'disabled' : '' ?>>
                    </div>
                  </div>
                </div>
                <?php endfor; ?>
              </div>
              <?php elseif ($type === 'job_roles'): ?>
              <div class="rh-corr-form__slots">
                <?php for ($slot = 0; $slot < $assignmentSlots; $slot++): ?>
                <?php
                  $jrow = $jobRows[$slot] ?? [];
                  $jidVal = (string) ((int) ($jrow['role_id'] ?? $jrow['personnel_job_role_id'] ?? 0));
                  if ($jidVal === '0') {
                      $jidVal = '';
                  }
                ?>
                <div class="rh-corr-form__slot">
                  <label class="rh-corr-form__slot-primary">
                    <input type="radio" name="job_primary_index" value="<?= $slot ?>" <?= $jobPrimary === $slot ? 'checked' : '' ?> <?= $disabled ? 'disabled' : '' ?>>
                    <?= $slot === 0 ? 'Principal' : 'Complémentaire ' . $slot ?>
                  </label>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                      <label class="mb-1 block text-[11px] font-bold text-slate-500" for="corr-job-id-<?= $slot ?>">Emploi</label>
                      <select id="corr-job-id-<?= $slot ?>" name="job_roles[<?= $slot ?>][role_id]" class="bo-select" <?= $disabled ? 'disabled' : '' ?>>
                        <option value="">— Non renseigné —</option>
                        <?php foreach ($choiceOptions('job_roles', $jidVal) as $opt): ?>
                        <?php $ov = (string) ($opt['value'] ?? ''); ?>
                        <option value="<?= $h($ov) ?>"<?= $jidVal === $ov ? ' selected' : '' ?>><?= $h((string) ($opt['label'] ?? $ov)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div>
                      <label class="mb-1 block text-[11px] font-bold text-slate-500" for="corr-job-detail-<?= $slot ?>">Précision</label>
                      <input type="text" id="corr-job-detail-<?= $slot ?>" name="job_roles[<?= $slot ?>][detail]" value="<?= $h((string) ($jrow['detail'] ?? $jrow['role_detail'] ?? '')) ?>" maxlength="150" <?= $disabled ? 'disabled' : '' ?>>
                    </div>
                  </div>
                </div>
                <?php endfor; ?>
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
              <?php elseif ($type !== 'checkbox'): ?>
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
        <a href="<?= $h($ficheUrl) ?>" class="pd-btn pd-btn--ghost">Retour à la fiche</a>
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
