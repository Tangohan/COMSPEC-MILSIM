/*
    App Statut : état du terminal d'un coup d'œil. Pastille de liaison en tête, puis tuiles
    (Athena, signal, batterie, latence) et sections Liaison, Synchronisation, Terminal (dont la mémoire vive) ;
    raccourcis vers Resynch, Athena et Debug.
    4.2.1 : signal, latence, attente avant renvoi, « il y a X s » de la dernière synchro et batterie réécrits sur place
    chaque seconde (["live"], minuterie COMSPEC_ATAK_StatusLive), téléphone ouvert sur cette page seulement.
    Params : [mode] ("" : dessin de la page ; "live" : valeurs vivantes seulement, sans redessiner)
*/
params [["_mode", ""]];
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
        case "CONNECTED": { ["EN LIGNE", (COMSPEC_ATAK_Th get "hexOk"), "Liaison Athena établie"] };
        case "DEGRADED": { ["DÉGRADÉ", (COMSPEC_ATAK_Th get "hexWarn"), "Liaison instable : envois retardés"] };
        default { ["HORS LIGNE", (COMSPEC_ATAK_Th get "hexAlert"), "Le terminal et la carte restent utilisables hors ligne"] };
    }
}) params ["_pill", "_pillHex", "_pillTxt"];
private _ago = {
    params ["_t"];
    if (_t < 0) exitWith { "jamais" };
    private _s = round (diag_tickTime - _t);
    if (_s < 60) exitWith { format ["il y a %1 s", _s] };
    format ["il y a %1 min", round (_s / 60)]
};
// Valeurs vivantes : même texte au dessin et à la mise à jour sur place (lignes « info » à id, fn_formSet).
private _live = createHashMapFromArray [
    ["stSig", format ["%1/4 · %2 kbit/s · %3", _q getOrDefault ["bars", 0], _q getOrDefault ["kbps", 0], _q getOrDefault ["label", "—"]]],
    ["stLat", format ["%1 ms · %2 %%", _q getOrDefault ["latency", 0], _q getOrDefault ["loss", 0]]],
    ["stBack", format ["%1 s", missionNamespace getVariable ["COMSPEC_SendBackoffSec", 0]]],
    ["stSync", if (_bridge) then { ["en attente", "assurée par Overwatch"] select _ready } else { [_data getOrDefault ["lastNetworkUpdate", -1]] call _ago }],
    ["stBat", format ["<t color='%1'>%2 %%</t>", [(COMSPEC_ATAK_Th get "hexAlert"), (COMSPEC_ATAK_Th get "hexWarn"), (COMSPEC_ATAK_Th get "hexOk")] select ((floor (_bat / 30)) min 2), _bat]]
];
if (_mode isEqualTo "live") exitWith {
    private _shown = false;
    { if ([_x, _y] call comspec_atak_native_fnc_formSet) then { _shown = true; }; } forEach _live;
    _shown
};
private _units = count (_data getOrDefault ["units", createHashMap]);
private _orders = count ([] call comspec_atak_native_fnc_tasksAll);
private _rows = [
    ["hero", "\z\comspec_atak_native\addons\main\data\app_status.paa", format ["<t size='1.35' font='COMSPEC_BarlowBold'>STATUT</t>  <t color='%1' font='COMSPEC_BarlowBold'>● %2</t><br/><t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "' size='0.85'>%3</t>", _pillHex, _pill, _pillTxt]],
    ["section", "Liaison", ["Session propre au terminal", "Session Athena de COMSPEC Link"] select _bridge],
    ["info", "Athena", format ["<t color='%1'>%2</t>", _pillHex, toLower _pill]],
    ["info", "Canal poste", ["<t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>fermé</t>", "<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>ouvert</t>"] select _ready],
    ["info", "Signal", _live get "stSig", "stSig"],
    ["info", "Latence · perte", _live get "stLat", "stLat"],
    ["info", "Attente avant renvoi", _live get "stBack", "stBack"],
    ["section", "Synchronisation", ""],
    ["info", "Dernière synchro", _live get "stSync", "stSync"],
    ["info", "Dernière resynchronisation", ["COMSPEC_ATAK_ResynchAt", "jamais"] call _str],
    ["info", "Unités sur la carte", str _units],
    ["info", "Ordres reçus", str _orders],
    ["section", "Terminal", ""],
    ["info", "Batterie", _live get "stBat", "stBat"],
    // Mémoire vive simulée (fn_ramUsage) : total, puis la part de chaque poste.
    ["info", "Mémoire vive", ([] call comspec_atak_native_fnc_ramUsage) call { format ["<t color='%1'>%2 / %3 Mo (%4 %5)</t>", switch (true) do { case ((_this get "pct") >= 90): { (COMSPEC_ATAK_Th get "hexAlert") }; case ((_this get "pct") >= 75): { (COMSPEC_ATAK_Th get "hexWarn") }; default { (COMSPEC_ATAK_Th get "hexOk") }; }, _this get "used", _this get "total", _this get "pct", "%"] }],
    ["text", format ["<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1</t>", ((missionNamespace getVariable ["COMSPEC_ATAK_Ram", createHashMap]) getOrDefault ["parts", []]) apply { format ["%1 : %2 Mo", _x select 0, _x select 1] } joinString " · "]],
    ["info", "Version du mod", ["COMSPEC_ATAK_NativeBuild", "?"] call _str],
    ["info", "Extension", ["DLL native", "DLL COMSPEC Link embarquée"] select _bridge],
    ["buttons", [
        ["RESYNCH", { ["RESYNCH"] call comspec_atak_native_fnc_navigate; }, true],
        ["ATHENA", { ["ATHENA"] call comspec_atak_native_fnc_navigate; }],
        ["DEBUG", { ["DEBUG"] call comspec_atak_native_fnc_navigate; }]
    ]]
];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
// Minuterie de la page (une seule) : valeurs vivantes chaque seconde, arrêtée dès que la page n'est plus affichée ou que le
// téléphone est fermé.
if (isNil { uiNamespace getVariable "COMSPEC_ATAK_StatusLive" }) then {
    uiNamespace setVariable ["COMSPEC_ATAK_StatusLive", [{
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if (isNull ([] call comspec_atak_native_fnc_display) || {(_st getOrDefault ["activePage", ""]) isNotEqualTo "STATUS"} || {!(["live"] call comspec_atak_native_fnc_pageStatus)}) exitWith {
            [_this select 1] call CBA_fnc_removePerFrameHandler;
            uiNamespace setVariable ["COMSPEC_ATAK_StatusLive", nil];
        };
    }, 1] call CBA_fnc_addPerFrameHandler];
};
true
