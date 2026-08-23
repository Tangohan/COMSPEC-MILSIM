/*
    API principale de génération SSE.
    [_entity, _profile, _complexity, _createdBy, _cluster] call comspec_sse_fnc_generateData
*/
params [
    ["_entity", objNull, [objNull]],
    ["_profile", "RANDOM", [""]],
    ["_complexity", "STANDARD", [""]],
    ["_createdBy", "SCRIPT", [""]],
    ["_cluster", createHashMap, [createHashMap]]
];

if (isNull _entity) exitWith { false };

// Anti-réentrée (dogtag wrap / Eden / ensureGenerated pendant génération en cours).
if (_entity getVariable ["comspec_sse_generating", false]) exitWith {
    ["generateData: réentrée bloquée", "WARNING"] call comspec_sse_fnc_log;
    false
};
_entity setVariable ["comspec_sse_generating", true];

_profile = [_profile] call comspec_sse_fnc_resolveProfile;
_complexity = toUpper _complexity;

private _type = [_entity] call comspec_sse_fnc_resolveEntityType;

private _existing = [_entity] call comspec_sse_fnc_getData;
private _seed = if (isNil "_existing" || {!(_existing isEqualType [])}) then {
    private _pos = getPosASL _entity;
    [floor random 999999, format ["%1_%2_%3_%4", typeOf _entity, round (_pos select 0), round (_pos select 1), netId _entity]] call comspec_sse_fnc_hash
} else {
    [_existing, "seed", floor random 999999] call comspec_sse_fnc_getPair
};

private _data = if (isNil "_existing" || {!(_existing isEqualType [])}) then {
    [_type, _createdBy, _profile, _complexity, _seed] call comspec_sse_fnc_createDataModel
} else {
    _existing
};

if (isNil "_data" || {!(_data isEqualType [])}) then {
    ["generateData: createDataModel a échoué — modèle de secours", "ERROR"] call comspec_sse_fnc_log;
    _data = [_type, _createdBy, _profile, _complexity, if (isNil "_seed") then { floor random 999999 } else { _seed }] call comspec_sse_fnc_createDataModel;
};
if (isNil "_data" || {!(_data isEqualType [])}) exitWith {
    ["generateData: abandon — _data invalide", "ERROR"] call comspec_sse_fnc_log;
    _entity setVariable ["comspec_sse_generating", false];
    false
};

_data = [_data, "profile", _profile] call comspec_sse_fnc_setPair;
_data = [_data, "complexity", _complexity] call comspec_sse_fnc_setPair;
_data = [_data, "type", _type] call comspec_sse_fnc_setPair;
_data = [_data, "generated", true] call comspec_sse_fnc_setPair;
_data = [_data, "lazyReady", true] call comspec_sse_fnc_setPair;
_data = [_data, "createdBy", _createdBy] call comspec_sse_fnc_setPair;

if ((_cluster getOrDefault ["clusterId", ""]) isEqualTo "") then {
    private _region = _entity getVariable ["comspec_sse_region", "IRAQ"];
    _cluster = [_seed, _profile, _complexity, _region] call comspec_sse_fnc_generateCluster;
};
if (!isNil "comspec_sse_fnc_applyAuthoredIdentity") then {
    _cluster = [_entity, _cluster] call comspec_sse_fnc_applyAuthoredIdentity;
};
_data = [_data, "clusterId", _cluster getOrDefault ["clusterId", ""]] call comspec_sse_fnc_setPair;
_data = [_data, "theme", _cluster getOrDefault ["theme", ""]] call comspec_sse_fnc_setPair;

private _sections = [_data, "sections", createHashMap] call comspec_sse_fnc_getPair;
private _region = _cluster getOrDefault ["region", "IRAQ"];
private _pools = [_region] call comspec_sse_fnc_getNarrativePools;

