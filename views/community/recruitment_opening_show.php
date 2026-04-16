<?php
declare(strict_types=1);
/** @var array<string,mixed> $tenant */
/** @var array<string,mixed> $opening */
/** @var string $jobRoleName */
/** @var list<array<string,mixed>> $relatedOpenings */
/** @var bool $printMode */
/** @var bool $communityLocked */
$tenant = $tenant ?? [];
$opening = $opening ?? [];
$jobRoleName = trim((string) ($jobRoleName ?? ''));
$relatedOpenings = is_array($relatedOpenings ?? null) ? $relatedOpenings : [];
$printMode = !empty($printMode);
$communityLocked = !empty($communityLocked);
$slug = (string) ($tenant['slug'] ?? '');
$name = (string) ($tenant['name'] ?? 'Communauté');
$tSlug = $slug;

$decodeJson = static function (?string $raw): array {
    if ($raw === null || $raw === '') {
        return [];
    }
    $d = json_decode($raw, true);

    return is_array($d) ? $d : [];
};
$profiles = $decodeJson(isset($opening['candidate_profile_items']) && is_string($opening['candidate_profile_items']) ? $opening['candidate_profile_items'] : null);
if ($profiles === [] && isset($opening['candidate_profile_items']) && is_array($opening['candidate_profile_items'])) {
    $profiles = $opening['candidate_profile_items'];
}
$blocks = $decodeJson(isset($opening['responsibility_blocks']) && is_string($opening['responsibility_blocks']) ? $opening['responsibility_blocks'] : null);
if ($blocks === [] && isset($opening['responsibility_blocks']) && is_array($opening['responsibility_blocks'])) {
    $blocks = $opening['responsibility_blocks'];
}

