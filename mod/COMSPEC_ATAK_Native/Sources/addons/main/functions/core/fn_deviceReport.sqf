/*
    Diagnostic matériel d'un téléphone : ce qui est cassé, en pourcentage.
    Params : [mode, état]
      "export" : état de mon téléphone en paires [[clé, valeur]...] (lu par un équipier en liaison NFC / câble,
                 fn_linkAllyAction « snapshot », et gardé dans la copie de sauvegarde) ;
      "rows"   : lignes de formulaire (fn_formRender) depuis un état exporté (vide : mon téléphone).
*/
params [["_mode", "rows"], ["_src", []]];
if (_mode isEqualTo "export") exitWith {
    private _hp = [] call comspec_atak_native_fnc_deviceHealth;
    private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
    private _p = _hp getOrDefault ["parts", createHashMap];
    private _r = { (round ((_this max 0 min 1) * 100)) / 100 };
    [
        ["state", _hp get "state"], ["reason", _hp get "reason"], ["damage", (_hp get "damage") call _r],
        ["scratch", (_p getOrDefault ["scratch", 0]) call _r], ["pixels", (_p getOrDefault ["pixels", 0]) call _r],
        ["battery", (_p getOrDefault ["battery", 0]) call _r], ["audio", (_p getOrDefault ["audio", 0]) call _r],
        ["gps", (_p getOrDefault ["gps", 0]) call _r], ["antenna", (_p getOrDefault ["antenna", 0]) call _r],
        ["dust", (_n getOrDefault ["dust", 0]) call _r], ["prints", (_n getOrDefault ["prints", 0]) call _r],
        ["blood", ((_n getOrDefault ["blood", 0]) max (_n getOrDefault ["frameBlood", 0])) call _r],
        ["level", round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100])],
        ["log", (_n getOrDefault ["log", []]) apply { format ["%1 · %2 : %3", _x select 0, _x select 1, _x select 2] }]
    ]
};
if ((count _src) isEqualTo 0) then { _src = ["export"] call comspec_atak_native_fnc_deviceReport; };
private _m = createHashMapFromArray _src;
private _g = { params ["_k"]; private _v = _m getOrDefault [_k, 0]; [0, _v] select (_v isEqualType 0) };
private _ok = "<t color='#5cc76b'>OK</t>";
private _col = { params ["_v", "_t"]; format ["<t color='%1'>%2</t>  <t color='#8a9a93'>%3 %%</t>", ["#f2ab33", "#e5483a"] select (_v >= 0.5), _t, round ((1 - _v) * 100)] };
private _state = _m getOrDefault ["state", "OK"];
private _dmg = ["damage"] call _g;
private _pix = ["pixels"] call _g;
private _scr = ["scratch"] call _g;
private _rows = [];
_rows pushBack ["info", "Appareil", switch (_state) do {
    case "BROKEN": { format ["<t color='#e5483a'>détruit</t>  <t color='#8a9a93'>%1</t>", _m getOrDefault ["reason", ""]] };
    case "OFF": { format ["<t color='#f2ab33'>éteint</t>  <t color='#8a9a93'>%1</t>", _m getOrDefault ["reason", ""]] };
    default { "<t color='#5cc76b'>en service</t>" };
}];
private _screen = switch (true) do {
    case (_dmg >= 1): { "en morceaux" };
    case (_dmg >= 0.75): { "en morceaux, zones mortes" };
    case (_dmg >= 0.45): { "très abîmé" };
    case (_dmg >= 0.2): { "fêlé" };
    case (_scr > 0): { "rayé" };
    default { "" };
};
if (_pix > 0) then { _screen = ([_screen + ", ", ""] select (_screen isEqualTo "")) + (["pixels morts", "lignes à l'écran"] select (_pix >= 0.5)); };
_rows pushBack ["info", "Écran", [[(_dmg max (_pix * 0.6) max (_scr * 0.3)), _screen] call _col, _ok] select (_screen isEqualTo "")];
{
    _x params ["_k", "_lbl", "_bad"];
    private _v = [_k] call _g;
    _rows pushBack ["info", _lbl, [[_v, [_v] call _bad] call _col, _ok] select (_v <= 0)];
} forEach [
    ["battery", "Batterie (cellule)", { params ["_v"]; format ["se vide %1 fois plus vite", (1 + 2 * _v) toFixed 1] }],
    ["audio", "Haut-parleur / micro", { params ["_v"]; ["grésille", "hors service"] select (_v >= 0.5) }],
    ["gps", "GPS", { params ["_v"]; [format ["imprécis (±%1 m)", round (15 + 85 * _v)], "hors service"] select (_v >= 1) }],
    ["antenna", "Antenne", { params ["_v"]; format ["signal -%1 %%", round (75 * _v)] }]
];
private _dirt = [];
if ((["dust"] call _g) > 0.1) then { _dirt pushBack "poussiéreux"; };
if ((["prints"] call _g) > 0.1) then { _dirt pushBack "traces de doigts"; };
if ((["blood"] call _g) > 0.05) then { _dirt pushBack "<t color='#e5483a'>taché de sang</t>"; };
_rows pushBack ["info", "Propreté", [_dirt joinString ", ", "propre"] select ((count _dirt) isEqualTo 0)];
private _log = _m getOrDefault ["log", []];
if (_log isEqualType [] && {(count _log) > 0}) then {
    _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>Derniers chocs : %1</t>", (_log apply { ["", _x] select (_x isEqualType "") }) joinString " | "]];
};
_rows
