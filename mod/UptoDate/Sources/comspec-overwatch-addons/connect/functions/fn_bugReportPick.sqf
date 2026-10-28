/*
    Formulaire de signalement : choisit la catégorie (bouton en vert, les autres sombres).
    Params : [_index] dans COMSPEC_BugReportCats (fn_bugReportShow).
*/
params [["_index", 0, [0]]];
private _disp = uiNamespace getVariable ["COMSPEC_BugReport_Display", displayNull];
if (isNull _disp) exitWith {};
private _cats = uiNamespace getVariable ["COMSPEC_BugReportCats", []];
_index = _index max 0 min (((count _cats) - 1) max 0);
uiNamespace setVariable ["COMSPEC_BugReportCat", _index];
{
    private _c = _disp displayCtrl (9811 + _forEachIndex);
    if (!isNull _c) then {
        _c ctrlSetText ((_x select 0) call comspec_overwatch_connect_fnc_tr);
        if (_forEachIndex isEqualTo _index) then {
            _c ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.96];
            _c ctrlSetTextColor [0.025, 0.030, 0.027, 1];
        } else {
            _c ctrlSetBackgroundColor [0.070, 0.082, 0.072, 0.96];
            _c ctrlSetTextColor [0.90, 0.94, 0.91, 1];
        };
    };
} forEach _cats;
