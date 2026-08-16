/*
    Importe les variables BII_Identifi_* d’une unité vers le record SSE.
    [_entity] call comspec_sse_fnc_biiImportEntityVars
*/
params [["_entity", objNull, [objNull]]];

if (isNull _entity) exitWith { false };
if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith { false };
if !([] call comspec_sse_fnc_biiIsPresent) exitWith { false };

private _name = _entity getVariable ["BII_Identifi_name", ""];
private _alias = _entity getVariable ["BII_Identifi_alias", ""];
private _nat = _entity getVariable ["BII_Identifi_nationality", ""];
private _org = _entity getVariable ["BII_Identifi_org", ""];
private _threat = _entity getVariable ["BII_Identifi_threat", ""];
private _family = _entity getVariable ["BII_Identifi_family", ""];
private _associates = _entity getVariable ["BII_Identifi_associates", ""];
private _leads = _entity getVariable ["BII_Identifi_leads", ""];
private _notes = _entity getVariable ["BII_Identifi_notes", ""];
private _bioKey = _entity getVariable ["BII_Identifi_bioKey", ""];
private _watchlist = _entity getVariable ["BII_Identifi_watchlist", false];

if (
    _name isEqualTo ""
    && {_alias isEqualTo ""}
    && {_nat isEqualTo ""}
    && {_org isEqualTo ""}
    && {_bioKey isEqualTo ""}
) exitWith { false };

// Éviter la récursion avec le wrap ensureGenerated → import → ensureGenerated
private _data0 = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data0") then {
    [_entity, "PERSON"] call comspec_sse_fnc_makeSearchable;
};

private _pairs = [];
if (_name isNotEqualTo "") then { _pairs pushBack ["name", _name]; };
if (_alias isNotEqualTo "") then { _pairs pushBack ["alias", _alias]; };
if (_nat isNotEqualTo "") then { _pairs pushBack ["nationality", _nat]; };
if (_org isNotEqualTo "") then {
    _pairs pushBack ["organization", _org];
    _pairs pushBack ["role", _org];
};
if (_pairs isNotEqualTo []) then {
    [_entity, _pairs] call comspec_sse_fnc_setIdentity;
};

if (_threat isNotEqualTo "") then {
    private _profile = [_threat] call comspec_sse_fnc_biiThreatToProfile;
    private _data = [_entity] call comspec_sse_fnc_getData;
    if (!isNil "_data" && {_data isEqualType []}) then {
        _data = [_data, "profile", _profile] call comspec_sse_fnc_setPair;
        [_entity, _data, true] call comspec_sse_fnc_setData;
    };
};

private _bio = [_entity, "biometrics"] call comspec_sse_fnc_getSection;
if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
if (_bioKey isNotEqualTo "") then {
    _bio set ["biiBioKey", _bioKey];
    if ((_bio getOrDefault ["fingerprintId", ""]) isEqualTo "") then {
        _bio set ["fingerprintId", _bioKey];
    };
};
_bio set ["biiWatchlist", _watchlist];
[_entity, "biometrics", _bio, true] call comspec_sse_fnc_setSection;

private _intel = [_entity, "intel"] call comspec_sse_fnc_getSection;
if (isNil "_intel" || {!(_intel isEqualType createHashMap)}) then { _intel = createHashMap; };
if (_family isNotEqualTo "") then { _intel set ["family", _family]; };
if (_associates isNotEqualTo "") then { _intel set ["associates", _associates]; };
if (_leads isNotEqualTo "") then { _intel set ["leads", _leads]; };
if (_notes isNotEqualTo "") then { _intel set ["biiNotes", _notes]; };
_intel set ["source", "BII"];
[_entity, "intel", _intel, true] call comspec_sse_fnc_setSection;

if (_associates isNotEqualTo "" || {_family isNotEqualTo ""}) then {
    private _assoc = [_entity, "associations"] call comspec_sse_fnc_getSection;
    if (isNil "_assoc" || {!(_assoc isEqualType [])}) then { _assoc = []; };
    if (_family isNotEqualTo "") then { _assoc pushBackUnique format ["Famille: %1", _family]; };
    if (_associates isNotEqualTo "") then { _assoc pushBackUnique format ["Associés: %1", _associates]; };
    [_entity, "associations", _assoc, true] call comspec_sse_fnc_setSection;
};

_entity setVariable ["comspec_sse_biiImported", true, true];
true
