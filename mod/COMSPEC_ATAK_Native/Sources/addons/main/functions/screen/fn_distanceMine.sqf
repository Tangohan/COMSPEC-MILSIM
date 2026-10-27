/*
    Compteurs de cette mission montrés au joueur (app Distances, carnet fn_distanceLog) : part comptée par le serveur
    (COMSPEC_ATAK_DistMe, poussée toutes les 15 s) + part comptée par ce téléphone quand le serveur n'est pas relié à
    Athena (COMSPEC_ATAK_DistMeSelf, relais de fn_distanceServer). Les deux ne comptent jamais la même période : la somme
    reste juste quand le relais passe de l'un à l'autre en cours de mission.
    Retour : [[[compteur, valeur]…], [[classe, nom, heli|plane, secondes, mètres]…]]
*/
private _c = createHashMap;
private _p = createHashMap;
{
    _x params [["_pairs", []], ["_pilot", []]];
    { _x params ["_k", "_v"]; _c set [_k, (_c getOrDefault [_k, 0]) + _v]; } forEach _pairs;
    {
        _x params ["_cls", "_label", "_pk", "_ps", "_pm"];
        (_p getOrDefault [_cls, [_label, _pk, 0, 0]]) params ["", "", "_s0", "_m0"];
        _p set [_cls, [_label, _pk, _s0 + _ps, _m0 + _pm]];
    } forEach _pilot;
} forEach [player getVariable ["COMSPEC_ATAK_DistMe", []], player getVariable ["COMSPEC_ATAK_DistMeSelf", []]];
[(keys _c) apply { [_x, _c get _x] }, (keys _p) apply { [_x] + (_p get _x) }]
