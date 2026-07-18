<?php
$askUrl = url('api/assistant/ask');
?>
<div class="bg-slate-50 pb-16 sm:pb-24">
    <div class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Doctrine</p>
            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Assistant</h1>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                Posez une question liée à votre communauté. Les réponses restent limitées au périmètre de votre organisation.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-3xl space-y-8 px-4 py-10 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <label for="assistant-question" class="block text-sm font-semibold text-slate-900">Votre question</label>
            <textarea id="assistant-question" rows="4" class="mt-3 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/30" placeholder="Ex. Où trouver le calendrier des manœuvres ?"></textarea>
            <button type="button" id="assistant-ask-btn" class="mt-4 inline-flex rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                Demander
            </button>
            <div id="assistant-answer" class="mt-6 hidden rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700" role="status" aria-live="polite"></div>
        </section>

        <p class="text-center text-sm text-slate-500">
            <a href="<?= htmlspecialchars(url('search'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 underline decoration-emerald-200 underline-offset-2 hover:text-emerald-800">Ouvrir la recherche du portail</a>
            ·
            <a href="<?= htmlspecialchars(url('documentation'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 underline decoration-emerald-200 underline-offset-2 hover:text-emerald-800">Guide intégré</a>
        </p>
    </div>
</div>
<script>
(function () {
    var btn = document.getElementById('assistant-ask-btn');
    var input = document.getElementById('assistant-question');
    var out = document.getElementById('assistant-answer');
    if (!btn || !input || !out) return;
    btn.addEventListener('click', function () {
        var q = (input.value || '').trim();
        if (!q) {
            out.classList.remove('hidden');
            out.textContent = 'Saisissez une question pour continuer.';
            return;
        }
        out.classList.remove('hidden');
        out.textContent = 'Recherche en cours…';
        var body = new FormData();
        body.append('question', q);
        fetch(<?= json_encode($askUrl, JSON_UNESCAPED_SLASHES) ?>, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    out.textContent = (data && data.error) ? data.error : 'Impossible d’obtenir une réponse pour le moment.';
                    return;
                }
                var html = '<p>' + String(data.answer || '').replace(/</g, '&lt;') + '</p>';
                if (data.suggestions && data.suggestions.length) {
                    html += '<ul class="mt-3 space-y-1">';
                    data.suggestions.forEach(function (s) {
                        html += '<li><a class="font-semibold text-emerald-700 underline-offset-2 hover:underline" href="' + String(s.href || '#').replace(/"/g, '') + '">' + String(s.label || '').replace(/</g, '&lt;') + '</a></li>';
                    });
                    html += '</ul>';
                }
                out.innerHTML = html;
            })
            .catch(function () {
                out.textContent = 'Impossible d’obtenir une réponse pour le moment. Réessayez plus tard.';
            });
    });
})();
</script>
