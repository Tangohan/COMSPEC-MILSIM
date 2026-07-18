<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $openings */
/** @var string $statusFilter */
/** @var array<string,string> $statusLabels */
/** @var string $publicOffersVitrineUrl */
$openings = $openings ?? [];
$statusFilter = $statusFilter ?? 'all';
$statusLabels = is_array($statusLabels ?? null) ? $statusLabels : [];
$publicOffersVitrineUrl = trim((string) ($publicOffersVitrineUrl ?? ''));
$hasVitrinePreview = $publicOffersVitrineUrl !== '';
?>
<div class="w-full max-w-[1600px] space-y-6" data-offers-admin-split>
    <div class="lms-panel rounded-[2rem] p-6 md:p-8 border border-slate-200/80 overflow-hidden relative">
        <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-emerald-500/80 via-emerald-500/20 to-transparent" aria-hidden="true"></div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[9px] font-black tracking-[0.45em] text-emerald-700 uppercase mb-2">Vitrine</p>
                <h1 class="text-2xl md:text-3xl font-black uppercase tracking-tight text-slate-900">Offres publiées</h1>
                <p class="mt-2 text-sm text-slate-600 max-w-2xl leading-relaxed">Avis de vacance affichés sur la vitrine de votre communauté (mise en page « prospection »).</p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <a href="<?= htmlspecialchars(url('back-office/recruitment/reference-format'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Format des références</a>
                <a href="<?= htmlspecialchars(url('back-office/recruitment/offers/create'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Nouvelle offre</a>
            </div>
        </div>
    </div>

    <?php if ($hasVitrinePreview): ?>
    <div class="lg:hidden flex rounded-2xl border border-slate-200/80 bg-white p-1 shadow-sm" role="tablist" aria-label="Affichage de la page">
        <button type="button" role="tab" id="offers-tab-manage" aria-selected="true" aria-controls="offers-panel-manage" data-offers-tab="manage"
            class="offers-mobile-tab flex-1 rounded-xl px-3 py-2.5 text-sm font-semibold transition bg-slate-900 text-white shadow-sm">
            Gestion
        </button>
        <button type="button" role="tab" id="offers-tab-preview" aria-selected="false" aria-controls="offers-panel-preview" data-offers-tab="preview"
            class="offers-mobile-tab flex-1 rounded-xl px-3 py-2.5 text-sm font-semibold transition text-slate-600 hover:text-slate-900">
            Aperçu vitrine
        </button>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 <?= $hasVitrinePreview ? 'lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] xl:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] gap-6 lg:gap-8 lg:items-start' : '' ?>">
        <div id="offers-panel-manage" role="tabpanel" aria-labelledby="offers-tab-manage" data-offers-panel="manage" class="min-w-0 space-y-6">
            <form method="get" action="<?= htmlspecialchars(url('back-office/recruitment/offers'), ENT_QUOTES, 'UTF-8') ?>" class="lms-panel rounded-2xl p-4 md:p-5 border border-slate-200/80 flex flex-wrap items-center gap-3">
                <label class="text-sm font-medium text-slate-700">Filtrer</label>
                <select name="status" class="<?= htmlspecialchars(bo_select_class('min-w-[11rem] sm:min-w-[13rem]'), ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                    <?php foreach ($statusLabels as $k => $lab): ?>
                        <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <div class="lms-panel rounded-[2rem] border border-slate-200/80 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Titre</th>
                            <th class="px-4 py-3">Unité</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3">Référence</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($openings === []): ?>
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Aucune offre pour l’instant.</td></tr>
                        <?php else: ?>
                            <?php foreach ($openings as $o): ?>
                                <?php
                                $st = (string) ($o['status'] ?? '');
                                $stLab = $statusLabels[$st] ?? $st;
                                ?>
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars((string) ($o['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars((string) ($o['unit_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700"><?= htmlspecialchars($stLab, ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= htmlspecialchars((string) ($o['reference_public'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <?php if ($st === 'draft'): ?>
                                            <a href="<?= htmlspecialchars(url('back-office/recruitment/offers/' . (int) ($o['id'] ?? 0) . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="text-sky-700 font-semibold hover:underline">Modifier</a>
                                            <form action="<?= htmlspecialchars(url('back-office/recruitment/offers/' . (int) ($o['id'] ?? 0) . '/publish'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-2 inline-block max-w-[220px] text-left align-top">
                                                <?= \App\Core\Csrf::field() ?>
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 mb-1.5">À la publication</p>
                                                <label class="flex items-start gap-2 text-xs text-slate-700 cursor-pointer mb-1.5 leading-snug">
                                                    <input type="checkbox" name="forum_annonce_generale" value="1" class="mt-0.5 rounded border-slate-300">
                                                    <span>Publier aussi une annonce dans le <strong>forum général</strong> (recrutement visible par toute la communauté).</span>
                                                </label>
                                                <label class="flex items-start gap-2 text-xs text-slate-700 cursor-pointer mb-2 leading-snug">
                                                    <input type="checkbox" name="forum_annonce_organisation" value="1" class="mt-0.5 rounded border-slate-300">
                                                    <span>Publier aussi une annonce dans l’<strong>espace réservé à l’organisation</strong> (membres et encadrement).</span>
                                                </label>
                                                <button type="submit" class="text-emerald-700 font-semibold hover:underline text-sm">Publier l’offre</button>
                                            </form>
                                        <?php elseif ($st === 'published'): ?>
                                            <form action="<?= htmlspecialchars(url('back-office/recruitment/offers/' . (int) ($o['id'] ?? 0) . '/close'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="inline">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="text-amber-800 font-semibold hover:underline">Clôturer</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>

        <?php if ($hasVitrinePreview): ?>
        <aside id="offers-panel-preview" role="tabpanel" aria-labelledby="offers-tab-preview" data-offers-panel="preview" class="min-w-0 hidden lg:block lg:sticky lg:top-6">
            <div class="lms-panel rounded-[2rem] border border-slate-200/80 overflow-hidden shadow-sm flex flex-col h-[min(78vh,820px)] lg:h-[calc(100vh-7.5rem)]">
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-slate-200/80 bg-emerald-50/40 shrink-0">
                    <div>
                        <p class="text-[9px] font-black tracking-[0.35em] text-emerald-700 uppercase">Aperçu</p>
                        <p class="text-sm font-semibold text-slate-800">Vitrine publique — section offres</p>
                    </div>
                    <a href="<?= htmlspecialchars($publicOffersVitrineUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200/80 bg-white px-3 py-2 text-xs font-semibold text-emerald-900 hover:bg-emerald-50 shrink-0">
                        Ouvrir dans un nouvel onglet
                        <span class="text-emerald-600/70" aria-hidden="true">↗</span>
                    </a>
                </div>
                <iframe
                    title="Aperçu de la vitrine publique des offres"
                    src="<?= htmlspecialchars($publicOffersVitrineUrl, ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full flex-1 min-h-0 border-0 bg-white"
                    loading="lazy"
                    referrerpolicy="same-origin"
                ></iframe>
            </div>
            <p class="mt-3 text-xs text-slate-500 leading-relaxed">L’aperçu reprend la page publique de votre communauté, positionnée sur le tableau des offres (prospection). Les modifications publiées s’y reflètent après rechargement.</p>
        </aside>
        <?php endif; ?>
    </div>
</div>
<?php if ($hasVitrinePreview): ?>
<script>
(function () {
  var root = document.querySelector('[data-offers-admin-split]');
  if (!root) return;
  var tabs = root.querySelectorAll('[data-offers-tab]');
  var panels = {
    manage: root.querySelector('[data-offers-panel="manage"]'),
    preview: root.querySelector('[data-offers-panel="preview"]')
  };
  function isMobile() {
    return window.matchMedia('(max-width: 1023px)').matches;
  }
  var activeClasses = ['bg-slate-900', 'text-white', 'shadow-sm'];
  var idleClasses = ['text-slate-600', 'hover:text-slate-900'];
  function setTab(name) {
    tabs.forEach(function (btn) {
      var on = btn.getAttribute('data-offers-tab') === name;
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
      activeClasses.forEach(function (c) { btn.classList.toggle(c, on); });
      idleClasses.forEach(function (c) { btn.classList.toggle(c, !on); });
    });
    if (!isMobile()) {
      if (panels.manage) panels.manage.classList.remove('hidden');
      if (panels.preview) panels.preview.classList.remove('hidden');
      return;
    }
    Object.keys(panels).forEach(function (key) {
      var el = panels[key];
      if (!el) return;
      if (key === name) {
        el.classList.remove('hidden');
      } else {
        el.classList.add('hidden');
      }
    });
  }
  tabs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      setTab(btn.getAttribute('data-offers-tab') || 'manage');
    });
  });
  window.addEventListener('resize', function () {
    var selected = root.querySelector('[data-offers-tab][aria-selected="true"]');
    setTab(selected ? (selected.getAttribute('data-offers-tab') || 'manage') : 'manage');
  });
  setTab('manage');
})();
</script>
<?php endif; ?>
