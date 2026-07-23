<?php
/** @var list<array<string, mixed>> $blockRows */
$blockRows = is_array($blockRows ?? null) ? $blockRows : [];
$typeLabel = static function (string $t): string {
    return match ($t) {
        'steam' => 'Identifiant Steam',
        'ip' => 'Adresse réseau',
        default => 'Restriction',
    };
};
$scopeLabel = static function (string $s): string {
    return $s === 'global' ? 'Toute la plateforme' : 'Cette communauté';
};
?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-10 space-y-10">
    <header>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Tactique · Mod Arma</p>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Restrictions d’accès au mod</h1>
        <p class="mt-3 text-sm text-slate-600 leading-relaxed max-w-2xl">
            Empêchez un joueur ou une adresse réseau d’utiliser le pack Overwatch / ATAK avec votre communauté :
            liaison de compte, synchronisation et envois depuis le jeu seront refusés.
        </p>
        <div class="mt-4 flex flex-wrap gap-3 text-sm">
            <a href="<?= htmlspecialchars(url('admin/atak-mod'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:underline">Pack Overwatch</a>
            <span class="text-slate-300">·</span>
            <a href="<?= htmlspecialchars(url('admin/atak-config'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:underline">Configuration ATAK</a>
            <span class="text-slate-300">·</span>
            <a href="<?= htmlspecialchars(url('admin/system/blocklist'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:underline">Liste e-mail / réseau (site)</a>
        </div>
    </header>

    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?><p class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($s): ?><p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-8 shadow-sm space-y-6">
        <div>
            <h2 class="text-sm font-bold text-slate-900">Ajouter une restriction</h2>
            <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                Recherchez un membre pour remplir l’identifiant Steam, ou saisissez une adresse réseau observée côté serveur.
            </p>
        </div>
        <form method="post" action="<?= htmlspecialchars(url('admin/atak-mod-blocks/add'), ENT_QUOTES, 'UTF-8') ?>" class="grid md:grid-cols-2 gap-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label for="block_kind" class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                <select name="block_kind" id="block_kind" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="steam">Identifiant Steam</option>
                    <option value="ip">Adresse réseau</option>
                </select>
            </div>
            <div class="md:col-span-2 rounded-lg border border-dashed border-slate-200 bg-slate-50/60 p-4"
                 data-mod-block-member-picker
                 data-search-url="<?= htmlspecialchars(url('api/admin/atak-mod-blocks/members'), ENT_QUOTES, 'UTF-8') ?>">
                <p class="text-xs font-semibold text-slate-700 mb-2">Choisir un membre (identifiant Steam)</p>
                <p class="text-xs text-slate-500 mb-2">Tapez au moins deux caractères (nom, indicatif, e-mail ou Steam). Seuls les comptes avec Steam lié peuvent être sélectionnés pour ce type.</p>
                <input type="search"
                       id="mod-block-member-search"
                       autocomplete="off"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white"
                       placeholder="Rechercher un membre…">
                <div id="mod-block-member-results" class="hidden mt-2 max-h-52 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-sm divide-y divide-slate-100"></div>
                <p id="mod-block-member-status" class="mt-2 text-xs text-slate-500 hidden" role="status"></p>
            </div>
            <div class="md:col-span-2">
                <label for="block_value" class="block text-xs font-semibold text-slate-600 mb-1">Valeur</label>
                <input type="text" name="block_value" id="block_value" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                       placeholder="Ex. 76561198… ou 203.0.113.10"
                       autocomplete="off">
            </div>
            <div class="md:col-span-2">
                <label for="block_reason" class="block text-xs font-semibold text-slate-600 mb-1">Motif (interne)</label>
                <input type="text" name="block_reason" id="block_reason"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                       placeholder="Ex. abus de liaison, griefing, contournement…"
                       maxlength="500">
            </div>
            <div class="md:col-span-2 flex flex-wrap gap-4 items-center text-sm text-slate-700">
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="block_duration_mode" value="permanent" checked class="rounded border-slate-300">
                    Sans date de fin
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="block_duration_mode" value="temporary" class="rounded border-slate-300">
                    Pendant
                </label>
                <input type="number" name="block_duration_days" value="30" min="1" max="3650"
                       class="w-20 rounded-lg border border-slate-300 px-2 py-1 text-sm"> jours
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                    Enregistrer la restriction
                </button>
            </div>
        </form>
    </section>

    <section class="space-y-4">
        <h2 class="text-sm font-bold text-slate-900">Restrictions actives</h2>
        <?php if ($blockRows === []): ?>
            <p class="text-sm text-slate-500">Aucune restriction Steam ou réseau active pour le mod sur cette communauté.</p>
        <?php else: ?>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="p-3">Type</th>
                            <th class="p-3">Repère</th>
                            <th class="p-3">Portée</th>
                            <th class="p-3">Fin</th>
                            <th class="p-3">Motif</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blockRows as $b): ?>
                            <?php
                            $id = (int) ($b['id'] ?? 0);
                            $type = (string) ($b['indicator_type'] ?? '');
                            $scope = (string) ($b['scope'] ?? 'tenant');
                            $hint = trim((string) ($b['display_hint'] ?? ''));
                            $canRevoke = $scope === 'tenant';
                            ?>
                            <tr class="border-t border-slate-100">
                                <td class="p-3 font-medium text-slate-800"><?= htmlspecialchars($typeLabel($type), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="p-3 text-slate-600"><?= htmlspecialchars($hint !== '' ? $hint : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="p-3 text-slate-600"><?= htmlspecialchars($scopeLabel($scope), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="p-3 text-slate-600"><?= !empty($b['expires_at']) ? htmlspecialchars((string) $b['expires_at'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                <td class="p-3 text-slate-600"><?= htmlspecialchars(trim((string) ($b['reason'] ?? '')) !== '' ? (string) $b['reason'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="p-3 text-right">
                                    <?php if ($canRevoke): ?>
                                        <form method="post" action="<?= htmlspecialchars(url('admin/atak-mod-blocks/revoke'), ENT_QUOTES, 'UTF-8') ?>" class="inline"
                                              onsubmit="return confirm('Lever cette restriction ?');">
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="indicator_id" value="<?= $id ?>">
                                            <button type="submit" class="text-sm font-semibold text-rose-700 hover:underline">Lever</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400">Site uniquement</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
    var root = document.querySelector('[data-mod-block-member-picker]');
    if (!root) return;
    var baseUrl = root.getAttribute('data-search-url');
    var input = document.getElementById('mod-block-member-search');
    var panel = document.getElementById('mod-block-member-results');
    var statusEl = document.getElementById('mod-block-member-status');
    var target = document.getElementById('block_value');
    var kind = document.getElementById('block_kind');
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
            setStatus('Aucun membre ne correspond.', false);
            panel.classList.add('hidden');
            return;
        }
        setStatus('', false);
        users.forEach(function (u) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full text-left px-3 py-2.5 text-sm hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';
            if (!u.has_steam) {
                btn.className += ' opacity-60';
            }
            var t1 = document.createElement('div');
            t1.className = 'font-medium text-slate-900';
            t1.textContent = u.display_name || u.email || 'Sans nom';
            var t2 = document.createElement('div');
            t2.className = 'text-xs text-slate-500 mt-0.5';
            var bits = [];
            if (u.callsign) bits.push('Indicatif : ' + u.callsign);
            bits.push(u.has_steam ? 'Steam lié' : 'Pas de Steam lié');
            t2.textContent = bits.join(' · ');
            btn.appendChild(t1);
            btn.appendChild(t2);
            btn.addEventListener('click', function () {
                if (!u.has_steam || !u.steam_id) {
                    setStatus('Ce compte n’a pas d’identifiant Steam lié. Liez Steam sur le profil, ou saisissez-le à la main.', true);
                    return;
                }
                target.value = u.steam_id;
                kind.value = 'steam';
                hidePanel();
                input.value = '';
                setStatus('Identifiant Steam renseigné. Vérifiez avant d’enregistrer.', false);
            });
            panel.appendChild(btn);
        });
        panel.classList.remove('hidden');
    }
    input.addEventListener('input', function () {
        clearTimeout(debounce);
        var q = (input.value || '').trim();
        if (q.length < 2) {
            hidePanel();
            setStatus('', false);
            return;
        }
        debounce = setTimeout(function () {
            setStatus('Recherche…', false);
            fetch(baseUrl + (baseUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (data) {
                renderList((data && data.users) || []);
            }).catch(function () {
                setStatus('Recherche indisponible pour le moment.', true);
                hidePanel();
            });
        }, 280);
    });
    document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) hidePanel();
    });
})();
</script>
