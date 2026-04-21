<?php
declare(strict_types=1);
if (!\App\Core\Session::get('user_id')) {
    return;
}
$crEndpoint = url('api/community/report');
$crCsrf = \App\Core\Csrf::token();
?>
<style>
  /* Hors Tailwind : z-index + calque voile / dialogue (évite flou sur le formulaire et sous-couche des .lms-panel). */
  #community-report-modal {
    z-index: 10000;
    isolation: isolate;
  }
  #cr-modal-backdrop {
    -webkit-backdrop-filter: blur(4px);
    backdrop-filter: blur(4px);
  }
</style>
<div id="community-report-modal" class="fixed inset-0 hidden flex items-center justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="community-report-title" tabindex="-1">
    <div id="cr-modal-backdrop" class="absolute inset-0 bg-slate-900/55" aria-hidden="true"></div>
    <div class="relative z-10 my-auto w-full max-w-md">
    <div class="w-full rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/20 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/90">
            <h2 id="community-report-title" class="text-sm font-black uppercase tracking-wide text-slate-900">Signaler un contenu</h2>
            <p id="community-report-summary" class="mt-1 text-xs text-slate-600 leading-relaxed"></p>
        </div>
        <form id="community-report-form" class="p-5 space-y-4">
            <input type="hidden" name="target_type" id="cr-target-type" value="">
            <input type="hidden" name="target_id" id="cr-target-id" value="0">
            <input type="hidden" name="documentation_key" id="cr-doc-key" value="">
            <input type="hidden" name="reported_url" id="cr-reported-url" value="">
            <input type="hidden" name="page_url" id="cr-page-url" value="">
            <div>
                <label for="cr-reason" class="block text-xs font-bold text-slate-700 mb-1.5">Motif</label>
                <select id="cr-reason" name="reason" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 bg-white">
                    <option value="inappropriate">Contenu inapproprié</option>
                    <option value="harassment">Harcèlement</option>
                    <option value="spam">Spam ou publicité abusive</option>
                    <option value="suspicious_link">Lien ou pièce douteuse</option>
                    <option value="illegal">Contenu illégal</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            <div>
                <label for="cr-details" class="block text-xs font-bold text-slate-700 mb-1.5">Précisions (optionnel)</label>
                <textarea id="cr-details" name="details" rows="4" maxlength="2000" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" placeholder="Expliquez brièvement le problème pour aider l’équipe de modération."></textarea>
            </div>
            <p class="text-[11px] text-slate-500 leading-relaxed">Votre signalement est confidentiel. Les modérateurs de la communauté en sont informés.</p>
            <div class="flex flex-wrap gap-2 justify-end pt-1">
                <button type="button" id="cr-cancel" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-slate-700 hover:bg-slate-50">Annuler</button>
                <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-white hover:bg-rose-500">Envoyer</button>
            </div>
        </form>
    </div>
    </div>
</div>
<script>
(function () {
    if (document.documentElement.getAttribute('data-community-report-modal-init') === '1') {
        return;
    }
    document.documentElement.setAttribute('data-community-report-modal-init', '1');

    var endpoint = <?= json_encode($crEndpoint, JSON_UNESCAPED_SLASHES) ?>;
    var csrf = <?= json_encode($crCsrf, JSON_UNESCAPED_SLASHES) ?>;

    function boot() {
        var modal = document.getElementById('community-report-modal');
        var form = document.getElementById('community-report-form');
        if (!modal || !form) {
            return;
        }
        var summaryEl = document.getElementById('community-report-summary');
        var cancelBtn = document.getElementById('cr-cancel');
        var backdropEl = document.getElementById('cr-modal-backdrop');

        function fillCr(opts) {
            opts = opts || {};
            document.getElementById('cr-target-type').value = opts.type || '';
            document.getElementById('cr-target-id').value = String(opts.id || '0');
            document.getElementById('cr-doc-key').value = opts.docKey || '';
            document.getElementById('cr-reported-url').value = opts.reportedUrl || '';
            document.getElementById('cr-page-url').value = opts.pageUrl || window.location.href;
            if (summaryEl) {
                summaryEl.textContent = opts.summary || '';
            }
        }

        function showCr() {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(function () {
                try {
                    modal.focus({ preventScroll: true });
                } catch (e1) {
                    modal.focus();
                }
                var reason = document.getElementById('cr-reason');
                if (reason) {
                    try {
                        reason.focus({ preventScroll: true });
                    } catch (e2) {
                        reason.focus();
                    }
                }
            });
        }

        function closeCr() {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        document.addEventListener('click', function (ev) {
            var btn = ev.target && typeof ev.target.closest === 'function' ? ev.target.closest('[data-community-report]') : null;
            if (!btn) {
                return;
            }
            ev.preventDefault();
            fillCr({
                type: btn.getAttribute('data-cr-type') || '',
                id: parseInt(btn.getAttribute('data-cr-id') || '0', 10) || 0,
                docKey: btn.getAttribute('data-cr-doc-key') || '',
                reportedUrl: btn.getAttribute('data-cr-reported-url') || '',
                pageUrl: btn.getAttribute('data-cr-page-url') || window.location.href,
                summary: btn.getAttribute('data-cr-summary') || ''
            });
            showCr();
        }, false);

        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeCr);
        }
        modal.addEventListener('click', function (e) {
            if (e.target === modal || (backdropEl && e.target === backdropEl)) {
                closeCr();
            }
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeCr();
            }
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var payload = {
                csrf_token: csrf,
                target_type: document.getElementById('cr-target-type').value,
                target_id: parseInt(document.getElementById('cr-target-id').value, 10) || 0,
                documentation_key: document.getElementById('cr-doc-key').value,
                reported_url: document.getElementById('cr-reported-url').value,
                page_url: document.getElementById('cr-page-url').value,
                reason: document.getElementById('cr-reason').value,
                details: document.getElementById('cr-details').value
            };
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (x) {
                    if (x.ok && x.j && x.j.success) {
                        closeCr();
                        document.getElementById('cr-details').value = '';
                        alert('Merci, votre signalement a été transmis à l’équipe de modération.');
                    } else {
                        alert((x.j && x.j.error) ? x.j.error : 'Envoi impossible pour le moment.');
                    }
                })
                .catch(function () { alert('Erreur réseau.'); });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
