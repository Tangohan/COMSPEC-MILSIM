/*
    Remplit (ou rafraîchit) la fenêtre Dépannage liaison : verdict, contrôles avec cause et action,
    correction proposée en vert, bloc pas à pas. Rappelé par fn_diagAction (Revérifier, après une correction).
*/
params [["_display", uiNamespace getVariable ["COMSPEC_DiagIsolate_Display", displayNull]]];
disableSerialization;
if (isNull _display) exitWith {};
uiNamespace setVariable ["COMSPEC_DiagIsolate_Display", _display];

private _T = { _this call comspec_overwatch_connect_fnc_tr };
private _col = createHashMapFromArray [
    ["ok", "#5CC76B"], ["info", "#5994B8"], ["warn", "#F2AB33"], ["ko", "#E04038"]
];
private _rgba = createHashMapFromArray [
    ["ok", [0.36, 0.78, 0.42, 1]], ["info", [0.35, 0.58, 0.72, 1]], ["warn", [0.95, 0.67, 0.20, 1]], ["ko", [0.88, 0.25, 0.22, 1]]
];
private _tag = createHashMapFromArray [
    ["ok", "OK"], ["info", "INFO"], ["warn", "ATTENTION" call _T], ["ko", "PROBLÈME" call _T]
];

([] call comspec_overwatch_connect_fnc_diagChecks) params ["_rows", "_verdict", "_fix"];
uiNamespace setVariable ["COMSPEC_DiagLastChecks", [_rows, _verdict, diag_tickTime]];

(_display displayCtrl 30) ctrlSetStructuredText parseText ((format [
    "<t font='PuristaSemibold' size='1.25' color='#E6F0E8'>%1</t><br/><t font='PuristaMedium' size='0.75' color='#94A399'>%2</t>",
    "Dépannage liaison" call _T,
    "COMSPEC Link · Athena · ATAK"
]) call comspec_overwatch_connect_fnc_tr);

_verdict params ["_vLvl", "_vTxt"];
(_display displayCtrl 31) ctrlSetStructuredText parseText ((format [
    "<t align='right' font='PuristaMedium' size='0.7' color='#94A399'>%1</t><br/><t align='right' font='PuristaSemibold' size='1.1' color='%2'>%3</t>",
    "ÉTAT" call _T, _col get _vLvl, _vTxt
]) call comspec_overwatch_connect_fnc_tr);
(_display displayCtrl 32) ctrlSetBackgroundColor (_rgba get _vLvl);

// Contrôles : problèmes d'abord, puis avertissements, puis le reste.
private _sorted = [];
{
    private _lvl = _x;
    _sorted append (_rows select { (_x select 0) isEqualTo _lvl });
} forEach ["ko", "warn", "info", "ok"];
// Textes venus de la DLL ou du site : & < > échappés pour parseText.
private _esc = {
    if ((_this find "&") < 0 && {(_this find "<") < 0} && {(_this find ">") < 0}) exitWith { _this };
    ((toArray _this) apply { switch (_x) do { case 38: { "&amp;" }; case 60: { "&lt;" }; case 62: { "&gt;" }; default { toString [_x] }; } }) joinString ""
};
private _html = "";
{
    _x params ["_lvl", "_title", "_value", "_hint"];
    _value = _value call _esc;
    _hint = _hint call _esc;
    if (_html isNotEqualTo "") then { _html = _html + "<br/>"; };
    _html = _html + format [
        "<t font='PuristaSemibold' size='0.78' color='%1'>%2</t>  <t font='PuristaSemibold' size='0.92' color='#E6F0E8'>%3</t>  <t font='PuristaMedium' size='0.88' color='#94A399'>%4</t>",
        _col get _lvl, _tag get _lvl, _title, _value
    ];
    if (_hint isNotEqualTo "") then {
        _html = _html + format ["<br/><t font='PuristaLight' size='0.8' color='#C8D2CA'>%1</t>", _hint];
    };
    if (_lvl in ["ko", "warn"] && {_forEachIndex < ((count _sorted) - 1)}) then { _html = _html + "<br/>"; };
} forEach _sorted;
private _body = _display displayCtrl 41;
_body ctrlSetStructuredText parseText ((_html) call comspec_overwatch_connect_fnc_tr);
private _p = ctrlPosition _body;
_p set [3, (ctrlTextHeight _body) max ((ctrlPosition (_display displayCtrl 40)) select 3)];
_body ctrlSetPosition _p;
_body ctrlCommit 0;

// Bloc pas à pas.
private _run = missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false];
private _isoTxt = if (_run) then {
    format [
        "<t font='PuristaSemibold' size='0.85' color='#F2AB33'>%1</t><br/><t font='PuristaLight' size='0.72' color='#C8D2CA'>%2</t>",
        (format ["Pas à pas : étape %1 / %2", (missionNamespace getVariable ["COMSPEC_DiagIsolateHudIndex", 0]) + 1, missionNamespace getVariable ["COMSPEC_DiagIsolateHudTotal", 1]]) call _T,
        (missionNamespace getVariable ["COMSPEC_DiagIsolateHudLabel", ""]) call _T
    ]
} else {
    format [
        "<t font='PuristaSemibold' size='0.85' color='#E6F0E8'>%1</t><br/><t font='PuristaLight' size='0.72' color='#C8D2CA'>%2</t>",
        "Le jeu se fige ou se ferme ?" call _T,
        "Le pas à pas coupe Overwatch puis rallume chaque fonction une par une : si le jeu s'arrête, la fonction affichée est en cause." call _T
    ]
};
(_display displayCtrl 50) ctrlSetStructuredText parseText ((_isoTxt) call comspec_overwatch_connect_fnc_tr);
{ (_display displayCtrl _x) ctrlShow !_run; } forEach [51, 52];
(_display displayCtrl 53) ctrlShow _run;

// Corrections : utiles seulement dans certains états ; la proposée passe en vert.
private _mode = [] call comspec_overwatch_connect_fnc_athenaMode;
private _enabled = missionNamespace getVariable ["comspec_overwatch_enabled", true];
(_display displayCtrl 61) ctrlEnable (_enabled && {_mode isNotEqualTo "off"} && {!_run});
(_display displayCtrl 62) ctrlEnable (_enabled && {_mode isNotEqualTo "off"});
(_display displayCtrl 63) ctrlEnable (!_enabled && {!_run});
(_display displayCtrl 64) ctrlEnable true;
private _fixIdc = createHashMapFromArray [["reconnect", 61], ["link", 62], ["enable", 63], ["athena", 64], ["bug", 67]];
{
    private _c = _display displayCtrl _x;
    if (_fixIdc getOrDefault [_fix, -1] isEqualTo _x) then {
        _c ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.96];
        _c ctrlSetTextColor [0.025, 0.030, 0.027, 1];
    } else {
        _c ctrlSetBackgroundColor [0.070, 0.082, 0.072, 0.96];
        _c ctrlSetTextColor [0.90, 0.94, 0.91, 1];
    };
} forEach [61, 62, 63, 64, 67];
