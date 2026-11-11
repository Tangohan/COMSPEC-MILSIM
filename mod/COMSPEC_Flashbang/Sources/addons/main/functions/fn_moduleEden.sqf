/*
    comspec_fb_fnc_moduleEden : module « COMSPEC Flashbang » (Eden, et Zeus vanilla avec les valeurs par défaut).
    Mode grenade : engin posé à la position du module, N détonations. Mode zone : effet sur toutes les unités dans
    le rayon, sans grenade. Mode drill : grenade d'entraînement (N pops). Déclenchement : début de mission + délai,
    ou activation d'un déclencheur synchronisé.
    [_logic, _units, _activated] call comspec_fb_fnc_moduleEden
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
if (!isServer || {!_activated} || {isNull _logic}) exitWith {};
// Valeurs lues prudemment : nombre ou texte selon l'éditeur, booléen ou 0/1.
private _num = {
    params ["_name", "_def"];
    private _v = _logic getVariable [_name, _def];
    if (_v isEqualType "") then { _v = parseNumber _v };
    [_def, _v] select (_v isEqualType 0)
};
private _mode = _logic getVariable ["Mode", "grenade"];
if !(_mode isEqualType "") then { _mode = "grenade" };
private _n = (round (["Bursts", 3] call _num) max 1) min 9;
private _delay = (["Delay", 0] call _num) max 0;
private _radius = ["Radius", 10] call _num;
private _strength = ["Strength", 0.8] call _num;
private _fx = _logic getVariable ["ShowFx", true];
if (_fx isEqualType 0) then { _fx = _fx > 0 };
if !(_fx isEqualType true) then { _fx = true };
private _pos = (getPosASL _logic) vectorAdd [0, 0, 0.05];
private _vdu = [[0, 1, 0], [0, 0, 1]];
switch (_mode) do {
    case "zone": { [_pos, _radius, _strength, _n, _fx, _delay] call comspec_fb_fnc_serverArea };
    case "drill": { ["COMSPEC_FB_Ammo_Drill", _pos, _vdu, [0, 0, 0], objNull, sideUnknown, _n, _delay] call comspec_fb_fnc_serverDetonate };
    default { [format ["COMSPEC_FB_Ammo_%1", _n], _pos, _vdu, [0, 0, 0], objNull, sideUnknown, _n, _delay] call comspec_fb_fnc_serverDetonate };
};
["module", format ["%1 x%2 délai %3 s", _mode, _n, _delay]] call comspec_fb_fnc_log;
