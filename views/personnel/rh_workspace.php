<?php
declare(strict_types=1);
$trainingAllowed = !empty($rhTrainingAllowed);
$charterReady = !empty($rhCharterReady);
$charterAccepted = !empty($rhCharterAccepted);
$seniorityLines = is_array($rhSeniorityLines ?? null) ? $rhSeniorityLines : [];
$dossierCompleteness = is_array($rhDossierCompleteness ?? null) ? $rhDossierCompleteness : ['score' => 0, 'filled' => 0, 'total' => 0, 'missing' => []];
$dossierScore = (int) ($dossierCompleteness['score'] ?? 0);
$dossierMissing = is_array($dossierCompleteness['missing'] ?? null) ? $dossierCompleteness['missing'] : [];
$testerCommunities = is_array($rhTesterCommunities ?? null) ? $rhTesterCommunities : [];
$rolloutRows = is_array($rhRolloutRows ?? null) ? $rhRolloutRows : [];
$greetingName = trim((string) ($rhGreetingName ?? ''));
$rhWorkspaceCsrf = htmlspecialchars((string) ($rhWorkspaceCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$absencesSchemaReady = !empty($rhAbsencesSchemaReady);
$personnelAbsences = is_array($rhPersonnelAbsences ?? null) ? $rhPersonnelAbsences : [];
$activeAbsences = is_array($rhActiveAbsences ?? null) ? $rhActiveAbsences : [];
$absenceReasonLabels = is_array($rhAbsenceReasonLabels ?? null) ? $rhAbsenceReasonLabels : [];
$mobilitySchemaReady = !empty($rhMobilitySchemaReady);
$myMobility = is_array($rhMyMobility ?? null) ? $rhMyMobility : [];
$mobilityTypeLabels = is_array($rhMobilityTypeLabels ?? null) ? $rhMobilityTypeLabels : [];
$hrDocsSchemaReady = !empty($rhHrDocsSchemaReady);
$myHrDocs = is_array($rhMyHrDocs ?? null) ? $rhMyHrDocs : [];
$hrDocTypeLabels = is_array($rhHrDocTypeLabels ?? null) ? $rhHrDocTypeLabels : [];

$formatAbsenceDate = static function (?string $ymd): string {
    if ($ymd === null || $ymd === '') {
        return '';
    }
    $ts = strtotime($ymd);

    return $ts !== false ? date('d/m/Y', $ts) : $ymd;
};

$absencePeriodLabel = static function (array $row) use ($formatAbsenceDate): string {
    $start = $formatAbsenceDate((string) ($row['starts_on'] ?? ''));
    $endRaw = $row['ends_on'] ?? null;
    if ($endRaw === null || $endRaw === '') {
        return $start !== '' ? ('À partir du ' . $start . ' — durée non précisée') : 'Durée non précisée';
    }
    $end = $formatAbsenceDate((string) $endRaw);
    if ($start !== '' && $end !== '' && $start === $end) {
        return 'Le ' . $start;
    }

    return ($start !== '' ? $start : '…') . ' → ' . ($end !== '' ? $end : '…');
};

$todoItems = [];
if ($trainingAllowed && $charterReady && !$charterAccepted) {
    $todoItems[] = [
        'label' => 'Prendre connaissance de la charte de participation aux formations et confirmer votre accord.',
        'href' => url('account/charte-formations'),
        'cta' => 'Ouvrir la charte',
    ];
}

$statusFormationsLabel = $trainingAllowed ? 'Accès au catalogue' : 'Non proposé sur votre communauté';
$statusFormationsTone = $trainingAllowed ? 'violet' : 'slate';

if (!$trainingAllowed) {
    $statusCharterLabel = 'Sans objet';
    $statusCharterTone = 'slate';
} elseif (!$charterReady) {
    $statusCharterLabel = 'Configuration en cours';
    $statusCharterTone = 'slate';
} elseif ($charterAccepted) {
    $statusCharterLabel = 'À jour';
    $statusCharterTone = 'emerald';
} else {
    $statusCharterLabel = 'Action attendue';
    $statusCharterTone = 'amber';
}

$statusSeniorityLabel = $seniorityLines === [] ? 'Aucun indicateur listé' : 'Synthèse disponible';
$statusSeniorityTone = $seniorityLines === [] ? 'slate' : 'indigo';

$statusDossierLabel = $dossierScore . ' % complet';
$statusDossierTone = $dossierScore >= 80 ? 'emerald' : ($dossierScore >= 50 ? 'amber' : 'slate');

$statusToneClasses = static function (string $tone): array {
    return match ($tone) {
        'violet' => ['ring' => 'ring-violet-200/80', 'bg' => 'bg-violet-50', 'dot' => 'bg-violet-500', 'text' => 'text-violet-900'],
        'emerald' => ['ring' => 'ring-emerald-200/80', 'bg' => 'bg-emerald-50', 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-900'],
        'amber' => ['ring' => 'ring-amber-200/80', 'bg' => 'bg-amber-50', 'dot' => 'bg-amber-500', 'text' => 'text-amber-950'],
        'indigo' => ['ring' => 'ring-indigo-200/80', 'bg' => 'bg-indigo-50', 'dot' => 'bg-indigo-500', 'text' => 'text-indigo-900'],
        default => ['ring' => 'ring-slate-200/80', 'bg' => 'bg-slate-50', 'dot' => 'bg-slate-400', 'text' => 'text-slate-800'],
    };
};
$cForm = $statusToneClasses($statusFormationsTone);
$cChart = $statusToneClasses($statusCharterTone);
$cSen = $statusToneClasses($statusSeniorityTone);
$cDoss = $statusToneClasses($statusDossierTone);
?>
<div class="bg-slate-50 pb-20">
    <header class="relative overflow-hidden border-b border-slate-800/80 bg-gradient-to-br from-slate-900 via-violet-950 to-slate-900 text-white">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_25%,rgba(167,139,250,0.15)_0,transparent_45%),radial-gradient(circle_at_85%_10%,rgba(52,211,153,0.12)_0,transparent_40%)]" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_80%,rgba(255,255,255,0.06)_0.5px,transparent_0.6px)] bg-[length:24px_24px] opacity-40" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-6xl px-4 pt-10 pb-12 sm:px-6 sm:pt-14 sm:pb-16 lg:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300/90">Personnel</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Espace RH et formations</h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-300 sm:text-base">
                <?php if ($greetingName !== ''): ?>
                    Bonjour, <span class="font-semibold text-white"><?= htmlspecialchars($greetingName, ENT_QUOTES, 'UTF-8') ?></span> — cet espace regroupe tout ce qui concerne votre parcours de formation, vos engagements formalisés et les informations utiles à votre suivi au sein de la communauté.
                <?php else: ?>
                    Bienvenue — cet espace regroupe tout ce qui concerne votre parcours de formation, vos engagements formalisés et les informations utiles à votre suivi au sein de la communauté.
                <?php endif; ?>
            </p>
            <div class="mt-8 flex flex-wrap gap-3 sm:gap-4">
                <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                    Ma fiche personnelle
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </a>
                <?php if ($absencesSchemaReady): ?>
                    <a href="#absences" class="inline-flex items-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-200 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                        Déclarer une absence
                    </a>
                <?php endif; ?>
                <?php if ($trainingAllowed): ?>
                    <a href="<?= htmlspecialchars(url('formations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-200 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                        Catalogue des formations
                    </a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(url('personnel/tutorials'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-200 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                        Tutoriels du portail
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php
    $rhFlashSuccess = \App\Core\Session::getFlash('success');
    $rhFlashError = \App\Core\Session::getFlash('error');
    ?>
    <?php if ($rhFlashSuccess || $rhFlashError): ?>
    <div class="mx-auto max-w-6xl space-y-3 px-4 pt-8 sm:px-6 lg:px-8">
        <?php if ($rhFlashSuccess): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm font-medium text-emerald-900 shadow-sm" role="status"><?= htmlspecialchars((string) $rhFlashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($rhFlashError): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900 shadow-sm" role="alert"><?= htmlspecialchars((string) $rhFlashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <main id="contenu-espace-rh" class="mx-auto max-w-6xl space-y-10 px-4 pt-10 sm:px-6 sm:pt-12 lg:px-8" tabindex="-1">
        <section class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="rh-status-heading">
            <h2 id="rh-status-heading" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Où vous en êtes</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 sm:gap-5">
                <div class="flex gap-3 rounded-xl ring-1 <?= htmlspecialchars($cDoss['ring'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($cDoss['bg'], ENT_QUOTES, 'UTF-8') ?> p-4">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= htmlspecialchars($cDoss['dot'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dossier personnel</p>
                        <p class="mt-1 text-sm font-semibold <?= htmlspecialchars($cDoss['text'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusDossierLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <div class="flex gap-3 rounded-xl ring-1 <?= htmlspecialchars($cForm['ring'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($cForm['bg'], ENT_QUOTES, 'UTF-8') ?> p-4">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= htmlspecialchars($cForm['dot'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Formations</p>
                        <p class="mt-1 text-sm font-semibold <?= htmlspecialchars($cForm['text'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusFormationsLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <div class="flex gap-3 rounded-xl ring-1 <?= htmlspecialchars($cChart['ring'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($cChart['bg'], ENT_QUOTES, 'UTF-8') ?> p-4">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= htmlspecialchars($cChart['dot'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Charte</p>
                        <p class="mt-1 text-sm font-semibold <?= htmlspecialchars($cChart['text'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusCharterLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <div class="flex gap-3 rounded-xl ring-1 <?= htmlspecialchars($cSen['ring'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($cSen['bg'], ENT_QUOTES, 'UTF-8') ?> p-4">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= htmlspecialchars($cSen['dot'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Indicateurs de parcours</p>
                        <p class="mt-1 text-sm font-semibold <?= htmlspecialchars($cSen['text'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusSeniorityLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($absencesSchemaReady): ?>
        <section id="absences" class="scroll-mt-8 rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="rh-absences-heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 id="rh-absences-heading" class="text-sm font-bold text-slate-900">Déclarer une absence</h2>
                    <p class="mt-1 max-w-2xl text-sm text-slate-600">Informez l’encadrement de votre indisponibilité, avec une période datée ou sans durée précisée (jusqu’à ce que vous l’interrompiez).</p>
                </div>
                <?php if ($activeAbsences !== []): ?>
                    <span class="inline-flex shrink-0 items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-950 ring-1 ring-amber-200/80">Absence en cours</span>
                <?php endif; ?>
            </div>

            <?php if ($activeAbsences !== []): ?>
                <div class="mt-6 space-y-3" role="status">
                    <?php foreach ($activeAbsences as $active): ?>
                        <?php
                        $aReason = (string) ($active['reason'] ?? 'autre');
                        $aReasonLabel = (string) ($absenceReasonLabels[$aReason] ?? 'Autre');
                        $aNote = trim((string) ($active['note'] ?? ''));
                        ?>
                        <div class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50/80 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-amber-950"><?= htmlspecialchars($absencePeriodLabel($active), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-1 text-xs text-amber-900/80"><?= htmlspecialchars($aReasonLabel, ENT_QUOTES, 'UTF-8') ?><?php if ($aNote !== ''): ?> — <?= htmlspecialchars($aNote, ENT_QUOTES, 'UTF-8') ?><?php endif; ?></p>
                            </div>
                            <form method="post" action="<?= htmlspecialchars(url('personnel/mon-espace-rh/absences/annuler'), ENT_QUOTES, 'UTF-8') ?>" class="shrink-0">
                                <input type="hidden" name="_csrf_token" value="<?= $rhWorkspaceCsrf ?>">
                                <input type="hidden" name="absence_id" value="<?= (int) ($active['id'] ?? 0) ?>">
                                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-white px-3 py-2 text-xs font-semibold text-amber-950 shadow-sm transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2">Interrompre</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars(url('personnel/mon-espace-rh/absences'), ENT_QUOTES, 'UTF-8') ?>" class="mt-8 space-y-6" id="rh-absence-form">
                <input type="hidden" name="_csrf_token" value="<?= $rhWorkspaceCsrf ?>">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="absence_starts_on" class="mb-1.5 block text-xs font-bold text-slate-600">Date de début</label>
                        <input type="date" id="absence_starts_on" name="starts_on" required value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 shadow-inner outline-none transition focus:border-slate-900 focus:bg-white focus:ring-2 focus:ring-slate-900/10">
                    </div>
                    <div>
                        <label for="absence_reason" class="mb-1.5 block text-xs font-bold text-slate-600">Motif</label>
                        <select id="absence_reason" name="reason" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 shadow-inner outline-none transition focus:border-slate-900 focus:bg-white focus:ring-2 focus:ring-slate-900/10">
                            <?php foreach ($absenceReasonLabels as $reasonValue => $reasonLabel): ?>
                                <option value="<?= htmlspecialchars((string) $reasonValue, ENT_QUOTES, 'UTF-8') ?>"<?= (string) $reasonValue === 'personnel' ? ' selected' : '' ?>><?= htmlspecialchars((string) $reasonLabel, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <fieldset class="space-y-3">
                    <legend class="text-xs font-bold text-slate-600">Durée</legend>
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <label class="inline-flex cursor-pointer items-start gap-2.5 rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-800 has-[:checked]:border-violet-300 has-[:checked]:bg-violet-50/80">
                            <input type="radio" name="has_duration" value="1" class="mt-1" checked data-absence-duration="dated">
                            <span>
                                <span class="block font-semibold">Période datée</span>
                                <span class="mt-0.5 block text-xs text-slate-600">Vous connaissez la date de retour.</span>
                            </span>
                        </label>
                        <label class="inline-flex cursor-pointer items-start gap-2.5 rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-800 has-[:checked]:border-violet-300 has-[:checked]:bg-violet-50/80">
                            <input type="radio" name="has_duration" value="0" class="mt-1" data-absence-duration="open">
                            <span>
                                <span class="block font-semibold">Sans durée précisée</span>
                                <span class="mt-0.5 block text-xs text-slate-600">Jusqu’à ce que vous interrompiez l’absence.</span>
                            </span>
                        </label>
                    </div>
                    <div id="absence-ends-wrap" class="max-w-sm">
                        <label for="absence_ends_on" class="mb-1.5 block text-xs font-bold text-slate-600">Date de fin</label>
                        <input type="date" id="absence_ends_on" name="ends_on" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 shadow-inner outline-none transition focus:border-slate-900 focus:bg-white focus:ring-2 focus:ring-slate-900/10">
                    </div>
                </fieldset>

                <div>
                    <label for="absence_note" class="mb-1.5 block text-xs font-bold text-slate-600">Précision (facultatif)</label>
                    <textarea id="absence_note" name="note" rows="2" maxlength="500" class="w-full resize-y rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 shadow-inner outline-none transition placeholder:text-slate-400 focus:border-slate-900 focus:bg-white focus:ring-2 focus:ring-slate-900/10" placeholder="Ex. : indisponible les soirs de semaine jusqu’à nouvel ordre"></textarea>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2">Enregistrer l’absence</button>
                    <p class="text-xs text-slate-500">Visible sur votre fiche pour l’encadrement autorisé.</p>
                </div>
            </form>

            <?php if ($personnelAbsences !== []): ?>
                <div class="mt-10 border-t border-slate-100 pt-8">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Historique récent</h3>
                    <ul class="mt-4 divide-y divide-slate-100">
                        <?php foreach ($personnelAbsences as $row): ?>
                            <?php
                            $st = (string) ($row['status'] ?? 'active');
                            $rKey = (string) ($row['reason'] ?? 'autre');
                            $rLab = (string) ($absenceReasonLabels[$rKey] ?? 'Autre');
                            $isActiveRow = $st === 'active';
                            $today = date('Y-m-d');
                            $starts = (string) ($row['starts_on'] ?? '');
                            $ends = $row['ends_on'] ?? null;
                            $coversToday = $isActiveRow && $starts <= $today && ($ends === null || $ends === '' || (string) $ends >= $today);
                            $statusLabel = !$isActiveRow ? 'Annulée' : ($coversToday ? 'En cours' : ($starts > $today ? 'À venir' : 'Terminée'));
                            $statusTone = !$isActiveRow ? 'text-slate-500 bg-slate-100' : ($coversToday ? 'text-amber-950 bg-amber-100' : ($starts > $today ? 'text-sky-900 bg-sky-100' : 'text-emerald-900 bg-emerald-100'));
                            ?>
                            <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900"><?= htmlspecialchars($absencePeriodLabel($row), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-0.5 text-xs text-slate-600"><?= htmlspecialchars($rLab, ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <span class="inline-flex w-fit rounded-full px-2.5 py-0.5 text-[11px] font-semibold <?= htmlspecialchars($statusTone, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>
        <script>
        (function () {
            var form = document.getElementById('rh-absence-form');
            if (!form) return;
            var wrap = document.getElementById('absence-ends-wrap');
            var ends = document.getElementById('absence_ends_on');
            function sync() {
                var dated = form.querySelector('input[name="has_duration"][value="1"]');
                var on = dated && dated.checked;
                if (wrap) wrap.classList.toggle('hidden', !on);
                if (ends) {
                    ends.required = !!on;
                    if (!on) ends.value = '';
                }
            }
            form.querySelectorAll('input[name="has_duration"]').forEach(function (el) {
                el.addEventListener('change', sync);
            });
            sync();
        })();
        </script>
        <?php endif; ?>

        <?php if ($dossierMissing !== []): ?>
        <section class="rounded-2xl border border-amber-200/80 bg-amber-50/70 p-6 sm:p-8" aria-labelledby="rh-dossier-heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 id="rh-dossier-heading" class="text-sm font-bold text-amber-950">Compléter votre dossier personnel</h2>
                    <p class="mt-1 text-sm text-amber-900/80">Il manque <?= count($dossierMissing) ?> élément<?= count($dossierMissing) > 1 ? 's' : '' ?> pour un dossier complet (<?= $dossierScore ?> %).</p>
                </div>
                <a href="<?= htmlspecialchars(url('personnel/me/edit'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2">
                    Compléter mon dossier
                </a>
            </div>
            <ul class="mt-4 flex flex-wrap gap-2">
                <?php foreach ($dossierMissing as $field): ?>
                    <li class="rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-medium text-amber-900"><?= htmlspecialchars((string) $field, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <section aria-labelledby="rh-quick-heading">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <h2 id="rh-quick-heading" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Accès rapides</h2>
                <p class="text-sm text-slate-600">Raccourcis vers les pages les plus utiles depuis cet espace.</p>
            </div>
            <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4">
                <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-violet-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 sm:p-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-800 ring-1 ring-violet-200/80" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-900">Fiche et dossier</p>
                        <p class="mt-0.5 hidden text-xs text-slate-600 sm:block">Qualifications et affectations.</p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-hover:text-violet-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
                <a href="<?= htmlspecialchars(url('personnel/tutorials'), ENT_QUOTES, 'UTF-8') ?>" class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:p-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A9.014 9.014 0 0112 15c2.685 0 5.198-.867 7-2.33V21c0 .552.448 1 1 1h3c.552 0 1-.448 1-1v-4.674c.002-.008.002-.016.002-.025M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-900">Tutoriels</p>
                        <p class="mt-0.5 hidden text-xs text-slate-600 sm:block">Prise en main du portail.</p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-hover:text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
                <a href="<?= htmlspecialchars(url('account'), ENT_QUOTES, 'UTF-8') ?>" class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-sky-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 sm:p-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-800 ring-1 ring-sky-200/80" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.542-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-900">Mon compte</p>
                        <p class="mt-0.5 hidden text-xs text-slate-600 sm:block">Sécurité et préférences.</p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-hover:text-sky-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
                <a href="<?= htmlspecialchars(url('orbat'), ENT_QUOTES, 'UTF-8') ?>" class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-amber-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 sm:p-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-900 ring-1 ring-amber-200/80" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008H17.25v-.008zm0 3h.008v.008H17.25V18zm0 3h.008v.008H17.25v-.008z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-900">Organisation</p>
                        <p class="mt-0.5 hidden text-xs text-slate-600 sm:block">Organigramme et unités.</p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-hover:text-amber-700" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
            </div>
        </section>

        <?php if ($todoItems !== []): ?>
            <section class="rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50 to-orange-50/80 p-6 sm:p-8 shadow-sm" aria-labelledby="rh-todo-heading">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 id="rh-todo-heading" class="text-lg font-bold text-amber-950">Pistes utiles pour la suite</h2>
                        <p class="mt-2 text-sm text-amber-900/80">Quelques actions courantes pour avancer sereinement sur le portail.</p>
                    </div>
                </div>
                <ol class="mt-6 space-y-3">
                    <?php foreach ($todoItems as $i => $item): ?>
                        <li class="flex flex-col gap-2 rounded-xl border border-amber-100/90 bg-white/90 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-200/80 text-sm font-bold text-amber-950"><?= $i + 1 ?></span>
                                <p class="text-sm font-medium text-slate-800 leading-relaxed"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <a href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8') ?>" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 sm:ml-4">
                                <?= htmlspecialchars((string) $item['cta'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>

        <nav class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:px-6 sm:py-5" aria-label="Raccourcis du portail">
            <p class="text-sm font-semibold text-slate-800">Raccourcis du portail</p>
            <ul class="mt-4 flex flex-col gap-2 sm:mt-5 sm:flex-row sm:flex-wrap sm:gap-3">
                <li>
                    <a href="<?= htmlspecialchars(url('hub'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex w-full items-center justify-center rounded-full border border-slate-200 bg-slate-50/80 px-4 py-2.5 text-sm font-medium text-emerald-800 transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:w-auto">Centre opérationnel</a>
                </li>
                <li>
                    <a href="<?= htmlspecialchars(url('activite'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex w-full items-center justify-center rounded-full border border-slate-200 bg-slate-50/80 px-4 py-2.5 text-sm font-medium text-emerald-800 transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:w-auto">Mon activité</a>
                </li>
                <li>
                    <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex w-full items-center justify-center rounded-full border border-slate-200 bg-slate-50/80 px-4 py-2.5 text-sm font-medium text-emerald-800 transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:w-auto">Annuaire des communautés</a>
                </li>
                <li>
                    <a href="<?= htmlspecialchars(url('evenements'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex w-full items-center justify-center rounded-full border border-slate-200 bg-slate-50/80 px-4 py-2.5 text-sm font-medium text-emerald-800 transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:w-auto">Événements</a>
                </li>
            </ul>
        </nav>

        <section class="space-y-8" aria-labelledby="rh-zone-training-heading">
            <div>
                <h2 id="rh-zone-training-heading" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Formations et engagements</h2>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Catalogue, parcours pédagogiques et charte lorsque les formations sont proposées.</p>
            </div>
            <div class="grid gap-8 lg:grid-cols-2 lg:gap-x-10 lg:gap-y-8">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm" aria-labelledby="rh-card-formations-heading">
                    <div class="flex gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-800 ring-1 ring-violet-200/80" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
                        </span>
                        <div>
                            <h3 id="rh-card-formations-heading" class="text-lg font-bold text-slate-900">Formations</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Catalogue, inscriptions et suivi de vos parcours pédagogiques.</p>
                        </div>
                    </div>
                    <?php if ($trainingAllowed): ?>
                        <div class="mt-8 flex flex-wrap gap-3 sm:gap-4">
                            <a href="<?= htmlspecialchars(url('formations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2">Découvrir le catalogue</a>
                            <a href="<?= htmlspecialchars(url('formations/mes-formations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">Mes parcours en cours</a>
                        </div>
                    <?php else: ?>
                        <div class="mt-8 rounded-xl border border-slate-100 bg-slate-50/90 p-5 sm:p-6">
                            <p class="text-sm text-slate-700 leading-relaxed">Les formations ne font pas partie des services activés pour votre communauté dans l’offre actuelle. Pour en savoir plus sur les possibilités d’accès, adressez-vous à l’encadrement ou à l’administration de votre organisation.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm" aria-labelledby="rh-card-charte-heading">
                    <div class="flex gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h3 id="rh-card-charte-heading" class="text-lg font-bold text-slate-900">Charte de participation</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Document d’engagement commun lorsque les formations sont proposées sur la plateforme.</p>
                        </div>
                    </div>
                    <div class="mt-8">
                        <?php if (!$trainingAllowed): ?>
                            <p class="text-sm text-slate-600 leading-relaxed">Sans accès aux formations, cette charte ne vous est pas demandée ici.</p>
                        <?php elseif (!$charterReady): ?>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                <p class="text-sm text-slate-700 leading-relaxed">Votre organisation finalise encore la configuration : la consultation et la confirmation seront proposées ici dès que le document sera publié.</p>
                            </div>
                        <?php else: ?>
                            <div class="rounded-xl border <?= $charterAccepted ? 'border-emerald-100 bg-emerald-50/50' : 'border-amber-100 bg-amber-50/60' ?> p-4">
                                <p class="text-sm font-medium text-slate-800">
                                    <?= $charterAccepted
                                        ? 'Votre dernière prise en compte est enregistrée. Vous pouvez relire le texte à tout moment.'
                                        : 'Une lecture attentive puis une confirmation sont nécessaires avant de poursuivre certains parcours.' ?>
                                </p>
                            </div>
                            <div class="mt-6">
                                <a href="<?= htmlspecialchars(url('account/charte-formations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                                    <?= $charterAccepted ? 'Relire la charte' : 'Lire et confirmer la charte' ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </section>

        <section class="space-y-8" aria-labelledby="rh-zone-path-heading">
            <div>
                <h2 id="rh-zone-path-heading" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Votre parcours</h2>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Indicateurs liés à votre présence et à votre historique au sein de la communauté.</p>
            </div>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm" aria-labelledby="rh-card-seniority-heading">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex gap-4 min-w-0">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-800 ring-1 ring-indigo-200/80" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h3 id="rh-card-seniority-heading" class="text-lg font-bold text-slate-900">Ancienneté et parcours</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Ces indicateurs sont mis à jour à partir des informations de votre dossier lorsque l’encadrement enregistre des changements.</p>
                        </div>
                    </div>
                    <div class="shrink-0 rounded-xl border border-indigo-100 bg-indigo-50/50 p-4 sm:max-w-sm">
                        <p class="text-xs text-slate-600 leading-relaxed">Utile après un changement d’affectation ou de rôle enregistré par l’encadrement.</p>
                        <form method="post" action="<?= htmlspecialchars(url('personnel/mon-espace-rh/actualiser'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4">
                            <input type="hidden" name="_csrf_token" value="<?= $rhWorkspaceCsrf ?>">
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 sm:w-auto">
                                Actualiser depuis mon dossier
                            </button>
                        </form>
                    </div>
                </div>
                <?php if ($seniorityLines === []): ?>
                    <div class="mt-8 rounded-xl border border-dashed border-slate-200 bg-slate-50/80 p-6 text-center sm:p-8">
                        <p class="text-sm text-slate-600 leading-relaxed">Aucun indicateur n’est affiché pour l’instant. Cela peut correspondre aux réglages de votre organisation ou à une mise à jour des données en cours.</p>
                        <p class="mt-6 text-sm">
                            <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-indigo-700 underline decoration-indigo-200 underline-offset-2 hover:text-indigo-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">Consulter ma fiche pour le détail du dossier</a>
                        </p>
                    </div>
                <?php else: ?>
                    <dl class="mt-8 divide-y divide-slate-100 rounded-xl border border-slate-100">
                        <?php foreach ($seniorityLines as $line): ?>
                            <div class="grid grid-cols-1 gap-1 px-4 py-3.5 text-sm sm:grid-cols-[minmax(0,1fr)_auto] sm:items-baseline sm:gap-x-6">
                                <dt class="font-medium text-slate-800"><?= htmlspecialchars((string) ($line['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dt>
                                <dd class="text-slate-600 tabular-nums sm:text-right"><?= htmlspecialchars((string) ($line['formatted'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                    <p class="mt-6 text-sm">
                        <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-indigo-700 underline decoration-indigo-200 underline-offset-2 hover:text-indigo-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">Ouvrir ma fiche personnelle</a>
                    </p>
                <?php endif; ?>
            </section>
        </section>

        <?php if ($mobilitySchemaReady || $hrDocsSchemaReady): ?>
        <section id="mobilite" class="space-y-8" aria-labelledby="rh-zone-mobility-heading">
            <div>
                <h2 id="rh-zone-mobility-heading" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Mobilité et dossier</h2>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Souhait d’évolution et documents RH partagés avec vous.</p>
            </div>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
                <?php if ($mobilitySchemaReady): ?>
                <form method="post" action="<?= htmlspecialchars(url('personnel/mon-espace-rh/mobilite'), ENT_QUOTES, 'UTF-8') ?>" class="grid gap-3 sm:grid-cols-2">
                    <input type="hidden" name="_csrf_token" value="<?= $rhWorkspaceCsrf ?>">
                    <label class="block text-sm">
                        <span class="font-semibold text-slate-800">Type</span>
                        <select name="request_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            <?php foreach ($mobilityTypeLabels as $k => $lab): ?>
                                <option value="<?= htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') ?>" <?= $k === 'career_wish' ? 'selected' : '' ?>><?= htmlspecialchars((string) $lab, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="font-semibold text-slate-800">Poste / unité visée</span>
                        <input type="text" name="target_label" maxlength="200" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Ex. Chef d’équipe, radio…">
                    </label>
                    <label class="block text-sm sm:col-span-2">
                        <span class="font-semibold text-slate-800">Motivation</span>
                        <textarea name="motivation" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Pourquoi ce mouvement ?"></textarea>
                    </label>
                    <div class="sm:col-span-2">
                        <button type="submit" class="inline-flex rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Envoyer à l’encadrement</button>
                    </div>
                </form>
                <?php if ($myMobility !== []): ?>
                    <ul class="mt-6 space-y-2 text-sm text-slate-700">
                        <?php foreach ($myMobility as $m): ?>
                            <li class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                <?= htmlspecialchars((string) ($mobilityTypeLabels[$m['request_type'] ?? ''] ?? $m['request_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                — <?= htmlspecialchars((string) ($m['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php $tl = trim((string) ($m['target_label'] ?? '')); if ($tl !== ''): ?>
                                    · <?= htmlspecialchars($tl, ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php endif; ?>
                <?php if ($hrDocsSchemaReady && $myHrDocs !== []): ?>
                    <div class="<?= $mobilitySchemaReady ? 'mt-8' : '' ?>">
                        <p class="text-sm font-semibold text-slate-900">Documents partagés avec vous</p>
                        <ul class="mt-3 space-y-2 text-sm text-slate-700">
                            <?php foreach ($myHrDocs as $doc): ?>
                                <?php
                                $docId = (int) ($doc['id'] ?? 0);
                                $stored = \App\Support\PersonnelHrDocumentStorage::isStoredPath((string) ($doc['file_path'] ?? ''));
                                ?>
                                <li class="rounded-lg border border-slate-100 px-3 py-2">
                                    <?= htmlspecialchars((string) ($hrDocTypeLabels[$doc['doc_type'] ?? ''] ?? $doc['doc_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    — <?= htmlspecialchars((string) ($doc['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($stored && $docId > 0): ?>
                                        · <a class="font-semibold text-emerald-800 underline" href="<?= htmlspecialchars(url('personnel/mon-espace-rh/documents/' . $docId . '/fichier'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir</a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </section>
        </section>
        <?php endif; ?>

        <section class="space-y-8" aria-labelledby="rh-zone-programs-heading">
            <div>
                <h2 id="rh-zone-programs-heading" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Programmes et évolutions</h2>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Participations ponctuelles et informations sur les versions proposées en avant-première.</p>
            </div>
            <div class="space-y-8">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm" aria-labelledby="rh-card-preq-heading">
                    <div class="flex gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-800 ring-1 ring-teal-200/80" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h3 id="rh-card-preq-heading" class="text-lg font-bold text-slate-900">Programmes de préqualification</h3>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Participations ponctuelles proposées par la plateforme ou votre encadrement pour découvrir des évolutions en avant-première.</p>
                        </div>
                    </div>
                    <?php if ($testerCommunities === []): ?>
                        <div class="mt-8 rounded-xl border border-slate-100 bg-slate-50 p-5 sm:p-6">
                            <p class="text-sm text-slate-700 leading-relaxed">Vous n’êtes rattaché à aucun programme de ce type pour le moment. Si une participation vous est proposée, vous en serez informé par les canaux habituels de votre communauté.</p>
                        </div>
                    <?php else: ?>
                        <ul class="mt-8 grid gap-5 sm:grid-cols-2">
                            <?php foreach ($testerCommunities as $tc): ?>
                                <li class="rounded-xl border border-teal-100 bg-teal-50/40 p-4 sm:p-5">
                                    <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($tc['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php $desc = trim((string) ($tc['description'] ?? '')); ?>
                                    <?php if ($desc !== ''): ?>
                                        <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php
                                    $vf = $tc['valid_from'] ?? null;
                                    $vu = $tc['valid_until'] ?? null;
                                    if (($vf !== null && $vf !== '') || ($vu !== null && $vu !== '')):
                                    ?>
                                        <p class="mt-3 text-xs text-slate-500">
                                            Période d’inclusion
                                            <?php if ($vf !== null && $vf !== ''): ?> du <?= htmlspecialchars((string) $vf, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                            <?php if ($vu !== null && $vu !== ''): ?> au <?= htmlspecialchars((string) $vu, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <?php if ($rolloutRows !== []): ?>
                    <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm" aria-labelledby="rh-card-rollout-heading">
                        <div class="flex gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-800 ring-1 ring-rose-200/80" aria-hidden="true">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h3 id="rh-card-rollout-heading" class="text-lg font-bold text-slate-900">Évolutions liées à vos programmes</h3>
                                <p class="mt-2 text-sm text-slate-600 leading-relaxed">Selon les règles définies par l’organisation, certaines fonctionnalités peuvent vous être proposées en avant-première ou faire l’objet de limitations temporaires.</p>
                            </div>
                        </div>
                        <ul class="mt-8 grid gap-5 md:grid-cols-2">
                            <?php foreach ($rolloutRows as $rr): ?>
                                <li class="rounded-xl border border-slate-100 bg-slate-50/80 p-4 sm:p-5">
                                    <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($rr['module_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-amber-800"><?= htmlspecialchars((string) ($rr['rule_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php $md = $rr['module_description'] ?? null; ?>
                                    <?php if (is_string($md) && trim($md) !== ''): ?>
                                        <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars(trim($md), ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php $ev = $rr['evaluation_version'] ?? null; ?>
                                    <?php if (is_string($ev) && $ev !== ''): ?>
                                        <p class="mt-4 text-sm text-slate-700">
                                            <span class="text-slate-600">Version proposée en avant-première sur votre espace : </span><span class="font-semibold text-slate-900"><?= htmlspecialchars($ev, ENT_QUOTES, 'UTF-8') ?></span>
                                        </p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
