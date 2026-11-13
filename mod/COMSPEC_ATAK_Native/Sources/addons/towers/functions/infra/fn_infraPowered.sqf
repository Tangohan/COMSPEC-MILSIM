/*
    Un lieu est-il alimenté par l'infrastructure réseau ? (API de mission, fn_infra « powered »)
    Vrai s'il n'y a aucune baie serveur / serveur tour COMSPEC dans le rayon (lieu indépendant), ou si l'un d'eux tourne.
    Params : [position ou objet, rayon en m (80)]
    Exemple : if !([getPosATL ecran1] call comspec_atak_native_fnc_infraPowered) then { ... };
*/
params [["_pos", [0, 0, 0], [[], objNull]], ["_radius", 80, [0]]];
["powered", [_pos, _radius]] call comspec_atak_native_fnc_infra
