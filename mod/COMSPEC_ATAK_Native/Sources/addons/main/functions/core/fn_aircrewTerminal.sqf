/*
    Terminal de bord : vrai si l'unité a l'ATAK par un véhicule (fn_vehicleTerminal) : tablette de vol d'un aéronef,
    tablette durcie d'un véhicule terrestre ou d'un bateau, poste de commandement mobile déployé.
    Le téléphone s'ouvre alors même sans téléphone porté, téléphone vide ou cassé : l'ATAK tourne sur le terminal
    du véhicule, alimenté par le bord. Params : [unité (player)]
*/
params [["_u", player]];
([_u] call comspec_atak_native_fnc_vehicleTerminal) isNotEqualTo ""
