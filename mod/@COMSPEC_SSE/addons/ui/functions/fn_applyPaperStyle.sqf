/*
    Applique l’aspect papier (propre / taché / froissé / jauni) sur la visionneuse.
*/
params [
    ["_display", displayNull, [displayNull]],
    ["_style", "clean", [""]]
];

if (isNull _display) exitWith { false };
_style = toLower _style;

private _paper = _display displayCtrl 93019;
private _header = _display displayCtrl 93022;
private _footerBg = _display displayCtrl 93023;
private _stainA = _display displayCtrl 93024;
private _stainB = _display displayCtrl 93025;
private _fold = _display displayCtrl 93026;

private _paperColor = [0.94, 0.91, 0.84, 0.98];
private _headerColor = [0.88, 0.84, 0.74, 1];
private _footerColor = [0.86, 0.82, 0.72, 1];
private _showStain = false;
private _showFold = false;

switch (_style) do {
    case "stained": {
        _paperColor = [0.90, 0.82, 0.68, 0.98];
        _headerColor = [0.84, 0.74, 0.58, 1];
        _footerColor = [0.80, 0.70, 0.54, 1];
        _showStain = true;
    };
    case "crumpled": {
        _paperColor = [0.91, 0.86, 0.76, 0.98];
        _headerColor = [0.85, 0.79, 0.68, 1];
        _footerColor = [0.82, 0.76, 0.64, 1];
        _showFold = true;
    };
    case "aged": {
        _paperColor = [0.88, 0.80, 0.58, 0.98];
        _headerColor = [0.80, 0.70, 0.48, 1];
        _footerColor = [0.76, 0.66, 0.44, 1];
        _showStain = true;
    };
    default { };
};

if (!(isNull _paper)) then { _paper ctrlSetBackgroundColor _paperColor; };
if (!(isNull _header)) then { _header ctrlSetBackgroundColor _headerColor; };
if (!(isNull _footerBg)) then { _footerBg ctrlSetBackgroundColor _footerColor; };

{
    _x params ["_ctrl", "_show"];
    if (!(isNull _ctrl)) then {
        _ctrl ctrlShow _show;
        _ctrl ctrlSetFade (if (_show) then { 0 } else { 1 });
        _ctrl ctrlCommit 0;
    };
} forEach [
    [_stainA, _showStain],
    [_stainB, _showStain],
    [_fold, _showFold]
];

true
