<?php
/** @var array<string,mixed> $targetUser */
/** @var array<string,mixed> $snapshot */
/** @var array<string,string> $fieldLabels */
/** @var list<array<string,mixed>> $pending */
/** @var bool $hasOpen */
/** @var bool $isSelf */
/** @var string $csrf */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$targetId = (int) ($targetUser['id'] ?? 0);
$displayName = trim((string) ($targetUser['display_name'] ?? '')) ?: 'Opérateur';
?>
<div class="mx-auto max-w-3xl space-y-6 px-4 py-8">
  <div>
    <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-400/90">Anomalie fiche</p>
    <h1 class="mt-1 text-2xl font-black text-white">Correction RH</h1>
    <p class="mt-2 text-sm text-slate-400">
      Proposez des corrections sur <?= $isSelf ? 'votre fiche' : 'la fiche de <strong class="text-slate-200">' . $h($displayName) . '</strong>' ?>.
      Chaque modification part en validation auprès d’un organisateur : un e-mail récapitulatif est envoyé aux deux parties, et la fiche n’est mise à jour qu’après confirmation.
    </p>
  </div>

  <?php if ($hasOpen): ?>
  <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
    Une demande est déjà en attente pour cette fiche. Attendez la décision avant d’en envoyer une nouvelle.
  </div>
  <?php endif; ?>

  <form method="post" action="<?= $h(url('personnel/' . $targetId . '/correction')) ?>" class="space-y-5 rounded-2xl border border-white/10 bg-slate-900/70 p-5 shadow-xl">
    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>" />

    <div class="grid gap-4 sm:grid-cols-2">
      <?php foreach ($fieldLabels as $key => $label): ?>
      <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">
        <?= $h($label) ?>
        <?php if ($key === 'enlistment_date'): ?>
        <input type="date" name="<?= $h($key) ?>" value="<?= $h($snapshot[$key] ?? '') ?>"
               class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white" <?= $hasOpen ? 'disabled' : '' ?> />
        <?php elseif ($key === 'weight_kg'): ?>
        <input type="number" name="<?= $h($key) ?>" min="20" max="300" value="<?= $h($snapshot[$key] ?? '') ?>"
               class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white" <?= $hasOpen ? 'disabled' : '' ?> />
        <?php else: ?>
        <input type="text" name="<?= $h($key) ?>" value="<?= $h($snapshot[$key] ?? '') ?>"
               class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white" <?= $hasOpen ? 'disabled' : '' ?> />
        <?php endif; ?>
      </label>
      <?php endforeach; ?>
    </div>

    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">
      Message pour l’organisateur (optionnel)
      <textarea name="note" rows="3" maxlength="1000"
                class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white"
                placeholder="Précisez le contexte de l’anomalie…" <?= $hasOpen ? 'disabled' : '' ?>></textarea>
    </label>

    <div class="flex flex-wrap items-center gap-3">
      <button type="submit" class="rounded-xl bg-emerald-500 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-slate-950 transition hover:bg-emerald-400 disabled:opacity-40"
              <?= $hasOpen ? 'disabled' : '' ?>>
        Envoyer pour confirmation
      </button>
      <a href="<?= $h(url('personnel/' . $targetId)) ?>" class="text-xs font-semibold text-slate-400 hover:text-white">Retour à la fiche</a>
    </div>
  </form>

  <?php if ($pending !== []): ?>
  <section class="rounded-2xl border border-white/10 bg-slate-900/50 p-5">
    <h2 class="text-sm font-black uppercase tracking-wider text-slate-300">Historique récent</h2>
    <ul class="mt-3 space-y-2 text-sm text-slate-400">
      <?php foreach ($pending as $row): ?>
      <li class="flex flex-wrap items-baseline justify-between gap-2 border-b border-white/5 pb-2">
        <span>
          #<?= (int) ($row['id'] ?? 0) ?> —
          <span class="font-semibold text-slate-200"><?= $h((string) ($row['status'] ?? '')) ?></span>
        </span>
        <span class="text-xs"><?= $h((string) ($row['created_at'] ?? '')) ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</div>
