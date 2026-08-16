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
missionNamespace setVariable ["comspec_sse_zeusSaveModelCount", 0];

private _jobs = _targets apply { [_x, _name] };

[
    _jobs,
    {
        params ["_ent", "_name"];
        if (isNull _ent) exitWith {};
        if (isNil {[_ent] call comspec_sse_fnc_getData}) then {
            if !(_ent getVariable ["comspec_sse_generating", false]) then {
                [_ent, "INSURGENT", "DETAILED", "ZEUS"] call comspec_sse_fnc_generateData;
            };
        };
        private _n = missionNamespace getVariable ["comspec_sse_zeusSaveModelCount", 0];
        private _model = [_ent, format ["%1 (%2)", _name, _n + 1]] call comspec_sse_fnc_modelFromEntity;
        if (!isNil "_model") then {
            missionNamespace setVariable ["comspec_sse_zeusSaveModelCount", _n + 1];
        };
    },
    0.15
] call comspec_sse_fnc_queueEntityJobs;

if (hasInterface && {!isNil "CBA_fnc_waitAndExecute"}) then {
    [{
        private _n = missionNamespace getVariable ["comspec_sse_zeusSaveModelCount", 0];
        hint format ["%1 modèle(s) SSE enregistré(s) (mission + profil local)", _n];
    }, [], ((count _jobs) * 0.15) + 0.5] call CBA_fnc_waitAndExecute;
};

deleteVehicle _logic;
true
