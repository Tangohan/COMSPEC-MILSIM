<?php
declare(strict_types=1);

$hubSections = $hubSections ?? [];
$hub_next_steps = $hub_next_steps ?? [];

$hubIconPaths = [
    'dashboard' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
    'activity' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
    'forum' => 'M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
    'messages' => 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75',
    'search' => 'm21 21-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z',
    'courrier' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    'personnel' => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
    'pointage' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5a2.25 2.25 0 002.25-2.25m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5a2.25 2.25 0 012.25 2.25v7.5',
    'orbat' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008H17.25v-.008zm0 3h.008v.008H17.25V18zm0 3h.008v.008H17.25v-.008z',
    'atak' => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
    'equipment' => 'm21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5v9l9 5.25M3 7.5l9 5.25m0-9v9',
    'training' => 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5',
    'rh_hub' => 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z',
    'documents' => 'M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z',
    'documents_admin' => 'M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z',
    'admin_platform' => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
    'admin_org' => 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9.75h18M3.75 21h16.5a.75.75 0 0 0 .75-.75V4.5a.75.75 0 0 0-.75-.75H3.75A.75.75 0 0 0 3 4.5v15.75a.75.75 0 0 0 .75.75Z',
    '_default' => 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z',
];

$hubVisibleSections = [];
foreach ($hubSections as $section) {
    $entries = $section['entries'] ?? [];
    if (!is_array($entries) || $entries === []) {
        continue;
    }
    $hubVisibleSections[] = $section;
}

$featuredEntries = [];
foreach ($hubVisibleSections as $section) {
    foreach ($section['entries'] ?? [] as $entry) {
        if (!empty($entry['featured'])) {
            $featuredEntries[] = $entry;
        }
    }
}
$featuredEntries = array_slice($featuredEntries, 0, 4);
?>

