<?php

declare(strict_types=1);

/**
 * Espace d'unité › Postes : tableau d'effectifs de l'unité (lecture pour tous, écriture organization.orbat.manage).
 * Inclus par unit_sheet.php (mêmes variables).
 */
?>
    <section id="postes" class="rounded-3xl border border-slate-200 bg-white p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Postes (TO&amp;E)</h2>
            <?php if ($billets !== []): ?>
                <p class="text-sm font-bold text-slate-600"><?= $filled ?> / <?= $authorized ?> postes pourvus</p>
            <?php endif; ?>
        </div>
        <p class="text-sm text-slate-600">L’effectif prévu indique combien de personnes le poste doit compter. Un poste est pourvu par la personne qui y est désignée ou, à défaut, par un membre de l’unité qui exerce l’emploi rattaché. Ces chiffres alimentent la composition de l’équipe sur la fiche de chaque membre.</p>
        <?php if ($canEdit): ?>
        <form method="post" action="<?= $h($postsUrl) ?>" class="space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <?php if ($billets === []): ?>
                <p class="text-sm text-slate-600">Aucun poste défini pour cette unité.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($billets as $b):
                        $bid = (int) ($b['id'] ?? 0);
                        $bJob = (int) ($b['job_role_id'] ?? 0);
                        $bVacant = (int) ($b['vacant'] ?? 0);
                        ?>
                        <li class="grid gap-3 py-3 sm:grid-cols-[minmax(0,2fr)_7rem_minmax(0,2fr)_auto] sm:items-end">
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500" for="poste-<?= $bid ?>-titre">Intitulé du poste</label>
                                <input id="poste-<?= $bid ?>-titre" name="posts[<?= $bid ?>][title]" value="<?= $h($b['title'] ?? '') ?>" maxlength="150" class="ath-field__input w-full">
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500" for="poste-<?= $bid ?>-effectif">Effectif prévu</label>
                                <input id="poste-<?= $bid ?>-effectif" type="number" min="1" max="99" name="posts[<?= $bid ?>][authorized_slots]" value="<?= (int) ($b['authorized'] ?? 1) ?>" class="ath-field__input w-full">
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500" for="poste-<?= $bid ?>-emploi">Emploi rattaché</label>
                                <select id="poste-<?= $bid ?>-emploi" name="posts[<?= $bid ?>][job_role_id]" class="ath-field__select w-full">
                                    <option value="">Aucun</option>
                                    <?php foreach ($jobRoleOptions as $jr): if (!empty($jr['hidden']) && $bJob !== (int) $jr['id']) continue; ?>
                                        <option value="<?= (int) $jr['id'] ?>" <?= $bJob === (int) $jr['id'] ? 'selected' : '' ?>><?= $h((!empty($jr['abbreviation']) ? $jr['abbreviation'] . ' · ' : '') . $jr['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <p class="text-sm font-bold <?= $bVacant > 0 ? 'text-amber-700' : 'text-emerald-700' ?>"><?= $h(sprintf((int) ($b['filled'] ?? 0) > 1 ? '%d / %d pourvus' : '%d / %d pourvu', (int) ($b['filled'] ?? 0), (int) ($b['authorized'] ?? 1))) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <fieldset class="grid gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-[minmax(0,2fr)_7rem_minmax(0,2fr)]">
                <legend class="px-1 text-[10px] font-black uppercase tracking-wider text-slate-500">Ajouter un poste</legend>
                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500" for="poste-nouveau-titre">Intitulé</label>
                    <input id="poste-nouveau-titre" name="new_title" maxlength="150" class="ath-field__input w-full" placeholder="Ex. Tireur de précision">
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500" for="poste-nouveau-effectif">Effectif prévu</label>
                    <input id="poste-nouveau-effectif" type="number" min="1" max="99" name="new_slots" value="1" class="ath-field__input w-full">
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500" for="poste-nouveau-emploi">Emploi rattaché</label>
                    <select id="poste-nouveau-emploi" name="new_job_role_id" class="ath-field__select w-full">
                        <option value="">Aucun</option>
                        <?php foreach ($jobRoleOptions as $jr): if (!empty($jr['hidden'])) continue; ?>
                            <option value="<?= (int) $jr['id'] ?>"><?= $h((!empty($jr['abbreviation']) ? $jr['abbreviation'] . ' · ' : '') . $jr['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </fieldset>
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer les postes</button>
        </form>
        <?php elseif ($billets === []): ?>
            <p class="text-sm text-slate-600">Aucun poste défini pour cette unité.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($billets as $b): ?>
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <?php $bDecor = ($jobRoleDecorations ?? [])[(int) ($b['job_role_id'] ?? 0)] ?? null; ?>
                        <p class="text-sm font-black uppercase text-slate-900"><?= \App\Support\JobRoleBadge::html($bDecor) ?><?= $h($b['title'] ?? '') ?><?php if (!empty($b['is_key_post'])): ?> <span class="text-xs font-semibold normal-case text-slate-500">· poste clé</span><?php endif; ?></p>
                        <p class="text-sm font-bold <?= (int) ($b['vacant'] ?? 0) > 0 ? 'text-amber-700' : 'text-emerald-700' ?>"><?= (int) ($b['filled'] ?? 0) ?> / <?= (int) ($b['authorized'] ?? 1) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
