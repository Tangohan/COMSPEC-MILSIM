/*
    comspec_fb_fnc_moduleEden : module « COMSPEC Flashbang » (Eden, et Zeus vanilla avec les valeurs par défaut).
    Mode grenade : engin posé à la position du module, N détonations. Mode zone : effet sur toutes les unités dans
    le rayon, sans grenade. Mode drill : grenade d'entraînement (N pops, son au choix). Déclenchement : début de mission
    + délai, ou activation d'un déclencheur synchronisé.
    Mèche (tous les modes) : ajoutée au délai. « Par défaut » = aucune mèche (comportement d'avant : missions existantes
    inchangées) ; 3 s / 8 s : grenade à délai (engin marqué 3 s / 8 s en mode grenade et DRILL) ; personnalisée 0,5-20 s.
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
private _str = {
    params ["_name", "_def"];
    private _v = _logic getVariable [_name, _def];
    [_def, _v] select (_v isEqualType "")
};
private _mode = ["Mode", "grenade"] call _str;
private _n = (round (["Bursts", 3] call _num) max 1) min 9;
private _delay = (["Delay", 0] call _num) max 0;
private _radius = ["Radius", 10] call _num;
private _strength = ["Strength", 0.8] call _num;
private _fx = _logic getVariable ["ShowFx", true];
if (_fx isEqualType 0) then { _fx = _fx > 0 };
if !(_fx isEqualType true) then { _fx = true };
private _fuseMode = ["FuseMode", "default"] call _str;
private _fuse = switch (_fuseMode) do {
    case "3": { 3 };
    case "8": { 8 };
    case "custom": { ((["FuseCustom", 1.5] call _num) max 0.5) min 20 };
    default { 0 };
};
// Suffixe de la variante à délai (engin au marquage 3 s / 8 s) ; mèche personnalisée : engin standard.
private _sfx = ["", format ["_D%1", _fuseMode]] select (_fuseMode in ["3", "8"]);
private _snd = ["DrillSound", "setting"] call _str;
if (_snd == "setting") then { _snd = "" };
private _lead = _delay + _fuse;
private _pos = (getPosASL _logic) vectorAdd [0, 0, 0.05];
private _vdu = [[0, 1, 0], [0, 0, 1]];
switch (_mode) do {
    case "zone": { [_pos, _radius, _strength, _n, _fx, _lead] call comspec_fb_fnc_serverArea };
    case "drill": { ["COMSPEC_FB_Ammo_Drill" + _sfx, _pos, _vdu, [0, 0, 0], objNull, sideUnknown, _n, _lead, -1, _snd] call comspec_fb_fnc_serverDetonate };
    default { [format ["COMSPEC_FB_Ammo_%1%2", _n, _sfx], _pos, _vdu, [0, 0, 0], objNull, sideUnknown, _n, _lead] call comspec_fb_fnc_serverDetonate };
};
["module", format ["%1 x%2 délai %3 s + mèche %4 s (%5) son DRILL %6", _mode, _n, _delay, _fuse, _fuseMode, [_snd, "réglage"] select (_snd == "")]] call comspec_fb_fnc_log;
