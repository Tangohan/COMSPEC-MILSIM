/*
    Objet seedé BII Evidence → record SSE OBJECT searchable.
    [_object] call comspec_sse_fnc_biiImportObject
*/
params [["_object", objNull, [objNull]]];

if (isNull _object) exitWith { false };
if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith { false };
if !(_object getVariable ["BII_Identifi_authoredEvidence", false]) exitWith { false };

[_object] call comspec_sse_fnc_makeSearchable;

private _name = _object getVariable ["BII_Identifi_evidenceName", getText (configOf _object >> "displayName")];
private _lead = _object getVariable ["BII_Identifi_lead", ""];
private _linked = _object getVariable ["BII_Identifi_linkedName", ""];
private _prio = _object getVariable ["BII_Identifi_priority", "Normal"];

private _data = [_object] call comspec_sse_fnc_getData;
if (!isNil "_data" && {_data isEqualType []}) then {
    _data = [_data, "type", "OBJECT"] call comspec_sse_fnc_setPair;
    [_object, _data, true] call comspec_sse_fnc_setData;
};

private _intel = [_object, "intel"] call comspec_sse_fnc_getSection;
if (isNil "_intel" || {!(_intel isEqualType createHashMap)}) then { _intel = createHashMap; };
_intel set ["source", "BII"];
_intel set ["evidenceName", _name];
_intel set ["lead", _lead];
_intel set ["linkedName", _linked];
_intel set ["priority", _prio];
[_object, "intel", _intel, true] call comspec_sse_fnc_setSection;

if (_name isNotEqualTo "") then {
    [_object, [["name", _name], ["alias", _linked]]] call comspec_sse_fnc_setIdentity;
};

_object setVariable ["comspec_sse_biiImported", true, true];
true
