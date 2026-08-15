params ["_map"];

private _state = call Iceman_fnc_drone_getState;
private _contacts = _state getOrDefault ["contacts", []];
if (_contacts isEqualTo []) exitWith {};

private _now = diag_tickTime;
private _fresh = _contacts select {(_now - (_x param [4, 0])) < 1800};
if ((count _fresh) != (count _contacts)) then {
    _state set ["contacts", _fresh];
    _contacts = _fresh;
};

{
    _x params ["_netId", "_label", "_pos", "_kind", "_time"];
    private _color = switch (_kind) do {
        case "ENY": {[1,0.05,0.04,1]};
        case "CIV": {[0.1,0.95,0.25,1]};
        default {[1,0.65,0.05,1]};
    };
    _map drawIcon [
        "\A3\ui_f\data\map\markers\military\dot_CA.paa",
        _color,
        _pos,
        30,
        30,
        0,
        _label,
        1,
        0.045,
        "RobotoCondensed",
        "right"
    ];
} forEach _contacts;
