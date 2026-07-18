<?php
/**
 * Onglet Bilans — fiche personnel.
 *
 * @var array $targetUser
 * @var bool $canViewBilans
 * @var bool $canCreateBilans
 * @var bool $personnelStageBilansSchemaReady
 * @var list<array<string,mixed>> $personnelStageBilans
 * @var list<array<string,mixed>> $personnelRecruitmentBilans
 * @var list<string> $bilanStageOptions
 * @var string|null $adminNotes
 * @var bool $canViewCommandNotes
 * @var array|null $latestEnlistment
 * @var string|null $rpStage
 * @var string|null $rpNotes
 * @var int|null $rpProgress
 */
$canViewBilans = !empty($canViewBilans);
$canCreateBilans = !empty($canCreateBilans);
$personnelStageBilansSchemaReady = !empty($personnelStageBilansSchemaReady);
$personnelStageBilans = is_array($personnelStageBilans ?? null) ? $personnelStageBilans : [];
$personnelRecruitmentBilans = is_array($personnelRecruitmentBilans ?? null) ? $personnelRecruitmentBilans : [];
$bilanStageOptions = is_array($bilanStageOptions ?? null) ? $bilanStageOptions : [];
$adminNotes = isset($adminNotes) ? trim((string) $adminNotes) : '';
$rpStage = isset($rpStage) ? trim((string) $rpStage) : '';
$rpNotes = isset($rpNotes) ? trim((string) $rpNotes) : '';
$rpProgress = isset($rpProgress) && $rpProgress !== null ? (int) $rpProgress : null;

$bilanKindFr = static function (?string $k): string {
    return match (trim((string) $k)) {
        'recrutement' => 'Recrutement',
        'rh' => 'Ressources humaines',
        'commandement' => 'Commandement',
        default => 'Bilan',
    };
};
$bilanRatingFr = static function (?int $r): string {
    return match ($r) {
        5 => '5 — Très satisfaisant',
        4 => '4 — Bon',
        3 => '3 — Correct',
        2 => '2 — En-dessous des attentes',
        1 => '1 — À améliorer',
        default => '',
    };
};
$ratingLabels = [
    5 => 'Très satisfaisant',
    4 => 'Bon',
    3 => 'Correct',
    2 => 'En-dessous des attentes',
    1 => 'À améliorer',
];
$hasAnyBilan = $personnelStageBilans !== [] || $personnelRecruitmentBilans !== []
    || ($adminNotes !== '' && !empty($canViewCommandNotes))
    || $rpStage !== '' || $rpNotes !== '';
