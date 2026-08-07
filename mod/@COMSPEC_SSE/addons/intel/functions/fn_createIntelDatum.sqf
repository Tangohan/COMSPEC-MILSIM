/*
    Crée une donnée renseignement normalisée.
    [_text, _overrides] call comspec_sse_fnc_createIntelDatum

    Champs : INTEL_VALUE, TIME_SENSITIVITY, CONFIDENCE, RELEVANCE,
             confidenceKind, tags, triage, discoveryState, expiresAt, sources
*/
params [
    ["_text", "", [""]],
    ["_ov", createHashMap, [createHashMap, []]]
];

private _o = if (_ov isEqualType createHashMap) then { _ov } else {
    private _h = createHashMap;
    { _x params ["_k", "_v"]; _h set [_k, _v]; } forEach _ov;
    _h
};

private _id = format ["SSE-INTEL-%1", floor random 999999];
if ((_o getOrDefault ["id", ""]) != "") then { _id = _o get "id"; };

createHashMapFromArray [
    ["id", _id],
    ["text", _text],
    ["INTEL_VALUE", _o getOrDefault ["INTEL_VALUE", _o getOrDefault ["intelValue", 40]]],
    ["TIME_SENSITIVITY", _o getOrDefault ["TIME_SENSITIVITY", _o getOrDefault ["timeSensitivity", 20]]],
    ["CONFIDENCE", _o getOrDefault ["CONFIDENCE", _o getOrDefault ["confidence", 50]]],
    ["RELEVANCE", _o getOrDefault ["RELEVANCE", _o getOrDefault ["relevance", 50]]],
    ["confidenceKind", _o getOrDefault ["confidenceKind", "EXTRACTED"]], // OBSERVED | EXTRACTED | PROBABLE | HYPOTHESIS
    ["discoveryState", _o getOrDefault ["discoveryState", "KNOWN"]], // KNOWN | ASSESSED | CONFIRMED | DISPROVEN
    ["tags", _o getOrDefault ["tags", []]],
    ["triage", _o getOrDefault ["triage", "UNKNOWN"]], // EXPLOIT_NOW | COLLECT | DOCUMENT_ONLY | LOW_VALUE | UNKNOWN
    ["levelRequired", _o getOrDefault ["levelRequired", "TACTICAL"]], // TACTICAL | FIELD | DETAILED | FUSION
    ["sources", _o getOrDefault ["sources", []]],
    ["expiresAt", _o getOrDefault ["expiresAt", -1]],
    ["contradicts", _o getOrDefault ["contradicts", []]],
    ["falsePositive", _o getOrDefault ["falsePositive", false]],
    ["actionable", _o getOrDefault ["actionable", false]],
    ["hookId", _o getOrDefault ["hookId", ""]],
    ["createdAt", time]
]
