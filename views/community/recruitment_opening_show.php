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
$clrKey = (string) ($opening['clearance_level'] ?? 'none');
$ref = (string) ($opening['reference_public'] ?? '');
$unitName = (string) ($opening['unit_name'] ?? '');
$title = (string) ($opening['title'] ?? '');
$h1 = $title;
if ($jobRoleName !== '') {
    $h1 .= ' (' . $jobRoleName . ')';
}
$statusBadge = \App\Services\Recruitment\RecruitmentOpeningPresentation::statusPublicBadge((string) ($opening['status'] ?? ''));
$statusKey = (string) ($opening['status'] ?? '');
$engagement = trim((string) ($opening['employment_contract_label'] ?? ''));
$contextLabel = trim((string) ($opening['employment_context_label'] ?? ''));
$summary = trim((string) ($opening['summary'] ?? ''));
$lead = trim((string) ($opening['mission_lead'] ?? ''));
$desc = trim((string) ($opening['description'] ?? ''));
$tn = trim((string) ($opening['technical_notice'] ?? ''));
$hasSidebar = $profiles !== [] || $tn !== '';
$hasMissionBody = $lead !== '' || $desc !== '' || $blocks !== [] || $summary !== '';
$enlistUrl = url('c/' . rawurlencode($slug) . '/enlistment?ouverture=' . (int) ($opening['id'] ?? 0));
$communityUrl = url('c/' . rawurlencode($slug));
?>
<style>
  .ro-page {
    --ro-athena: #059669;
    --ro-athena-soft: #ecfdf5;
    --ro-athena-mid: #10b981;
    --ro-ink: #0f172a;
  }
  .ro-page .ro-grain::before {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    opacity: .03;
    background-image: radial-gradient(circle at 20% 20%, #000 0.5px, transparent 0.6px), radial-gradient(circle at 80% 70%, #000 0.5px, transparent 0.6px);
    background-size: 16px 16px;
  }
  .ro-panel {
    box-shadow: 0 18px 50px -28px rgba(15, 23, 42, 0.28);
  }
  .ro-meta-card {
    background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
  }
  .ro-cta-primary {
    background: var(--ro-athena);
  }
  .ro-cta-primary:hover {
    background: #047857;
  }
  @media print {
    .portal-nav, [data-portal-nav], .no-print { display: none !important; }
    body { background: #fff !important; }
    .ro-print-main { padding-top: 0 !important; }
    .ro-panel { box-shadow: none !important; }
  }
</style>
<div class="ro-page ro-print-main bg-slate-100/80 min-h-screen relative ro-grain">
  <nav class="no-print h-14 border-b border-slate-200/90 flex items-center bg-white/95 backdrop-blur-md sticky top-0 z-50 px-4 sm:px-6 md:px-10">
    <div class="flex items-center gap-3 text-[10px] font-bold uppercase tracking-[0.18em] max-w-6xl mx-auto w-full min-w-0">
      <a href="<?= htmlspecialchars($communityUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-slate-400 hover:text-[color:var(--ro-athena)] transition-colors truncate"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></a>
      <span class="text-slate-300 shrink-0" aria-hidden="true">/</span>
      <span class="text-slate-700 shrink-0">Avis de vacance</span>
    </div>
  </nav>

  <main class="max-w-6xl mx-auto w-full px-4 pb-10 pt-5 sm:px-6 sm:pb-12 sm:pt-7 md:px-8 md:pt-8">
    <div class="grid grid-cols-12 gap-5 lg:gap-7">
      <section class="col-span-12 <?= $relatedOpenings !== [] ? 'lg:col-span-9' : 'lg:col-span-12' ?>">
        <article class="ro-panel rounded-2xl border border-slate-200/90 overflow-hidden bg-white">
          <div class="h-1.5 w-full bg-[color:var(--ro-athena)]" aria-hidden="true"></div>

          <header class="px-5 pt-5 pb-5 sm:px-7 sm:pt-6 sm:pb-6 md:px-8 border-b border-slate-100">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
              <?php if ($ref !== ''): ?>
              <p class="text-[11px] font-bold tracking-[0.14em] uppercase text-[color:var(--ro-athena)]">
                Réf. <?= htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') ?>
              </p>
              <?php else: ?>
              <p class="text-[11px] font-bold tracking-[0.14em] uppercase text-slate-400">Avis de vacance</p>
              <?php endif; ?>
              <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wide
                <?= $statusKey === 'published'
                  ? 'border-emerald-200 bg-[color:var(--ro-athena-soft)] text-[color:var(--ro-athena)]'
                  : ($statusKey === 'closed' ? 'border-slate-200 bg-slate-50 text-slate-600' : 'border-slate-200 bg-slate-50 text-slate-500') ?>">
                <span class="h-1.5 w-1.5 rounded-full <?= $statusKey === 'published' ? 'bg-[color:var(--ro-athena)]' : 'bg-slate-400' ?>" aria-hidden="true"></span>
                <?= htmlspecialchars($statusBadge, ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>

            <h1 class="text-xl sm:text-2xl md:text-[1.75rem] font-black tracking-tight text-[color:var(--ro-ink)] leading-snug">
              <?= htmlspecialchars($h1, ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <?php if ($unitName !== ''): ?>
            <p class="mt-1.5 text-sm sm:text-base font-semibold text-slate-500"><?= htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if ($contextLabel !== ''): ?>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed max-w-3xl"><?= htmlspecialchars($contextLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <div class="mt-5 grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
              <div class="ro-meta-card rounded-xl border border-slate-200/90 px-3.5 py-3 min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Catégorie</p>
                <p class="mt-1 text-sm font-bold text-slate-800 leading-snug"><?= htmlspecialchars($pc, ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <div class="ro-meta-card rounded-xl border border-slate-200/90 px-3.5 py-3 min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Arme / domaine</p>
                <p class="mt-1 text-sm font-bold text-slate-800 leading-snug"><?= htmlspecialchars($arm, ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <div class="ro-meta-card rounded-xl border border-slate-200/90 px-3.5 py-3 min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Engagement</p>
                <p class="mt-1 text-sm font-bold text-slate-800 leading-snug"><?= htmlspecialchars($engagement !== '' ? $engagement : 'Non précisé', ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <div class="rounded-xl border px-3.5 py-3 min-w-0 <?= $clrKey !== 'none' ? 'border-amber-200/90 bg-amber-50/60' : 'border-slate-200/90 ro-meta-card' ?>">
                <p class="text-[10px] font-bold uppercase tracking-wide <?= $clrKey !== 'none' ? 'text-amber-700' : 'text-slate-400' ?>">Habilitation</p>
                <p class="mt-1 text-sm font-bold leading-snug <?= $clrKey !== 'none' ? 'text-amber-900' : 'text-slate-800' ?>"><?= htmlspecialchars($clr, ENT_QUOTES, 'UTF-8') ?></p>
              </div>
            </div>
          </header>

          <div class="grid grid-cols-12 <?= $hasSidebar ? '' : '' ?>">
            <?php if ($hasSidebar): ?>
            <aside class="col-span-12 lg:col-span-4 border-b lg:border-b-0 lg:border-r border-slate-100 px-5 py-5 sm:px-6 sm:py-6 md:px-7 bg-slate-50/40 space-y-6">
              <?php if ($profiles !== []): ?>
              <div>
                <h2 class="text-[11px] font-bold tracking-[0.16em] uppercase text-slate-500 flex items-center gap-2 mb-3.5">
                  <span class="h-1.5 w-1.5 rounded-full bg-[color:var(--ro-athena)]" aria-hidden="true"></span>
                  Profil recherché
                </h2>
                <ul class="space-y-3">
                  <?php foreach ($profiles as $pr): ?>
                    <?php if (!is_array($pr)) { continue; } ?>
                  <li class="rounded-xl border border-slate-200/80 bg-white px-3.5 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-[color:var(--ro-athena)]"><?= htmlspecialchars((string) ($pr['rubrique'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-sm text-slate-700 leading-snug"><?= htmlspecialchars((string) ($pr['detail'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                  </li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <?php endif; ?>

              <?php if ($tn !== ''): ?>
              <div class="rounded-xl bg-slate-900 text-white px-4 py-4">
                <h2 class="text-[11px] font-bold uppercase tracking-[0.14em] text-emerald-300 mb-2">Avis technique</h2>
                <p class="text-xs leading-relaxed text-slate-200"><?= nl2br(htmlspecialchars($tn, ENT_QUOTES, 'UTF-8')) ?></p>
              </div>
              <?php endif; ?>
            </aside>
            <?php endif; ?>

            <div class="<?= $hasSidebar ? 'col-span-12 lg:col-span-8' : 'col-span-12' ?> px-5 py-5 sm:px-7 sm:py-6 md:px-8 md:py-7 flex flex-col">
              <h2 class="text-[11px] font-bold tracking-[0.16em] uppercase text-slate-500 mb-3.5">Description de la mission</h2>

              <?php if ($hasMissionBody): ?>
                <?php if ($lead !== ''): ?>
                <p class="text-base text-slate-800 leading-relaxed mb-4 font-medium"><?= nl2br(htmlspecialchars($lead, ENT_QUOTES, 'UTF-8')) ?></p>
                <?php elseif ($summary !== '' && $desc === ''): ?>
                <p class="text-base text-slate-700 leading-relaxed mb-4"><?= nl2br(htmlspecialchars($summary, ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>

                <?php if ($desc !== ''): ?>
                <div class="text-sm text-slate-600 leading-relaxed mb-5 space-y-3"><?= nl2br(htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')) ?></div>
                <?php elseif ($summary !== '' && $lead !== ''): ?>
                <p class="text-sm text-slate-600 leading-relaxed mb-5"><?= nl2br(htmlspecialchars($summary, ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>

                <?php if ($blocks !== []): ?>
                <div class="grid gap-3 mb-2">
                  <?php foreach ($blocks as $b): ?>
                    <?php if (!is_array($b)) { continue; } ?>
                  <div class="rounded-xl border border-slate-200/90 bg-slate-50/50 px-4 py-3.5 hover:border-emerald-300/80 transition-colors">
                    <?php if (trim((string) ($b['theme'] ?? '')) !== ''): ?>
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400"><?= htmlspecialchars((string) ($b['theme'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <h3 class="text-sm font-bold text-slate-900 mt-0.5"><?= htmlspecialchars((string) ($b['titre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                    <?php if (trim((string) ($b['corps'] ?? '')) !== ''): ?>
                    <p class="text-sm text-slate-600 mt-1.5 leading-relaxed"><?= nl2br(htmlspecialchars((string) ($b['corps'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              <?php else: ?>
                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-4 py-5 mb-2">
                  <p class="text-sm font-semibold text-slate-700">Aucune description fournie pour le moment</p>
                  <p class="mt-1 text-sm text-slate-500 leading-relaxed">Les détails de la mission seront précisés par l’équipe recrutement. Vous pouvez tout de même candidater si le poste vous intéresse.</p>
                </div>
              <?php endif; ?>

              <div class="no-print mt-6 pt-5 border-t border-slate-100 flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2.5 sm:gap-3">
                <?php if (!$communityLocked): ?>
                <a href="<?= htmlspecialchars($enlistUrl, ENT_QUOTES, 'UTF-8') ?>"
                   class="comspec-analytics-cta ro-cta-primary inline-flex items-center justify-center rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm shadow-emerald-900/10 transition-colors sm:min-w-[12.5rem]"
                   data-comspec-zone="fiche_poste"
                   data-comspec-opening="<?= (int) ($opening['id'] ?? 0) ?>">
                  Candidater au poste
                </a>
                <?php else: ?>
                <p class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Le recrutement est fermé pour cette communauté.</p>
                <?php endif; ?>
                <button type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                        onclick="window.print()">
                  Version imprimable
                </button>
                <a href="<?= htmlspecialchars($communityUrl . '#carrieres', ENT_QUOTES, 'UTF-8') ?>"
                   class="inline-flex items-center justify-center rounded-xl px-3 py-3 text-sm font-semibold text-slate-500 hover:text-[color:var(--ro-athena)] transition-colors sm:ml-auto">
                  ← Retour aux offres
                </a>
              </div>
            </div>
          </div>
        </article>
      </section>

      <?php if ($relatedOpenings !== []): ?>
      <aside class="no-print col-span-12 lg:col-span-3 space-y-4">
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm px-4 py-4 sm:px-5">
          <h2 class="text-[11px] font-bold tracking-[0.14em] uppercase text-slate-500 mb-3 flex items-center gap-2">
            <span class="h-px w-3 bg-slate-300" aria-hidden="true"></span>
            Postes liés
          </h2>
          <div class="space-y-1">
            <?php foreach ($relatedOpenings as $rel): ?>
              <?php
                $rs = (string) ($rel['public_page_slug'] ?? '');
                if ($rs === '') {
                    continue;
                }
                $rurl = url('c/' . rawurlencode($tSlug) . '/avis/' . rawurlencode($rs));
              ?>
            <a href="<?= htmlspecialchars($rurl, ENT_QUOTES, 'UTF-8') ?>" class="block rounded-xl px-3 py-2.5 hover:bg-emerald-50/80 transition-colors group">
              <p class="text-sm font-bold text-slate-800 group-hover:text-[color:var(--ro-athena)] leading-snug"><?= htmlspecialchars((string) ($rel['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
              <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars((string) ($rel['unit_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </aside>
      <?php endif; ?>
    </div>
  </main>
</div>
