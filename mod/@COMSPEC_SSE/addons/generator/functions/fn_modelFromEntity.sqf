/*
    Capture l'état SSE d'une entité pour en faire un modèle réutilisable.
    [_entity, _name] call comspec_sse_fnc_modelFromEntity
*/
params [
    ["_entity", objNull, [objNull]],
    ["_name", "", [""]]
];

if (isNull _entity) exitWith { nil };

private _data = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data") exitWith { nil };

private _profile = [_data, "profile", "INSURGENT"] call BIS_fnc_getFromPairs;
private _complexity = [_data, "complexity", "DETAILED"] call BIS_fnc_getFromPairs;
private _identity = [_entity, "identity"] call comspec_sse_fnc_getSection;
private _devices = [_entity, "digitalDevices"] call comspec_sse_fnc_getSection;
private _docs = [_entity, "documents"] call comspec_sse_fnc_getSection;

if (_name isEqualTo "") then {
    private _n = if (!isNil "_identity" && {_identity isEqualType createHashMap}) then {
        _identity getOrDefault ["name", "Cible"]
    } else { "Cible" };
    _name = format ["Modèle — %1", _n];
};

private _ov = createHashMapFromArray [
    ["profile", _profile],
    ["complexity", _complexity],
    ["region", "IRAQ"],
    ["includeBiometrics", true],
    ["includePhone", true],
    ["includeDocuments", true]
];

if (!isNil "_identity" && {_identity isEqualType createHashMap}) then {
    _ov set ["forcedIdentity", _identity];
    private _alias = _identity getOrDefault ["alias", ""];
    if (_alias != "") then { _ov set ["aliasPool", [_alias]]; };
};

if (!isNil "_devices" && {_devices isEqualType []} && {count _devices > 0}) then {
    private _d = _devices select 0;
    _ov set ["forcedPhone", _d];
    _ov set ["contactPool", _d getOrDefault ["contacts", []]];
    private _sms = _d getOrDefault ["sms", []];
    private _tpl = [];
    {
        if (_x isEqualType createHashMap) then {
            _tpl pushBack (_x getOrDefault ["text", ""]);
        } else {
            if (_x isEqualType "") then { _tpl pushBack _x; };
        };
    } forEach _sms;
    _ov set ["smsTemplates", _tpl];
    _ov set ["includeComputer", ((_d getOrDefault ["deviceType", ""]) find "COMPUTER") >= 0 || {(_d getOrDefault ["deviceType", ""]) == "LAPTOP"}];
};

if (!isNil "_docs" && {_docs isEqualType []}) then {
    private _titles = [];
    { if (_x isEqualType createHashMap) then { _titles pushBack (_x getOrDefault ["title", "Document"]); }; } forEach _docs;
    _ov set ["documentTemplates", _titles];
};

private _clusterId = [_data, "clusterId", ""] call BIS_fnc_getFromPairs;
_ov set ["notes", format ["Capture depuis %1 (cluster %2)", [_data, "uid", "?"], _clusterId]];
_ov set ["tags", ["capture", toLower _profile]];

private _model = [_name, _ov, name player] call comspec_sse_fnc_createModel;
[_model, true] call comspec_sse_fnc_saveModel;
_model