<style>
  .hub-portal { --hub-accent: #059669; --hub-ink: #0f172a; }
  .hub-portal a:focus-visible { outline: 2px solid var(--hub-accent); outline-offset: 2px; }
  .hub-jump a {
    display: inline-flex; align-items: center; border-radius: 0.5rem;
    border: 1px solid #e2e8f0; background: #fff; padding: 0.35rem 0.7rem;
    font-size: 0.75rem; font-weight: 700; color: #334155; text-decoration: none;
    transition: border-color .12s ease, color .12s ease, background .12s ease;
  }
  .hub-jump a:hover { border-color: #6ee7b7; background: #ecfdf5; color: #047857; }
  .hub-quick {
    display: flex; align-items: center; gap: 0.65rem;
    border-radius: 0.65rem; border: 1px solid #a7f3d0; background: #ecfdf5;
    padding: 0.55rem 0.75rem; text-decoration: none; color: inherit;
    transition: border-color .12s ease, background .12s ease;
  }
  .hub-quick:hover { border-color: #059669; background: #d1fae5; }
  .hub-quick__icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.85rem; height: 1.85rem; flex-shrink: 0; border-radius: 0.45rem;
    background: #059669; color: #fff;
  }
  .hub-quick__icon svg { width: 1rem; height: 1rem; }
  .hub-section {
    border: 1px solid #e2e8f0; border-radius: 0.75rem; background: #fff;
    overflow: hidden;
  }
  .hub-section__head {
    border-bottom: 1px solid #e2e8f0; background: #f8fafc;
    padding: 0.65rem 0.85rem;
  }
  .hub-link {
    display: flex; align-items: flex-start; gap: 0.7rem;
    padding: 0.65rem 0.85rem; text-decoration: none; color: inherit;
    border-top: 1px solid #f1f5f9;
    transition: background .12s ease;
  }
  .hub-link:first-child { border-top: 0; }
  .hub-link:hover { background: #f0fdf4; }
  .hub-link__icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.75rem; height: 1.75rem; flex-shrink: 0; margin-top: 0.05rem;
    border-radius: 0.4rem; background: #ecfdf5; color: #059669;
  }
  .hub-link__icon svg { width: 0.95rem; height: 0.95rem; }
  .hub-link--featured .hub-link__icon { background: #059669; color: #fff; }
</style>

<div class="hub-portal mx-auto max-w-6xl px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
  <header class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-end sm:justify-between sm:gap-4">
    <div class="min-w-0">
      <p class="text-[10px] font-black uppercase tracking-[0.22em] text-[#059669]">Athena</p>
      <h1 class="mt-1 text-xl font-black tracking-tight text-slate-900 sm:text-2xl">
        Centre de commandement
      </h1>
      <p class="mt-1 max-w-2xl text-sm text-slate-600">
        Annuaire des espaces Athena : ouvrez l’outil dont vous avez besoin.
      </p>
    </div>
    <div class="flex shrink-0 flex-wrap gap-2">
      <a
        href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>"
        class="inline-flex items-center gap-1.5 rounded-lg bg-[#059669] px-3.5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700"
      >
        Tableau de bord
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
      </a>
      <a
        href="<?= htmlspecialchars(url('centre-actions'), ENT_QUOTES, 'UTF-8') ?>"
        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-bold text-slate-800 transition hover:border-emerald-300 hover:bg-emerald-50"
      >
        Ce qui demande attention
      </a>
    </div>
  </header>

  <?php if ($hubVisibleSections !== []): ?>
  <nav class="hub-jump mt-4 flex flex-wrap gap-1.5" aria-label="Rubriques">
    <?php foreach ($hubVisibleSections as $nav): ?>
    <a href="#<?= htmlspecialchars((string) ($nav['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars((string) ($nav['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>

  <?php if ($featuredEntries !== []): ?>
  <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4" aria-label="Accès fréquents">
    <?php foreach ($featuredEntries as $entry):
      $iconKey = (string) ($entry['icon'] ?? '');
      $pathD = $hubIconPaths[$iconKey] ?? $hubIconPaths['_default'];
    ?>
    <a href="<?= htmlspecialchars((string) ($entry['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="hub-quick">
      <span class="hub-quick__icon" aria-hidden="true">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= htmlspecialchars($pathD, ENT_QUOTES, 'UTF-8') ?>"/></svg>
      </span>
      <span class="min-w-0">
        <span class="block truncate text-sm font-bold text-slate-900"><?= htmlspecialchars((string) ($entry['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="mt-0.5 block truncate text-[11px] text-slate-600"><?= htmlspecialchars((string) ($entry['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($hubVisibleSections !== []): ?>
  <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($hubVisibleSections as $section):
      $entries = $section['entries'] ?? [];
    ?>
    <section
      id="<?= htmlspecialchars((string) ($section['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
      class="hub-section scroll-mt-20"
      aria-labelledby="hub-sec-<?= htmlspecialchars((string) ($section['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
    >
      <header class="hub-section__head">
        <h2
          id="hub-sec-<?= htmlspecialchars((string) ($section['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
          class="text-sm font-black text-slate-900"
        ><?= htmlspecialchars((string) ($section['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if (!empty($section['subtitle'])): ?>
        <p class="mt-0.5 text-xs leading-snug text-slate-500"><?= htmlspecialchars((string) $section['subtitle'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
      </header>
      <ul>
        <?php foreach ($entries as $entry):
          $iconKey = (string) ($entry['icon'] ?? '');
          $pathD = $hubIconPaths[$iconKey] ?? $hubIconPaths['_default'];
          $isFeatured = !empty($entry['featured']);
        ?>
        <li>
          <a
            href="<?= htmlspecialchars((string) ($entry['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>"
            class="hub-link<?= $isFeatured ? ' hub-link--featured' : '' ?>"
          >
            <span class="hub-link__icon" aria-hidden="true">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= htmlspecialchars($pathD, ENT_QUOTES, 'UTF-8') ?>"/></svg>
            </span>
            <span class="min-w-0 flex-1">
              <span class="flex flex-wrap items-center gap-1.5">
                <span class="text-sm font-bold text-slate-900"><?= htmlspecialchars((string) ($entry['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($entry['badge'])): ?>
                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600"><?= htmlspecialchars((string) $entry['badge'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </span>
              <span class="mt-0.5 block text-xs leading-snug text-slate-600"><?= htmlspecialchars((string) ($entry['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
    Aucun accès n’est disponible pour le moment. Contactez un responsable de la communauté si vous pensez qu’il s’agit d’une erreur.
  </p>
  <?php endif; ?>

  <?php
  $next_steps = $hub_next_steps;
  $next_steps_title = 'Suggestions selon votre profil';
  $next_steps_intro = 'Quelques pistes utiles pour enchaîner, selon les modules auxquels vous avez accès.';
  require base_path('views/partials/ui/next_steps_block.php');
  ?>
</div>
