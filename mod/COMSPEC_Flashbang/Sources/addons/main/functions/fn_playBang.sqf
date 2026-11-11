/*
    comspec_fb_fnc_playBang : joue localement (playSound3D local) un son de la table COMSPEC_FB_Sons (CfgSons.hpp).
    Chaque appel crée une source indépendante : les bangs successifs se superposent sans se couper.
    Tirage aléatoire sans répéter deux fois de suite le même fichier, hauteur +/- pitchVar, volume +/- volumeVarDb.

    [_entree, _posASL, _retard, _interieur, _gain] call comspec_fb_fnc_playBang
      _retard    : secondes avant la lecture (vitesse du son : distance / 343)
      _interieur : true = son « intérieur » du moteur (étouffé)
      _gain      : multiplicateur de volume (réglage « Volume du bang » et atténuations)
*/
params [["_entry", "", [""]], ["_posASL", [0, 0, 0], [[]], 3], ["_delay", 0, [0]], ["_inside", false, [false]], ["_gain", 1, [0]]];
private _cfg = configFile >> "COMSPEC_FB_Sons" >> _entry;
private _files = getArray (_cfg >> "fichiers");
if (_files isEqualTo [] || {_gain <= 0}) exitWith {};

private _last = comspec_fb_lastSound getOrDefault [_entry, -1];
private _i = floor random count _files;
if (count _files > 1 && {_i == _last}) then { _i = (_i + 1 + floor random (count _files - 1)) mod count _files };
comspec_fb_lastSound set [_entry, _i];

private _pv = getNumber (_cfg >> "pitchVar");
private _dbv = getNumber (_cfg >> "volumeVarDb");
private _vol = getNumber (_cfg >> "volume") * _gain * (10 ^ (((random 2) - 1) * _dbv / 20));
private _pitch = 1 + ((random 2) - 1) * _pv;
private _args = ["z\comspec_flashbang\addons\main\sounds\" + (_files select _i), objNull, _inside, _posASL, _vol min 5, _pitch, getNumber (_cfg >> "distance"), 0, true];

if (_delay > 0.02) then {
    [{ playSound3D _this }, _args, _delay] call CBA_fnc_waitAndExecute;
} else {
    playSound3D _args;
};
