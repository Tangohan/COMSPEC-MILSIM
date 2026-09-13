/*
    Pousse une alerte médicale (inconscient / arrêt cardiaque / hors combat)
    dans la liste PANIC du téléphone ATAK IceMan, pour localisation.

    IceMan n’accepte dans ce panneau que PANIC / EAGLE_DOWN : on envoie
    EAGLE_DOWN avec un intitulé métier (INCONSCIENT, KIA, …).

    Params: [_kindNorm, _sender, _pos, _callsign, _label, _broadcast]
      _broadcast true (origine) : diffusion à tous les clients.
      _broadcast false : application locale (EH distant ou relais Athena).
*/
params [
    ["_kindNorm", "", [""]],
    ["_sender", objNull, [objNull]],
    ["_pos", [], [[]]],
    ["_callsign", "", [""]],
    ["_label", "", [""]],
    ["_broadcast", true, [true]]
];

if (!hasInterface) exitWith { false };

private _kindKey = toLower (trim _kindNorm);
if (_kindKey in ["death", "dead", "killed", "mort"]) then { _kindKey = "kia"; };
if !(_kindKey in ["unconscious", "cardiac_arrest", "kia"]) exitWith { false };

private _from = trim _callsign;
if (_from isEqualTo "") then {
    _from = if (!isNull _sender) then { name _sender } else { "Inconnu" };
};

private _dedupe = toLower format ["%1|%2", _from, _kindKey];
private _seen = missionNamespace getVariable ["COMSPEC_IcemanMedicalPushed", createHashMap];
if (!(_seen isEqualType createHashMap)) then { _seen = createHashMap; };
private _prev = _seen getOrDefault [_dedupe, -1e9];
if ((diag_tickTime - _prev) < 90) exitWith { false };

if (_broadcast) exitWith {
    ["COMSPEC_IcemanMedicalPanic", [_kindKey, _sender, _pos, _callsign, _label]] call CBA_fnc_globalEvent;
    true
};

private _kindText = switch (_kindKey) do {
    case "cardiac_arrest": { "ARRET CARDIAQUE" };
    case "kia": { "KIA" };
    default { "INCONSCIENT" };
};

if ((count _pos) < 2 && {!isNull _sender}) then { _pos = getPos _sender; };
if ((count _pos) < 2) exitWith { false };

private _grid = mapGridPosition _pos;
_seen set [_dedupe, diag_tickTime];
missionNamespace setVariable ["COMSPEC_IcemanMedicalPushed", _seen, false];

private _time = if (!isNil "cTab_fnc_currentTime") then {
    call cTab_fnc_currentTime
} else {
    [daytime, "HH:MM"] call BIS_fnc_timeToString
};

private _msgBody = format [
    "%1<br/>From: %2<br/>Grid: %3<br/>Time: %4<br/><br/>%5",
    _kindText,
    _from,
    _grid,
    _time,
    if (_label isEqualTo "") then { "Assistance médicale" } else { _label }
];

if (!isNil "Iceman_fnc_alerts_receive") exitWith {
    ["EAGLE_DOWN", _sender, _pos, _msgBody, _time, _kindText] call Iceman_fnc_alerts_receive;
    true
};

private _panics = +(missionNamespace getVariable ["Iceman_ATAK_Panic_reports", []]);
if (!(_panics isEqualType [])) then { _panics = []; };
_panics pushBack [_time, _kindText, _from, _grid, _msgBody, _pos];
while {(count _panics) > 50} do { _panics deleteAt 0; };
Iceman_ATAK_Panic_reports = _panics;
Iceman_ATAK_Panic_selected = (count _panics) - 1;
if (!isNil "Iceman_fnc_panic_updatePanel") then {
    call Iceman_fnc_panic_updatePanel;
};
true
