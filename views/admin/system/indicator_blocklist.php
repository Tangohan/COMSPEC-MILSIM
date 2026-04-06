<?php
/** @var list<array<string, mixed>> $blocklistRows */
$indicatorKindLabel = static function (string $t): string {
    return $t === 'ip' ? 'Adresse réseau' : 'Adresse e-mail';
};
?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-10">
    <header class="mb-8">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Administration plateforme</p>
        <h1 class="text-2xl font-black text-slate-900">Liste de restriction (toute la plateforme)</h1>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed">
            Cette liste est <strong class="font-semibold text-slate-800">globale</strong> : elle s’ajoute aux éventuelles restrictions gérées par chaque communauté dans son espace modération, et elle prime pour tout le portail.
        </p>
        <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-700">
            <summary class="cursor-pointer font-semibold text-slate-800 outline-none focus-visible:ring-2 focus-visible:ring-slate-300 rounded">Comment ça fonctionne ?</summary>
            <ul class="mt-3 space-y-2 list-disc pl-5 text-slate-600">
                <li><strong class="text-slate-800">Adresse e-mail</strong> : refuse la connexion, l’inscription et les candidatures qui utilisent cette adresse, sur <em>toutes</em> les communautés.</li>
                <li><strong class="text-slate-800">Adresse réseau</strong> : refuse les connexions (et certains parcours d’accès) depuis cette adresse telle qu’observée par l’hébergement — attention aux réseaux partagés ou mandataires.</li>
                <li>Les entrées peuvent être <strong class="text-slate-800">sans fin</strong> ou <strong class="text-slate-800">temporaires</strong> (date de fin automatique).</li>
                <li>Le tableau ci-dessous n’affiche pas les valeurs complètes (e-mail, réseau) pour limiter l’exposition ; l’historique d’audit garde la trace des actions des opérateurs site.</li>
            </ul>
        </details>
        <a href="<?= url('admin') ?>" class="inline-block mt-4 text-sm text-slate-600 hover:underline">Retour au centre opérateur site</a>
    </header>

    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($f) ?></p><?php endif; ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>

    <section class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm mb-10">
        <h2 class="text-sm font-bold text-slate-800 mb-3">Ajouter une entrée globale</h2>
        <form method="post" action="<?= url('admin/system/blocklist/add') ?>" id="blocklist-add-form" class="grid md:grid-cols-2 gap-3">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <div>
                <label class="block text-xs text-slate-500 mb-1">Type</label>
                <select name="indicator_kind" id="blocklist-indicator-kind" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                    <option value="email">Adresse e-mail</option>
                    <option value="ip">Adresse réseau</option>
                </select>
            </div>
            <div class="md:col-span-2 rounded-lg border border-dashed border-slate-200 bg-slate-50/60 p-4"
                 data-blocklist-user-picker
                 data-search-url="<?= htmlspecialchars(url('api/admin/user-search'), ENT_QUOTES, 'UTF-8') ?>">
                <p class="text-xs font-semibold text-slate-700 mb-2">Choisir un compte enregistré (e-mail)</p>
                <p class="text-xs text-slate-500 mb-2">Tapez au moins deux caractères : nom affiché, indicatif ou début d’adresse de connexion. Cliquez une ligne pour remplir le champ « Valeur » et sélectionner le type e-mail.</p>
                <input type="search"
                       id="blocklist-user-search-input"
                       autocomplete="off"
                       class="w-full border border-slate-300 rounded px-3 py-2 text-sm bg-white"
                       placeholder="Rechercher un membre…">
                <div id="blocklist-user-search-results" class="hidden mt-2 max-h-52 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-sm divide-y divide-slate-100"></div>
                <p id="blocklist-user-search-status" class="mt-2 text-xs text-slate-500 hidden" role="status"></p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-slate-500 mb-1">Valeur</label>
                <input type="text" name="restriction_target" id="blocklist-restriction-target" required class="w-full border border-slate-300 rounded px-3 py-2 text-sm" placeholder="E-mail complet ou adresse réseau observée côté serveur">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-slate-500 mb-1">Motif interne</label>
                <input type="text" name="block_reason" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-2 flex flex-wrap gap-4 items-center text-sm">
                <label class="flex items-center gap-2">
                    <input type="radio" name="block_duration_mode" value="permanent" checked class="rounded border-slate-300"> Sans date de fin
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="block_duration_mode" value="temporary" class="rounded border-slate-300"> Pendant
                </label>
                <input type="number" name="block_duration_days" value="90" min="1" class="w-20 border border-slate-300 rounded px-2 py-1 text-sm"> jours
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800">Enregistrer</button>
            </div>
        </form>
    </section>

    <section>
        <h2 class="text-sm font-bold text-slate-800 mb-3">Entrées actives</h2>
        <?php if ($blocklistRows === []): ?>
            <p class="text-sm text-slate-500">Aucune entrée globale active.</p>
        <?php else: ?>
            <table class="w-full text-sm border border-slate-200 rounded-lg overflow-hidden">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left p-2">Réf.</th>
                        <th class="text-left p-2">Type</th>
                        <th class="text-left p-2">Fin</th>
                        <th class="text-left p-2">Motif</th>
                        <th class="text-left p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blocklistRows as $b): ?>
                    <tr class="border-t border-slate-100 bg-white">
                        <td class="p-2">#<?= (int) ($b['id'] ?? 0) ?></td>
                        <td class="p-2"><?= htmlspecialchars($indicatorKindLabel((string) ($b['indicator_type'] ?? ''))) ?></td>
                        <td class="p-2"><?= !empty($b['expires_at']) ? htmlspecialchars((string) $b['expires_at']) : '—' ?></td>
                        <td class="p-2"><?= htmlspecialchars(trim((string) ($b['reason'] ?? '')) !== '' ? (string) $b['reason'] : '—') ?></td>
                        <td class="p-2">
                            <form method="post" action="<?= url('admin/system/blocklist/revoke') ?>" class="inline">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="indicator_id" value="<?= (int) ($b['id'] ?? 0) ?>">
                                <button type="submit" class="text-rose-600 text-xs underline">Lever</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
    var root = document.querySelector('[data-blocklist-user-picker]');
    if (!root) return;
    var baseUrl = root.getAttribute('data-search-url');
    var input = document.getElementById('blocklist-user-search-input');
    var panel = document.getElementById('blocklist-user-search-results');
    var statusEl = document.getElementById('blocklist-user-search-status');
    var target = document.getElementById('blocklist-restriction-target');
    var kind = document.getElementById('blocklist-indicator-kind');
    if (!baseUrl || !input || !panel || !target || !kind) return;
    var debounce = null;
    function hidePanel() {
        panel.classList.add('hidden');
        panel.innerHTML = '';
    }
    function setStatus(msg, err) {
        if (!statusEl) return;
        if (!msg) {
            statusEl.classList.add('hidden');
            statusEl.textContent = '';
            return;
        }
        statusEl.classList.remove('hidden');
        statusEl.textContent = msg;
        statusEl.className = 'mt-2 text-xs ' + (err ? 'text-rose-600' : 'text-slate-500');
    }
    function renderList(users) {
        panel.innerHTML = '';
        if (!users.length) {
            setStatus('Aucun compte ne correspond à cette recherche.', false);
            panel.classList.add('hidden');
            return;
        }
        setStatus('', false);
        users.forEach(function (u) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full text-left px-3 py-2.5 text-sm hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';
            var t1 = document.createElement('div');
            t1.className = 'font-medium text-slate-900';
            t1.textContent = u.display_name || u.email || 'Sans nom affiché';
            var t2 = document.createElement('div');
            t2.className = 'text-xs text-slate-500 mt-0.5';
            var bits = [];
            if (u.callsign) bits.push('Indicatif : ' + u.callsign);
            if (u.community) bits.push(u.community);
            if (u.account_state) bits.push(u.account_state);
            t2.textContent = bits.join(' · ');
            var t3 = document.createElement('div');
            t3.className = 'text-xs text-slate-600 mt-1 font-mono';
            t3.textContent = u.email;
            btn.appendChild(t1);
            btn.appendChild(t2);
            btn.appendChild(t3);
            btn.addEventListener('click', function () {
                target.value = u.email;
                kind.value = 'email';
                hidePanel();
                input.value = '';
                setStatus('Le champ « Valeur » a été rempli avec l’adresse de connexion choisie. Contrôlez avant d’enregistrer.', false);
            });
            panel.appendChild(btn);
        });
        panel.classList.remove('hidden');
    }
    input.addEventListener('input', function () {
        var q = (input.value || '').trim();
        if (debounce) clearTimeout(debounce);
        hidePanel();
        setStatus('', false);
        if (q.length < 2) return;
        debounce = setTimeout(function () {
            var sep = baseUrl.indexOf('?') >= 0 ? '&' : '?';
            fetch(baseUrl + sep + 'q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { if (!r.ok) throw new Error('bad'); return r.json(); })
                .then(function (data) { renderList(data.users || []); })
                .catch(function () { setStatus('La recherche n’a pas pu aboutir. Réessayez.', true); });
        }, 320);
    });
    document.addEventListener('click', function (e) {
        if (root.contains(e.target)) return;
        hidePanel();
    });
})();
</script>
