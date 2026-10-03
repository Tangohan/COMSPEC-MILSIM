/*
    Debug : JOURNAL (natif + Overwatch, filtrable), TRANSFERTS (réseau simulé, photos, file d'attente),
    ÉTAT (liaison, extensions, versions, simulations) et OUTILS (copier, vider, tests).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["dbgTab", "LOG"];
private _lvl = _s getOrDefault ["dbgLvl", "ALL"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['dbgTab', '%1']; [{ ['DEBUG'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _tab isEqualTo _k] };
private _rows = [["segment", "", [["JOURNAL", "LOG"] call _tabBtn, ["TRANSFERTS", "NET"] call _tabBtn, ["ÉTAT", "STATE"] call _tabBtn, ["OUTILS", "TOOLS"] call _tabBtn]]];
private _lvlColor = { params ["_v"]; switch (_v) do { case "ERROR": { "#e5483a" }; case "WARN"; case "WARNING": { "#f2ab33" }; case "DEBUG": { "#6c7671" }; default { "#8a9a93" }; } };
// Journal fusionné : natif [t, niveau, module, message] et Overwatch « t [COMSPEC Overwatch][NIV][Canal] message ».
private _merged = {
    private _out = (missionNamespace getVariable ["COMSPEC_ATAK_Log", []]) apply { [_x select 0, _x select 1, "ATAK·" + (_x select 2), _x select 3] };
    {
        private _parts = _x splitString " ";
        private _t = parseNumber (_parts param [0, "0"]);
        private _rest = _x select [(count (_parts param [0, ""])) + 1];
        private _m = (_rest splitString "]") apply { trim ([_x, "[", ""] call CBA_fnc_replace) };
        _out pushBack [_t, toUpper (_m param [1, "INFO"]), "OW·" + (_m param [2, ""]), (_m select [3]) joinString "]"];
    } forEach (missionNamespace getVariable ["COMSPEC_DiagLog", []]);
    _out sort false;
    _out
};
switch (_tab) do {
    case "NET": {
        private _q = [] call comspec_atak_native_fnc_linkQuality;
        _rows append [
            ["info", "Débit", format ["%1 · %2 kbit/s · %3 ms · perte %4 %%", _q get "label", _q get "kbps", _q get "latency", _q get "loss"]],
            ["info", "File sans réseau", str count (missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []])],
            ["info", "Photos en attente / reçues / refusées", format ["%1 / %2 / %3", count (missionNamespace getVariable ["COMSPEC_Athena_PhotoPending", []]), count (missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []]), count (missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []])]],
            ["info", "Dernier envoi photo", [missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", "—"]] call _esc],
            ["info", "Retour Athena photo", [str (missionNamespace getVariable ["COMSPEC_LastReconUploadResult", []])] call _esc],
            ["section", "Transferts", "Les plus récents d'abord"]
        ];
        private _t = +(missionNamespace getVariable ["COMSPEC_ATAK_NetLog", []]);
        reverse _t;
        private _lines = (_t select [0, 60]) apply {
            _x params ["_h", "_lab", "_kb", "_st", "_det"];
            format ["<t color='#8a9a93'>%1</t> <t color='%2'>%3</t> %4 <t color='#8a9a93'>(%5 Ko) %6</t>", _h, switch (_st) do { case "LIVRÉ"; case "IMMÉDIAT": { "#5cc76b" }; case "PERDU"; case "EN FILE": { "#e5483a" }; default { "#f2ab33" }; }, _st, [_lab] call _esc, _kb, [_det] call _esc]
        };
        _rows pushBack ["text", ["<t color='#8a9a93'>Aucun transfert pour l'instant.</t>", format ["<t size='0.8' font='EtelkaMonospacePro'>%1</t>", _lines joinString "<br/>"]] select ((count _lines) > 0)];
    };
    case "STATE": {
        private _q = { params ["_k", ["_d", "—"]]; private _v = missionNamespace getVariable [_k, _d]; [str _v, _v] select (_v isEqualType "") };
        _rows append [
            ["section", "Liaison", ""],
            ["info", "Mode", ["DLL native seule", "Overwatch connect (session partagée)"] select ([] call comspec_atak_native_fnc_bridge)],
            ["info", "Athena prête", ["COMSPEC_AthenaReady", false] call _q],
            ["info", "État de liaison", ["COMSPEC_LinkState"] call _q],
            ["info", "Authentification", ["comspec_overwatch_auth_state"] call _q],
            ["info", "Latence mesurée", format ["%1 ms", ["COMSPEC_LastLatencyMs", -1] call _q]],
            ["info", "Attente réseau (backoff)", format ["%1 s", ["COMSPEC_SendBackoffSec", 0] call _q]],
            ["section", "Extensions", ""],
            ["info", "Init DLL native", [["COMSPEC_ATAK_NativeExtensionInit"] call _q] call _esc],
            ["info", "Session restaurée", [["COMSPEC_ATAK_NativeAuthRestore"] call _q] call _esc],
            ["section", "Versions et simulations", ""],
            ["info", "ATAK natif", ["COMSPEC_ATAK_NativeVersion"] call _q],
            ["info", "Communauté", ["comspec_tenant_name", "—"] call _q],
            ["info", "Dégâts / débit / apps civiles", format ["%1 / %2 / %3", ["comspec_atak_native_damage_sim", true] call _q, ["comspec_atak_native_net_sim", true] call _q, ["comspec_atak_native_civil_apps", true] call _q]],
            ["info", "Téléphone", (([] call comspec_atak_native_fnc_deviceHealth) get "state") + format [" · batterie %1 %%", round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100])]],
            ["info", "FPS / joueurs", format ["%1 / %2", round diag_fps, count allPlayers]]
        ];
    };
    case "TOOLS": {
        _rows append [
            ["text", "<t size='0.85' color='#8a9a93'>Le journal complet est aussi dans le fichier RPT d'Arma (et dans COMSPEC/logs avec Overwatch).</t>"],
            ["buttons", [["COPIER LE JOURNAL", {
                private _lines = ([] call (uiNamespace getVariable ["COMSPEC_ATAK_DbgMerged", { [] }])) apply { format ["%1 [%2][%3] %4", (_x select 0) toFixed 1, _x select 1, _x select 2, _x select 3] };
                copyToClipboard (_lines joinString endl);
                ["SUCCESS", format ["%1 ligne(s) copiée(s) dans le presse-papiers", count _lines], 3, 20] call comspec_atak_native_fnc_notify;
            }, true], ["VIDER", { missionNamespace setVariable ["COMSPEC_ATAK_Log", []]; missionNamespace setVariable ["COMSPEC_ATAK_NetLog", []]; ["INFO", "Journaux natifs vidés", 2, 10] call comspec_atak_native_fnc_notify; }]]],
            ["buttons", [["TEST DE DÉBIT", { uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []]; private _q = [] call comspec_atak_native_fnc_linkQuality; ["INFO", format ["%1 kbit/s, %2 ms, perte %3 %%", _q get "kbps", _q get "latency", _q get "loss"], 4, 10] call comspec_atak_native_fnc_notify; }],
                ["MESURER LA LATENCE", { if (isNil "comspec_overwatch_connect_fnc_measureLatency") exitWith { ["WARNING", "Overwatch non chargé", 3, 10] call comspec_atak_native_fnc_notify; }; [] call comspec_overwatch_connect_fnc_measureLatency; }]]],
            ["buttons", [["ÉTAT DANS LE RPT", { [] call comspec_atak_native_fnc_debugDump; ["INFO", "État écrit dans le RPT", 3, 10] call comspec_atak_native_fnc_notify; }],
                ["RELIRE LES APPS", { uiNamespace setVariable ["COMSPEC_ATAK_AppsCache", []]; uiNamespace setVariable ["COMSPEC_ATAK_FoodMenu", []]; ["INFO", "Caches vidés", 2, 10] call comspec_atak_native_fnc_notify; }]]],
            ["switch", "Journal détaillé (DEBUG)", missionNamespace getVariable ["COMSPEC_ATAK_LogDebug", false], { missionNamespace setVariable ["COMSPEC_ATAK_LogDebug", !(missionNamespace getVariable ["COMSPEC_ATAK_LogDebug", false])]; [{ ['DEBUG'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, "Affiche aussi les lignes de niveau DEBUG"]
        ];
    };
    default {
        private _lvBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['dbgLvl', '%1']; [{ ['DEBUG'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _lvl isEqualTo _k] };
        _rows pushBack ["segment", "", [["TOUT", "ALL"] call _lvBtn, ["ALERTES", "WARN"] call _lvBtn, ["ERREURS", "ERROR"] call _lvBtn, ["ATAK", "ATAK"] call _lvBtn, ["OVERWATCH", "OW"] call _lvBtn]];
        private _all = call _merged;
        private _dbg = missionNamespace getVariable ["COMSPEC_ATAK_LogDebug", false];
        private _list = _all select {
            private _v = _x select 1;
            (_dbg || {_v isNotEqualTo "DEBUG"}) && {switch (_lvl) do {
                case "WARN": { _v in ["WARN", "WARNING", "ERROR"] };
                case "ERROR": { _v isEqualTo "ERROR" };
                case "ATAK": { ((_x select 2) select [0, 5]) isEqualTo "ATAK·" };
                case "OW": { ((_x select 2) select [0, 3]) isEqualTo "OW·" };
                default { true };
            }}
        };
        private _n = { (_x select 1) isEqualTo "ERROR" } count _all;
        private _w = { (_x select 1) in ["WARN", "WARNING"] } count _all;
        _rows pushBack ["text", format ["<t size='0.85'>%1 ligne(s) · <t color='#e5483a'>%2 erreur(s)</t> · <t color='#f2ab33'>%3 alerte(s)</t></t>", count _all, _n, _w]];
        private _lines = (_list select [0, 80]) apply {
            _x params ["_t", "_v", "_m", "_msg"];
            format ["<t color='#6c7671'>%1</t> <t color='%2'>%3</t> <t color='#7fb6e6'>%4</t> %5", _t toFixed 0, [_v] call _lvlColor, _v select [0, 4], [_m] call _esc, [_msg] call _esc]
        };
        _rows pushBack ["text", ["<t color='#8a9a93'>Journal vide.</t>", format ["<t size='0.78' font='EtelkaMonospacePro'>%1</t>", _lines joinString "<br/>"]] select ((count _lines) > 0)];
    };
};
uiNamespace setVariable ["COMSPEC_ATAK_DbgMerged", _merged];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
