<?php
declare(strict_types=1);

$boardEntry = $boardEntry ?? null;
$boardEntryPersonnel = $boardEntryPersonnel ?? [];
$boardEntryAssets = $boardEntryAssets ?? [];
$boardEntryNotes = $boardEntryNotes ?? [];
$boardEntryChecklists = $boardEntryChecklists ?? [];
$boardCategories = $boardCategories ?? [];
$boardMemberOptions = $boardMemberOptions ?? [];
$boardPrefill = $boardPrefill ?? [];
$boardSchemaReady = $boardSchemaReady ?? true;
$boardLinkedSource = $boardLinkedSource ?? null;
$boardVisibilityUnits = $boardVisibilityUnits ?? [];
$boardVisibilityJobRoles = $boardVisibilityJobRoles ?? [];
$boardFormReturnUrl = $boardFormReturnUrl ?? url('back-office/tableau-operationnel/fiche/nouvelle');

$isEdit = $boardEntry !== null;
$eid = $isEdit ? (int) ($boardEntry['id'] ?? 0) : 0;

$boardVisibilityRoleSelected = [];
if ($isEdit && isset($boardEntry['visibility_job_role_ids']) && (string) ($boardEntry['visibility_job_role_ids'] ?? '') !== '') {
    $decoded = json_decode((string) $boardEntry['visibility_job_role_ids'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $rid) {
            $boardVisibilityRoleSelected[(int) $rid] = true;
        }
    }
}

$val = static function (string $k, string $default = '') use ($boardEntry, $boardPrefill): string {
    if ($boardEntry !== null && array_key_exists($k, $boardEntry) && $boardEntry[$k] !== null) {
        return (string) $boardEntry[$k];
    }
    if (isset($boardPrefill[$k])) {
        return (string) $boardPrefill[$k];
    }

    return $default;
};

$entryTypes = [
    'permanence' => 'Permanence',
    'info' => 'Information pratique',
    'manifestation' => 'Manifestation / dispositif',
    'mission' => 'Mission',
    'task' => 'Tâche interne',
    'formation' => 'Activité de formation',
    'flash_info' => 'Flash information',
];

$boardFormVariant = $boardFormVariant ?? ($isEdit ? $val('entry_type', 'task') : 'task');
if (!isset($entryTypes[$boardFormVariant])) {
    $boardFormVariant = 'task';
}
$isFlashForm = ($boardFormVariant === 'flash_info');

$boardTypeHero = [
    'permanence' => [
        'headline' => 'Permanence',
        'intro' => 'Décrivez la période couverte, le rôle attendu et les relais utiles. Indiquez ensuite qui doit voir cette ligne sur le mur (communauté, unité ou emplois métier).',
        'wrap' => 'border-sky-200 bg-gradient-to-br from-sky-50/90 to-white ring-sky-100',
    ],
    'info' => [
        'headline' => 'Information pratique',
        'intro' => 'Message stable ou consigne de référence : horaires, accès, contacts. Choisissez le public concerné dans l’étape « Qui voit cette fiche ? ».',
        'wrap' => 'border-slate-200 bg-gradient-to-br from-slate-50/90 to-white ring-slate-100',
    ],
    'manifestation' => [
        'headline' => 'Manifestation / dispositif',
        'intro' => 'Précisez le dispositif, les points d’attention et la fenêtre de validité. Adaptez la visibilité si seule une partie de la communauté est concernée.',
        'wrap' => 'border-violet-200 bg-gradient-to-br from-violet-50/90 to-white ring-violet-100',
    ],
    'mission' => [
        'headline' => 'Mission',
        'intro' => 'Structuration d’une opération : objectifs, moyens, chaîne de décision et terrain. Les blocs encadrement et moyens sont prévus pour ce type de fiche.',
        'wrap' => 'border-emerald-200 bg-gradient-to-br from-emerald-50/90 to-white ring-emerald-100',
    ],
    'task' => [
        'headline' => 'Tâche interne',
        'intro' => 'Suivi d’actions internes ou préparation : utilisez la description et les blocs optionnels selon le besoin.',
        'wrap' => 'border-amber-200 bg-gradient-to-br from-amber-50/90 to-white ring-amber-100',
    ],
    'formation' => [
        'headline' => 'Activité de formation',
        'intro' => 'Période pédagogique ou module : dates, public et consignes. Ajustez la visibilité pour limiter aux personnes concernées si nécessaire.',
        'wrap' => 'border-cyan-200 bg-gradient-to-br from-cyan-50/90 to-white ring-cyan-100',
    ],
    'flash_info' => [
        'headline' => 'Flash information',
        'intro' => 'Message court et lisible en quelques secondes. Seuls l’essentiel, la période de validité et le public lecteur sont proposés ici.',
        'wrap' => 'border-orange-200 bg-gradient-to-br from-orange-50/90 to-white ring-orange-100',
    ],
];
$hero = $boardTypeHero[$boardFormVariant] ?? $boardTypeHero['task'];
$visCur = $val('visibility_scope', 'tenant');

