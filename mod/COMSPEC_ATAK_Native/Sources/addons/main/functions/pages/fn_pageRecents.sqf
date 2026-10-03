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
    _rows pushBack ["person", _a getOrDefault ["icon", ""], format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2</t>", _a getOrDefault ["name", _p], _a getOrDefault ["section", ""]],
        [["OUVRIR", compile format ["[{ ['%1'] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;", _p], true],
         ["FERMER", compile format ["private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; _s set ['history', (_s getOrDefault ['history', []]) - ['%1']]; [{ ['RECENTS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _p]]],
        [0.90, 0.94, 0.91, 1]];
} forEach _seen;
if ((count _seen) > 0) then {
    _rows pushBack ["buttons", [["TOUT FERMER", { private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["history", ["LAUNCHER"]]; [{ ["LAUNCHER", false] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame; }]]];
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
