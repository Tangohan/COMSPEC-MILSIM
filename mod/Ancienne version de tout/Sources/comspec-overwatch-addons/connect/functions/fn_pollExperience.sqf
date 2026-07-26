/*
    Poll l’expérience communauté depuis Athena → COMSPEC_TenantExperience.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};

private _raw = ["COMSPECExtension" callExtension ["GetExperience", []]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {};

private _payload = _raw select [3, count _raw - 3];
private _last = missionNamespace getVariable ["COMSPEC_TenantExperienceRaw", ""];
if (_payload isEqualTo _last) exitWith {};
missionNamespace setVariable ["COMSPEC_TenantExperienceRaw", _payload, false];

private _nl = toString [10];
private _lines = if (_payload isEqualTo "") then { [] } else { _payload splitString _nl };
private _map = createHashMap;

{
    private _line = trim _x;
    if (_line isEqualTo "") then { continue };
    private _parts = _line splitString toString [9];
    if ((count _parts) < 2) then { continue };
    private _key = _parts select 0;
    private _val = _parts select 1;
    if (_key isEqualTo "guide") then {
        _val = (_val splitString "§NL§") joinString toString [10];
    };
    _map set [_key, _val];
} forEach _lines;

missionNamespace setVariable ["COMSPEC_TenantExperience", _map, false];
[] call comspec_overwatch_connect_fnc_applyTenantExperience;

if (_map getOrDefault ["guide", ""] isNotEqualTo "") then {
    [] spawn comspec_overwatch_connect_fnc_showExperienceGuide;
};

private _realism = (_map getOrDefault ["realism", "0"]) isEqualTo "1";
private _troll = (_map getOrDefault ["troll", "0"]) isEqualTo "1";
[format [
    "Expérience communauté · réalisme %1 · troll %2",
    if (_realism) then { "actif" } else { "inactif" },
    if (_troll) then { "actif" } else { "inactif" }
]] call comspec_overwatch_connect_fnc_appendLinkLog;
