params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

if (!_activated) exitWith { true };

private _targets = synchronizedObjects _logic;
if (count _targets == 0) then {
    private _attached = _logic getVariable ["bis_fnc_curatorAttachObject_object", objNull];
    if (!isNull _attached) then { _targets = [_attached]; };
};

private _name = _logic getVariable ["ModelName", "Mon modèle SSE"];
private _saved = 0;

{
    if (isNil {[_x] call comspec_sse_fnc_getData}) then {
        [_x, "INSURGENT", "DETAILED", "ZEUS"] call comspec_sse_fnc_generateData;
    };
    private _model = [_x, format ["%1 (%2)", _name, _saved + 1]] call comspec_sse_fnc_modelFromEntity;
    if (!isNil "_model") then { _saved = _saved + 1; };
} forEach _targets;

if (hasInterface) then {
    hint format ["%1 modèle(s) SSE enregistré(s) (mission + profil local)", _saved];
};

deleteVehicle _logic;
true
