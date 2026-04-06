<div class="mx-auto max-w-xl px-4 py-8">
    <h1 class="text-2xl font-black text-slate-900">Export dossier formation</h1>
    <p class="mt-2 text-sm text-slate-600">Archive ZIP : tableau des parcours terminés et attestations PDF déjà générées sur le serveur.</p>
    <form method="post" action="<?= htmlspecialchars(url('back-office/conformite/export-dossier/telecharger'), ENT_QUOTES, 'UTF-8') ?>" class="mt-8 space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <?= \App\Core\Csrf::field() ?>
        <label class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" name="anonymize" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600">
            <span>
                <span class="block text-sm font-bold text-slate-900">Pseudonymiser les personnes</span>
                <span class="text-xs text-slate-500">Remplace les noms par des identifiants stables dans le fichier tableau (pas dans les PDF déjà générés).</span>
            </span>
        </label>
        <button type="submit" class="w-full rounded-lg bg-slate-900 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-800">Télécharger le ZIP</button>
    </form>
</div>
