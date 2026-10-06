/*
    Module « Inspecteur SSE (debug) » : étiquette 3D sur l'entité survolée en Zeus.
    Reposer le module permet de le désactiver.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
if (!hasInterface) exitWith { deleteVehicle _logic; true };

private _apply = {
    params ["_enable", "_drawLinks"];
    missionNamespace setVariable ["comspec_sse_debugInspector", _enable];
    missionNamespace setVariable ["comspec_sse_drawLinks", _drawLinks];
    if (!_enable) exitWith {
        ["Inspecteur SSE désactivé."] call comspec_sse_fnc_zeusNotify;
    };
    missionNamespace setVariable ["comspec_sse_debug", true];

    if (isNil "comspec_sse_debugEh") then {
        comspec_sse_debugEh = addMissionEventHandler ["EachFrame", {
            if !(missionNamespace getVariable ["comspec_sse_debugInspector", false]) exitWith {};
            if (isNull curatorCamera && {isNull (findDisplay 312)}) exitWith {};

            private _target = curatorMouseOver;
            if (_target isEqualType []) then {
                if ((_target param [0, ""]) isEqualTo "OBJECT") then {
                    _target = _target param [1, objNull];
                } else {
                    _target = objNull;
                };
            };
            if (isNull _target) exitWith {};

            private _data = [_target] call comspec_sse_fnc_getData;
            private _txt = "SSE (non généré)";
            private _col = [0.7, 0.7, 0.7, 1];
            if (!(isNil "_data") && {_data isEqualType []}) then {
                private _uid = [_data, "uid", ""] call comspec_sse_fnc_getPair;
                private _profile = [_data, "profile", ""] call comspec_sse_fnc_getPair;
                private _state = [_data, "state", ""] call comspec_sse_fnc_getPair;
                if (_uid isEqualTo "" && {_profile isEqualTo ""}) then {
                    _txt = "SSE (données invalides — régénérer)";
                    _col = [1, 0.45, 0.3, 1];
                } else {
                    _txt = format [
                        "SSE %1 | %2 | %3",
                        if (_uid isEqualTo "") then {"?"} else {_uid},
                        if (_profile isEqualTo "") then {"?"} else {_profile},
                        if (_state isEqualTo "") then {"?"} else {_state}
                    ];
                    _col = [0.45, 0.85, 0.5, 1];
                };
            };
            drawIcon3D ["", _col, ASLToAGL (getPosASL _target) vectorAdd [0,0,2], 0.5, 0.5, 0, _txt, 1, 0.035, "RobotoCondensedBold"];

            if (missionNamespace getVariable ["comspec_sse_drawLinks", false]) then {
                if (isNil "_data" || {!(_data isEqualType [])}) exitWith {};
                {
                    private _tn = _x getOrDefault ["targetNetId", ""];
                    private _sn = _x getOrDefault ["sourceNetId", ""];
                    private _other = objNull;
                    if (_tn == netId _target) then { _other = objectFromNetId _sn; };
                    if (_sn == netId _target) then { _other = objectFromNetId _tn; };
                    if (!isNull _other) then {
                        drawLine3D [ASLToAGL (getPosASL _target) vectorAdd [0,0,1.5], ASLToAGL (getPosASL _other) vectorAdd [0,0,1.5], [0.45,0.85,0.5,0.8]];
                    };
                } forEach ([_target] call comspec_sse_fnc_getLinks);
            };
        }];
    };
    ["Inspecteur SSE activé — survolez une entité en Zeus.\nReposez le module pour le désactiver."] call comspec_sse_fnc_zeusNotify;
};

private _active = missionNamespace getVariable ["comspec_sse_debugInspector", false];
private _drawLinks = _logic getVariable ["DrawLinks", true];

if (_ctx get "zeus") then {
    [
        "Inspecteur SSE (debug)",
        [
            ["CHECK", "Inspecteur actif", "Affiche UID, profil et état au-dessus de l'entité survolée.", !_active],
            ["CHECK", "Afficher les liens 3D", "Trace les relations du graphe vers les autres entités.", _drawLinks]
        ],
        {
            params ["_values", "_args"];
            _values call (_args select 0);
        },
        [_apply],
        if (_active) then { "L'inspecteur est actuellement ACTIF." } else { "L'inspecteur est actuellement inactif." }
    ] call comspec_sse_fnc_uiForm;
} else {
    [true, _drawLinks] call _apply;
};

deleteVehicle _logic;
true
