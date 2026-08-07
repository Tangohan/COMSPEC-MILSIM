/*
    Crée le modèle de données SSE sérialisable (ARRAY de paires clé/valeur).
    [_type, _createdBy, _profile, _complexity, _seed] call comspec_sse_fnc_createDataModel
*/
params [
    ["_type", "PERSON", [""]],
    ["_createdBy", "SYSTEM", [""]],
    ["_profile", "RANDOM", [""]],
    ["_complexity", "STANDARD", [""]],
    ["_seed", -1, [0]]
];

if (_seed < 0) then {
    _seed = floor random 2147483647;
};

private _uid = ["SSE"] call comspec_sse_fnc_generateUID;

[
    ["uid", _uid],
    ["type", toUpper _type],
    ["classification", "UNCLASSIFIED"],
    ["profile", toUpper _profile],
    ["complexity", toUpper _complexity],
    ["seed", _seed],
    ["generated", false],
    ["lazyReady", false],
    ["state", "UNTOUCHED"],
    ["searched", false],
    ["exploited", false],
    ["createdBy", _createdBy],
    ["createdAt", time],
    ["sections", createHashMapFromArray [
        ["identity", createHashMap],
        ["biometrics", createHashMap],
        ["documents", []],
        ["digitalDevices", []],
        ["communications", createHashMap],
        ["associations", []],
        ["locations", []],
        ["vehicle", createHashMap],
        ["weapons", []],
        ["equipment", []],
        ["notes", []],
        ["photos", []],
        ["intel", []],
        ["metadata", createHashMapFromArray [
            ["version", "0.1.0"],
            ["noiseProbability", missionNamespace getVariable ["comspec_sse_noiseProbability", 0.25]],
            ["falseLeadProbability", missionNamespace getVariable ["comspec_sse_falseLeadProbability", 0.05]]
        ]],
        ["chainOfCustody", []],
        ["sectionStatus", createHashMapFromArray [
            ["identity", "none"],
            ["biometrics", "none"],
            ["digital", "none"],
            ["documents", "none"]
        ]]
    ]],
    ["revealed", createHashMap],
    ["clusterId", ""],
    ["networkId", ""]
]
