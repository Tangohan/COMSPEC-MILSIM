/* COMSPEC ATAK — tactical symbology renderer.
 * APP-6 / MIL-STD-2525 visual grammar: affiliation frames + function glyph.
 * This is a compact operational subset, not a full SIDC implementation.
 */
(function () {
    "use strict";
    var AFF = {
        FRIENDLY: { color: "#65b7ff", frame: "rect", label: "FRIENDLY" },
        HOSTILE: { color: "#ff6464", frame: "diamond", label: "HOSTILE" },
        NEUTRAL: { color: "#6fe0a2", frame: "square", label: "NEUTRAL" },
        UNKNOWN: { color: "#f1d45f", frame: "quatrefoil", label: "UNKNOWN" }
    };
    var GLYPH = {
        POINT: "•", UNIT: "●", CONTACT: "?", OBJECTIVE: "◎", HAZARD: "!", SSE: "◇",
        LZ: "H", MEDICAL: "+", VEHICLE: "▭", INFANTRY: "X", FLAG: "⚑", TARGET: "⊙",
        AIR_DEFENCE: "∧", AA: "AA", AT: "AT", MG: "MG", UAV: "U", HELI: "H",
        SPECOPS: "◆", RESUPPLY: "□", RADIO: "R", RELAY: "R", IED: "IED",
        RALLY: "R", ENEMY_OBS: "?", OBSERVATION: "?"
    };
    var TEMPLATES = [
        { id: "enemy_obs", name: "Observation ennemie", drawType: "point", affiliation: "HOSTILE", symbol: "OBSERVATION", ttlMin: 30, color: "#ff6464",
            fields: [{ key: "Effectif estimé", placeholder: "ex. 4 à 6" }, { key: "Armement", placeholder: "fusils, lance-roquettes…" }, { key: "Direction", placeholder: "vers le nord" }, { key: "Heure d’observation", placeholder: "automatique" }, { key: "Confiance", placeholder: "faible / moyenne / haute" }] },
        { id: "rally", name: "Point de regroupement", drawType: "rally", affiliation: "FRIENDLY", symbol: "RALLY", ttlMin: 0, color: "#65b7ff",
            fields: [{ key: "Indicatif", placeholder: "qui s’y rassemble" }, { key: "Horaire", placeholder: "à quelle heure" }] },
        { id: "lz", name: "Zone d’atterrissage", drawType: "circle", affiliation: "FRIENDLY", symbol: "LZ", ttlMin: 0, color: "#6ae9c4",
            fields: [{ key: "Aéronef", placeholder: "type" }, { key: "Obstacles", placeholder: "fils, arbres, pente" }] },
        { id: "ied", name: "IED / menace explosive", drawType: "point", affiliation: "HOSTILE", symbol: "IED", ttlMin: 120, color: "#ff6464",
            fields: [{ key: "Type", placeholder: "confirmé / suspect" }, { key: "Déclenchement", placeholder: "commande / pression / inconnu" }] },
        { id: "relay", name: "Relais communications", drawType: "point", affiliation: "FRIENDLY", symbol: "RELAY", ttlMin: 0, color: "#65b7ff",
            fields: [{ key: "Réseau", placeholder: "ACRE / ATHENA" }, { key: "Portée", placeholder: "mètres" }] },
        { id: "medical", name: "Point médical", drawType: "point", affiliation: "FRIENDLY", symbol: "MEDICAL", ttlMin: 0, color: "#65b7ff", fields: [] },
        { id: "target", name: "Objectif / cible", drawType: "point", affiliation: "HOSTILE", symbol: "TARGET", ttlMin: 120, color: "#ff6464", fields: [] }
    ];
    function esc(v) { return String(v == null ? "" : v).replace(/[&<>"]/g, function (c) { return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]; }); }
    function frameSvg(frame, color) {
        if (frame === "diamond")
            return '<polygon points="20,2 38,20 20,38 2,20" fill="rgba(5,12,10,.82)" stroke="' + color + '" stroke-width="2.5"/>';
        if (frame === "square")
            return '<rect x="3" y="3" width="34" height="34" rx="1" fill="rgba(5,12,10,.82)" stroke="' + color + '" stroke-width="2.5"/>';
        if (frame === "quatrefoil")
            return '<path d="M20 2 C30 2 38 10 38 20 C38 30 30 38 20 38 C10 38 2 30 2 20 C2 10 10 2 20 2Z" fill="rgba(5,12,10,.82)" stroke="' + color + '" stroke-width="2.5"/>';
        return '<rect x="2" y="7" width="36" height="26" rx="2" fill="rgba(5,12,10,.82)" stroke="' + color + '" stroke-width="2.5"/>';
    }
    function render(opts) {
        opts = opts || {};
        var affiliation = String(opts.affiliation || opts.type || "UNKNOWN").toUpperCase();
        if (!AFF[affiliation])
            affiliation = "UNKNOWN";
        var a = AFF[affiliation];
        var sym = String(opts.symbol || opts.category || "POINT").toUpperCase().replace(/^COMSPEC_(?:CTAB|MRH)_/, "");
        var g = GLYPH[sym] || (sym.indexOf("INF") >= 0 ? "X" : sym.indexOf("AA") >= 0 ? "AA" : sym.indexOf("AT") >= 0 ? "AT" : sym.indexOf("MG") >= 0 ? "MG" : "•");
        var stale = Number(opts.stale || 0);
        var opacity = stale >= 2 ? .28 : stale === 1 ? .58 : 1;
        return '<span class="comspec-2525 ' + (stale ? "stale-" + stale : "") + '" style="opacity:' + opacity + '">' +
            '<svg viewBox="0 0 40 40" aria-hidden="true">' + frameSvg(a.frame, a.color) +
            '<text x="20" y="24" text-anchor="middle" font-family="Arial,sans-serif" font-size="' + (g.length > 2 ? 8 : 13) + '" font-weight="800" fill="' + a.color + '">' + esc(g) + '</text>' +
            '</svg></span>';
    }
    function template(id) {
        id = String(id || "").toLowerCase();
        for (var i = 0; i < TEMPLATES.length; i++)
            if (TEMPLATES[i].id === id)
                return TEMPLATES[i];
        return null;
    }
    function ttlFor(marker) {
        marker = marker || {};
        if (Number(marker.ttlMin) >= 0 && marker.ttlMin !== "" && marker.ttlMin != null)
            return Number(marker.ttlMin);
        var sym = String(marker.symbol || marker.template || "").toLowerCase();
        var t = template(sym);
        if (t)
            return Number(t.ttlMin || 0);
        var cat = String(marker.category || "").toUpperCase();
        if (cat === "CONTACT")
            return 30;
        if (cat === "HAZARD")
            return 120;
        if (cat === "OBJECTIVE")
            return 120;
        return 0;
    }
    window.COMSPEC_MapSymbols = { templates: TEMPLATES, affiliations: AFF, render: render, template: template, ttlFor: ttlFor };
})();
