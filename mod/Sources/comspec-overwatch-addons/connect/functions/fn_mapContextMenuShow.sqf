/*
    Menu contextuel (clic droit) sur la carte terrain native de la tablette Athena (idc 9410).
    Pose un marqueur coloré au point cliqué — mêmes types/couleurs que le double-clic existant
    (comspec_overwatch_connect_fnc_placeMarkerFromTablet). Un seul menu ouvert à la fois.

    Params: [_world, _screenX, _screenY]
*/
params [["_world", [0, 0, 0], [[]]], ["_screenX", 0.5, [0]], ["_screenY", 0.5, [0]]];

if (!hasInterface) exitWith {};
private _disp = findDisplay 9974;
if (isNull _disp) exitWith {};

[] call comspec_overwatch_connect_fnc_mapContextMenuClose;

private _wx = _world select 0;
private _wy = _world select 1;

private _items = [
    ["Marqueur rouge ici", "mil_dot", "ColorRed"],
    ["Marqueur bleu ici", "mil_dot", "ColorBlue"],
    ["Marqueur vert ici", "mil_dot", "ColorGreen"],
    ["Objectif ici", "mil_objective", "ColorYellow"],
    ["Fermer", "", ""]
];

private _itemH = 0.026 * safezoneH;
private _menuW = 0.13 * safezoneW;
private _menuH = _itemH * (count _items);
private _posX = (_screenX min (safezoneX + safezoneW - _menuW)) max safezoneX;
private _posY = (_screenY min (safezoneY + safezoneH - _menuH)) max safezoneY;

private _created = [];

private _bg = _disp ctrlCreate ["RscText", 9440];
_bg ctrlSetPosition [_posX, _posY, _menuW, _menuH];
_bg ctrlSetBackgroundColor [0.03, 0.06, 0.08, 0.97];
_bg ctrlCommit 0;
_created pushBack _bg;

{
    _x params ["_label", "_type", "_color"];
    private _idc = 9441 + _forEachIndex;
    private _btn = _disp ctrlCreate ["RscButton", _idc];
    _btn ctrlSetPosition [_posX, _posY + (_forEachIndex * _itemH), _menuW, _itemH];
    _btn ctrlSetText _label;
    _btn ctrlSetBackgroundColor [0.06, 0.14, 0.18, 0.95];
    _btn ctrlSetBackgroundColorFocused [0.1, 0.22, 0.28, 1];
    _btn ctrlSetActiveColor [1, 1, 1, 1];
    private _action = if (_type isEqualTo "") then {
        "[] call comspec_overwatch_connect_fnc_mapContextMenuClose;"
    } else {
        format [
            "[%1, %2, ""%3"", ""%4""] call comspec_overwatch_connect_fnc_placeMarkerFromTablet; [] call comspec_overwatch_connect_fnc_mapContextMenuClose;",
            _wx, _wy, _type, _color
        ]
    };
    _btn setVariable ["COMSPEC_MapContextAction", _action];
    _btn ctrlAddEventHandler ["ButtonClick", {
        private _act = (_this select 0) getVariable ["COMSPEC_MapContextAction", ""];
        if (_act != "") then { call compile _act; };
    }];
    _btn ctrlCommit 0;
    _created pushBack _btn;
} forEach _items;

uiNamespace setVariable ["COMSPEC_MapContextMenuCtrls", _created];
