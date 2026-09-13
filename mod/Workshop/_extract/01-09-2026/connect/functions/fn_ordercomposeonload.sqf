/*
    Initialise les listes de la mini-fenêtre d’émission d’ordre / FRAGO.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_OrderCompose_Display", displayNull];
if (isNull _disp) exitWith {};

private _kinds = [
    ["MOVE", "Se déplacer"],
    ["HOLD", "Tenir la position"],
    ["RECON", "Reconnaissance"],
    ["CAS", "Appui aérien"],
    ["QRF", "Force de réaction"],
    ["FRAGO", "Ordre fragmentaire (FRAGO)"]
];
private _prios = [
    ["ROUTINE", "Routine"],
    ["IMPORTANT", "Important"],
    ["URGENT", "Urgent"],
    ["CONTACT", "Contact"]
];

private _comboKind = _disp displayCtrl 9502;
private _comboPrio = _disp displayCtrl 9503;
private _comboTarget = _disp displayCtrl 9504;
lbClear _comboKind;
lbClear _comboPrio;
lbClear _comboTarget;

{
    _x params ["_code", "_label"];
    private _i = _comboKind lbAdd _label;
    _comboKind lbSetData [_i, _code];
} forEach _kinds;

{
    _x params ["_code", "_label"];
    private _i = _comboPrio lbAdd _label;
    _comboPrio lbSetData [_i, _code];
} forEach _prios;
_comboPrio lbSetCurSel 1; // Important

private _g = group player;
private _gid = trim (groupId _g);
if (_gid isEqualTo "") then { _gid = name player; };

private _iAll = _comboTarget lbAdd "Toute l’équipe (diffusion)";
_comboTarget lbSetData [_iAll, "all||"];
private _iGrp = _comboTarget lbAdd format ["Mon groupe — %1", _gid];
_comboTarget lbSetData [_iGrp, format ["group|%1|%1", _gid]];
_comboTarget lbSetCurSel _iGrp;

{
    if (_x != player && {alive _x}) then {
        private _nm = name _x;
        private _ix = _comboTarget lbAdd format ["Opérateur — %1", _nm];
        _comboTarget lbSetData [_ix, format ["solo|%1|%1", _nm]];
    };
} forEach (units _g);

private _pref = toUpper (trim (missionNamespace getVariable ["COMSPEC_OrderCompose_PrefKind", ""]));
private _sel = 0;
for "_i" from 0 to ((lbSize _comboKind) - 1) do {
    if ((_comboKind lbData _i) isEqualTo _pref) exitWith { _sel = _i; };
};
_comboKind lbSetCurSel _sel;

[] call comspec_overwatch_connect_fnc_orderComposeRefreshMode;
[] call comspec_overwatch_connect_fnc_orderComposeRefreshLinkStatus;

// Rafraîchir l’état ATAK tant que la fenêtre est ouverte
private _token = diag_tickTime;
uiNamespace setVariable ["COMSPEC_OrderCompose_StatusToken", _token];
[_token] spawn {
    params ["_token"];
    while {
        (uiNamespace getVariable ["COMSPEC_OrderCompose_StatusToken", -1]) isEqualTo _token
        && {!isNull (uiNamespace getVariable ["COMSPEC_OrderCompose_Display", displayNull])}
    } do {
        uiSleep 3;
        [] call comspec_overwatch_connect_fnc_orderComposeRefreshLinkStatus;
    };
};
