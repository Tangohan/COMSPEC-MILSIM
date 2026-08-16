/*
    Attache les couches d'intel progressives + états preuve à une entité déjà générée.
    [_entity] call comspec_sse_fnc_attachIntelLayers
*/
params [
    ["_entity", objNull, [objNull, []]]
];

if (_entity isEqualType []) then {
    _entity = _entity param [0, objNull, [objNull]];
};

if (isNull _entity) exitWith { false };

private _data = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data" || {!(_data isEqualType [])}) exitWith { false };

private _uid = [_data, "uid", ""] call comspec_sse_fnc_getPair;
private _seed = [_data, "seed", 0] call comspec_sse_fnc_getPair;
private _type = [_data, "type", "OBJECT"] call comspec_sse_fnc_getPair;
private _sections = [_data, "sections", createHashMap] call comspec_sse_fnc_getPair;
if !(_sections isEqualType createHashMap) then { _sections = createHashMap; };

// États preuve / accès
private _evState = [_seed, _type] call comspec_sse_fnc_applyEvidenceState;
private _access = [_seed, _type] call comspec_sse_fnc_rollAccessState;
_sections set ["evidenceState", _evState];
_sections set ["accessState", _access];

private _identity = _sections getOrDefault ["identity", createHashMap];
private _clusterName = if (_identity isEqualType createHashMap) then { _identity getOrDefault ["name", ""] } else { "" };
private _alias = if (_identity isEqualType createHashMap) then { _identity getOrDefault ["alias", ""] } else { "" };
private _phone = "";
private _devices = _sections getOrDefault ["digitalDevices", []];
if (_devices isEqualType [] && {count _devices > 0}) then {
    private _d0 = _devices select 0;
    if (_d0 isEqualType createHashMap) then { _phone = _d0 getOrDefault ["phoneNumber", ""]; };
};

private _tactical = [];
private _field = [];
private _detailed = [];
private _fusion = [];

_tactical pushBack (["Présence d'éléments potentiellement exploitables.", [
    ["INTEL_VALUE", 25], ["TIME_SENSITIVITY", 10], ["CONFIDENCE", 80], ["RELEVANCE", 40],
    ["confidenceKind", "OBSERVED"], ["levelRequired", "TACTICAL"], ["triage", "DOCUMENT_ONLY"],
    ["tags", ["LOCATION"]]
]] call comspec_sse_fnc_createIntelDatum);

if (_alias != "") then {
    _field pushBack ([format ["Alias terrain : %1", _alias], [
        ["INTEL_VALUE", 55], ["TIME_SENSITIVITY", 20], ["CONFIDENCE", 60], ["RELEVANCE", 70],
        ["confidenceKind", "EXTRACTED"], ["levelRequired", "FIELD"], ["triage", "COLLECT"],
        ["tags", ["PERSON", "HVT"]]
    ]] call comspec_sse_fnc_createIntelDatum);
};

if (_phone != "") then {
    _detailed pushBack ([format ["Numéro associé : %1", _phone], [
        ["INTEL_VALUE", 75], ["TIME_SENSITIVITY", 50], ["CONFIDENCE", 70], ["RELEVANCE", 80],
        ["confidenceKind", "EXTRACTED"], ["levelRequired", "DETAILED"], ["triage", "EXPLOIT_NOW"],
        ["tags", ["COMMS", "PERSON"]], ["actionable", true], ["id", format ["SSE-INTEL-PHONE-%1", _seed]]
    ]] call comspec_sse_fnc_createIntelDatum);

    // Entité logique
    [_phone, "PHONE_NUMBER", createHashMapFromArray [
        ["label", _phone],
        ["linkedEntity", netId _entity],
        ["tags", ["COMMS"]]
    ]] call comspec_sse_fnc_addLogicalEntity;
};

private _docs = _sections getOrDefault ["documents", []];
if (_docs isEqualType [] && {count _docs > 0}) then {
    private _doc = _docs select 0;
    if (_doc isEqualType createHashMap) then {
        _field pushBack ([format ["Document : %1", _doc getOrDefault ["title", "Document"]], [
            ["INTEL_VALUE", 45], ["TIME_SENSITIVITY", 15], ["CONFIDENCE", 65], ["RELEVANCE", 55],
            ["confidenceKind", "EXTRACTED"], ["levelRequired", "FIELD"], ["triage", "DOCUMENT_ONLY"],
            ["tags", ["LOGISTICS"]]
        ]] call comspec_sse_fnc_createIntelDatum);
        private _grid = _doc getOrDefault ["grid", ""];
        if (_grid != "") then {
            _detailed pushBack ([format ["Grid documentaire : %1", _grid], [
                ["INTEL_VALUE", 70], ["TIME_SENSITIVITY", 60], ["CONFIDENCE", 55], ["RELEVANCE", 75],
                ["confidenceKind", "PROBABLE"], ["levelRequired", "DETAILED"], ["triage", "EXPLOIT_NOW"],
                ["tags", ["LOCATION"]], ["actionable", true],
                ["expiresAt", time + 1200]
            ]] call comspec_sse_fnc_createIntelDatum);
        };
    };
};

