<?php
declare(strict_types=1);
if (!\App\Core\Session::get('user_id')) {
    return;
}
$phEndpoint = url('api/community/report');
$phCsrf = \App\Core\Csrf::token();
?>
<div id="portal-help-modal" class="fixed inset-0 z-[502] hidden flex items-center justify-center p-4 bg-slate-900/55 backdrop-blur-[2px]" role="dialog" aria-modal="true" aria-labelledby="portal-help-title">
    <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/25 overflow-hidden max-h-[min(90dvh,640px)] flex flex-col">
        <div class="px-5 py-4 border-b border-slate-100 bg-rose-50/90 shrink-0">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-600 text-xs font-black text-white shadow-sm" aria-hidden="true">?</span>
                <div class="min-w-0">
                    <h2 id="portal-help-title" class="text-sm font-black uppercase tracking-wide text-rose-950">Aide et signalement</h2>
                    <p class="mt-1 text-xs text-rose-900/80 leading-relaxed">Votre message est transmis aux administrateurs et modérateurs de la communauté. Décrivez le problème avec précision.</p>
                </div>
            </div>
        </div>
        <form id="portal-help-form" class="p-5 space-y-4 overflow-y-auto flex-1 min-h-0">
            <input type="hidden" name="target_type" value="portal_help">
            <input type="hidden" name="target_id" value="0">
            <div>
                <label for="ph-subject" class="block text-xs font-bold text-slate-700 mb-1.5">Sujet</label>
                <select id="ph-subject" name="help_subject" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 bg-white">
                    <option value="">Choisir…</option>
                    <option value="profile">Fiche ou profil</option>
                    <option value="page_content">Contenu affiché sur une page</option>
                    <option value="message">Message ou discussion</option>
                    <option value="user_account">Compte ou personne</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            <div>
                <label for="ph-reference" class="block text-xs font-bold text-slate-700 mb-1.5">Repère utile <span class="font-normal text-slate-500">(optionnel)</span></label>
                <input type="text" id="ph-reference" name="reference_note" maxlength="500" autocomplete="off" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" placeholder="Ex. pseudo, titre, endroit sur la page…">
            </div>
            <div>
                <label for="ph-reason" class="block text-xs font-bold text-slate-700 mb-1.5">Motif</label>
                <select id="ph-reason" name="reason" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 bg-white">
                    <option value="inappropriate">Contenu inapproprié</option>
                    <option value="harassment">Harcèlement</option>
                    <option value="spam">Spam ou publicité abusive</option>
                    <option value="suspicious_link">Lien ou pièce douteuse</option>
                    <option value="illegal">Contenu illégal</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            <div>
                <label for="ph-details" class="block text-xs font-bold text-slate-700 mb-1.5">Votre message</label>
                <textarea id="ph-details" name="details" rows="5" maxlength="2000" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" placeholder="Expliquez la situation pour que l’équipe puisse agir."></textarea>
            </div>
            <p class="text-[11px] text-slate-500 leading-relaxed">La page où vous vous trouvez est indiquée automatiquement. Les échanges restent confidentiels côté modération.</p>
            <div class="flex flex-wrap gap-2 justify-end pt-1 border-t border-slate-100">
                <button type="button" id="ph-cancel" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-slate-700 hover:bg-slate-50">Annuler</button>
                <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-white hover:bg-rose-500 shadow-sm">Envoyer</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('portal-help-modal');
    var form = document.getElementById('portal-help-form');
    if (!modal || !form) return;
    var endpoint = <?= json_encode($phEndpoint, JSON_UNESCAPED_SLASHES) ?>;
    var csrf = <?= json_encode($phCsrf, JSON_UNESCAPED_SLASHES) ?>;

    function openPh() {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        var d = document.getElementById('ph-details');
        if (d) d.focus();
    }
    function closePh() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-portal-help-trigger]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openPh();
        });
    });

    var phCancel = document.getElementById('ph-cancel');
    if (phCancel) phCancel.addEventListener('click', closePh);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closePh();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closePh();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var subj = (document.getElementById('ph-subject') || {}).value || '';
        var details = (document.getElementById('ph-details') || {}).value || '';
        if (!subj.trim()) {
            alert('Choisissez un sujet dans la liste.');
            return;
        }
        if (!details.trim() || details.trim().length < 10) {
            alert('Décrivez la situation en quelques phrases (au moins dix caractères).');
            return;
        }
        var payload = {
            csrf_token: csrf,
            target_type: 'portal_help',
            target_id: 0,
            help_subject: subj,
            reference_note: (document.getElementById('ph-reference') || {}).value || '',
            reason: (document.getElementById('ph-reason') || {}).value || 'other',
            details: details,
            page_url: window.location.href
        };
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (x) {
                if (x.ok && x.j && x.j.success) {
                    closePh();
                    form.reset();
                    alert('Merci, votre message a été transmis à l’équipe de modération.');
                } else {
                    alert((x.j && x.j.error) ? x.j.error : 'Envoi impossible pour le moment.');
                }
            })
            .catch(function () { alert('Erreur réseau.'); });
    });
})();
</script>
