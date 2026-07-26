/*
    Dual-send + inbox : alerte Iceman.
    - Autre joueur → journal inbox Athena locale seulement.
    - Émetteur local → push Athena via sendTacticalAlert (qui journalise + event).
*/
params ["_kind", "_sender", "_pos", ["_msgBody", ""], ["_time", ""], ["_kindText", ""]];

if (!hasInterface) exitWith {};
if (!(["iceman_alerts"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};

// Miroir Athena→Iceman en cours : ne rien faire (anti-boucle)
if (missionNamespace getVariable ["COMSPEC_AthenaBridge_SuppressMirror", false]) exitWith {};

private _kindKey = toUpper _kind;
if (_kindKey in ["CLEAR", "TIC CLEAR"]) then { _kindKey = "TIC_CLEAR"; };
if (_kindKey in ["PANIC"]) then { _kindKey = "EAGLE_DOWN"; };

private _label = switch (_kindKey) do {
    case "TIC": { "Contact" };
    case "TIC_CLEAR": { "Fin de contact" };
    case "FRAGO": { "Ordre fragmentaire" };
    case "SALUTE": { "Compte rendu SALUTE" };
    case "EAGLE_DOWN": { "Opérateur à terre" };
    default { if (_kindText isEqualTo "") then { "Alerte" } else { _kindText } };
};

private _from = if (isNull _sender) then { "—" } else { name _sender };
private _grid = if ((count _pos) >= 2) then { mapGridPosition _pos } else { "" };
private _timeStr = if (_time isEqualTo "") then { [daytime, "HH:MM"] call BIS_fnc_timeToString } else { _time };
private _summary = _msgBody;
_summary = [_summary, "<br/>", " | "] call BIS_fnc_replaceString;
_summary = [_summary, "<br>", " | "] call BIS_fnc_replaceString;

private _isLocalSender = !isNull _sender && { _sender isEqualTo player };

if (_isLocalSender) then {
    if (isNil "comspec_overwatch_connect_fnc_sendTacticalAlert") exitWith {};
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", true, false];
    [_kindKey, _summary, if ((count _pos) >= 2) then { _pos } else { getPos player }] call comspec_overwatch_connect_fnc_sendTacticalAlert;
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", false, false];
    [format ["Alerte %1 envoyée", _label]] call comspec_overwatch_connect_fnc_appendModuleLog;
} else {
    private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
    if (!(_inbox isEqualType [])) then { _inbox = []; };
    _inbox pushBack [_kindKey, _label, _summary, _grid, _timeStr, _from];
    while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
    ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;
};
