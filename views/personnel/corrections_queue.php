<?php
declare(strict_types=1);

/*
 * Back-office > Personnel > Corrections de fiches.
 * Trois onglets, chacun avec son adresse : ?vue=a-traiter (défaut), ?vue=historique, ?vue=corriger.
 * Valider d'un clic ; refuser demande un motif ; traitement par lot pour qui en a le droit.
 * Aucune adresse e-mail n'est affichée ici.
 */

/** @var list<array<string,mixed>> $requests */
/** @var list<array<string,mixed>> $history */
/** @var string $csrf */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$when = static function (mixed $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($raw);
    } catch (\Throwable) {
        return $raw;
    }

    return $dt->format('d/m/Y à H:i');
};
$vue = (string) ($vue ?? 'a-traiter');
$pendingCount = (int) ($pendingCount ?? 0);
$resolvedCount = (int) ($resolvedCount ?? 0);
$canBatch = !empty($canBatch);
$batchMax = (int) ($batchMax ?? 50);
$requests = is_array($requests ?? null) ? $requests : [];
$history = is_array($history ?? null) ? $history : [];
$csrf = (string) ($csrf ?? '');
$base = url('back-office/personnel/corrections');
$statusLabels = ['approved' => 'Acceptée', 'rejected' => 'Refusée', 'cancelled' => 'Annulée'];

/** Valeur actuelle et valeur proposée, côte à côte. */
$renderChanges = static function (array $changes) use ($h, $vue): void {
    if ($changes === []) {
        return;
    }
    $isHistory = $vue === 'historique';
    ?>
    <ul class="rh-corr__changes">
        <?php foreach ($changes as $change): ?>
            <?php
            $blank = trim((string) ($change['blank'] ?? ''));
            if ($blank === '') {
                $blank = 'Non renseigné';
            }
            $oldLines = is_array($change['before'] ?? null) ? $change['before'] : [];
            $newLines = is_array($change['after'] ?? null) ? $change['after'] : [];
            ?>
            <li class="rh-corr__change">
                <p class="rh-corr__field"><?= $h((string) ($change['label'] ?? 'Information du dossier')) ?></p>
                <div class="rh-corr__sides">
                    <div class="rh-corr__side rh-corr__side--old">
                        <span class="rh-corr__side-label"><?= $isHistory ? 'Avant' : 'Actuellement' ?></span>
                        <p><?php if ($oldLines === []): ?><span class="rh-corr__blank"><?= $h($blank) ?></span><?php else: ?><?php foreach ($oldLines as $line): ?><span class="rh-corr__line"><?= $h((string) $line) ?></span><?php endforeach; ?><?php endif; ?></p>
                    </div>
                    <div class="rh-corr__side rh-corr__side--new">
                        <span class="rh-corr__side-label">Proposé</span>
                        <p><?php if ($newLines === []): ?><span class="rh-corr__blank"><?= $h($blank) ?></span><?php else: ?><?php foreach ($newLines as $line): ?><span class="rh-corr__line"><?= $h((string) $line) ?></span><?php endforeach; ?><?php endif; ?></p>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};
/** « En attente depuis N jours » : le nombre est écrit dans la phrase, pour que la traduction reste possible. */
$waiting = static function (?int $days): void {
    if ($days === null) {
        return;
    }
    echo '<p class="rh-corr__since">';
    if ($days === 0) {
        ?>Reçue aujourd’hui<?php
    } elseif ($days === 1) {
        ?>En attente depuis hier<?php
    } else {
        ?>En attente depuis <?= $days ?> jours<?php
    }
    echo '</p>';
};
?>
<div class="rh-corr">
  <nav class="rh-corr__tabs" aria-label="Sections des corrections">
    <a class="rh-corr__tab<?= $vue === 'a-traiter' ? ' is-active' : '' ?>" href="<?= $h($base . '?vue=a-traiter') ?>"<?= $vue === 'a-traiter' ? ' aria-current="page"' : '' ?>>
      À traiter <span class="rh-corr__tab-n<?= $pendingCount > 0 ? ' is-on' : '' ?>"><?= $pendingCount ?></span>
    </a>
    <a class="rh-corr__tab<?= $vue === 'historique' ? ' is-active' : '' ?>" href="<?= $h($base . '?vue=historique') ?>"<?= $vue === 'historique' ? ' aria-current="page"' : '' ?>>
      Historique <span class="rh-corr__tab-n"><?= $resolvedCount ?></span>
    </a>
    <a class="rh-corr__tab<?= $vue === 'corriger' ? ' is-active' : '' ?>" href="<?= $h($base . '?vue=corriger') ?>"<?= $vue === 'corriger' ? ' aria-current="page"' : '' ?>>
      Corriger un dossier
    </a>
  </nav>

