/*
    comspec_fb_fnc_playBang : joue localement un son de la table COMSPEC_FB_Sons (CfgSons.hpp).
    Chaque appel crée une source indépendante : les bangs successifs se superposent sans se couper.
    Tirage aléatoire sans répéter deux fois de suite le même fichier, hauteur +/- pitchVar, volume +/- volumeVarDb
    (proportionnels au réglage « Variation aléatoire »).

    [_entree, _posASL, _retard, _interieur, _gain, _portee, _distance] call comspec_fb_fnc_playBang
      _retard    : secondes avant la lecture (vitesse du son : distance / 343)
      _interieur : true = son « intérieur » du moteur (étouffé)
      _gain      : multiplicateur de volume (réglage « Volume des flashbangs », limiteur des bangs en série, atténuations)
      _portee    : portée d'audibilité (m, réglage « Portée du son ») ; -1 = distance de la table
      _distance  : distance de l'auditeur (m) ; -1 = inconnue (pas de couche proche)

    Niveau (version 1.0.0) :
      - couche 3D (playSound3D) : plafonnée à 5 par lecture ; au-delà, 2 à 4 lectures identiques au même instant ;
      - couche proche 2D (playSoundUI, entrée ui[] de la table) sous 25 m : plein volume jusqu'à 8 m, puis décroissance
        douce ; elle compense l'atténuation 3D du moteur, cause principale d'un bang « pas assez fort » de près ;
      - « ducking » : les autres sons du jeu baissent 150 ms au pic du bang (contraste) ;
      - budget : au plus 12 lectures simultanées (au-delà une seule lecture, au-delà de 16 aucune : le bang en cours
        couvre déjà le suivant) ; volumes effectifs 3D / 2D écrits dans le journal (lignes « son », détail).
*/
params [["_entry", "", [""]], ["_posASL", [0, 0, 0], [[]], 3], ["_delay", 0, [0]], ["_inside", false, [false]], ["_gain", 1, [0]],
    ["_range", -1, [0]], ["_dc", -1, [0]]];
private _cfg = configFile >> "COMSPEC_FB_Sons" >> _entry;
private _files = getArray (_cfg >> "fichiers");
if (_files isEqualTo [] || {_gain <= 0}) exitWith {};

// Budget de lectures simultanées (fins estimées).
private _now = diag_tickTime;
if (isNil "comspec_fb_sndEnds") then { comspec_fb_sndEnds = [] };
comspec_fb_sndEnds = comspec_fb_sndEnds select { _x > _now };
private _busy = count comspec_fb_sndEnds;
if (_busy >= 16) exitWith {
    ["son", format ["%1 : budget atteint (%2 lectures en cours), lecture fusionnée avec les précédentes", _entry, _busy]] call comspec_fb_fnc_log;
};

private _last = comspec_fb_lastSound getOrDefault [_entry, -1];
private _i = floor random count _files;
if (count _files > 1 && {_i == _last}) then { _i = (_i + 1 + floor random (count _files - 1)) mod count _files };
comspec_fb_lastSound set [_entry, _i];

private _vr = (missionNamespace getVariable ["comspec_fb_variation", 0.6]) / 0.6;
private _pv = getNumber (_cfg >> "pitchVar") * _vr;
private _dbv = getNumber (_cfg >> "volumeVarDb") * _vr;
private _vol = getNumber (_cfg >> "volume") * _gain * (10 ^ (((random 2) - 1) * _dbv / 20));
private _pitch = 1 + ((random 2) - 1) * _pv;
private _copies = ((ceil (_vol / 5)) max 1) min ([4, 1] select (_busy >= 12));
private _dist = getNumber (_cfg >> "distance");
if (_range > 0) then { _dist = _dist min _range };
private _args = ["z\comspec_flashbang\addons\main\sounds\" + (_files select _i), objNull, _inside, _posASL, (_vol / _copies) min 5, _pitch, _dist, 0, true];

// Couche proche 2D : plein volume jusqu'à 8 m, nulle à 25 m (jamais derrière un mur : le son y est déjà étouffé).
private _ui = getArray (_cfg >> "ui") param [_i, ""];
private _v2 = 0;
if (_ui != "" && {!_inside} && {_dc >= 0} && {_dc < 25}) then {
    _v2 = (_vol * 0.5 * linearConversion [8, 25, _dc, 1, 0, true]) min 5;
};
// Durée comptée dans le budget : 2,5 s pour un bang ; durée du fichier (au plus 4 s) pour un son de BANG spécial.
private _dur = if (isNumber (_cfg >> "duree")) then { (getNumber (_cfg >> "duree")) min 4 } else { 2.5 };
for "_k" from 1 to (_copies + parseNumber (_v2 > 0.05)) do { comspec_fb_sndEnds pushBack (_now + _delay + _dur) };
["son", format ["%1 : %2, volume 3D %3 (%4 lecture(s)), couche proche 2D %5, portée %6 m, hauteur %7, retard %8 s%9", _entry,
    _files select _i, _vol toFixed 2, _copies, _v2 toFixed 2, round _dist, _pitch toFixed 3, _delay toFixed 3,
    ["", ", intérieur"] select _inside]] call comspec_fb_fnc_log;

private _play = {
    params ["_a", "_c", "_ui", "_v2", "_pitch"];
    for "_k" from 1 to _c do { playSound3D _a };
    if (_v2 > 0.05) then {
        playSoundUI [_ui, _v2, _pitch, false];
        // Ducking : les autres sons du jeu baissent 150 ms au pic du bang (le bang paraît plus fort par contraste).
        if (!isNil "ace_common_fnc_setHearingCapability") then {
            ["comspec_fb_duck", 0.35, true, 0] call ace_common_fnc_setHearingCapability;
            [{ ["comspec_fb_duck", 1, false, 0.2] call ace_common_fnc_setHearingCapability }, [], 0.15] call CBA_fnc_waitAndExecute;
        } else {
            private _back = soundVolume;
            0.02 fadeSound (_back * 0.35);
            [{
                // Retour au volume de l'ouïe étouffée en cours s'il y en a une, sinon au volume d'avant.
                private _st = comspec_fb_state;
                private _target = if ("orig" in _st) then { ((_st get "orig") getOrDefault ["sound", 1]) * (_st getOrDefault ["lastVol", 1]) } else { _this };
                0.2 fadeSound _target;
            }, _back, 0.15] call CBA_fnc_waitAndExecute;
        };
    };
};
if (_delay > 0.02) then {
    [_play, [_args, _copies, _ui, _v2, _pitch], _delay] call CBA_fnc_waitAndExecute;
} else {
    [_args, _copies, _ui, _v2, _pitch] call _play;
};
