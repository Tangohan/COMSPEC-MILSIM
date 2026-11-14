<?php

declare(strict_types=1);

/**
 * Espace d'unité du back-office : fiche de l'unité et onglets (Fiche, Postes, Membres, Chaîne de commandement,
 * Qualifications). Un seul onglet est calculé et rendu par requête (`?onglet=`) ; sans JavaScript, chaque onglet est
 * un lien. Les anciennes adresses (fiche, #postes, #journal, #adequation) restent valables.
 */

$unit = is_array($unit ?? null) ? $unit : [];
$unitTypes = is_array($unitTypes ?? null) ? $unitTypes : [];
$manning = is_array($manning ?? null) ? $manning : [];
$activityTypes = is_array($activityTypes ?? null) ? $activityTypes : [];
$timeline = is_array($timeline ?? null) ? $timeline : [];
$documents = is_array($documents ?? null) ? $documents : [];
$commander = is_array($commander ?? null) ? $commander : null;
$derivedCommand = is_array($derivedCommand ?? null) ? $derivedCommand : [];
$adminStatusOptions = is_array($adminStatusOptions ?? null) ? $adminStatusOptions : [];
$visibilityOptions = is_array($visibilityOptions ?? null) ? $visibilityOptions : [];
$canEdit = !empty($canEdit);
$csrf = (string) ($csrf ?? '');
$structureUrl = (string) ($structureUrl ?? url('back-office/organisation/structure'));
$saveUrl = (string) ($saveUrl ?? '#');
$activityUrl = (string) ($activityUrl ?? '#');
$unitTypeLabel = (string) ($unitTypeLabel ?? '');
$jobRoleOptions = is_array($jobRoleOptions ?? null) ? $jobRoleOptions : [];
$postsUrl = (string) ($postsUrl ?? '#');

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');

$unitName = trim((string) ($unit['name'] ?? 'Unité'));
$autoShort = mb_strlen($unitName) > \App\Support\UnitAbbreviation::MAX_LENGTH
    ? \App\Support\UnitAbbreviation::auto($unitName)
    : $unitName;
$motto = trim((string) ($unit['motto'] ?? ''));
$accent = trim((string) ($unit['accent_color'] ?? $unit['public_accent_color'] ?? ''));
if ($accent === '' || !preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) {
    $accent = '#0f172a';
}
$adminStatus = \App\Support\UnitAdminStatus::normalize((string) ($unit['admin_status'] ?? 'active'));
$authorized = (int) ($manning['authorized'] ?? 0);
$filled = (int) ($manning['filled'] ?? 0);
$vacant = (int) ($manning['vacant'] ?? 0);
$billets = is_array($manning['billets'] ?? null) ? $manning['billets'] : [];
$badgePath = trim((string) ($unit['badge_media_path'] ?? $unit['orbat_icon_path'] ?? ''));
$cmdLabel = '';
if ($commander !== null) {
    $cmdLabel = trim((string) ($commander['callsign'] ?? '')) !== ''
        ? (string) $commander['callsign']
        : trim((string) ($commander['display_name'] ?? ''));
}
$deputyLabel = '';
foreach ($derivedCommand as $dc) {
    if (strtolower((string) ($dc['kind'] ?? '')) === 'deputy') {
        $deputyLabel = (string) ($dc['label'] ?? '');
        break;
    }
}
$unitSpaceTab = (string) ($unitSpaceTab ?? 'fiche');
$unitSpaceTabs = is_array($unitSpaceTabs ?? null) ? $unitSpaceTabs : ['fiche'];
$unitSpaceDenied = (string) ($unitSpaceDenied ?? '');
$unitIdNow = (int) ($unit['id'] ?? 0);
?>
<div class="mx-auto max-w-6xl space-y-8 px-4 py-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-3">
                <?php if ($badgePath !== ''): ?>
                    <img src="<?= $h($badgePath) ?>" alt="" class="h-12 w-12 rounded-xl object-cover border border-slate-200" width="48" height="48">
                <?php else: ?>
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl text-white text-lg font-black" style="background:<?= $h($accent) ?>" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr($unitName, 0, 1))) ?></span>
                <?php endif; ?>
                <div>
                    <h1 class="text-3xl font-black tracking-tight text-slate-900"><?= $h($unitName) ?></h1>
                    <?php if ($motto !== ''): ?>
                        <p class="mt-1 text-sm italic text-slate-600">« <?= $h($motto) ?> »</p>
                    <?php endif; ?>
                </div>
            </div>
            <p class="text-sm text-slate-600">
                <?= $unitTypeLabel !== '' ? $h($unitTypeLabel) . ' · ' : '' ?>
                <?= $h(\App\Support\UnitAdminStatus::label($adminStatus)) ?>
                <?php if ($authorized > 0): ?>
                    · <?= (int) $filled ?> / <?= (int) $authorized ?> postes pourvus
                <?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= $h($structureUrl) ?>" class="ath-btn">Organigramme</a>
            <a href="<?= $h($structureUrl . '?focus_unit=' . $unitIdNow) ?>" class="ath-btn ath-btn--solid">Voir dans l’arbre</a>
        </div>
    </header>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Repères de l’unité">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Commandant</p>
            <p class="mt-2 text-sm font-black uppercase text-slate-900"><?= $h($cmdLabel !== '' ? $cmdLabel : 'Vacant') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Adjoint</p>
            <p class="mt-2 text-sm font-black uppercase text-slate-900"><?= $h($deputyLabel !== '' ? $deputyLabel : 'Vacant') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Postes</p>
            <p class="mt-2 text-sm font-black uppercase text-slate-900"><?= (int) $filled ?> / <?= (int) $authorized ?></p>
            <?php if ($vacant > 0): ?><p class="mt-1 text-xs text-amber-700"><?= (int) $vacant ?> vacant(s)</p><?php endif; ?>
        </div>
        <?php if ($unitSpaceTab === 'fiche'): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Documents</p>
            <p class="mt-2 text-sm font-black uppercase text-slate-900"><?= count($documents) ?></p>
        </div>
        <?php endif; ?>
    </section>

    <nav class="ath-periods" aria-label="Espace de l’unité">
        <?php foreach ($unitSpaceTabs as $tabKey): ?>
            <a href="<?= $h(url(\App\Support\UnitSpaceTabs::path($unitIdNow, $tabKey)) . '#onglet-' . $tabKey) ?>" class="ath-btn"<?= $tabKey === $unitSpaceTab ? ' aria-current="true"' : '' ?>><?= $h(\App\Support\UnitSpaceTabs::label($tabKey)) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($unitSpaceDenied !== ''): ?>
        <p class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">L’onglet « <?= $h(\App\Support\UnitSpaceTabs::label($unitSpaceDenied)) ?> » est réservé aux personnes qui gèrent les effectifs.</p>
    <?php endif; ?>

    <div id="onglet-<?= $h($unitSpaceTab) ?>" class="space-y-8">
        <?php require __DIR__ . '/unit_space/' . $unitSpaceTab . '.php'; ?>
    </div>
</div>
