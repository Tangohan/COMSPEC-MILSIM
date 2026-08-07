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

private _modelId = _logic getVariable ["ModelId", ""];

if (_modelId != "" && {count _targets > 0}) then {
    {
        [_x, _modelId, "ZEUS"] call comspec_sse_fnc_applyModel;
    } forEach _targets;
    if (hasInterface) then {
        hint format ["Modèle %1 appliqué sur %2 cible(s)", _modelId, count _targets];
    };
} else {
    if (hasInterface) then {
        missionNamespace setVariable ["comspec_sse_zeusPendingTargets", _targets];
        [] call comspec_sse_fnc_openModelDialog;
    };
};

deleteVehicle _logic;
true
