/*
    Palette ECOTI selon le thème choisi (lisibilité sous JVN).
    Retourne un HashMap-like via tableau de paires, ou lecture directe des clés.
    Clés utiles : allies, vehicles, outline, building, floor, unit, badge, glow
*/
private _theme = missionNamespace getVariable ["comspec_overwatch_ecoti_theme", "nvg"];
if (!(_theme isEqualType "")) then { _theme = "nvg"; };
_theme = toLower _theme;

private _allies = [0.55, 1, 0.85, 1];
private _vehicles = [0.55, 0.9, 1, 1];
private _outline = [1, 1, 0.45, 0.9];
private _building = [0.45, 1, 0.75, 0.95];
private _floor = [1, 0.95, 0.35, 0.95];
private _unit = [0.7, 1, 0.9, 0.95];
private _badge = [0.95, 1, 0.98, 1];
private _glow = [0.85, 1, 0.95, 0.55];

switch (_theme) do {
    case "lime": {
        _allies = [0.45, 1, 0.4, 1];
        _vehicles = [0.35, 0.95, 0.55, 1];
        _outline = [0.85, 1, 0.25, 0.9];
        _building = [0.35, 1, 0.45, 0.95];
        _floor = [1, 0.92, 0.2, 0.95];
        _unit = [0.5, 1, 0.45, 0.95];
        _badge = [0.9, 1, 0.85, 1];
        _glow = [0.4, 1, 0.35, 0.5];
    };
    case "amber": {
        _allies = [1, 0.85, 0.35, 1];
        _vehicles = [1, 0.7, 0.25, 1];
        _outline = [1, 0.9, 0.2, 0.9];
        _building = [1, 0.8, 0.3, 0.95];
        _floor = [1, 0.95, 0.55, 0.95];
        _unit = [1, 0.88, 0.4, 0.95];
        _badge = [1, 0.95, 0.8, 1];
        _glow = [1, 0.75, 0.2, 0.5];
    };
    case "white": {
        _allies = [1, 1, 1, 1];
        _vehicles = [0.9, 0.95, 1, 1];
        _outline = [1, 1, 1, 0.9];
        _building = [0.95, 1, 0.98, 0.95];
        _floor = [1, 1, 0.75, 0.95];
        _unit = [1, 1, 1, 0.95];
        _badge = [1, 1, 1, 1];
        _glow = [1, 1, 1, 0.45];
    };
    case "blue": {
        _allies = [0.45, 0.85, 1, 1];
        _vehicles = [0.35, 0.7, 1, 1];
        _outline = [0.55, 0.9, 1, 0.9];
        _building = [0.4, 0.85, 1, 0.95];
        _floor = [0.85, 0.95, 1, 0.95];
        _unit = [0.55, 0.9, 1, 0.95];
        _badge = [0.9, 0.97, 1, 1];
        _glow = [0.4, 0.8, 1, 0.5];
    };
    default {
        // nvg — cyan très clair, conçu pour survivre au post-process JVN
        _allies = [0.75, 1, 0.95, 1];
        _vehicles = [0.7, 0.95, 1, 1];
        _outline = [1, 1, 0.7, 0.95];
        _building = [0.65, 1, 0.9, 0.98];
        _floor = [1, 1, 0.55, 0.98];
        _unit = [0.8, 1, 0.95, 0.98];
        _badge = [1, 1, 1, 1];
        _glow = [0.7, 1, 0.95, 0.6];
    };
};

createHashMapFromArray [
    ["allies", _allies],
    ["vehicles", _vehicles],
    ["outline", _outline],
    ["building", _building],
    ["floor", _floor],
    ["unit", _unit],
    ["badge", _badge],
    ["glow", _glow]
]
