/*
    Outils de développement du réseau maillé (lot 0, docs/technical/reseau-mesh-mpu5.md, section 6 bis).
    Réservés au mode développeur : réglage serveur comspec_atak_native_mesh_dev (décoché par défaut) ET droits de l'app
    Admin (fn_adminAllowed : Zeus, hôte, admin connecté, UID autorisé). Pour un joueur ordinaire, rien n'existe : ni
    onglet, ni action, ni calcul, ni nœud simulé.
      "on"       -> vrai si le réglage est coché (lecture seule, à faire avant tout autre travail)
      "allowed"  -> vrai si le joueur local peut utiliser les outils
      "snapshot" -> dernier graphe vu par ce client : HashMap "nodes" [[objet, position ASL, nom, type, canal, portée m,
                    sauts (-1 hors maillage), débit kbit/s]...], "links" [[index A, index B, marge dB, kbit/s]...], "at"
                    (diag_tickTime) ; vide sans graphe
      "export"   -> écrit le graphe dans le RPT, trié par nom (deux clients comparent leurs exports) ; rend le nombre de lignes
      "perfRows" -> lignes du tableau de performance (onglet MAILLAGE de l'app Debug, fn_formRender)
      "askServer" : demande au serveur ses compteurs et le journal de l'hystérésis (réponse : événement comspec_atak_native_devMeshStats)
      "tabRows"  -> lignes de tout l'onglet MAILLAGE
*/
params [["_act", ""], ["_arg", []]];
switch (_act) do {
    case "on": { (missionNamespace getVariable ["comspec_atak_native_mesh_dev", false]) isEqualTo true };
    case "allowed": {
        hasInterface && {(missionNamespace getVariable ["comspec_atak_native_mesh_dev", false]) isEqualTo true} && {[player] call comspec_atak_native_fnc_adminAllowed}
    };
    case "snapshot": {
        private _m = uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap];
        private _d = _m getOrDefault ["dev", []];
        if (_d isEqualTo []) exitWith { createHashMap };
        createHashMapFromArray [["nodes", _d select 0], ["links", _d select 1], ["at", _m getOrDefault ["at", -1]]]
    };
    case "export": {
        if !(["allowed"] call comspec_atak_native_fnc_devMesh) exitWith { 0 };
        private _s = ["snapshot"] call comspec_atak_native_fnc_devMesh;
        private _tag = "[COMSPEC ATAK NATIVE][MESH]";
        if ((count _s) isEqualTo 0) exitWith {
            diag_log format ["%1 Export : aucun graphe sur ce client (liaison réaliste active et MPU5 allumée ?)", _tag];
            1
        };
        private _nodes = _s get "nodes";
        diag_log format ["%1 Export : %2 nœud(s), %3 lien(s), graphe calculé il y a %4 s, temps de mission %5", _tag, count _nodes, count (_s get "links"), ((diag_tickTime - (_s get "at")) toFixed 1), round time];
        private _rows = _nodes apply {
            _x params ["", "_p", "_n", "_k", "_c", "_r", "_h", "_kb"];
            format ["%1 Nœud %2 type=%3 canal=%4 portée=%5 m sauts=%6 débit=%7 kbit/s pos=%8", _tag, _n, _k, _c, round _r, _h, round _kb, _p apply { round _x }]
        };
        _rows sort true;
        { diag_log _x; } forEach _rows;
        private _lk = (_s get "links") apply {
            _x params ["_a", "_b", "_m", "_kb"];
            private _na = (_nodes select _a) select 2;
            private _nb = (_nodes select _b) select 2;
            // Paire dans l'ordre des noms : la même ligne sur tous les clients.
            private _pair = [_na, _nb];
            _pair sort true;
            format ["%1 Lien %2 <-> %3 marge=%4 dB débit=%5 kbit/s", _tag, _pair select 0, _pair select 1, _m toFixed 1, round _kb]
        };
        _lk sort true;
        { diag_log _x; } forEach _lk;
        1 + (count _rows) + (count _lk)
    };
    case "askServer": {
        if !(["allowed"] call comspec_atak_native_fnc_devMesh) exitWith { false };
        if !(uiNamespace getVariable ["COMSPEC_ATAK_MeshStatsEh", false]) then {
            uiNamespace setVariable ["COMSPEC_ATAK_MeshStatsEh", true];
            ["comspec_atak_native_devMeshStats", {
                params [["_pairs", []], ["_log", []]];
                missionNamespace setVariable ["COMSPEC_ATAK_MeshStatsSrv", createHashMapFromArray _pairs];
                missionNamespace setVariable ["COMSPEC_ATAK_MeshLinkLogSrv", _log];
            }] call CBA_fnc_addEventHandler;
        };
        ["request", ["stats", []]] call comspec_atak_native_fnc_devMeshSim
    };
    case "perfRows": {
        // Le constructeur tourne sur le serveur par défaut : ses compteurs viennent de là. Sur le client : ceux de sa lecture et, sans serveur de référence, de sa passe.
        private _srv = ["serverBuilt"] call comspec_atak_native_fnc_meshGraph;
        private _st = missionNamespace getVariable [["COMSPEC_ATAK_MeshStats", "COMSPEC_ATAK_MeshStatsSrv"] select (_srv && {!isServer}), createHashMap];
        private _out = [["info", "Graphe calculé", ["sur chaque client", "sur le serveur"] select _srv]];
        if ((count _st) isEqualTo 0) then {
            _out pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Aucune passe du constructeur mesurée sur ce client.</t>"];
        } else {
            private _hit = _st getOrDefault ["hits", 0];
            private _tests = _st getOrDefault ["tests", 0];
            _out append [
                ["info", "Dernière passe", format ["%1 ms de bout en bout · %2 ms de travail · %3 tranche(s), pire %4 ms", (_st getOrDefault ["ms", 0]) toFixed 1, (_st getOrDefault ["workMs", 0]) toFixed 1, _st getOrDefault ["slices", 0], (_st getOrDefault ["sliceMaxMs", 0]) toFixed 1]],
                ["info", "Nœuds · liens", format ["%1 · %2 · %3 camp(s) · %4 paire(s) élaguée(s)", _st getOrDefault ["nodes", 0], _st getOrDefault ["links", 0], _st getOrDefault ["sides", 0], _st getOrDefault ["pruned", 0]]],
                ["info", "Diffusion", format ["%1 fois depuis le début · %2 signes (texte, hors enveloppe réseau)", _st getOrDefault ["pubs", 0], _st getOrDefault ["size", 0]]],
                ["info", "Images par seconde (machine du graphe)", str (_st getOrDefault ["fps", 0])],
                ["info", "Bilans radio · cache", format ["%1 calculés · %2 en cache (%3 %%)", _tests, _hit, round (100 * _hit / ((_tests + _hit) max 1))]]
            ];
        };
        _out pushBack ["info", "Images par seconde", str round diag_fps];
        // Compteurs fn_perfAcc depuis le dernier bilan de 30 s.
        private _acc = missionNamespace getVariable ["COMSPEC_ATAK_PerfAcc", createHashMap];
        private _lines = ((keys _acc) select { (_x find "maillage") >= 0 || {_x isEqualTo "radio de données"} }) apply {
            private _c = _acc get _x;
            format ["%1 : %2× · moy %3 ms · max %4 ms", _x, _c select 0, ((_c select 1) / ((_c select 0) max 1) * 1000) toFixed 2, ((_c select 2) * 1000) toFixed 1]
        };
        _lines sort true;
        _out pushBack ["text", format ["<t size='0.8' font='EtelkaMonospacePro'>%1</t>", ["Compteurs : rien depuis le dernier bilan de 30 s.", _lines joinString "<br/>"] select (_lines isNotEqualTo [])]];
        _out
    };
    case "tabRows": {
        if !(["allowed"] call comspec_atak_native_fnc_devMesh) exitWith { [] };
        private _ov = (missionNamespace getVariable ["COMSPEC_ATAK_MeshOverlayEh", -1]) >= 0;
        private _refresh = { [{ ['DEBUG'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
        private _simBtn = {
            params ["_label", "_a", "_args"];
            [_label, compile format ["['request', [%1, %2]] call comspec_atak_native_fnc_devMeshSim;", str _a, str _args]]
        };
        ["askServer"] call comspec_atak_native_fnc_devMesh;
        private _rows = [["section", "Tableau de performance", "Mesures, aucune valeur n'est estimée"]];
        _rows append (["perfRows"] call comspec_atak_native_fnc_devMesh);
        _rows pushBack ["section", "Visualisation", format ["Nœuds simulés publiés : %1", count (missionNamespace getVariable ["COMSPEC_ATAK_MeshSimNodes", []])]];
        _rows pushBack ["buttons", [
            [["SUPERPOSITION 3D", "MASQUER 3D"] select _ov, compile "['toggle'] call comspec_atak_native_fnc_devMeshOverlay; [{ ['DEBUG'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;"],
            ["EXPORT RPT", { [format ["%1 ligne(s) écrite(s) dans le RPT", ["export"] call comspec_atak_native_fnc_devMesh], 3] call comspec_atak_native_fnc_toast; }],
            ["ACTUALISER", _refresh, true]
        ]];
        _rows pushBack ["buttons", [
            ["ACTIONS DE VISÉE", { ["actions"] call comspec_atak_native_fnc_devLinkInspect; }],
            ["TESTS", { [] call comspec_atak_native_fnc_devMeshTest; }]
        ]];
        _rows pushBack ["section", "Nœuds simulés", "Sur le serveur, canal du joueur"];
        _rows pushBack ["buttons", [
            ["CHAÎNE A-B-C-D", "scenario", ["chain"]] call _simBtn,
            ["GRILLE 30", "scenario", ["grid30"]] call _simBtn,
            ["GRILLE 60", "scenario", ["grid60"]] call _simBtn
        ]];
        _rows pushBack ["buttons", [
            ["PARTITION", "scenario", ["partition"]] call _simBtn,
            ["BROUILLEUR", "scenario", ["jammer"]] call _simBtn,
            ["MOUVEMENT", "scenario", ["move"]] call _simBtn,
            ["EFFACER", "clear", []] call _simBtn
        ]];
        _rows
    };
};
