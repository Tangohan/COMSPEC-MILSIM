/* App Alertes : ajoute une alerte reçue (ou envoyée) au journal. Params : [type, auteur, position, grille, texte] */
params ["_type", "_who", "_pos", "_grid", ["_text", ""]];
private _log = missionNamespace getVariable ["COMSPEC_ATAK_AlertLog", []];
_log pushBack [_type, _who, _pos, _grid, _text, [daytime, "HH:MM"] call BIS_fnc_timeToString];
while { (count _log) > 25 } do { _log deleteAt 0; };
missionNamespace setVariable ["COMSPEC_ATAK_AlertLog", _log];
[{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ALERTS") then { ["ALERTS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
true