// Faux positifs / contradictions selon complexité
if ((([_seed, "fp"] call comspec_sse_fnc_hash) mod 100) < 15) then {
    _field pushBack (["Mention d'un dépôt civil non lié à l'objectif.", [
        ["INTEL_VALUE", 20], ["TIME_SENSITIVITY", 5], ["CONFIDENCE", 40], ["RELEVANCE", 15],
        ["confidenceKind", "HYPOTHESIS"], ["levelRequired", "FIELD"], ["triage", "LOW_VALUE"],
        ["falsePositive", true], ["tags", ["LOCATION"]]
    ]] call comspec_sse_fnc_createIntelDatum);
};

if (_clusterName != "" && {_alias != ""}) then {
    _fusion pushBack ([format ["Réseau : %1 opère sous %2", _clusterName, _alias], [
        ["INTEL_VALUE", 85], ["TIME_SENSITIVITY", 40], ["CONFIDENCE", 45], ["RELEVANCE", 90],
        ["confidenceKind", "PROBABLE"], ["levelRequired", "FUSION"], ["triage", "EXPLOIT_NOW"],
        ["tags", ["PERSON", "COMMS", "HVT"]], ["actionable", true]
    ]] call comspec_sse_fnc_createIntelDatum);
};

// Caches dissimulées
if ((([_seed, "cache"] call comspec_sse_fnc_hash) mod 100) < 40) then {
    _sections set ["hiddenCaches", [
        createHashMapFromArray [
            ["id", format ["CACHE-%1", _seed]],
            ["label", "Compartiment dissimulé"],
            ["requires", "DETAILED"],
            ["tool", "COMSPEC_SSE_EvidenceBag"],
            ["revealed", false],
            ["content", "Documents / support numérique additionnel"]
        ]
    ]];
};

// Données « deleted » récupérables en DETAILED
if (_type in ["PHONE", "SMARTPHONE", "COMPUTER", "LAPTOP", "PERSON"]) then {
    _sections set ["deletedData", [
        createHashMapFromArray [
            ["id", format ["DEL-%1", _seed]],
            ["label", "Messages / fichiers marqués supprimés"],
            ["recoverableAt", "DETAILED"],
            ["recovered", false]
        ]
    ]];
};

_sections set ["intelLayers", createHashMapFromArray [
    ["TACTICAL", _tactical],
    ["FIELD", _field],
    ["DETAILED", _detailed],
    ["FUSION", _fusion]
]];
_sections set ["exploitationLevel", "NONE"];
_sections set ["evidenceLabel", [_entity] call comspec_sse_fnc_makeEvidenceLabel];

// Personnes inconnues
if (_type == "PERSON" && {(([_seed, "unk"] call comspec_sse_fnc_hash) mod 100) < 25}) then {
    if (_identity isEqualType createHashMap) then {
        private _unkId = format ["UNKNOWN %1 %2", ["MALE", "FEMALE"] select (([_seed, "sex"] call comspec_sse_fnc_hash) mod 2), 10 + (_seed mod 89)];
        _identity set ["name", _unkId];
        _identity set ["identityStatus", "UNKNOWN"];
        _sections set ["identity", _identity];
    };
};

// Biométrie enrichie + index
if (_type == "PERSON") then {
    private _bio = [_entity, _seed] call comspec_sse_fnc_enrichBiometrics;
    if (!isNil "_bio") then {
        _sections set ["biometrics", _bio];
    };
};

// TECHINT si arme / équipement
if (_type == "WEAPON") then {
    _sections set ["techint", [_seed] call comspec_sse_fnc_generateTechint];
};

// Optique
if (_type in ["MEDIA", "OBJECT"] || {((toLower typeOf _entity) find "camera") >= 0}) then {
    _sections set ["optical", [_seed] call comspec_sse_fnc_generateOpticalMedia];
};

_data = [_data, "sections", _sections] call comspec_sse_fnc_setPair;
if (isNil "_data" || {!(_data isEqualType [])}) exitWith { false };

[_entity, _data, true] call comspec_sse_fnc_setData;

if (_uid isEqualTo "") then {
    _uid = [_data, "uid", ""] call comspec_sse_fnc_getPair;
};
["SSE_RecordCreated", [_entity, _uid]] call comspec_sse_fnc_emitEvent;
true
