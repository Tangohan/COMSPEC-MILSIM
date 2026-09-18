/*
    Message → P2P réseau local : écran IceMan classique.
*/
if (!hasInterface) exitWith {};

missionNamespace setVariable ["COMSPEC_MessageHubOrigin", true, false];
if (!isNil "BCE_fnc_ATAK_setAPPs_props") then {
    private _apps = + (profileNamespace getVariable ["BCE_ATAK_APPs", []]);
    if (!(_apps isEqualType [])) then { _apps = []; };
    [_apps + ["AtakP2P"]] call BCE_fnc_ATAK_setAPPs_props;
};
["AtakP2P"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
[{
    ["p2p"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;
}, [], 0.15] call CBA_fnc_waitAndExecute;
