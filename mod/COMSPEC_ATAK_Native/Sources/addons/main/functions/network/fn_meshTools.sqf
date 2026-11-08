/*
    Outils du maillage pour Zeus, les créateurs de missions et les joueurs (lot 12, réglage comspec_atak_native_mesh_tools).
    Zeus (ZEN, clic droit) : brouilleur large bande, de canal ou GPS ; couper un nœud ; compromettre ou renouveler la clé de réseau d'un camp ; météo radio forcée ;
    graphe d'un camp en lecture seule (superposition 3D, graphe du serveur) ; journal de réseau. Actions vérifiées sur le serveur (fn_adminAllowed).
    Joueur (app Wave Relay, onglet RÉSEAU) : topologie, ping / chemin, prévision « si je pose un relais ici ». Lisent le graphe ; seule la prévision fait des bilans :
    à la demande, 64 au plus, une fois toutes les 10 s, dans un script planifié.
      "register" (client) ; "init" (serveur) ; "zeusAct" [action, arguments] (serveur) ; "off" : coupure de ma MPU5 ordonnée par Zeus
      "zeusSnapshot" -> graphe pour la superposition de Zeus, ou HashMap vide
      "playerRows" -> lignes de l'app ; "ping" [nom] ; "forecast"
*/
params [["_act", ""], ["_arg", []], ["_caller", objNull]];
switch (_act) do {
    case "init": {
        if (!isServer) exitWith {};
        {
            [_x, {
                params [["_who", objNull], ["_a", ""], ["_xa", []]];
                if (isNull _who || {!([_who] call comspec_atak_native_fnc_adminAllowed)}) exitWith {};
                ["zeusAct", [_a, _xa], _who] call comspec_atak_native_fnc_meshTools;
            }] call CBA_fnc_addEventHandler;
        } forEach ["comspec_atak_native_meshEwSrv", "comspec_atak_native_meshZeusSrv"];
    };
    case "zeusAct": {
        _arg params [["_a", ""], ["_xa", []]];
        switch (_a) do {
            case "jam": { ["jam", _xa] call comspec_atak_native_fnc_meshEW; };
            case "off": {
                private _u = _xa param [0, objNull];
                if (!isNull _u) then { ["comspec_atak_native_meshOff", [], _u] call CBA_fnc_targetEvent; ["log", ["zeus", format ["Nœud coupé par Zeus : %1", name _u]]] call comspec_atak_native_fnc_meshGraph; };
            };
            case "wx": {
                private _v = ((_xa param [0, 0]) max 0) min 40;
                missionNamespace setVariable ["COMSPEC_ATAK_MeshWxForce", _v, true];
                ["log", ["zeus", format ["Météo radio forcée : -%1 dB", _v]]] call comspec_atak_native_fnc_meshGraph;
            };
            case "key": {
                _xa params [["_side", ""], ["_how", "rekey"]];
                private _i = (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]) findIf { (_x param [4, ""]) isEqualTo _side && {_x param [5, false]} };
                if (_i >= 0) then { [_how, [((missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs") select _i) select 0], _caller] call comspec_atak_native_fnc_meshKeys; };
            };
            case "log": {
                private _l = missionNamespace getVariable ["COMSPEC_ATAK_MeshLog", []];
                { diag_log format ["[COMSPEC ATAK NATIVE][MESH] Journal t=%1 %2 : %3", _x select 0, _x select 1, _x select 2]; } forEach _l;
                ["comspec_atak_native_meshLogRx", [_l select [((count _l) - 12) max 0, 12]], _caller] call CBA_fnc_targetEvent;
            };
        };
    };
    case "register": {
        if (!hasInterface) exitWith {};
        ["comspec_atak_native_meshOff", {
            private _st = uiNamespace getVariable ["COMSPEC_ATAK_MpuSt", createHashMap];
            if (_st getOrDefault ["on", false]) then { ["power"] call comspec_atak_native_fnc_mpuLink; };
        }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_meshLogRx", {
            params [["_l", []]];
            { diag_log format ["[COMSPEC ATAK NATIVE][MESH] Journal t=%1 %2 : %3", _x select 0, _x select 1, _x select 2]; } forEach _l;
            hintSilent ((_l apply { format ["%1 %2", _x select 0, _x select 2] }) joinString "\n");
        }] call CBA_fnc_addEventHandler;
        if (isNil "zen_context_menu_fnc_addAction" || {!((missionNamespace getVariable ["comspec_atak_native_mesh_tools", true]) isEqualTo true)}) exitWith {};
        private _icon = "\z\comspec_atak_native\addons\main\data\app_waverelay.paa";
        private _root = ["comspec_atak_mesh", "ATAK · Maillage", _icon, {}, { ["active"] call comspec_atak_native_fnc_mpuLink }] call comspec_atak_native_fnc_zenAction;
        [_root, [], 6] call zen_context_menu_fnc_addAction;
        private _ask = { params ["_a", "_xa"]; ["comspec_atak_native_meshZeusSrv", [player, _a, _xa]] call CBA_fnc_serverEvent; };
        missionNamespace setVariable ["COMSPEC_ATAK_MeshZeusAsk", _ask];
        private _hover = { params ["", ["_objs", []], "", "", "", ["_h", objNull]]; ((_objs + [_h]) select { _x isEqualType objNull && {!isNull _x} && {_x isKindOf "CAManBase"} }) param [0, objNull] };
        missionNamespace setVariable ["COMSPEC_ATAK_MeshZeusHover", _hover];
        {
            _x params ["_id", "_label", "_code", ["_cond", { true }]];
            [[format ["comspec_atak_mesh_%1", _id], _label, "", _code, _cond] call comspec_atak_native_fnc_zenAction, ["comspec_atak_mesh"], 0] call zen_context_menu_fnc_addAction;
        } forEach [
            ["jamw", "Brouilleur large bande (120 s, 500 m)", { params ["_p"]; ["jam", [[_p, 500, 120, 0, ""]]] call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusAsk"); }],
            ["jamc", "Brouilleur du canal 1 (120 s, 500 m)", { params ["_p"]; ["jam", [[_p, 500, 120, 1, ""]]] call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusAsk"); }],
            ["jamg", "Brouillage GPS (120 s, 500 m)", { params ["_p"]; ["jam", [[_p, 500, 120, 0, "gps"]]] call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusAsk"); }],
            ["off", "Couper ce nœud", { ["off", [_this call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusHover")]] call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusAsk"); }, { !isNull (_this call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusHover")) }],
            ["cmp", "Compromettre la clé de son camp", { ["key", [str side group (_this call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusHover")), "compromise"]] call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusAsk"); }, { ["on"] call comspec_atak_native_fnc_meshKeys && {!isNull (_this call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusHover"))} }],
            ["rek", "Renouveler la clé de son camp", { ["key", [str side group (_this call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusHover")), "rekey"]] call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusAsk"); }, { ["on"] call comspec_atak_native_fnc_meshKeys && {!isNull (_this call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusHover"))} }],
            ["wx", "Météo radio : -10 dB oui / non", { ["wx", [[10, 0] select ((missionNamespace getVariable ["COMSPEC_ATAK_MeshWxForce", 0]) > 0)]] call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusAsk"); }],
            ["see", "Voir le graphe de ce camp (3D)", {
                private _u = _this call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusHover");
                uiNamespace setVariable ["COMSPEC_ATAK_MeshZeusSide", [str side group player, str side group _u] select (!isNull _u)];
                ["toggle"] call comspec_atak_native_fnc_devMeshOverlay;
            }],
            ["log", "Journal de réseau", { ["log", []] call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusAsk"); }]
        ];
    };
    case "zeusSnapshot": {
        if (isNull (getAssignedCuratorLogic player)) exitWith { createHashMap };
        private _dec = ["graph", [uiNamespace getVariable ["COMSPEC_ATAK_MeshZeusSide", str side group player]]] call comspec_atak_native_fnc_meshGraph;
        if (_dec isEqualTo []) exitWith { createHashMap };
        _dec params ["_nodes", "", "_links"];
        createHashMapFromArray [["at", diag_tickTime], ["links", _links], ["nodes", _nodes apply {
            _x params ["_o", "_owner", "_type", "_c", "_r", "_kb"];
            [_o, (getPosASL _o) vectorAdd [0, 0, 1.5], [format ["Passerelle, canal %1", _c], [_owner, true] call comspec_atak_native_fnc_unitCallsign] select (_type isNotEqualTo 2), ["node", "posed", "gw"] select _type, _c, _r, 0, _kb]
        }]]
    };
    case "playerRows": {
        if (!((missionNamespace getVariable ["comspec_atak_native_mesh_tools", true]) isEqualTo true) || {!(["active"] call comspec_atak_native_fnc_mpuLink)}) exitWith { [] };
        private _scr = (uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["screen", createHashMap];
        private _rows = [["section", "Topologie", "Mon réseau, par sauts jusqu'à la passerelle"]];
        private _lines = [];
        private _hops = _scr getOrDefault ["hops", []];
        {
            private _h = _hops param [_forEachIndex, -1];
            _lines pushBack [[_h, 99] select (_h < 0), format ["%1%2 %3 %4", [".", ""] select (_h <= 0), ["", "··"] select (_h > 1), ["coupé", format ["%1 saut(s)", _h]] select (_h >= 0), _x select 2]];
        } forEach (_scr getOrDefault ["nodes", []]);
        _lines sort true;
        _rows pushBack ["text", format ["<t size='0.8' font='EtelkaMonospacePro'>%1</t>", ["Aucun graphe reçu.", ((_lines select [0, 24]) apply { _x select 1 }) joinString "<br/>"] select (_lines isNotEqualTo [])]];
        _rows pushBack ["field", "meshPing", "Joindre (indicatif)", "", "PING", { ["ping", [["meshPing", ""] call comspec_atak_native_fnc_formValue]] call comspec_atak_native_fnc_meshTools; }, true];
        _rows pushBack ["buttons", [["PRÉVISION : UN RELAIS ICI", { ["forecast"] call comspec_atak_native_fnc_meshTools; }]]];
        _rows
    };
    case "ping": {
        private _q = toLower (trim (_arg param [0, ""]));
        if (_q isEqualTo "") exitWith {};
        private _nodes = ((uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["screen", createHashMap]) getOrDefault ["nodes", []];
        private _i = _nodes findIf { ((toLower (_x select 2)) find _q) >= 0 };
        if (_i < 0) exitWith { ["INFO", format ["%1 : introuvable dans mon réseau", _q], 4, 20] call comspec_atak_native_fnc_notify; };
        private _o = (_nodes select _i) select 0;
        private _p = ["peer", [_o]] call comspec_atak_native_fnc_meshGraph;
        private _name = (_nodes select _i) select 2;
        if (_p isEqualTo []) exitWith { ["WARNING", format ["%1 : injoignable par le maillage", _name], 5, 20] call comspec_atak_native_fnc_notify; };
        _p params ["_h", "_m", "_lvl"];
        ["INFO", format ["%1 : %2 saut(s), environ %3 ms, maillon faible : marge %4 dB, niveau %5", _name, _h, round (5 + _h * (missionNamespace getVariable ["comspec_atak_native_mpu_hop_ms", 15]) + 60 * (1 - (((_m max 0) / 20) min 1))), round _m, _lvl], 8, 30] call comspec_atak_native_fnc_notify;
    };
    case "forecast": {
        if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_MeshFcAt", -100]) < 10) exitWith { ["Prévision : attendez quelques secondes", 3] call comspec_atak_native_fnc_toast; };
        uiNamespace setVariable ["COMSPEC_ATAK_MeshFcAt", diag_tickTime];
        [] spawn {
            private _scr = (uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["screen", createHashMap];
            private _nodes = (_scr getOrDefault ["nodes", []]) select [0, 64];
            private _hops = _scr getOrDefault ["hops", []];
            private _at = (getPosASL player) vectorAdd [0, 0, 4];
            private _r = missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000];
            private _seen = 0;
            private _cut = 0;
            {
                if ((_x select 3) isEqualTo "gw" || {((_x select 1) distance _at) > _r}) then { continue };
                if ((([_at, _x select 1, _r, "coarse"] call comspec_atak_native_fnc_radioLink) select 0) >= 0) then {
                    _seen = _seen + 1;
                    if ((_hops param [_forEachIndex, -1]) < 0) then { _cut = _cut + 1; };
                };
            } forEach _nodes;
            ["INFO", format ["Un relais ici (4 m) joindrait %1 nœud(s), dont %2 aujourd'hui coupé(s). Estimation : relief seulement.", _seen, _cut], 8, 30] call comspec_atak_native_fnc_notify;
        };
    };
};
