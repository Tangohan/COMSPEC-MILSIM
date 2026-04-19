<?php
declare(strict_types=1);

$rosterData = $orbatRosterData ?? null;
$showOrbatEditTools = (bool) ($orbatCanManage ?? false);
$orbatCommanderOptions = $orbatCommanderOptions ?? [];
$orbatCsrfToken = $orbatCsrfToken ?? '';
$baseUrl = rtrim(url(''), '/');
$rosterJson = $rosterData !== null ? json_encode($rosterData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) : 'null';
$orbatCommanderJson = json_encode($orbatCommanderOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$orbatRecruitmentHub = !empty($orbatRecruitmentHub);
$orbatEmptyStateBackUrl = isset($orbatEmptyStateBackUrl) && is_string($orbatEmptyStateBackUrl) && $orbatEmptyStateBackUrl !== ''
    ? $orbatEmptyStateBackUrl
    : url('dashboard');
$orbatPageEyebrow = $orbatPageEyebrow ?? 'Command Structure';
$orbatPageTitle = $orbatPageTitle ?? 'ORBAT';
$orbatPageLead = $orbatPageLead ?? 'Structure organique, disponibilité des unités, consultation dynamique.';
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
    .orbat-placeholder-card {
        cursor: default;
        border-style: dashed;
        border-color: rgba(100, 116, 139, 0.45);
        background: rgba(248, 250, 252, 0.95);
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
    }
    .orbat-placeholder-card:hover {
        transform: none;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        border-color: rgba(100, 116, 139, 0.55);
    }
    .orbat-placeholder-card .orbat-insignia {
        background: linear-gradient(180deg, #e2e8f0, #cbd5e1);
        font-size: 14px;
        line-height: 1;
    }
    .orbat-placeholder-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-top: 8px;
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #475569;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        padding: 4px 8px;
    }
    .orbat-collapsed-hint {
        margin-top: 6px;
        font-size: 9px;
        font-weight: 700;
        color: #64748b;
        text-align: center;
        max-width: 200px;
        line-height: 1.35;
        letter-spacing: 0.02em;
    }
    .orbat-panel {
        background: rgba(255,255,255,0.88); border: 1px solid rgba(15,23,42,0.08);
        box-shadow: 0 16px 36px rgba(15,23,42,0.06); backdrop-filter: blur(10px);
    }
    .orbat-legend-dot { width: 11px; height: 11px; border-radius: 999px; display: inline-block; }
    @media (max-width: 900px) { .orbat-node-card { min-width: 124px; } }
    .orbat-mask-badge {
        position: absolute; top: 6px; right: 6px; font-size: 8px; font-weight: 900; text-transform: uppercase;
        letter-spacing: 0.06em; color: #7c2d12; background: #ffedd5; border: 1px solid #fdba74; border-radius: 6px; padding: 2px 5px;
        pointer-events: none;
    }
    #orbat-ctx-menu {
        position: fixed; z-index: 80; min-width: 220px; display: none;
        background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 20px 50px rgba(15,23,42,0.15);
        padding: 6px 0; font-size: 13px; font-weight: 600; color: #0f172a;
    }
    #orbat-ctx-menu button {
        display: block; width: 100%; text-align: left; padding: 10px 16px; background: transparent; border: 0; cursor: pointer;
        font: inherit; color: inherit;
    }
    #orbat-ctx-menu button:hover:not(:disabled) { background: #f1f5f9; }
    #orbat-ctx-menu button:disabled { opacity: 0.45; cursor: not-allowed; }
    .orbat-modal-overlay {
        position: fixed; inset: 0; z-index: 90; display: none; align-items: center; justify-content: center;
        background: rgba(15,23,42,0.45); padding: 16px;
    }
    .orbat-modal-overlay[aria-hidden="false"] { display: flex; }
    .orbat-modal {
        width: 100%; max-width: 420px; background: #fff; border-radius: 1.25rem; padding: 1.25rem 1.35rem;
        box-shadow: 0 24px 60px rgba(15,23,42,0.2); border: 1px solid #e2e8f0;
    }
    .orbat-modal h3 { font-size: 1rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.06em; color: #0f172a; margin-bottom: 0.5rem; }
    .orbat-modal p.hint { font-size: 0.8rem; color: #64748b; margin-bottom: 1rem; line-height: 1.45; }
    .orbat-modal label { display: block; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; margin-bottom: 0.35rem; }
    .orbat-modal input[type="text"], .orbat-modal select {
        width: 100%; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.5rem 0.65rem; font-size: 0.9rem; margin-bottom: 0.75rem;
    }
    .orbat-modal-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: flex-end; margin-top: 0.75rem; }
    .orbat-modal-actions button {
        border-radius: 0.75rem; padding: 0.45rem 1rem; font-size: 0.75rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.08em; cursor: pointer;
    }
    .orbat-btn-secondary { background: #f1f5f9; border: 1px solid #e2e8f0; color: #0f172a; }
    .orbat-btn-primary { background: #0f172a; border: 1px solid #0f172a; color: #fff; }
    .orbat-btn-danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
</style>

<div class="orbat-page relative min-h-screen overflow-hidden">
    <div class="orbat-watermark" aria-hidden="true">Athena</div>

    <header class="relative z-10 border-b border-slate-200/80 bg-white/75 backdrop-blur-xl">
        <div class="max-w-[1800px] mx-auto px-6 py-5 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <p class="text-[10px] font-black tracking-[0.32em] uppercase text-slate-400 mb-2"><?= htmlspecialchars((string) $orbatPageEyebrow, ENT_QUOTES, 'UTF-8') ?></p>
                <h1 class="text-3xl font-black tracking-tight uppercase leading-none"><?= htmlspecialchars((string) $orbatPageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-sm text-slate-500 mt-3 font-medium"><?= htmlspecialchars((string) $orbatPageLead, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($showOrbatEditTools): ?>
                <p class="mt-2 max-w-2xl text-xs font-semibold leading-relaxed text-emerald-900">Vous gérez cette communauté : clic droit ou bouton « ⋯ » sur une carte pour créer, rattacher, régler la confidentialité ou supprimer une unité. Les champs à droite servent à la fiche détaillée ; l’enregistrement est automatique après une courte pause, et l’organigramme se met à jour sans recharger la page.</p>
                <?php endif; ?>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 min-w-[320px]">
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
                <div class="orbat-panel rounded-2xl p-4">
                    <p class="text-[9px] font-black tracking-[0.22em] uppercase text-slate-400">Readiness ORBAT</p>
                    <p id="stat-readiness" class="text-2xl font-black tracking-tight mt-1">—</p>
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
                <span class="flex items-center gap-2"><span class="orbat-legend-dot border-2 border-dashed border-slate-400 bg-slate-100"></span> Non affichée (périmètre)</span>
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
                    <p class="mt-6"><a href="<?= htmlspecialchars($orbatEmptyStateBackUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-600 hover:underline font-semibold">Retour</a></p>
                </div>
                <?php endif; ?>
            </section>

            <aside class="orbat-panel rounded-[2rem] p-6">
                <p class="text-[10px] font-black tracking-[0.28em] uppercase text-slate-400 mb-3">Fiche Unité</p>
                <div id="detailsPanel" class="space-y-5">
                    <div id="detail-redacted-banner" class="hidden rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold leading-snug text-amber-950">
                        Les noms affichés sur cette unité sont abrégés : votre périmètre ne permet pas d’afficher l’identité complète des personnes listées.
                    </div>
                    <div id="detail-placeholder-banner" class="hidden rounded-2xl border border-slate-300 border-dashed bg-slate-50 px-4 py-3 text-xs font-semibold leading-snug text-slate-800">
                        Emplacement masqué dans l’organigramme : la structure existe côté organisation, mais n’est pas visible avec votre profil actuel.
                    </div>
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
                        <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                            <p class="text-[9px] font-black tracking-[0.18em] uppercase text-slate-400">Readiness</p>
                            <p id="detail-readiness" class="text-sm font-black uppercase mt-2">—</p>
                        </div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                        <p class="text-[9px] font-black tracking-[0.18em] uppercase text-slate-400">Mission</p>
                        <p id="detail-mission" class="text-sm text-slate-700 font-medium mt-2 leading-relaxed">—</p>
                    </div>
                    <div id="detail-orbat-extras-wrap" class="hidden rounded-2xl bg-slate-50 border border-slate-100 p-4">
                        <p class="text-[9px] font-black tracking-[0.18em] uppercase text-slate-400">Détails complémentaires</p>
                        <p id="detail-orbat-details" class="text-sm text-slate-700 font-medium mt-2 leading-relaxed whitespace-pre-wrap">—</p>
                        <div id="detail-chart-visuals" class="mt-3 flex flex-wrap gap-3 items-center"></div>
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
                            <select id="orbat-ed-type" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"></select>
                            <button type="button" id="orbat-btn-new-chart-type" class="mt-2 w-full rounded-xl border border-emerald-300 bg-white py-2 text-[10px] font-black uppercase tracking-wider text-emerald-900 hover:bg-emerald-50">Nouveau type sur l’organigramme…</button>
                        </div>
                        <div>
                            <label for="orbat-ed-mission" class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Mission ou description courte</label>
                            <textarea id="orbat-ed-mission" rows="3" maxlength="8000" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"></textarea>
                        </div>
                        <div id="orbat-ed-details-block">
                            <label for="orbat-ed-details" class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Détails complémentaires</label>
                            <p class="mb-2 text-[10px] text-slate-500 leading-snug">Informations de contexte affichées sur la fiche (repères, organisation interne, notes de pilotage).</p>
                            <textarea id="orbat-ed-details" rows="4" maxlength="16000" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"></textarea>
                        </div>
                        <div id="orbat-chart-media-block" class="rounded-xl border border-slate-200 bg-white p-3 space-y-3">
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Visuels sur la carte</p>
                            <p class="text-[10px] text-slate-500 leading-snug">Icône (PNG, ICO, JPG) et image d’illustration (PNG, JPG), jusqu’à 2,5&nbsp;Mo.</p>
                            <div class="flex flex-wrap gap-3 items-end">
                                <div class="flex-1 min-w-[140px]">
                                    <label class="mb-1 block text-[9px] font-black uppercase text-slate-400">Icône</label>
                                    <input type="file" id="orbat-upload-icon" accept=".png,.ico,.jpg,.jpeg,image/png,image/x-icon,image/vnd.microsoft.icon,image/jpeg" class="block w-full text-xs text-slate-600">
                                    <button type="button" id="orbat-clear-icon" class="mt-1 text-[10px] font-bold text-rose-700 hover:underline">Retirer l’icône</button>
                                </div>
                                <div class="flex-1 min-w-[140px]">
                                    <label class="mb-1 block text-[9px] font-black uppercase text-slate-400">Image</label>
                                    <input type="file" id="orbat-upload-image" accept=".png,.jpg,.jpeg,image/png,image/jpeg" class="block w-full text-xs text-slate-600">
                                    <button type="button" id="orbat-clear-image" class="mt-1 text-[10px] font-bold text-rose-700 hover:underline">Retirer l’image</button>
                                </div>
                            </div>
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

<?php if ($rosterData !== null && ($showOrbatEditTools || $orbatRecruitmentHub)): ?>
<div id="orbat-ctx-menu" role="menu" aria-hidden="true">
    <?php if ($showOrbatEditTools): ?>
    <button type="button" role="menuitem" data-ctx="edit">Modifier la fiche</button>
    <button type="button" role="menuitem" data-ctx="create">Créer une sous-unité…</button>
    <button type="button" role="menuitem" data-ctx="move">Rattacher à une autre unité…</button>
    <button type="button" role="menuitem" data-ctx="detach">Détacher (niveau racine)</button>
    <button type="button" role="menuitem" data-ctx="mask">Confidentialité sur l’organigramme…</button>
    <button type="button" role="menuitem" data-ctx="delete">Supprimer cette unité…</button>
    <?php endif; ?>
    <?php if ($orbatRecruitmentHub): ?>
    <button type="button" role="menuitem" data-ctx="hub-new-group">Nouveau regroupement rattaché ici…</button>
    <button type="button" role="menuitem" data-ctx="hub-new-team">Nouvelle équipe rattachée ici…</button>
    <?php endif; ?>
</div>
<div id="orbat-modal-create" class="orbat-modal-overlay" aria-hidden="true">
    <div class="orbat-modal" role="dialog" aria-modal="true" aria-labelledby="orbat-m-create-title">
        <h3 id="orbat-m-create-title">Nouvelle sous-unité</h3>
        <p class="hint">L’unité sera créée sous l’élément sélectionné dans l’organigramme.</p>
        <label for="orbat-create-name">Nom affiché</label>
        <input id="orbat-create-name" type="text" maxlength="255" autocomplete="off">
        <label for="orbat-create-type">Type d’unité</label>
        <select id="orbat-create-type"></select>
        <div class="orbat-modal-actions">
            <button type="button" class="orbat-btn-secondary" data-close-modal="create">Annuler</button>
            <button type="button" class="orbat-btn-primary" id="orbat-create-submit">Créer</button>
        </div>
    </div>
</div>
<div id="orbat-modal-move" class="orbat-modal-overlay" aria-hidden="true">
    <div class="orbat-modal" role="dialog" aria-modal="true" aria-labelledby="orbat-m-move-title">
        <h3 id="orbat-m-move-title">Rattacher l’unité</h3>
        <p class="hint">Choisissez l’unité parente. Les boucles dans la hiérarchie sont refusées automatiquement.</p>
        <label for="orbat-move-parent">Rattacher sous</label>
        <select id="orbat-move-parent"></select>
        <div class="orbat-modal-actions">
            <button type="button" class="orbat-btn-secondary" data-close-modal="move">Annuler</button>
            <button type="button" class="orbat-btn-primary" id="orbat-move-submit">Enregistrer</button>
        </div>
    </div>
</div>
<div id="orbat-modal-mask" class="orbat-modal-overlay" aria-hidden="true">
    <div class="orbat-modal" role="dialog" aria-modal="true" aria-labelledby="orbat-m-mask-title">
        <h3 id="orbat-m-mask-title">Confidentialité sur l’organigramme</h3>
        <p class="hint">Ces réglages s’appliquent aux personnes qui consultent l’ORBAT selon leurs affectations. Les gestionnaires voient toujours la structure complète pour le pilotage.</p>
        <label for="orbat-mask-select">Mode</label>
        <select id="orbat-mask-select"></select>
        <div class="orbat-modal-actions">
            <button type="button" class="orbat-btn-secondary" data-close-modal="mask">Annuler</button>
            <button type="button" class="orbat-btn-primary" id="orbat-mask-submit">Enregistrer</button>
        </div>
    </div>
</div>
<div id="orbat-modal-delete" class="orbat-modal-overlay" aria-hidden="true">
    <div class="orbat-modal" role="dialog" aria-modal="true" aria-labelledby="orbat-m-del-title">
        <h3 id="orbat-m-del-title">Supprimer l’unité</h3>
        <p class="hint" id="orbat-delete-hint">Cette action est définitive. Les sous-unités doivent être déplacées ou supprimées avant.</p>
        <div class="orbat-modal-actions">
            <button type="button" class="orbat-btn-secondary" data-close-modal="delete">Annuler</button>
            <button type="button" class="orbat-btn-danger" id="orbat-delete-submit">Supprimer</button>
        </div>
    </div>
</div>
<div id="orbat-modal-chart-type" class="orbat-modal-overlay" aria-hidden="true">
    <div class="orbat-modal" role="dialog" aria-modal="true" aria-labelledby="orbat-m-ct-title">
        <h3 id="orbat-m-ct-title">Nouveau type sur l’organigramme</h3>
        <p class="hint">Donnez un nom lisible (ex. « Cellule renseignement »). Une référence courte sera créée automatiquement pour le style des cartes.</p>
        <label for="orbat-chart-type-label">Nom du type</label>
        <input id="orbat-chart-type-label" type="text" maxlength="120" autocomplete="off">
        <div class="orbat-modal-actions">
            <button type="button" class="orbat-btn-secondary" data-close-modal="chart-type">Annuler</button>
            <button type="button" class="orbat-btn-primary" id="orbat-chart-type-submit">Créer</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($rosterData !== null): ?>
<script>
(function() {
    const appBaseUrl = <?= json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    let rosterData = <?= $rosterJson ?>;
    const showOrbatEditTools = <?= $showOrbatEditTools ? 'true' : 'false' ?>;
    const orbatRecruitmentHub = <?= $orbatRecruitmentHub ? 'true' : 'false' ?>;
    const orbatCsrf = <?= json_encode($orbatCsrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    const apiUnitUrl = appBaseUrl + "/api/orbat/unit";
    const apiRosterUrl = appBaseUrl + "/api/orbat/roster";
    const apiStructureUrl = appBaseUrl + "/api/orbat/structure";
    const apiStructureOptionsUrl = appBaseUrl + "/api/orbat/structure-options";
    const apiChartTypeUrl = appBaseUrl + "/api/orbat/chart-type";
    const apiUnitUploadUrl = appBaseUrl + "/api/orbat/unit-upload";

    const collapsedState = new Map();
    let lastRosterSnapshot = JSON.stringify(rosterData);
    let currentSelectedUnitId = 0;
    let saveTimer = null;
    let editorsBound = false;
    let saveInFlight = false;
    let isHydratingForm = false;

    var structureOptionsCache = null;
    var ctxTargetNode = null;

    function getStatusLabel(status) {
        if (status === "active") return "Active";
        if (status === "partial") return "Partielle";
        return "Inactive";
    }

    function getTypeLabel(type) {
        const labels = { command: "Commandement", alpha: "Alpha", bravo: "Bravo", support: "Soutien", special: "Spécial" };
        return labels[type] || (type || "—");
    }

    function getChartDisplayLabel(slug) {
        if (!slug) return "—";
        if (structureOptionsCache && structureOptionsCache.chartDisplayTypes) {
            var list = structureOptionsCache.chartDisplayTypes;
            for (var i = 0; i < list.length; i++) {
                if (list[i].id === slug) return list[i].label;
            }
        }
        return getTypeLabel(slug);
    }

    function mediaSrc(path) {
        if (!path) return "";
        if (path.indexOf("/") === 0) return appBaseUrl + path;
        return path;
    }

    function refreshOrbatTypeSelect() {
        var sel = document.getElementById("orbat-ed-type");
        if (!sel) return;
        var prev = sel.value;
        sel.innerHTML = "";
        var list = (structureOptionsCache && structureOptionsCache.chartDisplayTypes) ? structureOptionsCache.chartDisplayTypes : [];
        if (!list.length) {
            ["command", "alpha", "bravo", "support", "special"].forEach(function(id) {
                var o = document.createElement("option");
                o.value = id;
                o.textContent = getTypeLabel(id);
                sel.appendChild(o);
            });
        } else {
            list.forEach(function(t) {
                var o = document.createElement("option");
                o.value = t.id;
                var lab = t.label || t.id;
                if (t.builtin === false) lab = lab + " (personnalisé)";
                o.textContent = lab;
                sel.appendChild(o);
            });
        }
        if (prev) {
            for (var j = 0; j < sel.options.length; j++) {
                if (sel.options[j].value === prev) { sel.selectedIndex = j; break; }
            }
        }
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
        ["orbat-ed-name", "orbat-ed-code", "orbat-ed-type", "orbat-ed-mission", "orbat-ed-details", "orbat-ed-commander"].forEach(function(id) {
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
        const detailsEl = document.getElementById("orbat-ed-details");
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
        body.append("orbat_details", detailsEl ? detailsEl.value.trim() : "");
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
        const details = document.getElementById("orbat-ed-details");
        const cmd = document.getElementById("orbat-ed-commander");
        refreshOrbatTypeSelect();
        if (name) name.value = node.label || "";
        if (code) code.value = (node.role && node.role !== "Unité") ? node.role : "";
        if (typ && node.type) {
            for (var ti = 0; ti < typ.options.length; ti++) {
                if (typ.options[ti].value === node.type) { typ.selectedIndex = ti; break; }
            }
        }
        if (mission) mission.value = (node.mission && node.mission !== "—") ? node.mission : "";
        if (details) details.value = node.orbatDetails || "";
        if (cmd) cmd.value = (node.commanderUserId && node.commanderUserId > 0) ? String(node.commanderUserId) : "";
        isHydratingForm = false;
        bindEditors();
    }

    function countStats(node) {
        if (node.isOrbatPlaceholder) {
            return { units: 0, personnel: 0, active: 0, other: 0, readinessSum: 0, readinessCount: 0 };
        }
        let units = 1, personnel = node.strength || 0, active = node.status === "active" ? 1 : 0, other = node.status !== "active" ? 1 : 0;
        let readinessSum = 0, readinessCount = 0;
        if (typeof node.readinessScore === "number" && (node.readinessPopulation || 0) > 0) {
            readinessSum += node.readinessScore * (node.readinessPopulation || 0);
            readinessCount += (node.readinessPopulation || 0);
        }
        for (const child of (node.children || [])) {
            const c = countStats(child);
            units += c.units; personnel += c.personnel; active += c.active; other += c.other;
            readinessSum += c.readinessSum || 0;
            readinessCount += c.readinessCount || 0;
        }
        return { units, personnel, active, other, readinessSum, readinessCount };
    }

    function flattenNodes(node, arr) {
        arr = arr || [];
        arr.push(node);
        (node.children || []).forEach(function(c) { flattenNodes(c, arr); });
        return arr;
    }

    function applyOrbatCapabilities(j) {
        if (!j || !j.capabilities) return;
        var c = j.capabilities;
        var maskBtn = document.querySelector('#orbat-ctx-menu [data-ctx="mask"]');
        if (maskBtn) maskBtn.hidden = !c.mask_editing;
        var btnCt = document.getElementById("orbat-btn-new-chart-type");
        if (btnCt) btnCt.hidden = !c.custom_chart_types;
        var mediaBlock = document.getElementById("orbat-chart-media-block");
        if (mediaBlock) mediaBlock.hidden = !c.chart_media_upload;
        var detailsBlock = document.getElementById("orbat-ed-details-block");
        if (detailsBlock) detailsBlock.hidden = !c.details_field;
    }

    async function ensureStructureOptions() {
        if (structureOptionsCache || !showOrbatEditTools) return structureOptionsCache;
        try {
            var res = await fetch(apiStructureOptionsUrl, { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } });
            var j = await res.json().catch(function() { return null; });
            if (res.ok && j && j.success) {
                structureOptionsCache = j;
                applyOrbatCapabilities(j);
            }
        } catch (e) {}
        return structureOptionsCache;
    }

    async function postStructure(action, fields) {
        var body = new FormData();
        body.append("_csrf_token", orbatCsrf);
        body.append("action", action);
        Object.keys(fields || {}).forEach(function(k) {
            var v = fields[k];
            if (v === undefined || v === null) return;
            body.append(k, String(v));
        });
        var res = await fetch(apiStructureUrl, { method: "POST", body: body, credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } });
        var j = await res.json().catch(function() { return {}; });
        if (!res.ok || !j.success) {
            throw new Error(j.message || "Action impossible pour le moment.");
        }
        if (j.roster) applyRosterFromServer(j.roster);
    }

    function collectDescendantIds(flatUnits, rootId) {
        var childrenOf = {};
        flatUnits.forEach(function(u) {
            var p = u.parent_id === null || u.parent_id === undefined || u.parent_id === "" ? 0 : u.parent_id;
            if (!childrenOf[p]) childrenOf[p] = [];
            childrenOf[p].push(u.id);
        });
        var out = [];
        var stack = (childrenOf[rootId] || []).slice();
        while (stack.length) {
            var id = stack.pop();
            out.push(id);
            (childrenOf[id] || []).forEach(function(c) { stack.push(c); });
        }
        return out;
    }

    function openModal(id) {
        var el = document.getElementById("orbat-modal-" + id);
        if (!el) return;
        el.setAttribute("aria-hidden", "false");
    }

    function closeModal(id) {
        var el = document.getElementById("orbat-modal-" + id);
        if (!el) return;
        el.setAttribute("aria-hidden", "true");
    }

    function closeCtxMenu() {
        var m = document.getElementById("orbat-ctx-menu");
        if (m) { m.style.display = "none"; m.setAttribute("aria-hidden", "true"); }
        ctxTargetNode = null;
    }

    function openOrbatContextMenu(px, py, node) {
        ctxTargetNode = node;
        var m = document.getElementById("orbat-ctx-menu");
        if (!m) return;
        var uid = node.unitId || 0;
        m.querySelectorAll("button[data-ctx]").forEach(function(btn) {
            var k = btn.getAttribute("data-ctx");
            var dis = uid < 1 && k !== "edit";
            if (orbatRecruitmentHub && (k === "hub-new-group" || k === "hub-new-team")) {
                dis = uid < 1;
                if (k === "hub-new-group") {
                    dis = dis || String(node.structType || "") !== "group";
                }
                if (k === "hub-new-team") {
                    dis = dis || String(node.structType || "") !== "team";
                }
            }
            btn.disabled = !!dis;
        });
        m.style.display = "block";
        m.setAttribute("aria-hidden", "false");
        var vw = window.innerWidth, vh = window.innerHeight;
        var mw = m.offsetWidth, mh = m.offsetHeight;
        var x = Math.max(8, Math.min(px, vw - mw - 8));
        var y = Math.max(8, Math.min(py, vh - mh - 8));
        m.style.left = x + "px";
        m.style.top = y + "px";
    }

    function createNodeCard(node) {
        const isPh = !!node.isOrbatPlaceholder;
        const wrapper = document.createElement("div");
        wrapper.className = "orbat-node-wrapper";
        const card = document.createElement("div");
        card.className = "orbat-node-card" + (isPh ? " orbat-placeholder-card" : "");
        card.dataset.nodeId = node.id;
        card.style.position = "relative";
        const top = document.createElement("div");
        top.className = "orbat-node-top";
        const insignia = document.createElement("div");
        insignia.className = "orbat-insignia orbat-type-" + (isPh ? "command" : (node.type || "command"));
        insignia.textContent = isPh ? "\u25C6" : ((node.label || "").split(" ")[0].substring(0, 2).toUpperCase() || "\u2014");
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
        var readTxt = (typeof node.readinessScore === "number") ? (node.readinessScore + "% ready") : "n/d";
        meta.innerHTML = "<span>" + getChartDisplayLabel(node.type) + "</span><span>" + (node.strength || 0) + " pax · " + readTxt + "</span>";
        card.appendChild(top);
        card.appendChild(label);
        card.appendChild(sub);
        card.appendChild(meta);
        if (!isPh && (node.chartImageUrl || node.chartIconUrl)) {
            var vis = document.createElement("div");
            vis.className = "mt-2 flex items-center justify-center gap-2";
            if (node.chartImageUrl) {
                var im = document.createElement("img");
                im.src = mediaSrc(node.chartImageUrl);
                im.alt = "";
                im.className = "max-h-12 w-auto rounded-lg border border-slate-200 object-cover";
                vis.appendChild(im);
            } else if (node.chartIconUrl) {
                var ic = document.createElement("img");
                ic.src = mediaSrc(node.chartIconUrl);
                ic.alt = "";
                ic.className = "h-8 w-8 object-contain";
                vis.appendChild(ic);
            }
            card.appendChild(vis);
        }
        if (isPh) {
            var phLab = document.createElement("div");
            phLab.className = "orbat-placeholder-badge";
            phLab.textContent = "Hors périmètre";
            card.appendChild(phLab);
        }
        if (node.staffMaskActive) {
            var badge = document.createElement("span");
            badge.className = "orbat-mask-badge";
            badge.textContent = (node.maskHintLabel && String(node.maskHintLabel).trim()) ? String(node.maskHintLabel).trim() : "Confidentialité";
            badge.title = "Réglage de confidentialité sur l’organigramme pour les personnes qui consultent sans habilitation complète.";
            card.appendChild(badge);
        }
        card.addEventListener("click", function(e) { e.stopPropagation(); selectNode(node); });
        if ((showOrbatEditTools || orbatRecruitmentHub) && !isPh) {
            card.setAttribute("title", showOrbatEditTools ? "Clic droit ou bouton ⋯ pour les actions de structure" : "Clic droit ou bouton ⋯ pour créer un regroupement ou une équipe");
            var actBtn = document.createElement("button");
            actBtn.type = "button";
            actBtn.className = "absolute left-1 top-1 z-[3] h-6 w-6 rounded-lg border border-slate-200 bg-white/90 text-slate-600 text-sm font-black leading-none hover:bg-slate-50";
            actBtn.setAttribute("aria-label", "Actions sur cette unité");
            actBtn.textContent = "⋯";
            actBtn.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                var r = card.getBoundingClientRect();
                openOrbatContextMenu(r.left + 4, r.bottom + 6, node);
            });
            card.appendChild(actBtn);
            card.addEventListener("contextmenu", function(e) {
                e.preventDefault();
                e.stopPropagation();
                openOrbatContextMenu(e.clientX, e.clientY, node);
            });
        }
        wrapper.appendChild(card);

        if ((node.children || []).length > 0) {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "orbat-collapse-btn";
            var nKids = (node.children || []).length;
            btn.textContent = collapsedState.get(node.id) ? ("Déployer (" + nKids + ")") : "Réduire";
            btn.title = collapsedState.get(node.id) ? ("Afficher les " + nKids + " sous-unité(s)") : "Masquer les sous-unités";
            btn.addEventListener("click", function(e) {
                e.stopPropagation();
                collapsedState.set(node.id, !collapsedState.get(node.id));
                renderTree(filteredTree(currentSearch));
            });
            wrapper.appendChild(btn);
            if (collapsedState.get(node.id) && nKids > 0) {
                var cHint = document.createElement("div");
                cHint.className = "orbat-collapsed-hint";
                cHint.textContent = nKids + " sous-unité" + (nKids > 1 ? "s" : "") + " repliée" + (nKids > 1 ? "s" : "") + " — utiliser « Déployer »";
                wrapper.appendChild(cHint);
            }
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
        var sr = document.getElementById("stat-readiness");
        if (su) su.textContent = stats.units;
        if (sp) sp.textContent = stats.personnel;
        if (sa) sa.textContent = stats.active;
        if (so) so.textContent = stats.other;
        if (sr) sr.textContent = stats.readinessCount > 0 ? Math.round(stats.readinessSum / stats.readinessCount) + "%" : "—";
    }

    function selectNode(node) {
        currentSelectedUnitId = node.unitId || 0;
        var el;
        var banRed = document.getElementById("detail-redacted-banner");
        if (banRed) {
            if (node.viewerNamesRedacted) banRed.classList.remove("hidden");
            else banRed.classList.add("hidden");
        }
        var banPh = document.getElementById("detail-placeholder-banner");
        if (banPh) {
            if (node.isOrbatPlaceholder) banPh.classList.remove("hidden");
            else banPh.classList.add("hidden");
        }
        if (el = document.getElementById("detail-name")) el.textContent = node.label || "—";
        if (el = document.getElementById("detail-role")) el.textContent = node.role || "—";
        if (el = document.getElementById("detail-type")) el.textContent = getChartDisplayLabel(node.type);
        if (el = document.getElementById("detail-status")) el.textContent = getStatusLabel(node.status || "active");
        if (el = document.getElementById("detail-strength")) el.textContent = (node.strength || 0) + " personnels";
        if (el = document.getElementById("detail-lead")) el.textContent = node.leader || "—";
        if (el = document.getElementById("detail-readiness")) el.textContent = typeof node.readinessScore === "number"
            ? (node.readinessScore + "% (unité + sous-unités)")
            : "Non calculé";
        if (el = document.getElementById("detail-mission")) el.textContent = node.mission || "—";
        var exWrap = document.getElementById("detail-orbat-extras-wrap");
        var exDet = document.getElementById("detail-orbat-details");
        var exVis = document.getElementById("detail-chart-visuals");
        var dtxt = (node.orbatDetails || "").trim();
        var hasVis = !!(node.chartIconUrl || node.chartImageUrl);
        if (exWrap) {
            var showExtras = node.isOrbatPlaceholder
                ? (dtxt !== "")
                : ((dtxt !== "" || hasVis) && (node.unitId || 0) > 0);
            if (showExtras) {
                exWrap.classList.remove("hidden");
                if (exDet) exDet.textContent = dtxt !== "" ? dtxt : "—";
                if (exVis) {
                    exVis.innerHTML = "";
                    if (!node.isOrbatPlaceholder && node.chartImageUrl) {
                        var im = document.createElement("img");
                        im.src = mediaSrc(node.chartImageUrl);
                        im.alt = "";
                        im.className = "max-h-24 rounded-xl border border-slate-200 object-cover";
                        exVis.appendChild(im);
                    } else if (!node.isOrbatPlaceholder && node.chartIconUrl) {
                        var ic = document.createElement("img");
                        ic.src = mediaSrc(node.chartIconUrl);
                        ic.alt = "";
                        ic.className = "h-14 w-14 object-contain";
                        exVis.appendChild(ic);
                    }
                }
            } else {
                exWrap.classList.add("hidden");
            }
        }
        var membersBox = document.getElementById("detail-members");
        if (membersBox) {
            membersBox.innerHTML = "";
            var mems = node.members || [];
            var uu = node.unitId || 0;
            if (uu === 0) {
                membersBox.innerHTML = "<p class=\"text-xs text-slate-500\">Vue racine — sélectionnez une unité dans l’arbre.</p>";
            } else if (node.isOrbatPlaceholder) {
                membersBox.innerHTML = "<p class=\"text-xs text-slate-500\">Aucune liste de membres n’est affichée pour cet emplacement.</p>";
            } else if (mems.length === 0) {
                membersBox.innerHTML = "<p class=\"text-xs text-slate-500\">Aucun membre actif rattaché à cette unité.</p>";
            } else {
                mems.forEach(function(mem) {
                    var uidMem = parseInt(String(mem.user_id || 0), 10);
                    var row;
                    if (uidMem > 0) {
                        row = document.createElement("a");
                        row.href = appBaseUrl + "/personnel/" + encodeURIComponent(String(uidMem));
                        row.className = "flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:border-emerald-300 hover:bg-emerald-50/50";
                    } else {
                        row = document.createElement("div");
                        row.className = "flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600";
                    }
                    var sp = document.createElement("span");
                    sp.className = "truncate";
                    sp.textContent = mem.label || "";
                    row.appendChild(sp);
                    if (typeof mem.readiness === "number") {
                        var rd = document.createElement("span");
                        rd.className = "text-[10px] font-black uppercase text-emerald-700 shrink-0";
                        rd.textContent = mem.readiness + "%";
                        row.appendChild(rd);
                    }
                    if (uidMem > 0) {
                        var ar = document.createElement("span");
                        ar.className = "text-[10px] text-slate-400 shrink-0";
                        ar.textContent = "→";
                        row.appendChild(ar);
                    }
                    membersBox.appendChild(row);
                });
            }
        }
        const childrenBox = document.getElementById("detail-children");
        if (childrenBox) {
            childrenBox.innerHTML = "";
            if (!node.children || node.children.length === 0) {
                childrenBox.innerHTML = node.isOrbatPlaceholder
                    ? "<p class=\"text-slate-500\">Les sous-unités ne sont pas visibles pour cet emplacement.</p>"
                    : "<p class=\"text-slate-500\">Aucune sous-unité.</p>";
            } else {
                node.children.forEach(function(child) {
                    const row = document.createElement("div");
                    row.className = "flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 cursor-pointer hover:bg-slate-50";
                    var childRead = (typeof child.readinessScore === "number") ? (child.readinessScore + "% ready") : "n/d";
                    row.innerHTML = "<div><p class=\"font-black uppercase text-[11px] tracking-[0.14em]\">" + (child.label || "—") + "</p><p class=\"text-xs text-slate-500 font-medium\">" + (child.role || "—") + "</p></div><div class=\"text-right\"><p class=\"text-[10px] font-black uppercase\">" + (child.strength || 0) + " pax</p><p class=\"text-[10px] text-slate-500 font-bold uppercase\">" + childRead + " · " + getStatusLabel(child.status || "active") + "</p></div>";
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
                structType: node.structType, maskMode: node.maskMode, staffMaskActive: node.staffMaskActive,
                maskHintLabel: node.maskHintLabel,
                orbatDetails: node.orbatDetails, chartIconUrl: node.chartIconUrl, chartImageUrl: node.chartImageUrl,
                members: node.members || [], children: children,
                readinessScore: node.readinessScore, localReadinessScore: node.localReadinessScore, readinessPopulation: node.readinessPopulation,
                isOrbatPlaceholder: node.isOrbatPlaceholder, placeholderReason: node.placeholderReason,
                viewerNamesRedacted: node.viewerNamesRedacted
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

    var pendingMoveUnitId = 0;
    var pendingMaskUnitId = 0;
    var pendingDeleteUnitId = 0;
    var pendingCreateParentId = 0;

    function bootOrbatTree() {
        flattenNodes(rosterData).forEach(function(n) { collapsedState.set(n.id, false); });
        renderTree(JSON.parse(JSON.stringify(rosterData)));
        selectNode(rosterData);
    }

    if (showOrbatEditTools) {
        ensureStructureOptions().then(function() {
            refreshOrbatTypeSelect();
            bootOrbatTree();
        }).catch(function() { bootOrbatTree(); });
    } else {
        bootOrbatTree();
    }

    if (showOrbatEditTools || orbatRecruitmentHub) {
        var cm = document.getElementById("orbat-ctx-menu");
        if (cm) {
            cm.addEventListener("click", function(e) {
                var btn = e.target && e.target.closest ? e.target.closest("button[data-ctx]") : null;
                if (!btn || !ctxTargetNode) return;
                e.preventDefault();
                var act = btn.getAttribute("data-ctx");
                var node = ctxTargetNode;
                var uid = node.unitId || 0;
                if (act === "hub-new-group") {
                    closeCtxMenu();
                    if (typeof window.orbatHubOpenRecruitmentModal === "function") {
                        window.orbatHubOpenRecruitmentModal("groupe", uid);
                    }
                    return;
                }
                if (act === "hub-new-team") {
                    closeCtxMenu();
                    if (typeof window.orbatHubOpenRecruitmentModal === "function") {
                        window.orbatHubOpenRecruitmentModal("equipe", uid);
                    }
                    return;
                }
                if (!showOrbatEditTools) {
                    closeCtxMenu();
                    return;
                }
                if (act === "edit") {
                    selectNode(node);
                    closeCtxMenu();
                    return;
                }
                if (uid < 1) { closeCtxMenu(); return; }
                if (act === "create") {
                    pendingCreateParentId = uid;
                    closeCtxMenu();
                    ensureStructureOptions().then(function() {
                        var sel = document.getElementById("orbat-create-type");
                        var nameIn = document.getElementById("orbat-create-name");
                        if (nameIn) nameIn.value = "";
                        if (sel && structureOptionsCache && structureOptionsCache.structTypes) {
                            sel.innerHTML = "";
                            structureOptionsCache.structTypes.forEach(function(t) {
                                var o = document.createElement("option");
                                o.value = t.id;
                                o.textContent = t.label;
                                sel.appendChild(o);
                            });
                            if (sel.options.length === 0) {
                                var o = document.createElement("option");
                                o.value = "unit";
                                o.textContent = "Unité";
                                sel.appendChild(o);
                            }
                        }
                        openModal("create");
                    });
                    return;
                }
                if (act === "move") {
                    pendingMoveUnitId = uid;
                    closeCtxMenu();
                    ensureStructureOptions().then(function() {
                        var sel = document.getElementById("orbat-move-parent");
                        if (!sel || !structureOptionsCache) return;
                        sel.innerHTML = "";
                        var o0 = document.createElement("option");
                        o0.value = "";
                        o0.textContent = "Racine (sans parent)";
                        sel.appendChild(o0);
                        var all = structureOptionsCache.units || [];
                        var forbid = new Set([pendingMoveUnitId].concat(collectDescendantIds(all, pendingMoveUnitId)));
                        all.forEach(function(u) {
                            if (forbid.has(u.id)) return;
                            var o = document.createElement("option");
                            o.value = String(u.id);
                            o.textContent = u.name;
                            sel.appendChild(o);
                        });
                        openModal("move");
                    });
                    return;
                }
                if (act === "detach") {
                    closeCtxMenu();
                    if (!confirm("Détacher cette unité au niveau racine ?")) return;
                    postStructure("move", { unit_id: String(uid), parent_id: "" }).catch(function(err) { alert(err.message || err); });
                    return;
                }
                if (act === "mask") {
                    pendingMaskUnitId = uid;
                    closeCtxMenu();
                    ensureStructureOptions().then(function() {
                        if (structureOptionsCache && structureOptionsCache.capabilities && !structureOptionsCache.capabilities.mask_editing) {
                            alert("Les réglages de confidentialité sur l’organigramme ne sont pas encore disponibles sur cet environnement.");
                            return;
                        }
                        var sel = document.getElementById("orbat-mask-select");
                        if (!sel || !structureOptionsCache) return;
                        sel.innerHTML = "";
                        (structureOptionsCache.maskModes || []).forEach(function(m) {
                            var o = document.createElement("option");
                            o.value = m.id;
                            o.textContent = m.label;
                            sel.appendChild(o);
                        });
                        var cur = (node.maskMode || "none");
                        if (sel.querySelector('option[value="' + cur + '"]')) sel.value = cur;
                        openModal("mask");
                    });
                    return;
                }
                if (act === "delete") {
                    pendingDeleteUnitId = uid;
                    closeCtxMenu();
                    openModal("delete");
                }
            });
        }
        document.addEventListener("click", function(e) {
            if (cm && cm.style.display === "block" && !cm.contains(e.target)) closeCtxMenu();
        });
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") closeCtxMenu();
        });
    }

    if (showOrbatEditTools) {
        document.querySelectorAll("[data-close-modal]").forEach(function(b) {
            b.addEventListener("click", function() {
                closeModal(b.getAttribute("data-close-modal") || "");
            });
        });
        ["create", "move", "mask", "delete", "chart-type"].forEach(function(mid) {
            var ov = document.getElementById("orbat-modal-" + mid);
            if (ov) {
                ov.addEventListener("click", function(e) {
                    if (e.target === ov) closeModal(mid);
                });
            }
        });

        var createSub = document.getElementById("orbat-create-submit");
        if (createSub) {
            createSub.addEventListener("click", function() {
                var nameIn = document.getElementById("orbat-create-name");
                var typ = document.getElementById("orbat-create-type");
                var nm = nameIn ? nameIn.value.trim() : "";
                if (!nm) { alert("Indiquez un nom pour la nouvelle unité."); return; }
                var parentId = pendingCreateParentId > 0 ? String(pendingCreateParentId) : "";
                postStructure("create", { name: nm, struct_type: typ ? typ.value : "unit", parent_id: parentId })
                    .then(function() { closeModal("create"); })
                    .catch(function(err) { alert(err.message || err); });
            });
        }
        var moveSub = document.getElementById("orbat-move-submit");
        if (moveSub) {
            moveSub.addEventListener("click", function() {
                var sel = document.getElementById("orbat-move-parent");
                var pid = sel && sel.value !== undefined ? sel.value : "";
                postStructure("move", { unit_id: String(pendingMoveUnitId), parent_id: pid === "" ? "" : String(pid) })
                    .then(function() { closeModal("move"); })
                    .catch(function(err) { alert(err.message || err); });
            });
        }
        var maskSub = document.getElementById("orbat-mask-submit");
        if (maskSub) {
            maskSub.addEventListener("click", function() {
                var sel = document.getElementById("orbat-mask-select");
                var v = sel ? sel.value : "none";
                postStructure("set_mask", { unit_id: String(pendingMaskUnitId), orbat_mask_mode: v })
                    .then(function() { closeModal("mask"); })
                    .catch(function(err) { alert(err.message || err); });
            });
        }
        var delSub = document.getElementById("orbat-delete-submit");
        if (delSub) {
            delSub.addEventListener("click", function() {
                postStructure("delete", { unit_id: String(pendingDeleteUnitId) })
                    .then(function() { closeModal("delete"); })
                    .catch(function(err) { alert(err.message || err); });
            });
        }

        var btnNewCt = document.getElementById("orbat-btn-new-chart-type");
        if (btnNewCt) {
            btnNewCt.addEventListener("click", function() {
                var inp = document.getElementById("orbat-chart-type-label");
                if (inp) inp.value = "";
                openModal("chart-type");
            });
        }
        var ctSub = document.getElementById("orbat-chart-type-submit");
        if (ctSub) {
            ctSub.addEventListener("click", function() {
                var inp = document.getElementById("orbat-chart-type-label");
                var lab = inp ? inp.value.trim() : "";
                if (!lab) { alert("Indiquez un nom pour ce type."); return; }
                var fd = new FormData();
                fd.append("_csrf_token", orbatCsrf);
                fd.append("action", "create");
                fd.append("label", lab);
                fetch(apiChartTypeUrl, { method: "POST", body: fd, credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
                    .then(function(r) { return r.json(); })
                    .then(function(j) {
                        if (!j || !j.success) throw new Error(j && j.message ? j.message : "Erreur");
                        if (j.chartDisplayTypes) {
                            structureOptionsCache = structureOptionsCache || {};
                            structureOptionsCache.chartDisplayTypes = j.chartDisplayTypes;
                        }
                        refreshOrbatTypeSelect();
                        closeModal("chart-type");
                    })
                    .catch(function(err) { alert(err.message || err); });
            });
        }

        async function postUnitMediaClear(icon, image) {
            var fd = new FormData();
            fd.append("_csrf_token", orbatCsrf);
            fd.append("unit_id", String(currentSelectedUnitId));
            if (icon) fd.append("clear_chart_icon", "1");
            if (image) fd.append("clear_chart_image", "1");
            var res = await fetch(apiUnitUrl, { method: "POST", body: fd, credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } });
            var j = await res.json().catch(function() { return {}; });
            if (!res.ok || !j.success) throw new Error(j.message || "Impossible de mettre à jour.");
            if (j.roster) applyRosterFromServer(j.roster);
        }
        var clrIcBtn = document.getElementById("orbat-clear-icon");
        if (clrIcBtn) clrIcBtn.addEventListener("click", function() {
            if (currentSelectedUnitId < 1) return;
            if (!confirm("Retirer l’icône de cette unité ?")) return;
            postUnitMediaClear(true, false).catch(function(e) { alert(e.message || e); });
        });
        var clrImgBtn = document.getElementById("orbat-clear-image");
        if (clrImgBtn) clrImgBtn.addEventListener("click", function() {
            if (currentSelectedUnitId < 1) return;
            if (!confirm("Retirer l’image de cette unité ?")) return;
            postUnitMediaClear(false, true).catch(function(e) { alert(e.message || e); });
        });
        var upIcIn = document.getElementById("orbat-upload-icon");
        if (upIcIn) upIcIn.addEventListener("change", function() {
            if (!upIcIn.files || !upIcIn.files[0] || currentSelectedUnitId < 1) return;
            var fd = new FormData();
            fd.append("_csrf_token", orbatCsrf);
            fd.append("unit_id", String(currentSelectedUnitId));
            fd.append("slot", "icon");
            fd.append("file", upIcIn.files[0]);
            fetch(apiUnitUploadUrl, { method: "POST", body: fd, credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
                .then(function(r) { return r.json(); })
                .then(function(j) {
                    if (!j || !j.success) throw new Error(j && j.message ? j.message : "Envoi impossible");
                    upIcIn.value = "";
                    if (j.roster) applyRosterFromServer(j.roster);
                })
                .catch(function(e) { alert(e.message || e); });
        });
        var upImgIn = document.getElementById("orbat-upload-image");
        if (upImgIn) upImgIn.addEventListener("change", function() {
            if (!upImgIn.files || !upImgIn.files[0] || currentSelectedUnitId < 1) return;
            var fd = new FormData();
            fd.append("_csrf_token", orbatCsrf);
            fd.append("unit_id", String(currentSelectedUnitId));
            fd.append("slot", "image");
            fd.append("file", upImgIn.files[0]);
            fetch(apiUnitUploadUrl, { method: "POST", body: fd, credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
                .then(function(r) { return r.json(); })
                .then(function(j) {
                    if (!j || !j.success) throw new Error(j && j.message ? j.message : "Envoi impossible");
                    upImgIn.value = "";
                    if (j.roster) applyRosterFromServer(j.roster);
                })
                .catch(function(e) { alert(e.message || e); });
        });
    }

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
