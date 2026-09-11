/*
    Publie le dernier viewshed IceMan vers le poste (calque temporaire).
    Params optionnels : [_pos, _radiusM]
*/
params [["_pos", []], ["_radiusM", -1]];

if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };

if ((count _pos) < 2) then {
    private _state = missionNamespace getVariable ["Iceman_ATAK_Elevation_state", createHashMap];
    if (_state isEqualType createHashMap) then {
        _pos = _state getOrDefault ["viewshedPoint", []];
        if (_radiusM < 0) then {
            _radiusM = _state getOrDefault ["radiusM", 500];
        };
    };
};
if ((count _pos) < 2) exitWith { false };
if (_radiusM < 50) then { _radiusM = 500; };
if (_radiusM > 5000) then { _radiusM = 5000; };

private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_cs isEqualTo "") then { _cs = name player; };

"COMSPECExtension" callExtension [
    "PublishViewshed",
    [_cs, str (_pos select 0), str (_pos select 1), str (round _radiusM)]
];
true
