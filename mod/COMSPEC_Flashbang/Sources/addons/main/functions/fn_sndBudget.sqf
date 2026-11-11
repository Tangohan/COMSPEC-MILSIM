/*
    comspec_fb_fnc_sndBudget : fonction PURE : combien de lectures 3D superposées pour ce bang ?
    Chaque BANG reste audible : le budget réduit le nombre de lectures superposées (qui ne servent qu'au volume), il
    ne supprime un bang que si 28 lectures sont déjà en cours sur ce poste (cas extrême : plusieurs GANG BANG à la fois).
      moins de 12 lectures en cours : jusqu'à _maxCopies (4 ; 2 pour les bangs d'une rafale)
      12 à 19                        : 2 au plus
      20 à 27                        : 1
      28 et plus                     : 0 (bang fusionné avec ceux en cours, compté dans le journal)
    [_enCours, _voulues, _maxCopies] call comspec_fb_fnc_sndBudget -> 0..4
*/
params [["_busy", 0, [0]], ["_wanted", 1, [0]], ["_maxCopies", 4, [0]]];
if (_busy >= 28) exitWith { 0 };
private _c = ((_wanted max 1) min _maxCopies) min 4;
if (_busy >= 12) then { _c = _c min 2 };
if (_busy >= 20) then { _c = 1 };
_c
