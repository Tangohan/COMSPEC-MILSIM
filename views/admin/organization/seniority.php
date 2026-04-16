<?php
declare(strict_types=1);

$schemaReady = !empty($senioritySchemaReady);
$definitions = is_array($seniorityDefinitions ?? null) ? $seniorityDefinitions : [];
$stats = is_array($seniorityDefinitionStats ?? null) ? $seniorityDefinitionStats : ['total' => 0, 'active' => 0, 'visible' => 0, 'inactive' => 0, 'hidden' => 0];
$csrf = htmlspecialchars((string) ($seniorityCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$flashErr = \App\Core\Session::getFlash('error');
$flashOk = \App\Core\Session::getFlash('success');

$scopeLabel = static function (string $scope): string {
    return match ($scope) {
        'tenant' => 'Communauté',
        'global' => 'Global',
        'org' => 'Organisation',
        default => ucfirst(str_replace('_', ' ', $scope)),
    };
};
$calcModeLabel = static function (string $mode): string {
    return match ($mode) {
        'from_start' => 'Depuis première date',
        'sum_periods' => 'Somme des périodes',
        'active_only' => 'Périodes actives',
        'custom_rule' => 'Règle avancée',
        default => ucfirst(str_replace('_', ' ', $mode)),
    };
};
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
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase tracking-widest text-slate-500">Total</p><p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['total'] ?? 0) ?></p></div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm"><p class="text-xs uppercase tracking-widest text-emerald-700">Actifs</p><p class="mt-1 text-2xl font-black text-emerald-900"><?= (int) ($stats['active'] ?? 0) ?></p></div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-4 shadow-sm"><p class="text-xs uppercase tracking-widest text-indigo-700">Visibles</p><p class="mt-1 text-2xl font-black text-indigo-900"><?= (int) ($stats['visible'] ?? 0) ?></p></div>
            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 shadow-sm"><p class="text-xs uppercase tracking-widest text-amber-700">Inactifs</p><p class="mt-1 text-2xl font-black text-amber-900"><?= (int) ($stats['inactive'] ?? 0) ?></p></div>
            <div class="rounded-xl border border-slate-200 bg-slate-100/80 p-4 shadow-sm"><p class="text-xs uppercase tracking-widest text-slate-600">Masqués</p><p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['hidden'] ?? 0) ?></p></div>
        </div>

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
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-bold text-slate-900">Recherche & tri rapide des indicateurs</h2>
                <p class="mt-1 text-xs text-slate-500">Filtrage local sans rechargement pour auditer rapidement les catégories (systèmes, services, RH).</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <input id="senioritySearch" type="text" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Rechercher un code/libellé…">
                    <select id="seniorityScopeFilter" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Tous les périmètres</option>
                        <option value="tenant">Communauté</option>
                        <option value="global">Global</option>
                        <option value="org">Organisation</option>
                    </select>
                    <select id="seniorityStatusFilter" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Tous les statuts</option>
                        <option value="active">Actifs</option>
                        <option value="inactive">Inactifs</option>
                        <option value="visible">Visibles</option>
                        <option value="hidden">Masqués</option>
                    </select>
                    <select id="seniorityCalcFilter" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Tous les calculs</option>
                        <option value="from_start">Depuis première date</option>
                        <option value="sum_periods">Somme des périodes</option>
                        <option value="active_only">Périodes actives</option>
                        <option value="custom_rule">Règle avancée</option>
                    </select>
                </div>
            </div>
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
                            $labelIndex = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
                            $codeIndexRaw = (string) ($def['code'] ?? '');
                            $codeIndex = function_exists('mb_strtolower') ? mb_strtolower($codeIndexRaw, 'UTF-8') : strtolower($codeIndexRaw);
                            ?>
                            <li class="px-5 py-5 sm:px-6 js-seniority-row"
                                data-label="<?= htmlspecialchars($labelIndex, ENT_QUOTES, 'UTF-8') ?>"
                                data-code="<?= htmlspecialchars($codeIndex, ENT_QUOTES, 'UTF-8') ?>"
                                data-scope="<?= htmlspecialchars((string) ($def['scope'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-calc="<?= htmlspecialchars((string) ($def['calc_mode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-active="<?= $active ? '1' : '0' ?>"
                                data-visible="<?= $visible ? '1' : '0' ?>">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="mt-2 flex flex-wrap gap-2 text-[11px]">
                                            <span class="rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 font-semibold text-slate-700"><?= htmlspecialchars($scopeLabel((string) ($def['scope'] ?? 'tenant')), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 font-semibold text-blue-700"><?= htmlspecialchars($calcModeLabel((string) ($def['calc_mode'] ?? 'sum_periods')), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 font-semibold text-violet-700"><?= htmlspecialchars((string) ($def['source_type'] ?? 'manual'), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="rounded-full border border-slate-300 bg-white px-2 py-0.5 font-mono text-slate-600"><?= htmlspecialchars((string) ($def['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        </p>
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
<script>
(() => {
    const search = document.getElementById('senioritySearch');
    const scope = document.getElementById('seniorityScopeFilter');
    const status = document.getElementById('seniorityStatusFilter');
    const calc = document.getElementById('seniorityCalcFilter');
    const rows = Array.from(document.querySelectorAll('.js-seniority-row'));
    if (!search || !scope || !status || !calc || rows.length === 0) return;

    const apply = () => {
        const q = (search.value || '').trim().toLowerCase();
        const scopeValue = scope.value;
        const statusValue = status.value;
        const calcValue = calc.value;
        rows.forEach((row) => {
            const label = row.dataset.label || '';
            const code = row.dataset.code || '';
            const rowScope = row.dataset.scope || '';
            const rowCalc = row.dataset.calc || '';
            const active = row.dataset.active === '1';
            const visible = row.dataset.visible === '1';
            const statusMatch = statusValue === ''
                || (statusValue === 'active' && active)
                || (statusValue === 'inactive' && !active)
                || (statusValue === 'visible' && visible)
                || (statusValue === 'hidden' && !visible);
            const ok = (q === '' || label.includes(q) || code.includes(q))
                && (scopeValue === '' || rowScope === scopeValue)
                && (calcValue === '' || rowCalc === calcValue)
                && statusMatch;
            row.style.display = ok ? '' : 'none';
        });
    };
    [search, scope, status, calc].forEach((el) => el.addEventListener('input', apply));
    [scope, status, calc].forEach((el) => el.addEventListener('change', apply));
})();
</script>
