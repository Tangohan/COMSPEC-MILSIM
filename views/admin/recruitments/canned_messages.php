<?php
/** @var list<array<string,mixed>> $cannedMessages */
$rows = $cannedMessages ?? [];
$tableMissing = !empty($cannedMessagesTableMissing);
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$listUrl = url('back-office/recruitments');
$formAction = url('back-office/recruitments/messages-prefaits');
$contextLabels = [
    'generic' => 'Tous contextes',
    'accept' => 'Acceptation',
    'pending' => 'Mise en attente',
    'reject' => 'Refus / non admission',
    'redirect' => 'Redirection',
];
require base_path('views/admin/recruitment_workspace/partials/command_shell_open.php');
?>
<div class="recruitment-bureau min-h-[calc(100vh-3.5rem)] bg-gradient-to-b from-[#ebe6dc] via-[#f5f2eb] to-[#e8e4db]">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">

        <div class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-[0_25px_60px_-20px_rgba(28,45,65,0.35)] ring-1 ring-black/[0.03]">
            <div class="relative bg-[#1c2d41] px-5 py-6 sm:px-8 sm:py-8">
                <div class="absolute inset-0 bg-[linear-gradient(105deg,rgba(201,162,39,0.12)_0%,transparent_45%)] pointer-events-none" aria-hidden="true"></div>
                <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.35em] text-[#c9a227]/90">Service recrutement</p>
                        <h1 class="mt-2 font-serif text-2xl font-bold tracking-tight text-white sm:text-3xl">Modèles de texte</h1>
                        <p class="mt-3 max-w-xl text-sm leading-relaxed text-slate-300/95">
                            Phrases toutes prêtes à insérer dans le <strong class="text-white/95">commentaire interne</strong> lors du traitement d’une candidature (accueil, refus, non-admission). Chaque communauté gère sa propre liste.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="<?= htmlspecialchars(url('back-office/recruitments/settings'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex shrink-0 items-center rounded-xl border border-sky-300/40 bg-sky-300/10 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-sky-100 transition hover:bg-sky-300/20">
                            Délais d’alerte
                        </a>
                        <a href="<?= htmlspecialchars($listUrl) ?>" class="inline-flex shrink-0 items-center rounded-xl border border-white/15 bg-black/20 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-200 transition hover:bg-black/30">
                            ← Candidatures
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-b border-stone-200 bg-[#faf8f3] px-4 py-5 sm:px-8">
                <?php if ($flashOk): ?>
                    <p class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-950 shadow-sm"><?= htmlspecialchars((string) $flashOk) ?></p>
                <?php endif; ?>
                <?php if ($flashErr): ?>
                    <p class="rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-950 shadow-sm"><?= htmlspecialchars((string) $flashErr) ?></p>
                <?php endif; ?>
                <?php if ($tableMissing): ?>
                    <p class="rounded-xl border border-amber-200/90 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 shadow-sm">
                        Ce module n’est pas encore disponible sur cet environnement. Un administrateur technique doit finaliser la mise à jour de la plateforme ; rechargez ensuite cette page.
                    </p>
                <?php endif; ?>
            </div>

            <div class="px-4 py-8 sm:px-8 sm:py-10">
                <section class="mb-6 rounded-2xl border border-stone-200 bg-[#faf8f3] p-4 sm:p-5">
                    <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                        <label class="block">
                            <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-stone-600">Recherche rapide</span>
                            <input id="canned-search" type="search" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20" placeholder="Intitulé, contenu, contexte…">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-stone-600">Contexte</span>
                            <select id="canned-context-filter" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20">
                                <option value="">Tous</option>
                                <?php foreach ($contextLabels as $key => $label): ?>
                                    <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <p class="text-xs text-stone-600"><span id="canned-visible-count" class="font-black text-stone-900"><?= count($rows) ?></span> / <?= count($rows) ?> affiché(s)</p>
                    </div>
                </section>

                <section class="mb-10 overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm <?= $tableMissing ? 'pointer-events-none opacity-50' : '' ?>">
                    <div class="border-b border-stone-200 bg-[#f4f1ea] px-6 py-3">
                        <h2 class="text-[10px] font-bold uppercase tracking-[0.25em] text-stone-500">Annexe — Nouveau modèle</h2>
                    </div>
                    <div class="p-6">
                        <form method="post" action="<?= htmlspecialchars($formAction) ?>" class="space-y-5">
                            <?= \App\Core\Csrf::field() ?>
                            <div>
                                <label for="new-label" class="mb-1 block text-xs font-bold uppercase tracking-wide text-stone-600">Intitulé dans le menu</label>
                                <input type="text" id="new-label" name="label" maxlength="160" required class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20" placeholder="Ex. Message d’accueil, refus courtois…">
                            </div>
                            <div>
                                <label for="new-body" class="mb-1 block text-xs font-bold uppercase tracking-wide text-stone-600">Texte inséré dans le commentaire</label>
                                <textarea id="new-body" name="body" rows="5" required maxlength="8000" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20" placeholder="Rédigez le modèle tel qu’il apparaîtra dans le dossier…"></textarea>
                            </div>
                            <div>
                                <label for="new-sort" class="mb-1 block text-xs font-bold uppercase tracking-wide text-stone-600">Ordre d’affichage (optionnel)</label>
                                <input type="number" id="new-sort" name="sort_order" value="0" min="0" max="99999" class="w-36 rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20">
                            </div>
                            <div>
                                <label for="new-context" class="mb-1 block text-xs font-bold uppercase tracking-wide text-stone-600">Contexte</label>
                                <select id="new-context" name="context" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20">
                                    <?php foreach ($contextLabels as $key => $label): ?>
                                    <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#1c2d41] px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-[#1c2d41]/25 transition hover:bg-[#152333]">
                                Ajouter le modèle
                            </button>
                        </form>
                    </div>
                </section>

                <?php if ($tableMissing): ?>
                <?php elseif (empty($rows)): ?>
                    <div class="rounded-2xl border border-dashed border-stone-300 bg-[#faf8f3]/60 px-6 py-12 text-center">
                        <p class="font-serif text-lg font-semibold text-stone-800">Aucun modèle enregistré</p>
                        <p class="mt-2 text-sm text-stone-600">Créez un premier modèle ci-dessus, puis sélectionnez-le sur la fiche d’une candidature en attente de décision.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <h2 class="border-b border-stone-200 pb-2 font-serif text-lg font-bold text-[#1c2d41]">Modèles enregistrés</h2>
                        <?php foreach ($rows as $r): ?>
                            <?php $rid = (int) ($r['id'] ?? 0); ?>
                            <article class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm ring-1 ring-black/[0.02]" data-canned-row data-context="<?= htmlspecialchars((string) ($r['context'] ?? 'generic'), ENT_QUOTES, 'UTF-8') ?>" data-search="<?= htmlspecialchars(mb_strtolower(trim((string) (($r['label'] ?? '') . ' ' . ($r['body'] ?? '') . ' ' . ($r['context'] ?? ''))), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                                <div class="flex items-center justify-between gap-3 border-b border-stone-100 bg-[#f4f1ea] px-5 py-2.5">
                                    <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-stone-500">Réf. <?= $rid ?></span>
                                    <?php $ctx = (string) ($r['context'] ?? 'generic'); ?>
                                    <span class="rounded-md border border-stone-300 bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-stone-700"><?= htmlspecialchars($contextLabels[$ctx] ?? $contextLabels['generic'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="p-5 sm:p-6">
                                    <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits/' . $rid . '/update')) ?>" class="space-y-4">
                                        <?= \App\Core\Csrf::field() ?>
                                        <div>
                                            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-stone-600">Intitulé</label>
                                            <input type="text" name="label" value="<?= htmlspecialchars((string) ($r['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="160" required class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-stone-600">Texte</label>
                                            <textarea name="body" rows="4" required maxlength="8000" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20"><?= htmlspecialchars((string) ($r['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-stone-600">Ordre</label>
                                            <input type="number" name="sort_order" value="<?= (int) ($r['sort_order'] ?? 0) ?>" min="0" max="99999" class="w-36 rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-stone-600">Contexte</label>
                                            <select name="context" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20">
                                                <?php foreach ($contextLabels as $key => $label): ?>
                                                <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $ctx === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="flex flex-wrap gap-3 pt-2">
                                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#1c2d41] px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#152333]">Enregistrer</button>
                                        </div>
                                    </form>
                                    <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits/' . $rid . '/delete')) ?>" class="mt-5 border-t border-stone-100 pt-5" onsubmit="return confirm('Supprimer ce modèle ? Cette action est définitive.');">
                                        <?= \App\Core\Csrf::field() ?>
                                        <button type="submit" class="text-sm font-semibold text-rose-800 underline decoration-rose-300 underline-offset-2 hover:decoration-rose-600">Supprimer ce modèle</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
<?php require base_path('views/admin/recruitment_workspace/partials/command_shell_close.php'); ?>
<script>
(function () {
    var searchInput = document.getElementById('canned-search');
    var contextInput = document.getElementById('canned-context-filter');
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-canned-row]'));
    var count = document.getElementById('canned-visible-count');
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
    }
    searchInput.addEventListener('input', applyFilter);
    contextInput.addEventListener('change', applyFilter);
})();
</script>
