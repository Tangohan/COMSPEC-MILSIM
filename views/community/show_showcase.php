<?php
/** @var array $tenant */
/** @var array $memberships */
/** @var array $communityConfig */
/** @var array<string, mixed>|null $communityProfile */
/** @var array<string, mixed>|null $showcaseVm */
/** @var list<array<string,mixed>> $publicUnits */
/** @var list<array<string,mixed>> $publicRosterRows */
/** @var array<int,int> $unitMemberCounts */
/** @var array<int,string> $commanderNames */
/** @var bool $hasMembershipInTenant */
/** @var bool $showForumCta */
$slug = $tenant['slug'] ?? '';
$name = $tenant['name'] ?? '';
$cp = $communityProfile ?? \App\Services\Community\TenantCommunityProfileService::getPublicViewModel($communityConfig ?? [], (string) ($tenant['slug'] ?? ''));
$sv = is_array($showcaseVm ?? null) ? $showcaseVm : [];
$publicUnits = is_array($publicUnits ?? null) ? $publicUnits : [];
$publicRosterRows = is_array($publicRosterRows ?? null) ? $publicRosterRows : [];
$unitMemberCounts = is_array($unitMemberCounts ?? null) ? $unitMemberCounts : [];
$commanderNames = is_array($commanderNames ?? null) ? $commanderNames : [];
$hasMembershipInTenant = $hasMembershipInTenant ?? false;
$showForumCta = $showForumCta ?? true;
$tzLabel = (string) ($sv['timezoneLabel'] ?? '');
$communityCode = trim((string) ($tenant['community_code'] ?? ''));
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$recruitmentPublishedOpenings = is_array($recruitmentPublishedOpenings ?? null) ? $recruitmentPublishedOpenings : [];
$recruitmentProspectionRef = trim((string) ($recruitmentProspectionRef ?? ''));
$recruitmentListUpdatedAt = trim((string) ($recruitmentListUpdatedAt ?? ''));
$userId = (int) (\App\Core\Session::get('user_id') ?? 0);
$isLocked = !empty($cp['isLocked']);
$publicAudience = ($cp['publicAudience'] ?? 'unit') === 'platform' ? 'platform' : 'unit';
$eyebrowSub = $publicAudience === 'platform'
    ? 'Portail plateforme'
    : 'Fiche publique de communauté';