switch (_type) do {
    case "PERSON": {
        private _person = [_seed, _profile, _complexity, _cluster] call comspec_sse_fnc_generatePerson;
        _cluster = _person getOrDefault ["cluster", _cluster];
        _sections set ["identity", _person get "identity"];
        _sections set ["biometrics", _person get "biometrics"];
        _sections set ["documents", _person get "documents"];
        _sections set ["locations", _person getOrDefault ["locations", []]];
        _sections set ["intel", _person getOrDefault ["intel", []]];

        private _phoneMode = toUpper (trim (_entity getVariable ["comspec_sse_phoneMode", "AUTO"]));
        if (!(_phoneMode isEqualType "")) then { _phoneMode = "AUTO"; };
        private _digMode = toUpper (trim (_entity getVariable ["comspec_sse_digitalMode", "AUTO"]));
        if (!(_digMode isEqualType "")) then { _digMode = "AUTO"; };
        private _docMode = toUpper (trim (_entity getVariable ["comspec_sse_documentsMode", "AUTO"]));
        if (!(_docMode isEqualType "")) then { _docMode = "AUTO"; };

        private _devices = [];
        if (_phoneMode isNotEqualTo "NONE") then {
            private _phoneData = [_seed + 7, _profile, _complexity, _cluster] call comspec_sse_fnc_generatePhone;
            _devices = [_phoneData];
        };
        _sections set ["digitalDevices", _devices];

        if (_docMode isEqualTo "NONE") then {
            _sections set ["documents", []];
        };

        // PC : richesse élevée, ou ordinateur personnalisé Eden.
        private _wantPc = (_digMode isEqualTo "CUSTOM")
            || {_complexity in ["DETAILED", "HIGH_VALUE"] && {(([_seed, "pc"] call comspec_sse_fnc_hash) mod 100) < (if (_complexity == "HIGH_VALUE") then {80} else {45})}};
        if (_digMode isEqualTo "NONE") then { _wantPc = false; };
        if (_wantPc) then {
            _entity setVariable ["comspec_sse_pendingComputer", true, false];
            _entity setVariable ["comspec_sse_pendingComputerSeed", _seed + 99, false];
        };

        private _status = _sections getOrDefault ["sectionStatus", createHashMap];
        _status set ["identity", "complete"];
        _status set ["biometrics", if (count (_person get "biometrics") > 0) then {"complete"} else {"none"}];
        _status set ["digital", "complete"];
        _status set ["documents", "complete"];
        _sections set ["sectionStatus", _status];
    };
    case "PHONE";
    case "SMARTPHONE": {
        private _phoneData = [_seed, _profile, _complexity, _cluster] call comspec_sse_fnc_generatePhone;
        _cluster = _phoneData getOrDefault ["cluster", _cluster];
        _sections set ["digitalDevices", [_phoneData]];
        _sections set ["locations", _phoneData getOrDefault ["locations", []]];
        private _id = createHashMapFromArray [
            ["name", _phoneData getOrDefault ["owner", ""]],
            ["phone", _phoneData getOrDefault ["phoneNumber", ""]]
        ];
        _sections set ["identity", _id];
        private _status = _sections getOrDefault ["sectionStatus", createHashMap];
        _status set ["digital", "complete"];
        _status set ["identity", "partial"];
        _sections set ["sectionStatus", _status];
    };
    case "COMPUTER";
    case "LAPTOP": {
        if ((_cluster getOrDefault ["primaryName", ""]) isEqualTo "") then {
            _cluster = [_seed, _profile, _complexity, _region] call comspec_sse_fnc_generateCluster;
        };
        private _comp = [_seed, _profile, _complexity, _cluster, _pools] call comspec_sse_fnc_generateComputer;
        _sections set ["digitalDevices", [_comp]];
        private _docs = [_seed, 2, _cluster, _pools] call comspec_sse_fnc_generateDocument;
        _sections set ["documents", _docs];
        private _status = _sections getOrDefault ["sectionStatus", createHashMap];
        _status set ["digital", "complete"];
        _status set ["documents", "complete"];
        _sections set ["sectionStatus", _status];
    };
    case "VEHICLE": {
        if ((_cluster getOrDefault ["primaryName", ""]) isEqualTo "") then {
            _cluster = [_seed, _profile, _complexity, _region] call comspec_sse_fnc_generateCluster;
        };
        private _veh = [_seed, _profile, _complexity, _cluster, _pools] call comspec_sse_fnc_generateVehicle;
        _cluster = _veh getOrDefault ["cluster", _cluster];
        _sections set ["vehicle", _veh];
        _sections set ["documents", _veh getOrDefault ["documents", []]];
        _sections set ["locations", _veh getOrDefault ["locations", []]];
        _sections set ["intel", _veh getOrDefault ["intel", []]];
        _sections set ["identity", createHashMapFromArray [
            ["name", _veh getOrDefault ["ownerHint", ""]],
            ["alias", _veh getOrDefault ["linkedAlias", ""]],
            ["plate", _veh getOrDefault ["plate", ""]]
        ]];
        private _status = _sections getOrDefault ["sectionStatus", createHashMap];
        _status set ["vehicle", "complete"];
        _status set ["documents", "complete"];
        _sections set ["sectionStatus", _status];
    };
    case "RADIO": {
        if ((_cluster getOrDefault ["primaryName", ""]) isEqualTo "") then {
            _cluster = [_seed, _profile, _complexity, _region] call comspec_sse_fnc_generateCluster;
        };
        private _rad = [_seed, _profile, _complexity, _cluster, _pools] call comspec_sse_fnc_generateRadio;
        _cluster = _rad getOrDefault ["cluster", _cluster];
        _sections set ["radio", _rad];
        _sections set ["digitalDevices", [_rad]];
        _sections set ["locations", _rad getOrDefault ["locations", []]];
        _sections set ["identity", createHashMapFromArray [
            ["name", _rad getOrDefault ["ownerHint", ""]],
            ["alias", _rad getOrDefault ["callsign", ""]]
        ]];
        private _status = _sections getOrDefault ["sectionStatus", createHashMap];
        _status set ["radio", "complete"];
        _status set ["digital", "complete"];
        _sections set ["sectionStatus", _status];
    };
    case "WEAPON": {
        if ((_cluster getOrDefault ["primaryName", ""]) isEqualTo "") then {
            _cluster = [_seed, _profile, _complexity, _region] call comspec_sse_fnc_generateCluster;
        };
        private _wpn = [_seed, _profile, _complexity, _cluster, _pools] call comspec_sse_fnc_generateWeapon;
        _cluster = _wpn getOrDefault ["cluster", _cluster];
        _sections set ["weapon", _wpn];
        _sections set ["documents", _wpn getOrDefault ["documents", []]];
        _sections set ["intel", _wpn getOrDefault ["intel", []]];
        _sections set ["notes", _wpn getOrDefault ["notes", []]];
        private _status = _sections getOrDefault ["sectionStatus", createHashMap];
        _status set ["weapon", "complete"];
        _status set ["documents", "complete"];
        _sections set ["sectionStatus", _status];
    };
    case "BUILDING";
    case "CONTAINER";
    case "MEDIA": {
        if ((_cluster getOrDefault ["primaryName", ""]) isEqualTo "") then {
            _cluster = [_seed, _profile, _complexity, _region] call comspec_sse_fnc_generateCluster;
        };
        private _bld = [_seed, _profile, _complexity, _cluster, _pools, _type] call comspec_sse_fnc_generateBuilding;
        _cluster = _bld getOrDefault ["cluster", _cluster];
        _sections set ["site", _bld];
        _sections set ["documents", _bld getOrDefault ["documents", []]];
        _sections set ["digitalDevices", _bld getOrDefault ["digitalDevices", []]];
        _sections set ["locations", _bld getOrDefault ["locations", []]];
        _sections set ["intel", _bld getOrDefault ["intel", []]];
        private _status = _sections getOrDefault ["sectionStatus", createHashMap];
        _status set ["site", "complete"];
        _status set ["documents", "complete"];
        if (count (_bld getOrDefault ["digitalDevices", []]) > 0) then { _status set ["digital", "complete"]; };
        _sections set ["sectionStatus", _status];
    };
    case "DOCUMENT": {
        if ((_cluster getOrDefault ["primaryName", ""]) isEqualTo "") then {
            _cluster = [_seed, _profile, _complexity, _region] call comspec_sse_fnc_generateCluster;
        };
        private _docs = [_seed, if (_complexity == "LIGHT") then {2} else {4}, _cluster, _pools] call comspec_sse_fnc_generateDocument;
        _sections set ["documents", _docs];
        private _themeNote = _cluster getOrDefault ["themeLabel", ""];
        if (_themeNote isEqualTo "") then { _themeNote = _cluster getOrDefault ["theme", ""]; };
        private _deliveryNote = _cluster getOrDefault ["deliveryNote", ""];
        private _themeLine = format ["Thème: %1", _themeNote];
        private _primaryName = _cluster getOrDefault ["primaryName", "?"];
        private _intelText = format ["Documents liés à %1", _primaryName];
        private _intelItem = createHashMapFromArray [
            ["text", _intelText],
            ["confidence", 0.6]
        ];
        _sections set ["notes", [_deliveryNote, _themeLine]];
        _sections set ["intel", [_intelItem]];
        private _status = _sections getOrDefault ["sectionStatus", createHashMap];
        _status set ["documents", "complete"];
        _sections set ["sectionStatus", _status];
    };
    default {
        if ((_cluster getOrDefault ["primaryName", ""]) isEqualTo "") then {
            _cluster = [_seed, _profile, _complexity, _region] call comspec_sse_fnc_generateCluster;
        };
        private _docs = [_seed, 1, _cluster, _pools] call comspec_sse_fnc_generateDocument;
        _sections set ["documents", _docs];
        private _themeNote = _cluster getOrDefault ["themeLabel", ""];
        if (_themeNote isEqualTo "") then { _themeNote = _cluster getOrDefault ["theme", ""]; };
        private _deliveryNote = _cluster getOrDefault ["deliveryNote", ""];
        private _themeLine = format ["Thème: %1", _themeNote];
        private _primaryName = _cluster getOrDefault ["primaryName", "?"];
        private _intelText = format ["Objet lié à %1", _primaryName];
        private _intelItem = createHashMapFromArray [
            ["text", _intelText],
            ["confidence", 0.55]
        ];
        _sections set ["notes", [_deliveryNote, _themeLine]];
        _sections set ["intel", [_intelItem]];
        private _status = _sections getOrDefault ["sectionStatus", createHashMap];
        _status set ["documents", "complete"];
        _sections set ["sectionStatus", _status];
    };
};

