<?php
$unitsTree = $unitsTree ?? [];

/**
 * Normalise un nœud unité (DB) vers le format attendu par le JS orbat (label, role, type, status, strength, leader, mission, children).
 */
function normalizeOrbatNode(array $u): array {
    $type = isset($u['type']) && in_array((string)$u['type'], ['command', 'alpha', 'bravo', 'support', 'special'], true)
        ? (string) $u['type'] : 'command';
    $children = [];
    foreach ($u['children'] ?? [] as $c) {
        $children[] = normalizeOrbatNode($c);
    }
    return [
        'id' => 'unit-' . (int) $u['id'],
        'label' => $u['name'] ?? 'Unité',
        'role' => !empty($u['code']) ? (string) $u['code'] : 'Unité',
        'type' => $type,
        'status' => 'active',
        'strength' => 0,
        'leader' => '—',
        'mission' => '—',
        'children' => $children,
    ];
}

$rosterData = null;
if (!empty($unitsTree)) {
    if (count($unitsTree) === 1) {
        $rosterData = normalizeOrbatNode($unitsTree[0]);
    } else {
        $children = [];
        foreach ($unitsTree as $root) {
            $children[] = normalizeOrbatNode($root);
        }
        $rosterData = [
            'id' => 'command',
            'label' => 'Command',
            'role' => 'Structure organique',
            'type' => 'command',
            'status' => 'active',
            'strength' => 0,
            'leader' => '—',
            'mission' => 'Direction des unités et coordination.',
            'children' => $children,
        ];
    }
}
$rosterJson = $rosterData !== null ? json_encode($rosterData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : 'null';
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
                </div>
            </aside>
        </div>
    </main>
</div>

<?php if ($rosterData !== null): ?>
<script>
(function() {
    const rosterData = <?= $rosterJson ?>;

    const collapsedState = new Map();

    function getStatusLabel(status) {
        if (status === "active") return "Active";
        if (status === "partial") return "Partielle";
        return "Inactive";
    }

    function getTypeLabel(type) {
        const labels = { command: "Command", alpha: "Alpha", bravo: "Bravo", support: "Support", special: "Special" };
        return labels[type] || (type || "—");
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
        var el;
        if (el = document.getElementById("detail-name")) el.textContent = node.label || "—";
        if (el = document.getElementById("detail-role")) el.textContent = node.role || "—";
        if (el = document.getElementById("detail-type")) el.textContent = getTypeLabel(node.type);
        if (el = document.getElementById("detail-status")) el.textContent = getStatusLabel(node.status || "active");
        if (el = document.getElementById("detail-strength")) el.textContent = (node.strength || 0) + " personnels";
        if (el = document.getElementById("detail-lead")) el.textContent = node.leader || "—";
        if (el = document.getElementById("detail-mission")) el.textContent = node.mission || "—";
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
    }

    function nodeMatches(node, term) {
        var s = [node.label, node.role, node.type, node.status, node.leader, node.mission].join(" ").toLowerCase();
        return s.indexOf(term) !== -1;
    }

    function filterNode(node, term) {
        var children = (node.children || []).map(function(c) { return filterNode(c, term); }).filter(Boolean);
        if (nodeMatches(node, term) || children.length > 0) return { id: node.id, label: node.label, role: node.role, type: node.type, status: node.status, strength: node.strength, leader: node.leader, mission: node.mission, children: children };
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
})();
</script>
<?php endif; ?>
