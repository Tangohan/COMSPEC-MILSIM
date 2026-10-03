/* Coche / décoche un thème de la fiche FRS (4 au plus). */
params ["_code"];
[] call comspec_atak_native_fnc_frsDraftSave;
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_FrsDraft", createHashMap];
private _themes = +(_draft getOrDefault ["themes", []]);
private _max = ([] call comspec_overwatch_connect_fnc_intelNoteCatalog) getOrDefault ["themes_max", 4];
if (_code in _themes) then { _themes deleteAt (_themes find _code); } else {
    if ((count _themes) >= _max) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_FrsHint", [format ["%1 thèmes au maximum.", _max], true]]; };
    _themes pushBack _code;
};
_draft set ["themes", _themes];
uiNamespace setVariable ["COMSPEC_ATAK_FrsDraft", _draft];
[{ ["FRS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
