/*
    Crée un repère web compatible Athena depuis l'ATAK in-game.
    Le marqueur est posé localement sur la carte du joueur, puis envoyé
    directement au site sans dépendre du miroir BCE/cTab.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

private _position = [];
private _cursorTarget = cursorTarget;
if (!isNull _cursorTarget) then {
    _position = getPosWorld _cursorTarget;
};
if (_position isEqualTo []) then {
    _position = screenToWorld [0.5, 0.5];
};
if (_position isEqualTo [0, 0, 0]) then {
    _position = getPosWorld player;
};
if (!(_position isEqualType []) || {(count _position) < 2}) exitWith { false };

private _grid = mapGridPosition _position;
private _callSign = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_callSign isEqualTo "") then { _callSign = name player; };

private _markerName = format ["athena_webmk_%1_%2", round (diag_tickTime * 10), floor (random 100000)];
private _label = format ["%1 %2", _callSign, _grid];

private _marker = createMarkerLocal [_markerName, _position];
_marker setMarkerTypeLocal "mil_dot";
_marker setMarkerColorLocal "ColorYellow";
_marker setMarkerTextLocal _label;
_marker setMarkerAlphaLocal 1;

[_markerName, _position, "mil_dot", "ColorYellow", _label, "athena_web_marker"] call comspec_overwatch_connect_fnc_sendLocalTacticalMarker;

[format ["Repère web créé — %1", _grid], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;

[
    {
        params ["_name"];
        deleteMarkerLocal _name;
    },
    [_markerName],
    300
] call CBA_fnc_waitAndExecute;

true
