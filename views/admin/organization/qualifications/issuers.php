<?php
$issuers = $issuers ?? [];
$searchQuery = (string) ($searchQuery ?? '');
$memberSearchUrl = (string) ($memberSearchUrl ?? url('api/admin/qualifications/members'));
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$kindLabel = static function (string $kind): string {
    return \App\Support\QualificationUsArmyIssuerExamples::kindLabel($kind);
};
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <a href="<?= url('back-office/referentiels/qualifications') ?>" class="text-sm text-slate-500 hover:text-slate-800">← Référentiel</a>
    <h1 class="text-2xl font-black text-slate-900 mt-2 mb-2">Organismes émetteurs</h1>
    <p class="text-sm text-slate-600 mb-6">Écoles, unités et organismes qui délivrent les qualifications du catalogue. Utilisés lors de l’attribution.</p>

    <?php if ($flashSuccess): ?><p class="mb-4 text-sm text-emerald-700 bg-emerald-50 px-3 py-2 rounded"><?= htmlspecialchars((string) $flashSuccess) ?></p><?php endif; ?>
    <?php if ($flashError): ?><p class="mb-4 text-sm text-red-700 bg-red-50 px-3 py-2 rounded"><?= htmlspecialchars((string) $flashError) ?></p><?php endif; ?>

    <section class="rounded-xl border border-slate-200 bg-white p-5 mb-6" data-qr-member-lookup data-search-url="<?= htmlspecialchars($memberSearchUrl, ENT_QUOTES, 'UTF-8') ?>">
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-2">Retrouver l’identifiant d’un membre</h2>
        <p class="text-xs text-slate-500 mb-3">Utile avant une attribution : cherchez le personnel, copiez son id interne, puis ouvrez « Attribuer ».</p>
        <input type="search" id="qr-issuer-member-search" autocomplete="off"
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
               placeholder="Nom, indicatif, matricule, id…">
        <div id="qr-issuer-member-results" class="qr-search-results hidden mt-2"></div>
        <p id="qr-issuer-member-status" class="mt-2 text-xs text-slate-500 hidden" role="status"></p>
        <div id="qr-issuer-member-found" class="qr-member-chip mt-3 hidden">
            <strong id="qr-issuer-member-name"></strong>
            <span class="qr-member-meta" id="qr-issuer-member-meta"></span>
            <a id="qr-issuer-member-award" href="#" class="text-xs font-semibold text-emerald-800 underline ml-auto">Attribuer une qualification</a>
        </div>
    </section>

    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <form method="get" action="<?= url('back-office/referentiels/qualifications/emetteurs') ?>" class="flex flex-wrap gap-2 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1" for="issuer-q">Filtrer</label>
                <input type="search" name="q" id="issuer-q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
                       class="border border-slate-300 rounded-lg px-3 py-2 text-sm min-w-[14rem]"
                       placeholder="USAIS, Airborne, Ranger…">
            </div>
            <button class="px-3 py-2 text-sm font-semibold border border-slate-300 rounded-lg hover:bg-slate-50">Chercher</button>
            <?php if ($searchQuery !== ''): ?>
                <a href="<?= url('back-office/referentiels/qualifications/emetteurs') ?>" class="px-3 py-2 text-sm text-slate-600 underline">Effacer</a>
            <?php endif; ?>
        </form>
        <form method="post" action="<?= url('back-office/referentiels/qualifications/emetteurs/exemples-us-army') ?>"
              onsubmit="return confirm('Ajouter les exemples réalistes US Army manquants (écoles / SOF) ?');">
            <?= \App\Core\Csrf::field() ?>
            <button class="px-3 py-2 text-sm font-semibold bg-slate-900 text-white rounded-lg hover:bg-slate-800">
                Charger exemples US Army
            </button>
        </form>
    </div>

    <div class="mb-6 rounded-xl border border-slate-200 bg-white overflow-hidden">
        <?php if ($issuers === []): ?>
            <p class="px-4 py-8 text-sm text-slate-500 text-center">Aucun organisme. Chargez les exemples US Army ou créez-en un ci-dessous.</p>
        <?php else: ?>
            <?php foreach ($issuers as $i): ?>
                <div class="qr-issuer-row text-sm">
                    <div>
                        <strong class="text-slate-900"><?= htmlspecialchars((string) $i['name']) ?></strong>
                        <?php if (!empty($i['short_name'])): ?>
                            <span class="text-slate-500">(<?= htmlspecialchars((string) $i['short_name']) ?>)</span>
                        <?php endif; ?>
                        <?php if (!empty($i['parent_name'])): ?>
                            <p class="text-xs text-slate-500 mt-0.5">Rattaché à <?= htmlspecialchars((string) $i['parent_name']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="qr-kind"><?= htmlspecialchars($kindLabel((string) ($i['issuer_kind'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <details class="rounded-xl border border-dashed border-slate-300 bg-slate-50/60 p-5 mb-6">
        <summary class="text-sm font-semibold text-slate-800 cursor-pointer">Exemples inclus (US Army)</summary>
        <ul class="mt-3 text-sm text-slate-600 space-y-1 list-disc pl-5">
            <li>MCoE → USAIS, Airborne School, Ranger School, Pathfinder, Sniper School</li>
            <li>USASOC → USAJFKSWCS, 1st SFC, 75th Ranger Regiment, 160th SOAR</li>
            <li>USAJFKSWCS → Military Free Fall School, CDQC</li>
            <li>SERE Level C, Combined Arms Center</li>
        </ul>
    </details>

    <form method="post" action="<?= url('back-office/referentiels/qualifications/emetteurs') ?>" class="space-y-3 rounded-xl border border-slate-200 bg-white p-6">
        <?= \App\Core\Csrf::field() ?>
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-1">Ajouter un organisme</h2>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Nom</label>
            <input name="name" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="Ex. U.S. Army Airborne School">
        </div>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nom court</label>
                <input name="short_name" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="Ex. Airborne School">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nature</label>
                <select name="issuer_kind" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                    <option value="school">École</option>
                    <option value="unit" selected>Unité</option>
                    <option value="external">Organisme externe</option>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Rattaché à</label>
            <select name="parent_issuer_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <option value="">—</option>
                <?php foreach ($issuers as $i): ?>
                    <option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg">Ajouter</button>
    </form>
</div>
<script>
(function () {
    var root = document.querySelector('[data-qr-member-lookup]');
    if (!root) return;
    var baseUrl = root.getAttribute('data-search-url');
    var input = document.getElementById('qr-issuer-member-search');
    var panel = document.getElementById('qr-issuer-member-results');
    var statusEl = document.getElementById('qr-issuer-member-status');
    var found = document.getElementById('qr-issuer-member-found');
    var nameEl = document.getElementById('qr-issuer-member-name');
    var metaEl = document.getElementById('qr-issuer-member-meta');
    var awardLink = document.getElementById('qr-issuer-member-award');
    var awardBase = <?= json_encode(url('back-office/referentiels/qualifications/attribuer'), JSON_UNESCAPED_SLASHES) ?>;
    if (!baseUrl || !input || !panel) return;
    var debounce = null;

    function setStatus(msg, isErr) {
        if (!statusEl) return;
        if (!msg) { statusEl.classList.add('hidden'); statusEl.textContent = ''; return; }
        statusEl.classList.remove('hidden');
        statusEl.textContent = msg;
        statusEl.className = 'mt-2 text-xs ' + (isErr ? 'text-rose-600' : 'text-slate-500');
    }

    function pick(u) {
        if (found) found.classList.remove('hidden');
        if (nameEl) nameEl.textContent = u.display_name || u.callsign || ('Membre #' + u.id);
        var bits = ['id ' + u.id];
        if (u.callsign) bits.push(u.callsign);
        if (u.tenant_member_number) bits.push('mat. ' + u.tenant_member_number);
        if (u.athena_identifier) bits.push(u.athena_identifier);
        if (metaEl) metaEl.textContent = bits.join(' · ');
        if (awardLink) awardLink.href = awardBase + '?user_id=' + encodeURIComponent(u.id);
        panel.classList.add('hidden');
        panel.innerHTML = '';
        setStatus('Identifiant prêt : ' + u.id, false);
    }

    input.addEventListener('input', function () {
        var q = (input.value || '').trim();
        clearTimeout(debounce);
        if (q.length < 1 || (q.length < 2 && !/^\d+$/.test(q))) {
            panel.classList.add('hidden');
            setStatus('', false);
            return;
        }
        debounce = setTimeout(function () {
            setStatus('Recherche…', false);
            fetch(baseUrl + (baseUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (data) {
                var users = (data && data.users) || [];
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
                    var t1 = document.createElement('div');
                    t1.className = 'font-medium text-slate-900';
                    t1.textContent = u.display_name || u.callsign || ('#' + u.id);
                    var t2 = document.createElement('div');
                    t2.className = 'text-xs text-slate-500';
                    t2.textContent = 'id ' + u.id + (u.tenant_member_number ? (' · mat. ' + u.tenant_member_number) : '');
                    btn.appendChild(t1);
                    btn.appendChild(t2);
                    btn.addEventListener('click', function () { pick(u); });
                    panel.appendChild(btn);
                });
                panel.classList.remove('hidden');
            }).catch(function () {
                setStatus('Recherche indisponible.', true);
            });
        }, 220);
    });
})();
</script>
