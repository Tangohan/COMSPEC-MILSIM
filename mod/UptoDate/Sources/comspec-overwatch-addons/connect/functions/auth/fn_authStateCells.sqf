/*
    Découpe GetAuthState. Arma splitString ignore les cellules vides : on conserve
    les champs vides, et l’extension envoie aussi "-" à la place. Les adresses
    internes ne sont jamais un indicatif, un grade ou une unité.
*/
private _raw = ["COMSPECExtension" callExtension ["GetAuthState", []]] call comspec_overwatch_connect_fnc_extResult;
private _parts = [_raw] call comspec_overwatch_connect_fnc_splitKeepEmpty;

private _fnc_cell = {
    params ["_i"];
    if ((count _parts) <= _i) exitWith { "" };
    private _s = _parts select _i;
    if !(_s isEqualType "") then { _s = format ["%1", _s]; };
    _s = trim _s;
    if (_s isEqualTo "-" || {_s isEqualTo ""}) then { "" } else { _s };
};

private _fnc_identity = {
    params ["_i"];
    private _s = [_i] call _fnc_cell;
    if (_s isEqualTo "") exitWith { "" };
    private _l = toLower _s;
    if (((_l find "http://") == 0) || {(_l find "https://") == 0} || {(_l find "/api/") >= 0}) then { "" } else { _s };
};

createHashMapFromArray [
    ["state", [1] call _fnc_cell],
    ["progress", [2] call _fnc_cell],
    ["step", [3] call _fnc_cell],
    ["error", [4] call _fnc_cell],
    ["name", [5] call _fnc_identity],
    ["callsign", [6] call _fnc_identity],
    ["tenant", [7] call _fnc_identity],
    ["unit", [8] call _fnc_identity],
    ["grade", [9] call _fnc_identity],
    ["brand", [10] call _fnc_cell],
    ["slug", [11] call _fnc_identity],
    ["mod", [12] call _fnc_cell],
    ["ext", [13] call _fnc_cell],
    ["min", [14] call _fnc_cell],
    ["avatar", [15] call _fnc_cell],
    ["role", [16] call _fnc_identity],
    ["function", [17] call _fnc_identity]
]