$styleBadgeLabels = is_array($cp['styleBadgeLabels'] ?? null) ? $cp['styleBadgeLabels'] : [];
$stats = is_array($sv['stats'] ?? null) ? $sv['stats'] : [];
$regionBadges = is_array($sv['regionBadges'] ?? null) ? $sv['regionBadges'] : [];
$specialties = is_array($sv['specialties'] ?? null) ? $sv['specialties'] : [];
$commandChain = is_array($sv['commandChain'] ?? null) ? $sv['commandChain'] : [];
$mods = is_array($sv['publicModules'] ?? null) ? $sv['publicModules'] : [];
$modLabels = [
    'forum' => 'Forum',
    'documents' => 'Documents',
    'events' => 'Événements',
    'roster' => 'Roster',
    'training' => 'Formations',
    'analytics' => 'Analytique',
];
$decodeUnitTags = static function (array $u): array {
    $raw = $u['public_tags'] ?? null;
    if ($raw === null || $raw === '') {
        return [];
    }
    if (is_string($raw)) {
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }
    return [];
};
$rosterIndicatif = static function (array $r): string {
    $cs = trim((string) ($r['callsign'] ?? ''));
    if ($cs !== '') {
        return $cs;
    }
    $fa = trim((string) ($r['forum_alias'] ?? ''));
    if ($fa !== '') {
        return $fa;
    }
    $dn = trim((string) ($r['display_name'] ?? ''));
    return $dn !== '' ? $dn : '—';
};
$rosterStatusClass = static function (string $st): string {
    return match ($st) {
        'active' => 'bg-emerald-50 text-emerald-700',
        'pending' => 'bg-sky-50 text-sky-700',
        'inactive' => 'bg-amber-50 text-amber-700',
        default => 'bg-slate-100 text-slate-700',
    };
};
$rosterStatusLabel = static function (string $st): string {
    return match ($st) {
        'active' => 'Actif',
        'pending' => 'Instruction',
        'inactive' => 'Réserve',
        default => $st,
    };
};
$heroSubtitle = trim((string) ($sv['heroSubtitle'] ?? ''));
if ($heroSubtitle === '') {
    $heroSubtitle = trim((string) ($cp['simpleBody'] ?? ''));
}
if ($heroSubtitle === '') {
    $heroSubtitle = trim((string) ($cp['welcomeText'] ?? ''));
}
if ($heroSubtitle === '') {
    $heroSubtitle = trim((string) ($cp['gameLabel'] ?? ''));
}
if ($heroSubtitle === '' && ($cp['presentationMode'] ?? '') === 'military' && !empty($cp['militarySections'][0]) && is_array($cp['militarySections'][0])) {
    $heroSubtitle = trim((string) ($cp['militarySections'][0]['body'] ?? ''));
}
?>
<div class="community-public-vitrine min-h-screen bg-slate-100 font-sans text-slate-900 -mx-4 sm:-mx-6 lg:-mx-8">
  <div class="relative isolate overflow-hidden bg-slate-950 text-white">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.22),transparent_25%),radial-gradient(circle_at_80%_20%,rgba(14,165,233,0.16),transparent_22%),linear-gradient(to_bottom,rgba(2,6,23,0.78),rgba(2,6,23,0.94))]"></div>
    <div class="community-showcase-grain pointer-events-none absolute inset-0" aria-hidden="true"></div>

    <header class="relative border-b border-white/10">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
        <div>
          <p class="text-[11px] font-black uppercase tracking-[0.35em] text-emerald-300">ATHENA</p>
          <p class="mt-1 text-xs uppercase tracking-[0.22em] text-slate-400"><?= htmlspecialchars($eyebrowSub) ?></p>
        </div>
        <nav class="hidden items-center gap-3 md:flex" aria-label="Sections">
          <a href="#overview" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10">Vue générale</a>
          <a href="#structure" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10">Structure</a>
          <?php if (!empty($sv['publicRosterEnabled'])): ?>
          <a href="#roster" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10">Roster</a>
          <?php endif; ?>
          <a href="#units" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10">Unités</a>
          <?php if ($recruitmentPublishedOpenings !== []): ?>
          <a href="#carrieres" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10">Offres</a>
          <?php endif; ?>
        </nav>
      </div>
    </header>

    <section class="relative mx-auto max-w-7xl px-6 py-10 lg:px-8 lg:py-14">
      <div class="grid gap-8 xl:grid-cols-[1.15fr_0.85fr] xl:items-end">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <?php if ($publicAudience !== 'platform' && !empty($sv['recruitmentBadgeOpen']) && !$isLocked): ?>
            <span class="inline-flex items-center rounded-full bg-emerald-400 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-950">Recrutement ouvert</span>
            <?php elseif ($publicAudience !== 'platform' && $isLocked): ?>
            <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-white">Recrutement fermé</span>
            <?php elseif ($publicAudience === 'platform' && !$isLocked): ?>
            <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-white">Portail actif</span>
            <?php elseif ($publicAudience === 'platform' && $isLocked): ?>
            <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-white">Accès restreint</span>
            <?php endif; ?>
            <?php foreach ($styleBadgeLabels as $bl): ?>
              <?php if (is_string($bl) && $bl !== ''): ?>
              <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-white"><?= htmlspecialchars($bl) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
            <?php foreach ($regionBadges as $rb): ?>
              <?php if (is_string($rb) && $rb !== ''): ?>
              <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-white"><?= htmlspecialchars($rb) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>

          <h1 class="mt-5 text-4xl font-black uppercase tracking-tight text-white sm:text-5xl lg:text-6xl"><?= htmlspecialchars($name) ?></h1>
          <?php if ($heroSubtitle !== ''): ?>
          <p class="mt-5 max-w-3xl text-base leading-7 text-slate-300 lg:text-lg"><?= nl2br(htmlspecialchars($heroSubtitle)) ?></p>
          <?php endif; ?>

          <div class="mt-8 flex flex-wrap gap-3">
            <?php if (!$isLocked && $publicAudience !== 'platform'): ?>
            <a href="<?= htmlspecialchars(url('c/' . $slug . '/enlistment')) ?>" class="comspec-analytics-cta inline-flex items-center rounded-2xl bg-emerald-500 px-5 py-3 text-[11px] font-black uppercase tracking-[0.22em] text-slate-950 transition hover:bg-emerald-400" data-comspec-zone="vitrine_hero">Rejoindre (candidature)</a>
            <?php elseif (!$isLocked && $publicAudience === 'platform'): ?>
            <a href="<?= htmlspecialchars(url('c/' . $slug . '/enlistment')) ?>" class="comspec-analytics-cta inline-flex items-center rounded-2xl border border-white/20 bg-white/10 px-5 py-3 text-[11px] font-black uppercase tracking-[0.22em] text-white transition hover:bg-white/15" data-comspec-zone="vitrine_hero">Candidater</a>
            <?php endif; ?>
            <?php if (!empty($sv['publicRosterEnabled'])): ?>
            <a href="#roster" class="inline-flex items-center rounded-2xl border border-white/15 bg-white/10 px-5 py-3 text-[11px] font-black uppercase tracking-[0.22em] text-white transition hover:bg-white/15">Consulter le roster</a>
            <?php endif; ?>
            <a href="#units" class="inline-flex items-center rounded-2xl border border-white/15 bg-white/10 px-5 py-3 text-[11px] font-black uppercase tracking-[0.22em] text-white transition hover:bg-white/15">Explorer les unités</a>
            <a href="#actions-contact" class="inline-flex items-center rounded-2xl border border-white/15 bg-white/10 px-5 py-3 text-[11px] font-black uppercase tracking-[0.22em] text-white transition hover:bg-white/15">Contacter l'équipe</a>
          </div>
        </div>

        <aside class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
          <div class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">Identité publique</p>
            <dl class="mt-4 grid gap-3 text-sm">
              <div class="flex items-start justify-between gap-4 border-b border-white/10 pb-3">
                <dt class="text-slate-400">Code communauté</dt>
                <dd class="font-mono font-bold text-emerald-300"><?= $communityCode !== '' ? htmlspecialchars($communityCode) : '—' ?></dd>
              </div>
              <div class="flex items-start justify-between gap-4 border-b border-white/10 pb-3">
                <dt class="text-slate-400">Fuseau</dt>
                <dd class="font-semibold text-white"><?= $tzLabel !== '' ? htmlspecialchars($tzLabel) : '—' ?></dd>
              </div>
              <?php $doc = trim((string) ($sv['publicDoctrine'] ?? '')); ?>
              <?php if ($doc !== ''): ?>
              <div class="flex items-start justify-between gap-4 border-b border-white/10 pb-3">
                <dt class="text-slate-400">Doctrine</dt>
                <dd class="font-semibold text-white text-right"><?= htmlspecialchars($doc) ?></dd>
              </div>
              <?php endif; ?>
              <?php $acc = trim((string) ($sv['publicAccessLabel'] ?? '')); ?>
              <div class="flex items-start justify-between gap-4">
                <dt class="text-slate-400">Accès</dt>
                <dd class="font-semibold text-white text-right"><?= $acc !== '' ? htmlspecialchars($acc) : '—' ?></dd>
              </div>
            </dl>
          </div>

          <div class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">Modules publics</p>
            <div class="mt-4 flex flex-wrap gap-2">
              <?php foreach ($modLabels as $mk => $ml): ?>
                <?php if (!empty($mods[$mk])): ?>
                <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white"><?= htmlspecialchars($ml) ?></span>
                <?php endif; ?>
              <?php endforeach; ?>
              <?php
                $anyMod = false;
                foreach ($mods as $v) {
                    if (!empty($v)) {
                        $anyMod = true;
                        break;
                    }
                }
                ?>
              <?php if (!$anyMod): ?>
              <span class="text-sm text-slate-400">À configurer (back-office)</span>
              <?php endif; ?>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </div>

  <?php if ($flashSuccess): ?>
  <div class="max-w-7xl mx-auto px-6 pt-6 lg:px-8">
    <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars($flashSuccess) ?></p>
  </div>
  <?php endif; ?>
  <?php if ($flashError): ?>
  <div class="max-w-7xl mx-auto px-6 pt-6 lg:px-8">
    <p class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"><?= htmlspecialchars($flashError) ?></p>
  </div>
  <?php endif; ?>

  <main class="mx-auto max-w-7xl space-y-8 px-6 py-8 lg:px-8 lg:py-10">

    <section id="overview" class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
      <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-soft lg:p-8">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-400">Vue d'ensemble</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Positionnement public</h2>
          </div>
          <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-600"><?= $isLocked ? 'Fermé' : 'Actif' ?></span>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl bg-slate-50 px-4 py-4">
            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Effectif public</p>
            <p class="mt-2 text-3xl font-black tracking-tight text-slate-950"><?= htmlspecialchars((string) ($stats['effectif'] ?? '—')) ?></p>
            <p class="mt-1 text-xs text-slate-500">Membres actifs<?= ($sv['statsMode'] ?? '') === 'computed' ? ' (calculé)' : '' ?></p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-4">
            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Unités</p>
            <p class="mt-2 text-3xl font-black tracking-tight text-slate-950"><?= htmlspecialchars((string) ($stats['unites'] ?? '—')) ?></p>
            <p class="mt-1 text-xs text-slate-500">Cellules listées</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-4">
            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Taux d'activité</p>
            <p class="mt-2 text-3xl font-black tracking-tight text-slate-950"><?= htmlspecialchars((string) ($stats['activite'] ?? '—')) ?></p>
            <p class="mt-1 text-xs text-slate-500">30 derniers jours<?= ($sv['statsMode'] ?? '') === 'computed' ? ' (calculé)' : '' ?></p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-4">
            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Théâtre principal</p>
            <p class="mt-2 text-lg font-black tracking-tight text-slate-950"><?= htmlspecialchars((string) ($stats['theatre'] ?? '—')) ?></p>
            <p class="mt-1 text-xs text-slate-500">Référence</p>
          </div>
        </div>

        <div class="mt-6 grid gap-5 lg:grid-cols-2">
          <?php
          $mission = trim((string) ($sv['publicMission'] ?? ''));
          if ($mission === '') {
              $mission = trim((string) ($cp['expectations'] ?? ''));
          }
          ?>
          <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Mission publique</p>
            <p class="mt-3 text-sm leading-7 text-slate-700"><?= $mission !== '' ? nl2br(htmlspecialchars($mission)) : '—' ?></p>
          </div>
          <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Spécialités</p>
            <div class="mt-3 flex flex-wrap gap-2">
              <?php if ($specialties !== []): ?>
                <?php foreach ($specialties as $sp): ?>
                  <?php if (is_string($sp) && $sp !== ''): ?>
                  <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200"><?= htmlspecialchars($sp) ?></span>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="text-sm text-slate-500">—</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <aside class="space-y-6">
        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-soft">
          <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Chaîne de commandement publique</p>
          <div class="mt-5 space-y-3">
            <?php if ($commandChain !== []): ?>
              <?php foreach ($commandChain as $cc): ?>
                <?php if (!is_array($cc)) { continue; } ?>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                  <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400"><?= htmlspecialchars((string) ($cc['role_label'] ?? '')) ?></p>
                  <p class="mt-2 text-sm font-bold text-slate-950"><?= htmlspecialchars((string) ($cc['display_name'] ?? '')) ?></p>
                  <?php $hint = trim((string) ($cc['hint'] ?? '')); ?>
                  <?php if ($hint !== ''): ?>
                  <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($hint) ?></p>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="text-sm text-slate-500">Non renseignée (back-office).</p>
            <?php endif; ?>
          </div>
        </div>

        <div id="join" class="rounded-[2rem] border border-emerald-200 bg-emerald-50 p-6 shadow-soft">
          <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700">Accès</p>
          <h3 class="mt-2 text-xl font-black tracking-tight text-slate-950"><?= $publicAudience === 'platform' ? 'Accès &amp; participation' : 'Rejoindre la communauté' ?></h3>
          <p class="mt-3 text-sm leading-6 text-slate-700"><?= $publicAudience === 'platform' ? 'Code d\'organisation ou candidature selon les règles de la plateforme.' : 'Utilisez le code ou la candidature selon la configuration de l\'unité.' ?></p>
          <?php if ($communityCode !== ''): ?>
          <div class="mt-4 rounded-2xl border border-emerald-200 bg-white px-4 py-4">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Code communauté</p>
            <p class="mt-2 font-mono text-lg font-black text-emerald-800" id="public-community-code"><?= htmlspecialchars($communityCode) ?></p>
          </div>
          <div class="mt-4 flex flex-wrap gap-3">
            <?php if (!$isLocked): ?>
            <a href="<?= htmlspecialchars(url('c/' . $slug . '/enlistment')) ?>" class="comspec-analytics-cta inline-flex items-center rounded-2xl <?= $publicAudience === 'platform' ? 'border border-slate-300 bg-white text-slate-800 hover:bg-slate-50' : 'bg-slate-950 text-white hover:bg-slate-800' ?> px-4 py-3 text-[11px] font-black uppercase tracking-[0.18em] transition" data-comspec-zone="bloc_acces"><?= $publicAudience === 'platform' ? 'Candidature' : 'Postuler' ?></a>
            <?php endif; ?>
            <button type="button" class="rounded-2xl border border-slate-300 bg-white px-4 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-slate-700 transition hover:bg-slate-50" data-copy-code="<?= htmlspecialchars($communityCode, ENT_QUOTES, 'UTF-8') ?>">Copier le code</button>
          </div>
          <?php else: ?>
          <p class="mt-4 text-sm text-slate-600">Code communauté non défini.</p>
          <?php endif; ?>
        </div>
      </aside>
    </section>

    <?php if ($recruitmentPublishedOpenings !== []): ?>
    <section id="carrieres" class="py-24 bg-slate-50 relative">
      <div class="community-showcase-grain pointer-events-none absolute inset-0" aria-hidden="true"></div>
      <div class="relative max-w-7xl mx-auto px-6">
        <div class="flex flex-col md:flex-row justify-between items-end mb-16 pb-8 border-b-2 border-slate-200">
          <div>
            <h4 class="text-blue-600 text-xs font-black uppercase tracking-[0.3em] mb-2">Direction des ressources humaines</h4>
            <h2 class="text-4xl sm:text-5xl font-black uppercase italic tracking-tighter text-slate-900">Prospection <span class="text-blue-600">opérationnelle</span></h2>
          </div>
          <div class="text-right font-mono text-[10px] text-slate-400 uppercase hidden md:block mt-4 md:mt-0">
            <?php
            $docLine = $recruitmentProspectionRef !== '' ? htmlspecialchars($recruitmentProspectionRef, ENT_QUOTES, 'UTF-8') : 'Tableau des offres';
            $ts = $recruitmentListUpdatedAt !== '' ? strtotime($recruitmentListUpdatedAt) : false;
            $when = $ts ? date('d/m/Y H:i', $ts) : date('d/m/Y H:i');
            ?>
            Document mis à jour le : <?= htmlspecialchars($when, ENT_QUOTES, 'UTF-8') ?><br>
            Réf. affichée : <?= $docLine ?>
          </div>
        </div>
        <div class="grid grid-cols-1 gap-6">
          <?php foreach ($recruitmentPublishedOpenings as $ro): ?>
            <?php
              $pc = \App\Services\Recruitment\RecruitmentOpeningPresentation::personnelCategoryLabel((string) ($ro['personnel_category'] ?? 'other'));
              $arm = \App\Services\Recruitment\RecruitmentOpeningPresentation::armDomainLabel(isset($ro['arm_domain']) ? (string) $ro['arm_domain'] : null);
              $ref = (string) ($ro['reference_public'] ?? '');
              $sum = trim((string) ($ro['summary'] ?? ''));
              if ($sum === '') {
                  $sum = trim(strip_tags((string) ($ro['description'] ?? '')));
                  if (mb_strlen($sum) > 220) {
                      $sum = mb_substr($sum, 0, 217) . '…';
                  }
              }
              $avisSlug = (string) ($ro['public_page_slug'] ?? '');
              $detailUrl = $avisSlug !== '' ? url('c/' . rawurlencode($slug) . '/avis/' . rawurlencode($avisSlug)) : url('c/' . rawurlencode($slug) . '/enlistment');
            ?>
          <div class="bg-white border border-slate-200 shadow-sm hover:shadow-md transition-all overflow-hidden flex flex-col md:flex-row">
            <div class="bg-slate-900 text-white p-6 flex flex-col justify-center items-center md:w-48 text-center border-r border-slate-200">
              <span class="text-[10px] font-black uppercase tracking-widest opacity-60 mb-2">Catégorie</span>
              <span class="text-lg font-black italic uppercase leading-tight"><?= htmlspecialchars($pc, ENT_QUOTES, 'UTF-8') ?></span>
              <div class="mt-4 px-3 py-1 bg-blue-600 text-[9px] font-bold uppercase tracking-wide"><?= htmlspecialchars($arm, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="p-8 flex-grow">
              <div class="flex justify-between items-start mb-4 gap-4 flex-wrap">
                <div>
                  <h3 class="text-2xl font-black uppercase italic text-slate-900"><?= htmlspecialchars((string) ($ro['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                  <p class="text-sm font-bold text-blue-600 uppercase tracking-tight mt-1"><?= htmlspecialchars((string) ($ro['unit_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="text-right shrink-0">
                  <span class="text-[10px] font-mono bg-slate-100 px-2 py-1 rounded">Réf. <?= htmlspecialchars($ref !== '' ? $ref : '—', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </div>
              <?php if ($sum !== ''): ?>
              <p class="text-sm text-slate-600 leading-relaxed mb-6"><?= nl2br(htmlspecialchars($sum, ENT_QUOTES, 'UTF-8')) ?></p>
              <?php endif; ?>
              <div class="flex flex-wrap gap-6 border-t border-slate-100 pt-6">
                <?php if (trim((string) ($ro['employment_contract_label'] ?? '')) !== ''): ?>
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <span class="text-[10px] font-bold uppercase text-slate-500"><?= htmlspecialchars((string) $ro['employment_contract_label'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php endif; ?>
                <?php if (trim((string) ($ro['employment_context_label'] ?? '')) !== ''): ?>
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                  <span class="text-[10px] font-bold uppercase text-slate-500"><?= htmlspecialchars((string) $ro['employment_context_label'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <div class="p-8 bg-slate-50 border-t md:border-t-0 md:border-l border-slate-100 flex flex-col items-stretch justify-center gap-3 min-w-[200px]">
              <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-center bg-white border-2 border-slate-200 text-slate-900 px-6 py-3 text-[10px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all">Voir la fiche</a>
              <?php if (!$isLocked && $publicAudience !== 'platform'): ?>
              <a href="<?= htmlspecialchars(url('c/' . rawurlencode($slug) . '/enlistment?ouverture=' . (int) ($ro['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" class="comspec-analytics-cta text-center bg-slate-900 text-white px-6 py-3 text-[10px] font-black uppercase tracking-widest hover:bg-blue-600 transition-all" data-comspec-zone="liste_postes" data-comspec-opening="<?= (int) ($ro['id'] ?? 0) ?>">Candidater</a>
              <?php elseif (!$isLocked): ?>
              <a href="<?= htmlspecialchars(url('c/' . rawurlencode($slug) . '/enlistment?ouverture=' . (int) ($ro['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" class="comspec-analytics-cta text-center bg-blue-600 text-white px-6 py-3 text-[10px] font-black uppercase tracking-widest hover:bg-slate-900 transition-all" data-comspec-zone="liste_postes" data-comspec-opening="<?= (int) ($ro['id'] ?? 0) ?>">Candidater</a>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <section id="structure" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-soft lg:p-8">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-400">Structure</p>
          <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Architecture publique de l'organisation</h2>
          <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Unités ORBAT visibles sur la fiche (configurables par unité).</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700"><?= count($publicUnits) ?> unité(s) visible<?= count($publicUnits) > 1 ? 's' : '' ?></span>
          <?php if (!empty($sv['publicRosterEnabled'])): ?>
          <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Roster synchronisé</span>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($publicUnits === []): ?>
        <p class="mt-8 text-sm text-slate-600">Aucune unité publique pour l’instant. Complétez l’ORBAT dans le back-office.</p>
      <?php else: ?>
      <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($publicUnits as $unit): ?>
          <?php
            $uid = (int) ($unit['id'] ?? 0);
            $mc = (int) ($unitMemberCounts[$uid] ?? 0);
            $tags = $decodeUnitTags($unit);
            $blurb = trim((string) ($unit['public_blurb'] ?? ''));
            ?>
        <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400"><?= htmlspecialchars((string) ($unit['type'] ?? 'unité')) ?></p>
              <h3 class="mt-2 text-lg font-black tracking-tight text-slate-950"><?= htmlspecialchars((string) ($unit['name'] ?? '')) ?></h3>
            </div>
            <span class="rounded-full bg-white px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-700 ring-1 ring-slate-200"><?= $mc ?> pers.</span>
          </div>
          <?php if ($blurb !== ''): ?>
          <p class="mt-3 text-sm leading-6 text-slate-600"><?= nl2br(htmlspecialchars($blurb)) ?></p>
          <?php endif; ?>
          <?php if ($tags !== []): ?>
          <div class="mt-4 flex flex-wrap gap-2">
            <?php foreach ($tags as $tg): ?>
              <?php if (is_string($tg) && $tg !== ''): ?>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200"><?= htmlspecialchars($tg) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <?php if (!empty($sv['publicRosterEnabled'])): ?>
    <section id="roster" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-soft lg:p-8">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-400">Roster</p>
          <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Effectifs publics</h2>
          <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Membres ayant accepté d’apparaître (opt-in dans la fiche personnelle).</p>
        </div>
        <div class="w-full max-w-md">
          <label class="sr-only" for="roster-filter">Filtrer</label>
          <input type="search" id="roster-filter" autocomplete="off" placeholder="Filtrer (indicatif, grade, unité…)" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none focus:border-emerald-400" />
        </div>
      </div>

      <?php if ($publicRosterRows === []): ?>
        <p class="mt-8 text-sm text-slate-600">Aucun membre listé pour l’instant.</p>
      <?php else: ?>
      <div class="mt-8 overflow-hidden rounded-[1.5rem] border border-slate-200">
        <div class="max-h-[520px] overflow-auto scrollbar-thin">
          <table class="min-w-full divide-y divide-slate-200 text-sm roster-table">
            <thead class="sticky top-0 bg-slate-50">
              <tr>
                <th class="px-5 py-4 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Indicatif</th>
                <th class="px-5 py-4 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Grade</th>
                <th class="px-5 py-4 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Fonction</th>
                <th class="px-5 py-4 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Unité</th>
                <th class="px-5 py-4 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Statut</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <?php foreach ($publicRosterRows as $rr): ?>
              <?php
                $st = (string) ($rr['status'] ?? 'active');
                $rowClass = $rosterStatusClass($st);
                $rowLabel = $rosterStatusLabel($st);
                ?>
              <tr class="hover:bg-slate-50 roster-row" data-roster-search="<?= htmlspecialchars(strtolower($rosterIndicatif($rr) . ' ' . ($rr['grade_short'] ?? '') . ' ' . ($rr['role_name'] ?? '') . ' ' . ($rr['unit_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                <td class="px-5 py-4 font-bold text-slate-950"><?= htmlspecialchars($rosterIndicatif($rr)) ?></td>
                <td class="px-5 py-4 font-mono text-slate-700"><?= htmlspecialchars((string) ($rr['grade_short'] ?? '—')) ?></td>
                <td class="px-5 py-4 text-slate-700"><?= htmlspecialchars((string) ($rr['role_name'] ?? '—')) ?></td>
                <td class="px-5 py-4 text-slate-700"><?= htmlspecialchars((string) ($rr['unit_name'] ?? '—')) ?></td>
                <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-semibold <?= htmlspecialchars($rowClass) ?>"><?= htmlspecialchars($rowLabel) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section id="units" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-soft lg:p-8">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-400">Unités</p>
          <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Profils publics des unités</h2>
          <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Fiche courte, chef d’unité et disponibilité.</p>
        </div>
      </div>

      <?php if ($publicUnits === []): ?>
        <p class="mt-8 text-sm text-slate-600">Aucune unité à afficher.</p>
      <?php else: ?>
      <div class="mt-8 grid gap-5 xl:grid-cols-2">
        <?php foreach ($publicUnits as $unit): ?>
          <?php
            $uid = (int) ($unit['id'] ?? 0);
            $mc = (int) ($unitMemberCounts[$uid] ?? 0);
            $tags = $decodeUnitTags($unit);
            $blurb = trim((string) ($unit['public_blurb'] ?? ''));
            $cmdId = (int) ($unit['commander_user_id'] ?? 0);
            $cmdName = $cmdId > 0 ? ($commanderNames[$cmdId] ?? '—') : '—';
            ?>
        <article class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Fiche unité</p>
              <h3 class="mt-2 text-xl font-black tracking-tight text-slate-950"><?= htmlspecialchars((string) ($unit['name'] ?? '')) ?></h3>
              <p class="mt-1 text-sm text-slate-500"><?= htmlspecialchars((string) ($unit['type'] ?? '')) ?></p>
            </div>
            <span class="rounded-full bg-white px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-700 ring-1 ring-slate-200"><?= $mc ?> membres</span>
          </div>
          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl bg-white px-4 py-4 ring-1 ring-slate-200">
              <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Chef d'unité</p>
              <p class="mt-1 text-sm font-bold text-slate-950"><?= htmlspecialchars($cmdName) ?></p>
            </div>
            <div class="rounded-2xl bg-white px-4 py-4 ring-1 ring-slate-200">
              <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Effectif actif</p>
              <p class="mt-1 text-sm font-bold text-slate-950"><?= $mc ?> sur cette unité</p>
            </div>
          </div>
          <?php if ($blurb !== ''): ?>
          <p class="mt-5 text-sm leading-6 text-slate-700"><?= nl2br(htmlspecialchars($blurb)) ?></p>
          <?php endif; ?>
          <?php if ($tags !== []): ?>
          <div class="mt-4 flex flex-wrap gap-2">
            <?php foreach ($tags as $tg): ?>
              <?php if (is_string($tg) && $tg !== ''): ?>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200"><?= htmlspecialchars($tg) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <section id="actions-contact" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-soft">
      <h2 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Actions & contact</h2>
      <div class="flex flex-wrap gap-3">
        <?php if ($showForumCta): ?>
        <a href="<?= htmlspecialchars(url('c/' . $slug . '/forum')) ?>" class="inline-flex items-center px-4 py-2.5 bg-slate-900 text-white text-xs font-bold uppercase tracking-wider rounded-xl hover:bg-emerald-700">Forum</a>
        <?php endif; ?>
        <?php if (!$isLocked): ?>
        <a href="<?= htmlspecialchars(url('c/' . $slug . '/enlistment')) ?>" class="comspec-analytics-cta inline-flex items-center px-4 py-2.5 border border-slate-300 text-xs font-bold uppercase rounded-xl hover:bg-slate-50" data-comspec-zone="pied_page"><?= $publicAudience === 'platform' ? 'Candidater' : 'Rejoindre (candidature)' ?></a>
        <?php endif; ?>
        <a href="<?= url('communities') ?>" class="inline-flex items-center px-4 py-2.5 text-xs font-bold uppercase text-slate-500">Registre</a>
      </div>
      <?php
        $discordUrl = (string) ($cp['discordUrl'] ?? '');
        $contactEmail = (string) ($cp['contactEmail'] ?? '');
        $contactIntro = (string) ($cp['contactIntro'] ?? '');
        $contactFormEnabled = !empty($cp['contactFormEnabled']);
        ?>
      <?php if ($discordUrl !== '' || $contactEmail !== '' || ($userId && $hasMembershipInTenant)): ?>
      <div class="mt-6 border-t border-slate-100 pt-6">
        <?php if ($contactIntro !== ''): ?>
        <p class="text-sm text-slate-600 mb-4"><?= htmlspecialchars($contactIntro) ?></p>
        <?php endif; ?>
        <div class="flex flex-wrap gap-3">
          <?php if ($discordUrl !== ''): ?>
          <a href="<?= htmlspecialchars($discordUrl) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-indigo-500">Discord</a>
          <?php endif; ?>
          <?php if ($contactEmail !== ''): ?>
          <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-50">E-mail</a>
          <?php endif; ?>
          <?php if ($userId && $hasMembershipInTenant): ?>
          <a href="<?= url('messages') ?>" class="inline-flex items-center gap-2 rounded-xl border border-emerald-500 bg-emerald-50 px-4 py-2.5 text-xs font-bold text-emerald-900 hover:bg-emerald-100">Messagerie</a>
          <?php endif; ?>
        </div>
        <?php if ($contactFormEnabled): ?>
        <form method="post" action="<?= htmlspecialchars(url('c/' . rawurlencode($slug) . '/contact')) ?>" class="space-y-3 mt-6">
          <?= \App\Core\Csrf::field() ?>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Votre e-mail</label>
            <input type="email" name="from_email" required class="w-full max-w-md rounded-lg border border-slate-200 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Message</label>
            <textarea name="body" rows="3" required class="w-full max-w-lg rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
          </div>
          <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold uppercase text-white">Envoyer</button>
        </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </section>
  </main>
</div>
<script>
(function () {
  var btn = document.querySelector('[data-copy-code]');
  if (btn && navigator.clipboard) {
    btn.addEventListener('click', function () {
      var t = btn.getAttribute('data-copy-code') || '';
      navigator.clipboard.writeText(t).then(function () { btn.textContent = 'Copié'; setTimeout(function () { btn.textContent = 'Copier le code'; }, 2000); });
    });
  }
  var rf = document.getElementById('roster-filter');
  if (rf) {
    rf.addEventListener('input', function () {
      var q = (rf.value || '').toLowerCase().trim();
      document.querySelectorAll('tr.roster-row').forEach(function (tr) {
        var hay = (tr.getAttribute('data-roster-search') || '');
        tr.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
      });
    });
  }
})();
</script>
