/*
    Retient ou retire un thème de la fiche en cours.

    Args: [_index]  rang du thème dans le référentiel (0 à 16)
*/
params [["_index", -1, [0]]];

if (!hasInterface) exitWith {};

private _catalog = [] call comspec_overwatch_connect_fnc_intelNoteCatalog;
private _themes = _catalog getOrDefault ["themes", []];
private _themesMax = _catalog getOrDefault ["themes_max", 4];
if (_index < 0 || {_index >= (count _themes)}) exitWith {};

(_themes select _index) params ["_code", "_label"];
private _selected = uiNamespace getVariable ["COMSPEC_IntelNote_Themes", []];
if (!(_selected isEqualType [])) then { _selected = []; };

private _alreadyOn = _code in _selected;

// Le plafond est annoncé plutôt que subi : un clic sans effet passe pour un bug.
// La sortie est faite ici, au niveau du script — un exitWith dans un bloc
// « then » ne quitterait que ce bloc et le reste s'exécuterait quand même.
if (!_alreadyOn && {(count _selected) >= _themesMax}) exitWith {
    [
        format ["Quatre thèmes au maximum : retirez-en un avant d’ajouter « %1 ».", _label],
        "tactical",
        "warn"
    ] call comspec_overwatch_connect_fnc_announce;
};

if (_alreadyOn) then {
    _selected = _selected - [_code];
} else {
    _selected pushBack _code;
};

uiNamespace setVariable ["COMSPEC_IntelNote_Themes", _selected];
[] call comspec_overwatch_connect_fnc_intelNoteRefresh;
