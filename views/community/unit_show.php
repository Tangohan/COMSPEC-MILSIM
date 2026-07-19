<?php
declare(strict_types=1);

/**
 * Mini-site public d'une unité : landing, bio, informations, chaîne de commandement,
 * sous-unités, tableau des effectifs, statistiques, contact — dérivé du même design
 * system que la fiche communauté (community-landing__*).
 *
 * @var array<string,mixed> $tenant
 * @var array<string,mixed> $unit
 * @var int $unitMemberCount
 * @var list<array{user_id:int,label:string}> $unitRoster
 * @var string|null $unitCommanderName
 * @var list<array<string,mixed>> $unitChildren
 * @var array<int,int> $unitChildrenCounts
 * @var array<string,mixed>|null $unitParent
 * @var array<string,mixed>|null $tenantBranding
 * @var bool $unitIsPreview
 */

$slug = (string) ($tenant['slug'] ?? '');
$tenantName = (string) ($tenant['name'] ?? 'Communauté');
$unit = is_array($unit ?? null) ? $unit : [];
$unitName = trim((string) ($unit['name'] ?? 'Unité'));
$unitType = trim((string) ($unit['type'] ?? ''));
$unitCode = trim((string) ($unit['code'] ?? ''));
$blurb = trim((string) ($unit['public_blurb'] ?? ''));
$memberCount = (int) ($unitMemberCount ?? 0);
$roster = is_array($unitRoster ?? null) ? $unitRoster : [];
$commanderName = trim((string) ($unitCommanderName ?? ''));
$children = is_array($unitChildren ?? null) ? $unitChildren : [];
$childrenCounts = is_array($unitChildrenCounts ?? null) ? $unitChildrenCounts : [];
$parentUnit = is_array($unitParent ?? null) ? $unitParent : null;
$isPreview = !empty($unitIsPreview);

$tenantBranding = is_array($tenantBranding ?? null) ? $tenantBranding : [];
$brandLogo = trim((string) ($tenantBranding['logo_url'] ?? ''));
$brandBanner = trim((string) ($tenantBranding['banner_url'] ?? ''));

$tags = [];
$rawTags = $unit['public_tags'] ?? null;
if (is_string($rawTags) && $rawTags !== '') {
    $decoded = json_decode($rawTags, true);
    $tags = is_array($decoded) ? $decoded : [];
} elseif (is_array($rawTags)) {
    $tags = $rawTags;
}

$totalSubMembers = 0;
foreach ($childrenCounts as $cc) {
    $totalSubMembers += (int) $cc;
}

