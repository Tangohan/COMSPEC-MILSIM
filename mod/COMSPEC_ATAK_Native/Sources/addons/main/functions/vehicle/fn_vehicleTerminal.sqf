/*
    Terminal de bord : l'unité a-t-elle un ATAK fourni par un véhicule (sans téléphone porté, alimenté par le bord) ?
    Renvoie "" (non) ou le type :
      "air"     : pilote, copilote ou tourelle d'un aéronef (tablette de vol, réglage comspec_atak_native_aircrew)
      "vehicle" : conducteur, tireur, chef de bord ou tourelle d'un véhicule terrestre ou d'un bateau équipé d'une
                  tablette durcie (réglage comspec_atak_native_vehicle_terminal : "all" tous, "kit" ceux où le kit
                  COMSPEC_ATAK_VehicleKit est installé, "off" aucun)
      "cp"      : à bord (toute place) ou à moins de 8 m d'un véhicule de son camp dont le poste de commandement
                  est déployé (fn_vehicleGateway)
    Params : [unité (player)]
*/
params [["_u", player]];
if (isNull _u || {!alive _u}) exitWith { "" };
private _v = objectParent _u;
private _crew = {
    params ["_v", "_u"];
    driver _v isEqualTo _u || {gunner _v isEqualTo _u} || {commander _v isEqualTo _u} || {((assignedVehicleRole _u) param [0, ""]) isEqualTo "Turret"}
};
// Poste de commandement déployé : à bord ou autour.
private _cpOf = { params ["_o"]; !isNull _o && {alive _o} && {((_o getVariable ["COMSPEC_ATAK_Gateway", []]) param [0, ""]) isEqualTo "cp"} && {((_o getVariable ["COMSPEC_ATAK_Gateway", []]) param [1, ""]) isEqualTo str side group _u} };
if (!isNull _v && {[_v] call _cpOf}) exitWith { "cp" };
if (isNull _v && {((missionNamespace getVariable ["COMSPEC_ATAK_Gateways", []]) findIf { [_x] call _cpOf && {(_x distance _u) < 8} }) >= 0}) exitWith { "cp" };
if (isNull _v || {!alive _v} || {unitIsUAV _v}) exitWith { "" };
if (_v isKindOf "Air") exitWith {
    ["", "air"] select ((missionNamespace getVariable ["comspec_atak_native_aircrew", true]) && {[_v, _u] call _crew})
};
if (_v isKindOf "StaticWeapon" || {!(_v isKindOf "LandVehicle" || {_v isKindOf "Ship"})}) exitWith { "" };
private _mode = missionNamespace getVariable ["comspec_atak_native_vehicle_terminal", "all"];
private _fitted = switch (_mode) do { case "all": { true }; case "kit": { _v getVariable ["COMSPEC_ATAK_VehTerm", false] }; default { false }; };
["", "vehicle"] select (_fitted && {[_v, _u] call _crew})
