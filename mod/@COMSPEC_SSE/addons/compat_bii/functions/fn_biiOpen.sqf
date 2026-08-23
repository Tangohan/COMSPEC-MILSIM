/*
    Ouvre BII-10 Identifi (SEEK terrain) sur un onglet donné.
    [_tab] call comspec_sse_fnc_biiOpen
    _tab: scan|sse|db|watch|leads|case|sync|builder
*/
params [["_tab", "scan", [""]]];

if (!hasInterface) exitWith { false };
if !([] call comspec_sse_fnc_biiIsPresent) exitWith { false };
if (isNil "BII_fnc_identifi_open") exitWith { false };

_tab = toLower _tab;
if !(_tab in ["scan", "sse", "db", "watch", "leads", "case", "sync", "builder"]) then {
    _tab = "scan";
};

if (!isNil "BII_fnc_identifi_hasDevice" && {!([player] call BII_fnc_identifi_hasDevice)}) exitWith {
    false
};

// Aligner la cible BII sur le curseur / dernière cible SEEK
private _target = cursorObject;
if (isNull _target) then { _target = cursorTarget; };
if (isNull _target || {!(_target isKindOf "CAManBase")}) then {
    _target = missionNamespace getVariable ["comspec_sse_seekTarget", objNull];
};
if (!isNull _target && {_target isKindOf "CAManBase"} && {!isNil "BII_fnc_identifi_getState"}) then {
    private _state = call BII_fnc_identifi_getState;
    if (_state isEqualType createHashMap) then {
        _state set ["lastTarget", _target];
    };
};

missionNamespace setVariable ["comspec_sse_biiPendingTab", _tab];
call BII_fnc_identifi_open;

private _disp = uiNamespace getVariable ["BII_Identifi_Dialog", displayNull];
if (isNull _disp) then { _disp = findDisplay 861010; };
if (isNull _disp) exitWith { false };

if (!isNil "BII_fnc_identifi_setTab") then {
    [{
        if (isNull (uiNamespace getVariable ["BII_Identifi_Dialog", displayNull])) exitWith {};
        private _tab = missionNamespace getVariable ["comspec_sse_biiPendingTab", "scan"];
        missionNamespace setVariable ["comspec_sse_biiPendingTab", nil];
        [_tab] call BII_fnc_identifi_setTab;
    }, [], 0.08] call CBA_fnc_waitAndExecute;
};

true