$backHref = url('c/' . rawurlencode($slug));
$contactHref = $backHref . '#actions-contact';
?>
<div class="community-public-vitrine community-landing min-h-screen bg-slate-100 font-sans text-slate-900 -mx-4 sm:-mx-6 lg:-mx-8">
  <?php if ($isPreview): ?>
  <div class="bg-amber-400 px-6 py-2 text-center text-xs font-black uppercase tracking-wide text-amber-950">
    Aperçu réservé au staff — cette unité n’est pas visible publiquement (« Afficher sur la page publique » désactivé)
  </div>
  <?php endif; ?>

  <div class="community-landing__hero" style="min-height:22rem">
    <div class="community-landing__hero-media" aria-hidden="true">
      <?php if ($brandBanner !== ''): ?>
      <img src="<?= htmlspecialchars($brandBanner, ENT_QUOTES, 'UTF-8') ?>" alt="" data-img-fallback="hero" data-img-label="Visuel indisponible">
      <?php else: ?>
      <div style="width:100%;height:100%;background:radial-gradient(circle at 20% 20%,rgba(16,185,129,.28),transparent 36%),linear-gradient(160deg,#020617,#0f172a 55%,#022c22);"></div>
      <?php endif; ?>
    </div>
    <div class="community-landing__hero-scrim"></div>
    <div class="community-landing__hero-inner">
      <div class="community-landing__brand-row">
        <?php if ($brandLogo !== ''): ?>
        <img class="community-landing__logo" src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Emblème <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?>" data-img-fallback="logo" data-img-label="Emblème indisponible">
        <?php endif; ?>
        <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-white">← <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></a>
      </div>
      <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-300"><?= htmlspecialchars($unitType !== '' ? $unitType : 'Unité', ENT_QUOTES, 'UTF-8') ?><?= $unitCode !== '' ? ' · ' . htmlspecialchars($unitCode, ENT_QUOTES, 'UTF-8') : '' ?></p>
      <h1 class="community-landing__name"><?= htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8') ?></h1>
      <?php if ($tags !== []): ?>
      <div class="mt-4 flex flex-wrap gap-2">
        <?php foreach ($tags as $tg): ?>
          <?php if (is_string($tg) && $tg !== ''): ?>
          <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-white"><?= htmlspecialchars($tg, ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="community-landing__cta-row">
        <a href="<?= htmlspecialchars($contactHref, ENT_QUOTES, 'UTF-8') ?>" class="community-landing__cta community-landing__cta--primary">Contacter la communauté</a>
        <a href="#roster" class="community-landing__cta community-landing__cta--ghost">Voir les effectifs</a>
        <?php if ($children !== []): ?>
        <a href="#sub-units" class="community-landing__cta community-landing__cta--ghost">Sous-unités</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <main class="mx-auto max-w-6xl space-y-8 px-6 py-10 lg:px-8">

    <section class="grid gap-4 sm:grid-cols-3">
      <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-soft">
        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Effectif actif</p>
        <p class="mt-2 text-3xl font-black tracking-tight text-slate-950"><?= $memberCount ?></p>
        <p class="mt-1 text-xs text-slate-500">membre<?= $memberCount > 1 ? 's' : '' ?> rattaché<?= $memberCount > 1 ? 's' : '' ?> directement</p>
      </div>
      <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-soft">
        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Sous-unités</p>
        <p class="mt-2 text-3xl font-black tracking-tight text-slate-950"><?= count($children) ?></p>
        <p class="mt-1 text-xs text-slate-500"><?= $totalSubMembers ?> membre<?= $totalSubMembers > 1 ? 's' : '' ?> au total dans ces sous-unités</p>
      </div>
      <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-soft">
        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Chef d’unité</p>
        <p class="mt-2 text-lg font-black tracking-tight text-slate-950"><?= htmlspecialchars($commanderName !== '' ? $commanderName : 'Non désigné', ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($parentUnit): ?>
        <p class="mt-1 text-xs text-slate-500">Rattachée à <?= htmlspecialchars((string) ($parentUnit['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($blurb !== ''): ?>
    <section id="bio" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-soft lg:p-8">
      <p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-400">Présentation</p>
      <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">À propos de <?= htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="mt-4 max-w-3xl whitespace-pre-wrap text-sm leading-6 text-slate-600"><?= nl2br(htmlspecialchars($blurb, ENT_QUOTES, 'UTF-8')) ?></p>
    </section>
    <?php endif; ?>

    <section id="command" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-soft lg:p-8">
      <p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-400">Commandement</p>
      <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Chef d’unité</h2>
      <div class="mt-6 flex flex-wrap items-center gap-4">
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-lg font-black text-emerald-800 ring-1 ring-emerald-200">
          <?= htmlspecialchars($commanderName !== '' ? mb_strtoupper(mb_substr($commanderName, 0, 1)) : '?', ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div>
          <p class="text-lg font-black tracking-tight text-slate-950"><?= htmlspecialchars($commanderName !== '' ? $commanderName : 'Non désigné', ENT_QUOTES, 'UTF-8') ?></p>
          <?php if ($parentUnit): ?>
          <p class="mt-1 text-sm text-slate-500">Unité rattachée à <?= htmlspecialchars((string) ($parentUnit['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
          <?php else: ?>
          <p class="mt-1 text-sm text-slate-500">Responsable de cette unité au sein de <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <?php if ($children !== []): ?>
    <section id="sub-units" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-soft lg:p-8">
      <p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-400">Structure</p>
      <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Sous-unités</h2>
      <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <?php foreach ($children as $child): ?>
          <?php
          $csId = (int) ($child['id'] ?? 0);
          $csSlug = trim((string) ($child['slug'] ?? ''));
          $csCount = (int) ($childrenCounts[$csId] ?? 0);
          $csPublic = !empty($child['show_on_public_page']);
          $csHref = $csPublic && $csSlug !== '' ? url('c/' . rawurlencode($slug) . '/unite/' . rawurlencode($csSlug)) : null;
          ?>
        <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400"><?= htmlspecialchars((string) ($child['type'] ?? 'unité'), ENT_QUOTES, 'UTF-8') ?></p>
              <h3 class="mt-1 text-base font-black tracking-tight text-slate-950"><?= htmlspecialchars((string) ($child['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
            </div>
            <span class="rounded-full bg-white px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-700 ring-1 ring-slate-200"><?= $csCount ?> pers.</span>
          </div>
          <?php if ($csHref !== null): ?>
          <a href="<?= htmlspecialchars($csHref, ENT_QUOTES, 'UTF-8') ?>" class="mt-3 inline-flex text-xs font-bold uppercase tracking-wide text-emerald-700 hover:text-emerald-900">Ouvrir la fiche →</a>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section id="roster" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-soft lg:p-8">
      <p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-400">Effectifs</p>
      <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Tableau des effectifs</h2>
      <?php if ($roster === []): ?>
      <p class="mt-6 text-sm text-slate-600">Aucun membre listé pour l’instant.</p>
      <?php else: ?>
      <div class="mt-6 overflow-hidden rounded-[1.5rem] border border-slate-200">
        <div class="max-h-[420px] overflow-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="sticky top-0 bg-slate-50">
              <tr>
                <th class="px-5 py-3 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">#</th>
                <th class="px-5 py-3 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Membre</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <?php foreach ($roster as $i => $r): ?>
              <tr class="hover:bg-slate-50">
                <td class="px-5 py-3 text-slate-400 tabular-nums"><?= (int) $i + 1 ?></td>
                <td class="px-5 py-3 font-bold text-slate-950"><?= htmlspecialchars((string) ($r['label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </section>

    <section id="actions-contact" class="rounded-[2rem] border border-slate-200 bg-slate-950 p-6 text-white shadow-soft lg:p-8">
      <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-300">Contact</p>
      <h2 class="mt-2 text-2xl font-black tracking-tight text-white">Intéressé par cette unité ?</h2>
      <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">Le contact et la candidature se font au niveau de la communauté <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?>, qui orientera votre demande vers cette unité.</p>
      <div class="mt-5 flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars($backHref . '#actions-contact', ENT_QUOTES, 'UTF-8') ?>" class="community-landing__cta community-landing__cta--primary">Contacter la communauté</a>
        <a href="<?= htmlspecialchars(url('c/' . rawurlencode($slug) . '/enlistment'), ENT_QUOTES, 'UTF-8') ?>" class="community-landing__cta community-landing__cta--ghost">Candidater</a>
      </div>
    </section>

  </main>
</div>
