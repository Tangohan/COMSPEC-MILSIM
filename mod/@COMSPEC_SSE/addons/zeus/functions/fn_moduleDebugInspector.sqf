params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

if (!_activated || {!hasInterface}) exitWith { true };

private _drawLinks = _logic getVariable ["DrawLinks", true];
missionNamespace setVariable ["comspec_sse_debugInspector", true];
missionNamespace setVariable ["comspec_sse_debug", true];
missionNamespace setVariable ["comspec_sse_drawLinks", _drawLinks];

if (isNil "comspec_sse_debugEh") then {
    comspec_sse_debugEh = addMissionEventHandler ["EachFrame", {
        if !(missionNamespace getVariable ["comspec_sse_debugInspector", false]) exitWith {};
        if (isNull curatorCamera && {isNull (findDisplay 312)}) exitWith {};

        private _target = curatorMouseOver;
        if (_target isEqualType []) then {
            if ((_target select 0) isEqualTo "OBJECT") then {
                _target = _target select 1;
            } else {
                _target = objNull;
            };
        };
        if (isNull _target) exitWith {};

        private _data = [_target] call comspec_sse_fnc_getData;
        private _txt = "SSE (non généré)";
        if (!(isNil "_data") && {_data isEqualType []}) then {
            private _uid = [_data, "uid", ""] call comspec_sse_fnc_getPair;
            private _profile = [_data, "profile", ""] call comspec_sse_fnc_getPair;
            private _state = [_data, "state", ""] call comspec_sse_fnc_getPair;
            if (_uid isEqualTo "" && {_profile isEqualTo ""}) then {
                _txt = "SSE (données invalides — régénérer)";
            } else {
                _txt = format [
                    "SSE %1 | %2 | %3",
                    if (_uid isEqualTo "") then {"?"} else {_uid},
                    if (_profile isEqualTo "") then {"?"} else {_profile},
                    if (_state isEqualTo "") then {"?"} else {_state}
                ];
            };
        };
        drawIcon3D ["", [0.2, 1, 0.2, 1], ASLToAGL (getPosASL _target) vectorAdd [0,0,2], 0.5, 0.5, 0, _txt, 1, 0.035, "PuristaMedium"];

        if (missionNamespace getVariable ["comspec_sse_drawLinks", false]) then {
            if (isNil "_data" || {!(_data isEqualType [])}) exitWith {};
            private _links = [_target] call comspec_sse_fnc_getLinks;
            {
                private _tn = _x getOrDefault ["targetNetId", ""];
                private _sn = _x getOrDefault ["sourceNetId", ""];
                private _other = objNull;
                if (_tn == netId _target) then { _other = objectFromNetId _sn; };
                if (_sn == netId _target) then { _other = objectFromNetId _tn; };
                if (!isNull _other) then {
                    drawLine3D [ASLToAGL (getPosASL _target) vectorAdd [0,0,1.5], ASLToAGL (getPosASL _other) vectorAdd [0,0,1.5], [0,1,0.3,0.8]];
                };
            } forEach _links;
        };
    }];
};

hint "SSE Debug Inspector ACTIVÉ — pointez une entité en Zeus.";
deleteVehicle _logic;
true
