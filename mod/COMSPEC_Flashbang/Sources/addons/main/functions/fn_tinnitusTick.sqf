/*
    comspec_fb_fnc_tinnitusTick : planificateur unique de l'acouphène du joueur local (fn_tinnitus), toutes les 0,02 s.
    Joue le segment suivant 30 ms avant la fin du segment en cours (fondus de 30 ms cuits dans les fichiers : fondu
    croisé), au volume du niveau à cet instant. Jamais deux attaques, jamais deux segments de boucle en même temps
    (hors les 30 ms de fondu). Fin : segment de décroissance joué une fois, puis le planificateur s'arrête.
*/
params ["", "_handle"];
private _st = comspec_fb_state;
private _t = _st getOrDefault ["tinn2", createHashMap];
if (count _t == 0) exitWith { [_handle] call CBA_fnc_removePerFrameHandler };
private _now = CBA_missionTime;
if (_now < (_t get "segEnd") - 0.03) exitWith {};

private _cfg = configFile >> "COMSPEC_FB_Sons" >> (["Acouphene", "AcoupheneSynth"] select ((missionNamespace getVariable ["comspec_fb_tinnitusSound", 0]) == 1));
private _lvl = [_t, _now] call comspec_fb_fnc_tinnitusLevel;
// Volume : niveau x réglage « Volume de l'acouphène » (250 % par défaut) x 1,5 (acouphène présent, version 1.0.0).
private _vol = ((_lvl * 1.5 * (missionNamespace getVariable ["comspec_fb_tinnitusVolume", 2.5])) min 5) max 0;
private _state = _t get "state";
private _play = {
    params ["_cls", "_len"];
    private _id = playSoundUI [getText (_cfg >> _cls), _vol, 1, false];
    private _ids = (_t get "ids") select { _x >= 0 };
    if (count _ids > 2) then { _ids deleteAt 0 };
    _ids pushBack _id;
    _t set ["ids", _ids];
    _t set ["segEnd", _now + _len];
};
switch (true) do {
    case (_state == "attaque"): {
        ["attaque", getNumber (_cfg >> "dureeAttaque")] call _play;
        _t set ["state", "boucle"];
    };
    case (_state == "boucle" && {_now < (_t get "end")} && {_lvl > 0.02}): {
        ["boucle", getNumber (_cfg >> "dureeBoucle")] call _play;
    };
    case (_state == "boucle"): {
        ["fin", getNumber (_cfg >> "dureeFin")] call _play;
        _t set ["state", "fin"];
        ["acouphène", format ["fin (décroissance de %1 s), niveau=%2", getNumber (_cfg >> "dureeFin"), _lvl toFixed 2]] call comspec_fb_fnc_log;
    };
    default {
        // Décroissance jouée : terminé.
        _t set ["state", "fini"];
        [_handle] call CBA_fnc_removePerFrameHandler;
    };
};
