<?php
/** @var list<array<string,mixed>> $requests */
/** @var array<string,string> $fieldLabels */
/** @var string $csrf */
/** @var int $pendingCount */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="mx-auto max-w-5xl space-y-6 px-4 py-8">
  <div>
    <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-400/90">Ressources humaines</p>
    <h1 class="mt-1 text-2xl font-black text-white">Corrections RH en attente</h1>
    <p class="mt-2 text-sm text-slate-400">
      <?= (int) $pendingCount ?> demande(s) à confirmer. La fiche opérateur n’est mise à jour qu’après votre validation.
      Un e-mail récapitulatif part au membre et aux parties concernées.
    </p>
  </div>

  <?php if ($requests === []): ?>
  <div class="rounded-xl border border-white/10 bg-slate-900/60 px-5 py-8 text-center text-sm text-slate-400">
    Aucune correction en attente.
  </div>
  <?php else: ?>
  <div class="space-y-4">
    <?php foreach ($requests as $row): ?>
    <?php
      $id = (int) ($row['id'] ?? 0);
      $targetName = trim((string) ($row['target_display_name'] ?? '')) ?: ('#' . (int) ($row['target_user_id'] ?? 0));
      $reqName = trim((string) ($row['requester_display_name'] ?? '')) ?: 'Demandeur';
      $proposed = is_array($row['proposed'] ?? null) ? $row['proposed'] : [];
      $before = is_array($row['before'] ?? null) ? $row['before'] : [];
    ?>
    <article class="rounded-2xl border border-white/10 bg-slate-900/70 p-5 shadow-lg">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold text-white"><?= $h($targetName) ?></h2>
          <p class="text-xs text-slate-400">
            Demande #<?= $id ?> · par <?= $h($reqName) ?> · <?= $h((string) ($row['created_at'] ?? '')) ?>
          </p>
        </div>
        <a href="<?= $h(url('personnel/' . (int) ($row['target_user_id'] ?? 0))) ?>" class="text-xs font-semibold text-emerald-400 hover:text-emerald-300">Voir la fiche</a>
      </div>

      <?php if (trim((string) ($row['note'] ?? '')) !== ''): ?>
      <p class="mt-3 rounded-lg border border-white/5 bg-black/20 px-3 py-2 text-sm text-slate-300">
        <?= $h((string) $row['note']) ?>
      </p>
      <?php endif; ?>

      <ul class="mt-3 space-y-1 text-sm text-slate-300">
        <?php foreach ($proposed as $key => $newVal): ?>
        <?php
          $label = $fieldLabels[$key] ?? $key;
          $old = isset($before[$key]) ? trim((string) $before[$key]) : '';
          $new = trim((string) $newVal);
        ?>
        <li>
          <span class="font-semibold text-slate-100"><?= $h($label) ?></span> :
          <span class="text-slate-500"><?= $h($old !== '' ? $old : '—') ?></span>
          →
          <span class="text-emerald-300"><?= $h($new !== '' ? $new : '—') ?></span>
        </li>
        <?php endforeach; ?>
      </ul>

      <form method="post" action="<?= $h(url('back-office/personnel/corrections/' . $id . '/decide')) ?>" class="mt-4 flex flex-wrap items-end gap-3 border-t border-white/10 pt-4">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>" />
        <label class="min-w-[12rem] flex-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
          Commentaire (optionnel)
          <input type="text" name="resolution_note" maxlength="1000"
                 class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-2 text-sm normal-case tracking-normal text-white" />
        </label>
        <button type="submit" name="decision" value="approved"
                class="rounded-xl bg-emerald-500 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-slate-950 hover:bg-emerald-400">
          Confirmer
        </button>
        <button type="submit" name="decision" value="rejected"
                class="rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-rose-200 hover:bg-rose-500/20">
          Refuser
        </button>
      </form>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
