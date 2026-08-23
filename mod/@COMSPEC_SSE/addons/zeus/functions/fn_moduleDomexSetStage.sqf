params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

if (!_activated) exitWith { true };

private _obj = objNull;
private _attached = _logic getVariable ["bis_fnc_curatorAttachObject_object", objNull];
if (!isNull _attached) then { _obj = _attached; };
if (isNull _obj) then {
    private _sync = synchronizedObjects _logic;
    if (count _sync > 0) then { _obj = _sync select 0; };
};
if (isNull _obj) then {
    private _pool = [] call comspec_sse_fnc_curatorSelectedObjects;
    { if (!(_x isKindOf "CAManBase")) exitWith { _obj = _x; }; } forEach _pool;
};

private _stageArg = _logic getVariable ["Stage", ""];
if (_stageArg isNotEqualTo "" && {!isNull _obj}) then {
    [_obj, _stageArg, true] call comspec_sse_fnc_domexSetStage;
    if (hasInterface) then {
        hint "Palier d’accès mis à jour.";
    };
} else {
    private _open = uiNamespace getVariable ["COMSPEC_SSE_DomexOpenStage", {}];
    if (_open isEqualTo {}) then {
        [] call comspec_sse_fnc_registerZenDomexLive;
        _open = uiNamespace getVariable ["COMSPEC_SSE_DomexOpenStage", {}];
    };
    [_obj, getPosATL _logic] call _open;
};

deleteVehicle _logic;
true
