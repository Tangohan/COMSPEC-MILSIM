/*
    Fusionne plusieurs sources confirmant la même donnée → boost CONFIDENCE.
    [_intelIdOrText, _sourceIds] call comspec_sse_fnc_fuseIntel
*/
params [
    ["_key", "", [""]],
    ["_sourceIds", [], [[]]]
];

if (_key == "") exitWith { createHashMap };

private _state = comspec_sse_discoveryStates getOrDefault [_key, createHashMap];
if !(_state isEqualType createHashMap) then { _state = createHashMap; };

private _sources = _state getOrDefault ["sources", []];
{ _sources pushBackUnique _x } forEach _sourceIds;
_state set ["sources", _sources];

private _conf = 40 + ((count _sources) * 15);
if (_conf > 95) then { _conf = 95; };
_state set ["CONFIDENCE", _conf];
_state set ["discoveryState", if (count _sources >= 2) then {"CONFIRMED"} else {"ASSESSED"}];
_state set ["updatedAt", time];

comspec_sse_discoveryStates set [_key, _state];
["SSE_FusionUpdated", [_key, _state]] call comspec_sse_fnc_emitEvent;
_state