<?php if ($vue === 'a-traiter'): ?>
  <p class="rh-corr__count" aria-live="polite">
    <?php if ($pendingCount < 1): ?>
      Aucune demande à traiter.
    <?php else: ?>
      <?= $pendingCount ?> demande<?= $pendingCount > 1 ? 's' : '' ?> à traiter, les plus anciennes d’abord.
      La fiche n’est mise à jour qu’après votre validation.
    <?php endif; ?>
  </p>

  <?php if ($requests === []): ?>
  <div class="rh-corr__empty" role="status">
    <p class="rh-corr__empty-title">File vide</p>
    <p class="rh-corr__empty-text">Quand un membre signale une anomalie sur sa fiche, la demande apparaît ici pour validation ou refus. Vous pouvez aussi corriger un dossier tout de suite, sans attendre de demande.</p>
    <p><a class="rh-corr__btn" href="<?= $h($base . '?vue=corriger') ?>">Corriger un dossier sans demande</a></p>
  </div>
  <?php else: ?>
    <?php if ($canBatch && count($requests) > 1): ?>
    <form method="post" action="<?= $h($base . '/lot') ?>" id="rh-corr-batch" class="rh-corr__batch" data-rh-batch>
      <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
      <label class="rh-corr__check">
        <input type="checkbox" data-rh-batch-all>
        <span>Tout sélectionner</span>
      </label>
      <span class="rh-corr__batch-count" aria-live="polite">Sélection : <b data-rh-batch-n>0</b> sur <?= count($requests) ?></span>
      <button type="submit" name="decision" value="approved" class="rh-corr__btn rh-corr__btn--ok" data-rh-batch-go>Valider la sélection</button>
      <label class="rh-corr__reason">
        <span class="rh-corr__sr">Motif du refus de la sélection</span>
        <input type="text" name="resolution_note" maxlength="1000" placeholder="Motif du refus (obligatoire pour refuser)" data-rh-batch-reason>
      </label>
      <button type="submit" name="decision" value="rejected" class="rh-corr__btn rh-corr__btn--no" data-rh-batch-go data-rh-batch-reject>Refuser la sélection</button>
      <small class="rh-corr__batch-help">Au plus <?= $batchMax ?> demandes à la fois. Chaque membre est prévenu de la décision.</small>
    </form>
    <?php endif; ?>

  <ul class="rh-corr__list">
    <?php foreach ($requests as $row): ?>
    <?php
      $id = (int) ($row['id'] ?? 0);
      $targetId = (int) ($row['target_user_id'] ?? 0);
      $note = trim((string) ($row['note'] ?? ''));
      $askedAt = $when($row['created_at'] ?? '');
      $changes = is_array($row['changes'] ?? null) ? $row['changes'] : [];
    ?>
    <li class="rh-corr__card" id="demande-<?= $id ?>">
      <header class="rh-corr__card-head">
        <?php if ($canBatch && count($requests) > 1): ?>
        <label class="rh-corr__pick-one">
          <input type="checkbox" name="ids[]" value="<?= $id ?>" form="rh-corr-batch" data-rh-batch-one>
          <span class="rh-corr__sr">Sélectionner la demande de <?= $h((string) ($row['target_label'] ?? '')) ?></span>
        </label>
        <?php endif; ?>
        <div class="rh-corr__who">
          <h2 class="rh-corr__card-title"><?= $h((string) ($row['target_label'] ?? 'Membre')) ?></h2>
          <p class="rh-corr__card-meta">
            <?php if (!empty($row['self_request'])): ?>
              Demandé par le membre lui-même
            <?php else: ?>
              Demandé par <?= $h((string) ($row['requester_label'] ?? '')) ?>
            <?php endif; ?>
            <?php if ($askedAt !== ''): ?> · le <?= $h($askedAt) ?><?php endif; ?>
          </p>
          <?php $waiting(isset($row['waiting_days']) ? (int) $row['waiting_days'] : null); ?>
        </div>
        <?php if (!empty($row['already_applied'])): ?>
          <span class="rh-corr__badge">La fiche est déjà à jour</span>
        <?php endif; ?>
        <?php if ($targetId > 0): ?>
        <a class="rh-corr__link" href="<?= $h(url('personnel/' . $targetId)) ?>">Ouvrir la fiche</a>
        <?php endif; ?>
      </header>

      <?php if ($note !== ''): ?>
      <blockquote class="rh-corr__note"><span class="rh-corr__note-label">Message du membre</span><?= $h($note) ?></blockquote>
      <?php endif; ?>

      <?php $renderChanges($changes); ?>

      <div class="rh-corr__actions">
        <form method="post" action="<?= $h($base . '/' . $id . '/decide') ?>" class="rh-corr__approve">
          <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
          <button type="submit" name="decision" value="approved" class="rh-corr__btn rh-corr__btn--ok">Valider</button>
        </form>
        <details class="rh-corr__refuse">
          <summary class="rh-corr__btn rh-corr__btn--no">Refuser</summary>
          <form method="post" action="<?= $h($base . '/' . $id . '/decide') ?>" class="rh-corr__refuse-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <label for="rh-corr-motif-<?= $id ?>">Motif du refus (le membre le recevra)</label>
            <input type="text" id="rh-corr-motif-<?= $id ?>" name="resolution_note" maxlength="1000" required placeholder="Expliquez en une phrase pourquoi la fiche reste telle quelle">
            <button type="submit" name="decision" value="rejected" class="rh-corr__btn rh-corr__btn--no">Confirmer le refus</button>
          </form>
        </details>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($pendingCount > count($requests)): ?>
    <p class="rh-corr__more">Les <?= count($requests) ?> plus anciennes sont affichées. Traitez-les : les suivantes apparaîtront ensuite.</p>
  <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

