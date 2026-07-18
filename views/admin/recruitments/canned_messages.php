<?php
/** @var list<array<string,mixed>> $cannedMessages */
$rows = $cannedMessages ?? [];
$tableMissing = !empty($cannedMessagesTableMissing);
$listUrl = url('back-office/recruitments');
$formAction = url('back-office/recruitments/messages-prefaits');
$contextLabels = [
    'generic' => 'Usage général',
    'portal' => 'Fil de suivi du candidat',
    'accept' => 'Acceptation',
    'pending' => 'Mise en attente',
    'reject' => 'Refus ou non-admission',
    'redirect' => 'Redirection',
];
$contextGroups = [
    'general' => [
        'title' => 'Tous les usages',
        'keys' => ['generic'],
    ],
    'decision' => [
        'title' => 'Décision sur une candidature',
        'keys' => ['accept', 'pending', 'reject', 'redirect'],
    ],
    'portal' => [
        'title' => 'Fil du portail de suivi',
        'keys' => ['portal'],
    ],
];
$rowCount = count($rows);
$fieldClass = 'canned-field w-full max-w-xl rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25';
$labelClass = 'mb-1.5 block text-sm font-semibold text-slate-800';
$hintClass = 'mt-1.5 text-xs leading-relaxed text-slate-500';
?>
<div class="canned-messages-page recruitment-bureau max-w-3xl mx-auto w-full space-y-8">
    <nav class="overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-5" aria-label="Fil d’Ariane">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600">
            <a href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-transparent px-2 py-1.5 text-slate-700 transition hover:border-slate-200 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 focus-visible:ring-offset-2">Dossiers de candidature</a>
            <span class="text-slate-300" aria-hidden="true">/</span>
            <span class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 font-bold text-slate-900">Modèles de texte</span>
        </div>
    </nav>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 px-5 py-6 sm:px-8 sm:py-7">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-emerald-700">Bureau recrutement</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Modèles de texte</h1>
                    <p class="mt-3 max-w-xl text-sm leading-relaxed text-slate-600">
                        Préparez des formulations prêtes à l’emploi pour répondre plus vite, sans taper le même message à chaque dossier.
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <a href="<?= htmlspecialchars(url('back-office/recruitments/settings'), ENT_QUOTES, 'UTF-8') ?>" class="recruitment-lms-submit-secondary inline-flex min-h-[2.5rem] items-center rounded-xl px-3 py-2 text-[10px] font-bold uppercase tracking-[0.18em] shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                        Délais d’alerte
                    </a>
                    <a href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') ?>" class="recruitment-lms-nav-dark inline-flex min-h-[2.5rem] items-center rounded-xl px-3 py-2 text-[10px] font-bold uppercase tracking-[0.18em] shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 focus-visible:ring-offset-2">
                        ← File des dossiers
                    </a>
                </div>
            </div>
        </div>

        <?php if ($tableMissing): ?>
            <div class="border-b border-amber-200 bg-amber-50 px-5 py-4 sm:px-8">
                <p class="text-sm leading-relaxed text-amber-950">
                    Ce module n’est pas encore disponible sur cet environnement. Un administrateur technique doit finaliser la mise à jour de la plateforme ; rechargez ensuite cette page.
                </p>
            </div>
        <?php endif; ?>

        <div class="border-b border-slate-200 bg-white px-5 py-6 sm:px-8">
            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">Où ces modèles s’utilisent</p>
            <div class="canned-usage-grid mt-4 grid gap-3 sm:grid-cols-2" role="list">
                <div class="canned-usage-card rounded-xl border border-slate-200 bg-slate-50/80 p-4" role="listitem">
                    <p class="text-sm font-bold text-slate-900">Décision sur une candidature</p>
                    <p class="mt-1.5 text-xs leading-relaxed text-slate-600">
                        Texte proposé dans le commentaire envoyé avec la décision (acceptation, attente, refus…). Visible sur la fiche du dossier.
                    </p>
                </div>
                <div class="canned-usage-card rounded-xl border border-emerald-200/80 bg-emerald-50/50 p-4" role="listitem">
                    <p class="text-sm font-bold text-slate-900">Fil du portail de suivi</p>
                    <p class="mt-1.5 text-xs leading-relaxed text-slate-600">
                        Message destiné au candidat sur son lien de suivi sécurisé. Choisissez le contexte « Fil de suivi du candidat » pour ces modèles.
                    </p>
                </div>
            </div>
        </div>

        <div class="px-5 py-8 sm:px-8 sm:py-10 space-y-8 <?= $tableMissing ? 'pointer-events-none opacity-50' : '' ?>">
            <section class="canned-create-card overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="canned-create-title">
                <div class="border-b border-slate-200 bg-slate-50 px-5 py-3.5 sm:px-6">
                    <h2 id="canned-create-title" class="text-sm font-bold text-slate-900">Nouveau modèle</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Renseignez un intitulé clair, le texte, puis l’endroit où le proposer.</p>
                </div>
                <div class="p-5 sm:p-6">
                    <form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
                        <?= \App\Core\Csrf::field() ?>
                        <div>
                            <label for="new-label" class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Nom du modèle</label>
                            <input type="text" id="new-label" name="label" maxlength="160" required class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ex. Accueil, refus courtois, message de suivi…">
                            <p class="<?= htmlspecialchars($hintClass, ENT_QUOTES, 'UTF-8') ?>">Affiché dans le menu de sélection sur la fiche candidature.</p>
                        </div>
                        <div>
                            <label for="new-body" class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Texte du modèle</label>
                            <textarea id="new-body" name="body" rows="4" required maxlength="8000" class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>" placeholder="Rédigez le message tel qu’il sera inséré…"></textarea>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 sm:items-start">
                            <div>
                                <label for="new-context" class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Où le proposer</label>
                                <select id="new-context" name="context" class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach ($contextGroups as $group): ?>
                                        <optgroup label="<?= htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?php foreach ($group['keys'] as $key): ?>
                                                <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($contextLabels[$key], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <p class="<?= htmlspecialchars($hintClass, ENT_QUOTES, 'UTF-8') ?>">« Usage général » reste disponible dans tous les cas.</p>
                            </div>
                            <div>
                                <label for="new-sort" class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Ordre d’affichage</label>
                                <input type="number" id="new-sort" name="sort_order" value="0" min="0" max="99999" class="canned-field w-28 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25">
                                <p class="<?= htmlspecialchars($hintClass, ENT_QUOTES, 'UTF-8') ?>">Plus le chiffre est bas, plus le modèle apparaît tôt dans la liste.</p>
                            </div>
                        </div>
                        <div class="pt-1">
                            <button type="submit" class="recruitment-lms-submit-emerald canned-submit-btn inline-flex min-h-[2.75rem] items-center justify-center rounded-xl px-6 py-2.5 text-sm font-bold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/45 focus-visible:ring-offset-2">
                                Enregistrer le modèle
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <?php if (!$tableMissing): ?>
                <section class="canned-toolbar rounded-2xl border border-slate-200 bg-slate-50/90 p-4 sm:p-5" aria-label="Recherche et filtre">
                    <div class="flex flex-col gap-1 mb-4">
                        <h2 class="text-sm font-bold text-slate-900">Vos modèles</h2>
                        <p class="text-xs text-slate-500">Filtrez par mot-clé ou par contexte d’usage.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-[1fr_minmax(12rem,16rem)_auto] sm:items-end">
                        <label class="block min-w-0">
                            <span class="mb-1.5 block text-sm font-semibold text-slate-800">Rechercher</span>
                            <input id="canned-search" type="search" class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?> canned-field--full" placeholder="Nom ou contenu du modèle…">
                        </label>
                        <label class="block min-w-0">
                            <span class="mb-1.5 block text-sm font-semibold text-slate-800">Contexte d’usage</span>
                            <select id="canned-context-filter" class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?> canned-field--full">
                                <option value="">Tous les contextes</option>
                                <?php foreach ($contextGroups as $group): ?>
                                    <optgroup label="<?= htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?php foreach ($group['keys'] as $key): ?>
                                            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($contextLabels[$key], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <p class="text-xs text-slate-600 sm:pb-3 sm:text-right">
                            <span id="canned-visible-count" class="font-bold tabular-nums text-slate-900"><?= $rowCount ?></span>
                            <span class="text-slate-400">/</span>
                            <span class="tabular-nums"><?= $rowCount ?></span>
                            visible(s)
                        </p>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($tableMissing): ?>
            <?php elseif (empty($rows)): ?>
                <div class="canned-empty rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 px-6 py-12 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-700" aria-hidden="true">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h11M8 12h11M8 18h7M4 6h.01M4 12h.01M4 18h.01"/></svg>
                    </div>
                    <p class="mt-4 text-base font-bold text-slate-900">Aucun modèle pour l’instant</p>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600">
                        Créez votre premier modèle ci-dessus. Vous pourrez ensuite l’insérer en un clic depuis la fiche d’une candidature.
                    </p>
                    <a href="#canned-create-title" class="recruitment-lms-submit-emerald canned-submit-btn mt-6 inline-flex min-h-[2.5rem] items-center justify-center rounded-xl px-5 py-2 text-sm font-bold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/45 focus-visible:ring-offset-2">
                        Créer un modèle
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4" id="canned-list">
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $rid = (int) ($r['id'] ?? 0);
                        $ctx = (string) ($r['context'] ?? 'generic');
                        $ctxLabel = $contextLabels[$ctx] ?? $contextLabels['generic'];
                        $searchBlob = mb_strtolower(trim((string) (($r['label'] ?? '') . ' ' . ($r['body'] ?? '') . ' ' . $ctxLabel . ' ' . $ctx)), 'UTF-8');
                        ?>
                        <article class="canned-model-card overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-canned-row data-context="<?= htmlspecialchars($ctx, ENT_QUOTES, 'UTF-8') ?>" data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/90 px-4 py-2.5 sm:px-5">
                                <span class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-900"><?= htmlspecialchars($ctxLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-[11px] font-medium tabular-nums text-slate-400">n° <?= $rid ?></span>
                            </div>
                            <div class="p-4 sm:p-5">
                                <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits/' . $rid . '/update'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3.5">
                                    <?= \App\Core\Csrf::field() ?>
                                    <div>
                                        <label class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Nom du modèle</label>
                                        <input type="text" name="label" value="<?= htmlspecialchars((string) ($r['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="160" required class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div>
                                        <label class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Texte du modèle</label>
                                        <textarea name="body" rows="3" required maxlength="8000" class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($r['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Où le proposer</label>
                                            <select name="context" class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>">
                                                <?php foreach ($contextGroups as $group): ?>
                                                    <optgroup label="<?= htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?php foreach ($group['keys'] as $key): ?>
                                                            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $ctx === $key ? 'selected' : '' ?>><?= htmlspecialchars($contextLabels[$key], ENT_QUOTES, 'UTF-8') ?></option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>">Ordre d’affichage</label>
                                            <input type="number" name="sort_order" value="<?= (int) ($r['sort_order'] ?? 0) ?>" min="0" max="99999" class="canned-field w-28 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25">
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3 pt-1">
                                        <button type="submit" class="recruitment-lms-submit-emerald canned-submit-btn inline-flex min-h-[2.5rem] items-center justify-center rounded-xl px-5 py-2 text-sm font-bold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/45 focus-visible:ring-offset-2">
                                            Enregistrer
                                        </button>
                                    </div>
                                </form>
                                <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits/' . $rid . '/delete'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 border-t border-slate-100 pt-4" onsubmit="return confirm('Supprimer ce modèle ? Cette action est définitive.');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="canned-delete-btn text-sm font-semibold text-rose-700 underline decoration-rose-300 underline-offset-2 transition hover:text-rose-900 hover:decoration-rose-600">
                                        Supprimer ce modèle
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <p id="canned-no-match" class="hidden rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-600">
                        Aucun modèle ne correspond à cette recherche.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    var searchInput = document.getElementById('canned-search');
    var contextInput = document.getElementById('canned-context-filter');
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-canned-row]'));
    var count = document.getElementById('canned-visible-count');
    var noMatch = document.getElementById('canned-no-match');
    if (!searchInput || !contextInput || rows.length === 0) {
        return;
    }
    function applyFilter() {
        var q = (searchInput.value || '').trim().toLowerCase();
        var context = contextInput.value || '';
        var visible = 0;
        rows.forEach(function (row) {
            var matchQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
            var matchCtx = !context || (row.getAttribute('data-context') || '') === context;
            var show = matchQ && matchCtx;
            row.classList.toggle('hidden', !show);
            if (show) {
                visible++;
            }
        });
        if (count) {
            count.textContent = String(visible);
        }
        if (noMatch) {
            noMatch.classList.toggle('hidden', visible > 0);
        }
    }
    searchInput.addEventListener('input', applyFilter);
    contextInput.addEventListener('change', applyFilter);
})();
</script>
