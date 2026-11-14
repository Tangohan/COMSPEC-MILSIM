<?php

declare(strict_types=1);

/**
 * Espace d'unité › Fiche : adéquation, identité, résumé des postes, journal d'activité, documents.
 * Inclus par unit_sheet.php (mêmes variables).
 */
?>
    <?php $unitAdequacy = is_array($unitAdequacy ?? null) ? $unitAdequacy : null; ?>
    <?php if ($unitAdequacy !== null):
        require_once base_path('views/admin/effectifs_workspace/partials/adequacy.php');
        $uaIssues = is_array($unitAdequacy['issues_list'] ?? null) ? $unitAdequacy['issues_list'] : [];
        ?>
    <?= adq_styles() ?>
    <section class="rounded-3xl border border-slate-200 bg-white p-6 space-y-4 adq-scope" id="adequation" aria-labelledby="unit-adequacy-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="unit-adequacy-title" class="text-lg font-black uppercase tracking-tight text-slate-900">Adéquation de l’unité</h2>
            <?= adq_badge($uaIssues === [] ? 'ok' : (string) ($unitAdequacy['status'] ?? ''), true) ?>
        </div>
        <?php if (($unitAdequacy['standard_label'] ?? '') !== ''): ?>
            <p class="text-sm text-slate-600">
                <span>Échelon : <?= $h((string) $unitAdequacy['standard_label']) ?></span>
                · <span>Chef attendu : <?= $h((string) ($unitAdequacy['leader_label'] ?? '')) ?></span>
                · <span>Effectif : <?= (int) ($unitAdequacy['strength'] ?? 0) ?> / <?= $h((string) ($unitAdequacy['strength_expected'] ?? '')) ?></span>
                <?php if (($unitAdequacy['deputy_label'] ?? '') !== '' && (int) ($unitAdequacy['deputy_id'] ?? 0) > 0): ?>
                    · <span>Adjoint : <?= $h((string) $unitAdequacy['deputy_label']) ?></span>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="text-sm text-slate-600">Échelon non renseigné : choisissez-le dans l’organigramme pour comparer cette unité au référentiel.</p>
        <?php endif; ?>
        <?php if ($uaIssues === []): ?>
            <p class="text-sm text-slate-600">Aucun écart : chef, adjoint et effectif correspondent à ce que l’échelon attend.</p>
        <?php else: ?>
            <?= adq_issue_list($uaIssues, is_array($unitAdequacy['people'] ?? null) ? $unitAdequacy['people'] : [], static fn (int $pid): string => effectifs_workspace_url('membres/' . $pid), true) ?>
        <?php endif; ?>
        <p class="adq-note">Capacité de commandement des postes au regard de l’échelon, pas un jugement sur les personnes.</p>
        <p class="adq-note"><a href="<?= $h(effectifs_workspace_url('chaine/adequation')) ?>">Référentiel et réglages</a></p>
    </section>
    <?php endif; ?>

    <?php if ($canEdit): ?>
    <section class="rounded-3xl border border-slate-200 bg-white p-6 space-y-4">
        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Identité</h2>
        <form method="post" action="<?= $h($saveUrl) ?>" class="grid gap-4 sm:grid-cols-2">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
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

    <section id="postes" class="rounded-3xl border border-slate-200 bg-white p-6 space-y-3" aria-labelledby="unit-posts-summary-title">
        <h2 id="unit-posts-summary-title" class="text-lg font-black uppercase tracking-tight text-slate-900">Postes</h2>
        <p class="text-sm text-slate-600">
            <?= (int) $filled ?> / <?= (int) $authorized ?> postes pourvus<?= $vacant > 0 ? ' · ' . (int) $vacant . ' vacant(s)' : '' ?>.
        </p>
        <p><a class="ath-btn" href="<?= $h(url(\App\Support\UnitSpaceTabs::path((int) ($unit['id'] ?? 0), 'postes')) . '#onglet-postes') ?>">Voir et gérer les postes</a></p>
    </section>

    <section id="journal" class="rounded-3xl border border-slate-200 bg-white p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Journal d’activité</h2>
        </div>
        <?php if ($canEdit): ?>
        <form method="post" action="<?= $h($activityUrl) ?>" class="grid gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-2">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
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
