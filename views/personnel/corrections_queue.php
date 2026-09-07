<?php
/** @var list<array<string,mixed>> $requests */
/** @var array<string,string> $fieldLabels */
/** @var string $csrf */
/** @var int $pendingCount */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$when = static function (mixed $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($raw);
    } catch (\Throwable) {
        return $raw;
    }

    return $dt->format('d/m/Y à H:i');
};
$pendingCount = (int) ($pendingCount ?? count($requests ?? []));
$requests = is_array($requests ?? null) ? $requests : [];
$fieldLabels = is_array($fieldLabels ?? null) ? $fieldLabels : [];
?>
<div class="rh-corr">
  <p class="rh-corr__count" aria-live="polite">
    <?php if ($pendingCount < 1): ?>
      Aucune demande à confirmer.
    <?php else: ?>
      <?= $pendingCount ?> demande<?= $pendingCount > 1 ? 's' : '' ?> à confirmer.
      La fiche n’est mise à jour qu’après votre validation. Vous pouvez aussi corriger un dossier sans demande, plus bas.
    <?php endif; ?>
  </p>

  <?php if ($requests === []): ?>
  <div class="rh-corr__empty" role="status">
    <p class="rh-corr__empty-title">File vide</p>
    <p class="rh-corr__empty-text">Quand un membre signale une anomalie sur sa fiche, la demande apparaît ici pour confirmation ou refus. Vous pouvez aussi corriger un dossier tout de suite, sans attendre de demande.</p>
  </div>
  <?php else: ?>
  <div class="rh-corr__list">
    <?php foreach ($requests as $row): ?>
    <?php
      if (!is_array($row)) {
          continue;
      }
      $id = (int) ($row['id'] ?? 0);
      $targetId = (int) ($row['target_user_id'] ?? 0);
      $targetName = trim((string) ($row['target_display_name'] ?? '')) ?: ('Membre #' . $targetId);
      $reqName = trim((string) ($row['requester_display_name'] ?? '')) ?: 'Demandeur';
      $proposed = is_array($row['proposed'] ?? null) ? $row['proposed'] : [];
      $before = is_array($row['before'] ?? null) ? $row['before'] : [];
      $askedAt = $when($row['created_at'] ?? '');
      $note = trim((string) ($row['note'] ?? ''));
    ?>
    <article class="rh-corr__card">
      <header class="rh-corr__card-head">
        <div>
          <h2 class="rh-corr__card-title"><?= $h($targetName) ?></h2>
          <p class="rh-corr__card-meta">
            Demandé par <?= $h($reqName) ?>
            <?php if ($askedAt !== ''): ?> · <?= $h($askedAt) ?><?php endif; ?>
          </p>
        </div>
        <?php if ($targetId > 0): ?>
        <a class="rh-corr__link" href="<?= $h(url('personnel/' . $targetId)) ?>">Ouvrir la fiche</a>
        <?php endif; ?>
      </header>

      <?php if ($note !== ''): ?>
      <p class="rh-corr__note"><?= $h($note) ?></p>
      <?php endif; ?>

      <?php if ($proposed !== []): ?>
      <ul class="rh-corr__changes">
        <?php
          $proposedDisplay = is_array($row['proposed_display'] ?? null) ? $row['proposed_display'] : [];
          $beforeDisplay = is_array($row['before_display'] ?? null) ? $row['before_display'] : [];
        ?>
        <?php foreach ($proposed as $key => $newVal): ?>
        <?php
          $label = $fieldLabels[(string) $key] ?? (string) $key;
          $old = trim((string) ($beforeDisplay[$key] ?? $before[$key] ?? ''));
          $new = trim((string) ($proposedDisplay[$key] ?? $newVal));
        ?>
        <li>
          <span class="rh-corr__field"><?= $h($label) ?></span>
          <span class="rh-corr__old"><?= $h($old !== '' ? $old : 'Non renseigné') ?></span>
          <span class="rh-corr__arrow" aria-hidden="true">→</span>
          <span class="rh-corr__new"><?= $h($new !== '' ? $new : 'Vide') ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <form method="post" action="<?= $h(url('back-office/personnel/corrections/' . $id . '/decide')) ?>" class="rh-corr__actions">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <label class="rh-corr__comment">
          Commentaire (optionnel)
          <input type="text" name="resolution_note" maxlength="1000" placeholder="Précision pour le membre">
        </label>
        <div class="rh-corr__btns">
          <button type="submit" name="decision" value="approved" class="rh-corr__btn rh-corr__btn--ok">Confirmer</button>
          <button type="submit" name="decision" value="rejected" class="rh-corr__btn rh-corr__btn--no">Refuser</button>
        </div>
      </form>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <section class="rh-corr__direct" aria-labelledby="rh-corr-direct-title">
    <h2 id="rh-corr-direct-title" class="rh-corr__direct-title">Corriger un dossier sans demande</h2>
    <p class="rh-corr__direct-lead">
      Choisissez un membre, corrigez les informations, puis enregistrez.
      La fiche est mise à jour tout de suite. S’il restait une demande en attente pour ce dossier, elle est close.
    </p>
    <form method="get" action="<?= $h(url('back-office/personnel/corrections')) ?>" class="rh-corr__pick">
      <label class="rh-corr__comment" for="rh-corr-membre">
        Membre
        <select id="rh-corr-membre" name="membre" required>
          <option value="">Choisir un membre</option>
          <?php foreach (is_array($directMembers ?? null) ? $directMembers : [] as $opt): ?>
            <?php $oid = (int) ($opt['id'] ?? 0); if ($oid < 1) { continue; } ?>
            <option value="<?= $oid ?>" <?= (int) (($directTarget['id'] ?? 0)) === $oid ? 'selected' : '' ?>><?= $h((string) ($opt['label'] ?? 'Membre')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="rh-corr__btn rh-corr__btn--ok">Ouvrir le dossier</button>
    </form>

    <?php if (is_array($directTarget ?? null) && (int) ($directTarget['id'] ?? 0) > 0): ?>
      <?php
        $targetUser = $directTarget;
        $snapshot = is_array($directSnapshot ?? null) ? $directSnapshot : [];
        $fieldCatalog = is_array($directFieldCatalog ?? null) ? $directFieldCatalog : [];
        $fieldGroups = is_array($directFieldGroups ?? null) ? $directFieldGroups : [];
        $choiceCatalog = is_array($directChoiceCatalog ?? null) ? $directChoiceCatalog : [];
        $pending = is_array($directPending ?? null) ? $directPending : [];
        $hasOpen = !empty($directHasOpen);
        $isSelf = false;
        $applyImmediately = true;
        $embedded = true;
        $canApplyImmediately = true;
        $formAction = url('back-office/personnel/corrections/appliquer');
        $csrf = (string) ($csrf ?? '');
        require base_path('views/personnel/correction_form.php');
      ?>
    <?php endif; ?>
  </section>
</div>
