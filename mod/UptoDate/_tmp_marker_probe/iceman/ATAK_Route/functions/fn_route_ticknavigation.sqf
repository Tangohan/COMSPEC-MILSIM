#include "..\script_component.hpp"

private _state = call Iceman_fnc_route_getState;
if !(_state getOrDefault ["active", false]) exitWith {};

private _route = _state getOrDefault ["route", []];
private _turns = _state getOrDefault ["turns", []];
if ((count _route) < 2) exitWith {};

private _pos = getPosATL vehicle player;
private _measure = [_pos, _route] call Iceman_fnc_route_measureRemaining;
_measure params ["_remaining", "_distanceAtBest"];

private _mot = _state getOrDefault ["mot", "foot"];
private _speedSource = [player, vehicle player] select (_mot == "vehicle");
private _minSpeed = [4.5, 5] select (_mot == "vehicle");
private _speedMS = ((((speed _speedSource) max _minSpeed) max 1) / 3.6);
private _eta = [_remaining / _speedMS] call Iceman_fnc_route_formatEta;

if (diag_tickTime > (_state getOrDefault ["nextPanelUpdate", 0])) then {
    _state set ["remaining", _remaining];
    _state set ["nextPanelUpdate", diag_tickTime + 5];
    call Iceman_fnc_route_updatePanel;
};

private _nextTurn = _state getOrDefault ["nextTurn", 0];
while {_nextTurn < count _turns && {((_turns # _nextTurn) # 2) < (_distanceAtBest - 15)}} do {
    _nextTurn = _nextTurn + 1;
};
_state set ["nextTurn", _nextTurn];

if (_nextTurn < count _turns) then {
    private _turn = _turns # _nextTurn;
    _turn params ["_dir", "_turnPos", "_turnDistance"];
    private _metersToTurn = _turnDistance - _distanceAtBest;
    if (_metersToTurn <= 50 && {_metersToTurn >= -10} && {(_state getOrDefault ["lastPromptTurn", -1]) != _nextTurn}) then {
        private _bearingToTurn = _pos getDir _turnPos;
        private _playerBearing = getDir vehicle player;
        private _relative = ((_bearingToTurn - _playerBearing + 540) mod 360) - 180;
        private _relativeText = if (abs _relative > 100) then {"ahead after reorienting"} else {format ["%1m", round (_metersToTurn max 0)]};
        ["ROUTE", format ["Turn %1 in %2. ETA %3.", _dir, _relativeText, _eta], 4] call cTab_fnc_addNotification;
        _state set ["lastPromptTurn", _nextTurn];
    };
};

if (_remaining < 25) then {
    _state set ["active", false];
    ["ROUTE", "Arrived at route end point.", 4] call cTab_fnc_addNotification;
};