_data = [_data, "sections", _sections] call comspec_sse_fnc_setPair;
if (isNil "_data" || {!(_data isEqualType [])}) exitWith {
    ["generateData: setPair sections a renvoyé nil", "ERROR"] call comspec_sse_fnc_log;
    _entity setVariable ["comspec_sse_generating", false];
    false
};
[_entity, _data, false] call comspec_sse_fnc_setData;
if (!isNil "comspec_sse_fnc_applyAuthoredContent") then {
    [_entity] call comspec_sse_fnc_applyAuthoredContent;
};
_entity setVariable ["comspec_sse_clusterId", _cluster getOrDefault ["clusterId", ""], false];
_entity setVariable ["comspec_sse_theme", _cluster getOrDefault ["theme", ""], false];

// Pont SEEK / Eden : nom + alias générés visibles hors section SSE.
if (!isNil "comspec_sse_fnc_syncIdentityBridgeVars") then {
    [_entity, false] call comspec_sse_fnc_syncIdentityBridgeVars;
};

// Libérer le verrou AVANT couches intel / publish / dogtag (ACE + réseau).
_entity setVariable ["comspec_sse_generating", false];

// Couches intel + publish hors de la pile de génération (anti STACK_OVERFLOW).
if (!isNil "CBA_fnc_waitAndExecute") then {
    [{
        params ["_e"];
        if (isNull _e) exitWith {};
        if (!isNil "comspec_sse_fnc_attachIntelLayers") then {
            [_e] call comspec_sse_fnc_attachIntelLayers;
        };
        // PC pending (DETAILED) — après intel, hors pile initiale
        if (_e getVariable ["comspec_sse_pendingComputer", false]) then {
            _e setVariable ["comspec_sse_pendingComputer", false, false];
            private _seedPc = _e getVariable ["comspec_sse_pendingComputerSeed", 0];
            private _d0 = [_e] call comspec_sse_fnc_getData;
            if (!isNil "_d0" && {!isNil "comspec_sse_fnc_generateComputer"}) then {
                private _prof = [_d0, "profile", "INSURGENT"] call comspec_sse_fnc_getPair;
                private _cx = [_d0, "complexity", "DETAILED"] call comspec_sse_fnc_getPair;
                private _reg = _e getVariable ["comspec_sse_region", "IRAQ"];
                private _pools2 = [_reg] call comspec_sse_fnc_getNarrativePools;
                private _cluster2 = createHashMapFromArray [
                    ["clusterId", _e getVariable ["comspec_sse_clusterId", ""]],
                    ["theme", _e getVariable ["comspec_sse_theme", ""]],
                    ["primaryName", ""],
                    ["region", _reg]
                ];
                private _idSec = [_e, "identity"] call comspec_sse_fnc_getSection;
                if (!isNil "_idSec" && {_idSec isEqualType createHashMap}) then {
                    _cluster2 set ["primaryName", _idSec getOrDefault ["name", ""]];
                    _cluster2 set ["primaryPhone", _idSec getOrDefault ["phone", ""]];
                };
                private _comp = [_seedPc, _prof, _cx, _cluster2, _pools2] call comspec_sse_fnc_generateComputer;
                private _devs = [_e, "digitalDevices"] call comspec_sse_fnc_getSection;
                if (isNil "_devs" || {!(_devs isEqualType [])}) then { _devs = []; };
                _devs pushBack _comp;
                [_e, "digitalDevices", _devs, false] call comspec_sse_fnc_setSection;
                if (!isNil "comspec_sse_fnc_applyAuthoredContent") then {
                    [_e] call comspec_sse_fnc_applyAuthoredContent;
                };
            };
        };
        private _d = [_e] call comspec_sse_fnc_getData;
        if (!isNil "_d") then {
            [_e, _d, true] call comspec_sse_fnc_setData;
        };
        _e setVariable ["comspec_sse_clusterId", _e getVariable ["comspec_sse_clusterId", ""], true];
        _e setVariable ["comspec_sse_theme", _e getVariable ["comspec_sse_theme", ""], true];
        if (!isNil "comspec_sse_fnc_syncIdentityBridgeVars") then {
            [_e, true] call comspec_sse_fnc_syncIdentityBridgeVars;
        };
        if (!isNil "comspec_sse_fnc_aceDogtagSync") then {
            [_e] call comspec_sse_fnc_aceDogtagSync;
        };
    }, [_entity], 0.25] call CBA_fnc_waitAndExecute;
} else {
    if (!isNil "comspec_sse_fnc_attachIntelLayers") then {
        [_entity] call comspec_sse_fnc_attachIntelLayers;
    };
    private _dPub = [_entity] call comspec_sse_fnc_getData;
    if (!isNil "_dPub") then { [_entity, _dPub, true] call comspec_sse_fnc_setData; };
    _entity setVariable ["comspec_sse_clusterId", _entity getVariable ["comspec_sse_clusterId", ""], true];
    _entity setVariable ["comspec_sse_theme", _entity getVariable ["comspec_sse_theme", ""], true];
    if (!isNil "comspec_sse_fnc_syncIdentityBridgeVars") then {
        [_entity, true] call comspec_sse_fnc_syncIdentityBridgeVars;
    };
    if (!isNil "comspec_sse_fnc_aceDogtagSync") then {
        [_entity] call comspec_sse_fnc_aceDogtagSync;
    };
};

[format ["generateData %1 type=%2 profile=%3 theme=%4", _entity, _type, _profile, _cluster getOrDefault ["theme", "?"]]] call comspec_sse_fnc_log;
true
