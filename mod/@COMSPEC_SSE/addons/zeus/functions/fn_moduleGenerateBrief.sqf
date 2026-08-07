params ["_logic", "_units", "_activated"];
if (!_activated) exitWith { true };
private _brief = _logic getVariable ["Brief", "cellule logistique de 5 personnes"];
private _pack = _logic getVariable ["ScenarioPack", ""];
private _radius = _logic getVariable ["Radius", 40];
if (_pack != "") then {
    [_pack, _logic, _radius] call comspec_sse_fnc_loadScenarioPack;
} else {
    [_brief, _logic, _radius] call comspec_sse_fnc_generateFromBrief;
};
if (!isNull _logic) then { deleteVehicle _logic; };
true
