/*
    [_intelId] call comspec_sse_fnc_isDiscovered
*/
params [
    ["_intelId", "", [""]]
];
if (_intelId == "") exitWith { false };
if (isNil "comspec_sse_discoveryStates") exitWith { false };
_intelId in comspec_sse_discoveryStates
