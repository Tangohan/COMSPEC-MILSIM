/*
    Compare deux jeux biométriques (simulation).
    [_bioA, _bioB] call comspec_sse_fnc_compareBiometrics -> HashMap
*/
params [
    ["_bioA", createHashMap, [createHashMap]],
    ["_bioB", createHashMap, [createHashMap]]
];

private _score = 0;
private _checks = 0;

if ((_bioA getOrDefault ["fingerprintId", ""]) != "" && {(_bioA get "fingerprintId") isEqualTo (_bioB getOrDefault ["fingerprintId", ""])}) then {
    _score = _score + 40; _checks = _checks + 1;
};
if ((_bioA getOrDefault ["irisId", ""]) != "" && {(_bioA get "irisId") isEqualTo (_bioB getOrDefault ["irisId", ""])}) then {
    _score = _score + 35; _checks = _checks + 1;
};
if ((_bioA getOrDefault ["dnaId", ""]) != "" && {(_bioA get "dnaId") isEqualTo (_bioB getOrDefault ["dnaId", ""])}) then {
    _score = _score + 25; _checks = _checks + 1;
};

private _label = if (_score >= 70) then { "MATCH" } else {
    if (_score >= 35) then { "PARTIAL" } else { "NO_MATCH" }
};

createHashMapFromArray [
    ["score", _score],
    ["checks", _checks],
    ["label", _label]
]
