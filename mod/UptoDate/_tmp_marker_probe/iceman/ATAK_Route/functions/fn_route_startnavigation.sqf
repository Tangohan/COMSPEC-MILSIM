#include "..\script_component.hpp"

private _state = call Iceman_fnc_route_getState;
private _start = _state getOrDefault ["start", []];
private _end = _state getOrDefault ["end", []];
private _waypoints = +(_state getOrDefault ["waypoints", []]);
private _mot = _state getOrDefault ["mot", "foot"];

if (_start isEqualTo []) then {
    _start = getPosATL vehicle player;
    _state set ["start", _start];
};
if (_end isEqualTo []) exitWith {
    ["ROUTE", "Set an end point first.", 4] call cTab_fnc_addNotification;
    call Iceman_fnc_route_updatePanel;
};

private _points = [_start] + _waypoints + [_end];
private _label = ["concealed foot", "road vehicle"] select (_mot == "vehicle");
private _viaText = if ((count _waypoints) > 0) then {format [" via %1 waypoint(s)", count _waypoints]} else {""};
["ROUTE", format ["Planning %1 route%2...", _label, _viaText], 3] call cTab_fnc_addNotification;

private _mergeLegs = {
    params ["_points", "_planner"];
    private _route = [];
    private _distance = 0;
    private _turns = [];
    private _failedLeg = -1;

    for "_i" from 1 to ((count _points) - 1) do {
        private _legStart = _points # (_i - 1);
        private _legEnd = _points # _i;
        private _result = [_legStart, _legEnd] call _planner;
        _result params [["_legRoute", []], ["_legDistance", 0], ["_legTurns", []]];

        if (_legRoute isEqualTo []) exitWith {
            _failedLeg = _i;
        };

        private _appendRoute = +_legRoute;
        if (!(_route isEqualTo []) && {(count _appendRoute) > 0}) then {
            _appendRoute deleteAt 0;
        };
        _route append _appendRoute;

        {
            private _turn = +_x;
            if ((count _turn) > 2) then {
                _turn set [2, (_turn # 2) + _distance];
            };
            _turns pushBack _turn;
        } forEach _legTurns;

        _distance = _distance + _legDistance;
    };

    [_route, _distance, _turns, _failedLeg]
};

if (_mot != "vehicle") exitWith {
    if (_state getOrDefault ["planning", false]) exitWith {
        ["ROUTE", "Foot route is already planning.", 3] call cTab_fnc_addNotification;
    };

    private _planningId = diag_tickTime;
    _state set ["planning", true];
    _state set ["planningId", _planningId];
    _state set ["active", false];
    _state set ["route", []];
    _state set ["turns", []];
    _state set ["distance", 0];
    _state set ["remaining", 0];
    call Iceman_fnc_route_updatePanel;

    [_points, _waypoints, _planningId] spawn {
        params ["_points", "_waypoints", "_planningId"];
        uiSleep 0.01;

        private _mergeLegs = {
            params ["_points", "_planner"];
            private _route = [];
            private _distance = 0;
            private _turns = [];
            private _failedLeg = -1;

            for "_i" from 1 to ((count _points) - 1) do {
                private _legStart = _points # (_i - 1);
                private _legEnd = _points # _i;
                private _result = [_legStart, _legEnd] call _planner;
                _result params [["_legRoute", []], ["_legDistance", 0], ["_legTurns", []]];

                if (_legRoute isEqualTo []) exitWith {
                    _failedLeg = _i;
                };

                private _appendRoute = +_legRoute;
                if (!(_route isEqualTo []) && {(count _appendRoute) > 0}) then {
                    _appendRoute deleteAt 0;
                };
                _route append _appendRoute;

                {
                    private _turn = +_x;
                    if ((count _turn) > 2) then {
                        _turn set [2, (_turn # 2) + _distance];
                    };
                    _turns pushBack _turn;
                } forEach _legTurns;

                _distance = _distance + _legDistance;
            };

            [_route, _distance, _turns, _failedLeg]
        };

        private _result = [_points, {_this call Iceman_fnc_route_findConcealedPath}] call _mergeLegs;
        _result params ["_route", "_distance", "_turns", "_failedLeg"];

        private _state = call Iceman_fnc_route_getState;
        if ((_state getOrDefault ["planningId", -1]) != _planningId) exitWith {};
        if ((_state getOrDefault ["mot", "foot"]) != "foot") exitWith {
            _state set ["planning", false];
            _state set ["planningId", -1];
        };

        private _currentPoints = [_state getOrDefault ["start", []]] + (_state getOrDefault ["waypoints", []]) + [_state getOrDefault ["end", []]];
        if (!(_currentPoints isEqualTo _points)) exitWith {
            _state set ["planning", false];
            _state set ["planningId", -1];
        };

        _state set ["planning", false];
        _state set ["planningId", -1];

        if (_failedLeg > -1 || {_route isEqualTo []}) exitWith {
            _state set ["active", false];
            _state set ["route", []];
            _state set ["turns", []];
            _state set ["distance", 0];
            _state set ["remaining", 0];
            call Iceman_fnc_route_updatePanel;
            private _legText = if (_failedLeg > -1) then {format [" on leg %1", _failedLeg]} else {""};
            ["ROUTE", format ["No concealed foot route found%1.", _legText], 5] call cTab_fnc_addNotification;
        };

        _state set ["route", _route];
        _state set ["turns", _turns];
        _state set ["distance", _distance];
        _state set ["active", true];
        _state set ["nextTurn", 0];
        _state set ["lastPromptTurn", -1];
        call Iceman_fnc_route_updatePanel;

        private _remaining = ([getPosATL vehicle player, _route] call Iceman_fnc_route_measureRemaining) # 0;
        private _minFoot = missionNamespace getVariable ["Iceman_ATAK_Route_footMinSpeedKph", 4.5];
        private _eta = [_remaining / ((((speed player) max _minFoot) max 1) / 3.6)] call Iceman_fnc_route_formatEta;
        ["ROUTE", format ["Route ready: %1 km, ETA %2.", (_remaining / 1000) toFixed 1, _eta], 5] call cTab_fnc_addNotification;
    };
};

private _result = [_points, {_this call Iceman_fnc_route_findPath}] call _mergeLegs;
_result params ["_route", "_distance", "_turns", "_failedLeg"];

if (_failedLeg > -1 || {_route isEqualTo []}) exitWith {
    _state set ["active", false];
    _state set ["route", []];
    _state set ["turns", []];
    _state set ["distance", 0];
    _state set ["remaining", 0];
    call Iceman_fnc_route_updatePanel;
    private _legText = if (_failedLeg > -1) then {format [" on leg %1", _failedLeg]} else {""};
    ["ROUTE", format ["No %1 route found%2.", _label, _legText], 5] call cTab_fnc_addNotification;
};

_state set ["route", _route];
_state set ["turns", _turns];
_state set ["distance", _distance];
_state set ["active", true];
_state set ["nextTurn", 0];
_state set ["lastPromptTurn", -1];
call Iceman_fnc_route_updatePanel;

private _speedSource = [player, vehicle player] select (_mot == "vehicle");
private _minFoot = missionNamespace getVariable ["Iceman_ATAK_Route_footMinSpeedKph", 4.5];
private _minVehicle = missionNamespace getVariable ["Iceman_ATAK_Route_vehicleMinSpeedKph", 5];
private _minSpeed = [_minFoot, _minVehicle] select (_mot == "vehicle");
private _remaining = ([getPosATL vehicle player, _route] call Iceman_fnc_route_measureRemaining) # 0;
private _eta = [_remaining / ((((speed _speedSource) max _minSpeed) max 1) / 3.6)] call Iceman_fnc_route_formatEta;
["ROUTE", format ["Route ready: %1 km, ETA %2.", (_remaining / 1000) toFixed 1, _eta], 5] call cTab_fnc_addNotification;
