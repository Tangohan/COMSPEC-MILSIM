<?php
/** @var list<array<string,mixed>> $cannedMessages */
$rows = $cannedMessages ?? [];
$tableMissing = !empty($cannedMessagesTableMissing);
$baseUrl = url('');
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 mb-1">Recrutement</p>
            <h1 class="text-2xl font-black text-slate-900">Messages préfaits</h1>
            <p class="mt-2 text-sm text-slate-600 max-w-xl">Textes réutilisables pour le <strong>commentaire interne</strong> lors du traitement d’une candidature (acceptation, refus, interdiction). Chaque communauté gère sa propre liste.</p>
        </div>
        <a href="<?= $baseUrl ?>/back-office/recruitments" class="text-sm font-semibold text-slate-600 hover:text-slate-900 underline">← Candidatures</a>
    </div>

    <?php if ($flashOk): ?>
        <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars((string) $flashOk) ?></p>
    <?php endif; ?>
    <?php if ($flashErr): ?>
        <p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"><?= htmlspecialchars((string) $flashErr) ?></p>
    <?php endif; ?>
    <?php if ($tableMissing): ?>
        <p class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            La table <code class="rounded bg-amber-100/80 px-1">enlistment_canned_messages</code> n’existe pas encore. Exécutez <strong>php setup-database.php</strong> ou <strong>run-migrations.php</strong> sur le serveur, puis rechargez cette page.
        </p>
    <?php endif; ?>

    <section class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-8 <?= $tableMissing ? 'opacity-50 pointer-events-none' : '' ?>">
        <h2 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Ajouter un modèle</h2>
        <form method="post" action="<?= htmlspecialchars($baseUrl . '/back-office/recruitments/messages-prefaits') ?>" class="space-y-4">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label for="new-label" class="block text-xs font-bold text-slate-700 mb-1">Libellé (menu)</label>
                <input type="text" id="new-label" name="label" maxlength="160" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900" placeholder="Ex. Bienvenue, Refus standard…">
            </div>
            <div>
                <label for="new-body" class="block text-xs font-bold text-slate-700 mb-1">Texte inséré dans le commentaire</label>
                <textarea id="new-body" name="body" rows="5" required maxlength="8000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900" placeholder="Contenu du message interne…"></textarea>
            </div>
            <div>
                <label for="new-sort" class="block text-xs font-bold text-slate-700 mb-1">Ordre d’affichage (optionnel)</label>
                <input type="number" id="new-sort" name="sort_order" value="0" min="0" max="99999" class="w-32 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900">
            </div>
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Ajouter</button>
        </form>
    </section>

    <?php if ($tableMissing): ?>
    <?php elseif (empty($rows)): ?>
        <p class="text-sm text-slate-500 border border-dashed border-slate-200 rounded-xl p-8 text-center">Aucun message préfait pour l’instant. Créez-en un ci-dessus, puis sélectionnez-le sur la fiche d’une candidature en attente.</p>
    <?php else: ?>
        <section class="space-y-6">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Modèles existants</h2>
            <?php foreach ($rows as $r): ?>
                <?php
                $rid = (int) ($r['id'] ?? 0);
                ?>
                <div class="bg-amber-50/80 border border-amber-200 rounded-2xl p-5 shadow-sm">
                    <form method="post" action="<?= htmlspecialchars($baseUrl . '/back-office/recruitments/messages-prefaits/' . $rid . '/update') ?>" class="space-y-3">
                        <?= \App\Core\Csrf::field() ?>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <span class="text-[10px] font-black uppercase tracking-wider text-amber-900/80">#<?= $rid ?></span>
                            <button type="submit" class="text-xs font-bold text-emerald-800 hover:underline">Enregistrer les modifications</button>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-amber-950 mb-1">Libellé</label>
                            <input type="text" name="label" value="<?= htmlspecialchars((string) ($r['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="160" required class="w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm text-slate-900">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-amber-950 mb-1">Texte</label>
                            <textarea name="body" rows="4" required maxlength="8000" class="w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm text-slate-900"><?= htmlspecialchars((string) ($r['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-amber-950 mb-1">Ordre</label>
                            <input type="number" name="sort_order" value="<?= (int) ($r['sort_order'] ?? 0) ?>" min="0" max="99999" class="w-32 rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm text-slate-900">
                        </div>
                    </form>
                    <form method="post" action="<?= htmlspecialchars($baseUrl . '/back-office/recruitments/messages-prefaits/' . $rid . '/delete') ?>" class="mt-4 pt-4 border-t border-amber-200/80" onsubmit="return confirm('Supprimer ce message préfait ?');">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="text-xs font-bold text-red-800 hover:underline">Supprimer ce modèle</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>
