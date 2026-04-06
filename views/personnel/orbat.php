<?php
$rosterData = $orbatRosterData ?? null;
$showOrbatEditTools = (bool) ($orbatCanManage ?? false);
$orbatCommanderOptions = $orbatCommanderOptions ?? [];
$orbatCsrfToken = $orbatCsrfToken ?? '';
$baseUrl = rtrim(url(''), '/');
$rosterJson = $rosterData !== null ? json_encode($rosterData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) : 'null';
$orbatCommanderJson = json_encode($orbatCommanderOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>
<style>
    .orbat-page {
        font-family: 'Inter', sans-serif;
        background:
            linear-gradient(rgba(255,255,255,0.82), rgba(255,255,255,0.82)),
            repeating-linear-gradient(0deg, #e5e7eb 0px, #e5e7eb 1px, transparent 1px, transparent 72px),
            repeating-linear-gradient(90deg, #e5e7eb 0px, #e5e7eb 1px, transparent 1px, transparent 72px);
        color: #0f172a;
    }
    .orbat-watermark {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        pointer-events: none; user-select: none; opacity: 0.05;
        font-weight: 900; font-size: clamp(4rem, 16vw, 13rem); letter-spacing: 0.04em; text-transform: uppercase;
    }
    .orbat-tree-root { display: flex; justify-content: center; align-items: flex-start; min-width: max-content; }
    .orbat-node-wrapper { display: flex; flex-direction: column; align-items: center; position: relative; padding: 0 10px; }
    .orbat-node-card {
        min-width: 132px; max-width: 170px;
        background: rgba(255,255,255,0.92); border: 1px solid rgba(15,23,42,0.08); border-radius: 16px;
        padding: 12px 12px 10px; backdrop-filter: blur(8px);
        box-shadow: 0 12px 30px rgba(15,23,42,0.06), inset 0 1px 0 rgba(255,255,255,0.6);
        position: relative; z-index: 2; cursor: pointer;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .orbat-node-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 34px rgba(15,23,42,0.10), inset 0 1px 0 rgba(255,255,255,0.7);
        border-color: rgba(15,23,42,0.16);
    }
    .orbat-node-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
    .orbat-insignia {
        width: 28px; height: 28px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 900; color: #0f172a; border: 1px solid rgba(15,23,42,0.16);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.5);
    }
    .orbat-type-command, .orbat-type-alpha { background: linear-gradient(180deg, #bfdbfe, #93c5fd); }
    .orbat-type-bravo { background: linear-gradient(180deg, #bbf7d0, #86efac); }
    .orbat-type-support { background: linear-gradient(180deg, #fde68a, #fcd34d); }
    .orbat-type-special { background: linear-gradient(180deg, #fecaca, #fca5a5); }
    .orbat-status-dot {
        width: 10px; height: 10px; border-radius: 999px; flex-shrink: 0; border: 1px solid rgba(15,23,42,0.14);
    }
    .orbat-status-active { background: #22c55e; }
    .orbat-status-partial { background: #facc15; }
    .orbat-status-inactive { background: #ef4444; }
    .orbat-node-label { font-size: 11px; font-weight: 900; letter-spacing: 0.12em; text-transform: uppercase; color: #0f172a; line-height: 1.2; }
    .orbat-node-sub { margin-top: 4px; font-size: 10px; color: #475569; font-weight: 700; }
    .orbat-node-meta { margin-top: 8px; display: flex; justify-content: space-between; gap: 6px; font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 800; }
    .orbat-collapse-btn {
        margin-top: 8px; font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.14em; color: #334155;
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 999px; padding: 5px 8px; line-height: 1;
    }
    .orbat-children-container {
        display: flex; justify-content: center; align-items: flex-start; position: relative; margin-top: 34px; padding-top: 22px;
    }
    .orbat-children-container.has-children::before {
        content: ""; position: absolute; top: 0; left: 50%; transform: translateX(-50%);
        width: 1px; height: 22px; background: rgba(15,23,42,0.18);
    }
    .orbat-children-row { display: flex; justify-content: center; align-items: flex-start; position: relative; }
    .orbat-children-row::before {
        content: ""; position: absolute; top: 0; left: 24px; right: 24px; height: 1px; background: rgba(15,23,42,0.18);
    }
    .orbat-child-branch { position: relative; padding-top: 20px; display: flex; justify-content: center; }
    .orbat-child-branch::before {
        content: ""; position: absolute; top: 0; left: 50%; transform: translateX(-50%);
        width: 1px; height: 20px; background: rgba(15,23,42,0.18);
    }
    .orbat-hidden-children { display: none; }
    .orbat-panel {
        background: rgba(255,255,255,0.88); border: 1px solid rgba(15,23,42,0.08);
        box-shadow: 0 16px 36px rgba(15,23,42,0.06); backdrop-filter: blur(10px);
    }
    .orbat-legend-dot { width: 11px; height: 11px; border-radius: 999px; display: inline-block; }
    @media (max-width: 900px) { .orbat-node-card { min-width: 124px; } }
</style>

<div class="orbat-page relative min-h-screen overflow-hidden">
    <div class="orbat-watermark" aria-hidden="true">Athena</div>

    <header class="relative z-10 border-b border-slate-200/80 bg-white/75 backdrop-blur-xl">
        <div class="max-w-[1800px] mx-auto px-6 py-5 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <p class="text-[10px] font-black tracking-[0.32em] uppercase text-slate-400 mb-2">Command Structure</p>
                <h1 class="text-3xl font-black tracking-tight uppercase leading-none">ORBAT</h1>
                <p class="text-sm text-slate-500 mt-3 font-medium">Structure organique, disponibilité des unités, consultation dynamique.</p>
                <?php if ($showOrbatEditTools): ?>
                <p class="mt-2 max-w-2xl text-xs font-semibold leading-relaxed text-emerald-900">Vous gérez cette communauté : les champs à droite permettent de modifier une unité sélectionnée ; l’enregistrement est automatique après une courte pause dans la saisie, et l’organigramme se met à jour sans recharger la page.</p>
                <?php endif; ?>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 min-w-[320px]">
                <div class="orbat-panel rounded-2xl p-4">
                    <p class="text-[9px] font-black tracking-[0.22em] uppercase text-slate-400">Unités</p>
                    <p id="stat-units" class="text-2xl font-black tracking-tight mt-1">0</p>
                </div>
                <div class="orbat-panel rounded-2xl p-4">
                    <p class="text-[9px] font-black tracking-[0.22em] uppercase text-slate-400">Effectif</p>
                    <p id="stat-personnel" class="text-2xl font-black tracking-tight mt-1">0</p>
                </div>
                <div class="orbat-panel rounded-2xl p-4">
                    <p class="text-[9px] font-black tracking-[0.22em] uppercase text-slate-400">Actives</p>
                    <p id="stat-active" class="text-2xl font-black tracking-tight mt-1">0</p>
                </div>
                <div class="orbat-panel rounded-2xl p-4">
                    <p class="text-[9px] font-black tracking-[0.22em] uppercase text-slate-400">Partielles / Inactives</p>
                    <p id="stat-other" class="text-2xl font-black tracking-tight mt-1">0</p>
                </div>
            </div>
        </div>
    </header>

    <?php if ($rosterData !== null): ?>
    <section class="relative z-10 max-w-[1800px] mx-auto px-6 pt-6">
        <div class="orbat-panel rounded-3xl p-4 md:p-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex flex-wrap items-center gap-4 md:gap-6 text-[10px] font-black tracking-[0.18em] uppercase text-slate-500">
                <span class="flex items-center gap-2"><span class="orbat-legend-dot bg-green-500"></span> Active</span>
                <span class="flex items-center gap-2"><span class="orbat-legend-dot bg-yellow-400"></span> Partielle</span>
                <span class="flex items-center gap-2"><span class="orbat-legend-dot bg-red-500"></span> Inactive</span>
                <span class="flex items-center gap-2"><span class="orbat-legend-dot bg-blue-400"></span> Command / Alpha</span>
                <span class="flex items-center gap-2"><span class="orbat-legend-dot bg-green-300"></span> Bravo</span>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <input id="searchInput" type="text" placeholder="Recherche unité, rôle, officier..." class="w-full sm:w-80 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium outline-none focus:border-slate-400">
                <button id="expandAllBtn" type="button" class="rounded-2xl bg-slate-900 text-white px-5 py-3 text-[11px] font-black tracking-[0.16em] uppercase">Déployer tout</button>
                <button id="collapseAllBtn" type="button" class="rounded-2xl bg-white border border-slate-200 text-slate-900 px-5 py-3 text-[11px] font-black tracking-[0.16em] uppercase">Réduire tout</button>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <main class="relative z-10 max-w-[1800px] mx-auto px-6 py-8">
        <div class="grid grid-cols-1 xl:grid-cols-[1fr_360px] gap-6">
            <section class="orbat-panel rounded-[2rem] p-6 md:p-8 overflow-auto">
                <div id="treeContainer" class="min-w-max"></div>
                <?php if ($rosterData === null): ?>
                <div class="py-24 text-center">
                    <p class="text-sm font-bold uppercase tracking-[0.24em] text-slate-400">Aucune unité configurée</p>
                    <p class="mt-4 text-slate-500">Créez des unités dans l’administration.</p>
                    <p class="mt-6"><a href="<?= url('dashboard') ?>" class="text-emerald-600 hover:underline font-semibold">Retour au dashboard</a></p>
                </div>
                <?php endif; ?>
            </section>

            <aside class="orbat-panel rounded-[2rem] p-6">
                <p class="text-[10px] font-black tracking-[0.28em] uppercase text-slate-400 mb-3">Fiche Unité</p>
                <div id="detailsPanel" class="space-y-5">
                    <div>
                        <h2 id="detail-name" class="text-2xl font-black tracking-tight uppercase">Aucune sélection</h2>
                        <p id="detail-role" class="text-sm text-slate-500 font-semibold mt-1">Sélectionnez un élément du roster.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                            <p class="text-[9px] font-black tracking-[0.18em] uppercase text-slate-400">Type</p>
                            <p id="detail-type" class="text-sm font-black uppercase mt-2">—</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                            <p class="text-[9px] font-black tracking-[0.18em] uppercase text-slate-400">Statut</p>
                            <p id="detail-status" class="text-sm font-black uppercase mt-2">—</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                            <p class="text-[9px] font-black tracking-[0.18em] uppercase text-slate-400">Effectif</p>
                            <p id="detail-strength" class="text-sm font-black uppercase mt-2">—</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                            <p class="text-[9px] font-black tracking-[0.18em] uppercase text-slate-400">Chef d’unité</p>
                            <p id="detail-lead" class="text-sm font-black uppercase mt-2">—</p>
                        </div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                        <p class="text-[9px] font-black tracking-[0.18em] uppercase text-slate-400">Mission</p>
                        <p id="detail-mission" class="text-sm text-slate-700 font-medium mt-2 leading-relaxed">—</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                        <p class="text-[9px] font-black tracking-[0.18em] uppercase text-slate-400">Composition</p>
                        <div id="detail-children" class="mt-3 space-y-2 text-sm font-medium text-slate-700"></div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                        <p class="text-[9px] font-black tracking-[0.18em] uppercase text-slate-400">Membres rattachés</p>
                        <p class="mt-1 text-[10px] text-slate-500 leading-snug">Affectations actives, dossier personnel ou unité principale.</p>
                        <div id="detail-members" class="mt-3 space-y-1.5 max-h-52 overflow-y-auto text-sm"></div>
                    </div>
                    <?php if ($showOrbatEditTools): ?>
                    <div id="orbat-edit-panel" class="hidden rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 space-y-3">
                        <p class="text-[10px] font-black uppercase tracking-widest text-emerald-950">Modifier l’unité sélectionnée</p>
                        <p class="text-[11px] text-emerald-900/85 leading-snug">Choisissez une carte dans l’organigramme (pas la racine « Command »). Les modifications sont envoyées automatiquement après une courte pause.</p>
                        <div>
                            <label for="orbat-ed-name" class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Nom affiché</label>
                            <input id="orbat-ed-name" type="text" maxlength="255" autocomplete="off" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label for="orbat-ed-code" class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Sigle ou code court</label>
                            <input id="orbat-ed-code" type="text" maxlength="20" autocomplete="off" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label for="orbat-ed-type" class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Type sur l’organigramme</label>
                            <select id="orbat-ed-type" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                <option value="command">Commandement</option>
                                <option value="alpha">Alpha</option>
                                <option value="bravo">Bravo</option>
                                <option value="support">Soutien</option>
                                <option value="special">Spécial</option>
                            </select>
                        </div>
                        <div>
                            <label for="orbat-ed-mission" class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Mission ou description courte</label>
                            <textarea id="orbat-ed-mission" rows="3" maxlength="8000" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"></textarea>
                        </div>
                        <div>
                            <label for="orbat-ed-commander" class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Chef d’unité référent</label>
                            <select id="orbat-ed-commander" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                <option value="">— Non renseigné —</option>
                                <?php foreach ($orbatCommanderOptions as $opt): ?>
                                <option value="<?= (int) ($opt['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($opt['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p id="orbat-save-status" class="min-h-[1.25rem] text-xs font-medium" role="status" aria-live="polite"></p>
                    </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </main>
</div>

<?php if ($rosterData !== null): ?>
<script>
(function() {
    const appBaseUrl = <?= json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    let rosterData = <?= $rosterJson ?>;
    const showOrbatEditTools = <?= $showOrbatEditTools ? 'true' : 'false' ?>;
    const orbatCsrf = <?= json_encode($orbatCsrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    const apiUnitUrl = appBaseUrl + "/api/orbat/unit";
    const apiRosterUrl = appBaseUrl + "/api/orbat/roster";

    const collapsedState = new Map();
    let lastRosterSnapshot = JSON.stringify(rosterData);
    let currentSelectedUnitId = 0;
    let saveTimer = null;
    let editorsBound = false;
    let saveInFlight = false;
    let isHydratingForm = false;

    function getStatusLabel(status) {
        if (status === "active") return "Active";
        if (status === "partial") return "Partielle";
        return "Inactive";
    }

    function getTypeLabel(type) {
        const labels = { command: "Commandement", alpha: "Alpha", bravo: "Bravo", support: "Soutien", special: "Spécial" };
        return labels[type] || (type || "—");
    }

    function findNodeByUnitId(node, unitId) {
        if (!node) return null;
        if ((node.unitId || 0) === unitId) return node;
        for (let i = 0; i < (node.children || []).length; i++) {
            const f = findNodeByUnitId(node.children[i], unitId);
            if (f) return f;
        }
        return null;
    }

    function applyRosterFromServer(root) {
        if (!root) return;
        rosterData = root;
        lastRosterSnapshot = JSON.stringify(root);
        renderTree(filteredTree(currentSearch));
        const n = findNodeByUnitId(rosterData, currentSelectedUnitId);
        if (n) selectNode(n);
    }

    function bindEditors() {
        if (!showOrbatEditTools || editorsBound) return;
        editorsBound = true;
        ["orbat-ed-name", "orbat-ed-code", "orbat-ed-type", "orbat-ed-mission", "orbat-ed-commander"].forEach(function(id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener("input", scheduleSave);
            el.addEventListener("change", scheduleSave);
        });
    }

    function scheduleSave() {
        if (!showOrbatEditTools || isHydratingForm || saveInFlight) return;
        const uid = currentSelectedUnitId;
        if (uid < 1) return;
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function() { doSave(uid); }, 550);
    }

    async function doSave(unitId) {
        if (saveInFlight) return;
        const statusEl = document.getElementById("orbat-save-status");
        const nameEl = document.getElementById("orbat-ed-name");
        const codeEl = document.getElementById("orbat-ed-code");
        const typeEl = document.getElementById("orbat-ed-type");
        const missionEl = document.getElementById("orbat-ed-mission");
        const cmdEl = document.getElementById("orbat-ed-commander");
        const name = nameEl ? nameEl.value.trim() : "";
        if (!name) {
            if (statusEl) { statusEl.textContent = "Le nom affiché est obligatoire."; statusEl.className = "min-h-[1.25rem] text-xs font-medium text-red-600"; }
            return;
        }
        saveInFlight = true;
        if (statusEl) { statusEl.textContent = "Enregistrement…"; statusEl.className = "min-h-[1.25rem] text-xs font-medium text-slate-500"; }
        const body = new FormData();
        body.append("_csrf_token", orbatCsrf);
        body.append("unit_id", String(unitId));
        body.append("name", name);
        body.append("code", codeEl ? codeEl.value.trim() : "");
        body.append("public_blurb", missionEl ? missionEl.value.trim() : "");
        body.append("orbat_type", typeEl ? typeEl.value : "command");
        body.append("commander_user_id", cmdEl && cmdEl.value ? cmdEl.value : "");
        try {
            const res = await fetch(apiUnitUrl, { method: "POST", body: body, credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } });
            const j = await res.json().catch(function() { return {}; });
            if (!res.ok || !j.success) throw new Error(j.message || "Enregistrement impossible.");
            if (j.roster) applyRosterFromServer(j.roster);
            if (statusEl) { statusEl.textContent = "Enregistré"; statusEl.className = "min-h-[1.25rem] text-xs font-medium text-emerald-700"; }
            setTimeout(function() { if (statusEl && statusEl.textContent === "Enregistré") statusEl.textContent = ""; }, 2400);
        } catch (err) {
            if (statusEl) { statusEl.textContent = err.message || "Erreur"; statusEl.className = "min-h-[1.25rem] text-xs font-medium text-red-600"; }
        } finally {
            saveInFlight = false;
        }
    }

    function fillEditForm(node) {
        if (!showOrbatEditTools) return;
        const panel = document.getElementById("orbat-edit-panel");
        if (!panel) return;
        const uid = node.unitId || 0;
        if (uid < 1) {
            panel.classList.add("hidden");
            return;
        }
        panel.classList.remove("hidden");
        isHydratingForm = true;
        const name = document.getElementById("orbat-ed-name");
        const code = document.getElementById("orbat-ed-code");
        const typ = document.getElementById("orbat-ed-type");
        const mission = document.getElementById("orbat-ed-mission");
        const cmd = document.getElementById("orbat-ed-commander");
        if (name) name.value = node.label || "";
        if (code) code.value = (node.role && node.role !== "Unité") ? node.role : "";
        if (typ) typ.value = node.type || "command";
        if (mission) mission.value = (node.mission && node.mission !== "—") ? node.mission : "";
        if (cmd) cmd.value = (node.commanderUserId && node.commanderUserId > 0) ? String(node.commanderUserId) : "";
        isHydratingForm = false;
        bindEditors();
    }

    function countStats(node) {
        let units = 1, personnel = node.strength || 0, active = node.status === "active" ? 1 : 0, other = node.status !== "active" ? 1 : 0;
        for (const child of (node.children || [])) {
            const c = countStats(child);
            units += c.units; personnel += c.personnel; active += c.active; other += c.other;
        }
        return { units, personnel, active, other };
    }

    function flattenNodes(node, arr) {
        arr = arr || [];
        arr.push(node);
        (node.children || []).forEach(function(c) { flattenNodes(c, arr); });
        return arr;
    }

    function createNodeCard(node) {
        const wrapper = document.createElement("div");
        wrapper.className = "orbat-node-wrapper";
        const card = document.createElement("div");
        card.className = "orbat-node-card";
        card.dataset.nodeId = node.id;
        const top = document.createElement("div");
        top.className = "orbat-node-top";
        const insignia = document.createElement("div");
        insignia.className = "orbat-insignia orbat-type-" + (node.type || "command");
        insignia.textContent = (node.label || "").split(" ")[0].substring(0, 2).toUpperCase() || "—";
        const status = document.createElement("div");
        status.className = "orbat-status-dot orbat-status-" + (node.status || "active");
        top.appendChild(insignia);
        top.appendChild(status);
        const label = document.createElement("div");
        label.className = "orbat-node-label";
        label.textContent = node.label || "—";
        const sub = document.createElement("div");
        sub.className = "orbat-node-sub";
        sub.textContent = node.role || "—";
        const meta = document.createElement("div");
        meta.className = "orbat-node-meta";
        meta.innerHTML = "<span>" + getTypeLabel(node.type) + "</span><span>" + (node.strength || 0) + " pax</span>";
        card.appendChild(top);
        card.appendChild(label);
        card.appendChild(sub);
        card.appendChild(meta);
        card.addEventListener("click", function(e) { e.stopPropagation(); selectNode(node); });
        wrapper.appendChild(card);

        if ((node.children || []).length > 0) {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "orbat-collapse-btn";
            btn.textContent = collapsedState.get(node.id) ? "Déployer" : "Réduire";
            btn.addEventListener("click", function(e) {
                e.stopPropagation();
                collapsedState.set(node.id, !collapsedState.get(node.id));
                renderTree(filteredTree(currentSearch));
            });
            wrapper.appendChild(btn);
            const childrenContainer = document.createElement("div");
            childrenContainer.className = "orbat-children-container has-children" + (collapsedState.get(node.id) ? " orbat-hidden-children" : "");
            const row = document.createElement("div");
            row.className = "orbat-children-row";
            node.children.forEach(function(child) {
                const branch = document.createElement("div");
                branch.className = "orbat-child-branch";
                branch.appendChild(createNodeCard(child));
                row.appendChild(branch);
            });
            childrenContainer.appendChild(row);
            wrapper.appendChild(childrenContainer);
        }
        return wrapper;
    }

    function renderTree(data) {
        const treeContainer = document.getElementById("treeContainer");
        if (!treeContainer) return;
        treeContainer.innerHTML = "";
        if (!data) {
            treeContainer.innerHTML = "<div class=\"py-24 text-center\"><p class=\"text-sm font-bold uppercase tracking-[0.24em] text-slate-400\">Aucun résultat</p></div>";
            return;
        }
        const root = document.createElement("div");
        root.className = "orbat-tree-root";
        root.appendChild(createNodeCard(data));
        treeContainer.appendChild(root);
        const stats = countStats(data);
        var su = document.getElementById("stat-units");
        var sp = document.getElementById("stat-personnel");
        var sa = document.getElementById("stat-active");
        var so = document.getElementById("stat-other");
        if (su) su.textContent = stats.units;
        if (sp) sp.textContent = stats.personnel;
        if (sa) sa.textContent = stats.active;
        if (so) so.textContent = stats.other;
    }

    function selectNode(node) {
        currentSelectedUnitId = node.unitId || 0;
        var el;
        if (el = document.getElementById("detail-name")) el.textContent = node.label || "—";
        if (el = document.getElementById("detail-role")) el.textContent = node.role || "—";
        if (el = document.getElementById("detail-type")) el.textContent = getTypeLabel(node.type);
        if (el = document.getElementById("detail-status")) el.textContent = getStatusLabel(node.status || "active");
        if (el = document.getElementById("detail-strength")) el.textContent = (node.strength || 0) + " personnels";
        if (el = document.getElementById("detail-lead")) el.textContent = node.leader || "—";
        if (el = document.getElementById("detail-mission")) el.textContent = node.mission || "—";
        var membersBox = document.getElementById("detail-members");
        if (membersBox) {
            membersBox.innerHTML = "";
            var mems = node.members || [];
            var uu = node.unitId || 0;
            if (uu === 0) {
                membersBox.innerHTML = "<p class=\"text-xs text-slate-500\">Vue racine — sélectionnez une unité dans l’arbre.</p>";
            } else if (mems.length === 0) {
                membersBox.innerHTML = "<p class=\"text-xs text-slate-500\">Aucun membre actif rattaché à cette unité.</p>";
            } else {
                mems.forEach(function(mem) {
                    var row = document.createElement("a");
                    row.href = appBaseUrl + "/personnel/" + encodeURIComponent(String(mem.user_id));
                    row.className = "flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:border-emerald-300 hover:bg-emerald-50/50";
                    var sp = document.createElement("span");
                    sp.className = "truncate";
                    sp.textContent = mem.label || "";
                    var ar = document.createElement("span");
                    ar.className = "text-[10px] text-slate-400 shrink-0";
                    ar.textContent = "→";
                    row.appendChild(sp);
                    row.appendChild(ar);
                    membersBox.appendChild(row);
                });
            }
        }
        const childrenBox = document.getElementById("detail-children");
        if (childrenBox) {
            childrenBox.innerHTML = "";
            if (!node.children || node.children.length === 0) {
                childrenBox.innerHTML = "<p class=\"text-slate-500\">Aucune sous-unité.</p>";
            } else {
                node.children.forEach(function(child) {
                    const row = document.createElement("div");
                    row.className = "flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 cursor-pointer hover:bg-slate-50";
                    row.innerHTML = "<div><p class=\"font-black uppercase text-[11px] tracking-[0.14em]\">" + (child.label || "—") + "</p><p class=\"text-xs text-slate-500 font-medium\">" + (child.role || "—") + "</p></div><div class=\"text-right\"><p class=\"text-[10px] font-black uppercase\">" + (child.strength || 0) + " pax</p><p class=\"text-[10px] text-slate-500 font-bold uppercase\">" + getStatusLabel(child.status || "active") + "</p></div>";
                    row.addEventListener("click", function() { selectNode(child); });
                    childrenBox.appendChild(row);
                });
            }
        }
        fillEditForm(node);
    }

    function nodeMatches(node, term) {
        var s = [node.label, node.role, node.type, node.status, node.leader, node.mission].join(" ").toLowerCase();
        return s.indexOf(term) !== -1;
    }

    function filterNode(node, term) {
        var children = (node.children || []).map(function(c) { return filterNode(c, term); }).filter(Boolean);
        if (nodeMatches(node, term) || children.length > 0) {
            return {
                id: node.id, unitId: node.unitId, label: node.label, role: node.role, type: node.type, status: node.status,
                strength: node.strength, leader: node.leader, mission: node.mission, commanderUserId: node.commanderUserId || 0,
                members: node.members || [], children: children
            };
        }
        return null;
    }

    function filteredTree(term) {
        if (!term) return JSON.parse(JSON.stringify(rosterData));
        return filterNode(JSON.parse(JSON.stringify(rosterData)), term.toLowerCase());
    }

    var currentSearch = "";
    var searchInput = document.getElementById("searchInput");
    if (searchInput) searchInput.addEventListener("input", function(e) { currentSearch = e.target.value.trim(); renderTree(filteredTree(currentSearch)); });

    var expandAllBtn = document.getElementById("expandAllBtn");
    if (expandAllBtn) expandAllBtn.addEventListener("click", function() {
        flattenNodes(rosterData).forEach(function(n) { collapsedState.set(n.id, false); });
        renderTree(filteredTree(currentSearch));
    });

    var collapseAllBtn = document.getElementById("collapseAllBtn");
    if (collapseAllBtn) collapseAllBtn.addEventListener("click", function() {
        flattenNodes(rosterData).forEach(function(n) { if ((n.children || []).length > 0) collapsedState.set(n.id, true); });
        if (rosterData && rosterData.id) collapsedState.set(rosterData.id, false);
        renderTree(filteredTree(currentSearch));
    });

    flattenNodes(rosterData).forEach(function(n) { collapsedState.set(n.id, false); });
    renderTree(JSON.parse(JSON.stringify(rosterData)));
    selectNode(rosterData);

    if (showOrbatEditTools) {
        setInterval(function() {
            if (document.hidden || saveInFlight) return;
            var panel = document.getElementById("orbat-edit-panel");
            if (panel && document.activeElement && panel.contains(document.activeElement)) return;
            fetch(apiRosterUrl, { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
                .then(function(r) { return r.json(); })
                .then(function(j) {
                    if (!j || !j.success || !j.roster) return;
                    var snap = JSON.stringify(j.roster);
                    if (snap === lastRosterSnapshot) return;
                    lastRosterSnapshot = snap;
                    rosterData = j.roster;
                    renderTree(filteredTree(currentSearch));
                    var nn = findNodeByUnitId(rosterData, currentSelectedUnitId);
                    if (nn) selectNode(nn);
                })
                .catch(function() {});
        }, 32000);
    }
})();
</script>
<?php endif; ?>
