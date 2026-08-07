params [
    ["_intelId", "", [""]],
    ["_state", "KNOWN", [""]]
];
if (_intelId == "") exitWith { false };
if (isNil "comspec_sse_discoveryStates") then { comspec_sse_discoveryStates = createHashMap; };
private _rec = comspec_sse_discoveryStates getOrDefault [_intelId, createHashMap];
if !(_rec isEqualType createHashMap) then { _rec = createHashMap; };
_rec set ["discoveryState", toUpper _state];
_rec set ["updatedAt", time];
comspec_sse_discoveryStates set [_intelId, _rec];
true
