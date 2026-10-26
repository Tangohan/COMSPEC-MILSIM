/*
    Cap d'un point à un autre, vrai et magnétique. Les cartes Arma n'ont pas de déclinaison : le réglage serveur
    « Déclinaison magnétique » (comspec_atak_native_mag_decl, degrés, positif = est, 0 par défaut) la simule
    (magnétique = vrai − déclinaison est), pour le 9-LINE du JTAC et l'outil carte IP → CIBLE.
    Params : [de, vers] (positions ou objets). Renvoie [cap vrai, cap magnétique], en degrés entiers 0-359.
*/
params ["_from", "_to"];
private _true = round (((_from getDir _to) + 360) mod 360);
private _decl = missionNamespace getVariable ["comspec_atak_native_mag_decl", 0];
if !(_decl isEqualType 0) then { _decl = 0; };
[_true mod 360, (round (((_true - _decl) mod 360) + 360)) mod 360]
