/*
    Pont BDA Iceman → Athena (canal ALERTE TACTIQUE|BDA|…).
    Payload Iceman : [_sender, _pos, _bodyHtml, _time]
*/
params ["_sender", "_pos", ["_bodyHtml", ""], ["_time", ""]];

if (!hasInterface) exitWith {};
if (!(["iceman_bda"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};
if (missionNamespace getVariable ["COMSPEC_AthenaBridge_SuppressMirror", false]) exitWith {};

private _from = if (isNull _sender) then { "—" } else { name _sender };
private _grid = if ((count _pos) >= 2) then { mapGridPosition _pos } else { "" };
private _timeStr = if (_time isEqualTo "") then { [daytime, "HH:MM"] call BIS_fnc_timeToString } else { _time };
private _summary = _bodyHtml;
_summary = [_summary, "<br/>", " · "] call BIS_fnc_replaceString;
_summary = [_summary, "<br>", " · "] call BIS_fnc_replaceString;
_summary = [_summary, "<t color='#ffd36a'>BDA REPORT</t>", "BDA"] call BIS_fnc_replaceString;
// Nettoyer balises HTML restantes + pipe (sinon ALERTE TACTIQUE|… est tronqué).
_summary = (_summary splitString "<>") joinString "";
_summary = (_summary splitString "|") joinString " · ";
_summary = trim _summary;

private _isLocal = !isNull _sender && { _sender isEqualTo player };

if (_isLocal) then {
    if (isNil "comspec_overwatch_connect_fnc_sendTacticalAlert") exitWith {};
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", true, false];
    ["BDA", _summary, if ((count _pos) >= 2) then { _pos } else { getPos player }] call comspec_overwatch_connect_fnc_sendTacticalAlert;
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", false, false];
    ["Bilan des degats envoye vers Athena"] call comspec_overwatch_connect_fnc_appendModuleLog;
    ["ATHENA", "Bilan des dégâts transmis à Athena.", 4] call comspec_overwatch_connect_fnc_addScreenToast;
} else {
    private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
    if (!(_inbox isEqualType [])) then { _inbox = []; };
    _inbox pushBack ["BDA", "Bilan des dégâts", _summary, _grid, _timeStr, _from];
    while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
    private _detail = format [
        "<t color='#e0a060'>Bilan des dégâts</t><br/><t color='#8aa0b4'>De</t>  %1<br/><t color='#8aa0b4'>Grille</t>  %2<br/><t color='#8aa0b4'>Heure</t>  %3<br/><br/>%4",
        _from,
        if (_grid isEqualTo "") then { "—" } else { _grid },
        _timeStr,
        _summary
    ];
    [
        "bda",
        "Bilan des dégâts",
        format ["%1 — %2", _from, if (_grid isEqualTo "") then { "—" } else { _grid }],
        _detail,
        format ["bda_%1_%2", _from, _timeStr],
        _timeStr
    ] call comspec_overwatch_atak_athena_fnc_athena_pushNotification;
    ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;
};
