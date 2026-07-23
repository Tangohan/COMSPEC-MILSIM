/*
    Contacts ISR Drone Ops (Iceman) → pings Athena.
    Lit Iceman_ATAK_DroneOps_state.contacts sans modifier le mod Iceman.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
if (!(["drone"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};

private _state = missionNamespace getVariable ["Iceman_ATAK_DroneOps_state", createHashMap];
if (!(_state isEqualType createHashMap)) exitWith {};
private _contacts = _state getOrDefault ["contacts", []];
if (!(_contacts isEqualType []) || {(count _contacts) == 0}) exitWith {};

private _seen = missionNamespace getVariable ["COMSPEC_Athena_DroneContactSeen", createHashMap];
if (!(_seen isEqualType createHashMap)) then { _seen = createHashMap; };

private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_cs isEqualTo "") then { _cs = name player; };

{
    if (!(_x isEqualType [])) then { continue };
    if ((count _x) < 4) then { continue };
    private _netId = _x select 0;
    private _label = _x select 1;
    private _pos = _x select 2;
    private _kind = toUpper (str (_x select 3));
    if (_netId isEqualTo "" || {_label isEqualTo ""}) then { continue };
    if (_seen getOrDefault [_netId, false]) then { continue };
    if (!(_pos isEqualType []) || {(count _pos) < 2}) then { continue };

    _seen set [_netId, true];
    private _msg = format ["[DRONE %1] %2", _kind, _label];
    "COMSPECExtension" callExtension [
        "SendPing",
        [_cs, str (_pos select 0), str (_pos select 1), _msg]
    ];
    [format ["Contact ISR → Athena · %1", _label]] call comspec_overwatch_connect_fnc_appendModuleLog;
} forEach _contacts;

missionNamespace setVariable ["COMSPEC_Athena_DroneContactSeen", _seen, false];
