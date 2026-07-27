/*
    Enregistre ou met à jour le terminal ATAK côté Athena.
    Params: [_pairingToken]
    Retour : [terminalId, terminalUid, status] ou []
*/
params [["_pairingToken", "", [""]]];

if (!hasInterface) exitWith { [] };
if ((missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isNotEqualTo "linked") exitWith { [] };

private _terminalUid = [] call comspec_overwatch_connect_fnc_getTerminalUid;
if (_terminalUid isEqualTo "") exitWith { [] };

private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_callsign isEqualTo "") then { _callsign = name player; };

private _label = format ["Terminal %1", _callsign];
private _modVersion = [] call comspec_overwatch_connect_fnc_getModVersion;
private _platform = format ["Arma 3 · COMSPEC %1", _modVersion];

private _raw = ["COMSPECExtension" callExtension [
    "RegisterTerminal",
    [_terminalUid, _label, "tablet", _callsign, _pairingToken, "active", _platform]
]] call comspec_overwatch_connect_fnc_extResult;

private _parts = _raw splitString "|";
if ((count _parts) < 2 || {(_parts select 0) isNotEqualTo "OK"}) exitWith {
    private _code = if (count _parts >= 2) then { _parts select 1 } else { _raw };
    missionNamespace setVariable [
        "COMSPEC_AtakRealismLastError",
        [_code, "Impossible d’enregistrer le terminal — réessayez."] call comspec_overwatch_connect_fnc_realismErrorMessage,
        false
    ];
    diag_log format ["[COMSPEC] RegisterTerminal échec : %1", _raw];
    []
};

private _cols = (_parts select 1) splitString (toString [9]);
if (count _cols < 2) exitWith { [] };

private _terminalId = _cols select 0;
private _uid = _cols select 1;
private _status = if (count _cols >= 3) then { _cols select 2 } else { "active" };

missionNamespace setVariable ["COMSPEC_TerminalId", _terminalId, false];
missionNamespace setVariable ["COMSPEC_TerminalUid", _uid, false];
missionNamespace setVariable ["COMSPEC_TerminalStatus", _status, false];

[_terminalId, _uid, _status]
