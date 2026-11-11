/*
    comspec_fb_fnc_playBang : joue localement (playSound3D local) un son de la table COMSPEC_FB_Sons (CfgSons.hpp).
    Chaque appel crée une source indépendante : les bangs successifs se superposent sans se couper.
    Tirage aléatoire sans répéter deux fois de suite le même fichier, hauteur +/- pitchVar, volume +/- volumeVarDb.

    [_entree, _posASL, _retard, _interieur, _gain, _portee] call comspec_fb_fnc_playBang
      _retard    : secondes avant la lecture (vitesse du son : distance / 343)
      _interieur : true = son « intérieur » du moteur (étouffé)
      _gain      : multiplicateur de volume (réglage « Volume des flashbangs », limiteur des bangs en série, atténuations)
      _portee    : portée d'audibilité (m, réglage « Portée du son ») ; -1 = distance de la table
    playSound3D plafonne le volume à 5 : au-delà, le même fichier est joué 2 ou 3 fois au même instant et au même
    endroit (sources en phase : +6 dB pour deux), pour que le bang reste écrasant à courte portée.
*/
params [["_entry", "", [""]], ["_posASL", [0, 0, 0], [[]], 3], ["_delay", 0, [0]], ["_inside", false, [false]], ["_gain", 1, [0]], ["_range", -1, [0]]];
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
private _copies = ((ceil (_vol / 5)) max 1) min 3;
private _dist = getNumber (_cfg >> "distance");
if (_range > 0) then { _dist = _dist min _range };
private _args = ["z\comspec_flashbang\addons\main\sounds\" + (_files select _i), objNull, _inside, _posASL, (_vol / _copies) min 5, _pitch, _dist, 0, true];
["son", format ["%1 : %2, volume %3 (%4 lecture(s)), portée %8 m, hauteur %5, retard %6 s%7", _entry, _files select _i, _vol toFixed 2, _copies,
    _pitch toFixed 3, _delay toFixed 2, ["", ", intérieur"] select _inside, round _dist]] call comspec_fb_fnc_log;

if (_delay > 0.02) then {
    [{ params ["_a", "_c"]; for "_k" from 1 to _c do { playSound3D _a } }, [_args, _copies], _delay] call CBA_fnc_waitAndExecute;
} else {
    for "_k" from 1 to _copies do { playSound3D _args };
};
