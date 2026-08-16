/*
    Exporte l’identité SSE vers variables BII_Identifi_* (dual-use modules / UI BII).
    [_entity] call comspec_sse_fnc_biiExportEntityVars
*/
params [["_entity", objNull, [objNull]]];

if (isNull _entity) exitWith { false };
if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith { false };
if !(missionNamespace getVariable ["comspec_sse_biiExportToBii", true]) exitWith { false };
if !([] call comspec_sse_fnc_biiIsPresent) exitWith { false };

private _identity = [_entity, "identity"] call comspec_sse_fnc_getSection;
if (isNil "_identity" || {!(_identity isEqualType createHashMap)}) exitWith { false };

private _name = _identity getOrDefault ["name", ""];
private _alias = _identity getOrDefault ["alias", ""];
private _nat = _identity getOrDefault ["nationality", ""];
private _org = _identity getOrDefault ["organization", ""];
if (_org isEqualTo "") then { _org = _identity getOrDefault ["role", ""]; };

if (_name isNotEqualTo "") then {
    _entity setVariable ["BII_Identifi_name", _name, true];
    _entity setVariable ["BII_Identifi_bioName", _name, true];
};
if (_alias isNotEqualTo "") then { _entity setVariable ["BII_Identifi_alias", _alias, true]; };
if (_nat isNotEqualTo "") then { _entity setVariable ["BII_Identifi_nationality", _nat, true]; };
if (_org isNotEqualTo "") then { _entity setVariable ["BII_Identifi_org", _org, true]; };

private _intel = [_entity, "intel"] call comspec_sse_fnc_getSection;
if (_intel isEqualType createHashMap) then {
    private _family = _intel getOrDefault ["family", ""];
    private _associates = _intel getOrDefault ["associates", ""];
    private _leads = _intel getOrDefault ["leads", ""];
    private _notes = _intel getOrDefault ["biiNotes", ""];
    if (_family isNotEqualTo "") then { _entity setVariable ["BII_Identifi_family", _family, true]; };
    if (_associates isNotEqualTo "") then { _entity setVariable ["BII_Identifi_associates", _associates, true]; };
    if (_leads isNotEqualTo "") then { _entity setVariable ["BII_Identifi_leads", _leads, true]; };
    if (_notes isNotEqualTo "") then { _entity setVariable ["BII_Identifi_notes", _notes, true]; };
};

private _bio = [_entity, "biometrics"] call comspec_sse_fnc_getSection;
if (_bio isEqualType createHashMap) then {
    private _bk = _bio getOrDefault ["biiBioKey", ""];
    if (_bk isEqualTo "") then { _bk = _bio getOrDefault ["fingerprintId", ""]; };
    if (_bk isNotEqualTo "") then { _entity setVariable ["BII_Identifi_bioKey", _bk, true]; };
};

true
