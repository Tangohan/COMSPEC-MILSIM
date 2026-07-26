/*
    Crée un marqueur carte depuis la vue radar tablette.
    Params: [_wx, _wy, _type?, _color?]
      _type  : type marqueur Arma (défaut mil_dot) — mil_triangle, mil_objective, hd_dot…
      _color : ColorRed / ColorBlue / ColorGreen / ColorYellow / ColorWhite…
*/
params [
    ["_wx", 0, [0]],
    ["_wy", 0, [0]],
    ["_type", "mil_dot", [""]],
    ["_color", "ColorRed", [""]]
];
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (_wx isEqualTo 0 && {_wy isEqualTo 0}) exitWith {};

private _allowedTypes = [
    "mil_dot", "mil_triangle", "mil_box", "mil_circle", "mil_objective",
    "mil_marker", "mil_join", "hd_dot", "hd_warning", "hd_end"
];
private _allowedColors = [
    "ColorRed", "ColorBlue", "ColorGreen", "ColorYellow", "ColorWhite",
    "ColorBlack", "ColorOrange", "ColorPink", "ColorBrown", "ColorKhaki"
];
if (!(_type in _allowedTypes)) then { _type = "mil_dot"; };
if (!(_color in _allowedColors)) then { _color = "ColorRed"; };

private _z = getTerrainHeightASL [_wx, _wy];
private _callSign = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_callSign isEqualTo "") then { _callSign = name player; };

private _name = format ["comspec_tabletmk_%1_%2", round (diag_tickTime * 10), floor (random 100000)];
private _marker = createMarker [_name, [_wx, _wy, _z]];
_marker setMarkerType _type;
_marker setMarkerColor _color;
_marker setMarkerText _callSign;
_marker setMarkerAlpha 1;

private _grid = [[_wx, _wy, _z]] call comspec_overwatch_connect_fnc_gridPosition;
["COMSPEC_Info", [format ["Marqueur posé — %1 (%2)", _callSign, _grid]]] call comspec_overwatch_connect_fnc_showNotification;
[format ["[Marqueur] %1 a posé un marqueur (%2) depuis la tablette", _callSign, _grid], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;

// Rapport unique et déterministe vers Athena depuis la machine à l'origine du placement.
// Le préfixe "comspec_tabletmk_" est exclu du miroir générique (fn_syncMapMarker.sqf) car ce
// marqueur est global (createMarker) : sans cet appel direct, chaque client connecté recevrait
// MarkerCreated pour sa propre réplique et relaierait N fois le même marqueur vers Athena.
[_name, false] call comspec_overwatch_connect_fnc_syncMapMarker;
