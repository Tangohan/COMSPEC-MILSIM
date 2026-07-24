/*
    Enregistre l’indicatif et le rôle saisis dans le dialog.
*/
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_Callsign_Display", displayNull];
if (isNull _display) exitWith {};

private _edit = _display displayCtrl 9301;
private _roleEdit = _display displayCtrl 9305;
private _status = _display displayCtrl 9302;
private _cs = if (!isNull _edit) then { trim (ctrlText _edit) } else { "" };
private _role = if (!isNull _roleEdit) then { trim (ctrlText _roleEdit) } else { "" };

if (_cs isEqualTo "") exitWith {
    if (!isNull _status) then {
        _status ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#ff8a7a'>Indiquez un indicatif (ex. ALPHA-1).</t>";
    };
};

[_cs, true, "dialog"] call comspec_overwatch_connect_fnc_setCallsign;
if (!(_role isEqualTo "")) then {
    [_role, true] call comspec_overwatch_connect_fnc_setUnitRole;
};

private _msg = if (_role isEqualTo "") then {
    format ["Callsign registered : %1", _cs]
} else {
    format ["Identity registered : %1 · %2", _cs, _role]
};
["COMSPEC_Info", [_msg]] call comspec_overwatch_connect_fnc_showNotification;

// Best-effort : si lié, tenter aussi de récupérer / aligner avec Athena
0 spawn {
    uiSleep 0.2;
    [false] call comspec_overwatch_connect_fnc_syncCallsignFromAthena;
};

closeDialog 0;