$pc = \App\Services\Recruitment\RecruitmentOpeningPresentation::personnelCategoryLabel((string) ($opening['personnel_category'] ?? 'other'));
$arm = \App\Services\Recruitment\RecruitmentOpeningPresentation::armDomainLabel(isset($opening['arm_domain']) ? (string) $opening['arm_domain'] : null);
$clr = \App\Services\Recruitment\RecruitmentOpeningPresentation::clearanceLabel((string) ($opening['clearance_level'] ?? 'none'));
$ref = (string) ($opening['reference_public'] ?? '');
$unitName = (string) ($opening['unit_name'] ?? '');
$title = (string) ($opening['title'] ?? '');
$h1 = $title;
if ($jobRoleName !== '') {
    $h1 .= ' (' . $jobRoleName . ')';
}
$bandeau = $ref !== '' ? 'Réf. ' . $ref . ($arm !== '—' ? ' // ' . $arm : '') : ($arm !== '—' ? $arm : 'Avis de vacance');
$enlistUrl = url('c/' . rawurlencode($slug) . '/enlistment?ouverture=' . (int) ($opening['id'] ?? 0));
?>
<style>
  .recruitment-opening-grain::before {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    opacity: .035;
    background-image: radial-gradient(circle at 20% 20%, #000 0.5px, transparent 0.6px), radial-gradient(circle at 80% 70%, #000 0.5px, transparent 0.6px);
    background-size: 18px 18px;
  }
  .recruitment-panneau-tactique { box-shadow: 0 24px 80px -40px rgba(15,23,42,0.2); }
  @media print {
    .portal-nav, [data-portal-nav], .no-print { display: none !important; }
    body { background: #fff !important; }
    .recruitment-print-main { padding-top: 0 !important; }
  }
</style>
<div class="recruitment-print-main bg-slate-50 min-h-screen relative recruitment-opening-grain">
  <nav class="no-print h-16 border-b border-slate-200 flex items-center bg-white/90 backdrop-blur-md sticky top-0 z-50 px-4 sm:px-6 md:px-10 lg:px-12">
    <div class="flex items-center gap-4 text-[10px] font-black uppercase tracking-widest max-w-7xl mx-auto w-full min-w-0">
      <a href="<?= htmlspecialchars(url('c/' . rawurlencode($slug)), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-400 hover:text-slate-900 transition-colors italic"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></a>
      <span class="text-slate-300">/</span>
      <span class="text-blue-700">Avis de vacance de poste</span>
    </div>
  </nav>

  <main class="max-w-7xl mx-auto w-full px-5 pb-10 pt-6 sm:px-8 sm:pb-12 sm:pt-8 md:px-12 md:pb-14 md:pt-10 lg:px-14 lg:pt-12">
    <div class="grid grid-cols-12 gap-6 sm:gap-8 lg:gap-10">
      <section class="col-span-12 lg:col-span-9">
        <div class="recruitment-panneau-tactique rounded-xl border border-slate-200 border-t-[6px] border-t-blue-700 overflow-hidden relative bg-white shadow-sm">
          <div class="absolute top-8 right-6 sm:top-10 sm:right-8 md:top-12 md:right-10 text-[clamp(2rem,8vw,3rem)] font-black opacity-[0.05] -rotate-12 pointer-events-none uppercase select-none leading-none" aria-hidden="true">Document officiel</div>

          <header class="p-6 sm:p-8 md:p-10 lg:p-12 border-b border-slate-100 bg-slate-50/50">
            <div class="flex justify-between items-start mb-6 sm:mb-8 gap-4 sm:gap-6 flex-wrap">
              <div>
                <span class="text-[11px] font-black text-blue-700 tracking-[0.2em] uppercase border-b-2 border-blue-700 pb-1"><?= htmlspecialchars($bandeau, ENT_QUOTES, 'UTF-8') ?></span>
                <h1 class="text-2xl md:text-4xl font-black italic tracking-tighter text-slate-900 uppercase mt-4"><?= htmlspecialchars($h1, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-lg md:text-xl font-bold text-slate-500 mt-2 uppercase"><?= htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <div class="text-right shrink-0">
                <div class="inline-block border-2 border-emerald-500/20 px-4 py-2.5 sm:px-5 sm:py-3 bg-emerald-50 rounded-md">
                  <p class="text-[9px] font-black text-emerald-700 uppercase">Statut</p>
                  <p class="text-emerald-600 font-black tracking-widest uppercase"><?= htmlspecialchars(\App\Services\Recruitment\RecruitmentOpeningPresentation::statusPublicBadge((string) ($opening['status'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-5 md:gap-6">
              <div class="p-4 sm:p-5 border border-slate-200 rounded-lg min-w-0">
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">Catégorie</p>
                <p class="text-sm font-black text-slate-800 uppercase mt-1"><?= htmlspecialchars($pc, ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <div class="p-4 sm:p-5 border border-slate-200 rounded-lg min-w-0">
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">Arme / domaine</p>
                <p class="text-sm font-black text-slate-800 uppercase mt-1"><?= htmlspecialchars($arm, ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <div class="p-4 sm:p-5 border border-slate-200 rounded-lg min-w-0">
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">Engagement</p>
                <p class="text-sm font-black text-slate-800 uppercase italic mt-1"><?= htmlspecialchars(trim((string) ($opening['employment_contract_label'] ?? '')) !== '' ? (string) $opening['employment_contract_label'] : '—', ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <div class="p-4 sm:p-5 border border-orange-200 bg-orange-50/30 rounded-lg min-w-0">
                <p class="text-[10px] text-orange-500 font-bold uppercase tracking-tight">Habilitation</p>
                <p class="text-sm font-black text-orange-700 uppercase mt-1"><?= htmlspecialchars($clr, ENT_QUOTES, 'UTF-8') ?></p>
              </div>
            </div>
          </header>

          <div class="grid grid-cols-12">
            <aside class="col-span-12 lg:col-span-4 border-r border-slate-100 p-6 sm:p-8 md:p-10 lg:p-12 bg-slate-50/30 space-y-8 sm:space-y-10">
              <?php if ($profiles !== []): ?>
              <div>
                <h3 class="text-[11px] font-black text-slate-400 mb-6 tracking-widest uppercase flex items-center gap-2">
                  <span class="w-2 h-2 bg-blue-700 rounded-full"></span> Profil candidat
                </h3>
                <ul class="space-y-6">
                  <?php foreach ($profiles as $pr): ?>
                    <?php if (!is_array($pr)) { continue; } ?>
                  <li class="border-b border-slate-200 pb-2">
                    <p class="text-[9px] font-black text-blue-700 uppercase"><?= htmlspecialchars((string) ($pr['rubrique'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-xs font-bold text-slate-700 mt-1"><?= htmlspecialchars((string) ($pr['detail'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                  </li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <?php endif; ?>

              <?php $tn = trim((string) ($opening['technical_notice'] ?? '')); ?>
              <?php if ($tn !== ''): ?>
              <div class="p-6 bg-slate-900 text-white rounded shadow-xl">
                <h3 class="text-[11px] font-black mb-2 uppercase italic text-blue-400 underline">Avis technique</h3>
                <p class="text-[10px] leading-relaxed font-mono opacity-90"><?= nl2br(htmlspecialchars($tn, ENT_QUOTES, 'UTF-8')) ?></p>
              </div>
              <?php endif; ?>
            </aside>

            <article class="col-span-12 lg:col-span-8 p-6 sm:p-8 md:p-10 lg:p-12">
              <h3 class="text-[11px] font-black text-slate-400 mb-5 sm:mb-6 tracking-widest uppercase">Description de la mission</h3>
              <?php $lead = trim((string) ($opening['mission_lead'] ?? '')); ?>
              <?php if ($lead !== ''): ?>
              <p class="text-lg text-slate-700 leading-relaxed mb-10 font-medium"><?= nl2br(htmlspecialchars($lead, ENT_QUOTES, 'UTF-8')) ?></p>
              <?php endif; ?>
              <?php $desc = trim((string) ($opening['description'] ?? '')); ?>
              <?php if ($desc !== ''): ?>
              <div class="prose prose-slate max-w-none text-sm text-slate-600 mb-10"><?= nl2br(htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')) ?></div>
              <?php endif; ?>

              <?php if ($blocks !== []): ?>
              <div class="grid gap-6">
                <?php foreach ($blocks as $b): ?>
                  <?php if (!is_array($b)) { continue; } ?>
                <div class="group p-5 border border-slate-200 hover:border-blue-700 transition-all">
                  <span class="text-[9px] font-black text-slate-400 group-hover:text-blue-700 uppercase tracking-tighter"><?= htmlspecialchars((string) ($b['theme'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                  <h4 class="text-sm font-black text-slate-900 uppercase mt-1"><?= htmlspecialchars((string) ($b['titre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h4>
                  <p class="text-xs text-slate-500 mt-2"><?= nl2br(htmlspecialchars((string) ($b['corps'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

              <div class="no-print mt-12 sm:mt-16 mb-2 flex flex-col sm:flex-row gap-3 sm:gap-4">
                <?php if (!$communityLocked): ?>
                <a href="<?= htmlspecialchars($enlistUrl, ENT_QUOTES, 'UTF-8') ?>" class="comspec-analytics-cta flex-1 rounded-lg py-4 sm:py-5 px-4 bg-slate-900 hover:bg-blue-800 text-white text-center text-[12px] font-black uppercase tracking-[0.2em] transition-all flex items-center justify-center gap-3" data-comspec-zone="fiche_poste" data-comspec-opening="<?= (int) ($opening['id'] ?? 0) ?>">Candidater au poste</a>
                <?php else: ?>
                <p class="flex-1 py-4 sm:py-5 px-4 text-center text-sm text-slate-500 border border-slate-200 rounded-lg">Le recrutement est fermé pour cette communauté.</p>
                <?php endif; ?>
                <button type="button" class="shrink-0 rounded-lg px-6 sm:px-8 py-4 sm:py-5 border-2 border-slate-200 hover:bg-slate-50 text-slate-900 text-[12px] font-black uppercase transition-all" onclick="window.print()">Version imprimable</button>
              </div>
            </article>
          </div>
        </div>
      </section>

      <aside class="no-print col-span-12 lg:col-span-3 space-y-6 pt-2 lg:pt-0">
        <?php if ($relatedOpenings !== []): ?>
        <div class="bg-white border border-slate-200 p-5 sm:p-6 rounded-lg shadow-sm">
          <h4 class="text-[10px] font-black text-slate-900 mb-4 uppercase flex items-center gap-2">
            <span class="w-3 h-[1px] bg-slate-900"></span> Postes liés
          </h4>
          <div class="space-y-4">
            <?php foreach ($relatedOpenings as $rel): ?>
              <?php
                $rs = (string) ($rel['public_page_slug'] ?? '');
                if ($rs === '') {
                    continue;
                }
                $rurl = url('c/' . rawurlencode($tSlug) . '/avis/' . rawurlencode($rs));
              ?>
            <a href="<?= htmlspecialchars($rurl, ENT_QUOTES, 'UTF-8') ?>" class="block group border-t border-slate-100 first:border-t-0 first:pt-0 pt-4 first:mt-0 mt-0">
              <p class="text-[11px] font-black text-slate-800 group-hover:text-blue-700"><?= htmlspecialchars((string) ($rel['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
              <p class="text-[9px] text-slate-400 font-mono italic mt-1"><?= htmlspecialchars((string) ($rel['unit_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </aside>
    </div>
  </main>
</div>
