/* Apps récentes (touche « applications récentes » de la coque) : rouvrir, fermer une app, tout fermer. */
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _apps = [] call comspec_atak_native_fnc_appList;
private _seen = [];
{ if !(_x in ["LAUNCHER", "RECENTS"] || {_x in _seen}) then { _seen pushBack _x; }; } forEach (+(_s getOrDefault ["history", []]) call { reverse _this; _this });
private _rows = [["section", "Apps récentes", ["Aucune app ouverte.", format ["%1 app(s), de la plus récente à la plus ancienne", count _seen]] select ((count _seen) > 0)]];
{
    private _p = _x;
    private _a = (_apps select { (_x get "page") isEqualTo _p }) param [0, createHashMap];
    // Ligne de liste (composant « row », 3.1.0) : toute la ligne rouvre l'app ; FERMER à part, dessous à droite.
    _rows pushBack ["row", _a getOrDefault ["icon", ""], _a getOrDefault ["name", _p], _a getOrDefault ["section", ""], "",
        compile format ["[{ ['%1'] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;", _p], ["color", _p] call comspec_atak_native_fnc_iconTint];
    _rows pushBack ["buttons", [["FERMER", compile format ["private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; _s set ['history', (_s getOrDefault ['history', []]) - ['%1']]; [{ ['RECENTS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _p], false, true, "Retirer cette app des apps récentes"]]];
} forEach _seen;
if ((count _seen) isEqualTo 0) then {
    _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\ui_apps.paa", "Aucune app ouverte", "Les apps que vous ouvrez apparaîtront ici, la plus récente en tête.", ["TOUTES LES APPS", { [{ ["LAUNCHER"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame; }]];
};
if ((count _seen) > 0) then {
    _rows pushBack ["buttons", [["TOUT FERMER", { private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["history", ["LAUNCHER"]]; [{ ["LAUNCHER", false] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame; }]]];
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
