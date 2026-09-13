/*
    Préremplit le dialog d’indicatif / rôle.
*/
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_Callsign_Display", displayNull];
if (isNull _display) exitWith {};

private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_cs isEqualTo (name player)) then {
    private _saved = trim (profileNamespace getVariable ["COMSPEC_Callsign", ""]);
    if (!(_saved isEqualTo "")) then { _cs = _saved; };
};

private _edit = _display displayCtrl 9301;
if (!isNull _edit) then { _edit ctrlSetText _cs; };

private _role = [player] call comspec_overwatch_connect_fnc_getUnitRole;
private _roleEdit = _display displayCtrl 9305;
if (!isNull _roleEdit) then { _roleEdit ctrlSetText _role; };

private _status = _display displayCtrl 9302;
if (!isNull _status) then {
    _status ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#6a7c90'>Ces informations apparaissent sur la carte et dans les effectifs de l’équipe.</t>";
};
