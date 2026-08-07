/*
    Vue « ce que les joueurs savent » vs vérité (spoil Zeus).
    [_entity] call comspec_sse_fnc_getPlayerKnownView
*/
params [
    ["_entity", objNull, [objNull]]
];

private _known = [_entity] call comspec_sse_fnc_getRevealedIntel;
private _sec = [_entity, "sections"] call comspec_sse_fnc_getSection;
private _all = [];
if (!isNil "_sec" && {_sec isEqualType createHashMap}) then {
    private _layers = _sec getOrDefault ["intelLayers", createHashMap];
    if (_layers isEqualType createHashMap) then {
        { { _all pushBack _x } forEach (_layers getOrDefault [_x, []]); } forEach ["TACTICAL", "FIELD", "DETAILED", "FUSION"];
    };
};

createHashMapFromArray [
    ["known", _known],
    ["truth", _all],
    ["level", [_entity] call comspec_sse_fnc_getExploitationLevel],
    ["knownCount", count _known],
    ["truthCount", count _all],
    ["fog", createHashMapFromArray [
        ["KNOWN", count (_known select { (_x getOrDefault ["discoveryState", "KNOWN"]) == "KNOWN" })],
        ["ASSESSED", count (_known select { (_x getOrDefault ["discoveryState", ""]) == "ASSESSED" })],
        ["CONFIRMED", count (_known select { (_x getOrDefault ["discoveryState", ""]) == "CONFIRMED" })],
        ["DISPROVEN", count (_known select { (_x getOrDefault ["discoveryState", ""]) == "DISPROVEN" })]
    ]]
]
