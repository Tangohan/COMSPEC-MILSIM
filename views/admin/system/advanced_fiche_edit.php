<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $activeGrants */
/** @var list<array<string,mixed>> $recentGrants */
/** @var list<array<string,mixed>> $searchResults */
/** @var string $searchQuery */
/** @var string $csrf */
/** @var int $durationHours */

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$durationHours = (int) ($durationHours ?? 24);
$pageUrl = url('admin/system/advanced-fiche-edit');
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$flashInfo = \App\Core\Session::getFlash('info');
?>
<div class="mx-auto max-w-5xl space-y-8 px-4 py-10 sm:px-6">
  <header class="space-y-2">
    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Administration plateforme</p>
    <h1 class="text-2xl font-black text-slate-900">Édition avancée de fiche</h1>
    <p class="max-w-3xl text-sm text-slate-600 leading-relaxed">
      Accordez à <strong class="font-semibold text-slate-800">n’importe quel compte</strong> (toutes communautés)
      le droit de modifier toute sa fiche personnel pendant
      <strong class="font-semibold text-violet-800"><?= (int) $durationHours ?> heures</strong>,
      à l’exception de l’identifiant Athena (immuable).
      Un bandeau violet s’affiche sur tout le site pour le bénéficiaire.
    </p>
    <a href="<?= $h(url('admin/users')) ?>" class="inline-block text-sm font-semibold text-emerald-800 hover:underline">← Comptes utilisateurs</a>
  </header>

  <?php if ($flashError): ?><p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"><?= $h((string) $flashError) ?></p><?php endif; ?>
  <?php if ($flashSuccess): ?><p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= $h((string) $flashSuccess) ?></p><?php endif; ?>
  <?php if ($flashInfo): ?><p class="rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-sm text-violet-900"><?= $h((string) $flashInfo) ?></p><?php endif; ?>

  <section class="rounded-xl border border-violet-200 bg-white p-5 shadow-sm">
    <h2 class="text-sm font-black uppercase tracking-wider text-violet-900">Activer pour un membre</h2>
    <form method="get" action="<?= $h($pageUrl) ?>" class="mt-4 flex flex-wrap gap-3">
      <label class="min-w-[16rem] flex-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
        Rechercher (nom, prénom, indicatif, e-mail, Athena, Steam…)
        <input type="search" name="q" value="<?= $h($searchQuery ?? '') ?>" minlength="2"
               placeholder="Au moins 2 caractères — toutes communautés"
               class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm normal-case tracking-normal text-slate-900 shadow-sm" />
      </label>
      <button type="submit" class="self-end rounded-xl bg-violet-600 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-white hover:bg-violet-500">
        Chercher
      </button>
    </form>

    <?php if (($searchQuery ?? '') !== '' && ($searchResults ?? []) === []): ?>
    <p class="mt-4 text-sm text-slate-500">Aucun compte trouvé pour « <?= $h($searchQuery) ?> ».</p>
    <?php endif; ?>

    <?php if (($searchResults ?? []) !== []): ?>
    <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-slate-50/80">
      <?php foreach ($searchResults as $u): ?>
      <?php
        $uid = (int) ($u['id'] ?? 0);
        $label = trim((string) ($u['display_name'] ?? '')) ?: ('#' . $uid);
        $cs = trim((string) ($u['callsign'] ?? ''));
        $ath = trim((string) ($u['athena_identifier'] ?? ''));
        $email = trim((string) ($u['email'] ?? ''));
        $tenant = trim((string) ($u['tenant_name'] ?? ''));
        $civil = trim(trim((string) ($u['first_name'] ?? '')) . ' ' . trim((string) ($u['last_name'] ?? '')));
      ?>
      <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div>
          <p class="font-semibold text-slate-900"><?= $h($label) ?></p>
          <p class="text-xs text-slate-500">
            <?php if ($cs !== ''): ?>Indicatif <?= $h($cs) ?> · <?php endif; ?>
            <?php if ($civil !== '' && strcasecmp($civil, $label) !== 0): ?><?= $h($civil) ?> · <?php endif; ?>
            <?php if ($ath !== ''): ?>Athena <?= $h($ath) ?> · <?php endif; ?>
            <?php if ($email !== ''): ?><?= $h($email) ?> · <?php endif; ?>
            <?php if ($tenant !== ''): ?><?= $h($tenant) ?> · <?php endif; ?>
            #<?= $uid ?>
          </p>
        </div>
        <form method="post" action="<?= $h(url('admin/system/advanced-fiche-edit/grant')) ?>" class="flex flex-wrap items-end gap-2">
          <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>" />
          <input type="hidden" name="user_id" value="<?= $uid ?>" />
          <label class="text-[10px] font-bold uppercase tracking-wide text-slate-500">
            Motif (optionnel)
            <input type="text" name="reason" maxlength="500"
                   class="mt-1 block w-48 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs normal-case tracking-normal text-slate-900" />
          </label>
          <button type="submit" class="rounded-xl bg-violet-600 px-3 py-2 text-[11px] font-black uppercase tracking-wider text-white hover:bg-violet-500">
            Activer <?= (int) $durationHours ?> h
          </button>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section>
    <h2 class="text-sm font-black uppercase tracking-wider text-emerald-800">Autorisations actives</h2>
    <?php if (($activeGrants ?? []) === []): ?>
    <p class="mt-3 rounded-xl border border-slate-200 bg-white px-5 py-6 text-sm text-slate-500 shadow-sm">Aucune autorisation en cours.</p>
    <?php else: ?>
    <div class="mt-3 space-y-3">
      <?php foreach ($activeGrants as $g): ?>
      <?php
        $gid = (int) ($g['id'] ?? 0);
        $name = trim((string) ($g['target_display_name'] ?? '')) ?: ('#' . (int) ($g['user_id'] ?? 0));
        $ends = (string) ($g['ends_at'] ?? '');
        $endsLabel = $ends !== '' ? date('d/m/Y H:i', strtotime($ends)) : '—';
        $tenant = trim((string) ($g['tenant_name'] ?? ''));
      ?>
      <article class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-violet-200 bg-white px-5 py-4 shadow-sm">
        <div>
          <h3 class="font-bold text-slate-900"><?= $h($name) ?></h3>
          <p class="text-xs text-slate-500">
            <?php if ($tenant !== ''): ?><?= $h($tenant) ?> · <?php endif; ?>
            Jusqu’au <?= $h($endsLabel) ?>
            · activé par <?= $h((string) ($g['granter_display_name'] ?? '—')) ?>
            <?php if (trim((string) ($g['reason'] ?? '')) !== ''): ?>
            · <?= $h((string) $g['reason']) ?>
            <?php endif; ?>
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <a href="<?= $h(url('personnel/' . (int) ($g['user_id'] ?? 0))) ?>" class="text-xs font-semibold text-emerald-700 hover:text-emerald-900">Fiche</a>
          <form method="post" action="<?= $h(url('admin/system/advanced-fiche-edit/' . $gid . '/revoke')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>" />
            <button type="submit" class="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-[11px] font-black uppercase tracking-wider text-rose-800 hover:bg-rose-100">
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
    <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Historique récent</h2>
    <?php if (($recentGrants ?? []) === []): ?>
    <p class="mt-3 text-sm text-slate-500">Pas encore d’historique.</p>
    <?php else: ?>
    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
      <table class="min-w-full text-left text-xs text-slate-600">
        <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
          <tr>
            <th class="px-3 py-2">Membre</th>
            <th class="px-3 py-2">Communauté</th>
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
            $statusClass = $revoked ? 'text-rose-700' : ($ended ? 'text-slate-500' : 'text-violet-700');
          ?>
          <tr class="border-t border-slate-100">
            <td class="px-3 py-2 font-medium text-slate-900"><?= $h($name) ?></td>
            <td class="px-3 py-2"><?= $h((string) ($g['tenant_name'] ?? '—')) ?></td>
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
