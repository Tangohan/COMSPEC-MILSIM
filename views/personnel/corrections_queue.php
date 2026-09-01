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
      La fiche n’est mise à jour qu’après votre validation.
    <?php endif; ?>
  </p>

  <?php if ($requests === []): ?>
  <div class="rh-corr__empty" role="status">
    <p class="rh-corr__empty-title">File vide</p>
    <p class="rh-corr__empty-text">Quand un membre signale une anomalie sur sa fiche, la demande apparaît ici pour confirmation ou refus.</p>
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
        <?php foreach ($proposed as $key => $newVal): ?>
        <?php
          $label = $fieldLabels[(string) $key] ?? (string) $key;
          $old = isset($before[$key]) ? trim((string) $before[$key]) : '';
          $new = trim((string) $newVal);
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
</div>
