/*
    Convertit un record BII (ARRAY) en sections SSE sur l’entité cible.
    [_entity, _record] call comspec_sse_fnc_biiRecordToSse
*/
params [
    ["_entity", objNull, [objNull]],
    ["_record", [], [[]]]
];

if (isNull _entity || {_record isEqualTo []}) exitWith { false };

private _data0 = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data0") then {
    [_entity, "PERSON"] call comspec_sse_fnc_makeSearchable;
};

private _name = _record param [1, ""];
private _alias = _record param [2, ""];
private _nat = _record param [3, ""];
private _org = _record param [4, ""];
private _threat = _record param [5, "Orange"];
private _quality = _record param [6, 50];
private _bioPack = _record param [11, ["", []]];
private _bioKey = _bioPack param [0, ""];
private _modes = _bioPack param [1, []];
private _evidence = _record param [12, []];
private _notes = _record param [13, ""];
private _watchlist = _record param [14, false];
private _extra = _record param [17, createHashMap];
if !(_extra isEqualType createHashMap) then { _extra = createHashMap; };

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

private _profile = [_threat] call comspec_sse_fnc_biiThreatToProfile;
private _data = [_entity] call comspec_sse_fnc_getData;
if (!isNil "_data" && {_data isEqualType []}) then {
    _data = [_data, "profile", _profile] call comspec_sse_fnc_setPair;
    [_entity, _data, true] call comspec_sse_fnc_setData;
};

private _bio = [_entity, "biometrics"] call comspec_sse_fnc_getSection;
if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
if (_bioKey isNotEqualTo "") then {
    _bio set ["biiBioKey", _bioKey];
    _bio set ["fingerprintId", _bio getOrDefault ["fingerprintId", _bioKey]];
};
{
    private _m = toUpper _x;
    if (_m in ["FACE", "FULL", "ENROLL"]) then {
        _bio set ["facePhoto", true];
        _bio set ["faceQuality", _quality];
    };
    if (_m in ["FINGERPRINT", "FP", "FULL", "ENROLL"]) then {
        _bio set ["fingerprintCaptured", true];
        _bio set ["fingerprintQuality", _quality];
    };
    if (_m in ["IRIS", "FULL", "ENROLL"]) then {
        _bio set ["irisCaptured", true];
        _bio set ["irisQuality", _quality];
    };
} forEach _modes;
_bio set ["biiWatchlist", _watchlist];
_bio set ["biiConfidence", _quality];
[_entity, "biometrics", _bio, true] call comspec_sse_fnc_setSection;

private _intel = [_entity, "intel"] call comspec_sse_fnc_getSection;
if (isNil "_intel" || {!(_intel isEqualType createHashMap)}) then { _intel = createHashMap; };
_intel set ["source", "BII"];
_intel set ["biiRecordId", _record param [0, ""]];
_intel set ["family", _extra getOrDefault ["family", _intel getOrDefault ["family", ""]]];
_intel set ["associates", _extra getOrDefault ["associates", _intel getOrDefault ["associates", ""]]];
_intel set ["leads", _extra getOrDefault ["leads", _intel getOrDefault ["leads", ""]]];
if (_notes isNotEqualTo "") then {
    private _plain = [_notes, "<br/>", " | "] call BIS_fnc_replaceString;
    _plain = [_plain, "<br>", " | "] call BIS_fnc_replaceString;
    _intel set ["biiNotes", _plain];
};
[_entity, "intel", _intel, true] call comspec_sse_fnc_setSection;

{
    [_entity, _x] call comspec_sse_fnc_biiImportEvidenceEntry;
} forEach _evidence;

[_entity, _entity, "bii_import", _record param [0, ""]] call comspec_sse_fnc_registerActionHistory;
true
