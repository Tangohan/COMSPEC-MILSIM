<?php
$base = url('');
$enrollments = $enrollments ?? [];
$trainingStats = $trainingStats ?? ['total' => 0, 'in_progress' => 0, 'assigned' => 0, 'completed' => 0, 'expiring_soon' => 0];
$trainingFilter = $trainingFilter ?? 'all';
$viewerName = \App\Core\Session::get('display_name') ?? \App\Core\Session::get('email') ?? '';

$statusLabel = static function (string $s): string {
    return match ($s) {
        'assigned' => 'Non démarré',
        'in_progress' => 'En cours',
        'completed' => 'Terminé',
        'revoked' => 'Révoqué',
        default => $s,
    };
};
$levelLabel = static function (?string $l): string {
    return match ($l ?? '') {
        'initiation' => 'Initiation',
        'intermediaire' => 'Intermédiaire',
        'avance' => 'Avancé',
        'expert' => 'Expert',
        default => '—',
    };
};
$statusStyles = static function (string $s): string {
    return match ($s) {
        'in_progress' => 'bg-sky-100 text-sky-900 ring-sky-200',
        'assigned' => 'bg-amber-50 text-amber-900 ring-amber-200',
        'completed' => 'bg-emerald-100 text-emerald-900 ring-emerald-200',
        'revoked' => 'bg-slate-200 text-slate-700 ring-slate-300',
        default => 'bg-slate-100 text-slate-800 ring-slate-200',
    };
};
$coverUrl = static function (array $e) use ($base): ?string {
    $p = trim((string) ($e['thumbnail_path'] ?? ''));
    if ($p === '') {
        $p = trim((string) ($e['banner_path'] ?? ''));
    }
    if ($p === '') {
        return null;
    }

    return $base . '/' . ltrim($p, '/');
};
?>
<div class="relative overflow-hidden bg-gradient-to-b from-emerald-950 via-slate-900 to-slate-950">
  <div class="pointer-events-none absolute inset-0 opacity-[0.07]" style="background-image:url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>

  <div class="relative mx-auto max-w-6xl px-4 pb-10 pt-8 sm:px-6 lg:px-8 lg:pb-14 lg:pt-12">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
      <div class="min-w-0">
        <p class="text-[10px] font-black uppercase tracking-[0.4em] text-emerald-400/90">Parcours apprenant</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-white sm:text-4xl">Mes formations</h1>
        <p class="mt-3 max-w-xl text-sm leading-relaxed text-slate-300">
          <?php if ($viewerName !== ''): ?>
            <span class="text-white/90"><?= htmlspecialchars($viewerName) ?></span> — reprenez vos modules, suivez la progression et récupérez vos attestations.
          <?php else: ?>
            Reprenez vos modules, suivez la progression et récupérez vos attestations.
          <?php endif; ?>
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="<?= $base ?>/formations" class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-[11px] font-black uppercase tracking-wider text-white backdrop-blur-sm transition hover:bg-white/15">
          Catalogue
        </a>
        <a href="<?= $base ?>/dashboard" class="inline-flex items-center justify-center rounded-xl border border-emerald-500/40 bg-emerald-500 px-4 py-2.5 text-[11px] font-black uppercase tracking-wider text-emerald-950 shadow-lg shadow-emerald-900/30 transition hover:bg-emerald-400">
          Dashboard
        </a>
      </div>
    </div>

    <dl class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:gap-4">
      <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4 backdrop-blur-md">
        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Parcours</dt>
        <dd class="mt-1 text-2xl font-black tabular-nums text-white"><?= (int) $trainingStats['total'] ?></dd>
      </div>
      <div class="rounded-2xl border border-sky-500/20 bg-sky-500/10 px-4 py-4 backdrop-blur-md">
        <dt class="text-[10px] font-bold uppercase tracking-wider text-sky-200/90">En cours</dt>
        <dd class="mt-1 text-2xl font-black tabular-nums text-sky-100"><?= (int) $trainingStats['in_progress'] ?></dd>
      </div>
      <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-4 backdrop-blur-md">
        <dt class="text-[10px] font-bold uppercase tracking-wider text-emerald-200/90">Terminées</dt>
        <dd class="mt-1 text-2xl font-black tabular-nums text-emerald-100"><?= (int) $trainingStats['completed'] ?></dd>
      </div>
      <div class="rounded-2xl border border-amber-500/25 bg-amber-500/10 px-4 py-4 backdrop-blur-md">
        <dt class="text-[10px] font-bold uppercase tracking-wider text-amber-100/90">Échéance sous 30 j.</dt>
        <dd class="mt-1 text-2xl font-black tabular-nums text-amber-50"><?= (int) $trainingStats['expiring_soon'] ?></dd>
      </div>
    </dl>
  </div>
