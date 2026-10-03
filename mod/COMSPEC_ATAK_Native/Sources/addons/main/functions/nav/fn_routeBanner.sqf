/* Met à jour le bandeau de guidage GPS de la carte (manœuvre, distance, reste, arrivée). */
disableSerialization;
(uiNamespace getVariable ["COMSPEC_ATAK_RouteBanner", []]) params [["_g1", controlNull], ["_g2", controlNull], ["_g3", controlNull]];
if (isNull _g1) exitWith {};
private _g = [] call comspec_atak_native_fnc_routeGuide;
if ((count _g) isEqualTo 0) exitWith {
    uiNamespace setVariable ["COMSPEC_ATAK_RouteBanner", []];
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MAP") then { ["MAP"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
};
_g params ["_txt", "_dn", "_icon", "_left", "_eta", "_label"];
private _fmt = { params ["_m"]; switch (true) do { case (_m >= 1000): { format ["%1 km", (_m / 1000) toFixed 1] }; case (_m >= 100): { format ["%1 m", (round (_m / 10)) * 10] }; default { format ["%1 m", round _m] }; } };
if (!isNull _g3) then { _g3 ctrlSetText format ["\z\comspec_atak_native\addons\main\data\%1.paa", _icon]; };
_g1 ctrlSetStructuredText parseText format ["<t size='1.25' font='RobotoCondensedBold'>%1</t><br/><t size='1.0'>%2</t>", [_dn] call _fmt, _txt];
private _arr = dayTime + _eta / 3600;
_g2 ctrlSetStructuredText parseText format ["<t size='0.85' color='#5cc76b'>%1 min</t><t size='0.85'> · %2 · arrivée %3</t><t size='0.8' color='#8a9a93'>  %4</t>",
    ceil (_eta / 60), [_left] call _fmt, [_arr mod 24, "HH:MM"] call BIS_fnc_timeToString, _label];
