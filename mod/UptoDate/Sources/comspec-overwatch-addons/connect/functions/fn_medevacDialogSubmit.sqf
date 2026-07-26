/*
    Envoie une demande MEDEVAC depuis le mini-formulaire (nombre, gravité, grille, notes).
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_Medevac_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9987; };
if (isNull _disp) exitWith {};

private _countRaw = trim (ctrlText (_disp displayCtrl 9601));
private _gridRaw = trim (ctrlText (_disp displayCtrl 9603));
private _notes = trim (ctrlText (_disp displayCtrl 9604));

private _combo = _disp displayCtrl 9602;
private _sevIdx = lbCurSel _combo;
private _severity = if (_sevIdx < 0) then { "URGENT" } else { _combo lbData _sevIdx };
if (_severity isEqualTo "") then { _severity = "URGENT"; };

if (_countRaw isEqualTo "") exitWith {
    ["Indiquez le nombre de blessés à évacuer.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

private _count = floor (parseNumber _countRaw);
if (_count < 1) exitWith {
    ["Le nombre de blessés doit être au moins 1.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

private _t1 = 0;
private _t2 = 0;
private _t3 = 0;
switch (toUpper _severity) do {
    case "PRIORITY": { _t2 = _count; };
    case "ROUTINE";
    case "CONVENIENCE": { _t3 = _count; };
    default { _t1 = _count; };
};

private _pickupPos = getPosWorld player;
if (_gridRaw isNotEqualTo "") then {
    // La grille affichée est informative ; la position joueur reste la référence LZ.
    _pickupPos = getPosWorld player;
};

private _security = "POSSIBLE_ENEMY";
private _lzMarking = "SMOKE";
private _lzColor = "GREEN";

private _ok = [
    toUpper _severity,
    _t1,
    _t2,
    _t3,
    _security,
    _lzMarking,
    _lzColor,
    _pickupPos,
    _notes,
    _gridRaw
] call comspec_overwatch_connect_fnc_requestMEDEVAC;

if (!_ok) exitWith {};

if (!isNull _disp) then {
    _disp closeDisplay 1;
} else {
    closeDialog 0;
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updatePanel") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
