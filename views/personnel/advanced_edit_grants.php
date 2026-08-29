<?php
/** @var list<array<string,mixed>> $activeGrants */
/** @var list<array<string,mixed>> $recentGrants */
/** @var list<array<string,mixed>> $searchResults */
/** @var string $searchQuery */
/** @var string $csrf */
/** @var int $durationHours */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$durationHours = (int) ($durationHours ?? 24);
?>
<div class="mx-auto max-w-5xl space-y-8 px-4 py-8">
  <div>
    <p class="text-[10px] font-black uppercase tracking-[0.28em] text-violet-300/90">Ressources humaines</p>
    <h1 class="mt-1 text-2xl font-black text-white">Édition avancée de fiche</h1>
    <p class="mt-2 max-w-3xl text-sm text-slate-400">
      Accordez à un membre le droit de modifier <strong class="text-slate-200">toute sa fiche personnel</strong>
      pendant <strong class="text-violet-200"><?= (int) $durationHours ?> heures</strong>,
      à l’exception de l’identifiant Athena (immuable).
      Un bandeau violet s’affiche sur tout le site pour le bénéficiaire.
    </p>
  </div>

  <section class="rounded-2xl border border-violet-400/25 bg-violet-950/40 p-5 shadow-lg">
    <h2 class="text-sm font-black uppercase tracking-wider text-violet-100">Activer pour un membre</h2>
    <form method="get" action="<?= $h(url('back-office/personnel/advanced-edit')) ?>" class="mt-4 flex flex-wrap gap-3">
      <label class="min-w-[14rem] flex-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
        Rechercher (nom, indicatif, ID Athena)
        <input type="search" name="q" value="<?= $h($searchQuery ?? '') ?>" minlength="2"
               placeholder="Au moins 2 caractères…"
               class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-2 text-sm normal-case tracking-normal text-white" />
      </label>
      <button type="submit" class="self-end rounded-xl bg-violet-500 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-white hover:bg-violet-400">
        Chercher
      </button>
    </form>

    <?php if (($searchQuery ?? '') !== '' && ($searchResults ?? []) === []): ?>
    <p class="mt-4 text-sm text-slate-400">Aucun membre trouvé pour « <?= $h($searchQuery) ?> ».</p>
    <?php endif; ?>

    <?php if (($searchResults ?? []) !== []): ?>
    <ul class="mt-4 divide-y divide-white/10 rounded-xl border border-white/10 bg-black/20">
      <?php foreach ($searchResults as $u): ?>
      <?php
        $uid = (int) ($u['id'] ?? 0);
        $label = trim((string) ($u['display_name'] ?? '')) ?: ('#' . $uid);
        $cs = trim((string) ($u['callsign'] ?? ''));
        $ath = trim((string) ($u['athena_identifier'] ?? ''));
      ?>
      <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div>
          <p class="font-semibold text-white"><?= $h($label) ?></p>
          <p class="text-xs text-slate-400">
            <?php if ($cs !== ''): ?>Indicatif <?= $h($cs) ?> · <?php endif; ?>
            <?php if ($ath !== ''): ?>Athena <?= $h($ath) ?> · <?php endif; ?>
            #<?= $uid ?>
          </p>
        </div>
        <form method="post" action="<?= $h(url('back-office/personnel/advanced-edit/grant')) ?>" class="flex flex-wrap items-end gap-2">
          <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>" />
          <input type="hidden" name="user_id" value="<?= $uid ?>" />
          <label class="text-[10px] font-bold uppercase tracking-wide text-slate-500">
            Motif (optionnel)
            <input type="text" name="reason" maxlength="500"
                   class="mt-1 block w-48 rounded-lg border border-white/10 bg-slate-950/80 px-2 py-1.5 text-xs normal-case tracking-normal text-white" />
          </label>
          <button type="submit" class="rounded-xl bg-violet-500 px-3 py-2 text-[11px] font-black uppercase tracking-wider text-white hover:bg-violet-400">
            Activer <?= (int) $durationHours ?> h
          </button>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section>
    <h2 class="text-sm font-black uppercase tracking-wider text-emerald-300/90">Autorisations actives</h2>
    <?php if (($activeGrants ?? []) === []): ?>
    <p class="mt-3 rounded-xl border border-white/10 bg-slate-900/60 px-5 py-6 text-sm text-slate-400">Aucune autorisation en cours.</p>
    <?php else: ?>
    <div class="mt-3 space-y-3">
      <?php foreach ($activeGrants as $g): ?>
      <?php
        $gid = (int) ($g['id'] ?? 0);
        $name = trim((string) ($g['target_display_name'] ?? '')) ?: ('#' . (int) ($g['user_id'] ?? 0));
        $ends = (string) ($g['ends_at'] ?? '');
        $endsLabel = $ends !== '' ? date('d/m/Y H:i', strtotime($ends)) : '—';
      ?>
      <article class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-violet-400/20 bg-slate-900/70 px-5 py-4">
        <div>
          <h3 class="font-bold text-white"><?= $h($name) ?></h3>
          <p class="text-xs text-slate-400">
            Jusqu’au <?= $h($endsLabel) ?>
            · activé par <?= $h((string) ($g['granter_display_name'] ?? '—')) ?>
            <?php if (trim((string) ($g['reason'] ?? '')) !== ''): ?>
            · <?= $h((string) $g['reason']) ?>
            <?php endif; ?>
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <a href="<?= $h(url('personnel/' . (int) ($g['user_id'] ?? 0))) ?>" class="text-xs font-semibold text-emerald-400 hover:text-emerald-300">Fiche</a>
          <form method="post" action="<?= $h(url('back-office/personnel/advanced-edit/' . $gid . '/revoke')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>" />
            <button type="submit" class="rounded-xl border border-rose-400/40 bg-rose-500/10 px-3 py-2 text-[11px] font-black uppercase tracking-wider text-rose-200 hover:bg-rose-500/20">
              Révoquer
            </button>
          </form>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section>
    <h2 class="text-sm font-black uppercase tracking-wider text-slate-400">Historique récent</h2>
    <?php if (($recentGrants ?? []) === []): ?>
    <p class="mt-3 text-sm text-slate-500">Pas encore d’historique.</p>
    <?php else: ?>
    <div class="mt-3 overflow-x-auto rounded-xl border border-white/10">
      <table class="min-w-full text-left text-xs text-slate-300">
        <thead class="bg-black/30 text-[10px] uppercase tracking-wider text-slate-500">
          <tr>
            <th class="px-3 py-2">Membre</th>
            <th class="px-3 py-2">Période</th>
            <th class="px-3 py-2">Statut</th>
            <th class="px-3 py-2">Par</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentGrants as $g): ?>
          <?php
            $name = trim((string) ($g['target_display_name'] ?? '')) ?: ('#' . (int) ($g['user_id'] ?? 0));
            $revoked = !empty($g['revoked_at']);
            $ended = !$revoked && strtotime((string) ($g['ends_at'] ?? '')) <= time();
            $status = $revoked ? 'Révoqué' : ($ended ? 'Expiré' : 'Actif');
            $statusClass = $revoked ? 'text-rose-300' : ($ended ? 'text-slate-400' : 'text-violet-300');
          ?>
          <tr class="border-t border-white/5">
            <td class="px-3 py-2 font-medium text-white"><?= $h($name) ?></td>
            <td class="px-3 py-2 whitespace-nowrap">
              <?= $h((string) ($g['starts_at'] ?? '')) ?> → <?= $h((string) ($g['ends_at'] ?? '')) ?>
            </td>
            <td class="px-3 py-2 <?= $statusClass ?>"><?= $h($status) ?></td>
            <td class="px-3 py-2"><?= $h((string) ($g['granter_display_name'] ?? '—')) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
</div>
