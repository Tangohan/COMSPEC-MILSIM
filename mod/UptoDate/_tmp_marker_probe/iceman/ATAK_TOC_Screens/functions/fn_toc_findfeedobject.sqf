params [["_type", ""], ["_data", ""]];

private _candidates = switch (_type) do {
    case "helmet": {
        (missionNamespace getVariable ["cTabHcamlist", []]) + allPlayers + allUnits
    };
    case "vehicle": {
        (missionNamespace getVariable ["cTabUAVlist", []]) + vehicles
    };
    default {
        []
    };
};

private _found = objNull;
{
    if (!isNull _x && {str _x == _data}) exitWith {
        _found = _x;
    };
} forEach _candidates;

_found
