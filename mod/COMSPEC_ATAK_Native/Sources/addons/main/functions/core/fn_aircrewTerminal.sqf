/*
    Terminal de bord des aéronefs : vrai si l'unité est pilote, copilote ou à un poste de tourelle d'un aéronef
    et que le serveur l'autorise (réglage « ATAK pour les équipages d'aéronef », activé par défaut).
    Le téléphone s'ouvre alors même sans téléphone porté (tenue de pilote), téléphone vide ou cassé :
    l'ATAK tourne sur la tablette de vol de l'appareil, alimentée par le bord. Params : [unité (player)]
*/
params [["_u", player]];
if (isNull _u || {!alive _u}) exitWith { false };
if !(missionNamespace getVariable ["comspec_atak_native_aircrew", true]) exitWith { false };
private _v = objectParent _u;
if (isNull _v || {!(_v isKindOf "Air")} || {!alive _v}) exitWith { false };
// Drone piloté à distance : pas de cockpit, c'est le terminal UAV.
if (unitIsUAV _v) exitWith { false };
driver _v isEqualTo _u || {gunner _v isEqualTo _u} || {commander _v isEqualTo _u} || {((assignedVehicleRole _u) param [0, ""]) isEqualTo "Turret"}
