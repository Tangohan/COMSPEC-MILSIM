#include "..\script_component.hpp"

private _display = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
if (isNull _display) exitWith {};

private _state = call Iceman_fnc_route_getState;
private _route = _state getOrDefault ["route", []];
private _ctrl = _display displayCtrl 198602;
if !(missionNamespace getVariable ["Iceman_ATAK_Route_showMiniInfo", true]) exitWith {
    if (!isNull _ctrl) then {ctrlDelete _ctrl};
};
private _map = controlNull;
{
    private _candidate = _display displayCtrl _x;
    if (!isNull _candidate) exitWith {_map = _candidate};
} forEach [1201, 1202, 1203, 51];

if (_route isEqualTo []) exitWith {
    if (!isNull _ctrl) then {
        ctrlDelete _ctrl;
    };
};
if (isNull _map) exitWith {
    if (!isNull _ctrl) then {
        ctrlDelete _ctrl;
    };
};

if (isNull _ctrl) then {
    _ctrl = _display ctrlCreate ["RscStructuredText", 198602];
    _ctrl ctrlSetBackgroundColor [0.36,0.42,0.45,0.96];
};

private _mapPos = ctrlPosition _map;
private _panelX = (_mapPos # 0) + (_mapPos # 2);
private _panelW = (_mapPos # 2) * 0.49;
_ctrl ctrlSetPosition [
    _panelX,
    (_mapPos # 1) + ((_mapPos # 3) * 0.22),
    _panelW,
    (_mapPos # 3) * 0.25
];
_ctrl ctrlCommit 0;

private _distance = _state getOrDefault ["distance", 0];
private _remaining = _state getOrDefault ["remaining", _distance];
private _mot = _state getOrDefault ["mot", "foot"];
private _speedSource = [player, vehicle player] select (_mot == "vehicle");
private _minFoot = missionNamespace getVariable ["Iceman_ATAK_Route_footMinSpeedKph", 4.5];
private _minVehicle = missionNamespace getVariable ["Iceman_ATAK_Route_vehicleMinSpeedKph", 5];
private _minSpeed = [_minFoot, _minVehicle] select (_mot == "vehicle");
private _speedMS = (((speed _speedSource) max _minSpeed) max 1) / 3.6;

if (!(_route isEqualTo [])) then {
    _remaining = ([getPosATL vehicle player, _route] call Iceman_fnc_route_measureRemaining) # 0;
    _state set ["remaining", _remaining];
};

private _eta = [_remaining / _speedMS] call Iceman_fnc_route_formatEta;

_ctrl ctrlSetStructuredText parseText format [
    "<t font='RobotoCondensed' size='0.82'>Distance: %1 km</t><br/>" +
    "<t font='RobotoCondensed' size='0.82'>Distance Remaining: %2 km</t><br/>" +
    "<t font='RobotoCondensed' size='0.82'>ETA: %3</t>",
    (_distance / 1000) toFixed 1,
    (_remaining / 1000) toFixed 1,
    _eta
];
