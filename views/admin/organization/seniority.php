<?php
declare(strict_types=1);

$schemaReady = !empty($senioritySchemaReady);
$definitions = is_array($seniorityDefinitions ?? null) ? $seniorityDefinitions : [];
$csrf = htmlspecialchars((string) ($seniorityCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$flashErr = \App\Core\Session::getFlash('error');
$flashOk = \App\Core\Session::getFlash('success');
?>
<div class="mx-auto max-w-4xl space-y-8 px-4 py-10 sm:px-6 lg:px-8">
    <header class="space-y-3">
        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Back-office communauté</p>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Indicateurs d’ancienneté</h1>
        <p class="max-w-2xl text-sm leading-relaxed text-slate-600">
            Choisissez quels indicateurs apparaissent sur les fiches personnel et dans l’espace RH des membres. Après activation, les durées se calculent à partir des périodes saisies sur chaque dossier (dates de début et fin).
        </p>
        <p>
            <a href="<?= htmlspecialchars(url('back-office/organisation-effectifs'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-900">Retour à l’organisation des effectifs</a>
            <span class="text-slate-300" aria-hidden="true"> · </span>
            <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-900">Centre de pilotage</a>
        </p>
    </header>

    <?php if ($flashErr): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert"><?= htmlspecialchars((string) $flashErr, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($flashOk): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950" role="status"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!$schemaReady): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-6 text-sm leading-relaxed text-amber-950">
            Le référentiel d’ancienneté n’est pas encore déployé sur cette installation technique. Une fois la base à jour, cette page permettra de publier les indicateurs pour votre communauté.
        </div>
    <?php else: ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-bold text-slate-900">Démarrage rapide</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Si rien n’est encore configuré, utilisez le bouton ci-dessous pour installer un <span class="font-semibold text-slate-800">catalogue d’indicateurs</span> prêts à l’emploi : ancienneté dans la communauté, service cumulé, affectation, engagements, formation, qualifications, grade, rôles, campagne, réserve, encadrement, reconnaissance interne, etc. Seuls les plus courants sont affichés sur les fiches au départ ; vous choisissez ensuite ce qui est visible, l’ordre et l’activation de chaque ligne.
            </p>
            <form method="post" action="<?= htmlspecialchars(url('back-office/organisation/anciennete/initialiser'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                    Installer ou compléter les indicateurs standards
                </button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-bold text-slate-900">Synchroniser tout le personnel</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Pour chaque <span class="font-semibold text-slate-800">membre actif</span> de la communauté, recalcule l’indicateur
                <span class="font-semibold text-slate-800">« Ancienneté dans la communauté »</span> à partir des dates présentes sur le dossier
                (incorporation, enrôlement, candidature acceptée, ou date de création du compte). Les autres indicateurs
                (service cumulé, affectation, etc.) ne sont pas modifiés ici : ils restent pilotés par les périodes saisies sur chaque fiche.
            </p>
            <form method="post" action="<?= htmlspecialchars(url('back-office/organisation/anciennete/synchroniser-effectifs'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6" onsubmit="return confirm('Lancer la synchronisation pour tous les membres actifs ? Cela peut prendre quelques secondes sur une grande communauté.');">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                    Mettre à jour tout le personnel
                </button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-bold text-slate-900">Compléter depuis le dossier</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Pour les indicateurs concernant l’<span class="font-semibold text-slate-800">unité d’emploi</span>, un
                <span class="font-semibold text-slate-800">groupe fonctionnel</span>, le
                <span class="font-semibold text-slate-800">rôle communauté</span> ou le
                <span class="font-semibold text-slate-800">grade actuel</span>, lorsqu’aucune période n’a été saisie par l’encadrement,
                le portail peut <span class="font-semibold text-slate-800">proposer une date de départ</span> à partir des affectations,
                de l’historique du dossier et des traces d’organisation déjà enregistrées. Les saisies manuelles restent prioritaires et ne sont pas écrasées.
            </p>
            <form method="post" action="<?= htmlspecialchars(url('back-office/organisation/anciennete/completer-depuis-dossier'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6" onsubmit="return confirm('Lancer le complément pour tous les membres actifs ? Cela peut prendre quelques secondes sur une grande communauté.');">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                    Compléter les indicateurs à partir du dossier
                </button>
            </form>
        </div>

        <?php if ($definitions === []): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 p-8 text-center text-sm text-slate-600">
                Aucun indicateur n’est encore défini pour cette communauté. Utilisez le démarrage rapide ci-dessus pour en ajouter.
            </div>
        <?php else: ?>
            <form method="post" action="<?= htmlspecialchars(url('back-office/organisation/anciennete'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-6">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6">
                        <h2 class="text-lg font-bold text-slate-900">Indicateurs publiés</h2>
                        <p class="mt-1 text-xs text-slate-500">Cochez « afficher sur les fiches » pour que les membres voient la ligne correspondante. Désactivez un indicateur pour le retirer du calcul sans supprimer l’historique.</p>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($definitions as $def): ?>
                            <?php
                            $id = (int) ($def['id'] ?? 0);
                            if ($id < 1) {
                                continue;
                            }
                            $label = (string) ($def['label'] ?? 'Indicateur');
                            $active = !empty($def['is_active']);
                            $visible = !empty($def['is_visible']);
                            $sort = (int) ($def['sort_order'] ?? 0);
                            ?>
                            <li class="px-5 py-5 sm:px-6">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="mt-1 text-xs text-slate-500">Ordre d’affichage (plus petit = en premier)</p>
                                        <input type="number" name="rows[<?= $id ?>][sort]" value="<?= $sort ?>" min="0" max="9999" class="mt-2 w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-sm tabular-nums">
                                    </div>
                                    <div class="flex flex-col gap-3 sm:items-end">
                                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                            <input type="checkbox" name="rows[<?= $id ?>][active]" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" <?= $active ? 'checked' : '' ?>>
                                            Indicateur actif
                                        </label>
                                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                            <input type="checkbox" name="rows[<?= $id ?>][visible]" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" <?= $visible ? 'checked' : '' ?>>
                                            Afficher sur les fiches et l’espace RH
                                        </label>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2">
                        Enregistrer les réglages
                    </button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