</div>

<div class="mx-auto max-w-6xl px-4 pb-16 pt-8 sm:px-6 lg:px-8">
  <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-sm font-semibold text-slate-700">Filtrer l’affichage</p>
    <div class="flex flex-wrap gap-2">
      <?php
      $filters = [
          'all' => 'Tous',
          'active' => 'Actifs',
          'done' => 'Terminés',
      ];
      foreach ($filters as $key => $label):
          $on = $trainingFilter === $key;
          $href = $base . '/formations/mes-formations' . ($key === 'all' ? '' : '?filter=' . rawurlencode($key));
      ?>
        <a href="<?= htmlspecialchars($href) ?>"
           class="inline-flex items-center rounded-full border px-4 py-2 text-[11px] font-black uppercase tracking-wider transition <?= $on
               ? 'border-emerald-500 bg-emerald-50 text-emerald-900 shadow-sm'
               : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300' ?>">
          <?= htmlspecialchars($label) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($enrollments)): ?>
    <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-[0_20px_60px_-15px_rgba(15,23,42,0.12)]">
      <div class="grid gap-8 px-6 py-14 sm:px-10 md:grid-cols-[1fr_280px] md:items-center">
        <div>
          <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-slate-600">Aucun parcours</span>
          <h2 class="mt-4 text-2xl font-black text-slate-900">Explorez le catalogue</h2>
          <p class="mt-3 text-sm leading-relaxed text-slate-600">
            Vous n’avez pas encore de formation assignée, ou le filtre sélectionné ne renvoie aucun résultat. Les parcours attribués par votre organisation apparaîtront ici avec progression et échéances.
          </p>
          <div class="mt-8 flex flex-wrap gap-3">
            <a href="<?= $base ?>/formations" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-6 py-3 text-[11px] font-black uppercase tracking-wider text-white shadow-md transition hover:bg-emerald-600">
              Ouvrir le catalogue
            </a>
            <?php if ($trainingFilter !== 'all'): ?>
              <a href="<?= $base ?>/formations/mes-formations" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-3 text-[11px] font-black uppercase tracking-wider text-slate-800 hover:border-slate-300">
                Réinitialiser le filtre
              </a>
            <?php endif; ?>
          </div>
        </div>
        <div class="flex justify-center md:justify-end">
          <div class="relative flex h-48 w-48 items-center justify-center rounded-3xl bg-gradient-to-br from-emerald-100 to-slate-100 ring-1 ring-slate-200/80">
            <span class="text-6xl opacity-90" aria-hidden="true">📚</span>
            <div class="absolute -bottom-2 -right-2 rounded-2xl border border-white bg-white px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-500 shadow-md">
              LMS Athena
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <ul class="space-y-6">
      <?php foreach ($enrollments as $e):
          $st = (string) ($e['status'] ?? '');
          $pct = max(0, min(100, (int) ($e['progress_percent'] ?? 0)));
          $slug = (string) ($e['course_slug'] ?? '');
          $courseUrl = $slug !== '' ? $base . '/formations/' . rawurlencode($slug) : $base . '/formations';
          $img = $coverUrl($e);
          $certId = (int) ($e['certificate_id'] ?? 0);
          $isDone = $st === 'completed';
          $cta = $isDone ? 'Consulter' : ($st === 'assigned' ? 'Commencer' : 'Reprendre');
          ?>
        <li class="group overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm transition hover:shadow-md hover:border-emerald-200/80">
          <div class="flex flex-col lg:flex-row">
            <div class="relative h-44 shrink-0 overflow-hidden bg-slate-100 lg:h-auto lg:w-[min(100%,280px)]">
              <?php if ($img): ?>
                <img src="<?= htmlspecialchars($img) ?>" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy" />
              <?php else: ?>
                <div class="flex h-full min-h-[11rem] w-full items-center justify-center bg-gradient-to-br from-slate-800 to-slate-950">
                  <span class="text-4xl font-black text-white/25"><?= htmlspecialchars(mb_strtoupper(mb_substr((string) ($e['course_title'] ?? 'F'), 0, 1))) ?></span>
                </div>
              <?php endif; ?>
              <div class="absolute left-3 top-3 flex flex-wrap gap-2">
                <?php if (!empty($e['is_mandatory'])): ?>
                  <span class="rounded-lg bg-rose-600/95 px-2 py-1 text-[9px] font-black uppercase tracking-wider text-white shadow">Obligatoire</span>
                <?php endif; ?>
                <?php if (!empty($e['is_certifying'])): ?>
                  <span class="rounded-lg bg-emerald-600/95 px-2 py-1 text-[9px] font-black uppercase tracking-wider text-white shadow">Certifiant</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="flex min-w-0 flex-1 flex-col justify-between gap-6 p-6 sm:p-8">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider ring-1 <?= $statusStyles($st) ?>">
                    <?= htmlspecialchars($statusLabel($st)) ?>
                  </span>
                  <?php if (!empty($e['category'])): ?>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500"><?= htmlspecialchars((string) $e['category']) ?></span>
                  <?php endif; ?>
                  <span class="text-[10px] font-semibold text-slate-400"><?= htmlspecialchars($levelLabel($e['level'] ?? null)) ?></span>
                </div>
                <h2 class="mt-3 text-xl font-black leading-tight text-slate-900 sm:text-2xl">
                  <a href="<?= htmlspecialchars($courseUrl) ?>" class="hover:text-emerald-700"><?= htmlspecialchars((string) ($e['course_title'] ?? 'Formation')) ?></a>
                </h2>
                <?php if (!empty($e['short_description'])): ?>
                  <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-slate-600"><?= htmlspecialchars((string) $e['short_description']) ?></p>
                <?php endif; ?>

                <div class="mt-5">
                  <div class="flex items-center justify-between gap-2 text-[11px] font-bold text-slate-500">
                    <span>Progression</span>
                    <span class="tabular-nums text-slate-800"><?= $pct ?> %</span>
                  </div>
                  <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200/80">
                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 transition-all duration-500" style="width: <?= $pct ?>%"></div>
                  </div>
                </div>

                <dl class="mt-5 grid gap-3 text-[12px] text-slate-600 sm:grid-cols-2">
                  <div class="flex gap-2">
                    <dt class="font-semibold text-slate-400">Durée estimée</dt>
                    <dd><?= (int) ($e['estimated_minutes'] ?? 0) ?> min</dd>
                  </div>
                  <div class="flex gap-2">
                    <dt class="font-semibold text-slate-400">Assigné le</dt>
                    <dd><?= !empty($e['assigned_at']) ? date('d/m/Y', strtotime((string) $e['assigned_at'])) : '—' ?></dd>
                  </div>
                  <?php if (!empty($e['expires_at'])): ?>
                    <div class="flex gap-2 sm:col-span-2">
                      <dt class="font-semibold text-slate-400">Échéance</dt>
                      <dd class="<?= strtotime((string) $e['expires_at']) < strtotime('+30 days') && !$isDone ? 'font-bold text-amber-700' : '' ?>">
                        <?= date('d/m/Y', strtotime((string) $e['expires_at'])) ?>
                      </dd>
                    </div>
                  <?php endif; ?>
                </dl>
              </div>

              <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
                <a href="<?= htmlspecialchars($courseUrl) ?>" class="inline-flex flex-1 items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-center text-[11px] font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-emerald-600 sm:flex-none">
                  <?= htmlspecialchars($cta) ?>
                </a>
                <?php if ($isDone && $certId > 0 && !empty($e['is_certifying'])): ?>
                  <a href="<?= $base ?>/formations/certificate/<?= $certId ?>" class="inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-[11px] font-black uppercase tracking-wider text-emerald-900 transition hover:bg-emerald-100">
                    Attestation
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <aside class="mt-12 rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-5 py-5 sm:px-6">
    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500">Astuce</p>
    <p class="mt-2 text-sm text-slate-600">
      Les formations <strong class="text-slate-800">certifiantes</strong> délivrent une attestation une fois le parcours validé. Pensez à vérifier les <strong class="text-slate-800">échéances</strong> pour les modules obligatoires.
    </p>
  </aside>
</div>
