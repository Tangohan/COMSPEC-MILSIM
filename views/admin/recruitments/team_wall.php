<?php
declare(strict_types=1);
$entries = is_array($teamWallEntries ?? null) ? $teamWallEntries : [];
$labels = is_array($teamWallActorLabels ?? null) ? $teamWallActorLabels : [];
$tableMissing = !empty($teamWallTableMissing);
$extended = !empty($teamWallExtendedSchema);
$labelsKind = is_array($teamWallKindLabels ?? null) ? $teamWallKindLabels : [];
$filterKind = trim((string) ($teamWallFilterKind ?? ''));
$sort = (($teamWallSort ?? 'new') === 'old') ? 'old' : 'new';
$countsByKind = is_array($teamWallCountsByKind ?? null) ? $teamWallCountsByKind : [];
$total = (int) ($teamWallTotalCount ?? 0);

$baseUrl = url('back-office/recruitments/equipe');
$buildQuery = static function (?string $kind, string $ord): string {
    $q = [];
    if ($kind !== null && $kind !== '') {
        $q['kind'] = $kind;
    }
    if ($ord === 'old') {
        $q['ord'] = 'old';
    }

    return $q === [] ? '' : ('?' . http_build_query($q));
};
$hrefAll = htmlspecialchars($baseUrl . $buildQuery(null, $sort), ENT_QUOTES, 'UTF-8');
$hrefSortNew = htmlspecialchars($baseUrl . $buildQuery($filterKind !== '' ? $filterKind : null, 'new'), ENT_QUOTES, 'UTF-8');
$hrefSortOld = htmlspecialchars($baseUrl . $buildQuery($filterKind !== '' ? $filterKind : null, 'old'), ENT_QUOTES, 'UTF-8');