?>
<div class="space-y-6" x-show="tab === 'bilans'" x-cloak id="bilans">
    <section class="rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50/90 via-white to-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xs font-black uppercase tracking-[0.28em] text-emerald-900">Bilans du dossier</h2>
                <p class="mt-2 max-w-2xl text-sm text-slate-600 leading-relaxed">
                    Retrouvez ici les bilans déjà enregistrés (recrutement, RH, commandement) et, si vous y êtes habilité, créez un bilan lié à une étape du parcours.
                </p>
            </div>
            <?php if ($rpStage !== '' || $rpProgress !== null): ?>
            <div class="rounded-xl border border-emerald-200 bg-white px-4 py-3 text-right shadow-sm">
                <?php if ($rpStage !== ''): ?>
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-800/80">Étape en cours</p>
                <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($rpStage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($rpProgress !== null): ?>
                <p class="mt-1 text-xs font-semibold tabular-nums text-slate-600"><?= $rpProgress ?> % d’avancement</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!$canViewBilans): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-600">Les bilans de ce dossier sont réservés au titulaire et au personnel habilité.</p>
    </section>
    <?php else: ?>

    <?php if ($personnelRecruitmentBilans !== []): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-800 mb-4">Bilans de recrutement</h3>
        <div class="space-y-3">
            <?php foreach ($personnelRecruitmentBilans as $rb):
                $rr = isset($rb['rating']) ? (int) $rb['rating'] : null;
                $rbWhen = !empty($rb['created_at']) ? date('d/m/Y', strtotime((string) $rb['created_at']) ?: time()) : '—';
                $rbComment = trim((string) ($rb['comment'] ?? ''));
                ?>
            <article class="rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3.5">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars((string) ($rb['source_label'] ?? 'Bilan recrutement'), ENT_QUOTES, 'UTF-8') ?></p>
                    <span class="text-[10px] font-semibold tabular-nums text-slate-500"><?= htmlspecialchars($rbWhen, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php if ($rr !== null && isset($ratingLabels[$rr])): ?>
                <p class="mt-1 text-xs font-semibold text-emerald-800"><?= $rr ?> — <?= htmlspecialchars($ratingLabels[$rr], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($rbComment !== ''): ?>
                <p class="mt-2 text-sm text-slate-700 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($rbComment, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if (!empty($rb['enlistment_id']) && !empty($canCreateBilans)): ?>
                <p class="mt-2">
                    <a href="<?= htmlspecialchars(url('back-office/recruitments/' . (int) $rb['enlistment_id'] . '?dossier=1#bilan-recrutement'), ENT_QUOTES, 'UTF-8') ?>" class="text-[10px] font-black uppercase tracking-wider text-[#059669] hover:text-emerald-800">Voir le dossier de recrutement</a>
                </p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php elseif (is_array($latestEnlistment ?? null) && $latestEnlistment !== [] && !empty($canCreateBilans)): ?>
    <section class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 px-5 py-4">
        <p class="text-sm text-slate-600">Aucun bilan de recrutement n’est encore renseigné pour ce dossier. Ils apparaissent ici dès qu’ils sont saisis côté recrutement (bilan à 30 jours).</p>
    </section>
    <?php endif; ?>

    <?php if ($personnelStageBilans !== []): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-800 mb-4">Bilans d’étape</h3>
        <div class="space-y-3">
            <?php foreach ($personnelStageBilans as $sb):
                $sbKind = $bilanKindFr((string) ($sb['bilan_kind'] ?? ''));
                $sbRating = isset($sb['rating']) ? (int) $sb['rating'] : null;
                $sbRatingLabel = $bilanRatingFr($sbRating);
                $sbDate = !empty($sb['event_date']) ? date('d/m/Y', strtotime((string) $sb['event_date']) ?: time()) : '—';
                $sbAuthor = trim((string) ($sb['author_display_name'] ?? ''));
                if ($sbAuthor === '') {
                    $sbAuthor = trim((string) ($sb['author_callsign'] ?? ''));
                }
                ?>
            <article class="rounded-xl border-l-4 border-l-[#059669] border border-slate-200 bg-white px-4 py-3.5 shadow-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-emerald-800"><?= htmlspecialchars($sbKind, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($sb['stage_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <h4 class="mt-0.5 text-sm font-bold text-slate-900"><?= htmlspecialchars((string) ($sb['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h4>
                    </div>
                    <span class="text-[10px] font-semibold tabular-nums text-slate-500"><?= htmlspecialchars($sbDate, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php if ($sbRatingLabel !== ''): ?>
                <p class="mt-1 text-xs font-semibold text-slate-700"><?= htmlspecialchars($sbRatingLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <p class="mt-2 text-sm text-slate-700 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars((string) ($sb['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($sbAuthor !== ''): ?>
                <p class="mt-2 text-[10px] text-slate-500">Rédigé par <?= htmlspecialchars($sbAuthor, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php elseif (!$hasAnyBilan): ?>
    <section class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/90 px-5 py-8 text-center">
        <p class="text-sm font-semibold text-slate-700">Aucun bilan n’est encore consigné sur ce dossier.</p>
        <p class="mt-1 text-xs text-slate-500">Les bilans de recrutement, RH ou commandement apparaîtront ici dès qu’ils seront enregistrés.</p>
    </section>
    <?php endif; ?>

    <?php if (!empty($canViewCommandNotes) && $adminNotes !== ''): ?>
    <section class="rounded-2xl border border-rose-200/80 bg-rose-50/40 p-5 shadow-sm">
        <h3 class="text-[11px] font-black uppercase tracking-[0.28em] text-rose-900 mb-2">Notes de commandement</h3>
        <p class="text-sm text-slate-800 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($adminNotes, ENT_QUOTES, 'UTF-8') ?></p>
        <button type="button" @click="tab = 'historique'" class="mt-3 text-[10px] font-black uppercase tracking-wider text-[#059669] hover:text-emerald-800">Ouvrir l’onglet Historique & notes</button>
    </section>
    <?php endif; ?>

    <?php if ($rpNotes !== ''): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-800 mb-2">Notes de suivi d’immersion</h3>
        <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($rpNotes, ENT_QUOTES, 'UTF-8') ?></p>
    </section>
    <?php endif; ?>

    <?php if ($canCreateBilans): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" x-data="{ stage: '<?= htmlspecialchars((string) ($bilanStageOptions[0] ?? 'Autre'), ENT_QUOTES, 'UTF-8') ?>' }">
        <h3 class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-800 mb-1">Créer un bilan d’étape</h3>
        <p class="mb-5 text-xs text-slate-500 leading-relaxed">Choisissez le type de bilan, l’étape concernée, puis rédigez l’appréciation. Le formulaire est réservé aux référents habilités.</p>
        <?php if (!$personnelStageBilansSchemaReady): ?>
        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">La création de bilans d’étape sera disponible après la prochaine mise à jour technique côté hébergement.</p>
        <?php else: ?>
        <form method="post" action="<?= htmlspecialchars(url('personnel/' . (int) ($targetUser['id'] ?? 0) . '/bilans'), ENT_QUOTES, 'UTF-8') ?>" class="grid gap-4 sm:grid-cols-2">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label for="bilan_kind" class="mb-1.5 block text-xs font-bold text-slate-700">Type de bilan</label>
                <select id="bilan_kind" name="bilan_kind" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-[#059669] focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    <option value="rh">Ressources humaines</option>
                    <option value="commandement">Commandement</option>
                    <option value="recrutement">Recrutement</option>
                </select>
            </div>
            <div>
                <label for="bilan_event_date" class="mb-1.5 block text-xs font-bold text-slate-700">Date du bilan</label>
                <input type="date" id="bilan_event_date" name="event_date" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-[#059669] focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
            </div>
            <div>
                <label for="bilan_stage" class="mb-1.5 block text-xs font-bold text-slate-700">Étape du parcours</label>
                <select id="bilan_stage" name="stage_label" x-model="stage" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-[#059669] focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    <?php foreach ($bilanStageOptions as $stOpt): ?>
                    <option value="<?= htmlspecialchars((string) $stOpt, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $stOpt, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div x-show="stage === 'Autre'" x-cloak>
                <label for="bilan_stage_custom" class="mb-1.5 block text-xs font-bold text-slate-700">Précisez l’étape</label>
                <input type="text" id="bilan_stage_custom" name="stage_label_custom" maxlength="120" placeholder="Ex. Retour de mission" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-[#059669] focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
            </div>
            <div class="sm:col-span-2">
                <label for="bilan_title" class="mb-1.5 block text-xs font-bold text-slate-700">Titre</label>
                <input type="text" id="bilan_title" name="title" required maxlength="180" placeholder="Ex. Bilan d’intégration — premier mois" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-[#059669] focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
            </div>
            <div>
                <label for="bilan_rating" class="mb-1.5 block text-xs font-bold text-slate-700">Appréciation globale (facultatif)</label>
                <select id="bilan_rating" name="rating" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-[#059669] focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    <option value="">Sans note</option>
                    <?php foreach ($ratingLabels as $ri => $rl): ?>
                    <option value="<?= (int) $ri ?>"><?= (int) $ri ?> — <?= htmlspecialchars($rl, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="bilan_body" class="mb-1.5 block text-xs font-bold text-slate-700">Contenu du bilan</label>
                <textarea id="bilan_body" name="body" rows="5" required maxlength="8000" placeholder="Points forts, axes d’amélioration, décisions ou prochaines étapes…" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-[#059669] focus:outline-none focus:ring-2 focus:ring-emerald-500/20"></textarea>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="inline-flex min-h-[2.5rem] items-center rounded-xl bg-[#059669] px-5 text-[11px] font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-emerald-700">Enregistrer le bilan</button>
            </div>
        </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php endif; ?>
</div>
