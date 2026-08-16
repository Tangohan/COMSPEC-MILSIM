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

private _intelValue = _o getOrDefault ["INTEL_VALUE", -1];
if (_intelValue < 0) then { _intelValue = _o getOrDefault ["intelValue", 40]; };
private _timeSens = _o getOrDefault ["TIME_SENSITIVITY", -1];
if (_timeSens < 0) then { _timeSens = _o getOrDefault ["timeSensitivity", 20]; };
private _conf = _o getOrDefault ["CONFIDENCE", -1];
if (_conf < 0) then { _conf = _o getOrDefault ["confidence", 50]; };
private _rel = _o getOrDefault ["RELEVANCE", -1];
if (_rel < 0) then { _rel = _o getOrDefault ["relevance", 50]; };
private _tags = _o getOrDefault ["tags", []];
private _sources = _o getOrDefault ["sources", []];
private _contradicts = _o getOrDefault ["contradicts", []];

createHashMapFromArray [
    ["id", _id],
    ["text", _text],
    ["INTEL_VALUE", _intelValue],
    ["TIME_SENSITIVITY", _timeSens],
    ["CONFIDENCE", _conf],
    ["RELEVANCE", _rel],
    ["confidenceKind", _o getOrDefault ["confidenceKind", "EXTRACTED"]], // OBSERVED | EXTRACTED | PROBABLE | HYPOTHESIS
    ["discoveryState", _o getOrDefault ["discoveryState", "KNOWN"]], // KNOWN | ASSESSED | CONFIRMED | DISPROVEN
    ["tags", _tags],
    ["triage", _o getOrDefault ["triage", "UNKNOWN"]], // EXPLOIT_NOW | COLLECT | DOCUMENT_ONLY | LOW_VALUE | UNKNOWN
    ["levelRequired", _o getOrDefault ["levelRequired", "TACTICAL"]], // TACTICAL | FIELD | DETAILED | FUSION
    ["sources", _sources],
    ["expiresAt", _o getOrDefault ["expiresAt", -1]],
    ["contradicts", _contradicts],
    ["falsePositive", _o getOrDefault ["falsePositive", false]],
    ["actionable", _o getOrDefault ["actionable", false]],
    ["hookId", _o getOrDefault ["hookId", ""]],
    ["createdAt", time]
]
