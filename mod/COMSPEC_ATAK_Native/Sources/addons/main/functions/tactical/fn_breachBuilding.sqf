/*
    Porte au contact : la porte la plus proche à moins de 2,5 m du joueur (point mémoire Door_N_trigger),
    sur le bâtiment qu'il touche. Rien à distance : le Breacher n'a d'informations que sur ce qu'il a devant lui.
    Renvoie [bâtiment, numéro de porte, position] ou [].
*/
private _best = [];
private _bd = 2.5;
{
    private _b = _x;
    private _n = getNumber (configOf _b >> "numberOfDoors");
    for "_i" from 1 to _n do {
        private _mp = _b selectionPosition [format ["Door_%1_trigger", _i], "Memory"];
        if (_mp isNotEqualTo [0, 0, 0]) then {
            private _p = _b modelToWorld _mp;
            private _d = player distance _p;
            if (_d < _bd) then { _bd = _d; _best = [_b, _i, _p]; };
        };
    };
} forEach (nearestObjects [player, ["House"], 25]);
_best
