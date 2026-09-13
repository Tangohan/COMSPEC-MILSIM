<?php
$definitions = $definitions ?? [];
$issuers = $issuers ?? [];
$levels = $levels ?? [];
$customFields = $customFields ?? [];
$userId = (int) ($userId ?? 0);
$definitionId = (int) ($definitionId ?? 0);
$renewalOf = (int) ($renewalOf ?? 0);
$prefill = $prefill ?? null;
$selectedMember = $selectedMember ?? null;
$adminStatuses = $adminStatuses ?? [];
$visibilityLevels = $visibilityLevels ?? [];
$suggestedExpires = $suggestedExpires ?? null;
$memberSearchUrl = (string) ($memberSearchUrl ?? url('api/admin/qualifications/members'));
$flashError = \App\Core\Session::getFlash('error');

$memberLabel = '';
if (is_array($selectedMember)) {
    $memberLabel = trim((string) ($selectedMember['display_name'] ?? ''));
    if ($memberLabel === '') {
        $memberLabel = trim((string) ($selectedMember['callsign'] ?? ''));
    }
    if ($memberLabel === '') {
        $memberLabel = 'Membre #' . $userId;
    }
}
?>
<div class="max-w-3xl mx-auto px-6 py-12 qr-award">
    <a href="<?= url('back-office/referentiels/qualifications') ?>" class="text-sm text-slate-500 hover:text-slate-800">← Référentiel</a>
    <h1 class="text-2xl font-black text-slate-900 mt-2 mb-2"><?= $renewalOf > 0 ? 'Renouveler une qualification' : 'Attribuer une qualification' ?></h1>
    <p class="text-sm text-slate-600 mb-6">Lie une définition du catalogue à un membre, avec organisme émetteur, dates et statut administratifs.</p>

    <section class="qr-principle rounded-xl p-5 mb-6" aria-labelledby="qr-principle-title">
        <h2 id="qr-principle-title" class="text-xs font-black uppercase tracking-widest text-slate-600 mb-3">Principe</h2>
        <div class="qr-flow-steps mb-4">
            <div class="qr-flow-step">
                <span>1 · Catalogue</span>
                <p class="text-sm text-slate-700">Le référentiel décrit <strong>ce qu’est</strong> une qualification (code, niveaux, validité, champs).</p>
            </div>
            <div class="qr-flow-step">
                <span>2 · Émetteur</span>
                <p class="text-sm text-slate-700">L’organisme (école / unité) atteste <strong>qui a délivré</strong> le titre — ex. Airborne School, USAJFKSWCS.</p>
            </div>
            <div class="qr-flow-step">
                <span>3 · Attribution</span>
                <p class="text-sm text-slate-700">Cette page crée le <strong>dossier individuel</strong> : membre, dates, statut, brevet éventuel.</p>
            </div>
        </div>
        <ol class="text-sm text-slate-600 space-y-1.5">
            <li>Choisir le membre (recherche par nom, indicatif, matricule ou identifiant interne).</li>
            <li>Sélectionner la qualification du catalogue — les niveaux et champs spécifiques se rechargent alors.</li>
            <li>Renseigner l’émetteur, les dates et le statut (ex. Obtenue). La saisie rétroactive n’envoie pas de notification.</li>
        </ol>
    </section>

    <?php if ($flashError): ?>
        <p class="mb-4 text-sm text-red-700 bg-red-50 px-3 py-2 rounded"><?= htmlspecialchars((string) $flashError) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= url('back-office/referentiels/qualifications/attribuer') ?>" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        <?= \App\Core\Csrf::field() ?>
        <?php if ($renewalOf > 0): ?>
            <input type="hidden" name="renewal_of_id" value="<?= $renewalOf ?>">
        <?php endif; ?>

        <div data-qr-member-picker data-search-url="<?= htmlspecialchars($memberSearchUrl, ENT_QUOTES, 'UTF-8') ?>">
            <label class="block text-xs font-semibold text-slate-600 mb-1" for="qr-member-search">Membre</label>
            <p class="text-xs text-slate-500 mb-2">Recherchez par nom, indicatif, e-mail, identifiant Athena, matricule ou id numérique. L’identifiant interne est renseigné automatiquement.</p>
            <input type="search" id="qr-member-search" autocomplete="off"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                   placeholder="Ex. Hawk, 42, MAT-1204…">
            <div id="qr-member-results" class="qr-search-results hidden mt-2" role="listbox"></div>
            <p id="qr-member-status" class="mt-2 text-xs text-slate-500 hidden" role="status"></p>
            <input type="hidden" name="user_id" id="qr-user-id" required value="<?= $userId > 0 ? $userId : '' ?>">
            <div id="qr-member-chip" class="qr-member-chip mt-3 <?= $userId > 0 ? '' : 'hidden' ?>">
                <strong id="qr-member-chip-name"><?= htmlspecialchars($memberLabel !== '' ? $memberLabel : 'Membre sélectionné', ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="qr-member-meta" id="qr-member-chip-meta">id <?= $userId > 0 ? $userId : '—' ?></span>
                <button type="button" id="qr-member-clear" class="text-xs font-semibold text-rose-700 underline ml-auto">Changer</button>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1" for="qr-definition-id">Qualification</label>
            <select name="definition_id" id="qr-definition-id" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Choisir…</option>
                <?php foreach ($definitions as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= $definitionId === (int) $d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $d['name']) ?> (<?= htmlspecialchars((string) $d['code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-slate-500 mt-1">Changer la qualification recharge niveaux et champs spécifiques.</p>
        </div>

        <?php if ($levels !== []): ?>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Niveau</label>
            <select name="qualification_level_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <option value="">—</option>
                <?php foreach ($levels as $lvl): ?>
                    <option value="<?= (int) $lvl['id'] ?>"><?= htmlspecialchars((string) $lvl['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Organisme émetteur</label>
            <select name="issuer_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <option value="">—</option>
                <?php foreach ($issuers as $i): ?>
                    <option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?><?= !empty($i['short_name']) ? ' (' . htmlspecialchars((string) $i['short_name']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($issuers === []): ?>
                <p class="text-xs text-amber-700 mt-1">Aucun émetteur : <a class="underline font-semibold" href="<?= url('back-office/referentiels/qualifications/emetteurs') ?>">ajoutez des organismes</a> (exemples US Army disponibles).</p>
            <?php endif; ?>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Statut initial</label>
                <select name="admin_status" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                    <?php foreach ($adminStatuses as $st): ?>
                        <option value="<?= htmlspecialchars((string) $st) ?>" <?= $st === 'obtained' ? 'selected' : '' ?>><?= htmlspecialchars(\App\Support\QualificationAdminStatus::label((string) $st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Visibilité</label>
                <select name="visibility_level" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                    <?php foreach ($visibilityLevels as $vl): ?>
                        <option value="<?= htmlspecialchars((string) $vl) ?>"><?= htmlspecialchars(\App\Support\VisibilityLevel::label((string) $vl)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Date d’obtention</label>
                <input type="date" name="obtained_at" value="<?= htmlspecialchars((string) ($prefill['obtained_at'] ?? date('Y-m-d'))) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Date d’expiration</label>
                <input type="date" name="expires_at" value="<?= htmlspecialchars((string) ($suggestedExpires ?? '')) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">N° de brevet (optionnel)</label>
                <input name="certificate_number" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="Généré automatiquement si vide">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Référence</label>
                <input name="reference" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
            <textarea name="notes" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"></textarea>
        </div>

        <?php if ($customFields !== []): ?>
        <div class="space-y-3 border-t border-slate-100 pt-4">
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-500">Champs spécifiques</h2>
            <?php foreach ($customFields as $cf): ?>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1"><?= htmlspecialchars((string) $cf['name']) ?><?= !empty($cf['is_required']) ? ' *' : '' ?></label>
                    <?php if (($cf['field_type'] ?? '') === 'text_long'): ?>
                        <textarea name="custom_values[<?= (int) $cf['id'] ?>]" rows="2" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"><?= htmlspecialchars((string) ($cf['default_value'] ?? '')) ?></textarea>
                    <?php elseif (($cf['field_type'] ?? '') === 'boolean'): ?>
                        <select name="custom_values[<?= (int) $cf['id'] ?>]" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="0">Non</option>
                            <option value="1">Oui</option>
                        </select>
                    <?php else: ?>
                        <input name="custom_values[<?= (int) $cf['id'] ?>]" value="<?= htmlspecialchars((string) ($cf['default_value'] ?? '')) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" <?= ($cf['field_type'] ?? '') === 'date' ? 'type="date"' : (($cf['field_type'] ?? '') === 'number' ? 'type="number"' : 'type="text"') ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="flex flex-wrap gap-4 text-sm">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_primary" value="1"> Qualification principale</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_retrospective" value="1"> Saisie rétroactive (sans notification)</label>
        </div>

        <div class="flex justify-end">
            <button class="px-4 py-2.5 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800">Enregistrer l’attribution</button>
        </div>
    </form>
</div>
<script>
(function () {
    var root = document.querySelector('[data-qr-member-picker]');
    if (!root) return;
    var baseUrl = root.getAttribute('data-search-url');
    var input = document.getElementById('qr-member-search');
    var panel = document.getElementById('qr-member-results');
    var statusEl = document.getElementById('qr-member-status');
    var hidden = document.getElementById('qr-user-id');
    var chip = document.getElementById('qr-member-chip');
    var chipName = document.getElementById('qr-member-chip-name');
    var chipMeta = document.getElementById('qr-member-chip-meta');
    var clearBtn = document.getElementById('qr-member-clear');
    var defSelect = document.getElementById('qr-definition-id');
    if (!baseUrl || !input || !panel || !hidden) return;
    var debounce = null;

    function setStatus(msg, isErr) {
        if (!statusEl) return;
        if (!msg) {
            statusEl.classList.add('hidden');
            statusEl.textContent = '';
            return;
        }
        statusEl.classList.remove('hidden');
        statusEl.textContent = msg;
        statusEl.className = 'mt-2 text-xs ' + (isErr ? 'text-rose-600' : 'text-slate-500');
    }

    function selectMember(u) {
        hidden.value = String(u.id || '');
        if (chip) chip.classList.remove('hidden');
        if (chipName) chipName.textContent = u.display_name || u.callsign || ('Membre #' + u.id);
        var bits = ['id ' + u.id];
        if (u.callsign) bits.push(u.callsign);
        if (u.tenant_member_number) bits.push('mat. ' + u.tenant_member_number);
        if (u.athena_identifier) bits.push(u.athena_identifier);
        if (chipMeta) chipMeta.textContent = bits.join(' · ');
        panel.classList.add('hidden');
        panel.innerHTML = '';
        input.value = '';
        setStatus('', false);
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
            btn.setAttribute('role', 'option');
            var title = document.createElement('div');
            title.className = 'font-medium text-slate-900';
            title.textContent = u.display_name || u.callsign || u.email || ('#' + u.id);
            var sub = document.createElement('div');
            sub.className = 'text-xs text-slate-500';
            var parts = ['id ' + u.id];
            if (u.callsign) parts.push(u.callsign);
            if (u.tenant_member_number) parts.push('mat. ' + u.tenant_member_number);
            if (u.athena_identifier) parts.push(u.athena_identifier);
            if (u.email) parts.push(u.email);
            sub.textContent = parts.join(' · ');
            btn.appendChild(title);
            btn.appendChild(sub);
            btn.addEventListener('click', function () { selectMember(u); });
            panel.appendChild(btn);
        });
        panel.classList.remove('hidden');
    }

    input.addEventListener('input', function () {
        var q = (input.value || '').trim();
        clearTimeout(debounce);
        if (q.length < 1 || (q.length < 2 && !/^\d+$/.test(q))) {
            panel.classList.add('hidden');
            panel.innerHTML = '';
            setStatus(q ? 'Continuez la saisie…' : '', false);
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
                setStatus('Recherche indisponible.', true);
                panel.classList.add('hidden');
            });
        }, 220);
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            hidden.value = '';
            if (chip) chip.classList.add('hidden');
            input.focus();
        });
    }

    if (defSelect) {
        defSelect.addEventListener('change', function () {
            var uid = hidden.value || '';
            var did = defSelect.value || '';
            var url = <?= json_encode(url('back-office/referentiels/qualifications/attribuer'), JSON_UNESCAPED_SLASHES) ?>;
            var params = [];
            if (uid) params.push('user_id=' + encodeURIComponent(uid));
            if (did) params.push('definition_id=' + encodeURIComponent(did));
            <?php if ($renewalOf > 0): ?>
            params.push('renewal_of=<?= (int) $renewalOf ?>');
            <?php endif; ?>
            window.location = url + (params.length ? ('?' + params.join('&')) : '');
        });
    }
})();
</script>
