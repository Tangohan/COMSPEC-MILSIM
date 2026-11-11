/*
    comspec_fb_fnc_tinnitus : acouphène du joueur local, UNE seule lecture à la fois.

    Le fichier (60 s, table COMSPEC_FB_Sons >> Acouphene) contient déjà la décroissance réaliste :
    a(t) = 0,6 e^(-t/2,5) + 0,4 e^(-t/20) (aigu fort qui retombe vite, puis traîne lentement). On le joue avec
    playSoundUI et un « offset » : plus l'exposition est forte, plus on démarre tôt dans le fichier. Un nouveau flash
    plus fort relance la lecture plus tôt (le bang masque la reprise) ; un flash plus faible ne change rien.
    Son d'interface (non atténué par l'ouïe étouffée), volume lié à l'intensité. Durée audible bornée par le réglage.
    [_audition] call comspec_fb_fnc_tinnitus
*/
params [["_a", 0, [0]]];
if (!(missionNamespace getVariable ["comspec_fb_tinnitus", true]) || {_a < 0.05}) exitWith {};
private _st = comspec_fb_state;
private _entry = ["Acouphene", "AcoupheneSynth"] select ((missionNamespace getVariable ["comspec_fb_tinnitusSound", 0]) == 1);
private _cfg = configFile >> "COMSPEC_FB_Sons" >> _entry;
(getArray (_cfg >> "courbe")) params [["_a1", 0.6], ["_t1", 2.5], ["_a2", 0.4], ["_t2", 20]];
private _amp = { _a1 * exp (-_this / _t1) + _a2 * exp (-_this / _t2) };

// Niveau actuel (position dans le fichier) et niveau visé.
private _now = CBA_missionTime;
(_st getOrDefault ["tinn", [-1, 50, 0, 0]]) params ["_id", "_o0", "_tStart", "_g0"];
private _p = _o0 + (_now - _tStart);
private _cur = if (_p >= 48) then { 0 } else { (_p call _amp) * _g0 };
private _target = (((_cur max _a) + 0.2 * (_cur min _a)) min 1);
if (_target < _cur + 0.05) exitWith {};

// Offset tel que a(offset) = niveau visé (dichotomie), borné par la durée maximale réglée.
private _durMax = ((missionNamespace getVariable ["comspec_fb_tinnitusMax", 45]) min 58) max 10;
private _lo = 0;
private _hi = 47;
for "_k" from 1 to 18 do {
    private _mid = (_lo + _hi) / 2;
    if ((_mid call _amp) > _target) then { _lo = _mid } else { _hi = _mid };
};
private _off = (_lo max (50 - _durMax)) min 47;
// Gain : compense un départ plus tardif dans le fichier (le fichier part à -6 dBFS crête) ; réglage « Volume de
// l'acouphène » (150 % par défaut).
private _gain = (_target / ((_off call _amp) max 0.01)) min 2.5;
if (_id >= 0) then { stopSound _id };
private _vol = (_gain * (missionNamespace getVariable ["comspec_fb_tinnitusVolume", 1.5])) min 5;
private _newId = playSoundUI [getText (_cfg >> "cfgSound"), _vol, 1, false, _off];
_st set ["tinn", [_newId, _off, _now, _gain]];
["acouphène", format ["niveau %1 -> %2, offset %3 s, gain %4, volume %5", _cur toFixed 2, _target toFixed 2, _off toFixed 1, _gain toFixed 2, _vol toFixed 2]] call comspec_fb_fnc_log;
