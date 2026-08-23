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

// Privates au scope fonction : un `private` dans un `then {}` n’est plus visible
// après les `if (...) then { ... };` suivants (erreur Arma `_notes` indéfini).
private _family = "";
private _associates = "";
private _leads = "";
private _notes = "";
private _intel = [_entity, "intel"] call comspec_sse_fnc_getSection;
if (!isNil "_intel" && {_intel isEqualType createHashMap}) then {
    _family = _intel getOrDefault ["family", ""];
    _associates = _intel getOrDefault ["associates", ""];
    _leads = _intel getOrDefault ["leads", ""];
    _notes = _intel getOrDefault ["biiNotes", ""];
};
if (isNil "_family") then { _family = ""; };
if (isNil "_associates") then { _associates = ""; };
if (isNil "_leads") then { _leads = ""; };
if (isNil "_notes") then { _notes = ""; };
if (!(_notes isEqualType "")) then {
    if (_notes isEqualType []) then {
        _notes = _notes joinString " | ";
    } else {
        _notes = str _notes;
    };
};

if (_family isEqualType "" && {_family isNotEqualTo ""}) then {
    _entity setVariable ["BII_Identifi_family", _family, true];
};
if (_associates isEqualType "" && {_associates isNotEqualTo ""}) then {
    _entity setVariable ["BII_Identifi_associates", _associates, true];
};
if (_leads isEqualType "" && {_leads isNotEqualTo ""}) then {
    _entity setVariable ["BII_Identifi_leads", _leads, true];
};
if (_notes isEqualType "" && {_notes isNotEqualTo ""}) then {
    _entity setVariable ["BII_Identifi_notes", _notes, true];
};

private _bk = "";
private _bio = [_entity, "biometrics"] call comspec_sse_fnc_getSection;
if (!isNil "_bio" && {_bio isEqualType createHashMap}) then {
    _bk = _bio getOrDefault ["biiBioKey", ""];
    if (isNil "_bk" || {_bk isEqualTo ""}) then {
        _bk = _bio getOrDefault ["fingerprintId", ""];
    };
};
if (isNil "_bk" || {!(_bk isEqualType "")}) then { _bk = ""; };
if (_bk isNotEqualTo "") then { _entity setVariable ["BII_Identifi_bioKey", _bk, true]; };

true