<?php if ($vue === 'historique'): ?>
  <p class="rh-corr__count">Les demandes déjà traitées, de la plus récente à la plus ancienne.</p>
  <?php if ($history === []): ?>
  <div class="rh-corr__empty" role="status">
    <p class="rh-corr__empty-title">Aucun historique</p>
    <p class="rh-corr__empty-text">Les demandes validées, refusées ou annulées apparaîtront ici.</p>
  </div>
  <?php else: ?>
  <ul class="rh-corr__list rh-corr__list--history">
    <?php foreach ($history as $row): ?>
    <?php
      $status = (string) ($row['status'] ?? '');
      $resolvedAt = $when($row['resolved_at'] ?? ($row['updated_at'] ?? ''));
      $askedAt = $when($row['created_at'] ?? '');
      $resolution = trim((string) ($row['resolution_note'] ?? ''));
      $changes = is_array($row['changes'] ?? null) ? $row['changes'] : [];
      $fieldNames = array_map(static fn (array $c): string => (string) ($c['label'] ?? ''), $changes);
    ?>
    <li class="rh-corr__card rh-corr__card--<?= $h($status) ?>">
      <header class="rh-corr__card-head">
        <div class="rh-corr__who">
          <h2 class="rh-corr__card-title"><?= $h((string) ($row['target_label'] ?? 'Membre')) ?></h2>
          <p class="rh-corr__card-meta">
            <?php if (!empty($row['self_request'])): ?>Demandé par le membre lui-même<?php else: ?>Demandé par <?= $h((string) ($row['requester_label'] ?? '')) ?><?php endif; ?>
            <?php if ($askedAt !== ''): ?> · le <?= $h($askedAt) ?><?php endif; ?>
          </p>
          <p class="rh-corr__card-meta">
            <?php if ($resolvedAt !== ''): ?>Traitée le <?= $h($resolvedAt) ?><?php endif; ?>
            <?php if (trim((string) ($row['resolver_label'] ?? '')) !== ''): ?> par <?= $h((string) $row['resolver_label']) ?><?php endif; ?>
          </p>
        </div>
        <span class="rh-corr__status rh-corr__status--<?= $h($status) ?>"><?= $h($statusLabels[$status] ?? 'Traitée') ?></span>
      </header>
      <?php if ($fieldNames !== []): ?>
        <p class="rh-corr__fields"><?php foreach ($fieldNames as $name): ?><em class="rh-corr__chip"><?= $h($name) ?></em><?php endforeach; ?></p>
      <?php endif; ?>
      <?php if ($resolution !== ''): ?>
        <blockquote class="rh-corr__note"><span class="rh-corr__note-label"><?= $status === 'rejected' ? 'Motif du refus' : 'Message au membre' ?></span><?= $h($resolution) ?></blockquote>
      <?php endif; ?>
      <?php if ($changes !== []): ?>
      <details class="rh-corr__more-changes">
        <summary>Voir les valeurs avant et après</summary>
        <?php $renderChanges($changes); ?>
      </details>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($resolvedCount > count($history)): ?>
    <p class="rh-corr__more">Les <?= count($history) ?> plus récentes sont affichées.</p>
  <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

<?php if ($vue === 'corriger'): ?>
  <section class="rh-corr__direct" aria-labelledby="rh-corr-direct-title">
    <h2 id="rh-corr-direct-title" class="rh-corr__direct-title">Corriger un dossier sans demande</h2>
    <p class="rh-corr__direct-lead">
      Choisissez un membre : le dossier s’ouvre en entier (personnage, équipes, emplois, immersion, équipement, identifiants, notes et portrait).
      La fiche est mise à jour tout de suite. S’il restait une demande en attente pour ce dossier, elle est close.
    </p>
    <form method="get" action="<?= $h($base) ?>" class="rh-corr__pick">
      <input type="hidden" name="vue" value="corriger">
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
<?php endif; ?>
</div>
<script src="<?= $h(asset_url('assets/js/rh-corrections.js')) ?>" defer></script>