$kindBarClass = [
    'general' => 'border-l-4 border-l-slate-400',
    'consigne' => 'border-l-4 border-l-sky-500',
    'planning' => 'border-l-4 border-l-violet-500',
    'veille' => 'border-l-4 border-l-amber-500',
    'idee' => 'border-l-4 border-l-emerald-500',
    'annonce' => 'border-l-4 border-l-rose-500',
];
$kindBadgeClass = [
    'general' => 'bg-slate-100 text-slate-800',
    'consigne' => 'bg-sky-100 text-sky-950',
    'planning' => 'bg-violet-100 text-violet-950',
    'veille' => 'bg-amber-100 text-amber-950',
    'idee' => 'bg-emerald-100 text-emerald-950',
    'annonce' => 'bg-rose-100 text-rose-950',
];
?>
<div class="max-w-4xl mx-auto w-full space-y-8">
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-gradient-to-r from-slate-950 via-slate-900 to-slate-800 px-6 py-6 text-white sm:px-8">
            <p class="text-[10px] font-black uppercase tracking-[0.32em] text-slate-400">Recrutement — équipe</p>
            <h1 class="mt-2 text-2xl font-black tracking-tight">Échanges entre recruteurs</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-300">
                Fil interne commun à toute la communauté : consignes, vacations, idées. Ce n’est pas lié à un dossier individuel.
            </p>
            <?php if ($extended): ?>
                <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-300">
                    <li class="flex items-center gap-2"><span class="inline-block h-1.5 w-1.5 rounded-full bg-sky-400"></span> Thèmes pour classer les messages</li>
                    <li class="flex items-center gap-2"><span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Sujet court optionnel</li>
                    <li class="flex items-center gap-2"><span class="inline-block h-1.5 w-1.5 rounded-full bg-violet-400"></span> Filtres et ordre d’affichage</li>
                </ul>
            <?php endif; ?>
        </div>
        <div class="p-6 sm:p-8 space-y-8">
            <?php if ($tableMissing): ?>
                <p class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    Cette page n’est pas encore activée sur cette installation. Merci de contacter l’équipe technique pour finaliser la mise à jour du site.
                </p>
            <?php else: ?>
                <?php if ($extended && $labelsKind !== []): ?>
                    <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 sm:p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Filtrer par thème</p>
                            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                                <a href="<?= $hrefAll ?>" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 transition <?= $filterKind === '' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300' ?>">
                                    Tous
                                    <?php if ($total > 0): ?>
                                        <span class="tabular-nums opacity-80">(<?= (int) $total ?>)</span>
                                    <?php endif; ?>
                                </a>
                                <?php foreach ($labelsKind as $code => $lab): ?>
                                    <?php
                                    $c = (int) ($countsByKind[$code] ?? 0);
                                    $active = $filterKind === (string) $code;
                                    $hk = htmlspecialchars($baseUrl . $buildQuery((string) $code, $sort), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <a href="<?= $hk ?>" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 transition <?= $active ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300' ?>">
                                        <?= htmlspecialchars((string) $lab, ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($c > 0): ?>
                                            <span class="tabular-nums opacity-80">(<?= $c ?>)</span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/80 pt-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Ordre d’affichage</p>
                            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                                <a href="<?= $hrefSortNew ?>" class="inline-flex rounded-full border px-3 py-1.5 transition <?= $sort === 'new' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300' ?>">Plus récents en premier</a>
                                <a href="<?= $hrefSortOld ?>" class="inline-flex rounded-full border px-3 py-1.5 transition <?= $sort === 'old' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300' ?>">Plus anciens en premier</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 sm:p-5">
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Ordre d’affichage</p>
                        <div class="flex flex-wrap gap-2 text-xs font-semibold">
                            <a href="<?= $hrefSortNew ?>" class="inline-flex rounded-full border px-3 py-1.5 transition <?= $sort === 'new' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300' ?>">Plus récents en premier</a>
                            <a href="<?= $hrefSortOld ?>" class="inline-flex rounded-full border px-3 py-1.5 transition <?= $sort === 'old' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300' ?>">Plus anciens en premier</a>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6 space-y-4">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="return_kind" value="<?= htmlspecialchars($filterKind, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="return_ord" value="<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($extended && $labelsKind !== []): ?>
                        <div>
                            <label for="team_wall_kind" class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-600 mb-2">Thème du message</label>
                            <select id="team_wall_kind" name="team_wall_kind" class="w-full max-w-md rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10">
                                <?php foreach ($labelsKind as $code => $lab): ?>
                                    <option value="<?= htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $lab, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mt-1.5 text-xs text-slate-500">Aide toute l’équipe à repérer rapidement le type d’information.</p>
                        </div>
                        <div>
                            <label for="team_wall_subject" class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-600 mb-2">Sujet (facultatif)</label>
                            <input type="text" id="team_wall_subject" name="team_wall_subject" maxlength="200" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10" placeholder="Ex. Vacation du week-end, Nouveau guide d’entretien…">
                        </div>
                    <?php endif; ?>
                    <div>
                        <label for="team_wall_body" class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-600 mb-2">Votre message</label>
                        <textarea id="team_wall_body" name="team_wall_body" rows="4" maxlength="4000" required class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10" placeholder="Information pour toute l’équipe recrutement…"></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex min-h-[3rem] items-center justify-center rounded-2xl bg-slate-950 px-6 py-3 text-sm font-black text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                            Publier
                        </button>
                    </div>
                </form>

                <?php if ($entries === []): ?>
                    <p class="text-sm text-slate-600">
                        <?php if ($filterKind !== ''): ?>
                            Aucun message pour ce thème pour l’instant. Essayez un autre filtre ou publiez le premier message dans cette catégorie.
                        <?php else: ?>
                            Aucun message pour l’instant. Soyez le premier à laisser une note à l’équipe.
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <ol class="space-y-4">
                        <?php foreach ($entries as $ent): ?>
                            <?php
                            $aid = (int) ($ent['actor_user_id'] ?? 0);
                            $who = $aid > 0 ? ($labels[$aid] ?? ('Compte n°' . $aid)) : '—';
                            $ts = trim((string) ($ent['created_at'] ?? ''));
                            $tsFmt = $ts !== '' ? date('d/m/Y à H:i', strtotime($ts) ?: time()) : '—';
                            $txt = trim((string) ($ent['body'] ?? ''));
                            $pk = trim((string) ($ent['post_kind'] ?? 'general'));
                            if ($pk === '' || !isset($labelsKind[$pk])) {
                                $pk = 'general';
                            }
                            $kindLabel = $labelsKind[$pk] ?? 'Général';
                            $subj = trim((string) ($ent['subject'] ?? ''));
                            $bar = $kindBarClass[$pk] ?? $kindBarClass['general'];
                            $badge = $kindBadgeClass[$pk] ?? $kindBadgeClass['general'];
                            ?>
                            <li class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm <?= htmlspecialchars($bar, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($who, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if ($extended): ?>
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-wider <?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $kindLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <time class="text-[11px] font-semibold tabular-nums text-slate-500" datetime="<?= htmlspecialchars($ts, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tsFmt, ENT_QUOTES, 'UTF-8') ?></time>
                                </div>
                                <?php if ($extended && $subj !== ''): ?>
                                    <p class="mt-2 text-sm font-bold text-slate-900"><?= htmlspecialchars($subj, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <div class="mt-3 text-sm leading-relaxed text-slate-800 whitespace-pre-wrap"><?= htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
