params [
    ["_intelId", "", [""]]
];
if (_intelId == "" || {isNil "comspec_sse_discoveryStates"}) exitWith { "UNKNOWN" };
private _rec = comspec_sse_discoveryStates getOrDefault [_intelId, createHashMap];
if (_rec isEqualType createHashMap) then {
    _rec getOrDefault ["discoveryState", "KNOWN"]
} else {
    "KNOWN"
}
