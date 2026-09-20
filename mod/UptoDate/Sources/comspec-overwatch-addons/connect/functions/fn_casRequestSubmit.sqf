/*
    Envoie une demande d'appui aérien depuis le formulaire (ATAK ou overlay).
*/
if (!hasInterface) exitWith {};

private _combo = [9701] call comspec_overwatch_connect_fnc_casRequestCtrl;
if (isNull _combo) exitWith {};

private _typeIdx = lbCurSel _combo;
private _casType = if (_typeIdx < 0) then { "CAS" } else { _combo lbData _typeIdx };
if (_casType isEqualTo "") then { _casType = "CAS"; };

private _gridCtrl = [9702] call comspec_overwatch_connect_fnc_casRequestCtrl;
private _noteCtrl = [9703] call comspec_overwatch_connect_fnc_casRequestCtrl;
private _gridRaw = if (isNull _gridCtrl) then { "" } else { trim (ctrlText _gridCtrl) };
private _note = if (isNull _noteCtrl) then { "" } else { trim (ctrlText _noteCtrl) };

private _typeLabel = switch (toUpper _casType) do {
    case "RECON_AIR": { "Reconnaissance aérienne" };
    case "COVER": { "Couverture / survol" };
    case "EXTRACT": { "Extraction aérienne" };
    default { "Appui aérien" };
};

private _grid = if (_gridRaw isEqualTo "") then { mapGridPosition player } else { _gridRaw };

private _g = group player;
private _hasGroupLeader = !isNull leader _g;
private _targetName = if (_hasGroupLeader) then { groupId _g } else { name player };
private _targetType = if (_hasGroupLeader) then { "group" } else { "solo" };

private _body = if (_note isEqualTo "") then {
    format ["Demande %1 - grille %2", _typeLabel, _grid]
} else {
    format ["%1 - %2 - grille %3", _typeLabel, _note, _grid]
};

["CAS", _targetName, _body, "URGENT", "", _targetType] call comspec_overwatch_connect_fnc_issueOrder;
["Demande d'appui aérien transmise.", "order", "info"] call comspec_overwatch_connect_fnc_announce;

if (!isNull _noteCtrl) then { _noteCtrl ctrlSetText ""; };

private _disp = uiNamespace getVariable ["COMSPEC_CasRequest_Display", displayNull];
if (!isNull _disp) then {
    _disp closeDisplay 1;
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updatePanel") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
