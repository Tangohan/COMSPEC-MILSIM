/*
    Envoie un SALUTE structuré depuis le dialog (champs S/A/L/U/T/E).
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_Salute_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9993; };
if (isNull _disp) exitWith {};

private _s = trim (ctrlText (_disp displayCtrl 9401));
private _a = trim (ctrlText (_disp displayCtrl 9402));
private _l = trim (ctrlText (_disp displayCtrl 9403));
private _u = trim (ctrlText (_disp displayCtrl 9404));
private _t = trim (ctrlText (_disp displayCtrl 9405));
private _e = trim (ctrlText (_disp displayCtrl 9406));

private _parts = [];
{
    _x params ["_key", "_val"];
    if (_val isNotEqualTo "") then {
        _parts pushBack format ["%1=%2", _key, _val];
    };
} forEach [
    ["S", _s],
    ["A", _a],
    ["L", _l],
    ["U", _u],
    ["T", _t],
    ["E", _e]
];

if ((count _parts) < 1) exitWith {
    ["Renseignez au moins un champ du compte rendu SALUTE.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

private _body = _parts joinString "|";
["SALUTE", _body, getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert;

if (!isNull _disp) then {
    _disp closeDisplay 1;
} else {
    closeDialog 0;
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updatePanel") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
