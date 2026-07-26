/*
    Envoie une demande d’appui aérien depuis le mini-formulaire.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_CasRequest_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9988; };
if (isNull _disp) exitWith {};

private _combo = _disp displayCtrl 9701;
private _typeIdx = lbCurSel _combo;
private _casType = if (_typeIdx < 0) then { "CAS" } else { _combo lbData _typeIdx };
if (_casType isEqualTo "") then { _casType = "CAS"; };

private _gridRaw = trim (ctrlText (_disp displayCtrl 9702));
private _note = trim (ctrlText (_disp displayCtrl 9703));

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
    format ["Demande %1 — grille %2", _typeLabel, _grid]
} else {
    format ["%1 — %2 — grille %3", _typeLabel, _note, _grid]
};

["CAS", _targetName, _body, "URGENT", "", _targetType] call comspec_overwatch_connect_fnc_issueOrder;
["Demande d’appui aérien transmise.", "order", "info"] call comspec_overwatch_connect_fnc_announce;

if (!isNull _disp) then {
    _disp closeDisplay 1;
} else {
    closeDialog 0;
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updatePanel") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
