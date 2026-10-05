<?php

declare(strict_types=1);

/** @var array<string, mixed> $unit */
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
?>
<div class="mx-auto max-w-6xl space-y-8 px-4 py-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-2">
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-400">Fiche unité</p>
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
            <a href="<?= $h($structureUrl . '?focus_unit=' . (int) ($unit['id'] ?? 0)) ?>" class="ath-btn ath-btn--solid">Voir dans l’arbre</a>
        </div>
    </header>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Documents</p>
            <p class="mt-2 text-sm font-black uppercase text-slate-900"><?= count($documents) ?></p>
        </div>
    </section>

    <?php if ($canEdit): ?>
    <section class="rounded-3xl border border-slate-200 bg-white p-6 space-y-4">
        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Identité</h2>
        <form method="post" action="<?= $h($saveUrl) ?>" class="grid gap-4 sm:grid-cols-2">
            <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Nom</label>
                <input name="name" value="<?= $h($unit['name'] ?? '') ?>" required maxlength="255" class="ath-field__input w-full">
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500" for="unit-short-label">Abrégé</label>
                <input id="unit-short-label" name="short_label" value="<?= $h($unit['short_label'] ?? '') ?>" maxlength="40" class="ath-field__input w-full" placeholder="<?= $h('Automatique : ' . $autoShort) ?>">
                <p class="mt-1 text-xs text-slate-500">Affiché sur le site et dans l’ATAK à la place du nom complet (gardé au survol). Laissez vide pour l’abrégé automatique.</p>
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Devise</label>
                <input name="motto" value="<?= $h($unit['motto'] ?? '') ?>" maxlength="255" class="ath-field__input w-full">
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Couleur d’accent (ORBAT)</label>
                <input name="accent_color" type="color" value="<?= $h($accent) ?>" class="h-10 w-full rounded-xl border border-slate-200">
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Type d’unité</label>
                <select name="unit_type_id" class="ath-field__select w-full">
                    <option value="">— Non défini —</option>
                    <?php foreach ($unitTypes as $t): ?>
                        <option value="<?= (int) ($t['id'] ?? 0) ?>" <?= (int) ($unit['unit_type_id'] ?? 0) === (int) ($t['id'] ?? 0) ? 'selected' : '' ?>><?= $h($t['label'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Statut</label>
                <select name="admin_status" class="ath-field__select w-full">
                    <?php foreach ($adminStatusOptions as $opt): ?>
                        <option value="<?= $h($opt['id'] ?? '') ?>" <?= $adminStatus === (string) ($opt['id'] ?? '') ? 'selected' : '' ?>><?= $h($opt['label'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Visibilité</label>
                <select name="visibility_level" class="ath-field__select w-full">
                    <?php
                    $curVis = \App\Support\VisibilityLevel::normalize((string) ($unit['visibility_level'] ?? 'normal'));
                    foreach ($visibilityOptions as $opt):
                    ?>
                        <option value="<?= $h($opt['id'] ?? '') ?>" <?= $curVis === (string) ($opt['id'] ?? '') ? 'selected' : '' ?>><?= $h($opt['label'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Description</label>
                <textarea name="description_long" rows="4" class="ath-field__input w-full"><?= $h($unit['description_long'] ?? $unit['orbat_details'] ?? '') ?></textarea>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="ath-btn ath-btn--solid">Enregistrer l’identité</button>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <section class="rounded-3xl border border-slate-200 bg-white p-6 space-y-4">
        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Postes (TO&amp;E)</h2>
        <?php if ($billets === []): ?>
            <p class="text-sm text-slate-600">Aucun poste défini pour cette unité.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($billets as $b):
                    $seat = (string) ($b['seat_status'] ?? '');
                    $tone = $seat === 'vacant' ? 'text-rose-700' : ($seat === 'partial' ? 'text-amber-700' : 'text-emerald-700');
                    ?>
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div>
                            <p class="text-sm font-black uppercase text-slate-900"><?= $h($b['title'] ?? '') ?></p>
                            <p class="text-xs text-slate-500"><?= $h($b['code'] ?? '') ?><?= !empty($b['is_key_post']) ? ' · poste clé' : '' ?></p>
                        </div>
                        <p class="text-sm font-bold <?= $tone ?>"><?= (int) ($b['filled'] ?? 0) ?> / <?= (int) ($b['authorized'] ?? 1) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section id="journal" class="rounded-3xl border border-slate-200 bg-white p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Journal d’activité</h2>
        </div>
        <?php if ($canEdit): ?>
        <form method="post" action="<?= $h($activityUrl) ?>" class="grid gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-2">
            <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Titre</label>
                <input name="title" required maxlength="255" class="ath-field__input w-full" placeholder="Ex. Exercice de nuit">
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Type</label>
                <select name="activity_type_id" class="ath-field__select w-full">
                    <option value="">—</option>
                    <?php foreach ($activityTypes as $t): ?>
                        <option value="<?= (int) ($t['id'] ?? 0) ?>"><?= $h($t['label'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Date</label>
                <input type="date" name="occurred_on" value="<?= $h(date('Y-m-d')) ?>" class="ath-field__input w-full">
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Résumé</label>
                <textarea name="summary" rows="3" class="ath-field__input w-full"></textarea>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="ath-btn ath-btn--solid">Enregistrer une activité</button>
            </div>
        </form>
        <?php endif; ?>
        <?php if ($timeline === []): ?>
            <p class="text-sm text-slate-600">Aucune activité enregistrée pour le moment.</p>
        <?php else: ?>
            <ol class="space-y-3">
                <?php foreach ($timeline as $act):
                    $occurred = trim((string) ($act['occurred_on'] ?? ''));
                    $occurredLabel = $occurred !== '' && ($ts = strtotime($occurred)) ? date('d/m/Y', $ts) : $occurred;
                    ?>
                    <li class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400"><?= $h($occurredLabel) ?><?= !empty($act['activity_type_label']) ? ' · ' . $h($act['activity_type_label']) : '' ?></p>
                        <p class="mt-1 text-sm font-black text-slate-900"><?= $h($act['title'] ?? '') ?></p>
                        <?php if (trim((string) ($act['summary'] ?? '')) !== ''): ?>
                            <p class="mt-1 text-sm text-slate-600"><?= $h($act['summary']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-6 space-y-4">
        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Documents</h2>
        <?php if ($documents === []): ?>
            <p class="text-sm text-slate-600">Aucun document rattaché à cette unité.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($documents as $doc): ?>
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900"><?= $h($doc['title'] ?? '') ?></p>
                            <p class="text-xs text-slate-500"><?= $h($doc['category_name'] ?? $doc['classification_level'] ?? '') ?></p>
                        </div>
                        <?php if (!empty($doc['slug'])): ?>
                            <a class="ath-btn" href="<?= $h(url('documents/' . (string) $doc['slug'])) ?>">Ouvrir</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