?>
<div class="mx-auto max-w-5xl space-y-6 pb-10 px-4">
    <?php if (!$boardSchemaReady): ?>
        <div class="rounded-2xl border border-amber-200 bg-gradient-to-b from-amber-50/40 to-white px-6 py-10 shadow-sm sm:px-8" role="status">
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-amber-900">Activation en attente</p>
            <h2 class="mt-2 text-lg font-black tracking-tight text-slate-900 sm:text-xl">La saisie n’est pas disponible pour le moment</h2>
            <p class="mt-4 text-sm leading-relaxed text-slate-600">
                Le tableau opérationnel n’est pas encore activé sur cet environnement. Merci d’en informer la personne ou l’équipe qui administre l’hébergement du site : une étape d’installation prévue avec la version déployée doit encore être réalisée. Ensuite, actualisez cette page.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <button type="button" class="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2" onclick="location.reload()">
                    Actualiser la page
                </button>
                <a href="<?= url('back-office/tableau-operationnel') ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-800 shadow-sm transition hover:bg-slate-50">
                    Retour au tableau
                </a>
            </div>
        </div>
    <?php else: ?>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-700">Tableau opérationnel</p>
            <h1 class="mt-1 text-xl font-black text-slate-900"><?= $isEdit ? 'Modifier la fiche' : 'Nouvelle fiche' ?> — <?= htmlspecialchars((string) ($hero['headline'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <?php if ($isEdit): ?>
                <a href="<?= url('back-office/tableau-operationnel/fiche/nouvelle') ?>" class="text-sm font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-900">Changer de type (nouvelle fiche)</a>
            <?php else: ?>
                <a href="<?= url('back-office/tableau-operationnel/fiche/nouvelle') ?>" class="text-sm font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-900">Autre type d’entrée</a>
            <?php endif; ?>
            <a href="<?= url('back-office/tableau-operationnel') ?>" class="text-sm font-semibold text-emerald-800 underline decoration-emerald-200 underline-offset-2">Retour au mur</a>
        </div>
    </div>

    <section class="rounded-2xl border-2 p-5 shadow-sm ring-1 <?= htmlspecialchars((string) ($hero['wrap'] ?? 'border-slate-200 bg-white'), ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="board-type-hero-heading">
        <h2 id="board-type-hero-heading" class="text-sm font-bold uppercase tracking-wider text-slate-800">Vue « <?= htmlspecialchars((string) ($hero['headline'] ?? ''), ENT_QUOTES, 'UTF-8') ?> »</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-700"><?= htmlspecialchars((string) ($hero['intro'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    </section>

    <?php if ($isEdit): ?>
    <div class="flex flex-wrap gap-2">
        <form method="post" action="<?= url('back-office/tableau-operationnel/fiche/' . $eid . '/dupliquer') ?>" class="inline">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-800 hover:bg-slate-50">Dupliquer</button>
        </form>
    </div>
    <?php endif; ?>

    <section class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/40 p-5 shadow-sm space-y-3">
        <h2 class="text-sm font-bold uppercase tracking-wider text-emerald-950">Ajouter une rubrique de classement</h2>
        <p class="text-xs leading-relaxed text-slate-700">Ce bloc est indépendant de la fiche : il enregistre seulement une nouvelle rubrique dans la liste, puis vous pourrez la choisir ci-dessous après actualisation si besoin.</p>
        <form method="post" action="<?= url('back-office/tableau-operationnel/rubrique') ?>" class="grid gap-3 md:grid-cols-2">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="redirect_after" value="<?= htmlspecialchars($boardFormReturnUrl, ENT_QUOTES, 'UTF-8') ?>">
            <label class="md:col-span-2 block text-xs font-semibold text-slate-700">Nom de la rubrique
                <input type="text" name="category_name" maxlength="120" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" autocomplete="off" placeholder="Ex. : Permanences, Logistique…">
            </label>
            <label class="block text-xs font-semibold text-slate-700">Couleur d’étiquette
                <input type="color" name="category_color" value="#334155" class="mt-1 h-10 w-full max-w-[12rem] cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
            </label>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Enregistrer la rubrique</button>
            </div>
        </form>
    </section>

    <?php if ($isEdit): ?>
    <form method="post" action="<?= url('back-office/tableau-operationnel/fiche/' . $eid) ?>" class="space-y-8">
    <?php else: ?>
    <form method="post" action="<?= url('back-office/tableau-operationnel') ?>" class="space-y-8">
    <?php endif; ?>
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">Informations générales</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <label class="md:col-span-2 block text-xs font-semibold text-slate-600">Intitulé
                    <input name="title" required value="<?= htmlspecialchars($val('title'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm" autocomplete="off">
                </label>
                <input type="hidden" name="entry_type" value="<?= htmlspecialchars($boardFormVariant, ENT_QUOTES, 'UTF-8') ?>">
                <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Type de fiche (écran dédié)</p>
                    <p class="mt-1 font-semibold"><?= htmlspecialchars((string) ($entryTypes[$boardFormVariant] ?? $boardFormVariant), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-xs text-slate-600">Pour une autre nature d’activité, utilisez le lien « Autre type d’entrée » ou « Changer de type » en haut de page : chaque type dispose de son propre parcours.</p>
                </div>
                <label class="block text-xs font-semibold text-slate-600">Catégorie
                    <select name="category_id" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        <option value="0">—</option>
                        <?php foreach ($boardCategories as $c): ?>
                            <option value="<?= (int) ($c['id'] ?? 0) ?>" <?= (int) $val('category_id', '0') === (int) ($c['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if (is_array($boardLinkedSource) && ($boardLinkedSource['label'] ?? '') !== ''): ?>
                <div class="md:col-span-2 rounded-xl border border-sky-200 bg-sky-50/90 px-4 py-3 text-sm text-sky-950">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-sky-800">Source liée</p>
                    <p class="mt-1.5">
                        <?php if (!empty($boardLinkedSource['url'])): ?>
                            <a href="<?= htmlspecialchars($boardLinkedSource['url'], ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-sky-900 underline decoration-sky-400 underline-offset-2 hover:text-sky-950"><?= htmlspecialchars((string) $boardLinkedSource['label'], ENT_QUOTES, 'UTF-8') ?></a>
                        <?php else: ?>
                            <?= htmlspecialchars((string) $boardLinkedSource['label'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
                <label class="block text-xs font-semibold text-slate-600">Rattachement
                    <select name="linked_type" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        <option value="none" <?= $val('linked_type') === '' ? 'selected' : '' ?>>Aucun</option>
                        <option value="event" <?= $val('linked_type') === 'event' ? 'selected' : '' ?>>Événement</option>
                        <option value="mission" <?= $val('linked_type') === 'mission' ? 'selected' : '' ?>>Mission inter-unités</option>
                        <option value="formation" <?= $val('linked_type') === 'formation' ? 'selected' : '' ?>>Formation</option>
                    </select>
                </label>
                <label class="block text-xs font-semibold text-slate-600">Référence interne du rattachement
                    <input type="number" name="linked_id" min="1" value="<?= htmlspecialchars($val('linked_id'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm" placeholder="Souvent laissé vide — utilisez plutôt « Publier au mur » depuis la fiche source">
                </label>
                <label class="block text-xs font-semibold text-slate-600">Date de début (validité)
                    <input type="date" name="start_date" value="<?= htmlspecialchars($val('start_date'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                </label>
                <label class="block text-xs font-semibold text-slate-600">Date de fin (validité)
                    <input type="date" name="end_date" value="<?= htmlspecialchars($val('end_date'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                </label>
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 md:col-span-2">
                    <input type="checkbox" name="all_day" value="1" <?= ((int) $val('all_day', '1')) === 1 ? 'checked' : '' ?> class="rounded border-slate-400">
                    Journée entière (pas de créneau horaire précis)
                </label>
                <label class="block text-xs font-semibold text-slate-600">Priorité
                    <select name="priority" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        <?php foreach (['low' => 'Faible', 'normal' => 'Normale', 'high' => 'Élevée', 'critical' => 'Critique'] as $k => $lbl): ?>
                            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= $val('priority', 'normal') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-xs font-semibold text-slate-600">Ordre d’affichage
                    <input type="number" name="display_order" value="<?= htmlspecialchars($val('display_order', '100'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                    <span class="mt-1 block text-xs font-normal text-slate-500">Nombre plus petit = plus haut dans la liste (selon les filtres).</span>
                </label>
                <div class="md:col-span-2 space-y-3 rounded-2xl border border-indigo-200 bg-indigo-50/30 p-4 ring-1 ring-indigo-100">
                    <p class="text-sm font-bold uppercase tracking-wider text-indigo-950">Qui voit cette fiche sur le mur ?</p>
                    <p class="text-xs leading-relaxed text-slate-700">Chaque mode correspond à un écran de saisie différent juste en dessous. Choisissez celui qui correspond à votre besoin.</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="flex cursor-pointer flex-col gap-1 rounded-xl border-2 border-white bg-white p-3 shadow-sm transition hover:border-indigo-300 has-[:checked]:border-indigo-600 has-[:checked]:ring-2 has-[:checked]:ring-indigo-200">
                            <span class="flex items-center gap-2">
                                <input type="radio" name="visibility_scope" value="tenant" class="board-vis-radio text-indigo-700" <?= $visCur === 'tenant' ? 'checked' : '' ?>>
                                <span class="text-sm font-bold text-slate-900">Toute la communauté</span>
                            </span>
                            <span class="text-xs leading-relaxed text-slate-600">Visible par les membres autorisés à consulter le mur, selon les règles habituelles du portail.</span>
                        </label>
                        <label class="flex cursor-pointer flex-col gap-1 rounded-xl border-2 border-white bg-white p-3 shadow-sm transition hover:border-indigo-300 has-[:checked]:border-indigo-600 has-[:checked]:ring-2 has-[:checked]:ring-indigo-200">
                            <span class="flex items-center gap-2">
                                <input type="radio" name="visibility_scope" value="unit" class="board-vis-radio text-indigo-700" <?= $visCur === 'unit' ? 'checked' : '' ?>>
                                <span class="text-sm font-bold text-slate-900">Une unité précise</span>
                            </span>
                            <span class="text-xs leading-relaxed text-slate-600">Seuls les membres rattachés à l’unité choisie verront la ligne une fois publiée.</span>
                        </label>
                        <label class="flex cursor-pointer flex-col gap-1 rounded-xl border-2 border-white bg-white p-3 shadow-sm transition hover:border-indigo-300 has-[:checked]:border-indigo-600 has-[:checked]:ring-2 has-[:checked]:ring-indigo-200">
                            <span class="flex items-center gap-2">
                                <input type="radio" name="visibility_scope" value="role" class="board-vis-radio text-indigo-700" <?= $visCur === 'role' ? 'checked' : '' ?>>
                                <span class="text-sm font-bold text-slate-900">Certains emplois métier</span>
                            </span>
                            <span class="text-xs leading-relaxed text-slate-600">Ciblage par emplois du dossier personnel (référentiel communautaire).</span>
                        </label>
                        <label class="flex cursor-pointer flex-col gap-1 rounded-xl border-2 border-white bg-white p-3 shadow-sm transition hover:border-indigo-300 has-[:checked]:border-indigo-600 has-[:checked]:ring-2 has-[:checked]:ring-indigo-200">
                            <span class="flex items-center gap-2">
                                <input type="radio" name="visibility_scope" value="private" class="board-vis-radio text-indigo-700" <?= $visCur === 'private' ? 'checked' : '' ?>>
                                <span class="text-sm font-bold text-slate-900">Brouillon personnel</span>
                            </span>
                            <span class="text-xs leading-relaxed text-slate-600">Réservé à vous sur le mur lecture tant que vous ne changez pas le mode.</span>
                        </label>
                    </div>
                </div>

                <div id="vis-panel-tenant" class="board-vis-panel md:col-span-2 hidden rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 space-y-2">
                    <p class="text-sm font-bold text-emerald-950">Vue « toute la communauté »</p>
                    <p class="text-sm leading-relaxed text-slate-700">Aucun réglage supplémentaire : après validation et mise en ligne, la fiche suit les règles de lecture du portail pour tous les membres habilités.</p>
                </div>
                <div id="vis-panel-unit" class="board-vis-panel md:col-span-2 hidden rounded-2xl border border-sky-200 bg-sky-50/50 p-5 space-y-3">
                    <p class="text-sm font-bold text-sky-950">Vue « unité »</p>
                    <p class="text-xs leading-relaxed text-slate-700">Sélectionnez l’unité du tableau d’organisation dont les membres doivent voir cette entrée.</p>
                    <label class="block text-xs font-semibold text-slate-800">Unité destinataire sur le mur
                        <select id="board-visibility-unit" name="visibility_unit_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                            <option value="">— Choisir une unité —</option>
                            <?php foreach ($boardVisibilityUnits as $uopt): ?>
                                <option value="<?= (int) ($uopt['id'] ?? 0) ?>" <?= (int) $val('visibility_unit_id', '0') === (int) ($uopt['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($uopt['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div id="vis-panel-role" class="board-vis-panel md:col-span-2 hidden rounded-2xl border border-amber-200 bg-amber-50/50 p-5 space-y-3">
                    <p class="text-sm font-bold text-amber-950">Vue « emplois métier »</p>
                    <p class="text-xs leading-relaxed text-slate-700">Cochez au moins un emploi : les membres qui ont cet emploi sur leur fiche verront l’entrée lorsqu’elle est publiée.</p>
                    <div class="max-h-52 overflow-y-auto space-y-2 rounded-lg border border-amber-200/80 bg-white p-3">
                        <?php if ($boardVisibilityJobRoles === []): ?>
                            <p class="text-xs leading-relaxed text-amber-900">Aucun emploi métier n’est disponible pour l’instant. Les personnes qui gèrent l’organisation peuvent en créer dans le <a href="<?= url('back-office/personnel-job-roles') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-300 underline-offset-2">référentiel des emplois et attributions</a>.</p>
                        <?php else: ?>
                            <?php foreach ($boardVisibilityJobRoles as $jr): ?>
                                <?php $jid = (int) ($jr['id'] ?? 0); if ($jid < 1) { continue; } ?>
                                <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-transparent px-2 py-1.5 hover:border-amber-200 hover:bg-amber-50/50">
                                    <input type="checkbox" name="visibility_job_role_id[]" value="<?= $jid ?>" class="mt-0.5 rounded border-slate-400" <?= isset($boardVisibilityRoleSelected[$jid]) ? 'checked' : '' ?>>
                                    <span class="text-sm text-slate-800"><?= htmlspecialchars((string) ($jr['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div id="vis-panel-private" class="board-vis-panel md:col-span-2 hidden rounded-2xl border border-rose-200 bg-rose-50/50 p-5 space-y-2">
                    <p class="text-sm font-bold text-rose-950">Vue « brouillon personnel »</p>
                    <p class="text-sm leading-relaxed text-slate-700">Sur le mur en lecture, seul le créateur de la fiche verra cette entrée tant qu’elle reste dans ce mode. Utile pour préparer un texte avant diffusion plus large.</p>
                </div>
                <label class="block text-xs font-semibold text-slate-600">Niveau de sensibilité
                    <select name="security_level" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        <?php foreach (['unit_public' => 'Diffusion interne large', 'command_restricted' => 'Encadrement', 'confidential' => 'Confidentiel', 'secret_ops' => 'Très restreint'] as $k => $lbl): ?>
                            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= $val('security_level', 'unit_public') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-xs font-semibold text-slate-600">Statut opérationnel
                    <select name="operational_status" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        <?php foreach (['planned' => 'Planifié', 'in_progress' => 'En cours', 'suspended' => 'Suspendu', 'completed' => 'Terminé', 'cancelled' => 'Annulé'] as $k => $lbl): ?>
                            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= $val('operational_status', 'planned') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-xs font-semibold text-slate-600">Phase
                    <select name="phase_current" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        <?php foreach (['phase_1' => 'Phase 1', 'phase_2' => 'Phase 2', 'phase_3' => 'Phase 3'] as $k => $lbl): ?>
                            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= $val('phase_current', 'phase_1') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="md:col-span-2 block text-xs font-semibold text-slate-600">Description / consigne générale
                    <textarea name="description" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"><?= htmlspecialchars($val('description'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </label>
                <label class="md:col-span-2 block text-xs font-semibold text-slate-600">Mots-clés pour retrouver cette fiche
                    <input name="tags_csv" value="<?= htmlspecialchars($isEdit ? (string) ($boardEntry['tags_list'] ?? '') : '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm" autocomplete="off" placeholder="Exemple : logistique, salle Bravo (plusieurs mots séparés par une virgule)">
                    <span class="mt-1 block font-normal text-slate-500">Optionnel — facilite le filtrage sur le tableau.</span>
                </label>
            </div>
            <script>
            (function () {
                function currentScope() {
                    var r = document.querySelector('input[name="visibility_scope"]:checked');
                    return r ? r.value : 'tenant';
                }
                function sync() {
                    var v = currentScope();
                    ['tenant', 'unit', 'role', 'private'].forEach(function (k) {
                        var el = document.getElementById('vis-panel-' + k);
                        if (el) el.classList.toggle('hidden', k !== v);
                    });
                }
                document.querySelectorAll('input[name="visibility_scope"]').forEach(function (radio) {
                    radio.addEventListener('change', sync);
                });
                sync();
            })();
            </script>
        </section>

        <?php if (!$isFlashForm): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">Responsabilités</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <?php
                $selUser = static function (string $current) use ($boardMemberOptions): void {
                    ?>
                    <option value="">—</option>
                    <?php foreach ($boardMemberOptions as $opt): ?>
                        <option value="<?= (int) $opt['id'] ?>" <?= (string) (int) $current === (string) (int) $opt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                <?php }; ?>
                <label class="block text-xs font-semibold text-slate-600">Chef désigné
                    <select name="chief_user_id" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"><?php $selUser($val('chief_user_id')); ?></select>
                </label>
                <label class="block text-xs font-semibold text-slate-600">Adjoint
                    <select name="deputy_user_id" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"><?php $selUser($val('deputy_user_id')); ?></select>
                </label>
                <label class="block text-xs font-semibold text-slate-600">Remplaçant
                    <select name="replacement_user_id" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"><?php $selUser($val('replacement_user_id')); ?></select>
                </label>
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 md:col-span-2">
                    <input type="checkbox" name="replacement_auto_activate" value="1" <?= (int) $val('replacement_auto_activate', '0') === 1 ? 'checked' : '' ?> class="rounded border-slate-400">
                    Activer le remplacement automatiquement lorsque les conditions sont réunies
                </label>
                <label class="md:col-span-2 block text-xs font-semibold text-slate-600">Chaîne de commandement (texte)
                    <input name="command_chain" value="<?= htmlspecialchars($val('command_chain'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm" autocomplete="off">
                </label>
                <label class="md:col-span-2 block text-xs font-semibold text-slate-600">Responsabilité engagée
                    <input name="accountability_note" value="<?= htmlspecialchars($val('accountability_note'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm" autocomplete="off">
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">Terrain & cadre</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <label class="block text-xs font-semibold text-slate-600">Zone d’intervention
                    <input name="operation_zone" value="<?= htmlspecialchars($val('operation_zone'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm" autocomplete="off">
                </label>
                <label class="block text-xs font-semibold text-slate-600">Lien carte
                    <input name="map_link" value="<?= htmlspecialchars($val('map_link'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm" autocomplete="off" placeholder="https://…">
                </label>
                <label class="block text-xs font-semibold text-slate-600">Latitude
                    <input name="location_lat" value="<?= htmlspecialchars($val('location_lat'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm" autocomplete="off">
                </label>
                <label class="block text-xs font-semibold text-slate-600">Longitude
                    <input name="location_lng" value="<?= htmlspecialchars($val('location_lng'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm" autocomplete="off">
                </label>
                <label class="block text-xs font-semibold text-slate-600">Référence dossier
                    <input name="dossier_ref" value="<?= htmlspecialchars($val('dossier_ref'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm" autocomplete="off">
                </label>
                <label class="md:col-span-2 block text-xs font-semibold text-slate-600">Contraintes (cadre, sécurité, etc.)
                    <textarea name="legal_constraints" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"><?= htmlspecialchars($val('legal_constraints'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </label>
                <label class="block text-xs font-semibold text-slate-600">Début fenêtre d’action
                    <input type="datetime-local" name="fire_window_start" value="<?= htmlspecialchars(str_replace(' ', 'T', trim($val('fire_window_start'))), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                </label>
                <label class="block text-xs font-semibold text-slate-600">Fin fenêtre d’action
                    <input type="datetime-local" name="fire_window_end" value="<?= htmlspecialchars(str_replace(' ', 'T', trim($val('fire_window_end'))), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">Personnels affectés (liste)</h2>
            <p class="text-xs text-slate-600">Ajoutez une ligne par membre. Cochez « Responsable de ligne » pour le chef d’équipe sur cette entrée.</p>
            <div id="personnel-rows" class="space-y-3">
                <?php
                $pRows = $boardEntryPersonnel !== [] ? $boardEntryPersonnel : [['user_id' => '', 'role_label' => '', 'is_lead' => 0]];
                foreach ($pRows as $i => $pr):
                    $pu = (int) ($pr['user_id'] ?? 0);
                    ?>
                <div class="grid gap-2 rounded-lg border border-slate-100 bg-slate-50 p-3 md:grid-cols-12 md:items-end">
                    <label class="md:col-span-5 text-xs font-semibold text-slate-600">Membre
                        <select name="personnel_user_id[]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                            <option value="">—</option>
                            <?php foreach ($boardMemberOptions as $opt): ?>
                                <option value="<?= (int) $opt['id'] ?>" <?= $pu === (int) $opt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="md:col-span-4 text-xs font-semibold text-slate-600">Rôle sur la ligne
                        <input name="personnel_role[]" value="<?= htmlspecialchars((string) ($pr['role_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" placeholder="ex. OPJ, radio" autocomplete="off">
                    </label>
                    <label class="md:col-span-3 text-xs font-semibold text-slate-600">Responsable de ligne
                        <select name="personnel_is_lead[]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                            <option value="0" <?= empty($pr['is_lead']) ? 'selected' : '' ?>>Non</option>
                            <option value="1" <?= !empty($pr['is_lead']) ? 'selected' : '' ?>>Oui</option>
                        </select>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="text-xs font-bold text-emerald-800 underline" id="add-personnel-row">Ajouter une ligne</button>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">Moyens</h2>
            <div id="asset-rows" class="space-y-3">
                <?php
                $aRows = $boardEntryAssets !== [] ? $boardEntryAssets : [['asset_type' => 'vehicule', 'asset_label' => '', 'asset_reference' => '', 'asset_state' => 'available']];
                foreach ($aRows as $ar):
                    ?>
                <div class="grid gap-2 rounded-lg border border-slate-100 bg-slate-50 p-3 md:grid-cols-12 md:items-end">
                    <label class="md:col-span-3 text-xs font-semibold text-slate-600">Type
                        <input name="asset_type[]" value="<?= htmlspecialchars((string) ($ar['asset_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" autocomplete="off">
                    </label>
                    <label class="md:col-span-4 text-xs font-semibold text-slate-600">Libellé
                        <input name="asset_label[]" value="<?= htmlspecialchars((string) ($ar['asset_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" autocomplete="off">
                    </label>
                    <label class="md:col-span-3 text-xs font-semibold text-slate-600">Référence
                        <input name="asset_reference[]" value="<?= htmlspecialchars((string) ($ar['asset_reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" autocomplete="off">
                    </label>
                    <label class="md:col-span-2 text-xs font-semibold text-slate-600">État
                        <select name="asset_state[]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                            <?php foreach (['available' => 'Disponible', 'engaged' => 'Engagé', 'unavailable' => 'Indisponible'] as $ks => $ls): ?>
                                <option value="<?= htmlspecialchars($ks, ENT_QUOTES, 'UTF-8') ?>" <?= ((string) ($ar['asset_state'] ?? '')) === $ks ? 'selected' : '' ?>><?= htmlspecialchars($ls, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="text-xs font-bold text-emerald-800 underline" id="add-asset-row">Ajouter un moyen</button>
        </section>
        <?php endif; ?>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800"><?= $isFlashForm ? 'Précisions ou consigne courte' : 'Consignes structurées' ?></h2>
            <?php if ($isFlashForm): ?>
                <p class="text-xs text-slate-600">Ajoutez un ou plusieurs blocs si vous souhaitez détailler le message (sinon l’intitulé et la description générale suffisent).</p>
            <?php endif; ?>
            <div id="note-rows" class="space-y-3">
                <?php
                $nRows = $boardEntryNotes !== [] ? $boardEntryNotes : [['note_type' => 'consigne', 'content' => '', 'is_pinned' => 0]];
                foreach ($nRows as $ni => $nr):
                    ?>
                <div class="grid gap-2 rounded-lg border border-slate-100 bg-slate-50 p-3 md:grid-cols-12 md:items-end">
                    <label class="md:col-span-3 text-xs font-semibold text-slate-600">Nature
                        <select name="note_type[]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                            <?php foreach (['consigne' => 'Consigne', 'info' => 'Information', 'restriction' => 'Restriction', 'brief' => 'Brief'] as $ks => $ls): ?>
                                <option value="<?= htmlspecialchars($ks, ENT_QUOTES, 'UTF-8') ?>" <?= ((string) ($nr['note_type'] ?? '')) === $ks ? 'selected' : '' ?>><?= htmlspecialchars($ls, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="md:col-span-8 text-xs font-semibold text-slate-600">Texte
                        <textarea name="note_content[]" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm"><?= htmlspecialchars((string) ($nr['content'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                    <label class="md:col-span-1 text-xs font-semibold text-slate-600">Épinglage
                        <select name="note_pinned[]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                            <option value="0" <?= empty($nr['is_pinned']) ? 'selected' : '' ?>>Non</option>
                            <option value="1" <?= !empty($nr['is_pinned']) ? 'selected' : '' ?>>Oui</option>
                        </select>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="text-xs font-bold text-emerald-800 underline" id="add-note-row">Ajouter un bloc</button>
        </section>

        <?php if ($boardEntryChecklists !== []): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">Points de contrôle</h2>
            <?php foreach ($boardEntryChecklists as $cl):
                $cid = (int) ($cl['id'] ?? 0);
                ?>
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 p-3 text-sm">
                    <span class="text-slate-800"><?= htmlspecialchars((string) ($cl['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <form method="post" action="<?= url('back-office/tableau-operationnel/' . $eid . '/checklist/' . $cid) ?>" class="inline">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="is_done" value="<?= !empty($cl['is_done']) ? '0' : '1' ?>">
                        <button type="submit" class="rounded-lg bg-slate-800 px-3 py-1 text-xs font-bold text-white"><?= !empty($cl['is_done']) ? 'Annuler' : 'Valider' ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <div class="flex flex-wrap gap-3">
            <?php if ($isEdit): ?>
                <button type="submit" class="rounded-xl bg-emerald-700 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">Enregistrer</button>
            <?php else: ?>
                <button type="submit" class="rounded-xl bg-emerald-700 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">Créer le brouillon</button>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($isEdit && $eid > 0): ?>
    <section class="mx-auto max-w-5xl rounded-2xl border border-emerald-200 bg-emerald-50/30 px-4 py-5 shadow-sm space-y-3" aria-labelledby="board-save-template-heading">
        <h2 id="board-save-template-heading" class="text-sm font-bold uppercase tracking-wider text-emerald-950">Enregistrer comme modèle</h2>
        <p class="text-sm text-slate-700">Crée un modèle réutilisable à partir de cette fiche : type d’activité, visibilité, intitulé et textes types sont conservés. Les <strong class="font-semibold text-slate-900">dates</strong> et le <strong class="font-semibold text-slate-900">rattachement</strong> à un événement ou une autre fiche ne sont pas copiés (à renseigner sur chaque nouvelle fiche).</p>
        <form method="post" action="<?= url('back-office/tableau-operationnel/fiche/' . $eid . '/modele') ?>" class="grid gap-3 md:grid-cols-2">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <label class="md:col-span-2 block text-xs font-semibold text-slate-700">Nom du modèle (dans la liste du tableau)
                <input type="text" name="template_name" required maxlength="160" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" autocomplete="off" placeholder="Ex. Permanence week-end formation initiale">
            </label>
            <label class="block text-xs font-semibold text-slate-700">Famille / type métier
                <select name="mission_family" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                    <option value="permanence_opj">Permanence judiciaire</option>
                    <option value="mission_judiciaire">Mission judiciaire</option>
                    <option value="instruction">Instruction ou formation</option>
                    <option value="dispositif_securite">Dispositif sécurité</option>
                    <option value="exercice">Exercice</option>
                    <option value="custom" selected>Sur mesure</option>
                </select>
            </label>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">Créer le modèle</button>
            </div>
        </form>
    </section>
    <section class="mx-auto max-w-5xl rounded-2xl border border-rose-200 bg-rose-50/50 px-4 py-5 shadow-sm space-y-3" aria-labelledby="board-retire-heading">
        <h2 id="board-retire-heading" class="text-sm font-bold uppercase tracking-wider text-rose-900">Retirer du mur opérationnel</h2>
        <p class="text-sm text-slate-700">L’entrée ne sera plus affichée sur le portail lecture seule. Vous pourrez encore la retrouver ici en filtrant les fiches « retirées du mur ».</p>
        <form method="post" action="<?= url('back-office/tableau-operationnel/' . $eid . '/retirer-du-mur') ?>" class="space-y-3" onsubmit="return confirm('Retirer cette entrée du mur opérationnel ? Elle disparaîtra du portail public.');">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <label class="block text-xs font-semibold text-slate-600">Motif (optionnel, pour le journal)
                <input type="text" name="retire_reason" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" autocomplete="off" placeholder="Ex. information devenue sans objet">
            </label>
            <button type="submit" class="rounded-xl border border-rose-300 bg-white px-5 py-2.5 text-sm font-bold text-rose-900 shadow-sm hover:bg-rose-100">Retirer du mur</button>
        </form>
    </section>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($boardSchemaReady): ?>
<script>
(() => {
    function addRow(containerId, templateHtml) {
        const c = document.getElementById(containerId);
        if (!c) return;
        const wrap = document.createElement('div');
        wrap.innerHTML = templateHtml.trim();
        c.appendChild(wrap.firstElementChild);
    }
    document.getElementById('add-personnel-row')?.addEventListener('click', () => addRow('personnel-rows', `
        <div class="grid gap-2 rounded-lg border border-slate-100 bg-slate-50 p-3 md:grid-cols-12 md:items-end">
            <label class="md:col-span-5 text-xs font-semibold text-slate-600">Membre
                <select name="personnel_user_id[]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                    <option value="">—</option>
                    <?php foreach ($boardMemberOptions as $opt): ?>
                    <option value="<?= (int) $opt['id'] ?>"><?= htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="md:col-span-4 text-xs font-semibold text-slate-600">Rôle sur la ligne
                <input name="personnel_role[]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" autocomplete="off">
            </label>
            <label class="md:col-span-3 text-xs font-semibold text-slate-600">Responsable de ligne
                <select name="personnel_is_lead[]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                    <option value="0" selected>Non</option>
                    <option value="1">Oui</option>
                </select>
            </label>
        </div>`));
    document.getElementById('add-asset-row')?.addEventListener('click', () => addRow('asset-rows', `
        <div class="grid gap-2 rounded-lg border border-slate-100 bg-slate-50 p-3 md:grid-cols-12 md:items-end">
            <label class="md:col-span-3 text-xs font-semibold text-slate-600">Type
                <input name="asset_type[]" value="moyen" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
            </label>
            <label class="md:col-span-4 text-xs font-semibold text-slate-600">Libellé
                <input name="asset_label[]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
            </label>
            <label class="md:col-span-3 text-xs font-semibold text-slate-600">Référence
                <input name="asset_reference[]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
            </label>
            <label class="md:col-span-2 text-xs font-semibold text-slate-600">État
                <select name="asset_state[]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                    <option value="available">Disponible</option>
                    <option value="engaged">Engagé</option>
                    <option value="unavailable">Indisponible</option>
                </select>
            </label>
        </div>`));
    document.getElementById('add-note-row')?.addEventListener('click', () => addRow('note-rows', `
        <div class="grid gap-2 rounded-lg border border-slate-100 bg-slate-50 p-3 md:grid-cols-12 md:items-end">
            <label class="md:col-span-3 text-xs font-semibold text-slate-600">Nature
                <select name="note_type[]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                    <option value="consigne">Consigne</option>
                    <option value="info">Information</option>
                    <option value="restriction">Restriction</option>
                    <option value="brief">Brief</option>
                </select>
            </label>
            <label class="md:col-span-8 text-xs font-semibold text-slate-600">Texte
                <textarea name="note_content[]" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm"></textarea>
            </label>
            <label class="md:col-span-1 text-xs font-semibold text-slate-600">Épinglage
                <select name="note_pinned[]" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
                    <option value="0" selected>Non</option>
                    <option value="1">Oui</option>
                </select>
            </label>
        </div>`));
})();
</script>
<?php endif; ?>
