<?php
declare(strict_types=1);
/** @var list<array{id:int,type:string,label:string,options:list<string>,required:bool,position:int,active:bool}> $discordQuestions */
/** @var array<string,string> $discordQuestionTypes */
$rows = $discordQuestions ?? [];
$typeLabels = $discordQuestionTypes ?? [];
$tableMissing = !empty($discordQuestionsTableMissing);
$listUrl = url('back-office/recruitments');
$formAction = url('back-office/recruitments/discord-questions');
$fieldClass = 'canned-field w-full max-w-xl rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/25';
$labelClass = 'mb-1.5 block text-sm font-semibold text-slate-800';
$hintClass = 'mt-1.5 text-xs leading-relaxed text-slate-500';
?>
<div class="recruitment-bureau max-w-3xl mx-auto w-full space-y-8">
    <nav class="overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-5" aria-label="Fil d’Ariane">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600">
            <a href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-transparent px-2 py-1.5 text-slate-700 transition hover:border-slate-200 hover:bg-slate-50">Dossiers de candidature</a>
            <span class="text-slate-300" aria-hidden="true">/</span>
            <span class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 font-bold text-slate-900">Questions Discord</span>
        </div>
    </nav>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-indigo-50/70 px-5 py-6 sm:px-8 sm:py-7">
            <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-indigo-700">Recrutement Discord</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Questions du formulaire</h1>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-slate-600">
                Ces questions apparaissent, dans cet ordre, sur le formulaire public quand le mode de candidature « Recrutement via Discord » est activé (Réglages de l’organisation). Activez-en une ou désactivez-la sans la supprimer si besoin.
            </p>
        </div>

        <?php if ($tableMissing): ?>
            <div class="border-b border-amber-200 bg-amber-50 px-5 py-4 sm:px-8">
                <p class="text-sm leading-relaxed text-amber-950">
                    Ce module n’est pas encore disponible sur cet environnement. Un administrateur technique doit finaliser la mise à jour de la plateforme (exécuter les migrations) ; rechargez ensuite cette page.
                </p>
            </div>
        <?php endif; ?>

        <div class="px-5 py-8 sm:px-8 sm:py-10 space-y-8 <?= $tableMissing ? 'pointer-events-none opacity-50' : '' ?>">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-5 py-3.5 sm:px-6">
                    <h2 class="text-sm font-bold text-slate-900">Nouvelle question</h2>
                </div>
                <div class="p-5 sm:p-6">
                    <form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
                        <?= \App\Core\Csrf::field() ?>
                        <div>
                            <label for="new-label" class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Intitulé de la question</label>
                            <input type="text" id="new-label" name="label" maxlength="255" required class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ex. Quelle est votre disponibilité en soirée ?">
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="new-type" class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Type de question</label>
                                <select id="new-type" name="type" class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach ($typeLabels as $tv => $tl): ?>
                                        <option value="<?= htmlspecialchars($tv, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tl, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="flex items-center gap-2 pt-7 text-sm font-semibold text-slate-800">
                                    <input type="checkbox" name="required" value="1" class="rounded border-slate-300 text-indigo-700">
                                    Réponse obligatoire
                                </label>
                            </div>
                        </div>
                        <div>
                            <label for="new-options" class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Options (uniquement pour « Liste déroulante »)</label>
                            <textarea id="new-options" name="options" rows="3" class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>" placeholder="Une option par ligne"></textarea>
                            <p class="<?= htmlspecialchars($hintClass, ENT_QUOTES, 'UTF-8') ?>">Ignoré pour les autres types de question.</p>
                        </div>
                        <button type="submit" class="recruitment-lms-submit-emerald inline-flex min-h-[2.75rem] items-center justify-center rounded-xl px-6 py-2.5 text-sm font-bold shadow-sm transition">Ajouter la question</button>
                    </form>
                </div>
            </section>

            <?php if ($rows === []): ?>
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 px-6 py-12 text-center">
                    <p class="text-base font-bold text-slate-900">Aucune question pour l’instant</p>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600">Le formulaire Discord ne demandera que le pseudo Discord et les coordonnées de base tant qu’aucune question n’est ajoutée.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($rows as $q): ?>
                        <?php $qid = (int) $q['id']; ?>
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm <?= empty($q['active']) ? 'opacity-60' : '' ?>">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/90 px-4 py-2.5 sm:px-5">
                                <span class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-bold text-indigo-900"><?= htmlspecialchars($typeLabels[$q['type']] ?? $q['type'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-[11px] font-medium tabular-nums text-slate-400"><?= empty($q['active']) ? 'Désactivée' : 'Active' ?></span>
                            </div>
                            <div class="p-4 sm:p-5">
                                <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/discord-questions/' . $qid . '/update'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3.5">
                                    <?= \App\Core\Csrf::field() ?>
                                    <div>
                                        <label class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Intitulé</label>
                                        <input type="text" name="label" value="<?= htmlspecialchars($q['label'], ENT_QUOTES, 'UTF-8') ?>" maxlength="255" required class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Type</label>
                                            <select name="type" class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>">
                                                <?php foreach ($typeLabels as $tv => $tl): ?>
                                                    <option value="<?= htmlspecialchars($tv, ENT_QUOTES, 'UTF-8') ?>" <?= $q['type'] === $tv ? 'selected' : '' ?>><?= htmlspecialchars($tl, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="flex items-center gap-2 pt-7 text-sm font-semibold text-slate-800">
                                                <input type="checkbox" name="required" value="1" <?= !empty($q['required']) ? 'checked' : '' ?> class="rounded border-slate-300 text-indigo-700">
                                                Réponse obligatoire
                                            </label>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Options (liste déroulante)</label>
                                        <textarea name="options" rows="3" class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(implode("\n", $q['options']), ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
                                    <button type="submit" class="recruitment-lms-submit-emerald inline-flex min-h-[2.5rem] items-center justify-center rounded-xl px-5 py-2 text-sm font-bold shadow-sm transition">Enregistrer</button>
                                </form>
                                <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-slate-100 pt-4">
                                    <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/discord-questions/' . $qid . '/toggle'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="active" value="<?= !empty($q['active']) ? '0' : '1' ?>">
                                        <button type="submit" class="text-sm font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2 transition hover:text-slate-900">
                                            <?= !empty($q['active']) ? 'Désactiver' : 'Réactiver' ?>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/discord-questions/' . $qid . '/delete'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Supprimer cette question ? Cette action est définitive.');">
                                        <?= \App\Core\Csrf::field() ?>
                                        <button type="submit" class="text-sm font-semibold text-rose-700 underline decoration-rose-300 underline-offset-2 transition hover:text-rose-900">Supprimer</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
