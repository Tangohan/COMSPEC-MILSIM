/*
    App Statut : état du terminal d'un coup d'œil. Pastille de liaison en tête, puis tuiles
    (Athena, signal, batterie, latence) et sections Liaison, Synchronisation, Terminal ;
    raccourcis vers Resynch, Athena et Debug.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _str = { params ["_k", ["_d", ""]]; private _r = missionNamespace getVariable [_k, _d]; if (_r isEqualType "") then { _r } else { str _r } };
private _net = _state getOrDefault ["networkState", "OFFLINE"];
private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _q = [] call comspec_atak_native_fnc_linkQuality;
private _bat = round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]);
([_net] call {
    params ["_n"];
    switch (_n) do {
        case "CONNECTED": { ["EN LIGNE", "#5cc76b", "Liaison Athena établie"] };
        case "DEGRADED": { ["DÉGRADÉ", "#f2ab33", "Liaison instable : envois retardés"] };
        default { ["HORS LIGNE", "#e5483a", "Le terminal et la carte restent utilisables hors ligne"] };
    }
}) params ["_pill", "_pillHex", "_pillTxt"];
private _ago = {
    params ["_t"];
    if (_t < 0) exitWith { "jamais" };
    private _s = round (diag_tickTime - _t);
    if (_s < 60) exitWith { format ["il y a %1 s", _s] };
    format ["il y a %1 min", round (_s / 60)]
};
private _units = count (_data getOrDefault ["units", createHashMap]);
private _orders = count ([] call comspec_atak_native_fnc_tasksAll);
private _rows = [
    ["hero", "\z\comspec_atak_native\addons\main\data\app_status.paa", format ["<t size='1.35' font='RobotoCondensedBold'>STATUT</t>  <t color='%1' font='RobotoCondensedBold'>● %2</t><br/><t color='#8a9a93' size='0.85'>%3</t>", _pillHex, _pill, _pillTxt]],
    ["section", "Liaison", ["Session propre au terminal", "Session Athena d'Overwatch connect"] select _bridge],
    ["info", "Athena", format ["<t color='%1'>%2</t>", _pillHex, toLower _pill]],
    ["info", "Canal poste", ["<t color='#e5483a'>fermé</t>", "<t color='#5cc76b'>ouvert</t>"] select _ready],
    ["info", "Signal", format ["%1/4 · %2 kbit/s · %3", _q getOrDefault ["bars", 0], _q getOrDefault ["kbps", 0], _q getOrDefault ["label", "—"]]],
    ["info", "Latence · perte", format ["%1 ms · %2 %%", _q getOrDefault ["latency", 0], _q getOrDefault ["loss", 0]]],
    ["info", "Attente avant renvoi", format ["%1 s", missionNamespace getVariable ["COMSPEC_SendBackoffSec", 0]]],
    ["section", "Synchronisation", ""],
    ["info", "Dernière synchro", if (_bridge) then { ["en attente", "assurée par Overwatch"] select _ready } else { [_data getOrDefault ["lastNetworkUpdate", -1]] call _ago }],
    ["info", "Dernier Resynch", ["COMSPEC_ATAK_ResynchAt", "jamais"] call _str],
    ["info", "Unités sur la carte", str _units],
    ["info", "Ordres reçus", str _orders],
    ["section", "Terminal", ""],
    ["info", "Batterie", format ["<t color='%1'>%2 %%</t>", ["#e5483a", "#f2ab33", "#5cc76b"] select ((floor (_bat / 30)) min 2), _bat]],
    ["info", "Version du mod", ["COMSPEC_ATAK_NativeVersion", "?"] call _str],
    ["info", "Extension", ["DLL native", "DLL Overwatch embarquée"] select _bridge],
    ["buttons", [
        ["RESYNCH", { ["RESYNCH"] call comspec_atak_native_fnc_navigate; }, true],
        ["ATHENA", { ["ATHENA"] call comspec_atak_native_fnc_navigate; }],
        ["DEBUG", { ["DEBUG"] call comspec_atak_native_fnc_navigate; }]
    ]]
];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
