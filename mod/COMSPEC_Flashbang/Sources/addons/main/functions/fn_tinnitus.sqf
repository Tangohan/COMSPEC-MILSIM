/*
    comspec_fb_fnc_tinnitus : acouphène du joueur local, UN seul acouphène continu par train de flashs.

    Un planificateur par joueur (fn_tinnitusTick, toutes les 0,02 s) enchaîne des segments sonores :
      attaque (0,5 s, une seule fois au premier flash) -> boucle de 3 s (fichier sans clic, fondus de 30 ms aux bouts,
      la suivante part 30 ms avant la fin de la précédente : fondu croisé) tant que l'heure de fin n'est pas atteinte
      -> fin (décroissance de 6 s, une seule fois).
    Un flash suivant ne relance JAMAIS l'attaque : il reporte l'heure de fin et remonte le niveau cible. Le volume d'un
    son en cours ne pouvant pas changer, le niveau change à la frontière du segment suivant (volume de la boucle suivante).
    Niveau : monte au niveau du flash le plus fort, puis retombe (vite les premières secondes, puis lentement) ; durée
    audible = réglage « Durée max de l'acouphène » x audition^0,6, comptée depuis le DERNIER flash.
    [_audition] call comspec_fb_fnc_tinnitus
*/
params [["_a", 0, [0]]];
if (!(missionNamespace getVariable ["comspec_fb_tinnitus", true]) || {_a < 0.05}) exitWith {};
private _st = comspec_fb_state;
private _now = CBA_missionTime;
private _t = _st getOrDefault ["tinn2", createHashMap];
private _dur = (((missionNamespace getVariable ["comspec_fb_tinnitusMax", 45]) min 58) max 10) * ((_a min 1) ^ 0.6);
private _end = _now + (_dur max 4);

if (count _t == 0 || {_t getOrDefault ["state", ""] == "fini"}) exitWith {
    _t = createHashMapFromArray [["state", "attaque"], ["peak", _a min 1], ["hit", _now], ["end", _end], ["segEnd", 0],
        ["ids", []], ["lastLog", _now]];
    _st set ["tinn2", _t];
    _t set ["pfh", [comspec_fb_fnc_tinnitusTick, 0.02, []] call CBA_fnc_addPerFrameHandler];
    ["acouphène", format ["démarrage niveau=%1 fin prévue à t=%2", (_a min 1) toFixed 2, _end toFixed 1]] call comspec_fb_fnc_log;
};
// Train en cours : on prolonge et on remonte le niveau, sans rien relancer.
private _cur = [_t, _now] call comspec_fb_fnc_tinnitusLevel;
_t set ["peak", (((_cur max _a) + 0.15 * (_cur min _a)) min 1)];
_t set ["hit", _now];
_t set ["end", (_t get "end") max _end];
if (_t get "state" == "fin") then { _t set ["state", "boucle"] };
if (_now - (_t getOrDefault ["lastLog", 0]) > 1) then {
    _t set ["lastLog", _now];
    ["acouphène", format ["prolongé jusqu'à t=%1 niveau=%2", (_t get "end") toFixed 1, (_t get "peak") toFixed 2]] call comspec_fb_fnc_log;
};
