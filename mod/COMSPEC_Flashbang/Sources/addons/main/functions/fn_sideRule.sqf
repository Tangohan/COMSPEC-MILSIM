/*
    comspec_fb_fnc_sideRule : règle d'effet d'une unité (joueur ou IA) pour UNE détonation, d'après son camp COURANT
    et le lanceur. Lue à chaque détonation : un joueur qui change de camp, ou un Zeus qui change le réglage en cours de
    partie, est traité selon la valeur du moment.

    Niveaux (du moins au plus protecteur) :
      0 normal              tout s'applique
      1 réduit              éblouissement et durées x coefficient (réglage « Coefficient du mode Réduit », 0,35) ;
                            l'audition reste entière
      2 aucun effet visuel  ni voile, ni tache, ni flou ; le bang assourdit encore, à moitié (acouphène réduit)
      3 immunisé            aucun effet (ni vision, ni audition, ni visée) : il ne reste que la lumière et le son de la
                            grenade dans le monde
    Sources de la règle, LE PLUS PROTECTEUR GAGNE :
      - camp de l'unité : réglages « Effet sur BLUFOR / OPFOR / Indépendants / Civils » (0 à 3) ;
      - lanceur lui-même : « Mes propres grenades m'affectent » (oui 0, réduit 1, non 3) ;
      - même camp que le lanceur (sauf lui) : « Les grenades de mon camp m'affectent » (oui 0, réduit 1, non 3) ;
      - ancien réglage de mission comspec_fb_friendly = false : camp du lanceur immunisé (lanceur compris).
    [_campUnite, _campLanceur, _estLanceur] call comspec_fb_fnc_sideRule -> [_niveau, _kVision, _kDuree, _kAudition, _nom]
*/
params [["_unitSide", sideUnknown, [sideUnknown]], ["_throwSide", sideUnknown, [sideUnknown]], ["_isThrower", false, [false]]];
private _i = [west, east, independent, civilian] find _unitSide;
private _level = if (_i < 0) then { 0 } else {
    missionNamespace getVariable [["comspec_fb_sideWest", "comspec_fb_sideEast", "comspec_fb_sideIndep", "comspec_fb_sideCiv"] select _i, 0]
};
private _sameSide = _throwSide isNotEqualTo sideUnknown && {_unitSide isEqualTo _throwSide};
if (_isThrower) then {
    _level = _level max ([0, 1, 3] select (((missionNamespace getVariable ["comspec_fb_ownGrenade", 0]) max 0) min 2));
};
if (_sameSide && {!_isThrower}) then {
    _level = _level max ([0, 1, 3] select (((missionNamespace getVariable ["comspec_fb_sideGrenade", 0]) max 0) min 2));
};
if (_sameSide && {!(missionNamespace getVariable ["comspec_fb_friendly", true])}) then { _level = 3 };
_level = ((round _level) max 0) min 3;
private _k = ((missionNamespace getVariable ["comspec_fb_reducedCoef", 0.35]) max 0) min 1;
[_level] + ([[1, 1, 1], [_k, _k, 1], [0, 1, 0.5], [0, 0, 0]] select _level) + [["normal", "réduit", "aucun effet visuel", "immunisé"] select _level]
