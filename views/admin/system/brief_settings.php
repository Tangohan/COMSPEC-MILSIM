<?php
declare(strict_types=1);
$open = !empty($briefMemberAccessOpen);
$msg = (string) ($briefMemberClosedMessage ?? '');
$csrf = $csrfToken ?? \App\Core\Csrf::token();
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Brief — accès pour les membres</h1>
        <a href="<?= url('admin') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Retour</a>
    </div>
    <p class="text-slate-600 mb-6 text-sm leading-relaxed">
        Ce réglage s’applique à <strong class="font-semibold text-slate-800">toutes les communautés</strong> : lorsqu’il est désactivé, les membres ne peuvent plus ouvrir la salle de brief. Les personnes habilitées à la modération du forum ou au back-office y accèdent encore pour préparer la réouverture.
    </p>
    <form id="platform-brief-form" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <label class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" name="brief_member_access" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= $open ? 'checked' : '' ?>>
            <span>
                <span class="block text-sm font-semibold text-slate-900">Autoriser les membres à consulter le brief</span>
                <span class="mt-1 block text-xs leading-relaxed text-slate-600">Désactiver en cas de maintenance ou de consigne transverse. La configuration des canaux par communauté reste dans le back-office de chaque unité.</span>
            </span>
        </label>
        <div>
            <label for="brief_member_closed_message" class="block text-sm font-semibold text-slate-900">Message affiché quand le brief est fermé</label>
            <p class="mt-1 text-xs text-slate-600">Texte court, sans mise en forme avancée. Optionnel.</p>
            <textarea id="brief_member_closed_message" name="brief_member_closed_message" rows="4" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer</button>
            <p id="platform-brief-status" class="text-sm text-slate-600" aria-live="polite"></p>
        </div>
    </form>
</div>
<script>
(function () {
    const form = document.getElementById('platform-brief-form');
    const status = document.getElementById('platform-brief-status');
    if (!form || !status) return;
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        status.textContent = 'Enregistrement…';
        const fd = new FormData(form);
        fd.set('brief_member_access', form.querySelector('[name="brief_member_access"]')?.checked ? '1' : '0');
        try {
            const r = await fetch('<?= url('api/admin/platform-brief-settings') ?>', { method: 'POST', body: fd });
            const j = await r.json().catch(() => ({}));
            if (r.ok && j.success) {
                status.textContent = 'Enregistré.';
            } else {
                status.textContent = j.message || 'Impossible d’enregistrer.';
            }
        } catch (err) {
            status.textContent = 'Problème de connexion.';
        }
    });
})();
</script>
